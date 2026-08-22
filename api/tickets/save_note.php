<?php
/**
 * API Endpoint: Save a new note.
 * Thin UI adapter over TicketsService::createNote.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/services/tickets.php';

header('Content-Type: application/json');
if (!isset($_SESSION['analyst_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit; }
requireModuleAccessJson('tickets');

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $ticketId = $data['ticket_id'] ?? null;
    if (!$ticketId) {
        throw new Exception('Ticket ID is required');
    }
    $conn = connectToDatabase();
    $noteId = TicketsService::createNote($conn, ActorContext::fromSession($conn), (int)$ticketId, [
        'text'        => $data['note_text'] ?? '',
        'is_internal' => $data['is_internal'] ?? true,
    ]);
    // The id is returned because attachments need it (discussion #69): a note has
    // no id until it is saved, so the modal holds the chosen files in the browser
    // and uploads them against this id once the note is real. It was always
    // produced — createNote() returns it — and simply thrown away here.
    echo json_encode(['success' => true, 'note_id' => $noteId]);
} catch (ServiceError $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
