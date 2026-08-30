<?php
/**
 * At-a-glance previews of a linked record (discussion #91).
 *
 * dschipfel asked for linked records to be clickable; that shipped. He then
 * asked for a preview so you can see what is on the other end of a link without
 * leaving the page, named the relationships that matter most, and asked for the
 * fields listed below. Read-only, by his request: "allowing edits from the
 * preview would add complexity and could increase the risk of accidental
 * changes."
 *
 * 🔴 A PREVIEW IS A READ. Everything here is a way to see a record you are
 * looking at a link to — and a link can be to something you are not allowed to
 * open. So every type checks BOTH the module and the record itself, and an
 * unreachable record returns exactly what a non-existent one returns: null.
 * Telling the two apart would confirm that a record exists, which is the thing
 * the check is there to withhold.
 *
 * ⚠️ ONE function per type, all reached through recordPreview(). Wiring a
 * preview into a new screen must never mean writing a new query.
 *
 * The fields are the ones promised in the discussion, so they are listed here
 * as the contract they are:
 *
 *   Ticket            number, subject, status, priority, who it is with, requester
 *   Task              title, status, assignee, due date, subtask progress
 *   Change            title, status, risk, planned window
 *   Problem           number, title, status, how many tickets are attached
 *   Asset             tag, make and model, serial, who holds it, warranty
 *   Contract          number, supplier, renewal date
 *   Knowledge article title, and the opening lines
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/tenancy.php';
require_once __DIR__ . '/entity_links.php';

/** The types a preview can be asked for, and the module each needs. */
const RECORD_PREVIEW_MODULES = [
    'ticket'            => 'tickets',
    'task'              => 'tasks',
    'change'            => 'changes',
    'problem'           => 'problems',
    'asset'             => 'assets',
    'contract'          => 'contracts',
    'knowledge_article' => 'knowledge',
];

/**
 * A preview, or NULL when the type is unknown, the record does not exist, or
 * this analyst may not see it. One answer for all three, deliberately.
 */
function recordPreview(PDO $conn, int $analystId, string $type, int $id): ?array
{
    if ($id <= 0 || !isset(RECORD_PREVIEW_MODULES[$type])) {
        return null;
    }
    // The module gate first: somebody with no access to Assets should not learn
    // anything about one, however they arrived at the link.
    if (!analystCanAccessModule($conn, $analystId, RECORD_PREVIEW_MODULES[$type])) {
        return null;
    }

    $fn = 'recordPreview' . str_replace(' ', '', ucwords(str_replace('_', ' ', $type)));
    if (!function_exists($fn)) {
        return null;
    }
    $preview = $fn($conn, $analystId, $id);
    if ($preview === null) {
        return null;
    }

    $preview['type'] = $type;
    $preview['id']   = $id;
    $preview['url']  = entityLink($type, $id);
    return $preview;
}

/** Shorthand for one line of a preview. */
function rpField(string $label, $value, ?string $colour = null): ?array
{
    $value = is_string($value) ? trim($value) : $value;
    // An empty field is left out rather than shown blank. A preview is a glance;
    // six labels with nothing beside them is worse than three with something.
    if ($value === null || $value === '' ) {
        return null;
    }
    return array_filter([
        'label'  => $label,
        'value'  => (string)$value,
        'colour' => $colour,
    ], fn($v) => $v !== null);
}

/** Drop the empties, so callers can just list every field they might have. */
function rpFields(array $fields): array
{
    return array_values(array_filter($fields));
}

// ── Ticket ──────────────────────────────────────────────────────────────────
function recordPreviewTicket(PDO $conn, int $analystId, int $id): ?array
{
    // ⚠️ analystCanAccessTicket() is TENANCY only — it answers "is this ticket
    // in a company you can reach", not "may you read tickets". The module gate
    // in recordPreview() is the other half, and both are needed.
    if (!analystCanAccessTicket($conn, $analystId, $id)) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT t.ticket_number, t.subject,
                s.name AS status, s.colour AS status_colour,
                p.name AS priority, p.colour AS priority_colour,
                a.full_name AS assignee,
                -- The house form for a person's name; a preferred name wins when
                -- there is one, as it does everywhere else a requester is shown.
                COALESCE(NULLIF(TRIM(u.preferred_name), ''), u.display_name) AS requester
           FROM tickets t
      LEFT JOIN ticket_statuses s    ON s.id = t.status_id
      LEFT JOIN ticket_priorities p  ON p.id = t.priority_id
      LEFT JOIN analysts a           ON a.id = t.assigned_analyst_id
      LEFT JOIN users u              ON u.id = t.user_id
          WHERE t.id = ?"
    );
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    return [
        'heading'  => trim(($r['ticket_number'] ?? '') . ' ' . ($r['subject'] ?? '')),
        'fields'   => rpFields([
            rpField(t('common.preview.status'),    $r['status'],   $r['status_colour']),
            rpField(t('common.preview.priority'),  $r['priority'], $r['priority_colour']),
            // ⚠️ The ANALYST only. A ticket carries no team of its own - there
            // is no assigned_team_id on the table - so there is nothing to fall
            // back to, unlike a task.
            rpField(t('common.preview.with'),      $r['assignee']),
            rpField(t('common.preview.requester'), $r['requester']),
        ]),
    ];
}

