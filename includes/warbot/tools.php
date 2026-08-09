<?php
/**
 * Warbot's tools — the registry.
 *
 * 🔑 THIS FILE IS THE PRODUCT, NOT THE BOT. Each tool is declared once: a name, a
 * description the model reads, a JSON schema, the capability required to run it,
 * and a plain PHP handler. Warbot is one consumer. An MCP server is a second one,
 * and it is a thin adapter over this array rather than a rewrite — which is why
 * the registry came first and the protocol later.
 *
 * 🔑 THE HANDLERS NEED NO INTERNET. That is the whole design. The war room exists
 * for the day the internet is down, so a bot whose every answer requires a remote
 * model would be useless in exactly the situation it was built for. Only the
 * MODEL needs the network; the HANDS are local SQL. When the provider cannot be
 * reached, Warbot falls back to slash commands that run these same handlers
 * directly — see warbot.php. Degraded, but not dead.
 *
 * ⚠️ READ-ONLY, ALL OF THEM. Warbot reads chat, so anybody in the room can type
 * instructions at it, and the moment it reads ticket content a customer can plant
 * instructions in an email. Read-only makes prompt injection embarrassing rather
 * than dangerous. Ed was already wary of letting the CMDB graph take actions;
 * the same instinct applies harder to something with a chat box.
 *
 * ⚠️ WHAT THESE MAY RETURN, AND WHY IT IS NARROW. Warbot answers IN A CHANNEL, so
 * every member sees the reply. That makes the audience for any answer "everyone
 * in this room", not "the person who asked". So these return OPERATIONAL facts —
 * counts, states, names of services, hostnames, what changed — and deliberately
 * NOT ticket bodies, requester details, notes or anything else that belongs to a
 * person. Anything of that kind needs a one-to-one conversation, which is
 * outstanding work rather than an oversight.
 *
 * ⚠️ EVERY COLUMN NAME BELOW WAS READ OUT OF information_schema, NOT REMEMBERED.
 * Four of five guessed column names were wrong the last time this was done from
 * memory (see the Forms lookup fields work), and a wrong column here reads as
 * "no results" rather than as an error — the failure mode is a bot that
 * confidently says nothing is wrong.
 */

require_once __DIR__ . '/../capabilities.php';
require_once __DIR__ . '/../rbac.php';

/**
 * @return array<string,array{description:string,schema:array,capability:?string,handler:callable}>
 */
function warbotTools(): array
{
    return [

        /* ── what is on fire right now ─────────────────────────────────────── */
        'open_incidents' => [
            'description' => 'Count and list the open tickets at a given priority, newest first. '
                           . 'Use this to answer "how many P1s are open" or "what is outstanding".',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'priority' => ['type' => 'string', 'description' => 'Priority name, e.g. Critical, Urgent, High. Omit for all open tickets.'],
                    'limit'    => ['type' => 'integer', 'description' => 'How many to list (default 10, max 25).'],
                ],
                'required' => [],
            ],
            'capability' => null,           // module access to war-room is enough
            'handler'    => 'warbotToolOpenIncidents',
        ],

        /* ── is the thing the business is asking about actually down ───────── */
        'service_status' => [
            'description' => 'The current state of published services and any open service-status incidents. '
                           . 'Use this for "is the VPN down", "what is degraded", "what are we telling customers".',
            'schema' => ['type' => 'object', 'properties' => new stdClass(), 'required' => []],
            'capability' => null,
            'handler'    => 'warbotToolServiceStatus',
        ],

        /* ── THE incident question ─────────────────────────────────────────── */
        'recent_changes' => [
            'description' => 'Changes whose work or outage window falls in the last N days. '
                           . 'Use this for "what changed", "did anything go out this morning", '
                           . 'which is usually the first useful question in an incident.',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer', 'description' => 'How far back to look (default 2, max 30).'],
                ],
                'required' => [],
            ],
            'capability' => null,
            'handler'    => 'warbotToolRecentChanges',
        ],

        /* ── who do I wake up ──────────────────────────────────────────────── */
        'on_call' => [
            'description' => 'Who is on call today, and who is on shift, from the rota.',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
                ],
                'required' => [],
            ],
            'capability' => null,
            'handler'    => 'warbotToolOnCall',
        ],

        /* ── the box somebody just named in the chat ───────────────────────── */
        'asset_lookup' => [
            'description' => 'Find a machine by hostname, asset tag or service tag, and return its '
                           . 'operating system, status, location and warranty date.',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Hostname, asset tag or service tag; a partial name works.'],
                ],
                'required' => ['query'],
            ],
            'capability' => null,
            'handler'    => 'warbotToolAssetLookup',
        ],

        /* ── what else falls over if this does ─────────────────────────────── */
        'impact_of' => [
            'description' => 'What depends on a CMDB object — the blast radius if it is down. '
                           . 'Use this for "what else does this affect".',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Name of the configuration item.'],
                ],
                'required' => ['name'],
            ],
            'capability' => null,
            'handler'    => 'warbotToolImpactOf',
        ],

        /* ── have we seen this before ──────────────────────────────────────── */
        'search_knowledge' => [
            'description' => 'Search published knowledge base article titles for a phrase, and return '
                           . 'the matching titles. Use this for "is there a runbook for this".',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Words to look for in the title.'],
                ],
                'required' => ['query'],
            ],
            'capability' => null,
            'handler'    => 'warbotToolSearchKnowledge',
        ],
    ];
}

