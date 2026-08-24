<?php
/**
 * Gmail API Helper Functions
 *
 * Provides send/read/refresh/delete operations for Google mailboxes.
 * Mirrors the Microsoft Graph API functions used elsewhere.
 */

require_once __DIR__ . '/encryption.php';

/**
 * Refresh a Google access token using the refresh token.
 * Returns the updated token data array.
 */
function gmailRefreshAccessToken(PDO $conn, array $mailbox, array $tokenData): array {
    if (empty($tokenData['refresh_token'])) {
        throw new Exception('No Google refresh token available. Please re-authenticate.');
    }

    $postData = [
        'client_id' => $mailbox['azure_client_id'],
        'client_secret' => $mailbox['azure_client_secret'],
        'refresh_token' => $tokenData['refresh_token'],
        'grant_type' => 'refresh_token'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    sslApplyCurl($ch);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL error refreshing Google token: ' . $error);
    }

    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to refresh Google token. HTTP ' . $httpCode);
    }

    $newToken = json_decode($response, true);

    if (!isset($newToken['access_token'])) {
        throw new Exception('Google refresh did not return an access token');
    }

    $tokenData['access_token'] = $newToken['access_token'];
    // Google doesn't always return a new refresh_token — keep the old one
    if (isset($newToken['refresh_token'])) {
        $tokenData['refresh_token'] = $newToken['refresh_token'];
    }
    $tokenData['expires_at'] = time() + ($newToken['expires_in'] ?? 3600);

    // Persist
    $stmt = $conn->prepare("UPDATE target_mailboxes SET token_data = ? WHERE id = ?");
    // Encrypted at rest — see ENCRYPTED_MAILBOX_COLUMNS; decryptMailboxRow() reverses it.
    $stmt->execute([encryptValue(json_encode($tokenData)), $mailbox['id']]);

    return $tokenData;
}

/**
 * Get a valid Google access token, refreshing if expired.
 */
function gmailGetValidAccessToken(PDO $conn, array $mailbox, array $tokenData): string {
    if (isset($tokenData['expires_at']) && $tokenData['expires_at'] < (time() + 300)) {
        $tokenData = gmailRefreshAccessToken($conn, $mailbox, $tokenData);
    }
    return $tokenData['access_token'];
}

/**
 * Send an email via the Gmail API.
 *
 * $to       - recipient email address
 * $subject  - email subject
 * $htmlBody - HTML body content
 * $from     - sender email address (the mailbox address)
 */
function gmailSendEmail(string $accessToken, string $to, string $subject, string $htmlBody, string $from = ''): void {
    // Build RFC 2822 message
    $boundary = md5(uniqid(time()));
    $headers = "MIME-Version: 1.0\r\n";
    if ($from) {
        $headers .= "From: $from\r\n";
    }
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "\r\n";
    $rawMessage = $headers . $htmlBody;

    // Base64url encode
    $encoded = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

    $payload = json_encode(['raw' => $encoded]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    sslApplyCurl($ch);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Gmail send cURL error: ' . $error);
    }

    curl_close($ch);

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
        throw new Exception("Gmail API send failed: $errorMsg (HTTP $httpCode)");
    }
}

/**
 * Fetch unread emails from Gmail.
 * Returns an array of messages normalised to the same shape as Graph API results.
 */
function gmailGetEmails(string $accessToken, array $mailbox): array {
    $maxResults = $mailbox['max_emails_per_check'] ?? 10;

    // List unread message IDs
    $q = 'is:unread';
    $labelId = 'INBOX';
    $folder = strtoupper($mailbox['email_folder'] ?? 'INBOX');
    if ($folder !== 'INBOX') {
        $q .= ' label:' . strtolower($mailbox['email_folder']);
    }

    $listUrl = 'https://gmail.googleapis.com/gmail/v1/users/me/messages?'
        . http_build_query(['q' => $q, 'maxResults' => $maxResults, 'labelIds' => $labelId]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $listUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    sslApplyCurl($ch);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Gmail list messages failed: HTTP ' . $httpCode . ' ' . $response);
    }

    $data = json_decode($response, true);
    $messageIds = $data['messages'] ?? [];

    if (empty($messageIds)) {
        return [];
    }

    // Fetch full message for each ID
    $emails = [];
    foreach ($messageIds as $msg) {
        $detail = gmailGetMessage($accessToken, $msg['id']);
        if ($detail) {
            $emails[] = $detail;
        }
    }

    return $emails;
}

