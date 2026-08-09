<?php
/**
 * Printable QR label sheet.
 *
 * WHY A PRINT PAGE AND NOT A PDF
 * ------------------------------
 * FreeITSM ships no PDF library, and adding one to print a grid of squares
 * would be a dependency for nothing: `@page` plus millimetre units gives the
 * same result through the browser's own print dialog, on any printer, with a
 * live preview. The QR codes are drawn client-side by the qrcode library the
 * app already bundles for MFA enrolment — so nothing about your estate is sent
 * to a third-party QR service, which is the usual way this gets done badly.
 *
 * WHAT IT PRINTS
 * --------------
 * One label per asset: the QR (encoding the /a/<token> URL), the human-readable
 * asset tag, and the hostname. The tag is printed as text as well as encoded
 * because a damaged or dirty QR still has to be readable by a person — that is
 * the whole reason asset tags survived the invention of the barcode.
 *
 * Tokens are minted here, on demand: an install with 4,000 auto-discovered
 * assets should not carry 4,000 tokens for labels nobody will ever print.
 */
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/i18n.php';
require_once '../includes/theme.php';
require_once '../includes/tenancy.php';
require_once '../includes/asset_labels.php';
I18n::initFromSession();

requireModuleAccess('assets');

$conn = connectToDatabase();
$analystId = (int)$_SESSION['analyst_id'];

$ready = assetLabelsSchemaReady($conn);

// Which assets? An explicit id list (from a selection), capped so a stray URL
// can't try to render the whole estate into one page.
$ids = [];
if (!empty($_GET['ids'])) {
    foreach (explode(',', (string)$_GET['ids']) as $raw) {
        $id = (int)trim($raw);
        if ($id > 0) $ids[] = $id;
    }
}
$ids = array_slice(array_values(array_unique($ids)), 0, 200);

// Label stock. Sizes are the common European sheet pitches; "custom" is
// deliberately absent from v1 rather than half-done.
$sheets = [
    '65' => ['label' => '65 per sheet — 38.1 × 21.2 mm', 'w' => 38.1, 'h' => 21.2, 'cols' => 5, 'qr' => 17],
    '40' => ['label' => '40 per sheet — 45.7 × 25.4 mm', 'w' => 45.7, 'h' => 25.4, 'cols' => 4, 'qr' => 20],
    '24' => ['label' => '24 per sheet — 63.5 × 33.9 mm', 'w' => 63.5, 'h' => 33.9, 'cols' => 3, 'qr' => 27],
    '12' => ['label' => '12 per sheet — 63.5 × 72 mm',   'w' => 63.5, 'h' => 72.0, 'cols' => 3, 'qr' => 40],
];
// ⚠️ These keys are NUMERIC STRINGS, which PHP silently casts to integer array
// keys — so a `===` comparison against the string from $_GET never matches and
// every option renders unselected (the browser then shows the first one, while
// the grid below uses the real choice: a dropdown that disagrees with the page).
// Compare as strings on both sides.
$sheetKey = (isset($_GET['sheet']) && isset($sheets[$_GET['sheet']])) ? (string)$_GET['sheet'] : '40';
$sheet = $sheets[$sheetKey];

/**
 * CSV for a professional print house.
 *
 * There is no ITAM standard for handing labels to a printer, but there IS a
 * standard *process*: variable data printing. You send one row per label and
 * they merge it into their own template — so the deliverable is a plain CSV
 * with the QR payload as a literal URL (which any print system can encode) plus
 * the human text, not a picture of a QR code.
 *
 * Emitted before any HTML so the download isn't a page with a file bolted on.
 */
