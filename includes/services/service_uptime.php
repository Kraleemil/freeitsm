<?php
/**
 * Service history and uptime, DERIVED from incidents (discussion #59).
 *
 * ── Why there is nothing to "log" ────────────────────────────────────────────
 *
 * The request was for a table recording every status change per service. There
 * is no such thing to record: `status_services` has no status column. A
 * service's status is computed live in get_dashboard.php as
 *
 *     the worst impact level among its OPEN incidents, else 'Operational'
 *
 * so a history table of previous → new would sit permanently empty.
 *
 * What already exists is better. Every period a service was not Operational IS
 * an incident that touched it:
 *
 *     start   status_incidents.created_datetime
 *     end     status_incidents.resolved_datetime  (or now, if still open)
 *     what    status_incident_services.impact_level_id
 *
 * So the history can be derived from data every install already has, which
 * means it works RETROSPECTIVELY on outages that have already happened rather
 * than shipping empty and becoming useful in six months.
 *
 * ── What this deliberately cannot see yet ────────────────────────────────────
 *
 * ⚠️ Changes DURING an incident. `replaceIncidentServices()` deletes and
 * re-inserts the service links, so moving a service from Major Outage to
 * Degraded mid-incident overwrites the earlier value — this will report the
 * whole incident at whatever the level ended up as. Incident status transitions
 * (Investigating → Identified → Monitoring) are not timestamped either.
 *
 * That is the phase-two work: one incident-updates log, written where incidents
 * are saved, which this function would then read in preference to the
 * incident's own start/end. Until then every figure here is "per incident"
 * rather than "per change", and the UI says so rather than implying a precision
 * it does not have.
 */

require_once __DIR__ . '/../tenancy.php';

class ServiceUptime
{
    /** Windows offered in the UI, in days. */
    const WINDOWS = [7, 30, 90, 365];

    /** Fallback when the setting is absent or nonsense. */
    const DEFAULT_WINDOW = 90;

    /**
     * The install's chosen default window, clamped to one we offer.
     */
    public static function defaultWindowDays(PDO $conn): int
    {
        try {
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'status_uptime_window_days'");
            $stmt->execute();
            $v = (int) $stmt->fetchColumn();
            if (in_array($v, self::WINDOWS, true)) {
                return $v;
            }
        } catch (Exception $e) {
            // fall through
        }
        return self::DEFAULT_WINDOW;
    }