/**
 * Fetch a single Gmail message and normalise to Graph-like structure.
 */
function gmailGetMessage(string $accessToken, string $messageId): ?array {
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $messageId . '?format=full';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    sslApplyCurl($ch);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;

    $msg = json_decode($response, true);
    $headers = [];
    foreach ($msg['payload']['headers'] ?? [] as $h) {
        $headers[strtolower($h['name'])] = $h['value'];
    }

    // Extract body
    $body = gmailExtractBody($msg['payload'] ?? []);

    // Parse from header: "Name <email>" or just "email"
    $fromRaw = $headers['from'] ?? '';
    $fromName = '';
    $fromAddress = $fromRaw;
    if (preg_match('/^(.*?)\s*<(.+?)>$/', $fromRaw, $m)) {
        $fromName = trim($m[1], '" ');
        $fromAddress = $m[2];
    }

    // Parse to/cc
    $toRecipients = gmailParseAddressList($headers['to'] ?? '');
    $ccRecipients = gmailParseAddressList($headers['cc'] ?? '');

    // Parse date
    $dateStr = $headers['date'] ?? '';
    $receivedDateTime = $dateStr ? date('Y-m-d\TH:i:s\Z', strtotime($dateStr)) : date('Y-m-d\TH:i:s\Z');

    // Pull attachments now (in Graph shape) and pass them along inline — the importer
    // stores whatever a message carries in 'attachments_inline' rather than making a
    // second, provider-specific fetch. (Previously Gmail attachments were missed
    // because the importer only knew how to re-fetch from Microsoft Graph.)
    $inlineAttachments = [];
    if (!empty($msg['payload'])) {
        try {
            $inlineAttachments = gmailGetAttachmentsGraphShape($accessToken, $messageId, $msg['payload']);
        } catch (Exception $e) {
            error_log('Gmail attachment fetch failed for ' . $messageId . ': ' . $e->getMessage());
        }
    }

    // Return in Graph-like structure so the import code doesn't need major changes
    return [
        'id' => $messageId,
        'subject' => $headers['subject'] ?? '(No Subject)',
        'from' => [
            'emailAddress' => [
                'name' => $fromName,
                'address' => $fromAddress
            ]
        ],
        'toRecipients' => $toRecipients,
        'ccRecipients' => $ccRecipients,
        'receivedDateTime' => $receivedDateTime,
        'bodyPreview' => substr(strip_tags($body), 0, 255),
        'body' => [
            'contentType' => 'html',
            'content' => $body
        ],
        'hasAttachments' => !empty($inlineAttachments),
        'attachments_inline' => $inlineAttachments,
        'importance' => 'normal',
        'isRead' => false
    ];
}

/**
 * Fetch Gmail attachments for a message and return them in the Graph fileAttachment
 * shape the importer (saveAttachment) expects. Walks nested multiparts.
 */
function gmailGetAttachmentsGraphShape(string $accessToken, string $messageId, array $payload): array {
    $out = [];
    $walk = function ($parts) use (&$walk, &$out, $accessToken, $messageId) {
        foreach ($parts as $part) {
            if (!empty($part['parts'])) {
                $walk($part['parts']);
            }
            if (empty($part['filename'])) {
                continue;
            }
            $attId = $part['body']['attachmentId'] ?? null;
            if (!$attId) {
                continue;
            }

            $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $messageId . '/attachments/' . $attId;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            sslApplyCurl($ch);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode !== 200) {
                continue;
            }

            $attData = json_decode($response, true);
            if (!isset($attData['data'])) {
                continue;
            }
            $bytes = base64_decode(strtr($attData['data'], '-_', '+/'));

            // Content-ID (for inline images referenced by cid:)
            $contentId = null;
            $isInline = false;
            foreach ($part['headers'] ?? [] as $h) {
                $hn = strtolower($h['name'] ?? '');
                if ($hn === 'content-id') {
                    $contentId = trim($h['value'] ?? '', '<>');
                } elseif ($hn === 'content-disposition' && stripos($h['value'] ?? '', 'inline') !== false) {
                    $isInline = true;
                }
            }

            $out[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'id' => $attId,
                'name' => $part['filename'],
                'contentType' => $part['mimeType'] ?? 'application/octet-stream',
                'contentBytes' => base64_encode($bytes),
                'isInline' => $isInline,
                'contentId' => $contentId,
                'size' => strlen($bytes),
            ];
        }
    };
    $walk($payload['parts'] ?? []);
    return $out;
}

