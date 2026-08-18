<?php
/**
 * Mailbox health — everything that could be quietly wrong with a mailbox.
 *
 * Built for #79, where a mailbox collected mail perfectly well but stamped no
 * origin on the tickets it opened. Nothing was broken enough to error, so
 * nothing said anything: the mailbox list showed a green "Authenticated" badge
 * and the fault only surfaced weeks later, on the tickets.
 *
 * So the rule here is that a mailbox can be CONNECTED and still not be doing
 * what you think it is. Each check names one specific thing and says what the
 * consequence is, because "there is a problem" that doesn't say which problem
 * is barely better than silence.
 *
 * Severity:
 *   'error'   — mail is not being collected, or is being collected wrongly.
 *   'warning' — mail is collected, but something downstream is not set up.
 *
 * Pure function of a row already loaded by get_mailboxes.php (plus a couple of
 * looked-up extras), so it can't itself be the thing that breaks the list.
 */

/**
 * @param array $mb      A mailbox row as get_mailboxes.php has prepared it
 *                       (decrypted, typed, with auth_status already computed).
 * @param array $context 'origin_names'  => [id => name] of origins that exist
 *                       'multi_company' => bool, more than one company
 * @return array<int, array{key:string, severity:string, title:string, detail:string}>
 */
function mailboxHealthProblems(array $mb, array $context = []): array
{
    $problems     = [];
    $originNames  = $context['origin_names']  ?? [];
    $multiCompany = !empty($context['multi_company']);

    $add = static function (&$out, $key, $severity, $title, $detail) {
        $out[] = ['key' => $key, 'severity' => $severity, 'title' => $title, 'detail' => $detail];
    };

    // --- Is it even reading? ------------------------------------------------

    if (!empty($mb['decrypt_error'])) {
        $add($problems, 'decrypt', 'error',
            'Stored credentials cannot be read',
            'The saved settings for this mailbox could not be decrypted, so it cannot sign in. This usually means the encryption key changed or was lost. Re-enter the credentials.');
    }

    if (empty($mb['is_active'])) {
        $add($problems, 'inactive', 'error',
            'This mailbox is switched off',
            'It is set to inactive, so no mail is collected from it and no tickets are created. Nothing is lost — mail stays in the mailbox — but nothing is coming in either.');
    }

    $authStatus = $mb['auth_status'] ?? '';
    if ($authStatus === 'mismatch') {
        $add($problems, 'wrong_account', 'error',
            'Reading the wrong mailbox',
            'This mailbox is set to collect from ' . (string)($mb['target_mailbox'] ?? '?')
            . ' but the account that signed in is ' . (string)($mb['authenticated_as'] ?? '?')
            . '. Mail is being collected from the wrong inbox. Sign out and authenticate as the correct account.');
    } elseif ($authStatus === 'unauthenticated') {
        $add($problems, 'not_authenticated', 'error',
            'Never signed in',
            'This mailbox has no saved sign-in, so nothing is being collected from it yet. Use the authenticate button on the row.');
    } elseif ($authStatus === 'unverified') {
        $add($problems, 'unverified', 'warning',
            'Cannot confirm which account is signed in',
            'This mailbox signed in before FreeITSM started recording which account it was, so it cannot be checked against the address above. Signing out and back in confirms it.');
    }

    // Collected, but never actually run. A mailbox that has never been checked
    // is the normal state for thirty seconds and a fault after that, and the
    // list already shows "Never" without saying whether that matters.
    if (empty($mb['last_checked_datetime'])) {
        if (!empty($mb['is_active']) && $authStatus !== 'unauthenticated') {
            $add($problems, 'never_checked', 'warning',
                'Never checked for mail',
                'This mailbox is set up but has not been checked once. If the scheduled task that collects mail is not running, nothing will arrive from here.');
        }
    } else {
        // 24h is deliberately generous — this is meant to catch "the cron died",
        // not to nag an install that checks a low-traffic mailbox twice a day.
        $age = time() - strtotime((string)$mb['last_checked_datetime']);
        if ($age > 86400 && !empty($mb['is_active'])) {
            $days = max(1, (int) floor($age / 86400));
            $add($problems, 'stale', 'warning',
                'Not checked for ' . $days . ' day' . ($days === 1 ? '' : 's'),
                'The last check was ' . date('j M Y H:i', strtotime((string)$mb['last_checked_datetime']))
                . '. If mail is expected more often than that, the scheduled task may have stopped.');
        }
    }

    // --- Collected fine, but the tickets are wrong -------------------------

    // The #79 case. Not an error: a ticket without an origin is a working
    // ticket. It just cannot be reported on by where it came from, and that
    // fact is invisible until somebody tries.
    $originId = $mb['default_origin_id'] ?? null;
    if ($originId === null || $originId === '') {
        $add($problems, 'no_origin', 'warning',
            'No ticket origin set',
            'Tickets opened by this mailbox will not record where they came from, so they cannot be reported on by source. Set "Default ticket origin" in this mailbox\'s settings — Email for a helpdesk address, or something like Monitoring for an alerting one.');
    } elseif (!isset($originNames[(int)$originId])) {
        // The FK is ON DELETE SET NULL so this should not happen, but a restored
        // or hand-edited database can carry an id that points at nothing.
        $add($problems, 'origin_missing', 'error',
            'The ticket origin no longer exists',
            'This mailbox points at a ticket origin that has since been deleted, so no origin will be recorded. Pick a new one.');
    }

    // --- Set up half-way ---------------------------------------------------

    if (($mb['imported_action'] ?? '') === 'move_to_folder' && trim((string)($mb['imported_folder'] ?? '')) === '') {
        $add($problems, 'no_imported_folder', 'warning',
            'No folder chosen to move imported mail into',
            'This mailbox is set to move mail to a folder once imported, but no folder is named. Imported mail will be left where it is.');
    }

    if (($mb['provider'] ?? '') === 'imap' && trim((string)($mb['smtp_server'] ?? '')) === '') {
        $add($problems, 'no_smtp', 'warning',
            'No outgoing (SMTP) server',
            'Mail can be collected from this mailbox but replies cannot be sent from it.');
    }

    // Only worth saying on an install that HAS more than one company —
    // otherwise "shared intake" is the only thing it could possibly be.
    if ($multiCompany && ($mb['tenant_id'] ?? null) === null) {
        $add($problems, 'shared_intake', 'warning',
            'Not assigned to a company',
            'Tickets from this mailbox are routed by the sender\'s email domain, and any sender who does not match a known domain lands without a company. That is fine if this is a shared address — worth checking if it is not.');
    }

    return $problems;
}
