<?php
/**
 * The pending-extraction queue (discussion #53, tier 2).
 *
 * WHY A QUEUE AT ALL
 * ------------------
 * Tier 1 reads a `.docx` in milliseconds, so it happens inline. Tier 2 does not:
 * OCR on a scanned document takes seconds to minutes per page. Doing that inside
 * the mailbox poll would stall every ticket queued behind it, and inside a web
 * request it would simply time out.
 *
 * So an attachment that needs the external extractor is recorded as `pending`
 * and read later. `pending` is also what a REACHABILITY failure writes — see
 * tikaExtract() — because "the service was down for five minutes" and "this file
 * cannot be read" are different facts and must not share a status.
 *
 * TWO WAYS IT DRAINS, EACH WITH ITS OWN SWITCH
 * --------------------------------------------
 *   cron           the real answer for a real install: cron/attachment_extract.php
 *   opportunistic  a few items whenever somebody is already using FreeITSM
 *
 * Both exist because a cron-only design does nothing at all on an installation
 * that has not set one up — which includes every evaluation, and any host that
 * does not offer cron. Opportunistic draining means it works out of the box;
 * the cron means it keeps up under load. Either can be switched off in
 * Tickets → Settings → Indexing if it misbehaves.
 */

require_once __DIR__ . '/indexer.php';
require_once __DIR__ . '/tika.php';

/** Settings keys, both in `system_settings`. Default ON. */
const EXTRACT_SETTING_CRON          = 'attachment_extract_cron';
const EXTRACT_SETTING_OPPORTUNISTIC = 'attachment_extract_opportunistic';

/** How many pending items one opportunistic pass will take. Deliberately tiny. */
const EXTRACT_OPPORTUNISTIC_BATCH = 3;

/** Read a boolean setting that defaults to ON when absent. */
function extractQueueSettingOn(PDO $conn, string $key): bool {
    try {
        $st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null || $v === '') return true;   // unset = on
        return $v === '1' || $v === 1 || strtolower((string)$v) === 'true';
    } catch (Exception $e) {
        return true;
    }
}

/** How many attachments are waiting. */
function extractQueueDepth(PDO $conn): int {
    try {
        $st = $conn->prepare("SELECT COUNT(*) FROM attachment_text WHERE status = ?");
        $st->execute([ATT_TEXT_PENDING]);
        return (int)$st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Read up to $limit pending attachments.
 *
 * ⚠️ Re-indexes the whole TICKET for each one rather than writing the corpus row
 * directly. That keeps a single definition of what a ticket's rows are (see
 * searchIndexTicket) — and since the extracted text is cached in
 * `attachment_text`, re-reading the ticket's other attachments costs nothing.
 *
 * Never throws. Returns what it managed.
 *
 * @return array{done:int,still_pending:int,skipped_reason:string}
 */
function extractQueueDrain(PDO $conn, int $limit): array {
    $out = ['done' => 0, 'still_pending' => 0, 'skipped_reason' => ''];

    try {
        if (!tikaConfigured($conn)) {
            // Nothing can clear these. Not an error: an install may have had an
            // extractor and turned it off.
            $out['skipped_reason'] = 'no extractor configured';
            $out['still_pending']  = extractQueueDepth($conn);
            return $out;
        }

        // ⚠️ Self-heal rows that can never clear. A `pending` row whose format
        // this extractor is not asked about would be picked up forever, tried by
        // nothing, and left pending — a queue that looks busy and moves nothing.
        // Sending them back to `unsupported` is both true and terminal.
        //
        // This is not hypothetical: an earlier version of the "configure Tika"
        // save requeued every unsupported row indiscriminately, and a .ogg voice
        // recording and an .html file went round forever.
        try {
            $stuck = $conn->prepare(
                "SELECT t.attachment_id, a.filename
                   FROM attachment_text t
                   JOIN email_attachments a ON a.id = t.attachment_id
                  WHERE t.status = ?"
            );
            $stuck->execute([ATT_TEXT_PENDING]);
            $bad = [];
            foreach ($stuck->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!tikaHandles((string)$row['filename'])) $bad[] = (int)$row['attachment_id'];
            }
            if ($bad) {
                $in = implode(',', array_fill(0, count($bad), '?'));
                $conn->prepare("UPDATE attachment_text SET status = ? WHERE attachment_id IN ($in)")
                     ->execute(array_merge([ATT_TEXT_UNSUPPORTED], $bad));
            }
        } catch (Exception $e) { /* best effort */ }

        // Oldest first, so a backlog drains in the order it arrived.
        $sel = $conn->prepare(
            "SELECT t.attachment_id, e.ticket_id
               FROM attachment_text t
               JOIN email_attachments a ON a.id = t.attachment_id
               JOIN emails e            ON e.id = a.email_id
              WHERE t.status = ?
           ORDER BY t.extracted_datetime ASC
              LIMIT " . max(1, (int)$limit)
        );
        $sel->execute([ATT_TEXT_PENDING]);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

        // One ticket may own several pending attachments; reindexing it once
        // clears all of them.
        $tickets = array_values(array_unique(array_map(fn($r) => (int)$r['ticket_id'], $rows)));

        foreach ($tickets as $ticketId) {
            searchIndexTicket($conn, $ticketId);
            $out['done']++;

            // ⚠️ Stop the moment the extractor goes away again, rather than
            // grinding through the whole batch collecting timeouts. Each failed
            // attempt costs a connect timeout, so a long batch against a dead
            // service is minutes of nothing.
            if (extractQueueDepthUnchanged($conn, $rows)) break;
        }

        $out['still_pending'] = extractQueueDepth($conn);
    } catch (Throwable $e) {
        error_log('[extractQueueDrain] ' . $e->getMessage());
        $out['skipped_reason'] = $e->getMessage();
    }

    return $out;
}

/**
 * Did the attachments we just tried stay pending? If so the extractor is
 * unreachable again and there is no point continuing this pass.
 */
function extractQueueDepthUnchanged(PDO $conn, array $rows): bool {
    if (!$rows) return false;
    $ids = array_map(fn($r) => (int)$r['attachment_id'], $rows);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    try {
        $st = $conn->prepare("SELECT COUNT(*) FROM attachment_text
                               WHERE attachment_id IN ($in) AND status = ?");
        $st->execute(array_merge($ids, [ATT_TEXT_PENDING]));
        return (int)$st->fetchColumn() === count($ids);
    } catch (Exception $e) {
        return true;   // can't tell — stop rather than loop
    }
}

/**
 * A few items, on the back of a request somebody made anyway.
 *
 * Called from places an analyst already waits a moment: it must stay small and
 * must never be allowed to make that request feel slow. Silent by design.
 */
function extractQueueDrainOpportunistic(PDO $conn): void {
    try {
        if (!extractQueueSettingOn($conn, EXTRACT_SETTING_OPPORTUNISTIC)) return;
        if (!tikaConfigured($conn)) return;
        if (extractQueueDepth($conn) === 0) return;
        extractQueueDrain($conn, EXTRACT_OPPORTUNISTIC_BATCH);
    } catch (Throwable $e) {
        error_log('[extractQueueDrainOpportunistic] ' . $e->getMessage());
    }
}
