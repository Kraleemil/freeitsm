<?php
/**
 * Calendar sync — loading connections, choosing a provider, and reading an
 * analyst's enrolment (GH discussion #75).
 *
 * The core of the feature lives above the provider contract in
 * CalendarSyncProvider.php: this file is the plumbing that gets a usable
 * provider object and answers "does this analyst want their scheduled work in
 * their calendar, and where".
 *
 * Credential handling deliberately mirrors includes/integrations/integrations.php
 * — an encrypted JSON blob in a `credentials` column, decoded on load, and never
 * returned to a UI. One convention for secrets, not two.
 */

require_once __DIR__ . '/CalendarSyncProvider.php';
require_once __DIR__ . '/../encryption.php';

/** Modes an analyst can be in. ONE of these, never a combination — see below. */
const CALENDAR_MODE_OFF  = 'off';
const CALENDAR_MODE_PUSH = 'push';   // real events written into their calendar
const CALENDAR_MODE_FEED = 'feed';   // a subscribe (.ics) link they add themselves

/**
 * 🔑 WHY MODE IS ONE VALUE AND NOT TWO BOOLEANS.
 *
 * With a push AND a subscribed feed both live, every scheduled ticket appears
 * TWICE in the same calendar — once as a real event and once from the
 * subscription. Independent switches make that trivially easy to do by accident
 * and baffling to diagnose. A single choice makes it unrepresentable.
 */
function calendarModeIsValid(string $mode): bool
{
    return in_array($mode, [CALENDAR_MODE_OFF, CALENDAR_MODE_PUSH, CALENDAR_MODE_FEED], true);
}

/** Decrypt + JSON-decode a stored credentials blob (never throws). */
function calendarSyncDecodeCredentials($stored): array
{
    if ($stored === null || $stored === '') return [];
    try {
        $plain = decryptValue($stored);
    } catch (Exception $e) {
        return [];
    }
    $decoded = json_decode((string) $plain, true);
    return is_array($decoded) ? $decoded : [];
}

/** Encrypt a credentials array for storage. */
function calendarSyncEncodeCredentials(array $credentials): string
{
    return encryptValue(json_encode($credentials));
}

/**
 * Does this database have the calendar-sync tables yet?
 *
 * Same guard, and the same reasoning, as scheduleSchemaReady(): an install that
 * has pulled this code but not run Database Verification must simply not offer
 * the feature, rather than throwing "table doesn't exist" at somebody trying to
 * schedule a ticket. The push hangs off the schedule write path, which is
 * everyday work and must never be taken down by an unrelated missing table.
 */
function calendarSyncSchemaReady(PDO $conn): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $ready = (bool)$conn->query("SHOW TABLES LIKE 'calendar_enrolments'")->fetch(PDO::FETCH_NUM);
    } catch (Exception $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * Load a connection with its secrets decrypted, or null.
 *
 * A connection may BORROW an Azure app registration already configured for a
 * mailbox (calendar_connections.mailbox_id) instead of carrying its own —
 * most installs have already done that setup for mail, and adding one Graph
 * permission to an existing app is far less work than registering a second one.
 * Resolving that here means providers never have to know about it.
 */
function calendarSyncLoadConnection(PDO $conn, int $connectionId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM calendar_connections WHERE id = ? AND is_active = 1");
    $stmt->execute([$connectionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $row['credentials'] = calendarSyncDecodeCredentials($row['credentials'] ?? null);

    if (empty($row['credentials']) && !empty($row['mailbox_id'])) {
        $mStmt = $conn->prepare("SELECT * FROM target_mailboxes WHERE id = ? AND is_active = 1");
        $mStmt->execute([(int)$row['mailbox_id']]);
        $mailbox = $mStmt->fetch(PDO::FETCH_ASSOC);
        if ($mailbox) {
            $mailbox = decryptMailboxRow($mailbox);
            $row['credentials'] = [
                'tenant_id'     => $mailbox['azure_tenant_id']     ?? '',
                'client_id'     => $mailbox['azure_client_id']     ?? '',
                'client_secret' => $mailbox['azure_client_secret'] ?? '',
            ];
            $row['borrowed_from_mailbox'] = $mailbox['name'] ?? '';
        }
    }

    return $row;
}

/** The single active connection, or null when nobody has configured one. */
function calendarSyncActiveConnection(PDO $conn): ?array
{
    if (!calendarSyncSchemaReady($conn)) return null;
    try {
        $id = $conn->query("SELECT id FROM calendar_connections WHERE is_active = 1 ORDER BY id LIMIT 1")
                   ->fetchColumn();
    } catch (Exception $e) {
        return null;
    }
    return $id ? calendarSyncLoadConnection($conn, (int)$id) : null;
}

/**
 * Build the provider for a connection.
 *
 * ⚠️ Throws on an unknown provider rather than returning null. A connection row
 * naming a provider this build does not have is a misconfiguration, and pushing
 * silently to nowhere is the worst possible response to it.
 */
function calendarSyncProviderFor(array $connection): CalendarSyncProvider
{
    switch ($connection['provider'] ?? '') {
        case 'microsoft':
            require_once __DIR__ . '/MicrosoftCalendarProvider.php';
            return new MicrosoftCalendarProvider($connection);
        default:
            throw new Exception('Unknown calendar provider: ' . ($connection['provider'] ?? '?'));
    }
}

/**
 * One analyst's enrolment, or a synthetic "off" row when they have never chosen.
 *
 * ⚠️ NEVER returns null for a real analyst. A missing row means "has not decided
 * yet", which is off — and a caller forced to distinguish those two would sooner
 * or later treat one as the other.
 *
 * calendar_address falls back to analysts.email, which is what it is for nearly
 * everyone; the column exists for the analyst whose login address is not their
 * mailbox.
 */
function calendarSyncEnrolment(PDO $conn, int $analystId): array
{
    // 🔴 THE ADDRESS IS RESOLVED FOR THE "never chose" CASE TOO, which is
    // everyone until they opt in. Deriving it only when an enrolment row already
    // existed left the settings screen with nothing to show as "we will write
    // to…" for every analyst who had not enrolled yet — i.e. all of them, at
    // exactly the moment they are deciding whether to.
    $email = null;
    try {
        $aStmt = $conn->prepare("SELECT email FROM analysts WHERE id = ?");
        $aStmt->execute([$analystId]);
        $email = $aStmt->fetchColumn() ?: null;
    } catch (Exception $e) {
        // Falls through as null — an unknown address is reported honestly rather
        // than the caller being handed a guess.
    }

    $off = [
        'analyst_id'       => $analystId,
        'mode'             => CALENDAR_MODE_OFF,
        'connection_id'    => null,
        'calendar_address' => $email,
        'last_error'       => null,
    ];
    if (!calendarSyncSchemaReady($conn)) return $off;

    try {
        $stmt = $conn->prepare("SELECT * FROM calendar_enrolments WHERE analyst_id = ?");
        $stmt->execute([$analystId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return $off;
    }
    if (!$row) return $off;

    if (empty($row['calendar_address'])) {
        $row['calendar_address'] = $email;
    }
    if (!calendarModeIsValid((string)$row['mode'])) {
        $row['mode'] = CALENDAR_MODE_OFF;      // a value we do not recognise is not a licence to push
    }
    return $row;
}
