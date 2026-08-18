<?php
/**
 * Per-company settings, falling back to the install-wide value.
 *
 * ⚠️ WHY THIS EXISTS. `system_settings` is a flat key/value table for the WHOLE
 * install, and `getTenantConfigRows()` handles per-company LOOKUP LISTS (statuses,
 * ticket types). Neither can express "this company bills for time and that one
 * does not" — so there was no way to answer a per-company yes/no question at all.
 *
 * The shape is deliberately the only sensible one:
 *
 *     tenant_settings   the answer for ONE company, when it has been given
 *          ↓ falls back to
 *     system_settings   the install-wide default
 *          ↓ falls back to
 *     the caller's default
 *
 * 🔑 A COMPANY WITH NO ROW IS NOT "OFF". It is "whatever the install says", which
 * is what makes the default meaningful and what keeps this invisible on a
 * single-company install: one toggle, no company column, nothing new to learn.
 *
 * ⚠️ Built for ONE setting and expected to serve many. "Per company, defaulting to
 * the install-wide value" is the shape most settings take once an install has more
 * than one company in it, so this is deliberately generic rather than a
 * time-tracking flag with a table around it.
 */

require_once __DIR__ . '/tenancy.php';

/**
 * The value of a setting for one company.
 *
 * @param ?int $tenantId  null = ask the install-wide value only
 */
function tenantSetting(PDO $conn, ?int $tenantId, string $key, ?string $default = null): ?string
{
    static $cache = [];
    $ck = ($tenantId ?? 0) . '|' . $key;
    if (array_key_exists($ck, $cache)) return $cache[$ck];

    $value = null;

    // 1. The company's own answer, if it has one and companies exist at all.
    if ($tenantId !== null && $tenantId > 0) {
        try {
            if (isMultiTenant($conn) || tenancyTablesReady($conn)) {
                $st = $conn->prepare("SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = ?");
                $st->execute([$tenantId, $key]);
                $row = $st->fetchColumn();
                if ($row !== false) $value = (string) $row;
            }
        } catch (Throwable $e) {
            // Table absent on a part-migrated install: fall through to the
            // install-wide value rather than failing the page.
        }
    }

    // 2. The install-wide default.
    if ($value === null) {
        try {
            $st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $st->execute([$key]);
            $row = $st->fetchColumn();
            if ($row !== false) $value = (string) $row;
        } catch (Throwable $e) { /* fall through */ }
    }

    // 3. The caller's default.
    if ($value === null) $value = $default;

    $cache[$ck] = $value;
    return $value;
}

/** Convenience for a yes/no setting. Anything but '0' is on. */
function tenantSettingOn(PDO $conn, ?int $tenantId, string $key, bool $default = true): bool
{
    $v = tenantSetting($conn, $tenantId, $key, $default ? '1' : '0');
    return $v !== '0';
}

/**
 * Set (or clear) one company's answer.
 *
 * ⚠️ A NULL value DELETES the row rather than storing an empty string, so the
 * company goes back to following the install-wide default. "Not set" and "set to
 * nothing" have to stay distinguishable, or a company can never be handed back to
 * the default once it has been given an answer.
 */
function setTenantSetting(PDO $conn, int $tenantId, string $key, ?string $value): void
{
    if ($value === null) {
        $conn->prepare("DELETE FROM tenant_settings WHERE tenant_id = ? AND setting_key = ?")
             ->execute([$tenantId, $key]);
        return;
    }
    $conn->prepare(
        "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value)
         VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_datetime = UTC_TIMESTAMP()"
    )->execute([$tenantId, $key, $value]);
}

/**
 * Every company's answer for one key, for rendering a settings table.
 *
 * @return array<int,string> tenant_id => value, containing ONLY companies that
 *                           have their own answer. Anything absent follows the
 *                           install default, which the caller shows separately.
 */
function tenantSettingsForKey(PDO $conn, string $key): array
{
    $out = [];
    try {
        $st = $conn->prepare("SELECT tenant_id, setting_value FROM tenant_settings WHERE setting_key = ?");
        $st->execute([$key]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['tenant_id']] = (string) $r['setting_value'];
        }
    } catch (Throwable $e) { /* table absent — everyone follows the default */ }
    return $out;
}

// ─── Time tracking (discussion #72) ─────────────────────────────────────────
// Two separate answers, deliberately. Hiding the panel is about interface
// clutter; silently emptying an API endpoint breaks integrations belonging to
// people who changed nothing. They are different decisions, so they are
// different switches.

const SETTING_TIME_TRACKING_UI  = 'time_tracking_enabled';
const SETTING_TIME_TRACKING_API = 'time_tracking_api_enabled';

/** Should the time-recording UI appear for a ticket belonging to this company? */
function timeTrackingUiOn(PDO $conn, ?int $tenantId): bool
{
    return tenantSettingOn($conn, $tenantId, SETTING_TIME_TRACKING_UI, true);
}

/** Should the REST API serve time entries for a ticket belonging to this company? */
function timeTrackingApiOn(PDO $conn, ?int $tenantId): bool
{
    return tenantSettingOn($conn, $tenantId, SETTING_TIME_TRACKING_API, true);
}

/** The company a ticket belongs to, or null. Used to resolve both of the above. */
function ticketTenantId(PDO $conn, int $ticketId): ?int
{
    try {
        $st = $conn->prepare("SELECT tenant_id FROM tickets WHERE id = ?");
        $st->execute([$ticketId]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? null : (int) $v;
    } catch (Throwable $e) {
        return null;
    }
}
