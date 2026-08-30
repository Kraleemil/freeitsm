<?php
/**
 * The task recurrence date engine (discussion #94).
 *
 * Pure date arithmetic, no database, which is precisely why it is worth testing
 * hard: every interesting bug in a recurrence feature is a calendar edge case,
 * and none of them show up in a demo where everything falls on the 15th of a
 * 31-day month.
 *
 * The cases below are the ones that break naive implementations:
 *   - the 31st in a 30-day month, and in February
 *   - "the last day of the month" across month lengths and a leap year
 *   - "the 5th Tuesday" of a month that has only four
 *   - "every 2 weeks on Mon and Thu" not collapsing into every week
 *   - 29 February under a yearly rule
 *
 * Run:  php tests/task-recurrence-dates.php
 */

require_once __DIR__ . '/../includes/services/task_recurrence.php';

$pass = 0; $fail = 0;
function is_(string $what, $got, $want): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; printf("  PASS  %-58s %s\n", $what, var_export($got, true)); }
    else { $fail++; printf("  FAIL  %-58s got %s, wanted %s\n", $what, var_export($got, true), var_export($want, true)); }
}
function nxt(array $rule, string $from) { return TaskRecurrence::nextDate($rule, $from); }

echo "\nTask recurrence date engine (#94)\n" . str_repeat('=', 78) . "\n";

// ── Daily ────────────────────────────────────────────────────────────────────
echo "\nDaily:\n";
is_('every day',            nxt(['freq'=>'daily','interval_n'=>1], '2026-08-30'), '2026-08-31');
is_('every 3 days',         nxt(['freq'=>'daily','interval_n'=>3], '2026-08-30'), '2026-09-02');
is_('every day over a month end', nxt(['freq'=>'daily','interval_n'=>1], '2026-08-31'), '2026-09-01');
is_('every day over a year end',  nxt(['freq'=>'daily','interval_n'=>1], '2026-12-31'), '2027-01-01');

// ── Weekly ───────────────────────────────────────────────────────────────────
echo "\nWeekly:\n";
// 2026-08-30 is a Sunday.
is_('every week, same weekday',    nxt(['freq'=>'weekly','interval_n'=>1], '2026-08-30'), '2026-09-06');
is_('every 2 weeks, same weekday', nxt(['freq'=>'weekly','interval_n'=>2], '2026-08-30'), '2026-09-13');
// Mon=1 Thu=4. From Monday 2026-08-31 the next named day is Thursday.
is_('weekly on Mon+Thu, from a Monday', nxt(['freq'=>'weekly','interval_n'=>1,'weekdays'=>'1,4'], '2026-08-31'), '2026-09-03');
// From that Thursday, the week is out of named days, so back to Monday.
is_('weekly on Mon+Thu, from the Thursday', nxt(['freq'=>'weekly','interval_n'=>1,'weekdays'=>'1,4'], '2026-09-03'), '2026-09-07');
// ⭐ The one that catches a naive implementation: with interval 2 the wrap must
// skip a whole week, or "every fortnight" silently becomes "every week".
is_('FORTNIGHTLY on Mon+Thu, wrapping the week', nxt(['freq'=>'weekly','interval_n'=>2,'weekdays'=>'1,4'], '2026-09-03'), '2026-09-14');
is_('fortnightly on Mon+Thu, within the week',   nxt(['freq'=>'weekly','interval_n'=>2,'weekdays'=>'1,4'], '2026-08-31'), '2026-09-03');

// ── Monthly by day number ────────────────────────────────────────────────────
echo "\nMonthly, by day of the month:\n";
is_('the 15th, next month',        nxt(['freq'=>'monthly','interval_n'=>1,'day_of_month'=>15], '2026-08-15'), '2026-09-15');
is_('every 3 months',              nxt(['freq'=>'monthly','interval_n'=>3,'day_of_month'=>15], '2026-08-15'), '2026-11-15');
// ⭐ 31 January + 1 month. Naive addition lands on 2 or 3 March.
is_('the 31st into a SHORT month',  nxt(['freq'=>'monthly','interval_n'=>1,'day_of_month'=>31], '2026-01-31'), '2026-02-28');
is_('the 31st into a 30-day month', nxt(['freq'=>'monthly','interval_n'=>1,'day_of_month'=>31], '2026-03-31'), '2026-04-30');
is_('the 31st, and the month has one', nxt(['freq'=>'monthly','interval_n'=>1,'day_of_month'=>31], '2026-04-30'), '2026-05-31');
is_('the LAST day (-1), into February', nxt(['freq'=>'monthly','interval_n'=>1,'day_of_month'=>-1], '2026-01-31'), '2026-02-28');
is_('the LAST day (-1), leap February',  nxt(['freq'=>'monthly','interval_n'=>1,'day_of_month'=>-1], '2028-01-31'), '2028-02-29');

