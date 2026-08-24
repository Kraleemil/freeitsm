<?php
/**
 * API: Tasks — Reorder after drag-and-drop
 * POST — JSON body with {task_id, new_status, positions: [{id, board_position}, ...]}
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/tenancy.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('tasks');

$input = json_decode(file_get_contents('php://input'), true);
$taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
$newStatus = $input['new_status'] ?? '';
$positions = $input['positions'] ?? [];

if (!$taskId || !$newStatus) {
    echo json_encode(['success' => false, 'error' => 'Missing task_id or new_status']);
    exit;
}

try {
    $conn = connectToDatabase();

    // 🔒 Company scope. reorder.php deliberately stays off TasksService (the UI
    // sends client-computed positions where the API re-packs server-side), so it
    // needs its own gate rather than inheriting the service's.
    if (!analystCanAccessTask($conn, (int) $_SESSION['analyst_id'], $taskId)) {
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    $conn->beginTransaction();

    // Resolve new status name -> id and decide whether to stamp completed_datetime
    $stsStmt = $conn->prepare("SELECT id, is_closed FROM task_statuses WHERE name = ? LIMIT 1");
    $stsStmt->execute([$newStatus]);
    $sts = $stsStmt->fetch(PDO::FETCH_ASSOC);
    if (!$sts) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => "Unknown status: $newStatus"]);
        exit;
    }
    $newStatusId = (int)$sts['id'];
    $completedSql = $sts['is_closed']
        ? ", completed_datetime = COALESCE(completed_datetime, UTC_TIMESTAMP())"
        : ", completed_datetime = NULL";

    $stmt = $conn->prepare(
        "UPDATE tasks SET status_id = ?, updated_datetime = UTC_TIMESTAMP(){$completedSql} WHERE id = ?"
    );
    $stmt->execute([$newStatusId, $taskId]);

    // Update board positions for all tasks in the affected columns.
    //
    // ⚠️ These ids come straight from the browser and are NOT the task that was
    // dragged, so gating $taskId above does not cover them: a crafted request
    // could otherwise renumber another company's board. The scope predicate is
    // carried into the UPDATE itself, so an out-of-scope id matches no row and
    // changes nothing, rather than being rejected noisily — the client is sending
    // a whole column's worth of positions and a partial column is not an error.
    [$posTenantSql, $posTenantArgs] = activeTenantFilter($conn, (int) $_SESSION['analyst_id'], '');
    $posStmt = $conn->prepare("UPDATE tasks SET board_position = ? WHERE id = ?{$posTenantSql}");
    foreach ($positions as $pos) {
        $posStmt->execute(array_merge([(int)$pos['board_position'], (int)$pos['id']], $posTenantArgs));
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Task reordered']);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
