<?php
/**
 * Watchtower settings — which cards appear, and which statuses feed each count.
 *
 * WHY THIS IS NOT A FLAG ON THE STATUS ROW
 *
 * A status carries facts about itself — is_closed, is_default, pauses_sla — and
 * the SLA engine and half the application depend on them. "Show this one on my
 * dashboard" is not a fact about the status, it is a fact about the dashboard,
 * and it differs per site: one team treats Blocked as the first thing they want
 * to see in the morning, another never looks at it. Putting it on the status
 * would also mean tuning Watchtower by touring three modules' settings screens —
 * which is already the situation for the two Watchtower settings that exist
 * (one lives in Assets, and one has no screen at all).
 *
 * THE DEFAULT MUST BE CORRECT WITHOUT ANYONE CONFIGURING ANYTHING
 *
 * No row here means "use the built-in behaviour", which is the general,
 * name-free version. So a fresh install, and every existing one, is right on
 * day one and this screen only ever TRIMS what is already true. If ticking
 * boxes were what made Watchtower correct, every install would be wrong until
 * somebody found the screen — and a blank dashboard reads as "nothing needs
 * attention", which is the most dangerous thing it could say.
 *
 * is_customised is what makes that work: it separates "not configured" from
 * "configured to show nothing". Without it an empty selection is
 * indistinguishable from an untouched one, and would silently mean "all".
 */

/** analyst_id 0 = the installation's setting. Per-analyst overrides are future work. */
const WT_INSTALL_SCOPE = 0;

/** Every card Watchtower draws, in the order it draws them. */
function wtCardKeys(): array
{
    return ['morning_checks', 'tickets', 'changes', 'calendar', 'service_status',
            'contracts', 'knowledge', 'assets', 'tasks', 'workflows'];
}

/**
 * The set-driven items, and what kind of thing each one selects from.
 * item_key => ['entity_type' => …, 'table' => …, 'id' => …, 'name' => …, 'where' => …]
 */
function wtSelectableItems(): array
{
    return [
        'tickets.by_status' => [
            'entity_type' => 'ticket_status',
            'table' => 'ticket_statuses', 'id' => 'id', 'name' => 'name',
            'where' => 'is_closed = 0 AND is_active = 1', 'order' => 'display_order, name',
        ],
        'tickets.high_priority' => [
            'entity_type' => 'ticket_priority',
            'table' => 'ticket_priorities', 'id' => 'id', 'name' => 'name',
            'where' => 'is_active = 1', 'order' => 'display_order, name',
        ],
        'tasks.by_status' => [
            'entity_type' => 'task_status',
            'table' => 'task_statuses', 'id' => 'id', 'name' => 'name',
            'where' => 'is_closed = 0 AND is_active = 1', 'order' => 'display_order, name',
        ],
        'mc.attention' => [
            'entity_type' => 'mc_status',
            'table' => 'morningChecks_Statuses', 'id' => 'StatusID', 'name' => 'Label',
            'where' => 'IsActive = 1', 'order' => 'SortOrder, Label',
        ],
    ];
}

/** Card visibility. Missing row = visible. */
function wtVisibleCards(PDO $conn): array
{
    $visible = array_fill_keys(wtCardKeys(), true);
    try {
        $stmt = $conn->prepare(
            "SELECT item_key, is_visible FROM watchtower_items WHERE analyst_id = ? AND item_key LIKE 'card.%'"
        );
        $stmt->execute([WT_INSTALL_SCOPE]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = substr($row['item_key'], 5);
            if (isset($visible[$key])) $visible[$key] = (bool)(int)$row['is_visible'];
        }
    } catch (Exception $e) {
        // Table not created yet (upgrade not run) — everything shows, which is
        // the behaviour this screen was added on top of.
    }
    return $visible;
}

/**
 * The chosen members for an item, or NULL meaning "not customised, use the
 * built-in default". An empty ARRAY is a real answer — the admin chose nothing —
 * and is not the same as null.
 *
 * Ids are checked against the live lookup table, so a status deleted after being
 * selected drops out instead of being counted as a member that no longer exists.
 */
function wtItemMembers(PDO $conn, string $itemKey): ?array
{
    $items = wtSelectableItems();
    if (!isset($items[$itemKey])) return null;
    $spec = $items[$itemKey];

    try {
        $c = $conn->prepare("SELECT is_customised FROM watchtower_items WHERE analyst_id = ? AND item_key = ? LIMIT 1");
        $c->execute([WT_INSTALL_SCOPE, $itemKey]);
        if ((int)$c->fetchColumn() !== 1) return null;

        $stmt = $conn->prepare(
            "SELECT m.entity_id
               FROM watchtower_item_members m
               JOIN `{$spec['table']}` e ON e.`{$spec['id']}` = m.entity_id
              WHERE m.analyst_id = ? AND m.item_key = ? AND m.entity_type = ?"
        );
        $stmt->execute([WT_INSTALL_SCOPE, $itemKey, $spec['entity_type']]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Exception $e) {
        return null;   // never let a settings read break the dashboard
    }
}

/** A safe `IN (…)` fragment from a list of ints. '(0)' when empty, so it matches nothing. */
function wtIdListSql(array $ids): string
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    return $ids ? '(' . implode(',', $ids) . ')' : '(0)';
}
