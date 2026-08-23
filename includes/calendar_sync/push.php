<?php
/**
 * Putting a scheduled ticket into its owner's calendar, and taking it out again
 * (GH discussion #75, step 3).
 *
 * 🔑 RECONCILE, DON'T REACT. Every write path calls the SAME function, which
 * works out what SHOULD be in whose calendar and makes that true — rather than
 * each caller knowing whether its particular change means create, update, move
 * or delete. Scheduling, rescheduling, unscheduling, reassigning, closing,
 * trashing and opting out are then all one code path, and a combination nobody
 * thought of (reassigned AND rescheduled in one save) cannot fall through a gap
 * between two branches.
 *
 * ⚠️ NEVER FATAL. Scheduling a ticket must not fail because Microsoft was
 * unreachable — the schedule is FreeITSM's data and Outlook is a projection of
 * it. Failures are recorded against the analyst's enrolment and reported on
 * their settings; they never reach the analyst pressing Save.
 *
 * ⚠️ CALL AFTER COMMIT, never inside a transaction: this makes a network call,
 * and holding row locks open across one is how a slow third party becomes a
 * database problem.
 */

require_once __DIR__ . '/calendar_sync.php';

/**
 * Make the calendar match the ticket.
 *
 * @param bool $gone true when the ticket is going away entirely (trashed or
 *                   purged) and its row may no longer be readable.
 */
