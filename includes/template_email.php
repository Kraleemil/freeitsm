<?php
/**
 * Email Template Engine
 *
 * Sends automated emails triggered by ticket events (new ticket, assigned, closed).
 * Templates are configured in Tickets > Settings > Templates.
 */

require_once __DIR__ . '/encryption.php';
require_once __DIR__ . '/email_log.php';

/**
 * Main entry point — send a template email for a ticket event.
 * Returns silently if no active template exists or no mailbox is found.
 * Never throws — errors go to error_log().
 *
 * $extraMergeData allows callers (e.g. the CSAT flow) to inject additional
 * placeholders like `csat_link` that aren't derivable from the ticket alone.
 */
function sendTemplateEmail(PDO $conn, int $ticketId, string $eventTrigger, array $extraMergeData = []): void {
    try {
        $template = getActiveTemplate($conn, $eventTrigger);
        if (!$template) {
            return; // No active template for this event
        }

        $mergeData = buildTicketMergeData($conn, $ticketId);
        if (!$mergeData) {
            error_log("Template email: could not build merge data for ticket $ticketId");
            return;
        }

        // Caller-supplied merge codes win — e.g. csat_link, which needs a freshly
        // minted response row before the link can be built
        $mergeData = array_merge($mergeData, $extraMergeData);

        // Resolve merge codes in subject and body
        $subject = resolveMergeCodes($template['subject_template'], $mergeData);
        $body = resolveMergeCodes($template['body_template'], $mergeData);

        // Get the mailbox for this ticket
        $mailbox = templateGetMailboxForTicket($conn, $ticketId);
        if (!$mailbox) {
            return; // Manual ticket or no mailbox — skip silently
        }

        $provider = $mailbox['provider'] ?? 'microsoft';
        $accessToken = null;
        $graphBase = '/me';
        if ($provider === 'imap') {
            // Basic IMAP sends via SMTP — no OAuth token to validate/refresh.
            require_once __DIR__ . '/mailbox_imap.php';
        } elseif ($provider === 'google') {
            if (empty($mailbox['token_data'])) {
                error_log("Template email: mailbox {$mailbox['id']} has no token data");
                return;
            }
            $cleanedTokenData = preg_replace('/[\x00-\x1F\x7F]/', '', $mailbox['token_data']);
            $tokenData = json_decode($cleanedTokenData, true);
            if (!$tokenData || !isset($tokenData['access_token'])) {
                error_log("Template email: invalid token data for mailbox {$mailbox['id']}");
                return;
            }
            require_once __DIR__ . '/gmail.php';
            $accessToken = gmailGetValidAccessToken($conn, $mailbox, $tokenData);
            if (!$accessToken) {
                error_log("Template email: failed to get access token for mailbox {$mailbox['id']}");
                emailLogFailed($conn, $mailbox, 'template', $mergeData['requester_email'] ?? '',
                    $subject, 'Could not obtain an access token for this mailbox', $ticketId);
                return;
            }
        } else {
            // Microsoft: token source AND endpoint both depend on auth_mode, so don't
            // test for stored token_data here — an app-only mailbox legitimately has
            // none until it first mints one.
            $graph = templateGraphContext($conn, $mailbox);
            if (!$graph) {
                error_log("Template email: failed to get access token for mailbox {$mailbox['id']}");
                emailLogFailed($conn, $mailbox, 'template', $mergeData['requester_email'] ?? '',
                    $subject, 'Could not obtain an access token for this mailbox '
                    . '(check the mailbox is authenticated, and that its authentication mode matches its stored token)',
                    $ticketId);
                return;
            }
            $accessToken = $graph['token'];
            $graphBase   = $graph['base'];
        }

        // Get recipient (the ticket requester)
        $recipientEmail = $mergeData['requester_email'] ?? '';
        if (empty($recipientEmail)) {
            error_log("Template email: no requester email for ticket $ticketId");
            return;
        }

        $ticketNumber = $mergeData['ticket_reference'] ?? '';

        // Build subject with SDREF for threading
        $fullSubject = "[SDREF:$ticketNumber] $subject";

        // Build HTML body with reply marker
        $fullBody = buildTemplateEmailBody($body, $ticketNumber);

        // Send via appropriate API
        if ($provider === 'imap') {
            imapSmtpSend($mailbox, $recipientEmail, '', $fullSubject, $fullBody);
        } elseif ($provider === 'google') {
            $fromAddress = $mailbox['target_mailbox'] ?? '';
            gmailSendEmail($accessToken, $recipientEmail, $fullSubject, $fullBody, $fromAddress);
        } else {
            $message = [
                'message' => [
                    'subject' => $fullSubject,
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => $fullBody
                    ],
                    'toRecipients' => [
                        ['emailAddress' => ['address' => $recipientEmail]]
                    ]
                ],
                'saveToSentItems' => true
            ];
            templateSendViaGraph($accessToken, $message, $graphBase);
        }

        emailLogSent($conn, $mailbox, 'template', $recipientEmail, $fullSubject, $ticketId);

        // Save to emails table
        templateSaveSentEmail($conn, $ticketId, $mailbox, $recipientEmail, $fullSubject, $body);

    } catch (Exception $e) {
        error_log("Template email error ($eventTrigger, ticket $ticketId): " . $e->getMessage());
        // The error log alone is what made this class of failure invisible: an
        // acknowledgement that silently never sends looks identical to one nobody
        // happened to trigger.
        emailLogFailed(
            $conn, $mailbox ?? null, 'template',
            $recipientEmail ?? '', $fullSubject ?? '', $e->getMessage(), $ticketId
        );
    }
}

