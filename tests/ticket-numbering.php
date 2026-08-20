<?php
/**
 * Ticket numbering (GH discussion #71).
 *
 * The assertions that matter are not "it makes a number". They are:
 *
 *   1. A number is NEVER issued twice — including one a renumbered ticket used
 *      to have, because a reply quoting it must not land on a stranger's ticket.
 *   2. An old number KEEPS RESOLVING forever, because the emails quoting it live
 *      in customers' inboxes.
 *   3. The reference parser recognises ANY format, because an install can change
 *      format and every number it ever issued has to go on working.
 *   4. The padding is a MINIMUM, never a limit.
 *
 * ⚠️ Creates only `ZZNUM`-prefixed rows and sweeps them before AND after.
 *
 * Run:  php tests/ticket-numbering.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ticket_numbering.php';

$pass = 0; $fail = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  {$what}\n"; }
    else       { $fail++; echo "  FAIL  {$what}" . ($detail ? "  <- {$detail}" : '') . "\n"; }
}

$conn = connectToDatabase();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sweep = function () use ($conn): void {
    $conn->query("DELETE FROM ticket_number_history WHERE ticket_number LIKE 'ZZNUM%'");
    $conn->query("DELETE FROM ticket_number_counters WHERE counter_key IN ('t:ty999999','t')");
};
$sweep();
TicketNumbering::forget();

$countBefore = (int)$conn->query("SELECT COUNT(*) FROM tickets")->fetchColumn();

try {
    // ================================================================
    echo "\n--- the format engine ---\n";
    // ================================================================

    ok('pads to the width given',
        TicketNumbering::render('ZZNUM-{######}', 42) === 'ZZNUM-000042',
        TicketNumbering::render('ZZNUM-{######}', 42));

    // 🔑 The point Ed raised: a big organisation reaches a million sooner than
    // you would think, and the format must not become a cliff.
    ok('a million tickets still fits',
        TicketNumbering::render('ZZNUM-{######}', 1000000) === 'ZZNUM-1000000',
        TicketNumbering::render('ZZNUM-{######}', 1000000));
    ok('...and TEN million just gets longer, nothing breaks',
        TicketNumbering::render('ZZNUM-{######}', 10000000) === 'ZZNUM-10000000',
        TicketNumbering::render('ZZNUM-{######}', 10000000));

    $y = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y');
    ok('year token', TicketNumbering::render('ZZNUM-{YYYY}-{####}', 7) === "ZZNUM-{$y}-0007",
        TicketNumbering::render('ZZNUM-{YYYY}-{####}', 7));
    ok('any width works', TicketNumbering::render('{###}', 5) === '005');

    echo "\n--- format validation ---\n";
    ok('a format with no number token is refused',
        count(TicketNumbering::validateFormat('ZZNUM-ONLY')) === 1);
    ok('square brackets are refused (they would break the email tag)',
        (bool)array_filter(TicketNumbering::validateFormat('[{####}]'), fn($p) => str_contains($p, 'brackets')));
    ok('spaces are refused',
        (bool)array_filter(TicketNumbering::validateFormat('ZZ NUM-{####}'), fn($p) => str_contains($p, 'Spaces')));
    ok('a format longer than the column is refused',
        (bool)array_filter(TicketNumbering::validateFormat(str_repeat('X', 60) . '-{####}'), fn($p) => str_contains($p, '50')));
    ok('a good format passes', TicketNumbering::validateFormat('ZZNUM-{######}') === []);

    // ================================================================
    echo "\n--- the counter ---\n";
    // ================================================================

    TicketNumbering::withSettings([
        'ticket_number_style'  => 'sequential',
        'ticket_number_format' => 'ZZNUM-{#####}',
        'ticket_number_start'  => '1',
        'ticket_number_scope'  => 'per_type',
        'ticket_number_reset'  => 'never',
    ]);

    // ⚠️ A THROWAWAY COUNTER. With scope 'global' the key is literally 't' —
    // the one a real install uses — so an earlier version of this test was
    // winding the production counter forward. Scoping to a type id nothing owns
    // gives the test its own sequence to play with.
    $TESTTYPE = 999999;

    $cfg = ['ticket_number_scope' => 'global', 'ticket_number_reset' => 'never'];
    ok('the counter key is stable', TicketNumbering::counterKey($cfg, 5, 2) === 't');

    $perType = TicketNumbering::counterKey(['ticket_number_scope' => 'per_type'], 5, null);
    ok('per-type gives each type its own counter', $perType === 't:ty5', $perType);

    $yearly = TicketNumbering::counterKey(['ticket_number_reset' => 'yearly'], null, null);
    ok('a yearly reset is just a different counter, not a job', $yearly === "t:{$y}", $yearly);

    echo "\n--- issuing numbers ---\n";

    $n1 = TicketNumbering::next($conn, $TESTTYPE, null);
    $n2 = TicketNumbering::next($conn, $TESTTYPE, null);
    $n3 = TicketNumbering::next($conn, $TESTTYPE, null);
    ok('the first number is the configured start', $n1 === 'ZZNUM-00001', $n1);
    ok('numbers increment', $n2 === 'ZZNUM-00002' && $n3 === 'ZZNUM-00003', "{$n2} {$n3}");
    ok('...and they are all different', count(array_unique([$n1, $n2, $n3])) === 3);

    // ================================================================
    echo "\n--- 🔑 A RETIRED NUMBER IS NEVER REISSUED ---\n";
    // ================================================================

    // Park ZZNUM-00099 in history against a real ticket, then prove the
    // generator refuses to hand it out again.
    $someTicket = (int)$conn->query("SELECT id FROM tickets ORDER BY id LIMIT 1")->fetchColumn();
    if ($someTicket) {
        $conn->prepare("INSERT INTO ticket_number_history (ticket_id, ticket_number, reason) VALUES (?, 'ZZNUM-00099', 'renumber')")
             ->execute([$someTicket]);

        ok('a number held only in HISTORY counts as in use',
            TicketNumbering::inUse($conn, 'ZZNUM-00099'));

        // 🔑 The whole point: an old number still finds its ticket.
        ok('an old number still resolves to its ticket',
            TicketNumbering::findTicketId($conn, 'ZZNUM-00099') === $someTicket);

        // Wind the counter to 98 so the next number WOULD be 99 without the guard.
        $conn->prepare("UPDATE ticket_number_counters SET next_value = 98 WHERE counter_key = 't:ty999999'")->execute();
        $next = TicketNumbering::next($conn, $TESTTYPE, null);
        ok('the generator SKIPS a retired number rather than reusing it',
            $next !== 'ZZNUM-00099', $next);
    } else {
        ok('(skipped — no tickets on this install)', true);
    }

    // ================================================================
    echo "\n--- 🔴 THE PARSER MUST NOT ENCODE A FORMAT ---\n";
    // ================================================================

    // Every one of these has been, or could be, a real ticket number on some
    // install. The parser has to find all of them.
    $shapes = [
        'CKQ-418-73926'      => "today's random format",
        'TICKET-000042'      => 'a padded sequential',
        'INC-2026-0007'      => 'a prefix with a year',
        'ABC-123-XYZ'        => 'a number migrated from another system',
        '1010138'            => 'a bare increment, Zammad style',
        'SD/2026/000123'     => 'slashes',
    ];
    $found = 0;
    foreach ($shapes as $num => $desc) {
        // ⚠️ (string) is load-bearing: PHP silently casts a numeric-string array
        // KEY to an integer, so '1010138' arrives here as int 1010138 and a
        // === against the captured string would fail for the format that is
        // most likely to be used by an install migrating from Zammad.
        $num     = (string)$num;
        $subject = "Re: [SDREF:{$num}] Printer jammed";
        if (preg_match(TicketNumbering::REF_PATTERN, $subject, $m) && $m[1] === $num) {
            $found++;
        } else {
            echo "        (missed {$desc}: {$num})\n";
        }
    }
    ok('the parser recognises EVERY format, old and new', $found === count($shapes),
        "{$found}/" . count($shapes));

    ok('the reply-above-this-line marker is format-agnostic too',
        preg_match(TicketNumbering::REF_LINE_PATTERN,
                   '[*** SDREF:TICKET-000042 REPLY ABOVE THIS LINE ***]', $m2) && $m2[1] === 'TICKET-000042');

    ok('a malformed subject cannot swallow the line',
        !preg_match(TicketNumbering::REF_PATTERN, '[SDREF:has spaces in it] hello'));

    // ================================================================
    echo "\n--- preview writes nothing ---\n";
    // ================================================================

    $before = (int)$conn->query("SELECT next_value FROM ticket_number_counters WHERE counter_key='t:ty999999'")->fetchColumn();
    $prev = TicketNumbering::preview([
        'ticket_number_style'  => 'sequential',
        'ticket_number_format' => 'ZZNUM-{####}',
        'ticket_number_start'  => '500',
    ], 3);
    ok('preview shows what would come next', $prev === ['ZZNUM-0500', 'ZZNUM-0501', 'ZZNUM-0502'],
        json_encode($prev));
    $after = (int)$conn->query("SELECT next_value FROM ticket_number_counters WHERE counter_key='t:ty999999'")->fetchColumn();
    ok('...and moves no counter', $before === $after, "{$before} -> {$after}");

    echo "\n--- random style still works (the default) ---\n";
    TicketNumbering::withSettings(['ticket_number_style' => 'random']);
    $r = TicketNumbering::next($conn, $TESTTYPE, null);
    ok('random keeps the historical shape', (bool)preg_match('/^[A-Z]{3}-\d{3}-\d{5}$/', $r), $r);

} catch (Throwable $e) {
    $fail++;
    echo "\n  FATAL  " . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

TicketNumbering::withSettings(null);
$sweep();

echo "\n";
$countAfter = (int)$conn->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
ok('no tickets were created or destroyed', $countAfter === $countBefore, "{$countBefore} -> {$countAfter}");

echo "\n" . str_repeat('=', 52) . "\n";
echo ($fail === 0 ? 'ALL GREEN' : 'FAILURES') . ": {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
