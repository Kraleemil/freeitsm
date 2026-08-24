<?php
/**
 * Calendar sync — read changes back out of analysts' calendars (GH #75).
 *
 * The outbound half needs no cron: FreeITSM pushes as things change. This is
 * the inbound half — you move or delete one of these appointments in Outlook
 * and FreeITSM follows. Latency is roughly your cron interval; every 5 minutes
 * is sensible.
 *
 * 🔑 A DELTA QUERY, NOT A SUBSCRIPTION. Change notifications need a publicly
 * reachable HTTPS endpoint for Microsoft to call, plus renewal before it
 * expires. Plenty of FreeITSM installs are internal only and could never have
 * that. This is an ordinary outbound GET, so it works behind any firewall.
 *
 * ⚠️ NOT RUNNING THIS COSTS YOU NOTHING YOU ALREADY HAD. Without it the sync is
 * one-way, exactly as it shipped. Deleting an event in Outlook then simply means
 * the next change to that ticket puts a fresh one back.
 *
 * SECURITY (HTTP invocation only), mirroring the other crons: a shared-secret
 * token via ?token=<value> matching `webhook_cron_token` in system_settings.
 * CLI invocation skips it — there is no untrusted caller.
 */

set_time_limit(300);
if (PHP_SAPI !== 'cli') header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/encryption.php';
require_once __DIR__ . '/../includes/services/tickets.php';
require_once __DIR__ . '/../includes/calendar_sync/pull.php';

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

    if (!calendarSyncSchemaReady($conn)) {
        echo "Calendar sync tables are not present. Run Database Verification.\n";
        exit;
    }

    $reports = calendarSyncPullAll($conn);
    if (!$reports) {
        echo "Nobody has calendar sync switched on. Nothing to do.\n";
        exit;
    }

    $accept = calendarAcceptDeletes($conn) ? 'on' : 'off';
    echo "Polled " . count($reports) . " calendar(s). Deletions: $accept.\n";
    foreach ($reports as $r) {
        // A baseline is reported explicitly rather than looking like a quiet run,
        // because "I have just learned where I am" and "nothing changed" are very
        // different states and only one of them is worth worrying about twice.
        $line = "  analyst {$r['analyst_id']}: ";
        $line .= $r['baseline']
            ? 'baseline taken (nothing applied)'
            : "{$r['moved']} moved, {$r['unscheduled']} unscheduled, {$r['skipped']} ignored";
        if ($r['error']) $line .= ' — ' . $r['error'];
        echo $line . "\n";
    }
} catch (Exception $e) {
    echo "Calendar pull failed: " . $e->getMessage() . "\n";
    exit(1);
}
