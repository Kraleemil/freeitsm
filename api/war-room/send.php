<?php
/**
 * API: War room — post a message.
 *
 * POST JSON { team_id: <int|null>, body: "<text>" }
 *
 * Returns the message id. The page does not render the response — it lets the
 * next poll bring the message back, so what you see is what everyone else sees
 * rather than an optimistic local copy that might not have saved.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/i18n.php';
require_once '../../includes/warroom.php';

header('Content-Type: application/json');

requireModuleAccessJson('war-room');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

try {
    $conn      = connectToDatabase();
    $analystId = (int) $_SESSION['analyst_id'];
    I18n::initFromSession();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];

    $teamId = (isset($input['team_id']) && $input['team_id'] !== null && $input['team_id'] !== '')
        ? (int) $input['team_id']
        : null;
    $body = isset($input['body']) ? (string) $input['body'] : '';

    // Same check as the read path — posting into a channel you cannot see would
    // otherwise be a way to talk to a team you are not in.
    if (!warRoomCanAccessChannel($conn, $analystId, $teamId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No access to that channel']);
        exit;
    }

    $id = warRoomSend($conn, $analystId, $teamId, $body);
    if ($id === 0) {
        echo json_encode(['success' => false, 'error' => 'Empty message']);
        exit;
    }

    echo json_encode(['success' => true, 'id' => $id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not send']);
}