if (isset($_GET['csv'])) {
    $rows = [];
    if ($ready && $ids) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        [$tSql, $tArgs] = activeTenantFilter($conn, $analystId, 'a');
        $stmt = $conn->prepare(
            "SELECT a.id, a.asset_tag, a.hostname, a.service_tag
               FROM assets a WHERE a.id IN ($place)" . $tSql . "
              ORDER BY a.asset_tag IS NULL, a.asset_tag, a.hostname"
        );
        $stmt->execute(array_merge($ids, $tArgs));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asset-labels.csv"');
    $out = fopen('php://output', 'w');
    // BOM: without it Excel opens UTF-8 as mojibake, and a print house working
    // from a mangled asset tag prints a mangled label 500 times.
    fwrite($out, "\xEF\xBB\xBF");
    // ⚠️ $escape is passed EXPLICITLY. PHP 8.4 deprecates relying on its default,
    // and on a server with display_errors on, that notice is written INTO the
    // download — a CSV with an HTML warning in the middle of it, which a print
    // house would merge straight onto 500 labels. Empty string also disables
    // backslash escaping, which is what Excel and every CSV reader expects.
    fputcsv($out, ['asset_tag', 'hostname', 'serial', 'qr_url'], ',', '"', '');
    foreach ($rows as $r) {
        $token = assetEnsureToken($conn, (int)$r['id']);
        fputcsv($out, [
            $r['asset_tag'] ?? '',
            $r['hostname'] ?? '',
            $r['service_tag'] ?? '',
            $token ? assetLabelUrl($token) : '',
        ], ',', '"', '');
    }
    fclose($out);
    exit;
}

