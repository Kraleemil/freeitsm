<?php
/**
 * API Endpoint: an analyst's own scheduled work as an iCalendar feed (GH #75).
 *
 * The universal half of calendar integration. A native push (Graph, later
 * Google) gives a real event in a real mailbox — busy time, instant updates,
 * nothing exposed. This gives everyone else something: a subscribe URL that
 * works with Google Calendar, Apple Calendar, Outlook, Thunderbird or anything
 * else that speaks iCalendar, with no provider code, no credentials and no
 * internet ingress. "We have not implemented your calendar" must never mean
 * "you get nothing".
 *
 * ── SECURITY: this is a CAPABILITY URL ───────────────────────────────────────
 *
 * Authenticated by a 192-bit random token in the query string, because a
 * calendar client cannot carry a login cookie. Possession of the URL IS the
 * authentication — the same model Google ("Secret address in iCal format"),
 * Outlook.com and iCloud all use, with the same caveat.
 *
 * 🔴 THE CONTENT IS MORE SENSITIVE THAN THE SHARED CALENDAR'S. That one carries
 * maintenance windows; this carries TICKET SUBJECTS, which routinely name
 * customers and problems. Hence:
 *   - its own token, separate from calendar_feed_token, so revoking this cannot
 *     be dodged by someone still holding the other, and rotating it does not
 *     make everyone re-add the team calendar to their phone;
 *   - a per-analyst detail level, so a desk that does not want subjects leaving
 *     the login can publish times and ticket numbers alone;
 *   - an install-wide off switch, because that is a policy the organisation
 *     makes, not each analyst.
 */
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/ics.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/services/tickets.php';
require_once '../../includes/calendar_sync/calendar_sync.php';

function feed_deny($code, $msg) {
    header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

$token = $_GET['token'] ?? '';
// Shape-check before touching the database — a malformed token is not a lookup.
if (!preg_match('/^[a-f0-9]{32,64}$/', $token)) {
    feed_deny('403 Forbidden', 'Invalid or missing feed token.');
}

try {
    $conn = connectToDatabase();

    $stmt = $conn->prepare(
        "SELECT analyst_id FROM user_preferences
         WHERE preference_key = 'tickets_schedule_feed_token' AND preference_value = ? LIMIT 1"
    );
    $stmt->execute([$token]);
    $analystId = (int)$stmt->fetchColumn();
    if (!$analystId) {
        feed_deny('403 Forbidden', 'Invalid or missing feed token.');
    }

    // The install-wide policy switch. Turning subscribe links off must revoke
    // the ones already handed out, not merely stop new ones being minted —
    // otherwise the setting does nothing for exactly the people it is for.
    if (!scheduleFeedAllowed($conn)) {
        feed_deny('403 Forbidden', 'Calendar subscription links are switched off on this system.');
    }

    // Their own scheduled work, bounded: recent past plus everything ahead. An
    // unbounded feed grows for ever and every client re-downloads all of it.
    $stmt = $conn->prepare(
        "SELECT t.id, t.ticket_number, t.subject,
                t.work_start_datetime, t.work_end_datetime, t.work_all_day,
                t.updated_datetime,
                ts.name AS status_name, tp.name AS priority_name
           FROM tickets t
           LEFT JOIN ticket_statuses   ts ON ts.id = t.status_id
           LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
          WHERE t.owner_id = ?
            AND t.work_start_datetime IS NOT NULL
            AND t.work_start_datetime >= (NOW() - INTERVAL 3 MONTH)
            AND t.deleted_datetime IS NULL
            AND COALESCE(ts.is_closed, 0) = 0
          ORDER BY t.work_start_datetime"
    );
    $stmt->execute([$analystId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $detail = scheduleFeedDetail($conn, $analystId);
} catch (Exception $e) {
    feed_deny('500 Internal Server Error', 'Calendar feed error.');
}

$tz     = date_default_timezone_get() ?: 'UTC';
$host   = $_SERVER['HTTP_HOST'] ?? 'freeitsm';
$domain = preg_replace('/[^a-zA-Z0-9.\-]/', '', $host) ?: 'freeitsm';
$base   = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$https  = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

$lines = icsHeader('FreeITSM — my scheduled work', $tz);

foreach ($rows as $r) {
    // 'ref' publishes the ticket number and nothing else. The times still tell
    // you your day is full, which is most of the value, without the subject
    // leaving the login.
    $summary = $detail === 'ref'
        ? $r['ticket_number']
        : $r['ticket_number'] . ' — ' . $r['subject'];

    $description = '';
    if ($detail !== 'ref') {
        $bits = [];
        if (!empty($r['status_name']))   $bits[] = 'Status: '   . $r['status_name'];
        if (!empty($r['priority_name'])) $bits[] = 'Priority: ' . $r['priority_name'];
        $description = implode("\n", $bits);
    }

    // A ticket with no stored end gets the same default the calendar screen
    // resolves, so the two cannot disagree about an unspecified duration.
    $end = $r['work_end_datetime'];
    if (!$end || strtotime($end) <= strtotime($r['work_start_datetime'])) {
        $end = date('Y-m-d H:i:s',
            strtotime($r['work_start_datetime']) + TicketsService::SCHEDULE_DEFAULT_MINUTES * 60);
    }

    $lines = array_merge($lines, icsEvent([
        // Stable across refreshes, so a client updates the event in place rather
        // than deleting and re-adding it (which is what makes reminders re-fire).
        'uid'         => 'freeitsm-ticket-' . (int)$r['id'] . '@' . $domain,
        'summary'     => $summary,
        'description' => $description,
        'url'         => $base ? (($https ? 'https' : 'http') . '://' . $host . $base . '/tickets/?ticket_id=' . (int)$r['id']) : '',
        'start'       => $r['work_start_datetime'],
        'end'         => $end,
        'all_day'     => (int)$r['work_all_day'] === 1,
        'stamp'       => $r['updated_datetime'],
    ], $tz));
}

icsRespond($lines, 'freeitsm-my-work.ics');
