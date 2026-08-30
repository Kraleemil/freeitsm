<?php
/**
 * API: an at-a-glance preview of a linked record (discussion #91).
 * GET ?type=ticket&id=42
 *
 * ⚠️ NOT gated on one module. Seven kinds of record are reachable from here and
 * each needs a different one, so the gate lives in recordPreview() — which
 * checks the module AND the record. Naming a single module here would either
 * refuse somebody legitimately previewing a task from a ticket, or wave through
 * a type that module has nothing to do with.
 *
 * 🔴 An unreachable record and a non-existent one return the same thing. Telling
 * them apart would let somebody confirm a record exists by watching which error
 * they got, which is the fact the check exists to withhold.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/i18n.php';
require_once '../../includes/record_preview.php';
I18n::initFromSession();

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $type = (string)($_GET['type'] ?? '');
    $id   = (int)($_GET['id'] ?? 0);

    $conn    = connectToDatabase();
    $preview = recordPreview($conn, (int)$_SESSION['analyst_id'], $type, $id);

    if ($preview === null) {
        // Deliberately not a 403 and not a 404 — one answer, phrased as a fact
        // about what can be shown rather than about what exists.
        echo json_encode(['success' => false, 'error' => t('common.preview.unavailable')]);
        exit;
    }

    echo json_encode(['success' => true, 'preview' => $preview]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