/**
 * Get the first active template for a given event trigger.
 */
function getActiveTemplate(PDO $conn, string $eventTrigger): ?array {
    $sql = "SELECT * FROM ticket_email_templates
            WHERE event_trigger = ? AND is_active = 1
            ORDER BY display_order ASC, id ASC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$eventTrigger]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    return $template ?: null;
}

/**
 * Build merge data from ticket, analyst, and department tables.
 */
function buildTicketMergeData(PDO $conn, int $ticketId): ?array {
    $sql = "SELECT t.ticket_number, t.subject, ts.name AS status, tp.name AS priority,
                   COALESCE(u.display_name, u.email) AS requester_name,
                   u.email AS requester_email,
                   t.created_datetime, t.closed_datetime,
                   COALESCE(o.full_name, a.full_name) AS analyst_name,
                   COALESCE(o.email, a.email) AS analyst_email,
                   d.name AS department_name
            FROM tickets t
            LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
            LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN analysts a ON t.assigned_analyst_id = a.id
            LEFT JOIN analysts o ON t.owner_id = o.id
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return [
        'ticket_reference' => $row['ticket_number'] ?? '',
        'ticket_subject' => $row['subject'] ?? '',
        'ticket_status' => $row['status'] ?? '',
        'ticket_priority' => $row['priority'] ?? '',
        'requester_name' => $row['requester_name'] ?? '',
        // First word of the requester's name — for friendlier greetings ("Dear Ed").
        'requester_first_name' => trim(explode(' ', trim($row['requester_name'] ?? ''))[0]),
        'requester_email' => $row['requester_email'] ?? '',
        'analyst_name' => $row['analyst_name'] ?? '',
        'analyst_email' => $row['analyst_email'] ?? '',
        'department_name' => $row['department_name'] ?? '',
        'created_date' => $row['created_datetime'] ? date('d M Y H:i', strtotime($row['created_datetime'])) : '',
        'closed_date' => $row['closed_datetime'] ? date('d M Y H:i', strtotime($row['closed_datetime'])) : '',
    ];
}

/**
 * Replace [merge_code] placeholders with actual values.
 */
