<?php
/**
 * API Endpoint: Knowledge folders
 * Actions: list, create, rename, move, delete
 *
 * 🔑 THE TREE THIS RETURNS IS ALREADY FILTERED. A folder the caller cannot read
 * is not sent with a flag for the browser to hide — it is not sent at all.
 * Filtering in the client would mean the names had already crossed the wire,
 * and a folder name is exactly the kind of thing worth restricting ("Project
 * Nightingale", "Redundancies 2026").
 *
 * ⚠️ Readability is decided by includes/knowledge/visibility.php, the same file
 * that decides it for articles, so the tree and the article list can never
 * disagree about what exists.
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
    $action = $_GET['action'] ?? 'list';
} else {
    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? '';
}

try {
    $conn = connectToDatabase();

    switch ($action) {
        case 'list':   handleList($conn, $analystId); break;
        case 'create': handleCreate($conn, $analystId, $input); break;
        case 'rename': handleRename($conn, $analystId, $input); break;
        case 'move':   handleMove($conn, $analystId, $input); break;
        case 'delete': handleDelete($conn, $analystId, $input); break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * The folders this analyst may see, each with the number of articles they may
 * see inside it.
 *
 * ⚠️ THE COUNT USES THE SAME CLAUSE AS THE ARTICLE LIST. A folder that says "4"
 * and opens to show 2 is the tag-badge bug (#1212) about to happen again, and
 * for the same reason: two hand-written copies of one rule that were meant to
 * match. Both ask knowledgeVisibilitySql().
 */
function handleList(PDO $conn, int $analystId): void
{
    $viewer = KnowledgeViewer::forAnalyst($conn, $analystId);

    // 'unarchived' matches knowledge_articles.php exactly — drafts count, because
    // drafts are listed.
    [$visSql, $visParams] = knowledgeVisibilitySql($conn, $viewer, 'a', ['lifecycle' => 'unarchived']);

    $rows = $conn->query(
        "SELECT id, parent_id, name, is_restricted, inherit_permissions, owner_id, tenant_id
           FROM knowledge_folders ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Counts for every folder in one query rather than one query per folder.
    $counts = [];
    $st = $conn->prepare(
        "SELECT a.folder_id, COUNT(*) AS n FROM knowledge_articles a
          WHERE a.folder_id IS NOT NULL" . $visSql . " GROUP BY a.folder_id"
    );
    $st->execute($visParams);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $counts[(int)$r['folder_id']] = (int)$r['n'];

    // Articles at the root — the folder that is not a row.
    $st = $conn->prepare("SELECT COUNT(*) FROM knowledge_articles a WHERE a.folder_id IS NULL" . $visSql);
    $st->execute($visParams);
    $rootCount = (int)$st->fetchColumn();

    $folders = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        // A folder is listed only if this analyst may reach it — the same
        // question, asked of the same code, as an article inside it.
        if (!knowledgeFolderIsReadable($conn, $viewer, $id)) continue;
        $folders[] = [
            'id'            => $id,
            'parent_id'     => $r['parent_id'] === null ? null : (int)$r['parent_id'],
            'name'          => $r['name'],
            'is_restricted' => (int)$r['is_restricted'],
            'inherits'      => (int)$r['inherit_permissions'],
            'article_count' => $counts[$id] ?? 0,
        ];
    }

    // A folder whose parent was filtered out would be an orphan the tree cannot
    // place. Promote it to the root rather than dropping it: you are allowed to
    // see it, and hiding it because of where it sits would lose the document.
    $present = [];
    foreach ($folders as $f) $present[$f['id']] = true;
    foreach ($folders as &$f) {
        if ($f['parent_id'] !== null && !isset($present[$f['parent_id']])) {
            $f['parent_id'] = null;
            $f['detached']  = true;
        }
    }
    unset($f);

    echo json_encode([
        'success'    => true,
        'folders'    => $folders,
        'root_count' => $rootCount,
        'can_manage' => knowledgeViewerHasAdminFloor($conn, $viewer),
    ]);
}

/** May this viewer reach this folder? Delegates; never re-implements the rule. */
function knowledgeFolderIsReadable(PDO $conn, KnowledgeViewer $viewer, int $folderId): bool
{
    if ($viewer->isUnrestricted()) return true;
    if (!knowledgeAclHasAnyRows($conn)) return true;
    if (knowledgeViewerHasAdminFloor($conn, $viewer)) return true;
    return knowledgeFolderReadable(
        $folderId,
        knowledgeFolderIndex($conn),
        knowledgeAclIndex($conn),
        knowledgeViewerPrincipals($conn, $viewer),
        knowledgeFolderPermissionModel($conn)
    );
}

/** Refuse anything the caller may not reach, in the same words as "not found". */
function requireFolderAccess(PDO $conn, int $analystId, ?int $folderId): void
{
    if ($folderId === null) return;   // the root: everyone has it
    $viewer = KnowledgeViewer::forAnalyst($conn, $analystId);
    $exists = $conn->prepare("SELECT 1 FROM knowledge_folders WHERE id = ?");
    $exists->execute([$folderId]);
    if (!$exists->fetchColumn() || !knowledgeFolderIsReadable($conn, $viewer, $folderId)) {
        echo json_encode(['success' => false, 'error' => 'Folder not found']);
        exit;
    }
}

