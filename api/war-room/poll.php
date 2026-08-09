<?php
/**
 * API: War room — fetch new messages and register presence.
 *
 * GET  ?team_id=<int|blank>&since_id=<int>
 *   team_id blank/absent = the all-hands room.
 *   since_id = the newest id the caller already holds; 0 asks for the tail.
 *
 * This one request does three jobs — returns messages, records that the caller
 * is here, and reports who else is — because it is the request the page makes
 * every few seconds anyway. A separate heartbeat endpoint would double the
 * traffic for nothing.
 *
 * POLLING, NOT SSE, deliberately. Apache + mod_php holds one process per open
 * connection, so thirty analysts each keeping an EventSource open would sit on
 * thirty workers indefinitely — during an incident, when the rest of the app is
 * busiest. A short poll is stateless and degrades gracefully.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/i18n.php';
require_once '../../includes/warroom.php';

header('Content-Type: application/json');

// Analysts only — the war room is deliberately not exposed to portal users.
requireModuleAccessJson('war-room');

try {
    $conn      = connectToDatabase();
    $analystId = (int) $_SESSION['analyst_id'];
    I18n::initFromSession();

    $teamId = (isset($_GET['team_id']) && $_GET['team_id'] !== '')
        ? (int) $_GET['team_id']
        : null;
    $sinceId = isset($_GET['since_id']) ? max(0, (int) $_GET['since_id']) : 0;

    // ⚠️ Checked on every read, not just when the channel list is rendered.
    // Guessing a team_id you are not a member of must not work.
    if (!warRoomCanAccessChannel($conn, $analystId, $teamId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No access to that channel']);
        exit;
    }

    warRoomTouchPresence($conn, $analystId, $teamId);

    echo json_encode([
        'success'  => true,
        'messages' => warRoomMessages($conn, $teamId, $sinceId),
        'present'  => warRoomPresent($conn, $analystId),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load messages']);
}
