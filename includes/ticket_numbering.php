<?php
/**
 * Ticket numbering — the ONE place a ticket number is made or recognised.
 *
 * ⚠️ WHY THIS FILE EXISTS. `generateTicketNumber()` was written THREE times —
 * in includes/services/tickets.php, api/self-service/create_ticket.php and
 * api/tickets/check_mailbox_email.php — and the format was hardcoded a fourth,
 * fifth, sixth and seventh time as the regex `[A-Z]{3}-\d{3}-\d{5}` across four
 * more files. A setting that reached only one of them would have given portal
 * tickets one format and emailed tickets another.
 *
 * 🔴 THE LANDMINE THIS FILE DEFUSES. Every notification FreeITSM has ever sent
 * carries `[SDREF:CKQ-418-73926]` in its subject, and those emails live in
 * customers' inboxes forever. The inbound parser used to look for that exact
 * SHAPE. Change the format and every reply to every historical email silently
 * becomes a new ticket — no error, no warning, across the whole estate at once.
 * Zammad's own documentation carries the same warning about its numbering.
 *
 * 🔑 The fix is a change of principle: **the tag is a delimiter, the DATABASE is
 * the authority.** Capture whatever sits between `SDREF:` and `]`, then look it
 * up. The parser must never encode a format, because there is no longer only
 * one — an install can change format, and every number it ever issued has to go
 * on working.
 *
 * @see docs/design/ticket-numbering.md
 */

require_once __DIR__ . '/../config.php';

class TicketNumbering
{
    /** Every setting, with the default that reproduces today's behaviour. */
    const DEFAULTS = [
        // random = today's CKQ-418-73926. Kept as the default so an upgrade
        // changes nothing until somebody chooses otherwise.
        'ticket_number_style'      => 'random',      // random | sequential
        'ticket_number_format'     => 'TICKET-{######}',
        'ticket_number_start'      => '1',
        // What each counter counts. per_type gives INC-… and REQ-… their own
        // sequences, which is what ServiceNow does and what the request asked for.
        'ticket_number_scope'      => 'global',      // global | per_type | per_company
        'ticket_number_reset'      => 'never',       // never | yearly | monthly
        // Renumbering existing tickets is a MIGRATION tool, not housekeeping.
        'ticket_number_renumber'   => 'never',       // never | once
    ];

    /**
     * The tokens a format may contain.
     *
     * 🔑 `{###…}` is a MINIMUM WIDTH, never a limit. Ticket 1,000,001 under
     * `{######}` simply prints seven digits. A format that broke at its width
     * would need a migration on the day an install got busy, which is exactly
     * the sort of cliff nobody remembers is coming.
     */
    const TOKEN_HELP = [
        '{#}'     => 'the number, padded to as many digits as you write hashes',
        '{YYYY}'  => 'four-digit year',
        '{YY}'    => 'two-digit year',
        '{MM}'    => 'two-digit month',
        '{DD}'    => 'two-digit day',
        '{TYPE}'  => "the ticket type's short code, if it has one",
    ];

    // ====================================================================
    //  Settings
    // ====================================================================

    /** @var array|null cached settings; a class property so forget() can clear it */
    private static $cache = null;

