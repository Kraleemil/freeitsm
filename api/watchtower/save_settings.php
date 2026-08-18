<?php
/**
 * API Endpoint: save Watchtower settings.
 *
 * Two capabilities, checked separately: hiding a card is layout, while changing
 * what a count includes changes what a number MEANS — narrow "high priority"
 * and the figure everyone reads each morning quietly changes definition. So a
 * payload is accepted in parts: whichever sections the caller is allowed to
 * change are saved, and the rest are ignored rather than failing the lot.
 *
 * Ids are validated against the live lookup tables. An id that isn't a real
 * status of the right kind is dropped rather than stored, so nothing can write
 * a member that later reads back as a ghost.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/rbac.php';
require_once '../../includes/watchtower_settings.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('watchtower');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

try {
    $conn = connectToDatabase();
    $analystId = (int) $_SESSION['analyst_id'];
    $canCards  = analystHasCapability($conn, $analystId, Cap::WATCHTOWER_CARDS);
    $canCounts = analystHasCapability($conn, $analystId, Cap::WATCHTOWER_COUNTS);

    if (!$canCards && !$canCounts) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not permitted']);
        exit;
    }

    $conn->beginTransaction();

    // ---- Which cards appear -------------------------------------------------
    if ($canCards && isset($data['cards']) && is_array($data['cards'])) {
        $upsert = $conn->prepare(
            "INSERT INTO watchtower_items (analyst_id, item_key, is_visible, is_customised)
             VALUES (?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible)"
        );
        foreach (wtCardKeys() as $card) {
            // Absent means visible: a card can only be hidden by saying so.
            $visible = array_key_exists($card, $data['cards']) ? (int)(bool)$data['cards'][$card] : 1;
            $upsert->execute([WT_INSTALL_SCOPE, 'card.' . $card, $visible]);
        }
    }

    // ---- What each count includes ------------------------------------------
    if ($canCounts && isset($data['items']) && is_array($data['items'])) {
        $specs = wtSelectableItems();
        foreach ($data['items'] as $key => $payload) {
            if (!isset($specs[$key]) || !is_array($payload)) continue;
            $spec = $specs[$key];
            $customised = !empty($payload['customised']);

            $flag = $conn->prepare(
                "INSERT INTO watchtower_items (analyst_id, item_key, is_visible, is_customised)
                 VALUES (?, ?, 1, ?)
                 ON DUPLICATE KEY UPDATE is_customised = VALUES(is_customised)"
            );
            $flag->execute([WT_INSTALL_SCOPE, $key, $customised ? 1 : 0]);

            $del = $conn->prepare("DELETE FROM watchtower_item_members WHERE analyst_id = ? AND item_key = ?");
            $del->execute([WT_INSTALL_SCOPE, $key]);

            // Turning customisation off leaves no members behind, so switching it
            // back on later starts from the default rather than from a stale set
            // somebody last saved months ago.
            if (!$customised) continue;

            $wanted = array_values(array_unique(array_map('intval', (array)($payload['selected'] ?? []))));
            if (!$wanted) continue;

            // Only ids that really are a status/priority of this kind.
            $valid = $conn->query(
                "SELECT `{$spec['id']}` FROM `{$spec['table']}`
                  WHERE {$spec['where']} AND `{$spec['id']}` IN " . wtIdListSql($wanted)
            )->fetchAll(PDO::FETCH_COLUMN);

            $ins = $conn->prepare(
                "INSERT INTO watchtower_item_members (analyst_id, item_key, entity_type, entity_id)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($valid as $id) {
                $ins->execute([WT_INSTALL_SCOPE, $key, $spec['entity_type'], (int)$id]);
            }
        }
    }

    // ---- The paused-too-long threshold -------------------------------------
    if ($canCounts && isset($data['paused_hours'])) {
        $hours = (int)$data['paused_hours'];
        if ($hours < 1)    $hours = 1;
        if ($hours > 8760) $hours = 8760;      // a year; beyond that it means nothing
        $conn->prepare(
            "INSERT INTO system_settings (setting_key, setting_value) VALUES ('watchtower_paused_too_long_hours', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        )->execute([(string)$hours]);
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
