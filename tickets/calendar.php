<?php
/**
 * Calendar - View scheduled tickets
 */
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/i18n.php';
require_once '../includes/theme.php';
require_once '../includes/timezone.php';
I18n::initFromSession();
Tz::init();

requireModuleAccess('tickets');

$current_page = 'calendar';

// Namespaces the JS bridge needs (calendar.js looks up months, weekdays, modal labels).
$translationNamespaces = ['common', 'tickets'];

// Whose scheduled work this analyst last chose to see. Read server-side and
// rendered into the page so the first paint is already the right set — fetching
// the preference from JS would show everyone's tickets for a moment and then
// snatch them away, which reads as a bug.
// Defaults to 'mine': the screen is your day first, the team's second.
$calendarScope = 'mine';
try {
    $stmt = connectToDatabase()->prepare(
        "SELECT preference_value FROM user_preferences
         WHERE analyst_id = ? AND preference_key = 'tickets_calendar_scope' LIMIT 1"
    );
    $stmt->execute([(int)$_SESSION['analyst_id']]);
    if ($stmt->fetchColumn() === 'all') $calendarScope = 'all';
} catch (Exception $e) {
    // 'mine' stands — the safer default, since it shows less rather than more.
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('tickets.calendar.page_title')); ?></title>
    <link rel="stylesheet" href="../assets/css/theme.css?v=23">
    <link rel="stylesheet" href="../assets/css/inbox.css?v=37">
    <link rel="stylesheet" href="../assets/css/calendar-grid.css?v=1">
    <link rel="stylesheet" href="../assets/css/calendar.css?v=8">
    <script>window.translations = <?php echo json_encode(I18n::exportForJs($translationNamespaces), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
    <?php echo Tz::scriptTag(); ?>
    <script src="../assets/js/tz.js?v=1"></script>
    <script src="../assets/js/i18n.js?v=2"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="calendar-container">
        <div class="calendar-header">
            <div class="calendar-nav">
                <button class="btn btn-secondary" onclick="goToToday()"><?php echo htmlspecialchars(t('common.calendar.today')); ?></button>
                <button class="btn btn-icon" onclick="navigatePrev()" title="<?php echo htmlspecialchars(t('common.calendar.previous')); ?>">&lsaquo;</button>
                <button class="btn btn-icon" onclick="navigateNext()" title="<?php echo htmlspecialchars(t('common.calendar.next')); ?>">&rsaquo;</button>
                <h2 class="calendar-title" id="calendarTitle"></h2>
            </div>
            <?php /* Whose work. Same control shape as the view toggle beside it,
                     because it is the same kind of choice — one of a small set,
                     always one selected. */ ?>
            <div class="view-toggle scope-toggle">
                <button class="view-btn<?php echo $calendarScope === 'mine' ? ' active' : ''; ?>" data-scope="mine" onclick="setScope('mine')"><?php echo htmlspecialchars(t('tickets.calendar.scope_mine')); ?></button>
                <button class="view-btn<?php echo $calendarScope === 'all' ? ' active' : ''; ?>" data-scope="all" onclick="setScope('all')"><?php echo htmlspecialchars(t('tickets.calendar.scope_everyone')); ?></button>
            </div>
            <div class="view-toggle">
                <button class="view-btn active" data-view="month" onclick="setView('month')"><?php echo htmlspecialchars(t('common.calendar.view_month')); ?></button>
                <button class="view-btn" data-view="week" onclick="setView('week')"><?php echo htmlspecialchars(t('common.calendar.view_week')); ?></button>
                <button class="view-btn" data-view="day" onclick="setView('day')"><?php echo htmlspecialchars(t('common.calendar.view_day')); ?></button>
            </div>
        </div>

        <?php /* Who is on screen, built from the tickets actually loaded — so it is
                 self-explaining and there is no second lookup to keep in step.
                 Hidden entirely in "Mine", where every ticket is yours. */ ?>
        <div class="owner-legend" id="ownerLegend" style="display:none;"></div>

        <div class="calendar-grid" id="calendarGrid">
            <!-- View content (month / week / day) rendered here -->
        </div>
    </div>

    <!-- Ticket Detail Modal -->
    <div class="modal" id="ticketModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <span id="ticketModalTitle"><?php echo htmlspecialchars(t('tickets.calendar.modal_title')); ?></span>
            </div>
            <div class="modal-body" id="ticketModalBody">
                <!-- Ticket details will be rendered here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeTicketModal()"><?php echo htmlspecialchars(t('common.close')); ?></button>
                <a id="ticketModalLink" href="#" class="btn btn-primary"><?php echo htmlspecialchars(t('tickets.calendar.open_in_inbox')); ?></a>
            </div>
        </div>
    </div>

    <script>
        window.API_BASE = '../api/tickets/';
        window.INBOX_URL = 'index.php';
        window.CALENDAR_SCOPE = <?php echo json_encode($calendarScope); ?>;
    </script>
    <script src="../assets/js/calendar.js?v=5"></script>
</body>
</html>
