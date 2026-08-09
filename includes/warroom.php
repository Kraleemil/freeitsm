<?php
/**
 * War room — the fallback chat analysts use when Teams/Slack is unavailable.
 *
 * WHY THIS EXISTS. When the internet drops, the on-premise service desk is the
 * last thing still running on the LAN — and it already knows every analyst, who
 * is in which team, and what is currently on fire. So it is the natural place
 * for people to gather. This is deliberately RUDIMENTARY: nobody is giving up
 * Teams for it, and the whole point is that it works when Teams cannot.
 *
 * THE ONE DESIGN RULE: a channel is not stored anywhere — A CHANNEL IS A TEAM.
 * The list is rendered from `teams` filtered by the analyst's `analyst_teams`
 * rows, so there is no channel lifecycle, no membership to manage, nothing to
 * orphan when a team is renamed, and no admin screen to build. You manage
 * channels by managing teams, which is where an admin would look anyway. Let
 * people create free-form channels and you inherit all of that overnight.
 *
 * NO EXTERNAL DEPENDENCIES, ON PURPOSE. No CDN asset, no cert, no token, no
 * scheduled job. A tool for the day the internet is down must not have a
 * dependency that can expire or fail to resolve while it is down — which is
 * also why retention is applied on write rather than by cron.
 *
 * Service layer: the UI page and the API endpoints both come through here, so
 * the access rule exists once. See [[Service-Layer-Architecture]].
 */

/** How long since a poll before we stop calling someone "here". */
const WARROOM_PRESENCE_WINDOW_SECONDS = 90;

/** Hard cap on one message, so a paste of a log file can't wedge the page. */
const WARROOM_MAX_BODY = 4000;

/**
 * The channels this analyst can see: the all-hands room, then their teams.
 *
 * `team_id === null` is the all-hands war room. It always exists, is always
 * first, and cannot be removed — in an actual outage you want one obvious place
 * for everyone, not six team rooms and an argument about which to use.
 *
 * @return array<int,array{team_id:?int,name:string}>
 */
