<?php
/**
 * Equipment handover document (discussion #56).
 *
 * A printable record that a named person received a named list of equipment,
 * with somewhere for both of them to sign.
 *
 * ⚠️ RENDERED SERVER-SIDE, ON PURPOSE. This is the one page in the module that
 * somebody signs and files, so it must not depend on JavaScript having run, on a
 * fetch having succeeded, or on a chart library being present. What the printer
 * puts on paper is what the server sent.
 *
 * Printing is the browser's own print dialogue rather than a generated PDF: it
 * gives a true PDF via "Save as PDF" on every platform, keeps the text
 * selectable and searchable, and inherits the company logo and fonts already on
 * the page. A jsPDF re-implementation would be a second layout to keep in step
 * with this one.
 */
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/i18n.php';
require_once '../includes/theme.php';
require_once '../includes/services/assets.php';
require_once '../includes/tenancy.php';
I18n::initFromSession();

requireModuleAccess('assets');

$userId = (int)($_GET['user_id'] ?? 0);
$conn   = connectToDatabase();
$data   = $userId > 0 ? AssetsService::assetsForUser($conn, ActorContext::fromSession($conn), $userId) : null;

if ($data === null) {
    http_response_code(404);
    $notFound = true;
} else {
    $notFound = false;
    $user   = $data['user'];
    $assets = $data['assets'];
}

// The company logo, if one is set — the same one Branding manages, so a handover
// document looks like the rest of the organisation's paperwork.
$logoPath = null;
try {
    $s = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'branding_logo_path'");
    $s->execute();
    $p = (string)($s->fetchColumn() ?: '');
    if ($p !== '' && file_exists(__DIR__ . '/../' . $p)) {
        $logoPath = '../' . $p;
    }
} catch (Exception $e) {
    // A missing logo is cosmetic.
}

