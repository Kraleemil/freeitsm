<?php
/**
 * Keep the search corpus current as tickets happen.
 *
 * Until this existed, `scripts/search_backfill.php` was the ONLY thing that ever
 * wrote to the corpus, and somebody had to remember to run it. A ticket raised
 * this morning was invisible to search until then. That is worse than having no
 * search: people trust it, find nothing, and conclude the thing never happened.
 *
 * HOW IT HOOKS IN
 * ---------------
 * It subscribes to `WorkflowEngine::dispatch`, the same seam the notification
 * bell uses, rather than adding calls at every place a ticket changes. See
 * workflow/includes/engine.php — subscribers run in their own try/catch, before
 * the workflow loop, so none of them can break the others or the host request.
 *
 * ⚠️ WHOLE TICKETS, NOT SINGLE ROWS
 * Every event reindexes the ticket entire: its subject, all its messages, all
 * its notes. That looks wasteful and is deliberate.
 *
 *  - It is ORDERING-IMMUNE. Some paths announce before the opening message is
 *    written, some after. Indexing "the row that just changed" would need every
 *    caller to fire at exactly the right moment; reindexing the ticket does not
 *    care when it is told.
 *  - It is SELF-HEALING. Any row a missed event would have left stale is
 *    rewritten by the next event on that ticket.
 *  - It is CHEAP. A ticket is a handful of rows, and every write is an upsert.
 *
 * ⚠️ It must be called AFTER the host's transaction commits. InnoDB does not
 * expose uncommitted rows to MATCH...AGAINST, and a rolled-back transaction
 * would otherwise leave corpus rows describing a ticket that never existed.
 *
 * The document shapes live here and `searchBackfillRun()` calls them, so a
 * live-indexed ticket and a backfilled one are byte-for-byte the same. If those
 * two ever disagreed, a result would depend on HOW a ticket came to be indexed,
 * which is close to undebuggable.
 */

require_once __DIR__ . '/corpus.php';

/** Default cap on how much of one body is indexed. Mirrors the backfill's. */
const SEARCH_INDEX_MAX_BODY = 200000;

/**
 * Index (or reindex) one ticket and everything on it.
 *
 * Safe to call for a ticket that does not exist, or one that is in the trash —
 * a deleted ticket's words are removed rather than left sitting in a searchable
 * table.
 *
 * @return array{tickets:int,emails:int,notes:int,skipped:int}
 */
function searchIndexTicket(PDO $conn, int $ticketId, int $maxBody = SEARCH_INDEX_MAX_BODY): array
{
    $counts = ['tickets' => 0, 'emails' => 0, 'notes' => 0, 'skipped' => 0];
    if ($ticketId <= 0) return $counts;

    $tStmt = $conn->prepare(
        "SELECT id, subject, tenant_id, created_datetime, deleted_datetime
           FROM tickets WHERE id = ?"
    );
    $tStmt->execute([$ticketId]);
    $t = $tStmt->fetch(PDO::FETCH_ASSOC);

    // Gone, or in the trash: drop whatever we had rather than keep it findable.
    if (!$t || $t['deleted_datetime'] !== null) {
        searchCorpusDeleteTicket($conn, $ticketId);
        return $counts;
    }

    [$tenantId, $scope] = searchCorpusTicketScope(
        $t['tenant_id'] === null ? null : (int)$t['tenant_id']
    );

    // 1. the subject, as its own row — so "matched the subject" can be stated to
    //    the user and weighted separately from body text
    searchCorpusUpsert($conn, [
        'source_type'     => SEARCH_SOURCE_TICKET,
        'source_id'       => $ticketId,
        'ticket_id'       => $ticketId,
        'tenant_id'       => $tenantId,
        'tenant_scope'    => $scope,
        'is_internal'     => 0,
        'title'           => (string)$t['subject'],
        'body'            => '',
        'source_datetime' => $t['created_datetime'],
    ]);
    $counts['tickets']++;

    // 2. every message on the ticket
    $eStmt = $conn->prepare("SELECT id, subject, body_content, received_datetime
                               FROM emails WHERE ticket_id = ?");
    $eStmt->execute([$ticketId]);
    foreach ($eStmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $body = searchCorpusPlainText((string)($e['body_content'] ?? ''), $maxBody);
        if ($body === '' && (string)($e['subject'] ?? '') === '') { $counts['skipped']++; continue; }
        searchCorpusUpsert($conn, [
            'source_type'     => SEARCH_SOURCE_EMAIL,
            'source_id'       => (int)$e['id'],
            'ticket_id'       => $ticketId,
            'tenant_id'       => $tenantId,
            'tenant_scope'    => $scope,
            'is_internal'     => 0,
            'title'           => (string)($e['subject'] ?? ''),
            'body'            => $body,
            'source_datetime' => $e['received_datetime'],
        ]);
        $counts['emails']++;
    }

    // 3. every note. is_internal is carried as a FACT, so the search predicate
    //    can exclude them rather than the caller filtering after.
    $nStmt = $conn->prepare("SELECT id, note_text, is_internal, created_datetime
                               FROM ticket_notes WHERE ticket_id = ?");
    $nStmt->execute([$ticketId]);
    foreach ($nStmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $body = searchCorpusPlainText((string)$n['note_text'], $maxBody);
        if ($body === '') { $counts['skipped']++; continue; }
        searchCorpusUpsert($conn, [
            'source_type'     => SEARCH_SOURCE_NOTE,
            'source_id'       => (int)$n['id'],
            'ticket_id'       => $ticketId,
            'tenant_id'       => $tenantId,
            'tenant_scope'    => $scope,
            // NULL defaults to internal in ticket_notes, so treat it as internal.
            'is_internal'     => ($n['is_internal'] === null ? 1 : (int)$n['is_internal']),
            'title'           => '',
            'body'            => $body,
            'source_datetime' => $n['created_datetime'],
        ]);
        $counts['notes']++;
    }

    return $counts;
}

/**
 * The dispatch subscriber. Called for EVERY workflow event, so it decides
 * quickly whether it cares and gets out of the way.
 *
 * Never throws: a search index that cannot be updated must not cost anybody
 * their ticket, their note or their reply.
 */
function searchIndexHandleEvent(string $event, array $payload): void
{
    // Events that mean "this ticket's text may have changed". Deliberately a
    // short list: status and priority changes move no words about, so indexing
    // on them would be pure cost.
    static $INTERESTING = [
        'ticket.created'        => true,
        'ticket.note_added'     => true,
        'ticket.reply_received' => true,
        'ticket.subject_changed'=> true,
        'ticket.restored'       => true,
        'ticket.deleted'        => true,
    ];
    if (!isset($INTERESTING[$event])) return;

    try {
        $ticketId = 0;
        if (isset($payload['ticket']['id']))  $ticketId = (int)$payload['ticket']['id'];
        elseif (isset($payload['ticket_id'])) $ticketId = (int)$payload['ticket_id'];
        if ($ticketId <= 0) return;

        $conn = connectToDatabase();

        // An install that has never run Database Verification has no corpus.
        // That is a normal state, not a fault, so say nothing.
        if (!searchCorpusReady($conn)) return;

        // A delete is handled inside searchIndexTicket too (it re-reads the row
        // and finds deleted_datetime set), but calling it explicitly here means
        // the removal does not depend on the soft-delete having landed first.
        if ($event === 'ticket.deleted') {
            searchCorpusDeleteTicket($conn, $ticketId);
            return;
        }

        searchIndexTicket($conn, $ticketId);
    } catch (Throwable $e) {
        error_log('[searchIndexHandleEvent] ' . $event . ': ' . $e->getMessage());
    }
}
