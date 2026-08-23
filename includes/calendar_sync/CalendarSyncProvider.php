<?php
/**
 * CalendarSyncProvider — the provider-agnostic contract for putting an analyst's
 * scheduled work into the calendar they actually live in (GH discussion #75).
 *
 * Everything above this line — which tickets sync, whose, what happens when one
 * is reassigned or unscheduled, the remote-id map, the per-analyst enrolment and
 * the settings UI — talks to this interface and never knows which calendar is
 * live. That is the same split as includes/integrations/IssueTrackerProvider.php
 * and includes/messaging/MessagingProvider.php, and it is deliberately copied
 * rather than reinvented.
 *
 * 🔑 THE REASON THIS IS AN INTERFACE AND NOT JUST GRAPH CODE. What varies
 * between providers is genuinely small: create, update and delete an event in a
 * named person's calendar. Everything difficult here — deciding whose calendar,
 * removing the old copy when a ticket moves from A to B, not orphaning events
 * when somebody opts out — is provider-independent. So keeping the door open for
 * Google (or CalDAV, or nothing at all) costs almost nothing, while a
 * Microsoft-shaped hole would have to be dug out again later.
 *
 * ⚠️ Not every install can use ANY of these. A shop with no supported provider
 * falls back to a subscribe (.ics) link, which needs no provider code at all —
 * so "we have not implemented your calendar" must never mean "you get nothing".
 *
 * Concrete providers are constructed with a DECRYPTED calendar_connections row
 * (credentials already a PHP array), exactly as IssueTrackerProvider is.
 *
 * ── The canonical event shape (what push() is given) ────────────────────────
 *
 *   [
 *     'subject'   => 'TICKET-000105 — Rebuild laptop',   // required
 *     'body'      => 'Requester: …\nStatus: …',          // plain text, may be ''
 *     'start'     => '2026-09-01 14:00:00',   // NAIVE wall clock — see below
 *     'end'       => '2026-09-01 16:00:00',
 *     'all_day'   => false,
 *     'timezone'  => 'Europe/London',         // the zone the naive values mean
 *     'url'       => 'https://…/tickets/?ticket_id=635',  // link back
 *   ]
 *
 * ⚠️ START AND END ARE NAIVE WALL-CLOCK, not UTC. Scheduling values in FreeITSM
 * are stored without a zone on purpose — "2pm" means 2pm to every analyst (see
 * assets/js/schedule.js). A provider MUST combine them with 'timezone' rather
 * than assuming UTC, or every event lands an hour out for half the year. This is
 * the single most likely way to get this integration subtly wrong.
 */

abstract class CalendarSyncProvider
{
    /** Capability keys for supports(). */
    const CAP_ALL_DAY       = 'all_day';        // real all-day events, not 00:00-23:59
    const CAP_BODY_HTML     = 'body_html';      // rich body rather than plain text
    const CAP_VERIFY_TARGET = 'verify_target';  // can answer "does this mailbox exist?"

    /** @var array decrypted calendar_connections row */
    protected $connection;

    public function __construct(array $connection)
    {
        $this->connection = $connection;
    }

    public function getProvider(): string
    {
        return $this->connection['provider'] ?? '';
    }

    /**
     * Does this provider do X?
     *
     * The settings screen needs to know BEFORE it renders a field — discovering
     * it from a thrown exception is the wrong way round. Same reasoning as
     * IssueTrackerProvider::supports().
     */
    public function supports(string $capability): bool
    {
        return false;
    }

    // ------------------------------------------------------------- outbound

    /**
     * Create an event in $calendarAddress's calendar. Returns the provider's id
     * for it, which the caller stores in calendar_sync_events.
     *
     * 🔴 THE RETURNED ID IS LOAD-BEARING. Without it there is no way to update
     * or remove the event later, and a reassigned ticket leaves a copy in the
     * old analyst's calendar with nothing pointing at it. A provider that cannot
     * return a durable id cannot be supported.
     */
    abstract public function createEvent(string $calendarAddress, array $event): string;

    /**
     * Update an event in place.
     *
     * Implementations SHOULD treat "the event is gone" as a recoverable state
     * and throw CalendarEventMissing, so the caller can create a fresh one
     * rather than the analyst silently losing the entry — somebody deleting it
     * from their own calendar is a normal thing to do, not an error.
     */
    abstract public function updateEvent(string $calendarAddress, string $remoteEventId, array $event): void;

    /**
     * Remove an event.
     *
     * An event that is ALREADY gone is a success, not a failure: the desired
     * end state is "not in their calendar", and that is satisfied. Treating it
     * as an error would leave the map row behind and retry forever.
     */
    abstract public function deleteEvent(string $calendarAddress, string $remoteEventId): void;

    // ------------------------------------------------------------- discovery

    /**
     * Does this address have a calendar we can write to?
     *
     * Drives the honest error on the analyst's own settings — an analyst whose
     * FreeITSM email is not their mailbox UPN must be told "we could not find a
     * mailbox for you", not left with a switch that appears on and never works.
     *
     * Providers that cannot answer should report CAP_VERIFY_TARGET as false;
     * the default assumes the address is fine rather than blocking enrolment on
     * a check the provider cannot perform.
     */
    public function verifyTarget(string $calendarAddress): bool
    {
        return true;
    }
}

/**
 * The event we were told to update is no longer there.
 *
 * Its own class because the caller's response is specific and correct: create a
 * replacement and re-point the map row. Someone deleting a FreeITSM event from
 * their own calendar is ordinary behaviour.
 */
class CalendarEventMissing extends Exception {}
