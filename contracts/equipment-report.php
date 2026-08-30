<?php
/**
 * The equipment report for a contract, as a printable page.
 *
 * ?id=<contract>   and optionally &print=1 to open the print dialog on load.
 *
 * "PDF" in this product means printing to one, which is what asset handover and
 * the RFP preview already do. There is no PDF library in the stack, and adding
 * one to produce a table of six columns would be a dependency to maintain
 * forever in exchange for a Save-as-PDF button every browser already has.
 *
 * ⚠️ The body comes from includes/contract_report.php, the SAME renderer the
 * emailed copy uses. One layout, three destinations.
 */
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once '../includes/contract_report.php';

I18n::initFromSession();

if (!isset($_SESSION['analyst_id'])) {
    header('Location: ../login.php');
    exit;
}
requireModuleAccess('contracts');

$contractId = (int)($_GET['id'] ?? 0);
$conn       = connectToDatabase();

$contract = $contractId > 0 ? contractReportLoad($conn, $contractId) : null;
if (!$contract) {
    http_response_code(404);
    echo '<!DOCTYPE html><meta charset="UTF-8"><p style="font:14px sans-serif;padding:24px">'
       . htmlspecialchars(t('contracts.report.not_found')) . '</p>';
    exit;
}

// Scoped to the companies this analyst can reach, exactly as the on-screen
// panel is. A report is not a way around the filter.
$assets   = contractAssetsFor($conn, (int)$_SESSION['analyst_id'], $contractId);
$autoPrint = !empty($_GET['print']);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('contracts.report.heading')); ?> &mdash; <?php echo htmlspecialchars(trim(($contract['contract_number'] ?? '') . ' ' . ($contract['title'] ?? ''))); ?></title>
    <style>
        body { margin: 0; padding: 28px; background: #f5f7fa; }
        .cr-page { max-width: 900px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .cr-bar { max-width: 900px; margin: 0 auto 14px; display: flex; gap: 8px; }
        .cr-bar button, .cr-bar a {
            font: inherit; font-size: 13px; padding: 8px 16px; border-radius: 4px;
            border: none; cursor: pointer; text-decoration: none;
        }
        .cr-bar .primary { background: #f59e0b; color: #fff; }
        .cr-bar .secondary { background: #e5e7eb; color: #333; }
        <?php echo contractReportCss(); ?>
        @media print {
            body { background: #fff; padding: 0; }
            .cr-page { max-width: none; padding: 0; box-shadow: none; border-radius: 0; }
            <?php echo contractReportPrintCss(); ?>
        }
    </style>
</head>
<body>
    <div class="cr-bar cr-noprint">
        <button type="button" class="primary" onclick="window.print()"><?php echo htmlspecialchars(t('contracts.report.print')); ?></button>
        <a class="secondary" href="view.php?id=<?php echo (int)$contractId; ?>"><?php echo htmlspecialchars(t('contracts.report.back')); ?></a>
    </div>

    <div class="cr-page">
        <?php echo contractReportBody($contract, $assets); ?>
    </div>

<?php if ($autoPrint): ?>
    <script>
        // Opened from the report menu with "PDF" chosen, so go straight to the
        // dialog. Not on a plain visit — a print dialog nobody asked for is how
        // a page gets closed before it is read.
        window.addEventListener('load', () => window.print());
    </script>
<?php endif; ?>
</body>
</html>
