<?php
/**
 * API: what the search index currently holds (discussion #53).
 *
 * Powers System → Search. Everything here was already visible in
 * D007 — Search corpus health, but D007 is a diagnostic tool: it lives under
 * Debug Tools, prints a wall of environment detail, and is not somewhere an
 * administrator would think to look to answer "is search working?".
 *
 * Read-only. The rebuild is a separate endpoint so that this one can be polled.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/search/corpus.php';
require_once '../../includes/search/search.php';
require_once '../../includes/admin_api_guard.php';   // administrators only

header('Content-Type: application/json');

try {
    $conn = connectToDatabase();

    // An install that has never run Database Verification has no corpus. That is
    // a normal state with a clear remedy, not an error — say which it is.
    if (!searchCorpusReady($conn)) {
        echo json_encode([
            'success' => true,
            'ready'   => false,
            'reason'  => 'no_table',
        ]);
        exit;
    }

    $rows = $conn->query(
        "SELECT source_type, COUNT(*) AS rows_count, SUM(CHAR_LENGTH(body)) AS chars
           FROM search_documents GROUP BY source_type"
    )->fetchAll(PDO::FETCH_ASSOC);

    $bySource = [];
    $total = 0;
    foreach ($rows as $r) {
        $bySource[] = [
            'source_type' => $r['source_type'],
            'rows'        => (int)$r['rows_count'],
            'chars'       => (int)$r['chars'],
        ];
        $total += (int)$r['rows_count'];
    }

    $lastIndexed = $conn->query("SELECT MAX(indexed_datetime) FROM search_documents")->fetchColumn();
    $ticketsCovered = (int)$conn->query(
        "SELECT COUNT(DISTINCT ticket_id) FROM search_documents WHERE ticket_id IS NOT NULL"
    )->fetchColumn();

    // What SHOULD be there, so the screen can say "42 tickets have never been
    // indexed" rather than leaving an administrator to compare two numbers.
    $ticketsTotal  = (int)$conn->query("SELECT COUNT(*) FROM tickets WHERE deleted_datetime IS NULL")->fetchColumn();
    $articlesTotal = 0;
    try {
        $articlesTotal = (int)$conn->query(
            "SELECT COUNT(*) FROM knowledge_articles WHERE is_archived = 0 OR is_archived IS NULL"
        )->fetchColumn();
    } catch (Exception $e) { /* module absent — stays 0 */ }

    $articlesIndexed = 0;
    foreach ($bySource as $b) {
        if ($b['source_type'] === SEARCH_SOURCE_KB_ARTICLE) $articlesIndexed = $b['rows'];
    }

    // Attachment extraction, by outcome. §3.4 of the design: the status is shown
    // rather than kept internal, because a search that silently finds nothing —
    // when the file was never readable in the first place — is worse than one
    // that admits it could not read the file.
    $attachments = [];
    try {
        $attachments = $conn->query(
            "SELECT status, COUNT(*) AS n FROM attachment_text GROUP BY status"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $attachments = array_map('intval', $attachments);
    } catch (Exception $e) { /* table absent on a part-migrated install */ }

    echo json_encode([
        'success'          => true,
        'ready'            => true,
        'attachments'      => $attachments,
        'total_rows'       => $total,
        'by_source'        => $bySource,
        'last_indexed'     => $lastIndexed ?: null,
        'tickets_indexed'  => $ticketsCovered,
        'tickets_total'    => $ticketsTotal,
        'articles_indexed' => $articlesIndexed,
        'articles_total'   => $articlesTotal,
        // The server's own minimum word length. It is the single most common
        // cause of "search finds nothing" and is invisible from the application.
        'min_word_length'  => searchMinTokenSize($conn),
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Could not read the search index']);
}
