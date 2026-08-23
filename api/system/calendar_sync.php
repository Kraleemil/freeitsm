<?php
/**
 * API: the install's calendar-sync connection and feed policy (GH #75).
 *
 *   GET                 -> current connection (WITHOUT secrets) + feed policy +
 *                          the Microsoft mailboxes whose credentials can be borrowed
 *   POST action=save    -> create/update the connection and the feed policy
 *   POST action=test    -> mint a token and prove the permission was granted
 *   POST action=delete  -> remove the connection
 *
 * ⚠️ SECRETS ARE NEVER RETURNED. The GET reports has_credentials as a boolean and
 * that is all, exactly as integrations.php does — a read that hands secrets back
 * to a browser needs the same care as one that writes them, and there is no
 * reason for the screen to know them.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/admin_api_guard.php';   // System administrators only
require_once '../../includes/functions.php';
require_once '../../includes/encryption.php';
require_once '../../includes/calendar_sync/calendar_sync.php';

header('Content-Type: application/json');

$action = ($_SERVER['REQUEST_METHOD'] === 'POST') ? ($_POST['action'] ?? '') : '';

try {
    $conn = connectToDatabase();

    if (!calendarSyncSchemaReady($conn)) {
        echo json_encode([
            'success' => false,
            'needs_db_verify' => true,
            'error' => 'Calendar sync needs a database update — run System → Database Verification.',
        ]);
        exit;
    }

    // ── Read ────────────────────────────────────────────────────────────────
    if ($action === '') {
        $row = $conn->query("SELECT * FROM calendar_connections ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        // Only Microsoft mailboxes can lend an app registration, and only ones
        // that actually have Azure credentials on them — offering an IMAP mailbox
        // as a source would be offering something that cannot work.
        $mailboxes = [];
        foreach ($conn->query("SELECT id, name, provider, azure_client_id FROM target_mailboxes WHERE provider = 'microsoft' AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC) as $mb) {
            if (!empty($mb['azure_client_id'])) {
                $mailboxes[] = ['id' => (int)$mb['id'], 'name' => $mb['name']];
            }
        }

        echo json_encode([
            'success'   => true,
            'connection' => $row ? [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'provider'   => $row['provider'],
                'mailbox_id' => $row['mailbox_id'] !== null ? (int)$row['mailbox_id'] : null,
                'is_active'  => (int)$row['is_active'] === 1,
                // A boolean and nothing more. See the header.
                'has_credentials'     => !empty($row['credentials']),
                'last_error'          => $row['last_error'],
                'last_error_datetime' => $row['last_error_datetime'],
            ] : null,
            'mailboxes'  => $mailboxes,
            'feed_mode'  => scheduleFeedMode($conn),
            'enrolled'   => (int)$conn->query("SELECT COUNT(*) FROM calendar_enrolments WHERE mode <> 'off'")->fetchColumn(),
        ]);
        exit;
    }

    // ── Write ───────────────────────────────────────────────────────────────
    if ($action === 'save') {
        $name      = trim((string)($_POST['name'] ?? 'Microsoft 365'));
        $source    = ($_POST['source'] ?? 'mailbox') === 'own' ? 'own' : 'mailbox';
        $mailboxId = ($_POST['mailbox_id'] ?? '') !== '' ? (int)$_POST['mailbox_id'] : null;
        $feedMode  = (string)($_POST['feed_mode'] ?? FEED_MODE_FULL);

        if (!in_array($feedMode, [FEED_MODE_OFF, FEED_MODE_REF, FEED_MODE_FULL], true)) {
            $feedMode = FEED_MODE_FULL;
        }
        $conn->prepare(
            "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        )->execute([SCHEDULE_FEED_SETTING, $feedMode]);

        // The feed policy stands alone: an install with no calendar connection at
        // all still publishes subscribe links, and must be able to govern them.
        if (($_POST['policy_only'] ?? '') === '1') {
            echo json_encode(['success' => true, 'feed_mode' => $feedMode]);
            exit;
        }

        $existing = $conn->query("SELECT * FROM calendar_connections ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        $credentials = null;   // null = leave whatever is stored alone
        if ($source === 'own') {
            $tenant = trim((string)($_POST['tenant_id'] ?? ''));
            $client = trim((string)($_POST['client_id'] ?? ''));
            $secret = (string)($_POST['client_secret'] ?? '');
            // A blank or masked secret on an edit means "unchanged", so an admin
            // can rename the connection without retyping a secret they cannot see.
            if (isMaskedNoChangeValue($secret) && $existing && !empty($existing['credentials'])) {
                $old = calendarSyncDecodeCredentials($existing['credentials']);
                $secret = $old['client_secret'] ?? '';
            }
            if ($tenant === '' || $client === '' || $secret === '') {
                echo json_encode(['success' => false, 'error' => 'Tenant ID, client ID and client secret are all required.']);
                exit;
            }
            $credentials = calendarSyncEncodeCredentials([
                'tenant_id' => $tenant, 'client_id' => $client, 'client_secret' => $secret,
            ]);
            $mailboxId = null;               // own credentials win; don't leave a stale borrow
        } else {
            if (!$mailboxId) {
                echo json_encode(['success' => false, 'error' => 'Choose the mailbox to borrow credentials from.']);
                exit;
            }
            $credentials = '';               // '' = explicitly clear, so borrowing takes effect
        }

        if ($existing) {
            $conn->prepare(
                "UPDATE calendar_connections
                    SET name = ?, provider = 'microsoft', mailbox_id = ?, credentials = ?,
                        last_error = NULL, last_error_datetime = NULL,
                        token_data = NULL, updated_datetime = NOW()
                  WHERE id = ?"
            )->execute([$name, $mailboxId, ($credentials === '' ? null : $credentials), (int)$existing['id']]);
            $id = (int)$existing['id'];
        } else {
            $conn->prepare(
                "INSERT INTO calendar_connections (name, provider, mailbox_id, credentials, created_by)
                 VALUES (?, 'microsoft', ?, ?, ?)"
            )->execute([$name, $mailboxId, ($credentials === '' ? null : $credentials), (int)$_SESSION['analyst_id']]);
            $id = (int)$conn->lastInsertId();
        }
        // token_data is cleared above on purpose: a cached token minted with the
        // OLD credentials would keep working for up to an hour and make a broken
        // change look fine until long after the admin walked away.
        echo json_encode(['success' => true, 'id' => $id, 'feed_mode' => $feedMode]);
        exit;
    }

    if ($action === 'test') {
        $row = $conn->query("SELECT id FROM calendar_connections ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Save the connection first.']); exit; }

        $connection = calendarSyncLoadConnection($conn, (int)$row['id']);
        if (!$connection) { echo json_encode(['success' => false, 'error' => 'The connection is not active.']); exit; }

        $creds = $connection['credentials'] ?? [];
        if (empty($creds['tenant_id']) || empty($creds['client_id']) || empty($creds['client_secret'])) {
            echo json_encode(['success' => false, 'error' =>
                'No usable credentials. If you chose to borrow them, check that mailbox still has its Azure details.']);
            exit;
        }

        try {
            $provider = calendarSyncProviderFor($connection);
            $provider->conn = $conn;

            // Two separate questions, reported separately, because they fail for
            // completely different reasons and need different fixes.
            $probe = trim((string)($_POST['probe'] ?? ''));
            $r = new ReflectionMethod($provider, 'token');
            $r->setAccessible(true);
            $r->invoke($provider);                       // throws if credentials/consent are wrong

            $result = ['success' => true, 'token' => true, 'borrowed' => $connection['borrowed_from_mailbox'] ?? null];
            if ($probe !== '') {
                $result['probe']    = $probe;
                $result['probe_ok'] = $provider->verifyTarget($probe);
            }
            $conn->prepare("UPDATE calendar_connections SET last_error = NULL, last_error_datetime = NULL WHERE id = ?")
                 ->execute([(int)$row['id']]);
            echo json_encode($result);
        } catch (Exception $e) {
            $msg = substr($e->getMessage(), 0, 500);
            $conn->prepare("UPDATE calendar_connections SET last_error = ?, last_error_datetime = NOW() WHERE id = ?")
                 ->execute([$msg, (int)$row['id']]);
            echo json_encode(['success' => false, 'token' => false, 'error' => $msg]);
        }
        exit;
    }

    if ($action === 'delete') {
        // Enrolments point at this connection with ON DELETE SET NULL, so nobody's
        // choice is silently destroyed — their mode simply has nothing to push to.
        $conn->exec("DELETE FROM calendar_connections");
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