/**
 * The tools this analyst may actually use, in the shape aiProviderChatTools wants.
 * A capability they lack means the tool is not OFFERED at all, rather than offered
 * and then refused — a model told about a tool it cannot use will keep reaching
 * for it and narrate the failure to the room.
 */
function warbotToolsFor(PDO $conn, int $analystId): array
{
    $out = [];
    foreach (warbotTools() as $name => $t) {
        if ($t['capability'] !== null && !analystHasCapability($conn, $analystId, $t['capability'])) continue;
        $out[] = ['name' => $name, 'description' => $t['description'], 'schema' => $t['schema']];
    }
    return $out;
}

/**
 * Run one tool. NEVER throws — a failure is returned as words, because "the CMDB
 * lookup failed" is something the model can usefully tell the room, whereas an
 * exception here would lose the whole answer.
 */
function warbotRunTool(PDO $conn, int $analystId, string $name, array $args): string
{
    $tools = warbotTools();
    if (!isset($tools[$name])) return 'No such tool.';
    $t = $tools[$name];
    if ($t['capability'] !== null && !analystHasCapability($conn, $analystId, $t['capability'])) {
        return 'You do not have permission to use that.';
    }
    try {
        return (string) call_user_func($t['handler'], $conn, $args, $analystId);
    } catch (Throwable $e) {
        return 'That lookup failed: ' . $e->getMessage();
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   HANDLERS — plain SQL, no network. Each returns text for the model to read.
   ══════════════════════════════════════════════════════════════════════════ */

function warbotToolOpenIncidents(PDO $conn, array $args, int $analystId): string
{
    $limit    = max(1, min(25, (int)($args['limit'] ?? 10)));
    $priority = trim((string)($args['priority'] ?? ''));

    $where  = ["t.deleted_datetime IS NULL", "(s.is_closed IS NULL OR s.is_closed = 0)"];
    $params = [];
    if ($priority !== '') {
        $where[] = "p.name = :prio";
        $params[':prio'] = $priority;
    }
    $sql = "SELECT t.ticket_number, t.subject, p.name AS priority, s.name AS status,
                   a.full_name AS assignee, t.created_datetime
              FROM tickets t
              LEFT JOIN ticket_statuses   s ON s.id = t.status_id
              LEFT JOIN ticket_priorities p ON p.id = t.priority_id
              LEFT JOIN analysts          a ON a.id = t.assigned_analyst_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY t.id DESC LIMIT $limit";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countSql = "SELECT COUNT(*) FROM tickets t
                   LEFT JOIN ticket_statuses s ON s.id = t.status_id
                   LEFT JOIN ticket_priorities p ON p.id = t.priority_id
                  WHERE " . implode(' AND ', $where);
    $cs = $conn->prepare($countSql);
    $cs->execute($params);
    $total = (int) $cs->fetchColumn();

    if ($total === 0) return 'No open tickets' . ($priority !== '' ? " at priority $priority" : '') . '.';

    $lines = [$total . ' open ticket(s)' . ($priority !== '' ? " at priority $priority" : '') . '. Most recent:'];
    foreach ($rows as $r) {
        $lines[] = sprintf('- %s [%s/%s] %s — %s',
            $r['ticket_number'], $r['priority'] ?: '?', $r['status'] ?: '?',
            $r['subject'], $r['assignee'] ? 'assigned to ' . $r['assignee'] : 'unassigned');
    }
    return implode("\n", $lines);
}

function warbotToolServiceStatus(PDO $conn, array $args, int $analystId): string
{
    $svc = $conn->query(
        "SELECT name FROM status_services WHERE is_active = 1 ORDER BY COALESCE(display_order,0), name"
    )->fetchAll(PDO::FETCH_COLUMN);

    // An "open" incident is one with no resolved timestamp AND a status that is
    // not flagged resolved — the two can disagree, and either alone would report
    // a resolved incident as live.
    $inc = $conn->query(
        "SELECT i.title, i.comment, st.name AS state, i.updated_datetime,
                (SELECT GROUP_CONCAT(s2.name ORDER BY s2.name SEPARATOR ', ')
                   FROM status_incident_services sis
                   JOIN status_services s2 ON s2.id = sis.service_id
                  WHERE sis.incident_id = i.id) AS services
           FROM status_incidents i
           LEFT JOIN service_incident_statuses st ON st.id = i.status_id
          WHERE i.resolved_datetime IS NULL
            AND (st.is_resolved IS NULL OR st.is_resolved = 0)
          ORDER BY i.id DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);

    $lines = [count($svc) . ' published service(s): ' . (implode(', ', $svc) ?: 'none')];
    if (!$inc) {
        $lines[] = 'No open service-status incidents — nothing is currently being published to customers.';
        return implode("\n", $lines);
    }
    $lines[] = count($inc) . ' open service-status incident(s):';
    foreach ($inc as $i) {
        $lines[] = sprintf('- "%s" [%s] affecting %s. Latest note: %s',
            $i['title'], $i['state'] ?: '?', $i['services'] ?: 'no services listed',
            trim((string)$i['comment']) !== '' ? mb_substr(trim((string)$i['comment']), 0, 200) : '(none)');
    }
    return implode("\n", $lines);
}

function warbotToolRecentChanges(PDO $conn, array $args, int $analystId): string
{
    $days = max(1, min(30, (int)($args['days'] ?? 2)));
    // Match on the work OR outage window, not created_datetime: a change raised
    // three weeks ago and executed this morning is the one that matters, and
    // filtering on when it was typed up would hide exactly that case.
    $stmt = $conn->prepare(
        "SELECT c.title, c.work_start_datetime, c.outage_start_datetime,
                st.name AS state, a.full_name AS owner
           FROM changes c
           LEFT JOIN change_statuses st ON st.id = c.status_id
           LEFT JOIN analysts a ON a.id = c.assigned_to_id
          WHERE (c.work_start_datetime   >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :d1 DAY)
              OR c.outage_start_datetime >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :d2 DAY))
          ORDER BY COALESCE(c.outage_start_datetime, c.work_start_datetime) DESC
          LIMIT 20"
    );
    $stmt->bindValue(':d1', $days, PDO::PARAM_INT);
    $stmt->bindValue(':d2', $days, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) return "No changes with a work or outage window in the last $days day(s).";

    $lines = [count($rows) . " change(s) in the last $days day(s):"];
    foreach ($rows as $r) {
        $when = $r['outage_start_datetime'] ?: $r['work_start_datetime'];
        $lines[] = sprintf('- "%s" [%s] %s%s', $r['title'], $r['state'] ?: '?',
            $when ? 'window from ' . $when . ' UTC' : 'no window set',
            $r['owner'] ? ', owned by ' . $r['owner'] : '');
    }
    return implode("\n", $lines);
}

