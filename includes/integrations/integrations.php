<?php
/**
 * Integrations service — loading connections, escalating a work item to an
 * external tracker, and refreshing the status of what we have already linked.
 *
 * This is the ONE place an escalation happens. The workflow action
 * (escalate_to_tracker) and the manual right-click both call
 * integrationsEscalate(); there is deliberately not a second implementation for
 * the "automatic" path, because the guards below would then exist twice and one
 * copy would eventually drift.
 *
 * ⚠️ SECURITY: integrationsCompaniesCompatible() is an isolation boundary, not a
 * validation nicety. Read its docblock before changing anything here.
 */

require_once __DIR__ . '/IssueTrackerProvider.php';
require_once __DIR__ . '/IssueDoc.php';
require_once __DIR__ . '/JiraProvider.php';

// ⚠️ Self-contained on purpose. These were originally left to the caller, which
// worked only because the first callers (the settings endpoints) happened to
// require them already — the workflow engine does not, and the escalate action
// fataled on decryptValue() the first time it was actually run. A shared service
// cannot assume anything about who called it.
require_once __DIR__ . '/../encryption.php';   // decryptValue()
require_once __DIR__ . '/../ssl.php';          // sslApplyCurl(), used by every provider's httpRequest()
require_once __DIR__ . '/../tenancy.php';      // getDefaultTenantId(), for the company guard

/**
 * Are the integration tables present?
 *
 * Every read below goes through this. An install that has not run Database
 * Verification yet has no tables, and an unguarded query would throw inside the
 * ticket view — which is how a missing gate once produced an empty inbox.
 * "Not set up" must look like "no linked issues", never like an error.
 */
function integrationsSchemaReady(PDO $conn): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $conn->query("SELECT 1 FROM integration_connections LIMIT 1");
        $conn->query("SELECT 1 FROM integration_links LIMIT 1");
        return $ready = true;
    } catch (Exception $e) {
        return $ready = false;
    }
}

/**
 * The provider registry — the ONE list adding a tracker touches at this layer.
 *
 * `credential_fields` drives the settings form, so a provider whose auth looks
 * nothing like Jira's needs no change to the page: it declares its own fields and
 * the form renders them. That is the difference between "adding GitHub is one
 * file" and "adding GitHub means editing the settings screen too".
 *
 * `url_hint` / `url_label` exist because "Site URL" means something different per
 * tracker and a wrong-shaped URL is the most likely setup mistake.
 */
function integrationsAvailableProviders(): array
{
    return [
        'jira' => [
            'key'         => 'jira',
            'name'        => 'Jira',
            'blurb'       => 'system.integrations.jira_blurb',
            'url_label'   => 'system.integrations.jira_url_label',
            'url_hint'    => 'https://yourcompany.atlassian.net',
            'credential_fields' => [
                // Cloud wants email + API token; Data Center wants only a PAT. Both
                // are offered and the connector uses whichever its flavour needs.
                ['key' => 'email',     'label' => 'system.integrations.field_email',  'type' => 'text',     'required' => false],
                ['key' => 'api_token', 'label' => 'system.integrations.field_token',  'type' => 'password', 'required' => true],
            ],
        ],
    ];
}

/** One registry entry, or null if the key is not a provider we ship. */
function integrationsProviderMeta(string $key): ?array
{
    $all = integrationsAvailableProviders();
    return $all[$key] ?? null;
}

/**
 * An email body reduced to text worth putting in an issue description.
 *
 * ⚠️ `strip_tags()` alone is NOT enough, and this is not theoretical — the first
 * real ticket tried produced a Jira description containing several hundred lines
 * of the email's CSS. strip_tags removes the TAGS but keeps the CONTENT of
 * <style> and <script>, so a marketing-styled email dumps its entire stylesheet
 * into the description.
 *
 * So: kill those elements outright, turn block ends into newlines so paragraphs
 * survive, then collapse the whitespace an HTML mail is full of (&nbsp; runs,
 * tabs, CRLF pairs).
 *
 * $maxChars is a sanity cap, not a policy — a 200KB newsletter helps nobody, and
 * a dev reading the issue wants the request, not the footer. The link back to
 * the ticket is always there for the full thread.
 */
