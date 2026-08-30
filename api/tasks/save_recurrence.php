<?php
/**
 * API: Tasks — set, change or remove the recurrence on a task (#94).
 *
 * POST { task_id, ...rule }   creates or updates the series this task belongs to
 * POST { task_id, off: true } stops it repeating, leaving every task already made
 *
 * Stopping a series never deletes anything. Occurrences that have already been
 * created are real work, some of it done; switching the repeat off means "no
 * more of these", not "pretend the last six months did not happen".
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/services/task_recurrence.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('tasks');

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$taskId = (int)($in['task_id'] ?? 0);
if ($taskId <= 0) {
    echo json_encode(['success' => false, 'error' => 'A task is required']);
    exit;
}

try {
    $conn = connectToDatabase();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $t = $conn->prepare("SELECT id, due_date, recurrence_id, parent_task_id FROM tasks WHERE id = ?");
    $t->execute([$taskId]);
    $task = $t->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        echo json_encode(['success' => false, 'error' => 'No such task']);
        exit;
    }
    // A subtask repeating on its own would produce orphans with a parent that
    // was never repeated. The series belongs to the piece of work, not a step
    // inside it.
    if (!empty($task['parent_task_id'])) {
        echo json_encode(['success' => false, 'error' => 'A subtask cannot repeat on its own. Set the recurrence on the task it belongs to.']);
        exit;
    }

    // ---- Switching it off -------------------------------------------------
    if (!empty($in['off'])) {
        if (!empty($task['recurrence_id'])) {
            $conn->prepare("UPDATE task_recurrences SET is_active = 0, updated_datetime = UTC_TIMESTAMP() WHERE id = ?")
                 ->execute([(int)$task['recurrence_id']]);
        }
        echo json_encode(['success' => true, 'recurrence' => null]);
        exit;
    }

    // ---- Validate ---------------------------------------------------------
    // Shared with the preview endpoint. Two places sanitising the same input
    // differently is how a preview ends up showing dates the worker will not
    // produce, so there is exactly one home for it.
    $rule = TaskRecurrence::ruleFromInput($in);
    $mode = $rule['mode'];

    // A rule that cannot produce a date is refused HERE rather than saved and
    // found to be inert later. The engine is the authority on that, so ask it.
    $anchor = $task['due_date'] ?: gmdate('Y-m-d');
    $probe  = TaskRecurrence::nextDate($rule, $anchor);
    if ($probe === null) {
        echo json_encode(['success' => false, 'error' => 'Those settings never produce another date. Check the day or weekday you have chosen.']);
        exit;
    }

    // For a fixed schedule the worker starts from the occurrence AFTER this one.
    // Seeding it with this task's own due date would have the first run produce
    // a duplicate of the task in front of you.
    $rule['next_due_date'] = $mode === 'schedule' ? $probe : null;

    $conn->beginTransaction();

    if (!empty($task['recurrence_id'])) {
        $sets = [];
        $args = [];
        foreach ($rule as $col => $val) { $sets[] = "`$col` = ?"; $args[] = $val; }
        $sets[] = 'is_active = 1';
        $sets[] = 'updated_datetime = UTC_TIMESTAMP()';
        $args[] = (int)$task['recurrence_id'];
        $conn->prepare("UPDATE task_recurrences SET " . implode(', ', $sets) . " WHERE id = ?")->execute($args);
        $recurrenceId = (int)$task['recurrence_id'];
    } else {
        $cols = array_keys($rule);
        $conn->prepare(
            "INSERT INTO task_recurrences (`" . implode('`, `', $cols) . "`, created_by_id, created_datetime, updated_datetime)
             VALUES (" . rtrim(str_repeat('?, ', count($cols)), ', ') . ", ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        )->execute(array_merge(array_values($rule), [(int)$_SESSION['analyst_id']]));
        $recurrenceId = (int)$conn->lastInsertId();

        // This task becomes the first occurrence AND the master of the series.
        $conn->prepare("UPDATE tasks SET recurrence_id = ?, recurrence_master_id = COALESCE(recurrence_master_id, id) WHERE id = ?")
             ->execute([$recurrenceId, $taskId]);
    }

    $conn->commit();

    echo json_encode([
        'success'    => true,
        'recurrence' => ['id' => $recurrenceId] + $rule,
        'next_date'  => $probe,
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