function warRoomChannels(PDO $conn, int $analystId): array {
    $out = [[
        'team_id' => null,
        'name'    => function_exists('t') ? t('war-room.channel.all_hands') : 'War room',
    ]];

    $stmt = $conn->prepare(
        "SELECT t.id, t.name
           FROM teams t
           JOIN analyst_teams at ON at.team_id = t.id
          WHERE at.analyst_id = :aid
            AND (t.is_active IS NULL OR t.is_active = 1)
          ORDER BY COALESCE(t.display_order, 0), t.name"
    );
    $stmt->execute([':aid' => $analystId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[] = ['team_id' => (int) $row['id'], 'name' => $row['name']];
    }
    return $out;
}

/**
 * May this analyst read/write this channel?
 *
 * ⚠️ Enforced HERE, server-side, on every read and every write — never by only
 * rendering a shorter channel list. A team channel is visible to its members;
 * the all-hands room to every analyst. Anything unexpected fails CLOSED.
 */
function warRoomCanAccessChannel(PDO $conn, int $analystId, ?int $teamId): bool {
    if ($teamId === null) return true;          // all-hands: every analyst
    try {
        $stmt = $conn->prepare(
            "SELECT 1 FROM analyst_teams WHERE analyst_id = :aid AND team_id = :tid LIMIT 1"
        );
        $stmt->execute([':aid' => $analystId, ':tid' => $teamId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;                            // fail closed
    }
}

/**
 * Messages in a channel, oldest first.
 *
 * $sinceId drives the poll: the page asks for everything newer than the last id
 * it holds, so a quiet channel costs one indexed lookup that returns nothing.
 * On the first load $sinceId is 0 and we return the tail rather than the lot.
 *
 * A NULL analyst_id is a deleted account. It is rendered as "Former analyst"
 * rather than blank or "Unknown", so the reader can tell WHICH case it is.
 */
function warRoomMessages(PDO $conn, ?int $teamId, int $sinceId = 0, int $limit = 200): array {
    $limit = max(1, min(500, $limit));
    $teamClause = $teamId === null ? 'm.team_id IS NULL' : 'm.team_id = :tid';

    if ($sinceId > 0) {
        $sql = "SELECT m.id, m.body, m.created_datetime, m.analyst_id, a.full_name
                  FROM warroom_messages m
                  LEFT JOIN analysts a ON a.id = m.analyst_id
                 WHERE $teamClause AND m.id > :since
                 ORDER BY m.id ASC
                 LIMIT $limit";
    } else {
        // First load: the most recent $limit, flipped back into reading order.
        $sql = "SELECT * FROM (
                    SELECT m.id, m.body, m.created_datetime, m.analyst_id, a.full_name
                      FROM warroom_messages m
                      LEFT JOIN analysts a ON a.id = m.analyst_id
                     WHERE $teamClause
                     ORDER BY m.id DESC
                     LIMIT $limit
                ) AS recent ORDER BY id ASC";
    }

    $stmt = $conn->prepare($sql);
    if ($teamId !== null) $stmt->bindValue(':tid', $teamId, PDO::PARAM_INT);
    if ($sinceId > 0)     $stmt->bindValue(':since', $sinceId, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = [
            'id'        => (int) $r['id'],
            'body'      => $r['body'],
            'author'    => $r['full_name'] !== null
                ? $r['full_name']
                : (function_exists('t') ? t('war-room.former_analyst') : 'Former analyst'),
            'analyst_id' => $r['analyst_id'] !== null ? (int) $r['analyst_id'] : null,
            'created'   => $r['created_datetime'],
        ];
    }
    return $rows;
}

/** Post a message. Returns the new id, or 0 if the body was empty. */
function warRoomSend(PDO $conn, int $analystId, ?int $teamId, string $body): int {
    $body = trim($body);
    if ($body === '') return 0;
    if (mb_strlen($body) > WARROOM_MAX_BODY) $body = mb_substr($body, 0, WARROOM_MAX_BODY);

    $stmt = $conn->prepare(
        "INSERT INTO warroom_messages (team_id, analyst_id, body, created_datetime)
         VALUES (:tid, :aid, :body, UTC_TIMESTAMP())"
    );
    $stmt->bindValue(':tid', $teamId, $teamId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':aid', $analystId, PDO::PARAM_INT);
    $stmt->bindValue(':body', $body);
    $stmt->execute();

    // ⚠️ Read the id BEFORE anything else touches the connection. MySQL resets
    // the insert id on any statement that doesn't generate one, so the SELECT
    // inside warRoomPrune() below would turn this into 0 — the message saves
    // fine and the caller is told it failed. Caught by testing the endpoint
    // rather than by reading the code, which looked perfectly reasonable.
    $id = (int) $conn->lastInsertId();

    warRoomPrune($conn);
    return $id;
}

/**
 * Record that this analyst is here, and return who else is.
 *
 * The poll that fetches messages calls this, so presence costs no extra request
 * and there is no separate heartbeat to keep alive. The UPSERT onto the UNIQUE
 * analyst_id is what stops the table growing — one row per person, forever.
 */
function warRoomTouchPresence(PDO $conn, int $analystId, ?int $teamId): void {
    $stmt = $conn->prepare(
        "INSERT INTO warroom_presence (analyst_id, team_id, last_seen)
         VALUES (:aid, :tid, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE team_id = VALUES(team_id), last_seen = VALUES(last_seen)"
    );
    $stmt->bindValue(':aid', $analystId, PDO::PARAM_INT);
    $stmt->bindValue(':tid', $teamId, $teamId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Who has polled recently. Names only — this answers "is anyone reading this?",
 * which is most of what you want to know when your usual chat is down.
 *
 * @return array<int,string>
 */
function warRoomPresent(PDO $conn, int $excludeAnalystId = 0): array {
    $stmt = $conn->prepare(
        "SELECT a.full_name
           FROM warroom_presence p
           JOIN analysts a ON a.id = p.analyst_id
          WHERE p.last_seen >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :win SECOND)
            AND p.analyst_id <> :me
          ORDER BY a.full_name"
    );
    $stmt->bindValue(':win', WARROOM_PRESENCE_WINDOW_SECONDS, PDO::PARAM_INT);
    $stmt->bindValue(':me', $excludeAnalystId, PDO::PARAM_INT);
    $stmt->execute();
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'full_name');
}

/** Retention in days from settings. 0 (the default) means keep forever. */
function warRoomRetentionDays(PDO $conn): int {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'warroom_retention_days' LIMIT 1");
        $stmt->execute();
        $v = $stmt->fetchColumn();
        return $v === false ? 0 : max(0, (int) $v);
    } catch (Throwable $e) {
        return 0;                                 // never delete on an error
    }
}

/**
 * Apply the retention setting.
 *
 * Called from warRoomSend rather than from a cron job, deliberately: an
 * emergency tool must not depend on somebody having configured a scheduled
 * task. The LIMIT keeps any single send cheap — a long-overdue prune just takes
 * several sends to work through instead of stalling one of them.
 */
function warRoomPrune(PDO $conn): void {
    $days = warRoomRetentionDays($conn);
    if ($days <= 0) return;                       // keep forever
    try {
        $stmt = $conn->prepare(
            "DELETE FROM warroom_messages
              WHERE created_datetime < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :d DAY)
              LIMIT 500"
        );
        $stmt->bindValue(':d', $days, PDO::PARAM_INT);
        $stmt->execute();
    } catch (Throwable $e) {
        // Retention is housekeeping — never let it break posting a message.
    }
}