    /** Should portal users see uptime figures? Defaults to NO — see the settings tab. */
    public static function shownInPortal(PDO $conn): bool
    {
        try {
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'status_uptime_show_portal'");
            $stmt->execute();
            return (string) $stmt->fetchColumn() === '1';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Every incident that touched this service and overlaps the window.
     *
     * Returned newest first, each with its own start, end (null = ongoing) and
     * the impact level recorded against THIS service.
     *
     * @return array<int,array{incident_id:int,title:string,impact:string,colour:?string,
     *                         counts:bool,started:string,ended:?string,seconds:int,ongoing:bool}>
     */
    public static function incidentsFor(PDO $conn, int $serviceId, int $windowDays): array
    {
        $sql = "SELECT si.id AS incident_id, si.title,
                       si.created_datetime  AS started,
                       si.resolved_datetime AS ended,
                       il.name   AS impact,
                       il.colour AS colour,
                       il.counts_as_downtime AS counts,
                       sst.is_resolved AS status_resolved
                  FROM status_incident_services sis
                  JOIN status_incidents si       ON si.id = sis.incident_id
                  LEFT JOIN service_impact_levels il  ON il.id = sis.impact_level_id
                  LEFT JOIN service_incident_statuses sst ON sst.id = si.status_id
                 WHERE sis.service_id = ?
                   -- Overlaps the window: it either has not ended, or it ended
                   -- inside it. An incident that STARTED before the window but
                   -- is still running must be included, which a naive
                   -- `created_datetime >= cutoff` would drop — and that is
                   -- exactly the long outage nobody wants missing.
                   AND (si.resolved_datetime IS NULL
                        OR si.resolved_datetime >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY))
                 ORDER BY si.created_datetime DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$serviceId, $windowDays]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            // An incident is "ongoing" when it has no resolved stamp. The status
            // being a resolved one without a stamp is a data oddity rather than a
            // state; treat the stamp as authoritative and let the UI show the row.
            $ongoing = ($r['ended'] === null);
            $out[] = [
                'incident_id' => (int) $r['incident_id'],
                'title'       => (string) $r['title'],
                'impact'      => (string) ($r['impact'] ?? 'Unknown'),
                'colour'      => $r['colour'] ?? null,
                'counts'      => (int) ($r['counts'] ?? 1) === 1,
                'started'     => (string) $r['started'],
                'ended'       => $r['ended'],
                'ongoing'     => $ongoing,
                'seconds'     => self::spanSeconds((string) $r['started'], $r['ended']),
            ];
        }
        return $out;
    }

    /**
     * Downtime seconds in the window, and the uptime percentage.
     *
     * ⚠️ OVERLAPS ARE MERGED. Two incidents both taking the same service down at
     * the same time is one outage, not two — summing their durations would
     * happily report more downtime than there were seconds in the window, and
     * an uptime below zero. The intervals are clipped to the window, sorted, and
     * unioned before anything is added up.
     *
     * Only levels with counts_as_downtime = 1 are included, so planned
     * maintenance does not make a well-run service look worse than a neglected
     * one. That is a per-level setting — see Status → Settings → Impact levels.
     *
     * @return array{window_days:int,window_seconds:int,downtime_seconds:int,
     *                uptime_percent:float,incident_count:int,counted_count:int}
     */
    public static function summaryFor(PDO $conn, int $serviceId, int $windowDays): array
    {
        $incidents     = self::incidentsFor($conn, $serviceId, $windowDays);
        $windowSeconds = $windowDays * 86400;
        $now           = time();
        $windowStart   = $now - $windowSeconds;

        $spans = [];
        $counted = 0;
        foreach ($incidents as $i) {
            if (!$i['counts']) {
                continue;   // maintenance and the like
            }
            $counted++;
            $s = strtotime($i['started'] . ' UTC');
            $e = $i['ended'] !== null ? strtotime($i['ended'] . ' UTC') : $now;
            if ($s === false || $e === false || $e <= $s) {
                continue;
            }
            // Clip to the window — an outage that began before it only counts
            // for the part that falls inside.
            $s = max($s, $windowStart);
            $e = min($e, $now);
            if ($e > $s) {
                $spans[] = [$s, $e];
            }
        }

        $downtime = self::unionSeconds($spans);

        // Guard the division and the ceiling: a clipped span can never exceed the
        // window, but a clock change or a future-dated incident should not be able
        // to produce 103% or -4%.
        $downtime = max(0, min($downtime, $windowSeconds));
        $uptime   = $windowSeconds > 0
            ? round((($windowSeconds - $downtime) / $windowSeconds) * 100, 3)
            : 100.0;

        return [
            'window_days'      => $windowDays,
            'window_seconds'   => $windowSeconds,
            'downtime_seconds' => $downtime,
            'uptime_percent'   => $uptime,
            'incident_count'   => count($incidents),
            'counted_count'    => $counted,
        ];
    }

    /**
     * One entry per day in the window, newest LAST, for the bar strip.
     *
     * Each day carries the worst counting impact that touched it, so a strip of
     * 90 bars reads as "when was this service unhappy" at a glance. Days with no
     * incident are 'ok'; days touched only by a non-counting level are 'info',
     * which is how planned maintenance stays visible without being punitive.
     *
     * @return array<int,array{date:string,state:string,impact:?string,colour:?string,seconds:int}>
     */
    public static function dailyStrip(PDO $conn, int $serviceId, int $windowDays): array
    {
        $incidents = self::incidentsFor($conn, $serviceId, $windowDays);
        $now       = time();
        $days      = [];

        // Severity ordering so a day showing two impacts shows the worse one.
        $sevStmt = $conn->query("SELECT name, severity_order FROM service_impact_levels");
        $severity = [];
        foreach ($sevStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $severity[$r['name']] = (int) $r['severity_order'];
        }

        for ($d = $windowDays - 1; $d >= 0; $d--) {
            $dayStart = strtotime('today UTC') - ($d * 86400);
            $dayEnd   = $dayStart + 86400;
            $key      = gmdate('Y-m-d', $dayStart);

            $worst = null; $worstSev = PHP_INT_MAX; $secs = 0; $info = null;
            foreach ($incidents as $i) {
                $s = strtotime($i['started'] . ' UTC');
                $e = $i['ended'] !== null ? strtotime($i['ended'] . ' UTC') : $now;
                if ($s === false || $e === false || $e <= $dayStart || $s >= $dayEnd) {
                    continue;
                }
                // ⚠️ Keep the non-counting incident, do not just remember THAT there
                // was one. This was a bare `$sawInfo = true`, and the strip then had
                // no name to show — so the tooltip fell back to the word
                // "maintenance" for every excluded level. An incident logged at
                // Operational reported itself as maintenance, which is simply untrue.
                // Any level with counts_as_downtime = 0 lands here, and there are
                // three shipped ones plus anything an administrator adds.
                if (!$i['counts']) {
                    if ($info === null) { $info = $i; }
                    continue;
                }
                $secs += min($e, $dayEnd) - max($s, $dayStart);
                $sev = $severity[$i['impact']] ?? 99;
                if ($sev < $worstSev) { $worstSev = $sev; $worst = $i; }
            }

            $shown = $worst ?? $info;
            $days[] = [
                'date'    => $key,
                'state'   => $worst !== null ? 'down' : ($info !== null ? 'info' : 'ok'),
                'impact'  => $shown['impact'] ?? null,
                'colour'  => $shown['colour'] ?? null,
                'seconds' => $secs,
            ];
        }
        return $days;
    }

    /**
     * Merge overlapping [start, end] pairs and total the result.
     *
     * The whole reason summaryFor() cannot simply SUM durations.
     */
    private static function unionSeconds(array $spans): int
    {
        if (!$spans) {
            return 0;
        }
        usort($spans, fn($a, $b) => $a[0] <=> $b[0]);
        $total = 0;
        [$curStart, $curEnd] = $spans[0];
        for ($i = 1; $i < count($spans); $i++) {
            [$s, $e] = $spans[$i];
            if ($s <= $curEnd) {              // overlapping or touching → extend
                $curEnd = max($curEnd, $e);
            } else {
                $total += $curEnd - $curStart;
                [$curStart, $curEnd] = [$s, $e];
            }
        }
        return $total + ($curEnd - $curStart);
    }

    /** Seconds between two UTC stamps; an open incident runs to now. */
    private static function spanSeconds(string $started, ?string $ended): int
    {
        $s = strtotime($started . ' UTC');
        $e = $ended !== null ? strtotime($ended . ' UTC') : time();
        if ($s === false || $e === false || $e <= $s) {
            return 0;
        }
        return $e - $s;
    }

    /** "2h 15m" / "45m" / "3d 4h" — the shape the request asked for. */
    public static function humanDuration(int $seconds): string
    {
        if ($seconds < 60)  return $seconds . 's';
        $m = intdiv($seconds, 60);
        if ($m < 60)        return $m . 'm';
        $h = intdiv($m, 60); $m = $m % 60;
        if ($h < 24)        return $m ? "{$h}h {$m}m" : "{$h}h";
        $d = intdiv($h, 24); $h = $h % 24;
        return $h ? "{$d}d {$h}h" : "{$d}d";
    }
}
