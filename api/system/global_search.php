<?php
/**
 * API: Global search for the command palette (⌘/Ctrl-K).
 *
 * A single aggregator that fans out across the entity types worth jumping to
 * from anywhere — tickets, CMDB configuration items and assets — and returns a
 * flat, typed result list the palette renders directly.
 *
 * Every source is gated two ways, so the palette can never surface something the
 * analyst couldn't otherwise reach:
 *   1. Module access — skipped entirely unless the module is in the analyst's
 *      allowed_modules (the same list the waffle launcher honours). Reads aren't
 *      capability-gated in FreeITSM, so module membership is the right gate here.
 *   2. Company scope — each query runs through the module's own tenancy filter
 *      (ticketTenantFilter / activeTenantFilter), exactly as that module's own
 *      list endpoint does, so results respect the active company.
 *
 * Each source is wrapped in its own try/catch: on a part-migrated install a
 * missing table just yields no rows for that type rather than failing the whole
 * search.
 *
 * Query params:
 *   q — text to match (required, min 2 chars); matched as a substring.
 *
 * Returns: { success, results: [ { type, module, id, title, subtitle, url } ] }
 * where `url` is relative to BASE_URL (the client prefixes it).
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/rbac.php';
require_once '../../includes/tenancy.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$analystId = (int) $_SESSION['analyst_id'];
$q = trim((string) ($_GET['q'] ?? ''));

// Min 2 chars — one letter matches half the database and isn't a useful jump.
if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

// null allowed_modules = unrestricted (all modules). Matches the waffle panel.
$allowed = $_SESSION['allowed_modules'] ?? null;
$can = function (string $key) use ($allowed): bool {
    return $allowed === null || in_array($key, $allowed, true);
};

$like = '%' . $q . '%';
$perType = 6;   // a handful per source keeps the palette scannable
$results = [];

try {
    $conn = connectToDatabase();

    // --- Tickets: by reference or subject -------------------------------
    if ($can('tickets')) {
        try {
            [$tSql, $tArgs] = ticketTenantFilter($conn, $analystId, 't');
            $sql = "SELECT t.id, t.ticket_number, t.subject
                      FROM tickets t
                     WHERE t.deleted_datetime IS NULL
                       AND (t.ticket_number LIKE ? OR t.subject LIKE ?)" . $tSql . "
                     ORDER BY t.updated_datetime DESC
                     LIMIT " . $perType;
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_merge([$like, $like], $tArgs));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $results[] = [
                    'type'     => 'ticket',
                    'module'   => 'tickets',
                    'id'       => (int) $r['id'],
                    'title'    => $r['subject'],
                    'subtitle' => $r['ticket_number'],
                    'url'      => 'tickets/?ticket_id=' . (int) $r['id'],
                ];
            }
        } catch (Exception $e) { /* table not ready — no ticket results */ }
    }

    // --- CMDB configuration items: by name ------------------------------
    if ($can('cmdb')) {
        try {
            [$tSql, $tArgs] = activeTenantFilter($conn, $analystId, 'o');
            $sql = "SELECT o.id, o.name, c.name AS class_name
                      FROM cmdb_objects o
                      JOIN cmdb_classes c ON c.id = o.class_id
                     WHERE o.name LIKE ?" . $tSql . "
                     ORDER BY o.name
                     LIMIT " . $perType;
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_merge([$like], $tArgs));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $results[] = [
                    'type'     => 'ci',
                    'module'   => 'cmdb',
                    'id'       => (int) $r['id'],
                    'title'    => $r['name'],
                    'subtitle' => $r['class_name'],
                    'url'      => 'cmdb/object.php?id=' . (int) $r['id'],
                ];
            }
        } catch (Exception $e) { /* table not ready — no CI results */ }
    }

    // --- Assets: by hostname or service tag -----------------------------
    if ($can('assets')) {
        try {
            [$tSql, $tArgs] = activeTenantFilter($conn, $analystId, 'a');
            $sql = "SELECT a.id, a.hostname, a.service_tag
                      FROM assets a
                     WHERE (a.hostname LIKE ? OR a.service_tag LIKE ?)" . $tSql . "
                     ORDER BY a.hostname
                     LIMIT " . $perType;
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_merge([$like, $like], $tArgs));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $tag = trim((string) ($r['service_tag'] ?? ''));
                $results[] = [
                    'type'     => 'asset',
                    'module'   => 'assets',
                    'id'       => (int) $r['id'],
                    'title'    => $r['hostname'],
                    'subtitle' => $tag,
                    'url'      => 'asset-management/?asset_id=' . (int) $r['id'],
                ];
            }
        } catch (Exception $e) { /* table not ready — no asset results */ }
    }

    echo json_encode(['success' => true, 'results' => $results]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
