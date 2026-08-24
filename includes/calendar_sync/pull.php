<?php
/**
 * Reading changes back OUT of an analyst's calendar (GH #75, bi-directional).
 *
 * The case this exists for, in Ed's words: you are on the train, you look at
 * your phone, and the work in your calendar turns out not to be needed. You
 * delete it there, and by the time you sit down at your desk FreeITSM is
 * already clean. Moving something you have been asked to do later works the
 * same way.
 *
 * ── THE THREE GUARDS, AND WHY EACH ONE EXISTS ───────────────────────────────
 *
 * 1. 🔴 NOTHING IS APPLIED ON A BASELINE. A provider that has lost its place —
 *    an expired delta token, a moved mailbox, a revoked permission — answers
 *    with everything or with nothing. Code that read "absent" as "deleted"
 *    would unschedule an entire service desk because a token expired. Absence
 *    of history is not evidence of deletion.
 *
 * 2. 🔴 A CAP ON DELETIONS PER RUN. More than a handful at once is a symptom,
 *    not an instruction. We stop, change nothing, and record it — because the
 *    difference between "somebody cleared their week" and "something has gone
 *    wrong" cannot be told apart from here, and only one of those is safe to
 *    act on.
 *
 * 3. 🔴 EVERY CHANGE IS AUDITED. An unschedule that arrived from a phone on a
 *    train is otherwise a ticket that changed with no record of why. The audit
 *    row names the calendar it came from.
 *
 * ⚠️ ECHO SUPPRESSION IS BY COMPARISON, NOT BY MARKER. Our own push updates the
 * event, which comes back in the next delta as a change. Rather than tagging
 * our writes and hoping the tag survives, we simply compare what the calendar
 * says to what the ticket already says: equal means nothing to do. That is
 * immune to a lost marker, and it also means a change we somehow missed gets
 * picked up rather than suppressed.
 */

require_once __DIR__ . '/push.php';

/** system_settings key: does deleting the event unschedule the ticket? */
const CALENDAR_ACCEPT_DELETES = 'tickets_calendar_accept_deletes';

/**
 * Deletions honoured in one run, per analyst, before we assume something is
 * wrong rather than deliberate.
 */
const CALENDAR_DELETE_CAP = 5;

/** Off by default: whether a personal tidy-up may reach shared data is an
 *  organisation's call, and the safe answer is the one that changes nothing. */
