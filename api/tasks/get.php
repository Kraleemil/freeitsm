<?php
/**
 * API: Tasks — Get single task with subtasks and comments
 * GET ?id=N
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/entity_links.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing task ID']);
    exit;
}

try {
    $conn = connectToDatabase();

    // 🔒 Company scope. Framed as not-found so the existence of another company's
    // task is not confirmed. This gate also covers the comments and subtasks read
    // further down, since they are fetched by this task's id.
    if (!analystCanAccessTask($conn, (int) $_SESSION['analyst_id'], $id)) {
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    // Get the task
    $stmt = $conn->prepare(
        "SELECT t.*,
                ts.name AS status, ts.is_closed AS status_is_closed, ts.colour AS status_colour,
                tp.name AS priority, tp.colour AS priority_colour,
                a.full_name AS analyst_name, tm.name AS team_name,
                ca.full_name AS created_by_name,
                tk.ticket_number, tk.subject AS ticket_subject,
                ch.title AS change_title
         FROM tasks t
         LEFT JOIN task_statuses   ts ON ts.id = t.status_id
         LEFT JOIN task_priorities tp ON tp.id = t.priority_id
         LEFT JOIN analysts a ON t.assigned_analyst_id = a.id
         LEFT JOIN teams tm ON t.assigned_team_id = tm.id
         LEFT JOIN analysts ca ON t.created_by_id = ca.id
         LEFT JOIN tickets tk ON t.ticket_id = tk.id
         LEFT JOIN changes ch ON t.change_id = ch.id
         WHERE t.id = ?"
    );
    $stmt->execute([$id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    // Deep links for the records this task points at (GH #91). Built HERE, from
    // the one resolver, rather than assembled in JavaScript — a second copy of
    // the record→URL map in the client is precisely the drift entity_links.php
    // exists to end. NULL when there is no link, which the client renders as
    // plain text rather than a dead anchor.
    $task['ticket_url'] = $task['ticket_id'] ? entityLink('ticket', (int) $task['ticket_id']) : null;
    $task['change_url'] = $task['change_id'] ? entityLink('change', (int) $task['change_id']) : null;

    // Get parent task info if this is a subtask
    if ($task['parent_task_id']) {
        $stmt = $conn->prepare("SELECT id, title FROM tasks WHERE id = ?");
        $stmt->execute([$task['parent_task_id']]);
        $task['parent_task'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get subtasks with assignee names
    $stmt = $conn->prepare(
        "SELECT t.id, t.title,
                ts.name AS status, ts.is_closed AS status_is_closed,
                tp.name AS priority, tp.colour AS priority_colour,
                t.due_date,
                t.assigned_analyst_id, t.board_position, t.completed_datetime,
                a.full_name AS analyst_name
         FROM tasks t
         LEFT JOIN task_statuses   ts ON ts.id = t.status_id
         LEFT JOIN task_priorities tp ON tp.id = t.priority_id
         LEFT JOIN analysts a ON t.assigned_analyst_id = a.id
         WHERE t.parent_task_id = ?
         ORDER BY t.board_position ASC, t.created_datetime ASC"
    );
    $stmt->execute([$id]);
    $task['subtasks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get comments
    $stmt = $conn->prepare(
        "SELECT c.id, c.comment, c.created_datetime, a.full_name AS analyst_name
         FROM task_comments c
         JOIN analysts a ON c.analyst_id = a.id
         WHERE c.task_id = ?
         ORDER BY c.created_datetime ASC"
    );
    $stmt->execute([$id]);
    $task['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get tags
    $stmt = $conn->prepare(
        "SELECT tg.id, tg.name, tg.colour
         FROM task_tag_map m
         JOIN task_tags tg ON tg.id = m.tag_id
         WHERE m.task_id = ?
         ORDER BY tg.display_order, tg.name"
    );
    $stmt->execute([$id]);
    $task['tags'] = array_map(function ($tg) {
        $tg['id'] = (int)$tg['id'];
        return $tg;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['success' => true, 'task' => $task]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
