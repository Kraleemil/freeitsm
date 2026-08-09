<?php
/**
 * War room — fallback chat for when Teams/Slack is unavailable.
 *
 * ⚠️ NO EXTERNAL ASSETS ON THIS PAGE, EVER. Everything it loads must come from
 * this server. A page whose whole purpose is "the internet is down" cannot pull
 * a script from a CDN, a font from Google, or anything else that needs a name
 * to resolve beyond the LAN. Four other pages in the app do use cdnjs; this one
 * must not join them.
 *
 * The channel list is rendered server-side from the analyst's teams — see
 * includes/warroom.php for why a channel IS a team.
 */
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/i18n.php';
require_once '../includes/theme.php';
require_once '../includes/timezone.php';
require_once '../includes/warroom.php';
requireModuleAccess('war-room');
I18n::initFromSession();
Tz::init();

$current_page          = 'war-room';
$path_prefix           = '../';
$translationNamespaces = ['common', 'war-room'];

$conn     = connectToDatabase();
$channels = warRoomChannels($conn, (int) $_SESSION['analyst_id']);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - <?php echo htmlspecialchars(t('war-room.title')); ?></title>
    <link rel="stylesheet" href="../assets/css/theme.css?v=22">
    <link rel="stylesheet" href="../assets/css/inbox.css?v=37">
    <link rel="stylesheet" href="../assets/css/war-room.css?v=1">
    <link rel="stylesheet" href="../assets/css/mobile.css?v=41">
    <style>
        /* Pin the shared accent to the module's amber so buttons and focus
           rings are on-brand, the same way every other module does it. */
        body { --accent: var(--war-room-accent, #ea580c); --accent-hover: var(--war-room-accent-hover, #c2410c); }
    </style>
    <script>window.translations = <?php echo json_encode(I18n::exportForJs($translationNamespaces), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
    <?php echo Tz::scriptTag(); ?>
    <script src="../assets/js/tz.js?v=1"></script>
    <script src="../assets/js/i18n.js?v=2"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="wr-container">
        <!-- Channels. Rendered server-side because the list is derived from the
             analyst's teams and never changes while the page is open. -->
        <aside class="wr-sidebar">
            <h3 class="wr-sidebar-heading"><?php echo htmlspecialchars(t('war-room.channel.heading')); ?></h3>
            <div class="wr-channels" id="wrChannels">
                <?php foreach ($channels as $ch): ?>
                    <button type="button"
                            class="wr-channel<?php echo $ch['team_id'] === null ? ' active' : ''; ?>"
                            data-team-id="<?php echo $ch['team_id'] === null ? '' : (int) $ch['team_id']; ?>">
                        <?php echo htmlspecialchars($ch['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Who's here lives beside the channel list, not under the
                 conversation: it belongs with "where am I and who is around"
                 rather than competing with the message you are typing. -->
            <div class="wr-presence" id="wrPresence"></div>
        </aside>

        <main class="wr-main">
            <p class="wr-intro"><?php echo htmlspecialchars(t('war-room.intro')); ?></p>

            <div class="wr-messages" id="wrMessages">
                <div class="wr-empty" id="wrEmpty"><?php echo htmlspecialchars(t('war-room.empty')); ?></div>
            </div>

            <form class="wr-composer" id="wrComposer" autocomplete="off">
                <textarea id="wrBody" rows="1"
                          placeholder="<?php echo htmlspecialchars(t('war-room.composer.placeholder')); ?>"
                          maxlength="<?php echo WARROOM_MAX_BODY; ?>"></textarea>
                <button type="submit" class="btn btn-primary" id="wrSend"><?php echo htmlspecialchars(t('war-room.composer.send')); ?></button>
            </form>
        </main>
    </div>

    <script>window.API_BASE = '<?php echo BASE_URL; ?>api/war-room/';</script>
    <script src="../assets/js/war-room.js?v=1"></script>
    <script src="../assets/js/mobile.js?v=22"></script>
</body>
</html>
