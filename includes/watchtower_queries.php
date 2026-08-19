<?php
/**
 * Shared Watchtower Dashboard Queries
 * Returns unified attention summary data from all modules
 */

require_once __DIR__ . '/tenancy.php';   // knowledgeTenantFilter() for the Knowledge card
require_once __DIR__ . '/watchtower_settings.php';   // which cards show, which statuses count

/**
 * $analystId is optional and only used to scope the Knowledge card to the
 * company the analyst has switched to. Omitted (or 0) = unscoped, which is the
 * behaviour every other card on this dashboard still has.
 */
function getWatchtowerData($conn, $analystId = 0) {
    $today = date('Y-m-d');

    // -- Morning Checks --

    $mcTotal = (int)$conn->query("SELECT COUNT(*) FROM morningChecks_Checks WHERE IsActive = 1")->fetchColumn();

    $mcDoneStmt = $conn->prepare(
        "SELECT COUNT(DISTINCT r.CheckID)
         FROM morningChecks_Results r
         JOIN morningChecks_Checks c ON r.CheckID = c.CheckID
         WHERE c.IsActive = 1 AND DATE(r.CheckDate) = ?"
    );
    $mcDoneStmt->execute([$today]);
    $mcDone = (int)$mcDoneStmt->fetchColumn();

    // One row per status that actually exists, with the label and colour the
    // admin configured. This used to group the denormalised label snapshot and
    // the card then looked up 'OK', 'Warning' and 'Fail' — none of which are
    // status names FreeITSM has ever shipped (the seeds are Green/Amber/Red), so
    // all three counts read zero for ever and "all checks passing" was shown on
    // mornings when every check was red. Statuses are user-editable anyway, so
    // no hardcoded name could have been right for long.
    $mcStatusStmt = $conn->prepare(
        "SELECT COALESCE(s.Label, r.Status) AS label,
                MAX(s.Colour)               AS colour,
                MIN(COALESCE(s.SortOrder, 9999)) AS sort_order,
                COUNT(*)                    AS cnt
         FROM morningChecks_Results r
         LEFT JOIN morningChecks_Statuses s ON s.StatusID = r.StatusID
         WHERE DATE(r.CheckDate) = ?
         GROUP BY COALESCE(s.Label, r.Status)
         ORDER BY sort_order, label"
    );
    $mcStatusStmt->execute([$today]);
    $mcStatuses = [];
    foreach ($mcStatusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (($row['label'] ?? '') === '') continue;
        $mcStatuses[] = [
            'label'      => $row['label'],
            'colour'     => $row['colour'] ?: null,
            'sort_order' => (int)$row['sort_order'],
            'count'      => (int)$row['cnt'],
        ];
    }

    // The most favourable status the admin has defined — the first one in their
    // own order. Nothing records which statuses are "good", so the card claims
    // all-clear only when every check sits in that first status, and says
    // "completed" rather than "passing" for anything else. Without this, a
    // morning where every check is red would still show a green light.
    $mcBestOrder = $conn->query("SELECT MIN(SortOrder) FROM morningChecks_Statuses WHERE IsActive = 1")->fetchColumn();
    $mcBestOrder = ($mcBestOrder === null || $mcBestOrder === false) ? null : (int)$mcBestOrder;

    // If the admin has said which statuses mean trouble, say so outright rather
    // than inferring it from the order. This is the one judgement the correctness
    // pass could not make on its own — nothing in the database records which of
    // your morning-check statuses is a pass and which is a failure.
    $mcAttention = wtItemMembers($conn, 'mc.attention');
    if ($mcAttention !== null) {
        $attLabels = [];
        if ($mcAttention) {
            $lbl = $conn->query("SELECT Label FROM morningChecks_Statuses WHERE StatusID IN " . wtIdListSql($mcAttention));
            $attLabels = array_flip($lbl->fetchAll(PDO::FETCH_COLUMN));
        }
        foreach ($mcStatuses as &$s) { $s['is_attention'] = isset($attLabels[$s['label']]); }
        unset($s);
    }

    $morningChecks = [
        'total_checks'    => $mcTotal,
        'completed_today' => $mcDone,
        // A LIST now, not a map keyed by English label.
        'statuses'        => $mcStatuses,
        'best_sort_order' => $mcBestOrder,
        // true = the admin has named which statuses mean trouble, so the card
        // uses that instead of falling back to "the first status is the good one".
        'attention_set'   => $mcAttention !== null,
        'not_started'     => $mcDone === 0 && $mcTotal > 0
    ];

    // -- Tickets --

    // Every open status, in the order the admin arranged them, with its own name
    // and colour. The query was always general — it was the card that picked out
    // 'Open', 'In Progress' and 'On Hold' by name and summed those three into the
    // "open tickets" headline, so a ticket in any other open status (Awaiting
    // Response, say — a status FreeITSM ships) was missing from the total.
    // A selection made in Watchtower → Settings narrows which statuses get their
    // own metric. No selection = all of them, which is the correct default and
    // the behaviour with the screen untouched. The TOTAL below deliberately
    // follows the same selection, so the headline always equals the numbers
    // printed beside it — a total that silently counted more than it showed is
    // the bug this replaced.
    $tkPicked = wtItemMembers($conn, 'tickets.by_status');
    $tkPickSql = $tkPicked === null ? '' : ' AND ts.id IN ' . wtIdListSql($tkPicked);
    $tkStatusStmt = $conn->query(
        "SELECT ts.id, ts.name AS status, ts.colour, COUNT(t.id) AS cnt
         FROM ticket_statuses ts
         LEFT JOIN tickets t ON t.status_id = ts.id
         WHERE ts.is_closed = 0 AND ts.is_active = 1{$tkPickSql}
         GROUP BY ts.id, ts.name, ts.colour, ts.display_order
         ORDER BY ts.display_order, ts.name"
    );
    $tkStatuses = [];
    $tkTotalOpen = 0;
    foreach ($tkStatusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tkStatuses[] = [
            'name'   => $row['status'],
            'colour' => $row['colour'] ?: null,
            'count'  => (int)$row['cnt'],
        ];
        $tkTotalOpen += (int)$row['cnt'];
    }

    // "Needs attention" = ranked ABOVE the default priority, rather than a
    // hardcoded Urgent/High/Critical. On stock data that is the same three, so
    // the number is unchanged — but it now survives renaming and translation,
    // and a priority somebody adds above Normal (an "Emergency") is counted
    // instead of being silently left out of the very tile meant to catch it.
    // An explicit choice in Watchtower → Settings wins; otherwise the rule above.
    $hpPicked = wtItemMembers($conn, 'tickets.high_priority');
    $hpWhere = $hpPicked === null
        ? "tp.display_order > COALESCE(
               (SELECT display_order FROM ticket_priorities WHERE is_default = 1 LIMIT 1),
               (SELECT MIN(display_order) FROM ticket_priorities))"
        : 'tp.id IN ' . wtIdListSql($hpPicked);
    $tkUrgent = (int)$conn->query(
        "SELECT COUNT(*)
         FROM tickets t
         JOIN ticket_priorities tp ON tp.id = t.priority_id
         JOIN ticket_statuses   ts ON ts.id = t.status_id
         WHERE ts.is_closed = 0 AND {$hpWhere}"
    )->fetchColumn();

    // The names behind that one number, so the card can say which priorities it
    // means instead of the label claiming "urgent/high" for ever regardless of
    // what was actually chosen.
    $hpNames = $conn->query(
        "SELECT name FROM ticket_priorities WHERE is_active = 1 AND " .
        ($hpPicked === null
            ? "display_order > COALESCE(
                   (SELECT display_order FROM ticket_priorities WHERE is_default = 1 LIMIT 1),
                   (SELECT MIN(display_order) FROM ticket_priorities))"
            : 'id IN ' . wtIdListSql($hpPicked)) .
        " ORDER BY display_order, name"
    )->fetchAll(PDO::FETCH_COLUMN);

    $tkUnassigned = (int)$conn->query(
        "SELECT COUNT(*)
         FROM tickets t
         JOIN ticket_statuses ts ON ts.id = t.status_id
         WHERE t.assigned_analyst_id IS NULL AND ts.is_closed = 0"
    )->fetchColumn();

    // Paused too long: tickets currently in a status flagged pauses_sla
    // (e.g. On Hold, Awaiting Response) where the last status change was
    // longer ago than the configured threshold. Surfaces tickets being
    // parked in a paused status to escape the SLA clock. Falls back to
    // tickets.created_datetime if no status-change audit row exists
    // (ticket has never moved off its default status).
    $pausedThresholdStmt = $conn->prepare(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'watchtower_paused_too_long_hours' LIMIT 1"
    );
    $pausedThresholdStmt->execute();
    $pausedThresholdHours = (int)($pausedThresholdStmt->fetchColumn() ?: 24);
    if ($pausedThresholdHours < 1) $pausedThresholdHours = 24;

    $tkPausedStmt = $conn->prepare(
        "SELECT COUNT(*)
           FROM tickets t
           JOIN ticket_statuses ts ON ts.id = t.status_id
          WHERE ts.is_closed = 0
            AND ts.pauses_sla = 1
            AND COALESCE(
                (SELECT MAX(a.created_datetime)
                   FROM ticket_audit a
                  WHERE a.ticket_id = t.id AND a.field_name = 'status'),
                t.created_datetime
            ) < DATE_SUB(NOW(), INTERVAL ? HOUR)"
    );
    $tkPausedStmt->execute([$pausedThresholdHours]);
    $tkPausedTooLong = (int)$tkPausedStmt->fetchColumn();

    $tickets = [
        // A LIST of every open status, plus the true total. Replaces the three
        // English names the card used to add up.
        'by_status'               => $tkStatuses,
        'total_open'              => $tkTotalOpen,
        'urgent_high'             => $tkUrgent,
        'high_priority_names'     => $hpNames,
        'unassigned'              => $tkUnassigned,
        'paused_too_long'         => $tkPausedTooLong,
        'paused_threshold_hours'  => $pausedThresholdHours,
    ];

    // -- Changes --
    // changes.status (legacy VARCHAR) was migrated to status_id → change_statuses.
    // Join the lookup and compare by name to preserve the original semantics.

    $chUpcoming = (int)$conn->query(
        "SELECT COUNT(*)
         FROM changes c
         JOIN change_statuses cs ON cs.id = c.status_id
         WHERE c.work_start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
           AND cs.is_closed = 0 AND cs.is_default = 0"
    )->fetchColumn();
    // Was NOT IN ('Closed','Cancelled') — and FreeITSM has never shipped a change
    // status called 'Closed'. Of the four finished statuses (Rejected, Completed,
    // Failed, Cancelled) that list caught exactly one, so completed and failed
    // changes were counted as upcoming work. is_closed is the fact it wanted.

    // Awaiting approval is a recorded fact, not a status name: not yet approved,
    // not finished, and no longer sitting in the status it started in. Reading it
    // from the approval itself means an extra approval stage added to the
    // workflow is counted without being listed in here.
    //
    // is_default = 0 is doing real work. A change still in its starting status is
    // a DRAFT — nobody has submitted it, so it is not waiting on anyone, and
    // putting it on an "awaiting approval" count would nag about something that
    // is nobody's turn. The first version of this counted drafts, which is how
    // the number went from 0 to 1 on a database with one unsubmitted draft in it.
    $chUnapproved = (int)$conn->query(
        "SELECT COUNT(*)
         FROM changes c
         JOIN change_statuses cs ON cs.id = c.status_id
         WHERE c.approval_datetime IS NULL AND cs.is_closed = 0 AND cs.is_default = 0"
    )->fetchColumn();

    // Work that is under way right now: the work window already says so exactly,
    // so the status name added nothing except a way to break. Same draft rule —
    // a change nobody has submitted is not being worked on, whatever dates
    // somebody pencilled into it.
    $chInProgress = (int)$conn->query(
        "SELECT COUNT(*)
         FROM changes c
         JOIN change_statuses cs ON cs.id = c.status_id
         WHERE cs.is_closed = 0 AND cs.is_default = 0
           AND c.work_start_datetime <= NOW()
           AND (c.work_end_datetime >= NOW() OR c.work_end_datetime IS NULL)"
    )->fetchColumn();

    // Changes broken down by status, the same as tickets and tasks. The three
    // figures above are DERIVED (a date window, an approval) rather than status
    // counts, so on their own the card could never answer "how many changes are
    // sitting at Submitted?" — the one question the other two cards do answer.
    // Drafts are included here: this is a count of what exists, not of what is
    // waiting on somebody.
    $chPicked = wtItemMembers($conn, 'changes.by_status');
    $chPickSql = $chPicked === null ? '' : ' AND cs.id IN ' . wtIdListSql($chPicked);
    $chStatusStmt = $conn->query(
        "SELECT cs.id, cs.name, cs.colour, COUNT(c.id) AS cnt
         FROM change_statuses cs
         LEFT JOIN changes c ON c.status_id = cs.id
         WHERE cs.is_closed = 0 AND cs.is_active = 1{$chPickSql}
         GROUP BY cs.id, cs.name, cs.colour, cs.display_order
         ORDER BY cs.display_order, cs.name"
    );
    $chStatuses = [];
    $chTotalOpen = 0;
    foreach ($chStatusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $chStatuses[] = [
            'name'   => $row['name'],
            'colour' => $row['colour'] ?: null,
            'count'  => (int)$row['cnt'],
        ];
        $chTotalOpen += (int)$row['cnt'];
    }

    // The gap between the two numbers above is the interesting one: a change
    // whose scheduled window has finished but which nobody moved on. It counted
    // towards the status breakdown and was excluded from "in its window now", so
    // it fell between the two and was the one thing on this card nobody could
    // see — on an attention dashboard, the overrunning change is the whole point.
    // Needs an end date: an open-ended window has not been overrun, it is just
    // open-ended.
    $chOverrunning = (int)$conn->query(
        "SELECT COUNT(*)
         FROM changes c
         JOIN change_statuses cs ON cs.id = c.status_id
         WHERE cs.is_closed = 0 AND cs.is_default = 0
           AND c.work_end_datetime IS NOT NULL
           AND c.work_end_datetime < NOW()"
    )->fetchColumn();

    $changes = [
        'upcoming_7d'       => $chUpcoming,
        'unapproved'        => $chUnapproved,
        'in_progress_today' => $chInProgress,
        'overrunning'       => $chOverrunning,
        'by_status'         => $chStatuses,
        'total_open'        => $chTotalOpen
    ];

    // -- Calendar --

    $calTodayStmt = $conn->query(
        "SELECT id, title, start_datetime, end_datetime, all_day
         FROM calendar_events
         WHERE DATE(start_datetime) = CURDATE()
            OR (all_day = 1 AND DATE(start_datetime) <= CURDATE() AND (DATE(end_datetime) >= CURDATE() OR end_datetime IS NULL))
         ORDER BY all_day DESC, start_datetime
         LIMIT 10"
    );
    $calTodayEvents = $calTodayStmt->fetchAll(PDO::FETCH_ASSOC);

    $calWeek = (int)$conn->query(
        "SELECT COUNT(*) FROM calendar_events
         WHERE start_datetime BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
    )->fetchColumn();

    $calendar = [
        'today_count'  => count($calTodayEvents),
        'today_events' => $calTodayEvents,
        'week_count'   => $calWeek
    ];

    // -- Service Status --

    // The impact level's own colour and its counts_as_downtime flag come back with
    // it. The card used to decide red-versus-amber, and pick a badge style, by
    // comparing the level's NAME against 'Major Outage', 'Partial Outage' and
    // 'Maintenance' — the same levels #70 made renameable, so a renamed or
    // translated total outage was drawn amber, in the mildest style, on the one
    // card meant to shout about it.
    // Which impact levels put a service on this card. Default: anything that is
    // not the "healthy" level. A choice in Watchtower → Settings narrows it, so
    // planned maintenance can be kept off an attention board without pretending
    // it is not happening.
    $ssPicked = wtItemMembers($conn, 'service.levels');
    $ssPickSql = $ssPicked === null ? 'il.is_default = 0' : 'il.id IN ' . wtIdListSql($ssPicked);

    $ssDegradedStmt = $conn->query(
        "SELECT ss.id, ss.name, worst.current_status, worst.severity_order,
                worst.colour, worst.counts_as_downtime, worst.level_id
         FROM status_services ss
         JOIN (
            SELECT sis.service_id, il.id AS level_id, il.name AS current_status,
                   il.severity_order, il.colour, il.counts_as_downtime
            FROM status_incident_services sis
            JOIN status_incidents si ON sis.incident_id = si.id
            JOIN service_impact_levels il ON il.id = sis.impact_level_id
            LEFT JOIN service_incident_statuses sst ON sst.id = si.status_id
            WHERE (sst.is_resolved = 0 OR sst.id IS NULL)
              AND {$ssPickSql}
         ) worst ON worst.service_id = ss.id
         WHERE ss.is_active = 1
         ORDER BY worst.severity_order ASC, ss.display_order, ss.name"
    );
    $ssDegraded = $ssDegradedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Which levels are serious enough to turn the light red. Decided HERE rather
    // than in the browser, and deliberately not read straight off the level's
    // counts_as_downtime flag: that flag decides whether time at this level
    // counts against your uptime percentage, which is a reporting fact. Reusing
    // it for a dashboard colour would mean an admin who wanted a degraded
    // service drawn amber had to distort their uptime figures to get it.
    $ssSerious = wtItemMembers($conn, 'service.serious');
    foreach ($ssDegraded as &$svc) {
        $svc['is_serious'] = $ssSerious === null
            ? ((int)$svc['counts_as_downtime'] === 1)
            : in_array((int)$svc['level_id'], $ssSerious, true);
    }
    unset($svc);

    $ssActiveIncidents = (int)$conn->query(
        "SELECT COUNT(*) FROM status_incidents si
         LEFT JOIN service_incident_statuses sst ON sst.id = si.status_id
         WHERE (sst.is_resolved = 0 OR sst.id IS NULL)"
    )->fetchColumn();

    $serviceStatus = [
        'degraded_services' => $ssDegraded,
        'active_incidents'  => $ssActiveIncidents,
        'all_operational'   => empty($ssDegraded) && $ssActiveIncidents === 0
    ];

    // -- Contracts --

    $ctExp30 = (int)$conn->query(
        "SELECT COUNT(*) FROM contracts
         WHERE is_active = 1 AND contract_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
    )->fetchColumn();

    $ctExp90 = (int)$conn->query(
        "SELECT COUNT(*) FROM contracts
         WHERE is_active = 1 AND contract_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)"
    )->fetchColumn();

    $ctNotice = (int)$conn->query(
        "SELECT COUNT(*) FROM contracts
         WHERE is_active = 1 AND notice_date IS NOT NULL
           AND notice_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
    )->fetchColumn();

    $contracts = [
        'expiring_30d'       => $ctExp30,
        'expiring_90d'       => $ctExp90,
        'notice_periods_30d' => $ctNotice
    ];

    // -- Knowledge --

    // Company scope. NOTE: Knowledge is currently the ONLY card here that scopes
    // by company — the rest of this dashboard (tickets included) is install-wide,
    // which is a pre-existing multi-tenancy gap in Watchtower rather than
    // something this introduced. Scoped here anyway: an article carries an owning
    // company now, so surfacing another company's titles on the dashboard would be
    // a hole in Knowledge, whatever the neighbouring cards do.
    $kbTenantSql    = '';
    $kbTenantParams = [];
    if ($analystId > 0 && function_exists('knowledgeTenantFilter')) {
        [$kbTenantSql, $kbTenantParams] = knowledgeTenantFilter($conn, $analystId, 'ka');
    }

    $kbRecentStmt = $conn->prepare(
        "SELECT ka.id, ka.title, ka.created_datetime
         FROM knowledge_articles ka
         WHERE ka.is_published = 1 AND ka.is_archived = 0
           AND ka.created_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
         . $kbTenantSql . "
         ORDER BY ka.created_datetime DESC
         LIMIT 5"
    );
    $kbRecentStmt->execute($kbTenantParams);
    $kbRecent = $kbRecentStmt->fetchAll(PDO::FETCH_ASSOC);

    $kbOverdueStmt = $conn->prepare(
        "SELECT COUNT(*) FROM knowledge_articles ka
         WHERE ka.is_published = 1 AND ka.is_archived = 0
           AND ka.next_review_date IS NOT NULL AND ka.next_review_date < CURDATE()"
         . $kbTenantSql
    );
    $kbOverdueStmt->execute($kbTenantParams);
    $kbOverdue = (int)$kbOverdueStmt->fetchColumn();

    $knowledge = [
        'recent_articles' => $kbRecent,
        'overdue_reviews' => $kbOverdue
    ];

    // -- Assets --

    $asTotal = (int)$conn->query("SELECT COUNT(*) FROM assets")->fetchColumn();

    $asNotSeen = (int)$conn->query(
        "SELECT COUNT(*) FROM assets
         WHERE last_seen IS NOT NULL AND last_seen < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetchColumn();

    // Warranty expiries — only surfaced here when the asset_warranty_surface
    // setting includes the dashboard. Counts assets already expired or expiring
    // within asset_warranty_days (default 30). Defensive: warranty_expiry may
    // not exist until a DB verification has run.
    $wtSurface = 'dashboard';
    $wtDays = 30;
    try {
        $set = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('asset_warranty_surface','asset_warranty_days')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($set['asset_warranty_surface'])) $wtSurface = $set['asset_warranty_surface'];
        if (!empty($set['asset_warranty_days']) && (int)$set['asset_warranty_days'] > 0) $wtDays = (int)$set['asset_warranty_days'];
    } catch (Exception $e) { /* defaults */ }
    $wtShowWarranty = in_array($wtSurface, ['dashboard', 'both'], true);
    $asWarranty = 0;
    if ($wtShowWarranty) {
        try {
            $w = $conn->prepare("SELECT COUNT(*) FROM assets WHERE warranty_expiry IS NOT NULL AND warranty_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)");
            $w->execute([$wtDays]);
            $asWarranty = (int)$w->fetchColumn();
        } catch (Exception $e) { $wtShowWarranty = false; }
    }

    $assets = [
        'total'         => $asTotal,
        'not_seen_7d'   => $asNotSeen,
        'warranty_soon' => $asWarranty,
        'warranty_days' => $wtDays,
        'warranty_show' => $wtShowWarranty
    ];

    // -- Tasks --

    $taskOverdue = (int)$conn->query(
        "SELECT COUNT(*) FROM tasks t
         LEFT JOIN task_statuses ts ON ts.id = t.status_id
         WHERE t.due_date < CURDATE()
           AND (ts.is_closed = 0 OR ts.id IS NULL)
           AND t.parent_task_id IS NULL"
    )->fetchColumn();

    $taskDueToday = (int)$conn->query(
        "SELECT COUNT(*) FROM tasks t
         LEFT JOIN task_statuses ts ON ts.id = t.status_id
         WHERE t.due_date = CURDATE()
           AND (ts.is_closed = 0 OR ts.id IS NULL)
           AND t.parent_task_id IS NULL"
    )->fetchColumn();

    // Every open task status with its own name and colour, instead of counting
    // the two called 'To Do' and 'In Progress' — which left tasks in any other
    // open status (Blocked, on stock data) off the dashboard altogether.
    $tkTaskPicked = wtItemMembers($conn, 'tasks.by_status');
    $tkTaskPickSql = $tkTaskPicked === null ? '' : ' AND ts.id IN ' . wtIdListSql($tkTaskPicked);
    $taskStatusStmt = $conn->query(
        "SELECT ts.id, ts.name, ts.colour, COUNT(t.id) AS cnt
         FROM task_statuses ts
         LEFT JOIN tasks t ON t.status_id = ts.id AND t.parent_task_id IS NULL
         WHERE ts.is_closed = 0 AND ts.is_active = 1{$tkTaskPickSql}
         GROUP BY ts.id, ts.name, ts.colour, ts.display_order
         ORDER BY ts.display_order, ts.name"
    );
    $taskStatuses = [];
    $taskTotalOpen = 0;
    foreach ($taskStatusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $taskStatuses[] = [
            'name'   => $row['name'],
            'colour' => $row['colour'] ?: null,
            'count'  => (int)$row['cnt'],
        ];
        $taskTotalOpen += (int)$row['cnt'];
    }

    $tasksWt = [
        'overdue'     => $taskOverdue,
        'due_today'   => $taskDueToday,
        'by_status'   => $taskStatuses,
        'total_open'  => $taskTotalOpen
    ];

    // -- Workflows --
    // A failing workflow is silent by design: the engine swallows its own errors
    // so a broken rule can never break the ticket save that triggered it. Which
    // is correct — and means nothing tells you it's broken. This card is that
    // "something".
    //
    // Real runs only (is_dry_run = 0): a dry run is a person testing, and its
    // failures are expected, not an incident.
    $wf = [
        'failed_24h'      => 0,
        'aborted_24h'     => 0,
        'dead_webhooks'   => 0,
        'worst'           => [],   // the workflows failing most, so the card names names
        'all_clear'       => true,
        'available'       => false,
    ];
    try {
        $wfFailed = $conn->query(
            "SELECT COUNT(*) FROM workflow_executions
              WHERE status = 'failed' AND is_dry_run = 0
                AND started_datetime >= UTC_TIMESTAMP() - INTERVAL 24 HOUR"
        )->fetchColumn();
        $wfAborted = $conn->query(
            "SELECT COUNT(*) FROM workflow_executions
              WHERE status = 'aborted' AND is_dry_run = 0
                AND started_datetime >= UTC_TIMESTAMP() - INTERVAL 24 HOUR"
        )->fetchColumn();

        // Which workflows, and what they're actually saying — an error message is
        // far more useful on the dashboard than a bare count.
        $wfWorst = $conn->query(
            "SELECT COALESCE(w.name, e.workflow_name, '(deleted workflow)') AS name,
                    COUNT(*) AS failures,
                    SUBSTRING_INDEX(GROUP_CONCAT(e.error_message ORDER BY e.id DESC SEPARATOR '||'), '||', 1) AS last_error
               FROM workflow_executions e
               LEFT JOIN workflows w ON w.id = e.workflow_id
              WHERE e.status IN ('failed','aborted') AND e.is_dry_run = 0
                AND e.started_datetime >= UTC_TIMESTAMP() - INTERVAL 24 HOUR
              GROUP BY name
              ORDER BY failures DESC
              LIMIT 3"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Dead-lettered webhooks belong here too: the workflow itself "succeeded"
        // (it queued the send), so nothing else would ever surface the fact that
        // the message never arrived.
        $wfDead = 0;
        try {
            $wfDead = (int)$conn->query(
                "SELECT COUNT(*) FROM webhook_deliveries
                  WHERE status = 'dead'
                    AND updated_datetime >= UTC_TIMESTAMP() - INTERVAL 24 HOUR"
            )->fetchColumn();
        } catch (Exception $ignore) { /* table may not exist yet */ }

        $wf = [
            'failed_24h'    => (int)$wfFailed,
            'aborted_24h'   => (int)$wfAborted,
            'dead_webhooks' => $wfDead,
            'worst'         => array_map(fn($r) => [
                'name'       => $r['name'],
                'failures'   => (int)$r['failures'],
                'last_error' => $r['last_error'],
            ], $wfWorst),
            'all_clear'     => ((int)$wfFailed + (int)$wfAborted + $wfDead) === 0,
            'available'     => true,
        ];
    } catch (Exception $e) {
        // workflow_executions missing (pre-Database-Verify) — show nothing rather
        // than breaking the whole dashboard for every other module.
        $wf['available'] = false;
    }

    return [
        'morning_checks' => $morningChecks,
        'tickets'        => $tickets,
        'changes'        => $changes,
        'calendar'       => $calendar,
        'service_status' => $serviceStatus,
        'contracts'      => $contracts,
        'knowledge'      => $knowledge,
        'assets'         => $assets,
        'tasks'          => $tasksWt,
        'workflows'      => $wf,
        // Which cards this installation wants on screen. Every card is visible
        // unless somebody has said otherwise, so an install that never opens
        // Watchtower → Settings sees exactly what it saw before.
        'cards'          => wtVisibleCards($conn)
    ];
}
