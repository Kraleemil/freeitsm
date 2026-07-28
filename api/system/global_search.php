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

    // Digits pulled from the query, used where a record's reference is derived
    // from its id rather than stored (changes: "CHG-0047" isn't a column, so
    // searching "47" or "CHG-47" must match id 47).
    $digits = preg_replace('/\D+/', '', $q);

    // --- Changes: by title, or by id via its CHG-#### reference ----------
    if ($can('changes')) {
        try {
            [$tSql, $tArgs] = activeTenantFilter($conn, $analystId, 'c');
            $idClause = $digits !== '' ? ' OR c.id = ?' : '';
            $sql = "SELECT c.id, c.title
                      FROM changes c
                     WHERE (c.title LIKE ?" . $idClause . ")" . $tSql . "
                     ORDER BY c.modified_datetime DESC
                     LIMIT " . $perType;
            $params = $digits !== '' ? [$like, (int) $digits] : [$like];
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_merge($params, $tArgs));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $results[] = [
                    'type'     => 'change',
                    'module'   => 'changes',
                    'id'       => (int) $r['id'],
                    'title'    => $r['title'],
                    'subtitle' => sprintf('CHG-%04d', (int) $r['id']),
                    'url'      => 'change-management/?change_id=' . (int) $r['id'],
                ];
            }
        } catch (Exception $e) { /* table not ready — no change results */ }
    }

    // --- Problems: by reference or title --------------------------------
    if ($can('problems')) {
        try {
            [$tSql, $tArgs] = ticketTenantFilter($conn, $analystId, 'p');
            $sql = "SELECT p.id, p.problem_number, p.title
                      FROM problems p
                     WHERE (p.problem_number LIKE ? OR p.title LIKE ?)" . $tSql . "
                     ORDER BY p.updated_datetime DESC
                     LIMIT " . $perType;
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_merge([$like, $like], $tArgs));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $results[] = [
                    'type'     => 'problem',
                    'module'   => 'problems',
                    'id'       => (int) $r['id'],
                    'title'    => $r['title'],
                    'subtitle' => (string) ($r['problem_number'] ?? ''),
                    'url'      => 'problem-management/?problem_id=' . (int) $r['id'],
                ];
            }
        } catch (Exception $e) { /* table not ready — no problem results */ }
    }

    // --- Knowledge articles: by title -----------------------------------
    // knowledgeTenantFilter, NOT activeTenantFilter — for Knowledge a NULL
    // tenant means "shared with every company", the opposite of tickets/assets.
    // Archived articles are excluded; unpublished drafts are kept (analysts
    // should still find their own work-in-progress).
    if ($can('knowledge')) {
        try {
            [$tSql, $tArgs] = knowledgeTenantFilter($conn, $analystId, 'a');
            $sql = "SELECT a.id, a.title
                      FROM knowledge_articles a
                     WHERE a.title LIKE ?
                       AND (a.is_archived = 0 OR a.is_archived IS NULL)" . $tSql . "
                     ORDER BY a.modified_datetime DESC
                     LIMIT " . $perType;
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_merge([$like], $tArgs));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $results[] = [
                    'type'     => 'knowledge',
                    'module'   => 'knowledge',
                    'id'       => (int) $r['id'],
                    'title'    => $r['title'],
                    'subtitle' => '',
                    'url'      => 'knowledge/?article=' . (int) $r['id'],
                ];
            }
        } catch (Exception $e) { /* table not ready — no knowledge results */ }
    }

    // --- Contracts: by reference or title -------------------------------
    // Contracts are install-wide (no tenant_id column), so there is no company
    // scope to apply here.
    if ($can('contracts')) {
        try {
            $sql = "SELECT k.id, k.contract_number, k.title
                      FROM contracts k
                     WHERE (k.contract_number LIKE ? OR k.title LIKE ?)
                     ORDER BY k.title
                     LIMIT " . $perType;
            $stmt = $conn->prepare($sql);
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $results[] = [
                    'type'     => 'contract',
                    'module'   => 'contracts',
                    'id'       => (int) $r['id'],
                    'title'    => $r['title'],
                    'subtitle' => (string) ($r['contract_number'] ?? ''),
                    'url'      => 'contracts/view.php?id=' . (int) $r['id'],
                ];
            }
        } catch (Exception $e) { /* table not ready — no contract results */ }
    }

    echo json_encode(['success' => true, 'results' => $results]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
