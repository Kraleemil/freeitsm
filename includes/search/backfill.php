<?php
/**
 * Rebuild the search corpus from ticket content already in the database.
 *
 * TEXT ONLY. This reads `tickets.subject`, `emails.body_content` and
 * `ticket_notes.note_text` — all of which are already text. It never opens a
 * file, so no attachment is touched, no archive is unpacked and no extractor is
 * needed. Attachment text is a later, separate piece of work.
 *
 * It is also not a one-off. The same code is the "rebuild the index" command a
 * grown installation needs after a schema change, after the full-text settings
 * are corrected, or simply when someone suspects the corpus has drifted.
 *
 * SAFE TO RE-RUN. Every write is an upsert keyed on (source_type, source_id), so
 * running it twice updates in place rather than duplicating. It only ever writes
 * to `search_documents` — the source tables are read and never modified.
 *
 * ⚠️ COMMITS AS IT GOES, in batches. It cannot wrap the whole run in one
 * transaction: InnoDB does not expose uncommitted rows to MATCH...AGAINST, so
 * nothing indexed would be searchable until the very end, and a long run would
 * hold a huge transaction open for no benefit.
 */

require_once __DIR__ . '/corpus.php';

/**
 * @param callable|null $progress fn(string $stage, int $done, int $total): void
 * @return array{tickets:int,emails:int,notes:int,skipped:int,seconds:float}
 */
function searchBackfillRun(PDO $conn, array $opts = [], ?callable $progress = null): array {
    $batch     = max(50, min(2000, (int)($opts['batch'] ?? 500)));
    $maxBody   = max(1000, (int)($opts['max_body_chars'] ?? 200000));
    $sinceId   = (int)($opts['since_ticket_id'] ?? 0);
    $limit     = (int)($opts['limit'] ?? 0);          // 0 = everything; used for a quick sample
    $started   = microtime(true);
    $counts    = ['tickets' => 0, 'emails' => 0, 'notes' => 0, 'skipped' => 0];

    if (!searchCorpusReady($conn)) {
        throw new RuntimeException('search_documents does not exist — run Database Verification first.');
    }

    // Deleted tickets are skipped rather than indexed-then-hidden: a trashed
    // ticket's words should not sit in a searchable table at all.
    $where = "t.deleted_datetime IS NULL AND t.id > ?";
    $args  = [$sinceId];
    $limitSql = $limit > 0 ? " LIMIT $limit" : '';

    $cnt = $conn->prepare("SELECT COUNT(*) FROM tickets t WHERE $where");
    $cnt->execute($args);
    $total = (int)$cnt->fetchColumn();

    $sel = $conn->prepare("SELECT t.id, t.subject, t.tenant_id, t.created_datetime
                             FROM tickets t
                            WHERE $where
                            ORDER BY t.id$limitSql");
    $sel->execute($args);
    $tickets = $sel->fetchAll(PDO::FETCH_ASSOC);

    $emailSel = $conn->prepare("SELECT id, subject, body_content, body_type, received_datetime
                                  FROM emails WHERE ticket_id = ?");
    $noteSel  = $conn->prepare("SELECT id, note_text, is_internal, created_datetime
                                  FROM ticket_notes WHERE ticket_id = ?");

    $done = 0;
    $conn->beginTransaction();
    foreach ($tickets as $t) {
        $ticketId = (int)$t['id'];
        // NULL here means "the default company" for a ticket — searchCorpusTicketScope
        // records that as a scope rather than leaving NULL to be re-interpreted later.
        [$tenantId, $scope] = searchCorpusTicketScope(
            $t['tenant_id'] === null ? null : (int)$t['tenant_id']
        );

        // 1. the subject, as its own row — so "matched the subject" can be stated
        //    to the user and weighted separately from body text
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
        $emailSel->execute([$ticketId]);
        foreach ($emailSel->fetchAll(PDO::FETCH_ASSOC) as $e) {
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

        // 3. every note. is_internal is carried as a FACT, so the search
        //    predicate can exclude them rather than the caller filtering after.
        $noteSel->execute([$ticketId]);
        foreach ($noteSel->fetchAll(PDO::FETCH_ASSOC) as $n) {
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

        if (++$done % $batch === 0) {
            $conn->commit();
            $conn->beginTransaction();
            if ($progress) $progress('tickets', $done, $total);
        }
    }
    $conn->commit();
    if ($progress) $progress('tickets', $done, $total);

    $counts['seconds'] = round(microtime(true) - $started, 2);
    return $counts;
}

/**
 * Remove every corpus row whose ticket has since been deleted or trashed.
 * The foreign key handles a hard DELETE; this covers the soft-delete case.
 */
function searchBackfillPrune(PDO $conn): int {
    $sql = "DELETE sd FROM search_documents sd
            JOIN tickets t ON t.id = sd.ticket_id
            WHERE t.deleted_datetime IS NOT NULL";
    $st = $conn->prepare($sql);
    $st->execute();
    return $st->rowCount();
}
