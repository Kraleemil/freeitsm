<?php
/**
 * LMS API: Get progress data for the admin dashboard
 * Joins assignments -> group members -> progress to show every analyst's status
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/rbac.php';
header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
// Everyone's progress across every assignment — the admin Progress tab. Managers
// only; a learner sees only their own courses, on the My Courses page.
requireCapabilityJson(Cap::LMS_MANAGE);

$conn = connectToDatabase();

$courseId = $_GET['course_id'] ?? '';
$groupId = $_GET['group_id'] ?? '';
$status = $_GET['status'] ?? '';
// One person's row across every course they have been assigned. It composes
// with the group filter rather than competing with it: the two narrow
// different joins, so picking an analyst who is not in the chosen group
// correctly returns nothing rather than quietly ignoring one of them.
$analystId = $_GET['analyst_id'] ?? '';

$sql = "SELECT DISTINCT
            a.id as analyst_id, a.full_name as analyst_name,
            c.id as course_id, c.title as course_title,
            g.id as group_id, g.name as group_name,
            ca.deadline,
            COALESCE(p.status, 'not_started') as status,
            p.score_raw, p.score_max, p.total_time,
            p.last_access, p.completion_datetime
        FROM lms_course_assignments ca
        JOIN lms_learning_groups g ON ca.group_id = g.id AND g.is_active = 1
        JOIN lms_learning_group_members m ON m.group_id = g.id
        JOIN analysts a ON m.analyst_id = a.id AND a.is_active = 1
        JOIN lms_courses c ON ca.course_id = c.id AND c.is_active = 1
        LEFT JOIN lms_progress p ON p.analyst_id = a.id AND p.course_id = c.id
        WHERE 1=1";

$params = [];

if ($courseId) {
    $sql .= " AND c.id = ?";
    $params[] = (int)$courseId;
}

if ($groupId) {
    $sql .= " AND g.id = ?";
    $params[] = (int)$groupId;
}

// The analyst list for the dropdown is built from the SAME joins with the
// course and group filters applied but NOT this one — otherwise choosing a
// person removes everyone else from the list you chose them from.
$analystListSql = $sql;
$analystListParams = $params;

if ($analystId) {
    $sql .= " AND a.id = ?";
    $params[] = (int)$analystId;
}

$sql .= " ORDER BY a.full_name, c.title";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Post-process: calculate overdue flag and filter by status
$now = new DateTime('now', new DateTimeZone('UTC'));
$filtered = [];

foreach ($rows as &$row) {
    $row['is_overdue'] = false;
    if (!empty($row['deadline'])) {
        $deadline = new DateTime($row['deadline']);
        if ($now > $deadline && !in_array($row['status'], ['completed', 'passed'])) {
            $row['is_overdue'] = true;
        }
    }

    // Apply status filter
    if ($status === 'overdue') {
        if (!$row['is_overdue']) continue;
    } elseif ($status && $row['status'] !== $status) {
        continue;
    }

    $filtered[] = $row;
}

// Who the current course/group selection covers, for the analyst dropdown.
$alStmt = $conn->prepare("SELECT DISTINCT a.id, a.full_name" . substr($analystListSql, strpos($analystListSql, ' FROM ')) . " ORDER BY a.full_name");
$alStmt->execute($analystListParams);
$analysts = $alStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $filtered, 'analysts' => $analysts]);