$assets = [];
if ($ready && $ids) {
    $place = implode(',', array_fill(0, count($ids), '?'));
    // Company scope: never print a label for an asset this analyst can't see.
    [$tSql, $tArgs] = activeTenantFilter($conn, $analystId, 'a');
    $stmt = $conn->prepare(
        "SELECT a.id, a.asset_tag, a.hostname, a.service_tag
           FROM assets a
          WHERE a.id IN ($place)" . $tSql . "
          ORDER BY a.asset_tag IS NULL, a.asset_tag, a.hostname"
    );
    $stmt->execute(array_merge($ids, $tArgs));
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assets as &$a) {
        $a['token'] = assetEnsureToken($conn, (int)$a['id']);
        $a['url']   = $a['token'] ? assetLabelUrl($a['token']) : '';
    }
    unset($a);
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>">
<head>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <meta charset="UTF-8">
    <title>Asset labels · FreeITSM</title>
    <script src="../assets/js/qrcode.min.js"></script>
    <style>
        /* Screen chrome — everything here disappears for the printer. */
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f6fa; color: #222; }
        .bar { background: #546e7a; color: #fff; padding: 12px 18px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .bar h1 { font-size: 16px; margin: 0; font-weight: 600; }
        .bar label { font-size: 13px; }
        .bar select, .bar button, .bar a.btn {
            font-size: 13px; padding: 7px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.12); color: #fff; cursor: pointer; text-decoration: none;
        }
        .bar button.primary { background: #fff; color: #37474f; font-weight: 600; border-color: #fff; }
        .hint { padding: 12px 18px; font-size: 13px; color: #555; background: #fff8e1; border-bottom: 1px solid #ffe0a3; }
        .sheet { padding: 14px; }

        /* The label grid. Millimetres throughout, because that is the unit the
           label stock is sold in and the unit the printer thinks in. */
        .labels {
            display: grid;
            grid-template-columns: repeat(<?php echo (int)$sheet['cols']; ?>, <?php echo $sheet['w']; ?>mm);
            gap: 0;
        }
        .label {
            width: <?php echo $sheet['w']; ?>mm;
            height: <?php echo $sheet['h']; ?>mm;
            padding: 1.5mm;
            display: flex;
            align-items: center;
            gap: 1.5mm;
            overflow: hidden;
            background: #fff;
            border: 1px dashed #cfd6dd;   /* cut guides on screen only */
            box-sizing: border-box;
        }
        .label .qr { flex: 0 0 auto; width: <?php echo $sheet['qr']; ?>mm; height: <?php echo $sheet['qr']; ?>mm; }
        .label .qr img { width: 100%; height: 100%; image-rendering: pixelated; display: block; }
        .label .txt { min-width: 0; line-height: 1.15; }
        .label .tag { font-weight: 700; font-size: <?php echo max(7, $sheet['qr'] / 3); ?>pt; letter-spacing: 0.3px; }
        .label .host { font-size: <?php echo max(6, $sheet['qr'] / 4); ?>pt; color: #333; word-break: break-all; }
        .label .empty-tag { color: #999; font-weight: 400; }

        @media print {
            /* Nothing but labels reaches the paper. */
            .bar, .hint { display: none !important; }
            body { background: #fff; }
            .sheet { padding: 0; }
            .label { border: 0; }
            @page { size: A4; margin: 8mm; }
            /* A label must never be split across two pages. */
            .label { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="bar">
    <h1>Asset labels</h1>
    <label>Label sheet
        <select onchange="location.search = '?sheet=' + this.value + '&ids=<?php echo htmlspecialchars(implode(',', $ids)); ?>'">
            <?php foreach ($sheets as $k => $s): ?>
                <option value="<?php echo htmlspecialchars((string)$k); ?>" <?php echo (string)$k === $sheetKey ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="primary" onclick="window.print()">Print</button>
    <?php /* For a print house: one row per label, the QR payload as a literal
             URL for their variable-data merge. */ ?>
    <a class="btn" href="?csv=1&amp;ids=<?php echo htmlspecialchars(implode(',', $ids)); ?>">CSV for a printer</a>
    <a class="btn" href="./">Back to Assets</a>
    <span style="opacity:0.85;font-size:13px;"><?php echo count($assets); ?> label(s)</span>
</div>

<?php
/**
 * A label is printed once and stuck on a machine for years, so a URL that only
 * works on the server itself is the most expensive mistake this page can make —
 * you find out after 500 labels are on 500 laptops. `localhost` in a QR code
 * means "this phone" to the phone that scans it, so it can never work.
 */
$labelHost = parse_url(assetPublicBaseUrl(), PHP_URL_HOST) ?: '';
$hostIsLocal = in_array(strtolower($labelHost), ['localhost', '127.0.0.1', '::1'], true);
?>
<?php if ($ready && $hostIsLocal): ?>
    <div class="hint" style="background:#fdeceb;border-bottom-color:#f5c6cb;color:#8a1f1a;">
        <strong>These codes point at <code><?php echo htmlspecialchars($labelHost); ?></code> — a phone scanning them will fail.</strong>
        To a phone, <code>localhost</code> means the phone itself. Set the address this install is reached on
        (<strong>Tickets → Settings → Messaging → Public base URL</strong>) and reprint — the codes themselves
        don't change, only the address inside them.
    </div>
<?php endif; ?>

<?php if (!$ready): ?>
    <div class="hint">Asset labels need a database update first — an administrator can run <strong>System → Database Verification</strong>.</div>
<?php elseif (!$ids): ?>
    <div class="hint">No assets chosen. Open this page with a list of asset ids, e.g. <code>labels.php?ids=1,2,3</code>.</div>
<?php elseif (!$assets): ?>
    <div class="hint">None of those assets are visible to you.</div>
<?php else: ?>
    <div class="hint">
        Check the alignment on plain paper before using label stock — printers vary by a millimetre or two.
        The dashed guides are on screen only and won't print.
    </div>
<?php endif; ?>

<div class="sheet">
    <div class="labels" id="labels">
        <?php foreach ($assets as $a): ?>
            <div class="label">
                <div class="qr" data-url="<?php echo htmlspecialchars($a['url']); ?>"></div>
                <div class="txt">
                    <div class="tag<?php echo empty($a['asset_tag']) ? ' empty-tag' : ''; ?>">
                        <?php echo htmlspecialchars($a['asset_tag'] ?: 'no tag'); ?>
                    </div>
                    <div class="host"><?php echo htmlspecialchars($a['hostname'] ?: ('#' . $a['id'])); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
/**
 * Draw each QR from its URL, using the library already bundled for MFA.
 * Error-correction level M: enough redundancy that a scuffed label still
 * scans, without inflating the code so much it stops being readable small.
 * Type 0 lets the library pick the smallest version that fits the URL.
 */
document.querySelectorAll('.qr').forEach(function (box) {
    var url = box.getAttribute('data-url');
    if (!url) { box.textContent = '—'; return; }
    try {
        var qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();
        box.innerHTML = qr.createImgTag(4, 0);
    } catch (e) {
        box.textContent = '!';
    }
});
</script>
</body>
</html>