function handleCreate(PDO $conn, int $analystId, array $in): void
{
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') { echo json_encode(['success' => false, 'error' => 'A name is required']); return; }
    if (mb_strlen($name) > 255) { echo json_encode(['success' => false, 'error' => 'That name is too long']); return; }

    $parent = isset($in['parent_id']) && $in['parent_id'] !== null && $in['parent_id'] !== ''
        ? (int)$in['parent_id'] : null;
    requireFolderAccess($conn, $analystId, $parent);

    // A new folder INHERITS and restricts nothing. Anything else would mean
    // creating a folder silently changed who could see its contents.
    $conn->prepare(
        "INSERT INTO knowledge_folders (parent_id, name, is_restricted, inherit_permissions, created_by_id, owner_id, created_datetime, modified_datetime)
         VALUES (?, ?, 0, 1, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
    )->execute([$parent, $name, $analystId, $analystId]);
    $id = (int)$conn->lastInsertId();

    knowledgeAclResetCaches();
    knowledgeAudit($conn, 'folder', $id, 'create', $analystId, ['name' => $name, 'parent_id' => $parent]);
    echo json_encode(['success' => true, 'id' => $id]);
}

function handleRename(PDO $conn, int $analystId, array $in): void
{
    $id   = (int)($in['id'] ?? 0);
    $name = trim((string)($in['name'] ?? ''));
    if (!$id || $name === '') { echo json_encode(['success' => false, 'error' => 'A folder and a name are required']); return; }
    requireFolderAccess($conn, $analystId, $id);

    $conn->prepare("UPDATE knowledge_folders SET name = ?, modified_datetime = UTC_TIMESTAMP() WHERE id = ?")
         ->execute([$name, $id]);
    knowledgeAudit($conn, 'folder', $id, 'edit', $analystId, ['name' => $name]);
    echo json_encode(['success' => true]);
}

/**
 * Re-parent a folder.
 *
 * ⚠️ REFUSES A CYCLE. Dropping a folder into its own descendant would detach the
 * whole branch from the root: every folder in it still exists, still holds
 * documents, and is reachable from nothing. The permission walk survives a cycle
 * (it fails closed) but the tree does not, so this is refused at the door rather
 * than tolerated later.
 */
function handleMove(PDO $conn, int $analystId, array $in): void
{
    $id     = (int)($in['id'] ?? 0);
    $parent = isset($in['parent_id']) && $in['parent_id'] !== null && $in['parent_id'] !== ''
        ? (int)$in['parent_id'] : null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'A folder is required']); return; }

    requireFolderAccess($conn, $analystId, $id);
    requireFolderAccess($conn, $analystId, $parent);

    if ($parent === $id) { echo json_encode(['success' => false, 'error' => 'A folder cannot be inside itself']); return; }

    $folders = knowledgeFolderIndex($conn);
    $cursor  = $parent;
    $seen    = [];
    while ($cursor !== null && isset($folders[$cursor])) {
        if ($cursor === $id) { echo json_encode(['success' => false, 'error' => 'A folder cannot be moved inside one of its own subfolders']); return; }
        if (isset($seen[$cursor])) break;    // already corrupt; do not hang
        $seen[$cursor] = true;
        $cursor = $folders[$cursor]['parent'];
    }

    $conn->prepare("UPDATE knowledge_folders SET parent_id = ?, modified_datetime = UTC_TIMESTAMP() WHERE id = ?")
         ->execute([$parent, $id]);
    knowledgeAclResetCaches();
    knowledgeAudit($conn, 'folder', $id, 'move', $analystId, ['parent_id' => $parent]);
    echo json_encode(['success' => true]);
}

/**
 * Delete a folder.
 *
 * ⚠️ REFUSES WHILE IT HOLDS ANYTHING. Deleting a folder full of documents would
 * either destroy them or orphan them, and neither is something to do on one
 * click. Empty it first — which is a decision about the documents, made
 * deliberately, rather than a side effect of tidying the tree.
 */
function handleDelete(PDO $conn, int $analystId, array $in): void
{
    $id = (int)($in['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'A folder is required']); return; }
    requireFolderAccess($conn, $analystId, $id);

    $st = $conn->prepare("SELECT COUNT(*) FROM knowledge_articles WHERE folder_id = ?");
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'That folder still has articles in it. Move them out first.']);
        return;
    }
    $st = $conn->prepare("SELECT COUNT(*) FROM knowledge_folders WHERE parent_id = ?");
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'That folder still has subfolders in it. Move or delete them first.']);
        return;
    }

    // The access rows go with it. They name an object that will not exist, and a
    // later folder reusing the id would silently inherit somebody else's rules.
    $conn->prepare("DELETE FROM knowledge_acl WHERE object_type = 'folder' AND object_id = ?")->execute([$id]);
    $conn->prepare("DELETE FROM knowledge_folders WHERE id = ?")->execute([$id]);

    knowledgeAclResetCaches();
    knowledgeAudit($conn, 'folder', $id, 'delete', $analystId, null);
    echo json_encode(['success' => true]);
}

/** One audit row. Best-effort: never fail the action because the log is unwritable. */
function knowledgeAudit(PDO $conn, string $type, int $id, string $action, int $analystId, ?array $detail): void
{
    try {
        $conn->prepare(
            "INSERT INTO knowledge_audit (object_type, object_id, action, analyst_id, detail, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$type, $id, $action, $analystId, $detail === null ? null : json_encode($detail), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (PDOException $e) {
        error_log('knowledge audit: could not record ' . $action . ' on ' . $type . ' ' . $id . ' — ' . $e->getMessage());
    }
}
