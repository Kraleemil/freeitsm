<?php
/**
 * Task recurrence — the date engine and the spawning of occurrences.
 *
 * Discussion #94 (dschipfel): recurring tasks in the style of Microsoft Planner.
 *
 * ── The model ────────────────────────────────────────────────────────────────
 * A recurrence is a SERIES, not a template. The rule lives in one row of
 * task_recurrences; every task the series produces carries recurrence_id (the
 * series) and recurrence_master_id (the FIRST task of the series, so any
 * occurrence can point a reader back to where it started).
 *
 * That mirrors how people describe it - "this task repeats" - rather than
 * introducing a hidden template task that is not real work and would show up in
 * every list, count and report that was written before recurrence existed.
 *
 * ── Two ways to repeat, and they are genuinely different ─────────────────────
 *   'completion'  the next occurrence is created when this one is COMPLETED,
 *                 counted from the completion date. A monthly backup check you
 *                 finish four days late should next be due a month after you did
 *                 it, not a month after it was theoretically due. This is what
 *                 Planner does and what most people mean.
 *   'schedule'    occurrences appear on fixed dates whether or not the previous
 *                 one was finished. A compliance review happens on the 1st
 *                 whether or not last month's was signed off, and the fact that
 *                 last month's is still open is the point.
 *
 * The distinction matters because 'schedule' can produce a backlog and
 * 'completion' cannot. Only 'schedule' needs anything to run in the background.
 *
 * ⚠️ All arithmetic here is on DATES, never datetimes. A due date is a calendar
 * day, not an instant: "the 1st" means the 1st wherever the reader is. Passing a
 * datetime through this would reintroduce GH #116 by another route.
 */

class TaskRecurrence
{
    /** ISO weekday numbers, as PHP's date('N') gives them. */
    const MON = 1, TUE = 2, WED = 3, THU = 4, FRI = 5, SAT = 6, SUN = 7;

    /** A rule with nothing filled in - every field the engine understands. */
    public static function blankRule(): array
    {
        return [
            'mode'          => 'completion',  // completion | schedule
            'freq'          => 'weekly',      // daily | weekly | monthly | yearly
            'interval_n'    => 1,             // every N days/weeks/months/years
            'weekdays'      => '',            // weekly: CSV of ISO weekdays, e.g. "1,3,5"
            'month_mode'    => 'dom',         // monthly/yearly: dom | nth
            'day_of_month'  => null,          // dom: 1..31, or -1 for the last day
            'nth'           => null,          // nth: 1..4, or -1 for the last one
            'nth_weekday'   => null,          // nth: ISO weekday
            'month_of_year' => null,          // yearly: 1..12
            'ends_mode'     => 'never',       // never | on_date | after_count
            'ends_on'       => null,
            'max_occurrences' => null,
        ];
    }

    /**
     * The next date in the series strictly AFTER $from.
     *
     * $from is the anchor: the completion date for 'completion' mode, or the
     * previous occurrence's due date for 'schedule'. Returns Y-m-d, or null if
     * the rule cannot produce one (an empty weekly weekday set, say).
     *
     * Every branch is "move the anchor forward, then land on a valid day",
     * never "add days and hope" - which is what makes month ends and nth
     * weekdays behave.
     */
    public static function nextDate(array $rule, string $from): ?string
    {
        $r    = $rule + self::blankRule();
        $step = max(1, (int)($r['interval_n'] ?? 1));
        $d    = self::mkDate($from);
        if (!$d) return null;

        switch ($r['freq']) {
            case 'daily':
                return $d->modify("+{$step} day")->format('Y-m-d');

            case 'weekly':
                return self::nextWeekly($d, $step, (string)$r['weekdays']);

            case 'monthly':
                return self::nextMonthly($d, $step, $r);

            case 'yearly':
                return self::nextYearly($d, $step, $r);
        }
        return null;
    }

