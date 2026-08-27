<?php
/**
 * API Endpoint: who may read a Knowledge folder or article.
 * Actions: get, set_mode, add, remove, search_principals
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE MODEL, RESTATED HERE BECAUSE THIS FILE IS WHERE IT IS EDITED
 *
 * An object is Open or Restricted, and the list means the opposite thing in each:
 *
 *   Open       everyone (subject to the other axes), MINUS anyone on the list
 *   Restricted nobody, PLUS anyone on the list
 *
 * The polarity lives on the OBJECT, never on the rows, so allow and deny can
 * never coexist and there is no precedence rule to explain. That is the whole
 * reason knowledge_acl has no allow/deny column.
 *
 * ⚠️ CHANGING THE POLARITY WIPES THE LIST, and says how many rows it is about to
 * drop. Keeping them dormant would leave invisible entries that spring back on
 * the next flip — the "an unloaded checkbox looks exactly like OFF" failure in
 * another costume.
 *
 * ⚠️ EDITING PERMISSIONS REQUIRES THE ADMINISTRATOR FLOOR. Anyone who can merely
 * READ a folder must not be able to change who else can: that would let the
 * first person granted access hand it to anybody, and the access list would mean
 * nothing by the end of the week.
 * ─────────────────────────────────────────────────────────────────────────────
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/knowledge/visibility.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('knowledge');

$analystId = (int)$_SESSION['analyst_id'];
$method    = $_SERVER['REQUEST_METHOD'];
$input     = [];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'get';
} else {
    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? '';
}

try {
    $conn   = connectToDatabase();
    $viewer = KnowledgeViewer::forAnalyst($conn, $analystId);

    // Reading the list is gated too: knowing WHO is excluded from a folder is
    // itself information about the folder and about those people.
    if (!knowledgeViewerHasAdminFloor($conn, $viewer)) {
        echo json_encode(['success' => false, 'error' => 'You do not have permission to manage access.']);
        exit;
    }

    switch ($action) {
        case 'get':               handleGet($conn, $_GET); break;
        case 'set_mode':          handleSetMode($conn, $analystId, $input); break;
        case 'add':               handleAdd($conn, $analystId, $input); break;
        case 'remove':            handleRemove($conn, $analystId, $input); break;
        case 'search_principals': handleSearch($conn, $_GET); break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/** 'folder' or 'article', and an id that exists. Anything else is refused. */
function requireObject(PDO $conn, array $src): array
{
    $type = (string)($src['object_type'] ?? '');
    $id   = (int)($src['object_id'] ?? 0);
    if (!in_array($type, ['folder', 'article'], true) || $id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Unknown object']);
        exit;
    }
    $table = $type === 'folder' ? 'knowledge_folders' : 'knowledge_articles';
    $st = $conn->prepare("SELECT id, is_restricted, inherit_permissions FROM {$table} WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Not found']);
        exit;
    }
    return [$type, $id, $table, $row];
}

/**
 * The current state of one object's access.
 *
 * Returns the names as well as the ids: a list of "analyst 7, team 3" is not
 * something anybody can check, and a permissions screen you cannot read is a
 * permissions screen nobody will audit.
 */
