<?php
/**
 * API: an analyst's OWN calendar choice (GH #75).
 *
 *   GET            -> my mode, the mailbox my work would go to, and what is on offer
 *   POST mode=off|push|feed
 *
 * 🔴 THIS ENDPOINT CANNOT SET AN ADDRESS. Only mode. The application permission
 * behind the push can write to any mailbox in the tenant, so letting an analyst
 * name their own target would let them fill a colleague's calendar with their
 * tickets. Where it goes is an administrator's decision (System → Calendar
 * sync); whether it happens at all is the analyst's.
 *
 * 🔑 ONE MODE, NOT TWO SWITCHES. With a push and a subscribed feed both live you
 * see every scheduled ticket twice — once as a real event, once from the
 * subscription. A single value makes that unrepresentable.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/calendar_sync/calendar_sync.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('tickets');

$analystId = (int)$_SESSION['analyst_id'];

try {
    $conn = connectToDatabase();

    if (!calendarSyncSchemaReady($conn)) {
        // Not an error the analyst can do anything about — report it as "nothing
        // on offer" rather than a failure, and the screen simply shows less.
        echo json_encode([
            'success' => true, 'mode' => CALENDAR_MODE_OFF,
            'push_available' => false, 'feed_available' => false,
            'reason' => 'needs_db',
        ]);
        exit;
    }

    $enrolment  = calendarSyncEnrolment($conn, $analystId);
    $connection = calendarSyncActiveConnection($conn);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success'        => true,
            'mode'           => $enrolment['mode'],
            'address'        => $enrolment['calendar_address'],
            'push_available' => (bool)$connection,
            'feed_available' => scheduleFeedAllowed($conn),
            'last_error'     => $enrolment['last_error'] ?? null,
        ]);
        exit;
    }

    $mode = (string)($_POST['mode'] ?? '');
    if (!calendarModeIsValid($mode)) {
        echo json_encode(['success' => false, 'error' => 'Unknown option.']);
        exit;
    }
    if ($mode === CALENDAR_MODE_FEED && !scheduleFeedAllowed($conn)) {
        echo json_encode(['success' => false, 'error' => 'Subscription links are switched off on this system.']);
        exit;
    }

    if ($mode === CALENDAR_MODE_PUSH) {
        if (!$connection) {
            echo json_encode(['success' => false, 'error' => 'No calendar connection has been set up on this system yet.']);
            exit;
        }
        $address = $enrolment['calendar_address'] ?? '';
        if ($address === '' || $address === null) {
            echo json_encode(['success' => false, 'no_address' => true,
                'error' => 'We do not know which mailbox is yours.']);
            exit;
        }

        // 🔑 VERIFIED AT THE MOMENT OF OPTING IN, not on every page load. One
        // Graph call when the analyst actually chooses this is cheap and is
        // exactly when the answer matters; checking on every visit to Preferences
        // would be a call per page view for a question that rarely changes.
        //
        // ⚠️ And it must be checked SOMEWHERE. Ed's own FreeITSM address is
        // admin@localhost, which is not a mailbox at all — switching this on and
        // discovering nothing ever appeared, with no explanation, is the failure
        // this prevents.
        try {
            $provider = calendarSyncProviderFor($connection);
            $provider->conn = $conn;
            if (!$provider->verifyTarget($address)) {
                echo json_encode(['success' => false, 'bad_address' => true, 'address' => $address,
                    'error' => 'No calendar could be found for ' . $address . '.']);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    $wasPushing = ($enrolment['mode'] ?? '') === CALENDAR_MODE_PUSH;

    $conn->prepare(
        "INSERT INTO calendar_enrolments (analyst_id, mode, connection_id)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE mode = VALUES(mode), connection_id = VALUES(connection_id),
                                 last_error = NULL, updated_datetime = NOW()"
    )->execute([$analystId, $mode, ($mode === CALENDAR_MODE_PUSH && $connection) ? (int)$connection['id'] : null]);

    // 🔑 TURNING IT OFF TAKES BACK WHAT WE PUT THERE. Leaving events behind in
    // somebody's calendar that FreeITSM has stopped tracking is the worst of both
    // worlds: they cannot be updated, they cannot be removed by us later, and the
    // person has to delete each one by hand wondering where they came from.
    if ($wasPushing && $mode !== CALENDAR_MODE_PUSH) {
        require_once '../../includes/calendar_sync/push.php';
        calendarSyncRemoveAllForAnalyst($conn, $analystId);
    }

    // Switching ON backfills what is already scheduled, rather than only
    // affecting tickets touched from now on — an empty calendar after opting in
    // reads as "it didn't work".
    if (!$wasPushing && $mode === CALENDAR_MODE_PUSH) {
        require_once '../../includes/calendar_sync/push.php';
        $st = $conn->prepare(
            "SELECT t.id FROM tickets t
               LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
              WHERE t.owner_id = ? AND t.work_start_datetime IS NOT NULL
                AND t.deleted_datetime IS NULL AND COALESCE(ts.is_closed, 0) = 0
                AND t.work_start_datetime >= (NOW() - INTERVAL 1 WEEK)
              ORDER BY t.work_start_datetime"
        );
        $st->execute([$analystId]);
        // Bounded to the last week onwards on purpose: back-filling months of
        // finished work would fill a calendar with history nobody asked for, and
        // make opting in a very long request.
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $tid) {
            calendarSyncReconcileTicket($conn, (int)$tid);
        }
    }

    echo json_encode(['success' => true, 'mode' => $mode, 'address' => $enrolment['calendar_address']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