    /**
     * Weekly, optionally on named days.
     *
     * With no days named it is simply every N weeks on the same weekday. With
     * days named, the next named day is found by walking forward - and when the
     * week runs out, jumping to the start of the week N weeks on, so "every 2
     * weeks on Mon and Thu" does not quietly become every week.
     */
    private static function nextWeekly(DateTimeImmutable $d, int $step, string $weekdays): ?string
    {
        $days = array_values(array_filter(array_map('intval', explode(',', $weekdays)),
            fn($n) => $n >= 1 && $n <= 7));
        if (!$days) {
            return $d->modify('+' . (7 * $step) . ' day')->format('Y-m-d');
        }
        sort($days);

        $cur = (int)$d->format('N');
        foreach ($days as $wd) {
            if ($wd > $cur) {
                return $d->modify('+' . ($wd - $cur) . ' day')->format('Y-m-d');
            }
        }
        // Past the last named day this week: go to the Monday $step weeks on,
        // then out to the first named day of that week.
        $mondayNext = $d->modify('-' . ($cur - 1) . ' day')->modify('+' . (7 * $step) . ' day');
        return $mondayNext->modify('+' . ($days[0] - 1) . ' day')->format('Y-m-d');
    }

    /** Monthly, either on a day number or on the nth weekday. */
    private static function nextMonthly(DateTimeImmutable $d, int $step, array $r): ?string
    {
        // Move to the first of the target month FIRST. Adding a month to the
        // 31st in PHP lands in the month after next, which is the classic way
        // monthly recurrence quietly skips February.
        $firstOfTarget = $d->modify('first day of this month')->modify("+{$step} month");

        if (($r['month_mode'] ?? 'dom') === 'nth') {
            return self::nthWeekdayOf($firstOfTarget, (int)$r['nth'], (int)$r['nth_weekday']);
        }
        $dom = $r['day_of_month'] !== null ? (int)$r['day_of_month'] : (int)$d->format('j');
        return self::dayOfMonth($firstOfTarget, $dom);
    }

    /** Yearly. Same two shapes as monthly, a chosen month at a time. */
    private static function nextYearly(DateTimeImmutable $d, int $step, array $r): ?string
    {
        $target = $d->modify('first day of this month')->modify("+{$step} year");
        $month  = $r['month_of_year'] !== null ? (int)$r['month_of_year'] : (int)$d->format('n');
        $target = $target->setDate((int)$target->format('Y'), max(1, min(12, $month)), 1);

        if (($r['month_mode'] ?? 'dom') === 'nth') {
            return self::nthWeekdayOf($target, (int)$r['nth'], (int)$r['nth_weekday']);
        }
        $dom = $r['day_of_month'] !== null ? (int)$r['day_of_month'] : (int)$d->format('j');
        return self::dayOfMonth($target, $dom);
    }