function warbotToolOnCall(PDO $conn, array $args, int $analystId): string
{
    $date = (string)($args['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = gmdate('Y-m-d');

    $stmt = $conn->prepare(
        "SELECT a.full_name, sh.name AS shift, sh.start_time, sh.end_time, e.is_on_call
           FROM ticket_rota_entries e
           JOIN analysts a ON a.id = e.analyst_id
           LEFT JOIN ticket_rota_shifts sh ON sh.id = e.shift_id
          WHERE e.rota_date = :d
          ORDER BY e.is_on_call DESC, a.full_name"
    );
    $stmt->execute([':d' => $date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) return "Nobody is rostered for $date.";

    $onCall = array_filter($rows, function ($r) { return (int)$r['is_on_call'] === 1; });
    $lines  = [];
    $lines[] = $onCall
        ? 'On call ' . $date . ': ' . implode(', ', array_column($onCall, 'full_name'))
        : 'Nobody is flagged on call for ' . $date . '.';
    foreach ($rows as $r) {
        $lines[] = sprintf('- %s%s%s', $r['full_name'],
            $r['shift'] ? ' — ' . $r['shift'] : '',
            ($r['start_time'] && $r['end_time']) ? ' (' . substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5) . ')' : '');
    }
    return implode("\n", $lines);
}

function warbotToolAssetLookup(PDO $conn, array $args, int $analystId): string
{
    $q = trim((string)($args['query'] ?? ''));
    if ($q === '') return 'Give me a hostname, asset tag or service tag to look for.';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';

    $stmt = $conn->prepare(
        "SELECT a.hostname, a.asset_tag, a.service_tag, a.operating_system, a.logged_in_user,
                a.last_seen, a.warranty_expiry, t.name AS type, s.name AS status, l.name AS location
           FROM assets a
           LEFT JOIN asset_types        t ON t.id = a.asset_type_id
           LEFT JOIN asset_status_types s ON s.id = a.asset_status_id
           LEFT JOIN asset_locations    l ON l.id = a.location_id
          WHERE a.hostname LIKE :q1 ESCAPE '\\\\' OR a.asset_tag LIKE :q2 ESCAPE '\\\\' OR a.service_tag LIKE :q3 ESCAPE '\\\\'
          ORDER BY a.hostname LIMIT 8"
    );
    $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) return "No asset matches \"$q\".";
    $lines = [count($rows) . " asset(s) matching \"$q\":"];
    foreach ($rows as $r) {
        $lines[] = sprintf('- %s [%s/%s] %s%s%s%s',
            $r['hostname'] ?: '(no hostname)', $r['type'] ?: '?', $r['status'] ?: '?',
            $r['operating_system'] ?: 'OS unknown',
            $r['location'] ? ', at ' . $r['location'] : '',
            $r['logged_in_user'] ? ', last user ' . $r['logged_in_user'] : '',
            $r['last_seen'] ? ', last seen ' . $r['last_seen'] : '');
    }
    return implode("\n", $lines);
}

function warbotToolImpactOf(PDO $conn, array $args, int $analystId): string
{
    $name = trim((string)($args['name'] ?? ''));
    if ($name === '') return 'Give me the name of a configuration item.';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $name) . '%';

    $find = $conn->prepare("SELECT id, name FROM cmdb_objects WHERE name LIKE :q ESCAPE '\\\\' ORDER BY name LIMIT 1");
    $find->execute([':q' => $like]);
    $obj = $find->fetch(PDO::FETCH_ASSOC);
    if (!$obj) return "No configuration item matches \"$name\".";

    // One hop only, in both directions. The full blast-radius walk lives in the
    // CMDB module and needs its edge-direction rules; repeating that here would
    // be a second implementation of the thing most likely to be subtly wrong.
    // ⚠️ from_object_id / to_object_id — NOT source_/target_, which is what these
    // were first written as. A wrong column name here does not error, it returns
    // nothing, and Warbot would have calmly reported "no recorded relationships"
    // for every object forever. Read out of information_schema, not remembered.
    $stmt = $conn->prepare(
        "SELECT o.name AS other, rt.verb, rt.inverse_verb,
                CASE WHEN r.from_object_id = :id1 THEN 'out' ELSE 'in' END AS dir
           FROM cmdb_object_relationships r
           JOIN cmdb_relationship_types rt ON rt.id = r.relationship_type_id
           JOIN cmdb_objects o ON o.id = CASE WHEN r.from_object_id = :id2 THEN r.to_object_id ELSE r.from_object_id END
          WHERE r.from_object_id = :id3 OR r.to_object_id = :id4
          ORDER BY o.name LIMIT 25"
    );
    foreach (['id1', 'id2', 'id3', 'id4'] as $p) $stmt->bindValue(':' . $p, (int)$obj['id'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) return sprintf('"%s" exists in the CMDB but has no recorded relationships, so its blast radius is unknown rather than empty.', $obj['name']);

    $lines = [sprintf('"%s" is directly related to %d item(s):', $obj['name'], count($rows))];
    foreach ($rows as $r) {
        $verb = $r['dir'] === 'out' ? $r['verb'] : $r['inverse_verb'];
        $lines[] = sprintf('- %s %s', $verb ?: 'related to', $r['other']);
    }
    $lines[] = 'This is one hop only. Use the CMDB impact view for the full blast radius.';
    return implode("\n", $lines);
}

function warbotToolSearchKnowledge(PDO $conn, array $args, int $analystId): string
{
    $q = trim((string)($args['query'] ?? ''));
    if ($q === '') return 'Give me something to search for.';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';

    $stmt = $conn->prepare(
        "SELECT id, title FROM knowledge_articles
          WHERE title LIKE :q ESCAPE '\\\\'
            AND is_published = 1 AND (is_archived IS NULL OR is_archived = 0)
          ORDER BY view_count DESC, title LIMIT 8"
    );
    $stmt->execute([':q' => $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) return "No published article title matches \"$q\".";
    $lines = [count($rows) . " article(s) matching \"$q\":"];
    foreach ($rows as $r) $lines[] = sprintf('- #%d %s', $r['id'], $r['title']);
    // Titles only, on purpose: an article body pasted into a shared channel is a
    // wall of text nobody asked for, and may contain more than the room needs.
    $lines[] = 'Titles only — open the article in Knowledge to read it.';
    return implode("\n", $lines);
}