/**
 * Extract body (prefer HTML, fall back to plain text).
 */
function gmailExtractBody(array $payload): string {
    // Direct body
    if (!empty($payload['body']['data'])) {
        $decoded = base64_decode(strtr($payload['body']['data'], '-_', '+/'));
        if (($payload['mimeType'] ?? '') === 'text/html') return $decoded;
        if (($payload['mimeType'] ?? '') === 'text/plain') return nl2br(htmlspecialchars($decoded));
    }

    // Multi-part: look for text/html first, then text/plain
    $html = '';
    $plain = '';
    foreach ($payload['parts'] ?? [] as $part) {
        $mime = $part['mimeType'] ?? '';
        if ($mime === 'text/html' && !empty($part['body']['data'])) {
            $html = base64_decode(strtr($part['body']['data'], '-_', '+/'));
        } elseif ($mime === 'text/plain' && !empty($part['body']['data'])) {
            $plain = base64_decode(strtr($part['body']['data'], '-_', '+/'));
        } elseif (str_starts_with($mime, 'multipart/')) {
            // Recurse into nested multipart
            $nested = gmailExtractBody($part);
            if ($nested) return $nested;
        }
    }

    if ($html) return $html;
    if ($plain) return nl2br(htmlspecialchars($plain));
    return '';
}

/**
 * Parse a comma-separated address list into Graph-like format.
 */
function gmailParseAddressList(string $raw): array {
    if (empty($raw)) return [];
    $result = [];
    $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $raw);
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match('/^(.*?)\s*<(.+?)>$/', $part, $m)) {
            $result[] = ['emailAddress' => ['name' => trim($m[1], '" '), 'address' => $m[2]]];
        } elseif (filter_var($part, FILTER_VALIDATE_EMAIL)) {
            $result[] = ['emailAddress' => ['name' => '', 'address' => $part]];
        }
    }
    return $result;
}

/**
 * Delete a Gmail message (move to trash).
 */
function gmailTrashMessage(string $accessToken, string $messageId): void {
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $messageId . '/trash';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    sslApplyCurl($ch);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Gmail trash failed: HTTP ' . $httpCode);
    }
}

/**
 * Mark a Gmail message as read (remove UNREAD label).
 */
function gmailMarkAsRead(string $accessToken, string $messageId): void {
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $messageId . '/modify';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['removeLabelIds' => ['UNREAD']]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    sslApplyCurl($ch);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    curl_exec($ch);
    curl_close($ch);
}

/**
 * Get Gmail attachments for a message.
 * Returns array of [filename, mimeType, data (base64-decoded)].
 */
function gmailGetAttachments(string $accessToken, string $messageId, array $payload): array {
    $attachments = [];
    foreach ($payload['parts'] ?? [] as $part) {
        if (empty($part['filename'])) continue;
        $attId = $part['body']['attachmentId'] ?? null;
        if (!$attId) continue;

        $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $messageId . '/attachments/' . $attId;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        sslApplyCurl($ch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);

        $response = curl_exec($ch);
        curl_close($ch);

        $attData = json_decode($response, true);
        if (isset($attData['data'])) {
            $attachments[] = [
                'filename' => $part['filename'],
                'mimeType' => $part['mimeType'] ?? 'application/octet-stream',
                'data' => base64_decode(strtr($attData['data'], '-_', '+/'))
            ];
        }
    }
    return $attachments;
}