function calendarAcceptDeletes(PDO $conn): bool
{
    try {
        $st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $st->execute([CALENDAR_ACCEPT_DELETES]);
        return (string)$st->fetchColumn() === '1';
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Poll one analyst's calendar and apply what came back.
 *
 * @return array a small report, for the cron's output and for tests
 */
function calendarSyncPullForAnalyst(PDO $conn, int $analystId): array
{
    $report = ['analyst_id' => $analystId, 'baseline' => false,
               'moved' => 0, 'unscheduled' => 0, 'skipped' => 0, 'error' => null];

    $enrolment = calendarSyncEnrolment($conn, $analystId);
    if (($enrolment['mode'] ?? '') !== CALENDAR_MODE_PUSH) return $report;

    $connection = calendarSyncActiveConnection($conn);
    if (!$connection) return $report;

    try {
        $provider = calendarSyncProviderFor($connection);
        $provider->conn = $conn;
        $result = $provider->pollChanges($enrolment['calendar_address'], $enrolment['delta_token'] ?: null);
    } catch (Exception $e) {
        calendarSyncRecordError($conn, $analystId, $e->getMessage());
        $report['error'] = $e->getMessage();
        return $report;
    }

    // Always store the new token, even on a baseline — that IS the baseline.
    $conn->prepare(
        "UPDATE calendar_enrolments SET delta_token = ?, delta_synced_datetime = NOW() WHERE analyst_id = ?"
    )->execute([$result['token'], $analystId]);

    if (!empty($result['baseline'])) {
        // GUARD 1. We have just learned where we are; we have learned nothing
        // about what changed. Apply nothing.
        $report['baseline'] = true;
        return $report;
    }

    // Which of our events these ids belong to. Anything we did not create is
    // somebody's own appointment and none of our business.
    $ids = array_merge(
        array_column($result['changed'], 'remote_event_id'),
        $result['removed']
    );
    if (!$ids) return $report;

    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $conn->prepare(
        "SELECT s.*, t.work_start_datetime, t.work_end_datetime, t.work_all_day, t.ticket_number
           FROM calendar_sync_events s
           JOIN tickets t ON t.id = s.ticket_id
          WHERE s.analyst_id = ? AND s.remote_event_id IN ($in)"
    );
    $st->execute(array_merge([$analystId], $ids));
    $mine = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $mine[$row['remote_event_id']] = $row;

    // ── Moves ───────────────────────────────────────────────────────────────
    foreach ($result['changed'] as $change) {
        $row = $mine[$change['remote_event_id']] ?? null;
        if (!$row) { $report['skipped']++; continue; }

        $sameStart = substr((string)$row['work_start_datetime'], 0, 16) === substr($change['start'], 0, 16);
        $sameEnd   = substr((string)$row['work_end_datetime'], 0, 16)   === substr($change['end'], 0, 16);
        $sameAllDay = ((int)$row['work_all_day'] === 1) === (bool)$change['all_day'];
        if ($sameStart && $sameEnd && $sameAllDay) {
            // ECHO — this is our own push coming back. Nothing to do.
            $report['skipped']++;
            continue;
        }

        calendarPullApply($conn, $row, $change, $enrolment['calendar_address']);
        $report['moved']++;
    }

    // ── Deletions ───────────────────────────────────────────────────────────
    $removedMine = array_values(array_filter($result['removed'], fn($id) => isset($mine[$id])));
    if (!$removedMine) return $report;

    if (!calendarAcceptDeletes($conn)) {
        // Switched off. The event is gone from their calendar but the ticket is
        // still scheduled, so the next change to it will put a fresh event back
        // — which is the documented behaviour, not a bug.
        $report['skipped'] += count($removedMine);
        return $report;
    }

    // GUARD 2. A handful is somebody clearing their afternoon. Thirty is a
    // symptom. Refuse the lot rather than guessing which.
    if (count($removedMine) > CALENDAR_DELETE_CAP) {
        $msg = 'Ignored ' . count($removedMine) . ' calendar deletions in one run — more than the safety limit of '
             . CALENDAR_DELETE_CAP . '. Nothing was unscheduled. If this was deliberate, unschedule them in FreeITSM.';
        calendarSyncRecordError($conn, $analystId, $msg);
        $report['error'] = $msg;
        $report['skipped'] += count($removedMine);
        return $report;
    }

    foreach ($removedMine as $id) {
        $row = $mine[$id];
        // Drop our map row FIRST so the reconcile that follows the update does
        // not try to delete an event that is already gone, and does not put a
        // replacement back.
        $conn->prepare("DELETE FROM calendar_sync_events WHERE id = ?")->execute([(int)$row['id']]);
        calendarPullUnschedule($conn, (int)$row['ticket_id'], $analystId, $enrolment['calendar_address']);
        $report['unscheduled']++;
    }
    return $report;
}

/** Apply a moved event to its ticket. */
function calendarPullApply(PDO $conn, array $row, array $change, string $address): void
{
    $conn->prepare(
        "UPDATE tickets SET work_start_datetime = ?, work_end_datetime = ?, work_all_day = ? WHERE id = ?"
    )->execute([$change['start'], $change['end'], $change['all_day'] ? 1 : 0, (int)$row['ticket_id']]);

    // GUARD 3. Otherwise this is a ticket that moved with no record of why.
    calendarPullAudit($conn, (int)$row['ticket_id'], (int)$row['analyst_id'], 'Scheduled',
        (string)$row['work_start_datetime'], $change['start'] . ' (moved in ' . $address . ')');

    $conn->prepare("UPDATE calendar_sync_events SET updated_datetime = NOW() WHERE id = ?")
         ->execute([(int)$row['id']]);
}

/** Clear a ticket's schedule because its event was deleted. */
function calendarPullUnschedule(PDO $conn, int $ticketId, int $analystId, string $address): void
{
    $before = $conn->query("SELECT work_start_datetime FROM tickets WHERE id = " . (int)$ticketId)->fetchColumn();
    $conn->prepare(
        "UPDATE tickets SET work_start_datetime = NULL, work_end_datetime = NULL, work_all_day = 0 WHERE id = ?"
    )->execute([$ticketId]);
    calendarPullAudit($conn, $ticketId, $analystId, 'Scheduled', (string)$before,
        'cleared (removed from ' . $address . ')');
}

/**
 * A ticket_audit row, best effort.
 *
 * Written directly rather than through TicketsService, because the service's
 * update would trigger a reconcile that pushes straight back to the calendar we
 * are reading from — the loop this whole file is careful to avoid.
 */
function calendarPullAudit(PDO $conn, int $ticketId, int $analystId, string $field, string $old, string $new): void
{
    try {
        $conn->prepare(
            "INSERT INTO ticket_audit (ticket_id, analyst_id, field_name, old_value, new_value, created_datetime)
             VALUES (?, ?, ?, ?, ?, NOW())"
        )->execute([$ticketId, $analystId, $field, substr($old, 0, 500), substr($new, 0, 500)]);
    } catch (Exception $e) {
        // An audit we cannot write must not stop the change the analyst asked for.
    }
}

/** Every enrolled analyst. Returns one report per person. */
function calendarSyncPullAll(PDO $conn): array
{
    if (!calendarSyncSchemaReady($conn)) return [];
    $ids = $conn->query("SELECT analyst_id FROM calendar_enrolments WHERE mode = 'push'")
                ->fetchAll(PDO::FETCH_COLUMN);
    $out = [];
    foreach ($ids as $id) $out[] = calendarSyncPullForAnalyst($conn, (int)$id);
    return $out;
}