function resolveMergeCodes(string $template, array $mergeData): string {
    foreach ($mergeData as $code => $value) {
        $template = str_replace("[$code]", $value, $template);
    }
    return $template;
}

/**
 * Build the full HTML body with reply marker for threading.
 */
function buildTemplateEmailBody(string $bodyContent, string $ticketNumber): string {
    // Convert newlines to <br> if the body appears to be plain text (no HTML tags)
    if (strip_tags($bodyContent) === $bodyContent) {
        $bodyContent = nl2br(htmlspecialchars($bodyContent, ENT_QUOTES, 'UTF-8'));
    }

    return '<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">'
        . $bodyContent
        . '</div>'
        . '<div style="border-top: 1px solid #ccc; padding: 10px 0; margin: 20px 0; color: #999; font-size: 12px; text-align: center;" data-reply-marker="true">'
        . '&mdash; Please reply above this line &mdash;'
        . '</div>'
        . '<div style="display: none;">[*** SDREF:' . $ticketNumber . ' REPLY ABOVE THIS LINE ***]</div>';
}

// ---------------------------------------------------------------
// Graph API helpers (self-contained to avoid conflicts with
// send_email.php which defines the same functions)
// ---------------------------------------------------------------

/**
 * Get the mailbox associated with a ticket's emails.
 */
function templateGetMailboxForTicket(PDO $conn, int $ticketId): ?array {
    $sql = "SELECT tm.*
            FROM emails e
            INNER JOIN target_mailboxes tm ON e.mailbox_id = tm.id
            WHERE e.ticket_id = ?
            ORDER BY e.is_initial DESC, e.received_datetime ASC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$ticketId]);
    $mailbox = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($mailbox) {
        $mailbox = decryptMailboxRow($mailbox);
    }

    return $mailbox ?: null;
}

/**
 * Get a valid access token, refreshing if expired.
 */