$today = date('j F Y');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('asset-management.handover.page_title')); ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <style>
        /* Deliberately NOT themed. A handover document is printed and filed, so it
           is always dark ink on white paper — a dark-mode print would waste toner
           and read badly, and the analyst's theme is not the document's business. */
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px;
            background: #f4f6f8;
            color: #1a1a1a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        .sheet {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            padding: 40px 44px;
            border-radius: 6px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .doc-head {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 24px; padding-bottom: 18px; border-bottom: 2px solid #1a1a1a;
        }
        .doc-title { font-size: 24px; font-weight: 700; margin: 0 0 4px; }
        .doc-sub { color: #555; font-size: 13px; }
        .doc-logo { max-height: 56px; max-width: 200px; }
        .section-title {
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px;
            color: #666; font-weight: 700; margin: 26px 0 8px;
        }
        .who { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 28px; }
        .field-label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }
        .field-value { font-size: 15px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th {
            text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px;
            color: #555; padding: 8px 6px; border-bottom: 2px solid #1a1a1a; white-space: nowrap;
        }
        td { padding: 9px 6px; border-bottom: 1px solid #e2e2e2; vertical-align: top; }
        td.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
        .none-row { text-align: center; padding: 26px; color: #666; }
        .declaration {
            margin-top: 26px; padding: 14px 16px;
            background: #f7f8fa; border-left: 3px solid #1a1a1a;
            font-size: 13px; color: #333;
        }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 34px; }
        .sig-line { border-bottom: 1px solid #1a1a1a; height: 46px; }
        .sig-label { font-size: 11px; color: #555; margin-top: 6px; }
        .sig-name { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
        .doc-foot { margin-top: 34px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 11px; color: #777; display: flex; justify-content: space-between; gap: 12px; }
        .toolbar { max-width: 820px; margin: 0 auto 14px; display: flex; gap: 8px; justify-content: flex-end; }
        .tb-btn {
            padding: 8px 16px; border: 1px solid #ccd2d8; border-radius: 5px;
            background: #fff; color: #1a1a1a; font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .tb-btn.primary { background: #0078d4; border-color: #0078d4; color: #fff; }
        .tb-btn:active { transform: scale(0.97); }

        @media print {
            /* The toolbar and the page chrome are for the screen only. */
            body { background: #fff; padding: 0; font-size: 12px; }
            .toolbar { display: none !important; }
            .sheet { box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
            /* Never split a signature block or a table row across two pages —
               a signature on its own on page 2 is not a signed document. */
            .signatures, .declaration, tr { break-inside: avoid; page-break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>

<?php if ($notFound): ?>
    <div class="sheet">
        <h1 class="doc-title"><?php echo htmlspecialchars(t('asset-management.handover.not_found')); ?></h1>
        <p class="doc-sub"><?php echo htmlspecialchars(t('asset-management.handover.not_found_hint')); ?></p>
    </div>
<?php else: ?>

<div class="toolbar">
    <button class="tb-btn" onclick="window.close()"><?php echo htmlspecialchars(t('asset-management.handover.close')); ?></button>
    <button class="tb-btn primary" onclick="window.print()"><?php echo htmlspecialchars(t('asset-management.handover.print')); ?></button>
</div>

<div class="sheet">
    <div class="doc-head">
        <div>
            <h1 class="doc-title"><?php echo htmlspecialchars(t('asset-management.handover.title')); ?></h1>
            <div class="doc-sub"><?php echo htmlspecialchars(t('asset-management.handover.dated', ['date' => $today])); ?></div>
        </div>
        <?php if ($logoPath): ?>
            <img class="doc-logo" src="<?php echo htmlspecialchars($logoPath); ?>" alt="">
        <?php endif; ?>
    </div>

    <div class="section-title"><?php echo htmlspecialchars(t('asset-management.handover.person')); ?></div>
    <div class="who">
        <div>
            <div class="field-label"><?php echo htmlspecialchars(t('asset-management.handover.field_name')); ?></div>
            <div class="field-value"><?php echo htmlspecialchars($user['name']); ?></div>
        </div>
        <div>
            <div class="field-label"><?php echo htmlspecialchars(t('asset-management.handover.field_email')); ?></div>
            <div class="field-value"><?php echo htmlspecialchars($user['email'] ?? '—'); ?></div>
        </div>
    </div>

    <div class="section-title"><?php echo htmlspecialchars(t('asset-management.handover.equipment', ['n' => count($assets)])); ?></div>
    <table>
        <thead>
            <tr>
                <th><?php echo htmlspecialchars(t('asset-management.handover.col_type')); ?></th>
                <th><?php echo htmlspecialchars(t('asset-management.handover.col_name')); ?></th>
                <th><?php echo htmlspecialchars(t('asset-management.handover.col_model')); ?></th>
                <th><?php echo htmlspecialchars(t('asset-management.handover.col_serial')); ?></th>
                <th><?php echo htmlspecialchars(t('asset-management.handover.col_tag')); ?></th>
                <th><?php echo htmlspecialchars(t('asset-management.handover.col_assigned')); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$assets): ?>
            <tr><td class="none-row" colspan="6"><?php echo htmlspecialchars(t('asset-management.handover.none')); ?></td></tr>
        <?php else: foreach ($assets as $a): ?>
            <tr>
                <td><?php echo htmlspecialchars($a['asset_type'] ?? '—'); ?></td>
                <td><strong><?php echo htmlspecialchars($a['hostname'] ?? '—'); ?></strong></td>
                <td><?php echo htmlspecialchars(trim(($a['manufacturer'] ?? '') . ' ' . ($a['model'] ?? '')) ?: '—'); ?></td>
                <td class="mono"><?php echo htmlspecialchars($a['service_tag'] ?? '—'); ?></td>
                <td class="mono"><?php echo htmlspecialchars($a['asset_tag'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($a['assigned_datetime'] ? date('j M Y', strtotime($a['assigned_datetime'])) : '—'); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="declaration"><?php echo htmlspecialchars(t('asset-management.handover.declaration')); ?></div>

    <div class="signatures">
        <div>
            <div class="sig-line"></div>
            <div class="sig-name"><?php echo htmlspecialchars($user['name']); ?></div>
            <div class="sig-label"><?php echo htmlspecialchars(t('asset-management.handover.sig_employee')); ?></div>
        </div>
        <div>
            <div class="sig-line"></div>
            <div class="sig-name">&nbsp;</div>
            <div class="sig-label"><?php echo htmlspecialchars(t('asset-management.handover.sig_it')); ?></div>
        </div>
    </div>

    <div class="doc-foot">
        <span><?php echo htmlspecialchars(t('asset-management.handover.footer_ref', ['id' => $user['id']])); ?></span>
        <span><?php echo htmlspecialchars(t('asset-management.handover.footer_generated', ['date' => $today])); ?></span>
    </div>
</div>

<?php endif; ?>
</body>
</html>