function calendarSyncReconcileTicket(PDO $conn, int $ticketId, bool $gone = false): void
{
    if (!calendarSyncSchemaReady($conn)) return;

    try {
        $existing = $conn->prepare("SELECT * FROM calendar_sync_events WHERE ticket_id = ?");
        $existing->execute([$ticketId]);
        $rows = $existing->fetchAll(PDO::FETCH_ASSOC);

        $ticket = null;
        if (!$gone) {
            $st = $conn->prepare(
                "SELECT t.id, t.ticket_number, t.subject, t.owner_id,
                        t.work_start_datetime, t.work_end_datetime, t.work_all_day,
                        t.deleted_datetime, ts.name AS status_name, tp.name AS priority_name,
                        COALESCE(ts.is_closed, 0) AS is_closed,
                        u.display_name AS requester_name
                   FROM tickets t
                   LEFT JOIN ticket_statuses   ts ON ts.id = t.status_id
                   LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
                   LEFT JOIN users u ON u.id = t.user_id
                  WHERE t.id = ?"
            );
            $st->execute([$ticketId]);
            $ticket = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // Who, if anyone, should be holding this in their calendar right now.
        //
        // A CLOSED ticket is removed deliberately: the calendar answers "what am
        // I going to do", and the tickets calendar and the .ics feed both hide
        // closed work already. Leaving finished jobs in someone's diary for ever
        // would make it useless within a month.
        $wantAnalyst = null;
        if ($ticket
            && empty($ticket['deleted_datetime'])
            && (int)$ticket['is_closed'] === 0
            && !empty($ticket['work_start_datetime'])
            && !empty($ticket['owner_id'])) {
            $enrolment = calendarSyncEnrolment($conn, (int)$ticket['owner_id']);
            if (($enrolment['mode'] ?? '') === CALENDAR_MODE_PUSH && !empty($enrolment['calendar_address'])) {
                $wantAnalyst = (int)$ticket['owner_id'];
            }
        }

        // ── Remove anything that should no longer be there ──────────────────
        // This is what handles reassignment: the OLD owner's row is stale, and
        // the address is taken from the row rather than looked up, because they
        // may have changed theirs since — the event lives in the old mailbox.
        foreach ($rows as $row) {
            if ($wantAnalyst !== null && (int)$row['analyst_id'] === $wantAnalyst) continue;
            calendarSyncRemoveRow($conn, $row);
        }
        if ($wantAnalyst === null) return;

        // ── Create or update the one that should ────────────────────────────
        $connection = calendarSyncActiveConnection($conn);
        if (!$connection) return;                      // nothing to push through

        $enrolment = calendarSyncEnrolment($conn, $wantAnalyst);
        $address   = $enrolment['calendar_address'];
        $event     = calendarSyncEventFromTicket($ticket);

        $mine = null;
        foreach ($rows as $row) {
            if ((int)$row['analyst_id'] === $wantAnalyst) { $mine = $row; break; }
        }

        try {
            $provider = calendarSyncProviderFor($connection);
            $provider->conn = $conn;

            if ($mine) {
                try {
                    $provider->updateEvent($mine['remote_calendar'], $mine['remote_event_id'], $event);
                    $conn->prepare("UPDATE calendar_sync_events SET updated_datetime = NOW() WHERE id = ?")
                         ->execute([(int)$mine['id']]);
                } catch (CalendarEventMissing $e) {
                    // Somebody deleted it from their own calendar — ordinary, not
                    // an error. Put a fresh one back and re-point the map row,
                    // rather than the analyst silently losing the entry for good.
                    $newId = $provider->createEvent($address, $event);
                    $conn->prepare(
                        "UPDATE calendar_sync_events
                            SET remote_event_id = ?, remote_calendar = ?, updated_datetime = NOW()
                          WHERE id = ?"
                    )->execute([$newId, $address, (int)$mine['id']]);
                }
            } else {
                $newId = $provider->createEvent($address, $event);
                // ⚠️ The UNIQUE key on (ticket_id, analyst_id) is what makes this
                // an upsert. Without it a ticket bouncing A -> B -> A accumulates
                // rows and the delete above stops finding the right one.
                $conn->prepare(
                    "INSERT INTO calendar_sync_events
                        (ticket_id, analyst_id, connection_id, remote_event_id, remote_calendar)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE remote_event_id = VALUES(remote_event_id),
                                             remote_calendar = VALUES(remote_calendar),
                                             updated_datetime = NOW()"
                )->execute([$ticketId, $wantAnalyst, (int)$connection['id'], $newId, $address]);
            }
            calendarSyncClearError($conn, $wantAnalyst);
        } catch (Exception $e) {
            calendarSyncRecordError($conn, $wantAnalyst, $e->getMessage());
        }
    } catch (Exception $e) {
        // Anything unexpected — a missing table, a query that failed — must not
        // take down the save that triggered this.
    }
}

/** Remove one mapped event from the calendar it was written to, and forget it. */
function calendarSyncRemoveRow(PDO $conn, array $row): void
{
    try {
        $connection = $row['connection_id']
            ? calendarSyncLoadConnection($conn, (int)$row['connection_id'])
            : calendarSyncActiveConnection($conn);
        if ($connection) {
            $provider = calendarSyncProviderFor($connection);
            $provider->conn = $conn;
            // An already-gone event counts as success — see the provider.
            $provider->deleteEvent($row['remote_calendar'], $row['remote_event_id']);
        }
    } catch (Exception $e) {
        calendarSyncRecordError($conn, (int)$row['analyst_id'], $e->getMessage());
        // 🔑 FALL THROUGH AND DROP THE ROW ANYWAY. Keeping a map row we can no
        // longer act on means retrying the same failing delete for ever. The
        // worst case is one stale entry in a calendar, which a person can delete;
        // the alternative is a permanent loop nobody sees.
    }
    try {
        $conn->prepare("DELETE FROM calendar_sync_events WHERE id = ?")->execute([(int)$row['id']]);
    } catch (Exception $e) {
        // Nothing sensible left to do.
    }
}

/** Everything FreeITSM put in one analyst's calendar, removed. */
function calendarSyncRemoveAllForAnalyst(PDO $conn, int $analystId): void
{
    if (!calendarSyncSchemaReady($conn)) return;
    try {
        $st = $conn->prepare("SELECT * FROM calendar_sync_events WHERE analyst_id = ?");
        $st->execute([$analystId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            calendarSyncRemoveRow($conn, $row);
        }
    } catch (Exception $e) {
        // Best effort.
    }
}

/** The canonical event shape (see CalendarSyncProvider) for one ticket row. */
function calendarSyncEventFromTicket(array $t): array
{
    $end = $t['work_end_datetime'];
    if (!$end || strtotime($end) <= strtotime($t['work_start_datetime'])) {
        // The same default the calendar screen and the feed resolve, so all three
        // agree about what an unspecified duration means.
        $end = date('Y-m-d H:i:s',
            strtotime($t['work_start_datetime']) + TicketsService::SCHEDULE_DEFAULT_MINUTES * 60);
    }

    $bits = [];
    if (!empty($t['requester_name'])) $bits[] = 'Requester: ' . $t['requester_name'];
    if (!empty($t['status_name']))    $bits[] = 'Status: '    . $t['status_name'];
    if (!empty($t['priority_name']))  $bits[] = 'Priority: '  . $t['priority_name'];

    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    // Only offer a link when we can build an absolute one. A relative URL in a
    // calendar event is worse than none — it looks clickable and goes nowhere.
    $url = ($host && $base !== '')
        ? ((!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http')
            . '://' . $host . $base . '/tickets/?ticket_id=' . (int)$t['id'])
        : '';

    return [
        'subject'  => trim(($t['ticket_number'] ?? '') . ' — ' . ($t['subject'] ?? '')),
        'body'     => implode("\n", $bits),
        'start'    => $t['work_start_datetime'],
        'end'      => $end,
        'all_day'  => (int)($t['work_all_day'] ?? 0) === 1,
        // The zone the naive values are in. See the provider: sending these as
        // UTC puts every event an hour out for half the year.
        'timezone' => date_default_timezone_get(),
        'url'      => $url,
    ];
}

function calendarSyncRecordError(PDO $conn, int $analystId, string $message): void
{
    try {
        $conn->prepare(
            "INSERT INTO calendar_enrolments (analyst_id, mode, last_error)
             VALUES (?, 'off', ?)
             ON DUPLICATE KEY UPDATE last_error = VALUES(last_error), updated_datetime = NOW()"
        )->execute([$analystId, substr($message, 0, 500)]);
    } catch (Exception $e) {
    }
}

function calendarSyncClearError(PDO $conn, int $analystId): void
{
    try {
        $conn->prepare(
            "UPDATE calendar_enrolments SET last_error = NULL, last_sync_datetime = NOW() WHERE analyst_id = ?"
        )->execute([$analystId]);
    } catch (Exception $e) {
    }
}