/**
 * Verify a label exists in a Google mailbox and report what the reader will see.
 *
 * ⚠️ WHY THIS EXISTS. verify_mailbox_folder.php was written for Microsoft Graph.
 * A Google mailbox DOES hold an OAuth token, so it sailed past the token gate and
 * then ran the whole Microsoft flow with Google credentials: the access token went
 * to graph.microsoft.com, and an expired one was refreshed against
 * login.microsoftonline.com using azure_client_id / azure_client_secret that a
 * Google mailbox does not have. The user got a Microsoft error about a mailbox
 * with nothing to do with Microsoft.
 *
 * ⚠️ Counts deliberately mirror gmailGetEmails() rather than the label's own
 * totals. That reader ALWAYS lists with labelIds=INBOX and applies the configured
 * folder as an extra `label:` filter on top, so it only ever sees mail that is
 * both in the Inbox AND carries the label. Reporting the label's own message count
 * would pass a label whose mail is archived, which reading then collects nothing
 * from — the GH #77 failure shape.
 *
 * @return array{displayName:string, totalItemCount:int, unreadItemCount:int, note:string|null}
 * @throws Exception naming the label, or explaining the API failure.
 */
function gmailVerifyFolder(string $accessToken, array $mailbox, string $folderName): array {
    $folderName = trim($folderName);
    if ($folderName === '') {
        throw new Exception('No mail folder configured.');
    }

    $get = function (string $url) use ($accessToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        sslApplyCurl($ch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        if ($err !== null) {
            throw new Exception('cURL error talking to Gmail: ' . $err);
        }
        return ['code' => (int) $code, 'body' => json_decode($response, true)];
    };

    $res = $get('https://gmail.googleapis.com/gmail/v1/users/me/labels');
    if ($res['code'] !== 200) {
        throw new Exception('Could not list Gmail labels (HTTP ' . $res['code'] . ').');
    }
    $labels = $res['body']['labels'] ?? [];

    $match = null;
    foreach ($labels as $label) {
        if (strcasecmp((string) ($label['name'] ?? ''), $folderName) === 0) {
            $match = $label;
            break;
        }
    }

    if ($match === null) {
        $names = [];
        foreach ($labels as $label) {
            if (!empty($label['name'])) {
                $names[] = $label['name'];
            }
        }
        $names = array_slice($names, 0, 12);
        throw new Exception(
            'Label "' . $folderName . '" was not found in this Gmail account.'
            . ($names ? ' Labels on the account: ' . implode(', ', $names) . '.' : '')
        );
    }

    // Rebuild the reader's own query (gmailGetEmails) so the counts are what
    // FreeITSM would actually collect, not what the label happens to hold.
    $isInbox = strcasecmp($folderName, 'INBOX') === 0;
    $filter  = $isInbox ? '' : ' label:' . strtolower($folderName);

    $countFor = function (string $query) use ($get) {
        $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages?'
             . http_build_query(['q' => $query, 'maxResults' => 1, 'labelIds' => 'INBOX']);
        $res = $get($url);
        if ($res['code'] !== 200) {
            throw new Exception('Could not list Gmail messages (HTTP ' . $res['code'] . ').');
        }
        return (int) ($res['body']['resultSizeEstimate'] ?? 0);
    };

    $total  = $countFor(trim($filter));
    $unread = $countFor(trim('is:unread' . $filter));

    // A label that exists but sits outside the Inbox reads as "found, 0 messages",
    // which looks like an empty folder rather than a mailbox that will never
    // collect anything. Say so plainly instead.
    $note = null;
    if (!$isInbox && $total === 0) {
        $detail = $get('https://gmail.googleapis.com/gmail/v1/users/me/labels/' . rawurlencode((string) $match['id']));
        $labelTotal = $detail['code'] === 200 ? (int) ($detail['body']['messagesTotal'] ?? 0) : 0;
        if ($labelTotal > 0) {
            $note = 'The label holds ' . $labelTotal . ' message(s), but none are in the Inbox. '
                  . 'FreeITSM only reads mail that is in the Inbox AND labelled "' . $match['name'] . '", '
                  . 'so archived mail carrying this label will not be collected.';
        }
    }

    return [
        'displayName'     => $match['name'],
        'totalItemCount'  => $total,
        'unreadItemCount' => $unread,
        'note'            => $note,
    ];
}
