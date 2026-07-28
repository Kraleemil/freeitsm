<?php
/**
 * API Endpoint: Get list of emails
 * Returns emails from the database for display in inbox
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/ticket_snooze.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    // Get filter parameters
    $department_id = $_GET['department_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $assignee_id = $_GET['assignee_id'] ?? null;

    // Connect to database
    $conn = connectToDatabase();

    // Pre-upgrade installs have no snooze columns; the busiest query in the
    // product must not name them until Database Verification has run.
    $snoozeReady = snoozeSchemaReady($conn);
    $snoozeCols  = $snoozeReady ? "t.snoozed_until, t.snooze_reason," : "NULL AS snoozed_until, NULL AS snooze_reason,";

    // Build query with filters - show only the most recent email per ticket
    $sql = "WITH LatestEmails AS (
                SELECT
                    e.id,
                    e.from_address,
                    e.from_name,
                    e.received_datetime,
                    e.body_preview,
                    e.is_read,
                    e.has_attachments,
                    e.importance,
                    e.ticket_id,
                    ROW_NUMBER() OVER (PARTITION BY e.ticket_id ORDER BY e.received_datetime DESC) as rn
                FROM emails e
            )
            SELECT
                le.id,
                le.from_address,
                le.from_name,
                le.received_datetime,
                le.body_preview,
                le.is_read,
                le.has_attachments,
                le.importance,
                le.ticket_id,
                t.ticket_number,
                t.subject,
                ts.name AS status,
                t.department_id,
                t.assigned_analyst_id,
                $snoozeCols
                tp.name AS priority,
                (SELECT COUNT(*) FROM emails WHERE ticket_id = t.id) as email_count
            FROM LatestEmails le
            INNER JOIN tickets t ON le.ticket_id = t.id
            LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
            LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
            WHERE le.rn = 1";

    $params = [];

    if ($department_id === 'unassigned') {
        $sql .= " AND t.department_id IS NULL";
    } elseif ($department_id !== null && $department_id !== '') {
        $sql .= " AND t.department_id = ?";
        $params[] = $department_id;
    }

    if ($assignee_id === 'unassigned') {
        $sql .= " AND t.assigned_analyst_id IS NULL";
    } elseif ($assignee_id !== null && $assignee_id !== '') {
        $sql .= " AND t.assigned_analyst_id = ?";
        $params[] = $assignee_id;
    }

    if ($status !== null && $status !== '') {
        $sql .= " AND ts.name = ?";
        $params[] = $status;
    }

    // Multi-tenancy: scope the list to the analyst's active company (no-op at N=1).
    list($tenantSql, $tenantParams) = ticketTenantFilter($conn, (int)$_SESSION['analyst_id']);
    $sql .= $tenantSql;
    $params = array_merge($params, $tenantParams);

    // Trash: the Trash folder shows ONLY soft-deleted tickets; every other view
    // hides them.
    $sql .= !empty($_GET['trashed'])
        ? " AND t.deleted_datetime IS NOT NULL"
        : " AND t.deleted_datetime IS NULL";

    // Snoozed (#933): sleeping tickets leave every folder and gather in their own,
    // exactly as trashed ones do. The Trash view is excepted so a ticket that was
    // snoozed and then binned is still findable where it was put last.
    if (!empty($_GET['snoozed'])) {
        $sql .= snoozeOnlySql($conn, 't');
    } elseif (empty($_GET['trashed'])) {
        $sql .= snoozeHiddenSql($conn, 't');
    }

    // The Snoozed folder sorts by when each ticket comes back — the only order
    // that answers the question you open that folder to ask ("what's next?").
    $sql .= (!empty($_GET['snoozed']) && $snoozeReady)
        ? " ORDER BY t.snoozed_until ASC"
        : " ORDER BY le.received_datetime DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates for display
    foreach ($emails as &$email) {
        if ($email['received_datetime']) {
            $email['received_datetime'] = date('Y-m-d\TH:i:s', strtotime($email['received_datetime']));
        }
        // Convert bit fields to boolean
        $email['is_read'] = (bool)$email['is_read'];
        $email['has_attachments'] = (bool)$email['has_attachments'];
        // Only report a snooze that is still in the future — an expired one has
        // already woken, and a row badge saying "until last Tuesday" is noise.
        if (!empty($email['snoozed_until']) && strtotime($email['snoozed_until'] . ' UTC') <= time()) {
            $email['snoozed_until'] = null;
            $email['snooze_reason'] = null;
        }
    }

    echo json_encode([
        'success' => true,
        'emails' => $emails,
        'count' => count($emails)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
