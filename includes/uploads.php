<?php
/**
 * Safe file uploads — the ONE place the rules live.
 *
 * ⚠️ WHY THIS EXISTS. Change management accepted an upload with no validation
 * at all, kept the caller's filename, and wrote it into a web-reachable folder.
 * A file called `probe.php` could be uploaded and then fetched over HTTP, and it
 * EXECUTED. That is remote code execution by anyone who could attach a file to a
 * change. It was found while building form file uploads, where the same pattern
 * would have handed the hole to self-service customers rather than analysts.
 *
 * Three defences, and the point is that NO ONE of them is trusted alone:
 *
 *   1. an extension AND mime whitelist — a renamed `.php` fails the mime check,
 *      and a file whose bytes look like a PNG still fails if it is named .php;
 *   2. a RANDOM stored filename with an extension drawn from our own whitelist,
 *      never from the caller's string. The original name is kept in the database
 *      for display only, so nothing user-controlled reaches the filesystem;
 *   3. an .htaccess that disables execution in the upload directory, so even a
 *      future endpoint that forgets 1 and 2 does not become an RCE.
 *
 * 🔑 Files are still served through an authorising PHP endpoint. The directory
 * protection is the net under the tightrope, not the tightrope.
 */

/** Extension => the mime types that extension is allowed to actually be. */
const UPLOAD_TYPES_DOCUMENT = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'xls'  => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'ppt'  => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'txt'  => ['text/plain'],
    'csv'  => ['text/plain', 'text/csv', 'application/csv'],
    'log'  => ['text/plain'],
    'md'   => ['text/plain', 'text/markdown'],
    'rtf'  => ['text/rtf', 'application/rtf'],
    'zip'  => ['application/zip'],
    'json' => ['application/json', 'text/plain'],
    'xml'  => ['text/xml', 'application/xml'],
];

const UPLOAD_TYPES_IMAGE = [
    'png'  => ['image/png'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
    // ⚠️ SVG is deliberately ABSENT. It is XML, it can carry <script>, and a
    // browser will run it if the file is ever served inline. Branding allows it
    // because an administrator uploads the logo; nowhere a customer can reach
    // should take one.
];

/** The everyday set: what somebody attaches to a change, a ticket or a form. */
const UPLOAD_TYPES_ATTACHMENT = UPLOAD_TYPES_DOCUMENT + UPLOAD_TYPES_IMAGE;

const UPLOAD_MAX_BYTES = 10485760;   // 10 MB, matching the issue-tracker cap

/**
 * Validate one $_FILES entry and move it somewhere safe.
 *
 * @param array  $file     one entry from $_FILES
 * @param string $destDir  absolute directory; created (and protected) if absent
 * @param array  $allowed  ext => [mime, …]; defaults to the everyday set
 * @param int    $maxBytes
 * @return array ['stored_name'=>…, 'original_name'=>…, 'size'=>int, 'mime'=>string, 'path'=>absolute]
 * @throws Exception with a message safe to show a user
 */
function uploadStoreFile(array $file, string $destDir, array $allowed = UPLOAD_TYPES_ATTACHMENT, int $maxBytes = UPLOAD_MAX_BYTES): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(uploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE));
    }
    // ⚠️ Confirms the file really came from a POST upload rather than being a
    // path an attacker got us to pass in.
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('That was not a valid upload.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0)        throw new Exception('That file is empty.');
    if ($size > $maxBytes) throw new Exception('That file is too large (maximum ' . uploadFormatBytes($maxBytes) . ').');

    $original = uploadCleanName((string)($file['name'] ?? ''));
    $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    // ⚠️ Gate 1: the extension must be one WE named. Note this rejects a file
    // with no extension too, which is correct — we would have nothing safe to
    // store it as.
    if ($ext === '' || !isset($allowed[$ext])) {
        throw new Exception('That file type is not allowed. Accepted: ' . implode(', ', array_keys($allowed)) . '.');
    }

    // ⚠️ Gate 2: and the CONTENT must match it. This is what stops `shell.php`
    // renamed to `shell.png`, and equally a real PNG named `.php`.
    $mime = uploadDetectMime($file['tmp_name']);
    if ($mime !== null && !in_array($mime, $allowed[$ext], true)) {
        throw new Exception('That file\'s contents do not match its ' . $ext . ' extension.');
    }

    uploadPrepareDir($destDir);

    // ⚠️ Gate 3: the name on disk is OURS. Nothing the caller sent is used to
    // build it — not the base name, not the extension. The original is kept in
    // the database for display, which is the only place it is safe.
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $path   = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $stored;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new Exception('The file could not be saved.');
    }
    @chmod($path, 0644);

    return [
        'stored_name'   => $stored,
        'original_name' => $original,
        'size'          => $size,
        'mime'          => $mime ?: 'application/octet-stream',
        'path'          => $path,
    ];
}

/**
 * Create an upload directory and drop execution protection into it.
 *
 * 🔑 Called on every store, not only on create — so a directory that already
 * exists from before this file was written gets protected the next time
 * anything is uploaded into it.
 */
function uploadPrepareDir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new Exception('The upload folder could not be created.');
    }

    $htaccess = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        // Covers Apache 2.2 and 2.4, mod_php and any handler-based PHP: turn the
        // engine off, strip every handler, and refuse to serve script types at
        // all. Files here are meant to be read by get_attachment.php, never
        // fetched directly.
        @file_put_contents($htaccess, <<<'HT'
# Uploaded content. NEVER executed — see includes/uploads.php.
php_flag engine off
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .cgi .pl .py .jsp .asp .aspx .shtml
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .cgi .pl .py
<IfModule mod_php.c>
  php_flag engine off
</IfModule>
<IfModule mod_php7.c>
  php_flag engine off
</IfModule>
<IfModule mod_php5.c>
  php_flag engine off
</IfModule>
<FilesMatch "\.(php|phtml|php[0-9]|phps|cgi|pl|py|jsp|asp|aspx|sh|shtml|htaccess)$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
</FilesMatch>
HT
        );
    }
}

/**
 * The original filename, reduced to something safe to STORE AND DISPLAY.
 *
 * Not used to build the path — see uploadStoreFile — but it is echoed into a
 * Content-Disposition header and rendered in lists, so it must not carry a
 * newline (header injection), a quote (breaks out of the header's filename),
 * or any path segment.
 */
function uploadCleanName(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]/u', '', $name);
    $name = trim(preg_replace('/\s+/u', ' ', (string)$name));
    if ($name === '' || $name === '.' || $name === '..') $name = 'file';
    if (mb_strlen($name) > 180) {
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 170) . ($ext !== '' ? '.' . $ext : '');
    }
    return $name;
}

/** The file's real mime type, or null if this server cannot tell us. */
function uploadDetectMime(string $path): ?string
{
    if (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $m  = @$fi->file($path);
        if (is_string($m) && $m !== '') return $m;
    }
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if (is_string($m) && $m !== '') return $m;
    }
    return null;   // unknown → gate 1 and gate 3 still stand
}

/** PHP's upload error codes as something a person can act on. */
function uploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:  return 'That file is larger than the server allows.';
        case UPLOAD_ERR_PARTIAL:    return 'The file only partly uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:    return 'No file was chosen.';
        case UPLOAD_ERR_NO_TMP_DIR: return 'The server has no temporary folder configured for uploads.';
        case UPLOAD_ERR_CANT_WRITE: return 'The server could not write the file to disk.';
        case UPLOAD_ERR_EXTENSION:  return 'A server extension blocked the upload.';
        default:                    return 'The file could not be uploaded.';
    }
}

function uploadFormatBytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
    return $bytes . ' bytes';
}