// ── Monthly by nth weekday ───────────────────────────────────────────────────
echo "\nMonthly, by nth weekday (Ed's ask):\n";
// September 2026: the 1st is a Tuesday.
is_('1st Tuesday of next month',  nxt(['freq'=>'monthly','interval_n'=>1,'month_mode'=>'nth','nth'=>1,'nth_weekday'=>TaskRecurrence::TUE], '2026-08-04'), '2026-09-01');
is_('2nd Tuesday of next month',  nxt(['freq'=>'monthly','interval_n'=>1,'month_mode'=>'nth','nth'=>2,'nth_weekday'=>TaskRecurrence::TUE], '2026-08-04'), '2026-09-08');
is_('3rd Monday of next month',   nxt(['freq'=>'monthly','interval_n'=>1,'month_mode'=>'nth','nth'=>3,'nth_weekday'=>TaskRecurrence::MON], '2026-08-04'), '2026-09-21');
is_('LAST Friday of next month',  nxt(['freq'=>'monthly','interval_n'=>1,'month_mode'=>'nth','nth'=>-1,'nth_weekday'=>TaskRecurrence::FRI], '2026-08-04'), '2026-09-25');
// September 2026 HAS five Tuesdays (1, 8, 15, 22, 29), so this asks for a real
// fifth one and must get it rather than spilling into October.
is_('5th Tuesday, and the month has one', nxt(['freq'=>'monthly','interval_n'=>1,'month_mode'=>'nth','nth'=>5,'nth_weekday'=>TaskRecurrence::TUE], '2026-08-04'), '2026-09-29');
// ⭐ The fallback case. September 2026 has only FOUR Fridays (4, 11, 18, 25), so
// a rule asking for the fifth must land on the last one and not on 2 October.
// An earlier version of this test used Tuesday, which that month has five of -
// it passed without ever exercising the fallback it claimed to cover.
is_('5th Friday when the month has only four', nxt(['freq'=>'monthly','interval_n'=>1,'month_mode'=>'nth','nth'=>5,'nth_weekday'=>TaskRecurrence::FRI], '2026-08-04'), '2026-09-25');

// ── Yearly ───────────────────────────────────────────────────────────────────
echo "\nYearly:\n";
is_('same day next year',        nxt(['freq'=>'yearly','interval_n'=>1,'month_of_year'=>8,'day_of_month'=>30], '2026-08-30'), '2027-08-30');
is_('every 2 years',             nxt(['freq'=>'yearly','interval_n'=>2,'month_of_year'=>8,'day_of_month'=>30], '2026-08-30'), '2028-08-30');
// ⭐ 29 Feb in a non-leap year has to clamp rather than roll into March.
is_('29 Feb into a NON-leap year', nxt(['freq'=>'yearly','interval_n'=>1,'month_of_year'=>2,'day_of_month'=>29], '2028-02-29'), '2029-02-28');
is_('29 Feb, four years on',       nxt(['freq'=>'yearly','interval_n'=>4,'month_of_year'=>2,'day_of_month'=>29], '2028-02-29'), '2032-02-29');
is_('2nd Monday of March, yearly', nxt(['freq'=>'yearly','interval_n'=>1,'month_of_year'=>3,'month_mode'=>'nth','nth'=>2,'nth_weekday'=>TaskRecurrence::MON], '2026-03-09'), '2027-03-08');

// ── Guards ───────────────────────────────────────────────────────────────────
echo "\nRefusals and edges:\n";
is_('an unknown frequency yields nothing', nxt(['freq'=>'fortnightly'], '2026-08-30'), null);
is_('a malformed date yields nothing',     nxt(['freq'=>'daily'], 'not-a-date'), null);
is_('interval 0 is treated as 1',          nxt(['freq'=>'daily','interval_n'=>0], '2026-08-30'), '2026-08-31');
is_('a negative interval is treated as 1', nxt(['freq'=>'daily','interval_n'=>-5], '2026-08-30'), '2026-08-31');
is_('nth with no weekday yields nothing',  nxt(['freq'=>'monthly','month_mode'=>'nth','nth'=>2,'nth_weekday'=>0], '2026-08-04'), null);
is_('weekly with a junk weekday list falls back to every N weeks',
    nxt(['freq'=>'weekly','interval_n'=>1,'weekdays'=>'9,0,x'], '2026-08-30'), '2026-09-06');

// ── Ending a series ──────────────────────────────────────────────────────────
echo "\nWhen a series stops:\n";
is_('never ends',            TaskRecurrence::isExhausted(['ends_mode'=>'never'], '2099-01-01'), false);
is_('before the end date',   TaskRecurrence::isExhausted(['ends_mode'=>'on_date','ends_on'=>'2026-12-31'], '2026-12-30'), false);
is_('exactly on the end date is still allowed', TaskRecurrence::isExhausted(['ends_mode'=>'on_date','ends_on'=>'2026-12-31'], '2026-12-31'), false);
is_('past the end date',     TaskRecurrence::isExhausted(['ends_mode'=>'on_date','ends_on'=>'2026-12-31'], '2027-01-01'), true);
is_('5 of 6 made',           TaskRecurrence::isExhausted(['ends_mode'=>'after_count','max_occurrences'=>6,'occurrences_created'=>5], '2026-09-01'), false);
is_('6 of 6 made - stop',    TaskRecurrence::isExhausted(['ends_mode'=>'after_count','max_occurrences'=>6,'occurrences_created'=>6], '2026-09-01'), true);

echo "\n" . str_repeat('=', 78) . "\n  {$pass} passed, {$fail} failed\n\n";
exit($fail === 0 ? 0 : 1);