function integrationsBodyToText(?string $raw, string $bodyType = 'text', int $maxChars = 8000): string
{
    $s = (string) $raw;
    if ($s === '') return '';

    if (strtolower($bodyType) === 'html' || stripos($s, '<html') !== false || stripos($s, '<div') !== false) {
        // Content-bearing elements that must go entirely, not just lose their tags.
        $s = preg_replace('#<(style|script|head|title)\b[^>]*>.*?</\1>#is', ' ', $s);
        // Block boundaries become newlines so the text does not run together.
        $s = preg_replace('#<(br|/p|/div|/tr|/li|/h[1-6])\b[^>]*>#i', "\n", $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // NBSP (both the entity-decoded char and the raw byte) reads as a space.
    $s = str_replace(["\xC2\xA0", "\r\n", "\r"], [' ', "\n", "\n"], $s);
    $s = preg_replace('/[ \t]+/u', ' ', $s);          // runs of spaces
    $s = preg_replace('/ *\n */u', "\n", $s);         // trim around newlines
    $s = preg_replace('/\n{3,}/u', "\n\n", $s);       // never more than one blank line
    $s = trim($s);

    if (mb_strlen($s) > $maxChars) {
        $s = mb_substr($s, 0, $maxChars) . "\n\n… (truncated — see the ticket for the full thread)";
    }
    return $s;
}

/** provider string → concrete connector. */
function integrationsProviderFor(array $connection): IssueTrackerProvider
{
    switch ($connection['provider'] ?? '') {
        case 'jira':
            return new JiraProvider($connection);
        default:
            throw new Exception('Unknown integration provider: ' . ($connection['provider'] ?? '?'));
    }
}

/** Decrypt + JSON-decode a stored credentials blob (never throws). */
function integrationsDecodeCredentials($stored): array
{
    if ($stored === null || $stored === '') return [];
    try {
        $plain = decryptValue($stored);
    } catch (Exception $e) {
        return [];
    }
    $decoded = json_decode((string) $plain, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Load a connection with its secrets decrypted. Returns null if missing or if
 * the schema is not there yet.
 */
function integrationsLoadConnection(PDO $conn, $connectionId): ?array
{
    if (!integrationsSchemaReady($conn)) return null;
    try {
        $stmt = $conn->prepare("SELECT * FROM integration_connections WHERE id = ?");
        $stmt->execute([(int) $connectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
    if (!$row) return null;

    $row['credentials'] = integrationsDecodeCredentials($row['credentials'] ?? null);
    // decryptValue returns the value unchanged without the ENC: prefix, so
    // pre-encryption rows still work (migration-safe).
    if (!empty($row['webhook_secret'])) {
        try { $row['webhook_secret'] = decryptValue($row['webhook_secret']); } catch (Exception $e) { /* leave */ }
    }
    $row['is_active'] = (bool) ($row['is_active'] ?? 1);
    return $row;
}

/**
 * List connections for the admin UI.
 *
 * ⚠️ DELIBERATELY UNFILTERED by company. This is a CONNECTION-shaped table (the
 * same shape as messaging_channels and mailboxes), where tenant_id NULL means
 * SHARED across every company rather than "the Default company's". An admin
 * configuring routing needs to see every connection at once. Scoping this with
 * activeTenantFilter() would hide every shared connection from every client
 * company — see Multi-Tenancy-Developer-Guide §1 for all three meanings of NULL.
 *
 * ⚠️ Secrets are NEVER returned. `has_credentials` is a boolean and that is all
 * the UI gets. A read that hands back credentials needs the same care as a
 * write, and an unfiltered list that leaked tokens would be a cross-company
 * credential leak rather than a convenience.
 */
function integrationsListConnections(PDO $conn, bool $activeOnly = false): array
{
    if (!integrationsSchemaReady($conn)) return [];
    $sql = "SELECT id, name, provider, base_url, auth_type, ingress_mode, inbound_enabled,
                   poll_interval_minutes, account_identity, tenant_id, is_active,
                   last_poll_datetime, created_datetime,
                   (credentials IS NOT NULL AND credentials <> '') AS has_credentials,
                   (webhook_secret IS NOT NULL AND webhook_secret <> '') AS has_webhook_secret
            FROM integration_connections";
    if ($activeOnly) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY name";
    try {
        $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
    foreach ($rows as &$r) {
        $r['has_credentials']     = (bool) $r['has_credentials'];
        $r['has_webhook_secret']  = (bool) $r['has_webhook_secret'];
        $r['is_active']           = (bool) $r['is_active'];
        $r['inbound_enabled']     = (bool) $r['inbound_enabled'];
    }
    return $rows;
}

/**
 * ⚠️ THE ISOLATION BOUNDARY. Read this before touching it.
 *
 * May a work item belonging to company $entityTenantId be escalated into
 * $connectionTenantId's tracker?
 *
 *   connection tenant NULL  → SHARED: serves every company. Anything may go there
 *                             (an MSP's own Jira, used for all their clients).
 *   connection tenant set   → PINNED: only that company's work items.
 *
 * A NULL work item means "unrouted, treated as the Default company's", so it is
 * resolved to the Default company id before comparing — otherwise a Default
 * ticket could never reach a connection pinned to Default.
 *
 * Why this exists: escalation is driven by a WORKFLOW, and a workflow's
 * conditions are editable by anyone who can author workflows. The wiki is blunt
 * that company routing must never be a workflow rule
 * (Multi-Tenancy-Developer-Guide §1 — "a hardcoded synchronous membrane"). This
 * is the outbound twin of that rule: without it, one mis-scoped workflow
 * escalates Acme's ticket content into Globex's Jira. So the check lives HERE,
 * in code, and never in anything an admin can edit.
 *
 * On a single-company install every call is trivially true, which is correct
 * rather than a bypass — there is only one company for anything to leak to.
 */
function integrationsCompaniesCompatible(?int $entityTenantId, ?int $connectionTenantId, ?int $defaultTenantId = null): bool
{
    if ($connectionTenantId === null) {
        return true;                       // shared connection: serves everyone
    }
    $effective = $entityTenantId ?? $defaultTenantId;   // NULL work item = the Default company's
    if ($effective === null) {
        return false;                      // unknown owner + pinned target = refuse, never guess
    }
    return $effective === $connectionTenantId;
}

/** The tenant_id of a ticket / problem / change, or null. */
function integrationsEntityTenantId(PDO $conn, string $entityType, int $entityId): ?int
{
    $table = [
        'ticket'  => 'tickets',
        'problem' => 'problem_tickets',
        'change'  => 'change_tickets',
    ][$entityType] ?? null;
    if ($table === null) return null;
    try {
        $stmt = $conn->prepare("SELECT tenant_id FROM `$table` WHERE id = ?");
        $stmt->execute([$entityId]);
        $v = $stmt->fetchColumn();
    } catch (Exception $e) {
        return null;
    }
    return ($v === false || $v === null) ? null : (int) $v;
}

/** Links for one work item, newest first. Empty when the schema is not ready. */
function integrationsLinksFor(PDO $conn, string $entityType, int $entityId): array
{
    if (!integrationsSchemaReady($conn)) return [];
    try {
        $stmt = $conn->prepare(
            "SELECT l.*, c.name AS connection_name, c.provider, c.is_active AS connection_active
             FROM integration_links l
             JOIN integration_connections c ON c.id = l.connection_id
             WHERE l.entity_type = ? AND l.entity_id = ?
             ORDER BY l.created_datetime DESC"
        );
        $stmt->execute([$entityType, $entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/** Is this work item already linked to this connection? (drives skip_if_linked) */
function integrationsAlreadyLinked(PDO $conn, int $connectionId, string $entityType, int $entityId): bool
{
    if (!integrationsSchemaReady($conn)) return false;
    try {
        $stmt = $conn->prepare(
            "SELECT 1 FROM integration_links
             WHERE connection_id = ? AND entity_type = ? AND entity_id = ? LIMIT 1"
        );
        $stmt->execute([$connectionId, $entityType, $entityId]);
        return (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Escalate a work item into an external tracker: create the issue, record the
 * link, return it.
 *
 * The single entry point for BOTH the workflow action and the manual button.
 *
 * @param array $opts  ['entity_type'=>'ticket', 'entity_id'=>1042,
 *                      'connection_id'=>3, 'target'=>['project'=>'OPS','issue_type'=>'Bug'],
 *                      'summary'=>'…', 'body'=>IssueDoc, 'analyst_id'=>7,
 *                      'skip_if_linked'=>true, 'dry_run'=>false]
 * @return array the created link row, or ['skipped'=>true, …] / ['dry_run'=>true, …]
 */
function integrationsEscalate(PDO $conn, array $opts): array
{
    $entityType = (string)($opts['entity_type'] ?? 'ticket');
    $entityId   = (int)($opts['entity_id'] ?? 0);
    $connId     = (int)($opts['connection_id'] ?? 0);
    $body       = $opts['body'] ?? null;

    if (!integrationsSchemaReady($conn)) {
        throw new Exception('Integrations are not set up on this install yet — run Database Verification.');
    }
    if ($entityId <= 0 || $connId <= 0) {
        throw new Exception('Escalation needs a work item and a connection.');
    }
    if (!$body instanceof IssueDoc) {
        throw new Exception('Escalation needs an IssueDoc body.');
    }

    $connection = integrationsLoadConnection($conn, $connId);
    if (!$connection)                 throw new Exception('That integration connection no longer exists.');
    if (empty($connection['is_active'])) throw new Exception('That integration connection is switched off.');

    // ── The isolation boundary. Before any network call, before any write. ──
    $entityTenant  = integrationsEntityTenantId($conn, $entityType, $entityId);
    $connTenant    = isset($connection['tenant_id']) && $connection['tenant_id'] !== null
                        ? (int) $connection['tenant_id'] : null;
    $defaultTenant = function_exists('getDefaultTenantId') ? @getDefaultTenantId($conn) : null;
    if (!integrationsCompaniesCompatible($entityTenant, $connTenant, $defaultTenant)) {
        throw new Exception(
            'That tracker belongs to a different company than this ticket, so it cannot be escalated there.'
        );
    }

    if (!empty($opts['skip_if_linked'])
        && integrationsAlreadyLinked($conn, $connId, $entityType, $entityId)) {
        return ['skipped' => true, 'reason' => 'already_linked'];
    }

    $provider = integrationsProviderFor($connection);
    $summary  = (string)($opts['summary'] ?? '');
    $target   = (array)($opts['target'] ?? []);

    // Dry run describes, never creates. A workflow test that mints a real Jira
    // issue is an unacceptable surprise, so this returns BEFORE the network call.
    if (!empty($opts['dry_run'])) {
        return [
            'dry_run'    => true,
            'connection' => $connection['name'],
            'target'     => $target,
            'summary'    => $summary,
            'preview'    => $body->toPlainText(),
        ];
    }

    $issue = $provider->createIssue($target, $summary, $body, (array)($opts['fields'] ?? []));

    $stmt = $conn->prepare(
        "INSERT INTO integration_links
            (connection_id, entity_type, entity_id, external_id, external_key, external_url,
             status_name, status_category, assignee_name, last_synced_datetime, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?)"
    );
    $stmt->execute([
        $connId, $entityType, $entityId,
        $issue['external_id'], $issue['external_key'] ?? null, $issue['external_url'] ?? null,
        $issue['status_name'] ?? null, $issue['status_category'] ?? null,
        $issue['assignee_name'] ?? null,
        ($opts['analyst_id'] ?? null) ?: null,
    ]);

    return array_merge($issue, [
        'id'            => (int) $conn->lastInsertId(),
        'connection_id' => $connId,
        'entity_type'   => $entityType,
        'entity_id'     => $entityId,
    ]);
}

// =====================================================================
//  Comments coming back — the inbound half
// =====================================================================

/**
 * Is the comment map present? Separate from integrationsSchemaReady() because it
 * arrived a release later: an install that ran Database Verification for V1 but
 * not since has the links but not the map, and must degrade to "no comments come
 * back" rather than throwing inside the poll or the ticket view.
 */
function integrationsCommentSchemaReady(PDO $conn): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $conn->query("SELECT 1 FROM integration_comment_map LIMIT 1");
        return $ready = true;
    } catch (Exception $e) {
        return $ready = false;
    }
}

/**
 * ⚠️ ECHO SUPPRESSION, GUARD 2 — is this inbound comment our own write coming
 * back?
 *
 * The failure mode this exists to stop: we push a note to Jira → the poll sees a
 * new comment → we import it as a note → which pushes again → forever. It is the
 * single thing most likely to make two-way sync unusable, and it is cheap here
 * and miserable to retrofit.
 *
 * This is the identity half: anything authored by the account our own token
 * authenticates as is ours. Guard 1 (the comment map's UNIQUE key) is exact and
 * catches the specific comments we created; this one also catches what guard 1
 * cannot, because it does not depend on us having recorded the write.
 *
 * ⚠️ An UNKNOWN identity is never treated as ours. A tracker that stops sending
 * an author must not silently swallow every comment — it must let them through
 * and be noisy, because the failure is then visible instead of invisible.
 *
 * Pure on purpose: the whole decision is provable without a database.
 */
function integrationsCommentIsEcho(array $event, ?string $accountIdentity): bool
{
    $author = trim((string)($event['author_identity'] ?? ''));
    $us     = trim((string)($accountIdentity ?? ''));
    if ($author === '' || $us === '') return false;
    return $author === $us;
}

/**
 * Should this comment event become a note? The complete decision, pure.
 *
 * @param string[] $knownCommentIds ids already imported for this link (guard 1)
 * @return string '' to import, otherwise the reason it was dropped
 */
function integrationsCommentSkipReason(array $event, ?string $accountIdentity, array $knownCommentIds, string $entityType = 'ticket'): string
{
    if (($event['type'] ?? '') !== 'comment_added')                return 'not_a_comment';
    if ($entityType !== 'ticket')                                  return 'unsupported_entity';
    if (trim((string)($event['comment_body'] ?? '')) === '')       return 'empty';
    if ((string)($event['comment_id'] ?? '') === '')               return 'no_comment_id';
    if (integrationsCommentIsEcho($event, $accountIdentity))       return 'echo';
    if (in_array((string)$event['comment_id'], array_map('strval', $knownCommentIds), true)) {
        return 'already_imported';
    }
    return '';
}

/** Comment ids already mapped for one link, in either direction. */
function integrationsKnownCommentIds(PDO $conn, int $linkId): array
{
    if (!integrationsCommentSchemaReady($conn)) return [];
    try {
        $stmt = $conn->prepare("SELECT external_comment_id FROM integration_comment_map WHERE link_id = ?");
        $stmt->execute([$linkId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Record a comment we PUSHED, so it is recognised if it comes back.
 *
 * Guard 1 of echo suppression. Called by send_note_to_tracker straight after the
 * provider returns the new comment's id. Never throws: failing to record an echo
 * marker must not fail the workflow run that successfully posted the comment.
 */
function integrationsRecordOutboundComment(PDO $conn, int $linkId, string $externalCommentId, ?int $localNoteId = null): void
{
    if ($externalCommentId === '' || !integrationsCommentSchemaReady($conn)) return;
    try {
        $conn->prepare(
            "INSERT INTO integration_comment_map
                (link_id, direction, external_comment_id, local_note_id, created_datetime)
             VALUES (?, 'out', ?, ?, UTC_TIMESTAMP())"
        )->execute([$linkId, $externalCommentId, $localNoteId ?: null]);
    } catch (Exception $e) {
        // Duplicate key or a missing table — neither is worth failing a send for.
    }
}

/**
 * How an imported comment reads on the ticket.
 *
 * The attribution lives in the note TEXT, not only in the display join, because
 * plenty of things read note_text directly — the REST API, the portal, the AI
 * write-up. A note that says only "any update?" with no hint it came from a dev
 * in Jira is worse than no note.
 */
function integrationsCommentNoteText(array $event, array $link): string
{
    $ref    = (string)($link['external_key'] ?: $link['external_id']);
    $author = trim((string)($event['author_name'] ?? ''));
    $header = $ref . ($author !== '' ? ' · comment from ' . $author : ' · new comment');
    return $header . "\n\n" . trim((string)($event['comment_body'] ?? ''));
}

/**
 * Apply one canonical comment event: an internal note on the ticket, plus the
 * map row that stops it ever being imported twice.
 *
 * ⚠️ THE MAP ROW IS WRITTEN FIRST, and its UNIQUE (link_id, external_comment_id)
 * key is what actually guarantees "once". Writing the note first and the marker
 * second would let two overlapping cron runs both pass the "already imported?"
 * read and both post the note — the classic check-then-act race. Here the second
 * writer loses on the key before any note exists.
 *
 * ⚠️ Always internal. A Jira comment is dev-to-analyst; it is written by people
 * who do not know a customer can see it, and it must never reach the requester
 * except by an analyst deciding so.
 *
 * @return string '' if imported, otherwise the reason it was skipped
 */
function integrationsApplyCommentEvent(PDO $conn, array $connection, array $link, array $event): string
{
    $entityType = (string)($link['entity_type'] ?? 'ticket');
    $reason = integrationsCommentSkipReason(
        $event,
        $connection['account_identity'] ?? null,
        integrationsKnownCommentIds($conn, (int)$link['id']),
        $entityType
    );
    if ($reason !== '') return $reason;

    try {
        $conn->prepare(
            "INSERT INTO integration_comment_map
                (link_id, direction, external_comment_id, author_identity, author_name, created_datetime)
             VALUES (?, 'in', ?, ?, ?, UTC_TIMESTAMP())"
        )->execute([
            (int)$link['id'],
            (string)$event['comment_id'],
            (string)($event['author_identity'] ?? '') ?: null,
            (string)($event['author_name'] ?? '') ?: null,
        ]);
    } catch (Exception $e) {
        // Lost the race (or the row was already there) — the other writer owns it.
        return 'already_imported';
    }
    $mapId = (int) $conn->lastInsertId();

    // analyst_id 0: a Jira comment has no FreeITSM author. Readers LEFT JOIN
    // analysts and fall back to the connection's name — see api/tickets/get_notes.php.
    $conn->prepare(
        "INSERT INTO ticket_notes (ticket_id, analyst_id, note_text, is_internal, created_datetime)
         VALUES (?, 0, ?, 1, UTC_TIMESTAMP())"
    )->execute([(int)$link['entity_id'], integrationsCommentNoteText($event, $link)]);
    $noteId = (int) $conn->lastInsertId();

    $conn->prepare("UPDATE integration_comment_map SET local_note_id = ? WHERE id = ?")
         ->execute([$noteId, $mapId]);
    $conn->prepare("UPDATE tickets SET updated_datetime = UTC_TIMESTAMP() WHERE id = ?")
         ->execute([(int)$link['entity_id']]);

    return '';
}

/**
 * Pull new comments for every link on one connection. The second half of the
 * poll cron's unit of work.
 *
 * The watermark is stamped from BEFORE the provider call, not after, so a comment
 * posted while the poll was running lands inside the next window rather than in
 * the gap between them. It is only advanced on success: a failed pull must be
 * retried against the same window, not skipped past.
 *
 * @return array ['pulled'=>int, 'imported'=>int, 'skipped'=>['echo'=>n, …]]
 */
function integrationsPullComments(PDO $conn, array $connection): array
{
    $out = ['pulled' => 0, 'imported' => 0, 'skipped' => []];

    if (!integrationsSchemaReady($conn) || !integrationsCommentSchemaReady($conn)) return $out;
    if (empty($connection['inbound_enabled'])) return $out;   // off by default; nothing is written until an admin says so

    $provider = integrationsProviderFor($connection);
    if (!$provider->supports(IssueTrackerProvider::CAP_POLLING)) return $out;

    $stmt = $conn->prepare("SELECT * FROM integration_links WHERE connection_id = ?");
    $stmt->execute([(int)$connection['id']]);
    $links = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $links[(string)$l['external_id']] = $l;
    }

    $startedAt = gmdate('Y-m-d H:i:s');
    $watermark = !empty($connection['last_poll_watermark']) ? (string)$connection['last_poll_watermark'] : null;

    if ($links) {
        $events = $provider->pollChanges($watermark, array_keys($links));
        $out['pulled'] = count($events);

        foreach ($events as $event) {
            $link = $links[(string)($event['external_id'] ?? '')] ?? null;
            if (!$link) continue;   // an event about an issue we no longer track
            $reason = integrationsApplyCommentEvent($conn, $connection, $link, $event);
            if ($reason === '') {
                $out['imported']++;
            } else {
                $out['skipped'][$reason] = ($out['skipped'][$reason] ?? 0) + 1;
            }
        }
    }

    $conn->prepare("UPDATE integration_connections SET last_poll_watermark = ? WHERE id = ?")
         ->execute([$startedAt, (int)$connection['id']]);

    return $out;
}

/**
 * Refresh the cached status of every link on one connection. The poll cron's
 * unit of work.
 *
 * Batched through fetchIssues() — one JQL call rather than one per link — and
 * only rows whose status actually moved are written, so a quiet day costs one
 * read and no writes.
 *
 * @return array ['checked'=>int, 'changed'=>int, 'changes'=>[ ['link'=>row,
 *                'from'=>?string, 'to'=>?string], … ]]
 */
function integrationsRefreshConnection(PDO $conn, array $connection): array
{
    $out = ['checked' => 0, 'changed' => 0, 'changes' => []];
    if (!integrationsSchemaReady($conn)) return $out;

    $stmt = $conn->prepare("SELECT * FROM integration_links WHERE connection_id = ?");
    $stmt->execute([(int) $connection['id']]);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$links) return $out;

    $provider = integrationsProviderFor($connection);
    $issues   = $provider->fetchIssues(array_column($links, 'external_id'));
    $out['checked'] = count($links);

    foreach ($links as $link) {
        $fresh = $issues[$link['external_id']] ?? null;
        if (!$fresh) continue;   // absent from the batch: leave the cached value rather than blanking it

        $movedCategory = ($fresh['status_category'] ?? null) !== ($link['status_category'] ?? null);
        $movedName     = ($fresh['status_name'] ?? null)     !== ($link['status_name'] ?? null);
        $movedAssignee = ($fresh['assignee_name'] ?? null)   !== ($link['assignee_name'] ?? null);
        if (!$movedCategory && !$movedName && !$movedAssignee) continue;

        $upd = $conn->prepare(
            "UPDATE integration_links
             SET status_name = ?, status_category = ?, assignee_name = ?,
                 last_synced_datetime = UTC_TIMESTAMP()
             WHERE id = ?"
        );
        $upd->execute([
            $fresh['status_name'] ?? null,
            $fresh['status_category'] ?? null,
            $fresh['assignee_name'] ?? null,
            $link['id'],
        ]);

        $out['changed']++;
        // Only a CATEGORY move is worth telling the rest of the app about. A
        // rename from "In Progress" to "In progress" is not an event, and firing
        // a workflow for it would wake the requester up for nothing.
        if ($movedCategory) {
            $out['changes'][] = [
                'link' => $link,
                'from' => $link['status_category'] ?? null,
                'to'   => $fresh['status_category'] ?? null,
            ];
        }
    }
    return $out;
}