// ── Task ────────────────────────────────────────────────────────────────────
function recordPreviewTask(PDO $conn, int $analystId, int $id): ?array
{
    [$where, $args] = activeTenantFilter($conn, $analystId, 'tk');

    $stmt = $conn->prepare(
        "SELECT tk.title, tk.due_date,
                s.name AS status, s.colour AS status_colour,
                a.full_name AS assignee, tm.name AS team,
                (SELECT COUNT(*) FROM tasks c WHERE c.parent_task_id = tk.id) AS subtasks,
                (SELECT COUNT(*) FROM tasks c
                   JOIN task_statuses cs ON cs.id = c.status_id
                  WHERE c.parent_task_id = tk.id AND cs.is_closed = 1) AS subtasks_done
           FROM tasks tk
      LEFT JOIN task_statuses s ON s.id = tk.status_id
      LEFT JOIN analysts a      ON a.id = tk.assigned_analyst_id
      LEFT JOIN teams tm        ON tm.id = tk.assigned_team_id
          WHERE tk.id = ?" . $where
    );
    $stmt->execute(array_merge([$id], $args));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    $progress = (int)$r['subtasks'] > 0
        ? t('common.preview.subtask_progress', ['done' => (int)$r['subtasks_done'], 'total' => (int)$r['subtasks']])
        : null;

    return [
        'heading' => $r['title'],
        'fields'  => rpFields([
            rpField(t('common.preview.status'),   $r['status'], $r['status_colour']),
            rpField(t('common.preview.assignee'), $r['assignee'] ?: $r['team']),
            rpField(t('common.preview.due'),      $r['due_date']),
            rpField(t('common.preview.subtasks'), $progress),
        ]),
    ];
}

// ── Change ──────────────────────────────────────────────────────────────────
function recordPreviewChange(PDO $conn, int $analystId, int $id): ?array
{
    [$where, $args] = activeTenantFilter($conn, $analystId, 'c');

    $stmt = $conn->prepare(
        "SELECT c.title, c.risk_level, c.work_start_datetime, c.work_end_datetime,
                s.name AS status, s.colour AS status_colour
           FROM changes c
      LEFT JOIN change_statuses s ON s.id = c.status_id
          WHERE c.id = ?" . $where
    );
    $stmt->execute(array_merge([$id], $args));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    // The planned window is two columns and reads as one fact.
    $window = null;
    if (!empty($r['work_start_datetime'])) {
        $window = substr((string)$r['work_start_datetime'], 0, 16);
        if (!empty($r['work_end_datetime'])) {
            $window .= ' → ' . substr((string)$r['work_end_datetime'], 0, 16);
        }
    }

    return [
        'heading' => $r['title'],
        'fields'  => rpFields([
            rpField(t('common.preview.status'), $r['status'], $r['status_colour']),
            rpField(t('common.preview.risk'),   $r['risk_level']),
            rpField(t('common.preview.window'), $window),
        ]),
    ];
}

// ── Problem ─────────────────────────────────────────────────────────────────
function recordPreviewProblem(PDO $conn, int $analystId, int $id): ?array
{
    [$where, $args] = activeTenantFilter($conn, $analystId, 'p');

    $stmt = $conn->prepare(
        "SELECT p.problem_number, p.title,
                s.name AS status, s.colour AS status_colour,
                -- ⚠️ A JOIN TABLE, not a column on tickets. One problem can hold
                -- many tickets and the link is recorded in problem_tickets.
                (SELECT COUNT(*) FROM problem_tickets pt WHERE pt.problem_id = p.id) AS tickets
           FROM problems p
      LEFT JOIN problem_statuses s ON s.id = p.status_id
          WHERE p.id = ?" . $where
    );
    $stmt->execute(array_merge([$id], $args));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    return [
        'heading' => trim(($r['problem_number'] ?? '') . ' ' . ($r['title'] ?? '')),
        'fields'  => rpFields([
            rpField(t('common.preview.status'),  $r['status'], $r['status_colour']),
            // Shown even when zero: "no tickets attached" is a fact about a
            // problem worth knowing, unlike an empty due date.
            rpField(t('common.preview.tickets'), (string)(int)$r['tickets']),
        ]),
    ];
}

