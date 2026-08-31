<?php
/**
 * API: Tickets — "read this ticket for me" (discussion #104, idea 12).
 *
 *   POST { ticket_id: int }  →  { briefing: "…" }
 *
 * A different question from the maintained summary. The summary is a standing
 * description of where a ticket stands; this is what you want at the moment you
 * open one cold — what happened, and what should I do next.
 *
 * ⚠️ NOTHING IS STORED. Deliberately. A stored suggestion becomes a fact the
 * next person reads without knowing a machine wrote it, and unlike the summary
 * this one is allowed to suggest. You ask for it, you read it, it is gone. That
 * also means it costs exactly as much as it is used and not a penny more.
 *
 * Uses the Tickets AI provider (ns=tickets_reply_cleanup).
 */

session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/encryption.php';
require_once '../../includes/rbac.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/ai_settings.php';
require_once '../../includes/ticket_ai.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('tickets');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$ticketId = (int)($input['ticket_id'] ?? 0);
if ($ticketId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ticket id required']);
    exit;
}

try {
    $conn      = connectToDatabase();
    $analystId = (int)$_SESSION['analyst_id'];

    if (!analystCanAccessTicket($conn, $analystId, $ticketId)) {
        echo json_encode(['success' => false, 'error' => 'Ticket not found']);
        exit;
    }

    $settings = ticketAiSettings($conn);
    if (!$settings['read_enabled']) {
        echo json_encode(['success' => false, 'error' => 'disabled']);
        exit;
    }

    $cfg = aiSettingsLoad($conn, TICKET_AI_NS);
    if (($cfg['api_key'] ?? '') === '') {
        echo json_encode(['success' => false, 'error' => 'not_configured']);
        exit;
    }

    /* Reads further back than the standing summary does, because this is the one
       you ask for when you do not know the ticket at all — and "how it got here"
       is a question about the beginning. */
    $transcript = ticketAiTranscript($conn, $ticketId, [
        'max_messages'  => 120,
        'include_notes' => (bool)$settings['summary_include_notes'],
    ]);
    if (trim($transcript['text']) === '') {
        echo json_encode(['success' => false, 'error' => 'nothing_to_read']);
        exit;
    }

    $user = '';
    if ($transcript['truncated']) {
        $user .= "NOTE: this ticket is longer than what follows. You are seeing the most "
               . "recent part only. Say so under 'How it got here' rather than describing "
               . "a beginning you cannot see.\n\n";
    }
    $user .= "TICKET CONVERSATION:\n\n" . $transcript['text'];

    $result = aiProviderChat($cfg, [
        'system'     => ticketAiReadSystemPrompt(),
        'user'       => $user,
        /* ⚠️ Generous, because a REASONING MODEL spends this budget thinking before
           it writes anything. 700 was plenty for the ~180 words asked for and not
           nearly enough for a model that reasons first — it returned an empty string
           and a full usage record. A model that does not think out loud stops at its
           own natural end, so the higher ceiling costs those nothing. */
        'max_tokens' => 3500,
    ]);
    $text = trim((string)($result['content'] ?? ''));
    if ($text === '') {
        // See ai_summary.php: a reasoning model that spent the whole budget
        // thinking is a nameable settings problem, not a generic failure.
        $ranOut = ($result['finish_reason'] ?? '') === 'length'
                  || ($result['finish_reason'] ?? '') === 'max_tokens'
                  || (int)($result['reasoning_tokens'] ?? 0) > 0;
        echo json_encode(['success' => false, 'error' => $ranOut ? 'reasoning_overran' : 'empty_response']);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'briefing'  => $text,
        'model'     => (string)($result['model'] ?? ''),
        'messages'  => $transcript['messages'],
        'notes'     => $transcript['notes'],
        'truncated' => (bool)$transcript['truncated'],
    ]);
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => 'provider_unreachable', 'detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('ai_read: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read the ticket']);
}
