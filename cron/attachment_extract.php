<?php
/**
 * Attachment text extraction worker — cron entry point (discussion #53, tier 2).
 *
 * Reads attachments sitting at `pending` in `attachment_text` and asks the
 * configured extraction service (System → Integrations → Apache Tika) for their
 * text. Tier 1 formats never reach here; they are read inline in milliseconds.
 * This is for the slow ones — PDFs, and anything needing OCR, which can take
 * minutes per document and must not run inside a web request.
 *
 * Every 5 minutes is a sensible default. Latency is roughly your cron interval.
 *
 * ⚠️ It is NOT the only way the queue drains. FreeITSM also takes a few items
 * opportunistically when somebody is already using it, so an installation with
 * no cron still works — see includes/search/extract_queue.php. Either mechanism
 * can be switched off in Tickets → Settings → Indexing.
 *
 * SECURITY (HTTP invocation only), mirroring cron/webhook_deliveries.php:
 *   - Shared-secret token via ?token=<value> matching `webhook_cron_token` in
 *     system_settings (hash_equals). The same token the other crons use, so an
 *     administrator has one secret to manage rather than four.
 * CLI invocation skips the token — there is no untrusted caller.
 */

set_time_limit(600);   // OCR is slow; a batch of scanned documents needs room
if (PHP_SAPI !== 'cli') header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/encryption.php';
require_once __DIR__ . '/../includes/search/extract_queue.php';

/** Attachments per run. Bounded so one run cannot become open-ended. */
const EXTRACT_CRON_BATCH = 25;

$isCli = (PHP_SAPI === 'cli');

try {
    $conn = connectToDatabase();

    if (!$isCli) {
        $st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'webhook_cron_token'");
        $st->execute();
        $expected = decryptValue($st->fetchColumn() ?: null);
        if (empty($expected)) {
            http_response_code(503);
            echo "Cron token not set. Run Database Verification to seed webhook_cron_token.\n";
            exit;
        }
        if (!hash_equals((string)$expected, (string)($_GET['token'] ?? ''))) {
            http_response_code(403);
            echo "Forbidden\n";
            exit;
        }
    }

    // The administrator's switch. Off means off — including for a cron someone
    // scheduled and forgot about.
    if (!extractQueueSettingOn($conn, EXTRACT_SETTING_CRON)) {
        echo "Scheduled extraction is switched off in Tickets > Settings > Indexing.\n";
        exit;
    }

    if (!tikaConfigured($conn)) {
        echo "No extraction service configured (System > Integrations > Apache Tika). Nothing to do.\n";
        exit;
    }

    // ⚠️ Overlap guard, same idea as cron/webhook_deliveries.php. A five-minute
    // schedule with a run that takes ten gives you two workers on one queue.
    // The claim in extractQueueDrain() makes that SAFE, but it is still two
    // processes doing half the work each and competing for the extractor, so
    // the second one is better off not starting.
    $MIN_INTERVAL = 60;   // seconds
    $lastRun = null;
    try {
        $st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'attachment_extract_last_run'");
        $st->execute();
        $lastRun = $st->fetchColumn() ?: null;
    } catch (Exception $e) { /* first run */ }

    if ($lastRun) {
        $age = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, UTC_TIMESTAMP())");
        $age->execute([$lastRun]);
        $secs = (int)$age->fetchColumn();
        if ($secs >= 0 && $secs < $MIN_INTERVAL) {
            echo "Skipped: last run was {$secs}s ago (minimum {$MIN_INTERVAL}s).\n";
            exit;
        }
    }
    $conn->prepare(
        "INSERT INTO system_settings (setting_key, setting_value)
         VALUES ('attachment_extract_last_run', UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE setting_value = UTC_TIMESTAMP()"
    )->execute();

    $waiting = extractQueueDepth($conn);
    if ($waiting === 0) {
        echo "Nothing pending.\n";
        exit;
    }

    $started = microtime(true);
    $res     = extractQueueDrain($conn, EXTRACT_CRON_BATCH);
    $secs    = round(microtime(true) - $started, 1);

    printf("waiting %d, processed %d tickets in %ss, %d still pending%s\n",
        $waiting, $res['done'], $secs, $res['still_pending'],
        $res['skipped_reason'] !== '' ? ' (' . $res['skipped_reason'] . ')' : '');

    // A queue that did not move is worth saying out loud: it means the service
    // is unreachable, and the run looks identical to a successful one otherwise.
    if ($res['still_pending'] >= $waiting && $res['done'] > 0) {
        echo "WARNING: nothing cleared. The extraction service is probably unreachable.\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