    /**
     * A day number within the month $first belongs to.
     * -1 means the last day. Anything past the end of a short month CLAMPS to
     * its last day: "the 31st" in a 30-day month means the 30th, not the 1st of
     * the month after, which is what naive date arithmetic produces.
     */
    private static function dayOfMonth(DateTimeImmutable $first, int $dom): string
    {
        $len = (int)$first->format('t');
        if ($dom === -1 || $dom > $len) $dom = $len;
        if ($dom < 1) $dom = 1;
        return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), $dom)->format('Y-m-d');
    }

    /**
     * The nth given weekday of the month $first belongs to - "the 2nd Tuesday",
     * "the last Friday". $nth of -1 means the last one.
     *
     * A month has either four or five of any weekday, so a rule asking for the
     * 5th falls back to the last rather than spilling into the next month.
     */
    private static function nthWeekdayOf(DateTimeImmutable $first, int $nth, int $weekday): ?string
    {
        if ($weekday < 1 || $weekday > 7) return null;

        if ($nth === -1) {
            $last  = $first->modify('last day of this month');
            $shift = ((int)$last->format('N') - $weekday + 7) % 7;
            return $last->modify("-{$shift} day")->format('Y-m-d');
        }
        $nth   = max(1, min(5, $nth));
        $shift = ($weekday - (int)$first->format('N') + 7) % 7;
        $cand  = $first->modify('+' . ($shift + 7 * ($nth - 1)) . ' day');
        // Asked for the 5th and the month has only four: use the last one.
        if ((int)$cand->format('n') !== (int)$first->format('n')) {
            return self::nthWeekdayOf($first, -1, $weekday);
        }
        return $cand->format('Y-m-d');
    }

    /**
     * Has the series finished? Checked BEFORE creating, so a series that ends
     * "after 6 occurrences" produces six and not seven.
     */
    public static function isExhausted(array $rule, string $nextDate): bool
    {
        $mode = $rule['ends_mode'] ?? 'never';
        if ($mode === 'on_date') {
            return !empty($rule['ends_on']) && $nextDate > (string)$rule['ends_on'];
        }
        if ($mode === 'after_count') {
            $max = (int)($rule['max_occurrences'] ?? 0);
            return $max > 0 && (int)($rule['occurrences_created'] ?? 0) >= $max;
        }
        return false;
    }

    private static function mkDate(string $ymd): ?DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr(trim($ymd), 0, 10), new DateTimeZone('UTC'));
        return $d ?: null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Making the next occurrence
    // ─────────────────────────────────────────────────────────────────────────

    /** The rule a task belongs to, or null. */
    public static function ruleForTask(PDO $conn, int $taskId): ?array
    {
        $s = $conn->prepare(
            "SELECT r.* FROM task_recurrences r
               JOIN tasks t ON t.recurrence_id = r.id
              WHERE t.id = ? AND r.is_active = 1"
        );
        $s->execute([$taskId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * A task has just been completed. If it repeats on completion, make the next
     * one and return its id.
     *
     * Called from BOTH ways a task can close - saving a status change, and
     * dragging the card on the board. moveTask() is documented "No workflow
     * event" and dispatches nothing, so hooking only the save path would have
     * meant a task that repeats when you tick it and silently does not when you
     * drag it, which is the worst of both.
     *
     * Never throws. A recurrence that cannot be produced must not take the
     * completion down with it: the person finished their work and that has to be
     * recorded whatever happens next.
     */
    public static function onTaskClosed(PDO $conn, int $taskId): ?int
    {
        try {
            $rule = self::ruleForTask($conn, $taskId);
            if (!$rule || $rule['mode'] !== 'completion') return null;

            // Counted from the day it was actually finished, not from the day it
            // was due. A monthly check finished four days late is next due a
            // month after you did it.
            $next = self::nextDate($rule, gmdate('Y-m-d'));
            if ($next === null || self::isExhausted($rule, $next)) {
                if ($next === null || self::isExhausted($rule, $next)) self::deactivate($conn, (int)$rule['id']);
                return null;
            }
            return self::createOccurrence($conn, $taskId, $rule, $next);
        } catch (Throwable $e) {
            error_log('TaskRecurrence::onTaskClosed failed for task ' . $taskId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Produce every occurrence now due on a fixed schedule. Returns the ids made.
     *
     * Deliberately catches up rather than skipping: a series whose worker has not
     * run for a week produces the occurrences it owed, because a missed
     * compliance review is a fact somebody needs to see, not something to quietly
     * forget. Capped per series per run so a rule left dormant for two years
     * cannot create six hundred tasks in one go.
     */
    public static function runDue(PDO $conn, ?string $today = null, int $capPerSeries = 24): array
    {
        $today = $today ?: gmdate('Y-m-d');
        $made  = [];

        $s = $conn->prepare(
            "SELECT * FROM task_recurrences
              WHERE is_active = 1 AND mode = 'schedule'
                AND next_due_date IS NOT NULL AND next_due_date <= ?"
        );
        $s->execute([$today]);

        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $rule) {
            $rid  = (int)$rule['id'];
            $due  = (string)$rule['next_due_date'];
            $seen = 0;

            while ($due !== '' && $due <= $today && $seen < $capPerSeries) {
                if (self::isExhausted($rule, $due)) { self::deactivate($conn, $rid); break; }

                // The newest occurrence is what the next one is copied from, so
                // an edit to the series carries forward rather than resurrecting
                // whatever the first one said a year ago.
                $src = self::latestOccurrence($conn, $rid);
                if (!$src) { self::deactivate($conn, $rid); break; }

                try {
                    // Idempotency, and it is not theoretical. A series is seeded
                    // with next_due_date set to its FIRST occurrence's own due
                    // date, so without this the very first worker run produces a
                    // duplicate of the task the series was created from. The same
                    // guard covers the worker running twice — two cron entries, or
                    // someone running it by hand after it has already fired.
                    if (self::occurrenceExistsFor($conn, $rid, $due)) {
                        $id = null;
                    } else {
                        $id = self::createOccurrence($conn, $src, $rule, $due);
                    }
                    if ($id) { $made[] = $id; $rule['occurrences_created'] = (int)$rule['occurrences_created'] + 1; }
                } catch (Throwable $e) {
                    error_log("TaskRecurrence::runDue series $rid failed: " . $e->getMessage());
                    break;
                }

                $next = self::nextDate($rule, $due);
                if ($next === null || $next === $due) { self::deactivate($conn, $rid); break; }
                $due = $next;
                $seen++;
                $conn->prepare("UPDATE task_recurrences SET next_due_date = ? WHERE id = ?")->execute([$due, $rid]);
            }
        }
        return $made;
    }

    private static function deactivate(PDO $conn, int $recurrenceId): void
    {
        $conn->prepare("UPDATE task_recurrences SET is_active = 0, updated_datetime = UTC_TIMESTAMP() WHERE id = ?")
             ->execute([$recurrenceId]);
    }

    /** Does this series already have a top-level occurrence due on that date? */
    private static function occurrenceExistsFor(PDO $conn, int $recurrenceId, string $due): bool
    {
        $s = $conn->prepare(
            "SELECT 1 FROM tasks
              WHERE recurrence_id = ? AND parent_task_id IS NULL AND due_date = ? LIMIT 1"
        );
        $s->execute([$recurrenceId, $due]);
        return (bool)$s->fetchColumn();
    }

    /** The most recent task in a series - the one a new occurrence is copied from. */
    private static function latestOccurrence(PDO $conn, int $recurrenceId): ?int
    {
        $s = $conn->prepare(
            "SELECT id FROM tasks WHERE recurrence_id = ? AND parent_task_id IS NULL
              ORDER BY id DESC LIMIT 1"
        );
        $s->execute([$recurrenceId]);
        $id = $s->fetchColumn();
        return $id ? (int)$id : null;
    }

    /**
     * Copy $sourceTaskId into a new task due on $due, honouring the rule's
     * copy_* flags, and count it against the series.
     *
     * What is NEVER copied: the completed timestamp, and the status. A new
     * occurrence starts open even if the series was completed into some other
     * status - spawning one straight into a closed status would make it look
     * done on arrival, and in completion mode could loop.
     */
    private static function createOccurrence(PDO $conn, int $sourceTaskId, array $rule, string $due): ?int
    {
        $src = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
        $src->execute([$sourceTaskId]);
        $t = $src->fetch(PDO::FETCH_ASSOC);
        if (!$t) return null;

        $statusId = self::defaultOpenStatusId($conn);
        if ($statusId === null) return null;   // no open status configured: do nothing rather than guess

        // Keep the gap between starting and being due. "Start a week before it is
        // due" is part of the plan, not an accident of the first occurrence.
        $start = null;
        if (!empty($t['start_date']) && !empty($t['due_date'])) {
            $gap = (int)((strtotime((string)$t['due_date']) - strtotime((string)$t['start_date'])) / 86400);
            if ($gap > 0) $start = gmdate('Y-m-d', strtotime($due) - $gap * 86400);
        }

        $master = $t['recurrence_master_id'] ?: $t['id'];

        $conn->prepare(
            "INSERT INTO tasks (title, description, status_id, priority_id, start_date, due_date,
                                assigned_analyst_id, assigned_team_id,
                                ticket_id, change_id, contract_id, tenant_id,
                                recurrence_id, recurrence_master_id,
                                board_position, created_by_id, created_datetime, updated_datetime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                     (SELECT * FROM (SELECT COALESCE(MAX(board_position), -1) + 1 FROM tasks
                                      WHERE status_id = ? AND parent_task_id IS NULL) x),
                     ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        )->execute([
            $t['title'],
            (int)$rule['copy_description'] ? $t['description'] : null,
            $statusId,
            $t['priority_id'],
            $start,
            $due,
            (int)$rule['copy_assignee'] ? $t['assigned_analyst_id'] : null,
            (int)$rule['copy_assignee'] ? $t['assigned_team_id']    : null,
            (int)$rule['copy_links'] ? $t['ticket_id']   : null,
            (int)$rule['copy_links'] ? $t['change_id']   : null,
            (int)$rule['copy_links'] ? $t['contract_id'] : null,
            $t['tenant_id'],
            $rule['id'],
            $master,
            $statusId,
            $t['created_by_id'],
        ]);
        $newId = (int)$conn->lastInsertId();

        if ((int)$rule['copy_tags']) {
            $conn->prepare(
                "INSERT IGNORE INTO task_tag_map (task_id, tag_id)
                 SELECT ?, tag_id FROM task_tag_map WHERE task_id = ?"
            )->execute([$newId, $sourceTaskId]);
        }

        if ((int)$rule['copy_subtasks']) {
            // Titles and assignees carry; a subtask's own completion never does,
            // and neither do its dates - last month's dates mean nothing here.
            $subs = $conn->prepare(
                "SELECT title, assigned_analyst_id, assigned_team_id, priority_id
                   FROM tasks WHERE parent_task_id = ? ORDER BY board_position, id"
            );
            $subs->execute([$sourceTaskId]);
            $ins = $conn->prepare(
                "INSERT INTO tasks (title, status_id, priority_id, assigned_analyst_id, assigned_team_id,
                                    parent_task_id, tenant_id, created_by_id, created_datetime, updated_datetime)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            );
            foreach ($subs->fetchAll(PDO::FETCH_ASSOC) as $s2) {
                $ins->execute([
                    $s2['title'], $statusId, $s2['priority_id'],
                    (int)$rule['copy_assignee'] ? $s2['assigned_analyst_id'] : null,
                    (int)$rule['copy_assignee'] ? $s2['assigned_team_id']    : null,
                    $newId, $t['tenant_id'], $t['created_by_id'],
                ]);
            }
        }

        if ((int)$rule['copy_attachments']) {
            // Links the SAME documents to the new task. It does not duplicate
            // files: one procedure attached to twelve monthly checks should be
            // one document, edited once.
            try {
                $conn->prepare(
                    "INSERT INTO document_links (document_id, parent_type, parent_id, linked_by_id, created_datetime)
                     SELECT document_id, 'task', ?, linked_by_id, UTC_TIMESTAMP()
                       FROM document_links WHERE parent_type = 'task' AND parent_id = ?"
                )->execute([$newId, $sourceTaskId]);
            } catch (Throwable $e) {
                // An install without the documents tables still gets its task.
            }
        }

        $conn->prepare(
            "UPDATE task_recurrences
                SET occurrences_created = occurrences_created + 1, updated_datetime = UTC_TIMESTAMP()
              WHERE id = ?"
        )->execute([(int)$rule['id']]);

        return $newId;
    }

    /**
     * The status a new occurrence starts in: the configured default, but only
     * among OPEN statuses.
     *
     * TasksService::lookupDefault deliberately does not filter on is_closed,
     * because an admin who makes a closed status the default has said what they
     * meant. That reasoning does not carry here - a recurrence that arrives
     * already closed is not work anybody will do, and on completion mode it
     * would complete itself.
     */
    private static function defaultOpenStatusId(PDO $conn): ?int
    {
        $id = $conn->query(
            "SELECT id FROM task_statuses WHERE is_active = 1 AND is_closed = 0
              ORDER BY is_default DESC, display_order, id LIMIT 1"
        )->fetchColumn();
        return $id ? (int)$id : null;
    }
}