// ── Asset ───────────────────────────────────────────────────────────────────
function recordPreviewAsset(PDO $conn, int $analystId, int $id): ?array
{
    if (!analystCanAccessAsset($conn, $analystId, $id)) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT a.hostname, a.manufacturer, a.model, a.service_tag, a.warranty_expiry,
                ty.name AS type_name,
                -- ⚠️ An asset can be held by MORE THAN ONE person - a shared
                -- laptop, a meeting-room screen - so the count comes back with
                -- the name. Naming the most recent holder alone would say one
                -- person has something two people do.
                (SELECT COALESCE(NULLIF(TRIM(u.preferred_name), ''), u.display_name)
                   FROM users_assets ua JOIN users u ON u.id = ua.user_id
                  WHERE ua.asset_id = a.id ORDER BY ua.assigned_datetime DESC LIMIT 1) AS holder,
                (SELECT COUNT(*) FROM users_assets ua WHERE ua.asset_id = a.id) AS holders
           FROM assets a
      LEFT JOIN asset_types ty ON ty.id = a.asset_type_id
          WHERE a.id = ?"
    );
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    // The asset tag column only exists once Database Verification has run, so it
    // is fetched separately rather than naming it in the query above.
    $tag = null;
    require_once __DIR__ . '/asset_labels.php';
    if (assetLabelsSchemaReady($conn)) {
        $q = $conn->prepare("SELECT asset_tag FROM assets WHERE id = ?");
        $q->execute([$id]);
        $tag = $q->fetchColumn() ?: null;
    }

    $makeModel = trim(($r['manufacturer'] ?? '') . ' ' . ($r['model'] ?? ''));

    return [
        'heading' => $r['hostname'] ?: ($tag ?: ($makeModel ?: ('#' . $id))),
        'fields'  => rpFields([
            rpField(t('common.preview.tag'),      $tag),
            rpField(t('common.preview.type'),     $r['type_name']),
            rpField(t('common.preview.model'),    $makeModel),
            rpField(t('common.preview.serial'),   $r['service_tag']),
            rpField(t('common.preview.held_by'),  (int)$r['holders'] > 1
                ? t('common.preview.held_by_many', ['name' => $r['holder'], 'n' => (int)$r['holders'] - 1])
                : $r['holder']),
            rpField(t('common.preview.warranty'), $r['warranty_expiry']),
        ]),
    ];
}

// ── Contract ────────────────────────────────────────────────────────────────
function recordPreviewContract(PDO $conn, int $analystId, int $id): ?array
{
    // ⚠️ No tenancy filter: contracts carry no tenant_id. The module gate is the
    // whole of the check, which is true of the contracts module generally — see
    // includes/contract_assets.php.
    $stmt = $conn->prepare(
        "SELECT c.contract_number, c.title, c.contract_end, c.notice_date,
                s.legal_name AS supplier, s.trading_name AS supplier_trading,
                st.name AS status
           FROM contracts c
      LEFT JOIN suppliers s          ON s.id = c.supplier_id
      LEFT JOIN contract_statuses st ON st.id = c.contract_status_id
          WHERE c.id = ?"
    );
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    return [
        'heading' => trim(($r['contract_number'] ?? '') . ' ' . ($r['title'] ?? '')),
        'fields'  => rpFields([
            rpField(t('common.preview.status'),   $r['status']),
            rpField(t('common.preview.supplier'), $r['supplier_trading'] ?: $r['supplier']),
            rpField(t('common.preview.renewal'),  $r['contract_end']),
            // Not in the promised list, and included anyway: on a contract the
            // notice date is the one that costs money to miss.
            rpField(t('common.preview.notice'),   $r['notice_date']),
        ]),
    ];
}

// ── Knowledge article ───────────────────────────────────────────────────────
function recordPreviewKnowledgeArticle(PDO $conn, int $analystId, int $id): ?array
{
    // 🔴 Knowledge has its own visibility rules — folders, audiences, lifecycle —
    // and they are not a tenancy filter. Reuse them rather than approximating:
    // an approximation here would be a way to read a restricted article.
    require_once __DIR__ . '/knowledge/visibility.php';
    $viewer = KnowledgeViewer::forAnalyst($conn, $analystId);
    [$vis, $args] = knowledgeVisibilitySql($conn, $viewer, 'a');

    $stmt = $conn->prepare("SELECT a.title, a.body FROM knowledge_articles a WHERE a.id = ?" . $vis);
    $stmt->execute(array_merge([$id], $args));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    // The opening lines, as plain text. Tags are stripped rather than rendered:
    // a preview is a glance, and an article's own markup would fight the panel's.
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$r['body'])));
    $lead  = mb_substr($plain, 0, 240);
    if (mb_strlen($plain) > 240) {
        $lead = rtrim($lead) . '…';
    }

    return [
        'heading' => $r['title'],
        'lead'    => $lead,
        'fields'  => [],
    ];
}
