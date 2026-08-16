<?php
/**
 * API: run a directory sync, or preview one.
 *
 * POST { provider_id: N, mode: 'preview' | 'live' }
 *
 * A preview runs the identical code path and writes nothing to `users`, so what
 * it reports is what a live run would actually do — not a separate estimate that
 * can drift from the real thing.
 *
 * Administrators only. A sync creates and deactivates people wholesale; that is
 * not a thing an ordinary analyst should be able to set off.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/encryption.php';
require_once '../../includes/directory_sync.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $conn = connectToDatabase();
    if (!analystIsAdmin($conn, (int)$_SESSION['analyst_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Administrator access required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $pid  = (int)($data['provider_id'] ?? 0);
    $mode = ($data['mode'] ?? 'preview') === 'live' ? 'live' : 'preview';

    $s = $conn->prepare("SELECT * FROM auth_providers WHERE id = ? AND protocol = 'ldap'");
    $s->execute([$pid]);
    $provider = $s->fetch(PDO::FETCH_ASSOC);
    if (!$provider) {
        echo json_encode(['success' => false, 'error' => 'No such directory provider']);
        exit;
    }
    if ((int)$provider['sync_enabled'] !== 1) {
        echo json_encode([
            'success' => false,
            'error'   => 'Directory sync is switched off for this provider. Turn it on and save first.',
        ]);
        exit;
    }

    // ⚠️ Only the bind password is encrypted, and decryptValue is what unwraps
    // it. Miss this and the bind fails as "Invalid credentials", which reads
    // like a wrong password rather than an un-decrypted one.
    $provider['ldap_bind_password'] = decryptValue($provider['ldap_bind_password'] ?? '');

    $run = directorySyncRun($conn, $provider, $mode, (int)$_SESSION['analyst_id']);

    echo json_encode(['success' => true, 'run' => $run]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
