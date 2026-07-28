<?php
/**
 * API: set an asset's human-readable tag — the number printed on its label.
 *
 * POST { asset_id, asset_tag } -> { success, asset_tag }
 *
 * WHY THIS ISN'T update_asset_field.php
 * -------------------------------------
 * That endpoint is a generic single-field writer over a whitelist, and this
 * field has a rule none of the others have: the tag must be unique WITHIN THE
 * COMPANY that owns the asset. Two companies on one install may each run their
 * own LT0001, so the check needs the asset's tenant — which a generic setter
 * has no reason to know about.
 *
 * The check is here, in code, and NOT a unique index: `UNIQUE (tenant_id,
 * asset_tag)` would not hold for the Default company, because MySQL treats
 * NULLs as distinct in a unique index — two default assets could both be
 * LT0001 while the index looked like it was guarding them. The same reasoning
 * already governs hostname uniqueness in this schema.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/rbac.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/asset_labels.php';

header('Content-Type: application/json');
if (!isset($_SESSION['analyst_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit; }
requireModuleAccessJson('assets');

try {
    $data    = json_decode(file_get_contents('php://input'), true) ?: [];
    $assetId = (int)($data['asset_id'] ?? 0);
    $tag     = trim((string)($data['asset_tag'] ?? ''));

    if ($assetId <= 0) throw new Exception('asset_id is required');
    if (mb_strlen($tag) > 64) throw new Exception('An asset tag can be at most 64 characters');

    $conn = connectToDatabase();
    $analystId = (int)$_SESSION['analyst_id'];

    if (!assetLabelsSchemaReady($conn)) {
        throw new Exception('Asset tags need a database update — run System → Database Verification.');
    }
    if (!analystCanAccessAsset($conn, $analystId, $assetId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Asset not found']);
        exit;
    }

    $stmt = $conn->prepare("SELECT tenant_id, asset_tag FROM assets WHERE id = ?");
    $stmt->execute([$assetId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('Asset not found');

    $old = (string)($row['asset_tag'] ?? '');
    if ($old === $tag) {
        echo json_encode(['success' => true, 'asset_tag' => $tag, 'unchanged' => true]);
        exit;
    }

    // Blank clears the tag. Everything else has to be free within this company.
    $tenantId = $row['tenant_id'] === null ? null : (int)$row['tenant_id'];
    if ($tag !== '' && !assetTagAvailable($conn, $tenantId, $tag, $assetId)) {
        // Name the conflict plainly: "already in use" without saying where is
        // the kind of error that has somebody hunting through a list of 4,000.
        $find = $conn->prepare("SELECT hostname FROM assets WHERE tenant_id <=> ? AND asset_tag = ? AND id <> ? LIMIT 1");
        $find->execute([$tenantId, $tag, $assetId]);
        $clash = $find->fetchColumn();
        throw new Exception($clash
            ? "That tag is already on " . $clash
            : "That tag is already in use in this company");
    }

    $conn->prepare("UPDATE assets SET asset_tag = ? WHERE id = ?")
         ->execute([$tag === '' ? null : $tag, $assetId]);

    // Best-effort history, matching how the assets service records field edits.
    try {
        $conn->prepare(
            "INSERT INTO asset_history (asset_id, analyst_id, field_name, old_value, new_value, created_datetime)
             VALUES (?, ?, 'Asset tag', ?, ?, UTC_TIMESTAMP())"
        )->execute([$assetId, $analystId, ($old === '' ? null : $old), ($tag === '' ? null : $tag)]);
    } catch (Exception $e) { /* history is best-effort */ }

    echo json_encode(['success' => true, 'asset_tag' => $tag]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