function handleGet(PDO $conn, array $src): void
{
    [$type, $id, , $row] = requireObject($conn, $src);

    $st = $conn->prepare("SELECT id, principal_type, principal_id FROM knowledge_acl WHERE object_type = ? AND object_id = ? ORDER BY principal_type, principal_id");
    $st->execute([$type, $id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $entries = [];
    foreach ($rows as $r) {
        $entries[] = [
            'id'             => (int)$r['id'],
            'principal_type' => $r['principal_type'],
            'principal_id'   => (int)$r['principal_id'],
            'name'           => principalName($conn, $r['principal_type'], (int)$r['principal_id']),
        ];
    }

    echo json_encode([
        'success'       => true,
        'is_restricted' => (int)$row['is_restricted'],
        'inherits'      => (int)$row['inherit_permissions'],
        'entries'       => $entries,
    ]);
}

/**
 * Resolve a principal to something a human can check.
 *
 * A principal whose row has since been deleted is named as such rather than
 * shown blank: an unexplained empty line in a permissions list is the sort of
 * thing people leave alone forever because they dare not remove it.
 */
function principalName(PDO $conn, string $type, int $id): string
{
    $map = [
        'analyst'    => ['analysts', 'full_name'],
        'team'       => ['teams', 'name'],
        'user'       => ['users', 'display_name'],
        'user_group' => ['knowledge_user_groups', 'name'],
    ];
    if (!isset($map[$type])) return '(unknown)';
    [$table, $col] = $map[$type];
    try {
        $st = $conn->prepare("SELECT {$col} FROM {$table} WHERE id = ?");
        $st->execute([$id]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null || $v === '') {
            return '(deleted — safe to remove)';
        }
        return (string)$v;
    } catch (PDOException $e) {
        return '(unknown)';
    }
}

/**
 * Set Open/Restricted and the inherit tickbox.
 *
 * ⚠️ A POLARITY CHANGE DELETES THE LIST. See the header. The count of what will
 * be dropped is returned so the interface can say so BEFORE asking again, rather
 * than reporting a loss after the fact.
 */
function handleSetMode(PDO $conn, int $analystId, array $in): void
{
    [$type, $id, $table, $row] = requireObject($conn, $in);

    $restricted = array_key_exists('is_restricted', $in) ? (int)(bool)$in['is_restricted'] : (int)$row['is_restricted'];
    $inherits   = array_key_exists('inherits', $in)      ? (int)(bool)$in['inherits']      : (int)$row['inherit_permissions'];

    $flipped = $restricted !== (int)$row['is_restricted'];
    $dropped = 0;
    if ($flipped) {
        $st = $conn->prepare("SELECT COUNT(*) FROM knowledge_acl WHERE object_type = ? AND object_id = ?");
        $st->execute([$type, $id]);
        $dropped = (int)$st->fetchColumn();
        $conn->prepare("DELETE FROM knowledge_acl WHERE object_type = ? AND object_id = ?")->execute([$type, $id]);
    }

    $conn->prepare("UPDATE {$table} SET is_restricted = ?, inherit_permissions = ? WHERE id = ?")
         ->execute([$restricted, $inherits, $id]);

    knowledgeAclResetCaches();
    permAudit($conn, $type, $id, $analystId, [
        'is_restricted' => $restricted,
        'inherits'      => $inherits,
        'entries_dropped_by_polarity_change' => $dropped,
    ]);
    echo json_encode(['success' => true, 'dropped' => $dropped]);
}

function handleAdd(PDO $conn, int $analystId, array $in): void
{
    [$type, $id] = requireObject($conn, $in);
    $ptype = (string)($in['principal_type'] ?? '');
    $pid   = (int)($in['principal_id'] ?? 0);
    if (!in_array($ptype, ['analyst', 'team', 'user', 'user_group'], true) || $pid <= 0) {
        echo json_encode(['success' => false, 'error' => 'Unknown person or group']);
        return;
    }
    try {
        $conn->prepare(
            "INSERT INTO knowledge_acl (object_type, object_id, principal_type, principal_id, created_by_id, created_datetime)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())"
        )->execute([$type, $id, $ptype, $pid, $analystId]);
    } catch (PDOException $e) {
        // The unique key. Already listed is not an error worth showing — the
        // state the caller wanted is the state that exists.
        if (strpos($e->getMessage(), '1062') === false && $e->getCode() !== '23000') throw $e;
    }
    knowledgeAclResetCaches();
    permAudit($conn, $type, $id, $analystId, ['added' => $ptype . ':' . $pid]);
    echo json_encode(['success' => true]);
}

function handleRemove(PDO $conn, int $analystId, array $in): void
{
    [$type, $id] = requireObject($conn, $in);
    $rowId = (int)($in['entry_id'] ?? 0);
    if ($rowId <= 0) { echo json_encode(['success' => false, 'error' => 'Nothing to remove']); return; }

    $conn->prepare("DELETE FROM knowledge_acl WHERE id = ? AND object_type = ? AND object_id = ?")
         ->execute([$rowId, $type, $id]);

    knowledgeAclResetCaches();
    permAudit($conn, $type, $id, $analystId, ['removed_entry' => $rowId]);

    // Say when the object has just become unreachable to everyone but an
    // administrator. It is a legal state — "restricted to nobody" — but it is
    // almost never what somebody meant to do, and finding out later means
    // finding out from a colleague who cannot open their own document.
    $st = $conn->prepare("SELECT COUNT(*) FROM knowledge_acl WHERE object_type = ? AND object_id = ?");
    $st->execute([$type, $id]);
    $left = (int)$st->fetchColumn();

    $table = $type === 'folder' ? 'knowledge_folders' : 'knowledge_articles';
    $st = $conn->prepare("SELECT is_restricted FROM {$table} WHERE id = ?");
    $st->execute([$id]);
    $restricted = (int)$st->fetchColumn();

    echo json_encode([
        'success'          => true,
        'now_unreachable'  => ($restricted === 1 && $left === 0),
    ]);
}

/**
 * Find people, teams and groups to add.
 *
 * All four kinds in one search rather than a type picker first: you know the
 * NAME of who you want, not which of four tables they live in.
 */
function handleSearch(PDO $conn, array $src): void
{
    $q = trim((string)($src['q'] ?? ''));
    if (mb_strlen($q) < 2) { echo json_encode(['success' => true, 'results' => []]); return; }
    $like = '%' . $q . '%';
    $out  = [];

    $add = function (string $sql, string $type, string $label) use ($conn, $like, &$out) {
        try {
            $st = $conn->prepare($sql);
            $st->execute([$like]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = ['type' => $type, 'id' => (int)$r['id'], 'name' => (string)$r['name'], 'kind' => $label];
            }
        } catch (PDOException $e) { /* table absent — that kind simply has no matches */ }
    };

    $add("SELECT id, full_name AS name FROM analysts WHERE is_active = 1 AND full_name LIKE ? ORDER BY full_name LIMIT 8", 'analyst', 'Analyst');
    $add("SELECT id, name FROM teams WHERE is_active = 1 AND name LIKE ? ORDER BY name LIMIT 8", 'team', 'Team');
    $add("SELECT id, COALESCE(NULLIF(display_name,''), email, username) AS name FROM users WHERE COALESCE(NULLIF(display_name,''), email, username) LIKE ? ORDER BY name LIMIT 8", 'user', 'Portal user');
    $add("SELECT id, name FROM knowledge_user_groups WHERE is_active = 1 AND name LIKE ? ORDER BY name LIMIT 8", 'user_group', 'Group');

    echo json_encode(['success' => true, 'results' => $out]);
}

/** Permission changes are the rows people actually come looking for. Never silent. */
function permAudit(PDO $conn, string $type, int $id, int $analystId, array $detail): void
{
    try {
        $conn->prepare(
            "INSERT INTO knowledge_audit (object_type, object_id, action, analyst_id, detail, ip_address)
             VALUES (?, ?, 'permissions', ?, ?, ?)"
        )->execute([$type, $id, $analystId, json_encode($detail), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (PDOException $e) {
        error_log('knowledge audit: could not record a permission change on ' . $type . ' ' . $id . ' — ' . $e->getMessage());
    }
}
