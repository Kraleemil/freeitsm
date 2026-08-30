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
}