function templateGetValidAccessToken(PDO $conn, array $mailbox, array $tokenData): ?string {
    if (isset($tokenData['expires_at']) && $tokenData['expires_at'] < (time() + 300)) {
        if (!isset($tokenData['refresh_token'])) {
            return null;
        }

        $tokenUrl = 'https://login.microsoftonline.com/' . $mailbox['azure_tenant_id'] . '/oauth2/v2.0/token';
        $postData = [
            'client_id' => $mailbox['azure_client_id'],
            'client_secret' => $mailbox['azure_client_secret'],
            'refresh_token' => $tokenData['refresh_token'],
            'grant_type' => 'refresh_token',
            'scope' => $mailbox['oauth_scopes']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        sslApplyCurl($ch);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $newToken = json_decode($response, true);
        if (!isset($newToken['access_token'])) {
            return null;
        }

        $tokenData['access_token'] = $newToken['access_token'];
        $tokenData['refresh_token'] = $newToken['refresh_token'] ?? $tokenData['refresh_token'];
        $tokenData['expires_at'] = time() + ($newToken['expires_in'] ?? 3600);

        // Save refreshed token
        $saveSql = "UPDATE target_mailboxes SET token_data = ? WHERE id = ?";
        $saveStmt = $conn->prepare($saveSql);
        // Encrypted at rest — see ENCRYPTED_MAILBOX_COLUMNS; decryptMailboxRow() reverses it.
        $saveStmt->execute([encryptValue(json_encode($tokenData)), $mailbox['id']]);
    }

    return $tokenData['access_token'];
}

/**
 * Everything a Microsoft send needs for one mailbox: a valid access token and the
 * Graph base path to send from. Returns null if no usable token could be obtained.
 *
 * The two auth modes differ in BOTH halves, which is what made issue #67 subtle:
 *
 *   delegated : a user signed in. token_data carries a refresh_token, and calls go
 *               to /me — Graph resolves "me" from the user inside the token.
 *   app_only  : client credentials. There is no user, so /me is meaningless and Graph
 *               rejects it with "/me request is only valid with delegated
 *               authentication flow" (HTTP 400). Calls go to /users/<target>, and
 *               there is no refresh_token either — the token is re-minted from the
 *               client secret.
 *
 * Correcting only the path leaves a slower bug behind: templateGetValidAccessToken()
 * returns null for an app-only mailbox the moment its cached token expires, because it
 * looks for a refresh_token that client credentials never issue. That failure is masked
 * in normal use because the mail poller re-mints the cached token as a side effect of
 * reading — so sends work until polling stops or an hour passes, then fail with a
 * misleading "failed to get access token".
 */
function templateGraphContext(PDO $conn, array $mailbox): ?array {
    require_once __DIR__ . '/mailbox_graph.php';

    if (mailboxIsAppOnly($mailbox)) {
        try {
            // No refresh token exists for client credentials — mint (or reuse the
            // cached) token. Throws on a bad secret / consent problem; that must not
            // escape into callers that only expect a null.
            $token = mailboxAppOnlyToken($conn, $mailbox);
        } catch (Exception $e) {
            error_log('Graph app-only token failed for mailbox '
                . ($mailbox['id'] ?? '?') . ': ' . $e->getMessage());
            return null;
        }
    } else {
        $cleaned   = preg_replace('/[\x00-\x1F\x7F]/', '', (string)($mailbox['token_data'] ?? ''));
        $tokenData = $cleaned !== '' ? json_decode($cleaned, true) : null;
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return null;
        }
        // A token minted for app-only is NOT usable here. Switching a mailbox from
        // app-only back to delegated used to leave the old token in place, and it is
        // refused at /me with the very error this function exists to prevent — while
        // looking like the original bug had come back. Refuse it and make the mailbox
        // report itself unauthenticated, which prompts a real sign-in.
        if (!empty($tokenData['app_only'])) {
            error_log('Graph: mailbox ' . ($mailbox['id'] ?? '?')
                . ' is delegated but holds an app-only token — re-authentication needed');
            return null;
        }
        $token = templateGetValidAccessToken($conn, $mailbox, $tokenData);
    }

    return $token ? ['token' => $token, 'base' => mailboxResolveGraphBase($mailbox)] : null;
}

/**
 * Send an email message via Microsoft Graph API.
 *
 * $graphBase is '/me' (delegated) or '/users/<addr>' (app-only) — get it from
 * templateGraphContext(). It is deliberately REQUIRED rather than defaulting to '/me':
 * a default would reproduce exactly the silent wrong-endpoint bug this argument exists
 * to fix, and a missed caller should fail loudly at development time instead of quietly
 * sending from the wrong mailbox.
 */
function templateSendViaGraph(string $accessToken, array $message, string $graphBase): void {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://graph.microsoft.com/v1.0' . $graphBase . '/sendMail');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    sslApplyCurl($ch);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL error: ' . $error);
    }

    curl_close($ch);

    if ($httpCode !== 202 && $httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
        throw new Exception("Graph API send failed: $errorMessage (HTTP $httpCode)");
    }
}

/**
 * Save the sent template email to the emails table.
 */
function templateSaveSentEmail(PDO $conn, int $ticketId, array $mailbox, string $to, string $subject, string $body): void {
    try {
        $sql = "INSERT INTO emails (
            subject, from_address, from_name, to_recipients,
            received_datetime, body_content, body_type, ticket_id, is_initial, direction, mailbox_id
        ) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), ?, 'html', ?, 0, 'Outbound', ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $subject,
            $mailbox['target_mailbox'] ?? '',
            $mailbox['name'] ?? 'Service Desk',
            $to,
            $body,
            $ticketId,
            $mailbox['id']
        ]);

        // Update ticket's updated_datetime
        $updateSql = "UPDATE tickets SET updated_datetime = UTC_TIMESTAMP() WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$ticketId]);
    } catch (Exception $e) {
        error_log('Template email: failed to save sent email: ' . $e->getMessage());
    }
}
