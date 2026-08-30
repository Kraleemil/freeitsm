<?php
/**
 * Recurring tasks on a fixed schedule — cron entry point (discussion #94).
 *
 * Only 'schedule' series need this. A series set to repeat ON COMPLETION makes
 * its next occurrence the moment somebody finishes the last one, which happens
 * inside the web request that completed it — nothing has to run in the
 * background for that to work, and nothing here touches those series.
 *
 * So an installation that never schedules this cron still gets recurring tasks;
 * it just does not get the fixed-date kind. That is a deliberate split, because
 * ⚠️ A CRON THAT WAS NEVER SET UP DOES NOT ANNOUNCE ITSELF, and the commonest
 * outcome of a feature that silently depends on one is a user reporting that it
 * "doesn't work". System → Debug Tools reports when this last ran.
 *
 * Missed days are CAUGHT UP, not skipped: a quarterly access review that did not
 * appear because the server was down is still a review somebody owes. The
 * catch-up is capped per series per run so a rule left dormant for two years
 * cannot produce hundreds of tasks in one pass, and creating an occurrence is
 * idempotent on (series, due date) so overlapping or repeated runs are safe.
 *
 * SECURITY (HTTP invocation only), mirroring the other crons:
 *   - Shared-secret token via ?token=<value> matching `workflow_cron_token`.
 *   - Minimum interval between runs, defeating double-scheduling.
 * CLI invocation skips the token — there is no untrusted caller.
 *
 * Run it once a day; more often is harmless.
 */

set_time_limit(120);
if (PHP_SAPI !== 'cli') header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/encryption.php';            // decryptValue() for the token
require_once __DIR__ . '/../includes/services/task_recurrence.php';

$isCli = (PHP_SAPI === 'cli');

try {
    $conn = connectToDatabase();

    $settings = [];
    foreach ($conn->query(
        "SELECT setting_key, setting_value FROM system_settings
          WHERE setting_key IN ('workflow_cron_token','task_recurrence_min_interval_seconds','task_recurrence_last_run')"
    )->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    // Shares workflow_cron_token rather than inventing a second secret: an
    // administrator setting up FreeITSM's crons should copy one token, not
    // collect one per worker.
    $minInterval = max(0, (int)($settings['task_recurrence_min_interval_seconds'] ?? 300));

    if (!$isCli) {
        $expected = decryptValue($settings['workflow_cron_token'] ?? null);
        if (empty($expected)) {
            http_response_code(503);
            echo "Cron token not set. Run Database Verification to seed workflow_cron_token.\n";
            exit;
        }
        if (!hash_equals((string)$expected, (string)($_GET['token'] ?? ''))) {
            http_response_code(403);
            echo "Forbidden\n";
            exit;
        }
    }

    if ($minInterval > 0 && !empty($settings['task_recurrence_last_run'])) {
        $ageStmt = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, UTC_TIMESTAMP())");
        $ageStmt->execute([$settings['task_recurrence_last_run']]);
        $age = (int)$ageStmt->fetchColumn();
        if ($age >= 0 && $age < $minInterval) {
            http_response_code(429);
            echo "Rate limited. Last run {$age}s ago; minimum interval is {$minInterval}s.\n";
            exit;
        }
    }

    $conn->prepare(
        "INSERT INTO system_settings (setting_key, setting_value) VALUES ('task_recurrence_last_run', UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE setting_value = UTC_TIMESTAMP()"
    )->execute();

    $made = TaskRecurrence::runDue($conn);

    echo "Task recurrence: created " . count($made) . " occurrence(s)";
    echo $made ? " (task ids: " . implode(', ', $made) . ")\n" : ".\n";

} catch (Throwable $e) {
    if (!$isCli) http_response_code(500);
    echo "Task recurrence failed: " . $e->getMessage() . "\n";
    error_log('cron/task_recurrence.php: ' . $e->getMessage());
    exit(1);
}