    public static function settings(PDO $conn): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $cache = self::DEFAULTS;
        try {
            $keys = array_keys(self::DEFAULTS);
            $ph   = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ({$ph})");
            $stmt->execute($keys);
            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                if ($v !== null && $v !== '') {
                    $cache[$k] = $v;
                }
            }
        } catch (Exception $e) {
            // No settings table yet — the defaults reproduce today's behaviour.
        }
        self::$cache = $cache;
        return self::$cache;
    }

    /** Forget the cached settings — after a save, and between tests. */
    public static function forget(): void
    {
        self::$cache    = null;
        self::$override = null;
    }

    /** @var array|null test/preview override, bypassing system_settings */
    private static $override = null;

    /** Run with a specific configuration — used by the live preview and tests. */
    public static function withSettings(?array $settings): void
    {
        self::$override = $settings;
    }

    private static function config(PDO $conn): array
    {
        return self::$override !== null
            ? array_merge(self::DEFAULTS, self::$override)
            : self::settings($conn);
    }

    // ====================================================================
    //  Making a number
    // ====================================================================

    /**
     * The next ticket number.
     *
     * @param ?int $ticketTypeId used by the per_type scope and {TYPE}
     * @param ?int $tenantId     used by the per_company scope
     */
    public static function next(PDO $conn, ?int $ticketTypeId = null, ?int $tenantId = null): string
    {
        $cfg = self::config($conn);

        if (($cfg['ticket_number_style'] ?? 'random') === 'random') {
            return self::randomNumber($conn);
        }

        // ⚠️ Ten attempts, then give up loudly. A sequential number can still
        // collide — two requests can read the same counter, and an install that
        // has just been renumbered may have gaps — so uniqueness is proven
        // against the table rather than assumed from the counter.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $seq    = self::claimNext($conn, $cfg, $ticketTypeId, $tenantId);
            $number = self::render($cfg['ticket_number_format'], $seq, $conn, $ticketTypeId);
            if (!self::inUse($conn, $number)) {
                return $number;
            }
        }
        throw new Exception('Could not generate a unique ticket number after 10 attempts.');
    }

    /** Today's format: three letters, three digits, five digits. */
    private static function randomNumber(PDO $conn): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $letters = chr(rand(65, 90)) . chr(rand(65, 90)) . chr(rand(65, 90));
            $number  = $letters . '-' . rand(100, 999) . '-'
                     . str_pad((string)rand(0, 99999), 5, '0', STR_PAD_LEFT);
            if (!self::inUse($conn, $number)) {
                return $number;
            }
        }
        throw new Exception('Could not generate a unique ticket number after 10 attempts.');
    }

    /**
     * Substitute the tokens.
     *
     * 🔑 `{####}` pads to at least four digits and NEVER truncates — the width
     * is a floor, so an install that outgrows it just gets longer numbers.
     */
    public static function render(string $format, int $seq, ?PDO $conn = null, ?int $ticketTypeId = null): string
    {
        $out = $format;

        // The number token, however many hashes it has.
        $out = preg_replace_callback('/\{(#+)\}/', static function ($m) use ($seq) {
            return str_pad((string)$seq, strlen($m[1]), '0', STR_PAD_LEFT);
        }, $out);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $out = str_replace(
            ['{YYYY}', '{YY}', '{MM}', '{DD}'],
            [$now->format('Y'), $now->format('y'), $now->format('m'), $now->format('d')],
            $out
        );

        if (strpos($out, '{TYPE}') !== false) {
            $out = str_replace('{TYPE}', self::typeCode($conn, $ticketTypeId), $out);
        }
        return $out;
    }

    /**
     * A short code for a ticket type, for {TYPE}.
     *
     * Derived from the name rather than stored: an install that never uses
     * {TYPE} should not have to fill in a column, and one that does gets
     * something sensible without configuring anything. "Incident" -> INC.
     */
    private static function typeCode(?PDO $conn, ?int $ticketTypeId): string
    {
        if (!$conn || !$ticketTypeId) {
            return '';
        }
        try {
            $stmt = $conn->prepare("SELECT name FROM ticket_types WHERE id = ?");
            $stmt->execute([$ticketTypeId]);
            $name = (string)$stmt->fetchColumn();
        } catch (Exception $e) {
            return '';
        }
        if ($name === '') {
            return '';
        }
        $clean = strtoupper(preg_replace('/[^A-Za-z]/', '', $name));
        return substr($clean, 0, 3);
    }

    /**
     * Take the next number from the right counter, atomically.
     *
     * ⚠️ ON DUPLICATE KEY UPDATE with LAST_INSERT_ID() is the trick that makes
     * this safe: the read and the increment are one statement, so two tickets
     * created in the same instant cannot take the same number. A SELECT then an
     * UPDATE would race, and the collision would only appear under load.
     */
    private static function claimNext(PDO $conn, array $cfg, ?int $ticketTypeId, ?int $tenantId): int
    {
        $key   = self::counterKey($cfg, $ticketTypeId, $tenantId);
        $start = max(1, (int)($cfg['ticket_number_start'] ?? 1));

        $stmt = $conn->prepare(
            "INSERT INTO ticket_number_counters (counter_key, next_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value + 1)"
        );
        $stmt->execute([$key, $start]);

        // ⚠️ rowCount() FIRST, not lastInsertId(). MySQL returns 1 for a fresh
        // INSERT and 2 when ON DUPLICATE KEY actually updated a row. On the
        // insert path lastInsertId() is meaningless here — the table has no
        // AUTO_INCREMENT, so it would be 0 and every first ticket of a counter
        // would be numbered zero.
        if ($stmt->rowCount() === 1) {
            return $start;          // first number this counter has ever issued
        }
        return (int)$conn->lastInsertId();   // LAST_INSERT_ID(next_value + 1)
    }

    /**
     * Which counter a ticket draws from.
     *
     * The reset period is part of the KEY rather than a stored date: a yearly
     * reset is simply a different counter each year, so nothing has to notice
     * midnight on the 31st of December or run a job to zero anything.
     */
    public static function counterKey(array $cfg, ?int $ticketTypeId, ?int $tenantId): string
    {
        $parts = ['t'];
        if (($cfg['ticket_number_scope'] ?? 'global') === 'per_type') {
            $parts[] = 'ty' . (int)$ticketTypeId;
        } elseif (($cfg['ticket_number_scope'] ?? 'global') === 'per_company') {
            $parts[] = 'co' . (int)$tenantId;
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if (($cfg['ticket_number_reset'] ?? 'never') === 'yearly') {
            $parts[] = $now->format('Y');
        } elseif (($cfg['ticket_number_reset'] ?? 'never') === 'monthly') {
            $parts[] = $now->format('Ym');
        }
        return implode(':', $parts);
    }

    // ====================================================================
    //  Recognising a number
    // ====================================================================

    /**
     * Is this number already taken — by a live ticket OR by history?
     *
     * 🔑 History counts. A number that once belonged to a renumbered ticket must
     * never be handed to a different one, or a reply to an old email would land
     * on somebody else's ticket. That is worse than not matching at all.
     */
    public static function inUse(PDO $conn, string $number): bool
    {
        $stmt = $conn->prepare("SELECT 1 FROM tickets WHERE ticket_number = ? LIMIT 1");
        $stmt->execute([$number]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }
        try {
            $stmt = $conn->prepare("SELECT 1 FROM ticket_number_history WHERE ticket_number = ? LIMIT 1");
            $stmt->execute([$number]);
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            return false;   // table not created yet
        }
    }

    /**
     * The ticket id for a number — current OR any it has ever had.
     *
     * This is what makes renumbering safe. It is the same principle merges
     * already use (`resolveMergedTicket`): an old identifier keeps working
     * forever, because the emails quoting it do.
     */
    public static function findTicketId(PDO $conn, string $number): ?int
    {
        $stmt = $conn->prepare("SELECT id FROM tickets WHERE ticket_number = ? LIMIT 1");
        $stmt->execute([$number]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
        try {
            $stmt = $conn->prepare("SELECT ticket_id FROM ticket_number_history WHERE ticket_number = ? LIMIT 1");
            $stmt->execute([$number]);
            $id = $stmt->fetchColumn();
            return $id === false ? null : (int)$id;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * The pattern that finds a reference in an email subject or body.
     *
     * ⚠️ DELIBERATELY FORMAT-AGNOSTIC. It captures whatever sits between
     * `SDREF:` and the closing bracket, and the DATABASE decides whether that is
     * a real ticket. Encoding a shape here is what would break every historical
     * email the day an install changed format — see this file's header.
     *
     * The character class is still bounded (no spaces, no brackets) so a
     * malformed subject cannot swallow the rest of the line.
     */
    const REF_PATTERN      = '/\[SDREF:\s*([A-Za-z0-9._\/-]{3,60})\s*\]/i';
    const REF_LINE_PATTERN = '/\[\*{3}\s*SDREF:\s*([A-Za-z0-9._\/-]{3,60})\s*REPLY ABOVE THIS LINE\s*\*{3}\]/i';

    // ====================================================================
    //  Preview
    // ====================================================================

    /**
     * What the next few numbers would look like under a given configuration,
     * without writing anything. Drives the live preview in settings.
     */
    public static function preview(array $cfg, int $count = 3): array
    {
        $cfg   = array_merge(self::DEFAULTS, $cfg);
        $start = max(1, (int)($cfg['ticket_number_start'] ?? 1));
        $out   = [];

        if (($cfg['ticket_number_style'] ?? 'random') === 'random') {
            for ($i = 0; $i < $count; $i++) {
                $out[] = chr(rand(65, 90)) . chr(rand(65, 90)) . chr(rand(65, 90)) . '-'
                       . rand(100, 999) . '-' . str_pad((string)rand(0, 99999), 5, '0', STR_PAD_LEFT);
            }
            return $out;
        }
        for ($i = 0; $i < $count; $i++) {
            $out[] = self::render($cfg['ticket_number_format'], $start + $i);
        }
        return $out;
    }

    /**
     * Sanity-check a format before it is saved.
     *
     * @return string[] problems, empty when the format is usable
     */
    public static function validateFormat(string $format): array
    {
        $problems = [];
        if (trim($format) === '') {
            $problems[] = 'The format cannot be empty.';
            return $problems;
        }
        if (!preg_match('/\{#+\}/', $format)) {
            // Without a number token every ticket gets the same reference.
            $problems[] = 'The format needs a number — add {######} where the digits should go.';
        }
        if (preg_match('/[\[\]]/', $format)) {
            // Square brackets would break the [SDREF:…] tag in email subjects.
            $problems[] = 'Square brackets cannot be used in a ticket number.';
        }
        if (preg_match('/\s/', $format)) {
            $problems[] = 'Spaces cannot be used in a ticket number.';
        }
        if (mb_strlen(self::render($format, 999999)) > 50) {
            // The column is VARCHAR(50).
            $problems[] = 'That format is too long — a ticket number can be at most 50 characters.';
        }
        return $problems;
    }
}
