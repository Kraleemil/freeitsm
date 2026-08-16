<?php
/**
 * System → Authentication → one LDAP / Active Directory provider.
 *
 * A directory carries far too much configuration for a dialog: a connection, a
 * sign-in scope, group gating, an import scope, attribute mapping, safety
 * settings and a run history. It had all been bolted onto the modal on the list
 * page, where the import section ended up below the fold and people reasonably
 * concluded it was not there.
 *
 * OIDC providers keep the modal — an issuer, a client id and a secret genuinely
 * is a dialog's worth of information.
 *
 * Tabs use the shared renderSettingsTabBar() and the same .tabs/.tab markup as
 * every module settings screen, so this page behaves the way the rest of the
 * application has already taught people to expect.
 */
session_start();
require_once '../../config.php';
require_once '../../includes/i18n.php';
require_once '../../includes/timezone.php';
I18n::initFromSession();
Tz::init();

require_once '../../includes/functions.php';
require_once '../../includes/theme.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/settings_manifest.php';   // renderSettingsTabBar()

$current_page = 'sso';
$path_prefix  = '../../';
$translationNamespaces = ['common', 'system'];

if (empty($_SESSION['analyst_id'])) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'auth/login.php');
    exit;
}

$conn = connectToDatabase();
if (!analystIsAdmin($conn, (int)$_SESSION['analyst_id'])) {
    http_response_code(403);
    exit('Administrator access required.');
}

$providerId = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM auth_providers WHERE id = ? AND protocol = 'ldap'");
$stmt->execute([$providerId]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    header('Location: index.php');
    exit;
}

// Never send the stored bind password to the browser. A masked placeholder means
// "leave it alone", which is the same contract the modal already used.
$hasBindPassword = !empty($p['ldap_bind_password']);

$multiTenant = isMultiTenant($conn);
$tenants = $multiTenant
    ? $conn->query("SELECT id, name FROM tenants WHERE is_active = 1 ORDER BY is_default DESC, name")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$tabs = [
    ['id' => 'connection', 'cap' => null, 'label' => t('system.sso.tab_connection')],
    ['id' => 'signin',     'cap' => null, 'label' => t('system.sso.tab_signin')],
    ['id' => 'import',     'cap' => null, 'label' => t('system.sso.tab_import')],
    ['id' => 'mapping',    'cap' => null, 'label' => t('system.sso.tab_mapping')],
    ['id' => 'history',    'cap' => null, 'label' => t('system.sso.tab_history')],
];
$activeTab = in_array($_GET['tab'] ?? '', ['connection','signin','import','mapping','history'], true)
    ? $_GET['tab'] : 'connection';

/** Print a value into an input safely. */
function v($row, string $k): string { return htmlspecialchars((string)($row[$k] ?? '')); }
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - <?php echo htmlspecialchars($p['display_name']); ?></title>
    <link rel="stylesheet" href="../../assets/css/theme.css?v=23">
    <link rel="stylesheet" href="../../assets/css/inbox.css">
    <script>window.translations = <?php echo json_encode(I18n::exportForJs($translationNamespaces), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script src="../../assets/js/i18n.js?v=2"></script>
    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/confirm.js"></script>
    <style>
        body {
            --accent: var(--sys-accent, #546e7a);
            --accent-hover: var(--sys-accent-hover, #37474f);
            --on-accent: var(--sys-on-accent, #fff);
            margin: 0; background: var(--app-bg, #f5f5f5);
            /* A flex column, so the scrolling area is "whatever is left below the
               header" and nothing has to know how tall the header is.
               ⚠️ It was calc(100vh - 48px) first. The header is 58px, so the
               wrapper hung 10px past the bottom of the window and took the Save
               button with it. Measuring found that; the CSS read as correct. */
            display: flex; flex-direction: column; height: 100vh; overflow: hidden;
        }
        /* Full width, edge to edge. ⚠️ `max-width: none` alone is not enough
           elsewhere in this app — an inherited `margin: … auto` inside a flex
           parent cancels the stretch and re-centres the column. Belt (width) and
           braces (margin: 0) both stated so this cannot regress into a narrow
           centred page that looks exactly like the cap is still there. */
        .prov-wrap {
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
            /* No bottom padding: the sticky save bar is the last element and
               provides the end-of-page spacing itself. With padding here AND a
               negative margin on the bar to cancel it, the bar ended up sitting
               over the final field and clipping it. */
            padding: 24px 32px 0;
            /* ⚠️ THIS is what scrolls, not the document. Without it the content
               below the fold is simply unreachable — there is nothing to scroll.
               flex:1 + min-height:0 takes exactly the space the header leaves,
               with no hardcoded header height to get wrong. */
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
        }
        .prov-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 6px; }
        .prov-title { font-size: 22px; font-weight: 600; color: var(--text, #333); margin: 0; }
        .prov-sub { font-size: 13px; color: var(--text-dim, #888); margin: 2px 0 18px; }
        .prov-card { background: var(--surface, #fff); border-radius: 8px; padding: 22px; box-shadow: 0 1px 4px var(--shadow, rgba(0,0,0,0.08)); }
        .fld { margin-bottom: 18px; }
        .fld label { display: block; font-size: 13px; font-weight: 600; color: var(--text, #333); margin-bottom: 3px; }
        .fld .hint { font-size: 12px; color: var(--text-dim, #888); margin-bottom: 6px; line-height: 1.5; }
        .fld input[type=text], .fld input[type=password], .fld input[type=number], .fld select {
            width: 100%; box-sizing: border-box; padding: 9px 11px; font-size: 13px;
            border: 1px solid var(--border, #ddd); border-radius: 6px;
            background: var(--surface, #fff); color: var(--text, #333);
        }
        .fld-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .fld-row > * { flex: 1; min-width: 160px; }
        .chk { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text, #333); font-weight: 600; }
        .chk input { width: auto; }
        .attr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; }
        .result { margin-top: 8px; font-size: 12px; padding: 9px 11px; border-radius: 6px; display: none; white-space: pre-wrap; line-height: 1.5; }
        .result.ok   { display: block; background: #e8f5e9; color: #2e7d32; }
        .result.err  { display: block; background: #ffebee; color: #c62828; }
        /* The safety brake is a REFUSAL, not a failure. Amber: nothing is broken,
           we declined to act on something that looked wrong. */
        .result.warn { display: block; background: #fff4ce; color: #6b5900; }
        [data-theme-mode="dark"] .result.ok   { background: #16331f; color: #86efac; }
        [data-theme-mode="dark"] .result.err  { background: #3a1b1e; color: #fca5a5; }
        [data-theme-mode="dark"] .result.warn { background: #3a3218; color: #fde68a; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
        .btn-primary { background: var(--sys-accent, #546e7a); color: var(--sys-on-accent, #fff); }
        .btn-test { background: var(--surface, #fff); color: var(--sys-accent, #546e7a); border: 1px solid var(--border, #cfd8dc); }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-row { display: flex; gap: 8px; flex-wrap: wrap; }
        /* Sticky to the bottom of the SCROLLING wrapper, so Save is reachable
           without scrolling to the end of a long tab. The negative margins let
           its background span the wrapper's padding rather than leaving a
           transparent gutter each side that the content shows through. */
        .save-bar {
            position: sticky; bottom: 0; z-index: 5;
            margin: 20px -32px 0; padding: 14px 32px;
            background: var(--app-bg, #f5f5f5);
            border-top: 1px solid var(--border, #e0e0e0);
            display: flex; gap: 10px; align-items: center;
        }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        /* Using the width is not the same as stretching ONE text box across it.
           On a wide screen the field blocks flow into two columns; anything that
           genuinely wants the full run (the attribute grids, the test and run
           buttons) opts out with .wide.
           ⚠️ MUST come after `.tab-pane.active { display: block }` — same
           specificity, so the later rule wins. Placed above it first, and the
           measured display stayed `block` while the CSS read as though it were
           grid. */
        @media (min-width: 1250px) {
            .tab-pane.active { display: grid; grid-template-columns: 1fr 1fr; gap: 0 40px; align-items: start; }
            .tab-pane.active > .wide { grid-column: 1 / -1; }
        }
        /* The mapping table. Fixed layout so the three columns stay put as
           example values of wildly different lengths arrive — a distinguished
           name in the manager row is enormous and would otherwise shove the
           attribute column into a sliver. */
        table.map { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: fixed; }
        table.map th, table.map td { text-align: left; padding: 8px 10px; vertical-align: top; border-bottom: 1px solid var(--border-soft, #f0f0f0); }
        table.map thead th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-dim, #888); border-bottom: 1px solid var(--border, #e0e0e0); }
        table.map tbody th { font-weight: 600; color: var(--text, #333); }
        table.map .map-hint { display: block; font-weight: 400; font-size: 11.5px; color: var(--text-dim, #888); margin-top: 2px; line-height: 1.45; }
        table.map input { width: 100%; box-sizing: border-box; padding: 7px 9px; font-size: 12.5px; font-family: ui-monospace, Consolas, monospace;
            border: 1px solid var(--border, #ddd); border-radius: 5px; background: var(--surface, #fff); color: var(--text, #333); }
        tr.map-group td { background: var(--app-bg, #f7f7f7); font-size: 11.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .04em; color: var(--text-dim, #888); padding: 7px 10px; border-bottom: 1px solid var(--border, #e6e6e6); }
        /* The example column. Before a test it is a dash, not an empty cell —
           an empty cell reads as "this field imports nothing", which is exactly
           the thing the test exists to tell you. */
        td.map-sample { font-size: 12.5px; color: var(--text-muted, #555); word-break: break-word; }
        td.map-sample.filled { color: var(--text, #2e7d32); }
        td.map-sample.missing { color: #c62828; font-style: italic; }
        [data-theme-mode="dark"] td.map-sample.filled { color: #86efac; }
        [data-theme-mode="dark"] td.map-sample.missing { color: #fca5a5; }
        .avail { margin-top: 12px; font-size: 12px; color: var(--text-dim, #888); }
        .avail summary { cursor: pointer; font-weight: 600; color: var(--sys-accent, #546e7a); }
        .avail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 3px 18px; margin-top: 10px; }
        .avail-grid code { font-family: ui-monospace, Consolas, monospace; color: var(--text, #444); }
        .avail-grid span { color: var(--text-dim, #999); }
        table.runs { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        table.runs th { text-align: left; padding: 7px 9px; color: var(--text-dim, #888); font-weight: 600; border-bottom: 1px solid var(--border-soft, #eee); white-space: nowrap; }
        table.runs td { padding: 7px 9px; border-bottom: 1px solid var(--border-soft, #f4f4f4); color: var(--text, #444); white-space: nowrap; }
        td.run-msg { white-space: normal; color: var(--text-muted, #666); font-size: 11.5px; padding-bottom: 12px; }
        .pill { display: inline-block; padding: 1px 9px; border-radius: 10px; font-size: 11px; font-weight: 700; }
        .pill.ok { background: #e8f5e9; color: #2e7d32; }
        .pill.stopped { background: #fff4ce; color: #6b5900; }
        .pill.failed, .pill.running { background: #ffebee; color: #c62828; }
        .back-link { font-size: 13px; color: var(--sys-accent, #546e7a); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        @media (max-width: 700px) { .prov-wrap { padding: 14px 12px 50px; } }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="prov-wrap">
    <a class="back-link" href="index.php">&larr; <?php echo htmlspecialchars(t('system.sso.back_to_list')); ?></a>
    <div class="prov-head">
        <div>
            <h1 class="prov-title"><?php echo htmlspecialchars($p['display_name']); ?></h1>
            <div class="prov-sub"><?php echo htmlspecialchars(t('system.sso.provider_page_sub')); ?></div>
        </div>
    </div>

    <?php renderSettingsTabBar($tabs, $activeTab, 'switchProvTab'); ?>

    <div class="prov-card">
        <!-- ================= Connection ================= -->
        <div class="tab-pane<?php echo $activeTab === 'connection' ? ' active' : ''; ?>" id="connection-pane">
            <div class="fld">
                <label><?php echo htmlspecialchars(t('system.sso.field_display_name')); ?></label>
                <input type="text" id="fDisplayName" value="<?php echo v($p, 'display_name'); ?>">
            </div>
            <div class="fld fld-row">
                <div>
                    <label><?php echo htmlspecialchars(t('system.sso.field_ldap_host')); ?></label>
                    <input type="text" id="fHost" value="<?php echo v($p, 'ldap_host'); ?>">
                </div>
                <div style="max-width:130px;">
                    <label><?php echo htmlspecialchars(t('system.sso.field_ldap_port')); ?></label>
                    <input type="number" id="fPort" value="<?php echo v($p, 'ldap_port'); ?>">
                </div>
                <div style="max-width:170px;">
                    <label><?php echo htmlspecialchars(t('system.sso.field_ldap_encryption')); ?></label>
                    <select id="fEncryption">
                        <?php foreach (['none' => 'None', 'ldaps' => 'LDAPS', 'starttls' => 'STARTTLS'] as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"<?php echo ($p['ldap_encryption'] ?? 'none') === $k ? ' selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="fld">
                <label><?php echo htmlspecialchars(t('system.sso.field_ldap_bind_dn')); ?></label>
                <div class="hint"><?php echo htmlspecialchars(t('system.sso.field_ldap_bind_dn_hint')); ?></div>
                <input type="text" id="fBindDn" value="<?php echo v($p, 'ldap_bind_dn'); ?>">
            </div>
            <div class="fld">
                <label><?php echo htmlspecialchars(t('system.sso.field_ldap_bind_password')); ?></label>
                <div class="hint"><?php echo htmlspecialchars(t('system.sso.field_ldap_bind_password_hint')); ?></div>
                <input type="password" id="fBindPassword" autocomplete="new-password"
                       placeholder="<?php echo $hasBindPassword ? '••••••••  (unchanged)' : ''; ?>">
            </div>
            <?php if ($multiTenant): ?>
            <div class="fld">
                <label><?php echo htmlspecialchars(t('system.sso.field_tenant')); ?></label>
                <select id="fTenantId">
                    <option value=""><?php echo htmlspecialchars(t('system.sso.tenant_all')); ?></option>
                    <?php foreach ($tenants as $tn): ?>
                    <option value="<?php echo (int)$tn['id']; ?>"<?php echo (int)$p['tenant_id'] === (int)$tn['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($tn['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="fld">
                <label class="chk"><input type="checkbox" id="fEnabled"<?php echo (int)$p['enabled'] === 1 ? ' checked' : ''; ?>> <?php echo htmlspecialchars(t('system.sso.field_enabled')); ?></label>
            </div>
            <div class="fld wide">
                <label><?php echo htmlspecialchars(t('system.sso.ldap_test_heading')); ?></label>
                <div class="hint"><?php echo htmlspecialchars(t('system.sso.ldap_test_desc')); ?></div>
                <div class="fld-row">
                    <input type="text" id="fTestUser" placeholder="<?php echo htmlspecialchars(t('system.sso.ldap_test_user')); ?>">
                    <input type="password" id="fTestPass" placeholder="<?php echo htmlspecialchars(t('system.sso.ldap_test_pass')); ?>" autocomplete="new-password">
                    <button class="btn btn-test" id="testBtn" type="button" style="flex:0 0 auto;"><?php echo htmlspecialchars(t('system.sso.test')); ?></button>
                </div>
                <div class="result" id="testResult"></div>
            </div>
        </div>

        <!-- ================= Sign-in ================= -->
        <div class="tab-pane<?php echo $activeTab === 'signin' ? ' active' : ''; ?>" id="signin-pane">
            <div class="fld">
                <label><?php echo htmlspecialchars(t('system.sso.field_ldap_base_dn')); ?></label>
                <div class="hint"><?php echo htmlspecialchars(t('system.sso.field_ldap_base_dn_hint')); ?></div>
                <input type="text" id="fBaseDn" value="<?php echo v($p, 'ldap_base_dn'); ?>">
            </div>
            <div class="fld">
                <label><?php echo htmlspecialchars(t('system.sso.field_ldap_user_filter')); ?></label>
                <input type="text" id="fUserFilter" value="<?php echo v($p, 'ldap_user_filter'); ?>">
            </div>
            <!-- The attribute boxes used to sit here. Leaving a pointer rather
                 than nothing: somebody who knew where they were will otherwise
                 conclude they have been removed. -->
            <div class="fld">
                <div class="hint"><?php echo htmlspecialchars(t('system.sso.attrs_moved')); ?>
                    <a href="#" onclick="switchProvTab('mapping');return false;"><?php echo htmlspecialchars(t('system.sso.tab_mapping')); ?></a>.</div>
            </div>
            <div class="fld">
                <label><?php echo htmlspecialchars(t('system.sso.field_ldap_groups')); ?></label>
                <div class="hint"><?php echo htmlspecialchars(t('system.sso.field_ldap_groups_hint')); ?></div>
                <div class="fld-row">
                    <input type="text" id="fAnalystGroup" value="<?php echo v($p, 'ldap_analyst_group'); ?>" placeholder="<?php echo htmlspecialchars(t('system.sso.field_ldap_analyst_group')); ?>">
                    <input type="text" id="fUserGroup"    value="<?php echo v($p, 'ldap_user_group'); ?>"    placeholder="<?php echo htmlspecialchars(t('system.sso.field_ldap_user_group')); ?>">
                </div>
                <div style="margin-top:8px;">
                    <input type="text" id="fGroupFilter"  value="<?php echo v($p, 'ldap_group_filter'); ?>"  placeholder="(&(objectClass=group)(member=%s))">
                </div>
                <div style="margin-top:8px;">
                    <input type="text" id="fGroupBaseDn"  value="<?php echo v($p, 'ldap_group_base_dn'); ?>" placeholder="<?php echo htmlspecialchars(t('system.sso.field_ldap_group_base_dn_placeholder')); ?>">
                </div>
            </div>
            <div class="fld">
                <label class="chk"><input type="checkbox" id="fAutoCreate"<?php echo (int)$p['auto_create_users'] === 1 ? ' checked' : ''; ?>> <?php echo htmlspecialchars(t('system.sso.field_auto_create')); ?></label>
            </div>
        </div>

        <!-- ================= Importing people ================= -->
        <div class="tab-pane<?php echo $activeTab === 'import' ? ' active' : ''; ?>" id="import-pane">
            <div class="fld">
                <div class="hint" style="margin-bottom:10px;"><?php echo htmlspecialchars(t('system.sso.sync_desc')); ?></div>
                <label class="chk"><input type="checkbox" id="fSyncEnabled"<?php echo (int)$p['sync_enabled'] === 1 ? ' checked' : ''; ?>> <?php echo htmlspecialchars(t('system.sso.sync_enabled')); ?></label>
            </div>
            <div id="syncFields">
                <div class="fld">
                    <label><?php echo htmlspecialchars(t('system.sso.sync_scope')); ?></label>
                    <div class="hint"><?php echo htmlspecialchars(t('system.sso.sync_base_dn_hint')); ?></div>
                    <input type="text" id="fSyncBaseDn" value="<?php echo v($p, 'sync_base_dn'); ?>" placeholder="<?php echo htmlspecialchars(t('system.sso.sync_base_dn_placeholder')); ?>">
                    <div class="hint" style="margin-top:8px;"><?php echo htmlspecialchars(t('system.sso.sync_filter_hint')); ?></div>
                    <input type="text" id="fSyncFilter" value="<?php echo v($p, 'sync_filter'); ?>" placeholder="(&(objectClass=user)(objectCategory=person))">
                </div>
                <div class="fld">
                    <label><?php echo htmlspecialchars(t('system.sso.sync_conflict')); ?></label>
                    <div class="hint"><?php echo htmlspecialchars(t('system.sso.sync_conflict_hint')); ?></div>
                    <select id="fSyncOnConflict">
                        <option value="adopt"<?php echo ($p['sync_on_conflict'] ?? 'adopt') === 'adopt' ? ' selected' : ''; ?>><?php echo htmlspecialchars(t('system.sso.sync_conflict_adopt')); ?></option>
                        <option value="flag"<?php echo ($p['sync_on_conflict'] ?? '') === 'flag' ? ' selected' : ''; ?>><?php echo htmlspecialchars(t('system.sso.sync_conflict_flag')); ?></option>
                    </select>
                </div>
                <div class="fld">
                    <label><?php echo htmlspecialchars(t('system.sso.sync_safety')); ?></label>
                    <div class="hint"><?php echo htmlspecialchars(t('system.sso.sync_deactivate_hint')); ?></div>
                    <input type="number" id="fSyncDeactivateAfter" min="0" max="50" style="max-width:130px;" value="<?php echo (int)$p['sync_deactivate_after']; ?>">
                    <div class="hint" style="margin-top:10px;"><?php echo htmlspecialchars(t('system.sso.sync_brake_hint')); ?></div>
                    <input type="number" id="fSyncBrakePercent" min="0" max="100" style="max-width:130px;" value="<?php echo (int)$p['sync_brake_percent']; ?>">
                </div>
                <div class="fld">
                    <div class="hint"><?php echo htmlspecialchars(t('system.sso.attrs_moved')); ?>
                        <a href="#" onclick="switchProvTab('mapping');return false;"><?php echo htmlspecialchars(t('system.sso.tab_mapping')); ?></a>.</div>
                </div>
                <div class="fld wide">
                    <label><?php echo htmlspecialchars(t('system.sso.sync_run_heading')); ?></label>
                    <div class="hint"><?php echo htmlspecialchars(t('system.sso.sync_run_hint')); ?></div>
                    <div class="btn-row">
                        <button class="btn btn-test" id="previewBtn" type="button"><?php echo htmlspecialchars(t('system.sso.sync_preview')); ?></button>
                        <button class="btn btn-test" id="runBtn" type="button"><?php echo htmlspecialchars(t('system.sso.sync_run')); ?></button>
                    </div>
                    <div class="result" id="syncResult"></div>
                </div>
            </div>
        </div>

        <!-- ================= Field mapping =================
             Every attribute box on one screen, FreeITSM's field on the left and
             the directory's on the right, because that IS the sentence being
             written: "put THIS of theirs into THAT of ours". They were split
             across two tabs before — four on Signing in, seven on Importing
             people — which read as two unrelated settings rather than one map.

             The identity rows are separated out because they are the ones
             sign-in also depends on: changing `Unique id` after people have
             been imported re-identifies everybody, so it is worth knowing which
             four carry that weight. -->
        <div class="tab-pane<?php echo $activeTab === 'mapping' ? ' active' : ''; ?>" id="mapping-pane">
            <?php
            // key => [input id, column, placeholder, whether it is an identity field]
            $mapRows = [
                'name'        => ['fAttrName',       'ldap_attr_name',        'displayName',                true],
                'username'    => ['fAttrUsername',   'ldap_attr_username',    'sAMAccountName',             true],
                'email'       => ['fAttrEmail',      'ldap_attr_email',       'mail',                       true],
                'guid'        => ['fAttrGuid',       'ldap_attr_guid',        'objectGUID',                 true],
                'job_title'   => ['fAttrJobTitle',   'ldap_attr_job_title',   'title',                      false],
                'department'  => ['fAttrDepartment', 'ldap_attr_department',  'department',                 false],
                'office'      => ['fAttrOffice',     'ldap_attr_office',      'physicalDeliveryOfficeName', false],
                'phone'       => ['fAttrPhone',      'ldap_attr_phone',       'telephoneNumber',            false],
                'mobile'      => ['fAttrMobile',     'ldap_attr_mobile',      'mobile',                     false],
                'employee_id' => ['fAttrEmployeeId', 'ldap_attr_employee_id', 'employeeID',                 false],
                'manager'     => ['fAttrManager',    'ldap_attr_manager',     'manager',                    false],
            ];
            $renderMapRows = function (bool $identity) use ($mapRows, $p) {
                foreach ($mapRows as $key => [$inputId, $col, $ph, $isIdentity]) {
                    if ($isIdentity !== $identity) continue;
                    ?>
                    <tr data-field="<?php echo $key; ?>">
                        <th scope="row">
                            <?php echo htmlspecialchars(t('system.sso.map_field_' . $key)); ?>
                            <span class="map-hint"><?php echo htmlspecialchars(t('system.sso.map_hint_' . $key)); ?></span>
                        </th>
                        <td><input type="text" id="<?php echo $inputId; ?>" value="<?php echo v($p, $col); ?>" placeholder="<?php echo $ph; ?>"></td>
                        <td class="map-sample">&mdash;</td>
                    </tr>
                    <?php
                }
            };
            ?>
            <div class="fld wide">
                <div class="hint" style="margin-bottom:14px;"><?php echo htmlspecialchars(t('system.sso.map_desc')); ?></div>
                <table class="map">
                    <thead>
                        <tr>
                            <th style="width:32%;"><?php echo htmlspecialchars(t('system.sso.map_col_ours')); ?></th>
                            <th style="width:30%;"><?php echo htmlspecialchars(t('system.sso.map_col_theirs')); ?></th>
                            <th><?php echo htmlspecialchars(t('system.sso.map_col_example')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="map-group"><td colspan="3"><?php echo htmlspecialchars(t('system.sso.map_group_identity')); ?></td></tr>
                        <?php $renderMapRows(true); ?>
                        <tr class="map-group"><td colspan="3"><?php echo htmlspecialchars(t('system.sso.map_group_details')); ?></td></tr>
                        <?php $renderMapRows(false); ?>
                    </tbody>
                </table>
            </div>
            <div class="fld wide">
                <label><?php echo htmlspecialchars(t('system.sso.map_test_heading')); ?></label>
                <div class="hint"><?php echo htmlspecialchars(t('system.sso.map_test_hint')); ?></div>
                <div class="fld-row">
                    <input type="text" id="fMapSample" placeholder="<?php echo htmlspecialchars(t('system.sso.map_test_sample')); ?>">
                    <button class="btn btn-test" id="mapTestBtn" type="button" style="flex:0 0 auto;"><?php echo htmlspecialchars(t('system.sso.map_test')); ?></button>
                </div>
                <div class="result" id="mapResult"></div>
                <div id="mapAvailable"></div>
            </div>
        </div>

        <!-- ================= History ================= -->
        <div class="tab-pane<?php echo $activeTab === 'history' ? ' active' : ''; ?>" id="history-pane">
            <div class="fld">
                <label><?php echo htmlspecialchars(t('system.sso.history_heading')); ?></label>
                <div class="hint"><?php echo htmlspecialchars(t('system.sso.history_hint')); ?></div>
            </div>
            <div id="runsBox"></div>
        </div>
    </div>

    <div class="save-bar">
        <button class="btn btn-primary" id="saveBtn" type="button"><?php echo htmlspecialchars(t('common.save')); ?></button>
        <span id="saveMsg" style="font-size:13px;color:var(--text-dim,#888);"></span>
    </div>
</div>

<script>
const PROVIDER_ID = <?php echo (int)$p['id']; ?>;
const API = '../../api/system/';
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));

/* Tabs. The URL carries the tab so a reload, a bookmark or the back button all
   land where you were — the modal could not do that at all. */
function switchProvTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === id));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.toggle('active', p.id === id + '-pane'));
    history.replaceState(null, '', '?id=' + PROVIDER_ID + '&tab=' + id);
    if (id === 'history') loadRuns();
}

function syncToggle() { $('syncFields').style.display = $('fSyncEnabled').checked ? '' : 'none'; }
$('fSyncEnabled').addEventListener('change', syncToggle);
syncToggle();

/** Everything on the page, as the save endpoint expects it. */
function payload() {
    return {
        id: PROVIDER_ID,
        protocol: 'ldap',
        display_name: $('fDisplayName').value.trim(),
        enabled: $('fEnabled').checked ? 1 : 0,
        auto_create_users: $('fAutoCreate').checked ? 1 : 0,
        tenant_id: $('fTenantId') ? ($('fTenantId').value || null) : null,
        ldap_host: $('fHost').value.trim(),
        ldap_port: parseInt($('fPort').value, 10) || 389,
        ldap_encryption: $('fEncryption').value,
        ldap_bind_dn: $('fBindDn').value.trim(),
        // Blank means "keep the stored one" — the same contract as the modal.
        ldap_bind_password: $('fBindPassword').value,
        ldap_base_dn: $('fBaseDn').value.trim(),
        ldap_user_filter: $('fUserFilter').value.trim(),
        ldap_attr_username: $('fAttrUsername').value.trim(),
        ldap_attr_email: $('fAttrEmail').value.trim(),
        ldap_attr_name: $('fAttrName').value.trim(),
        ldap_attr_guid: $('fAttrGuid').value.trim(),
        ldap_group_base_dn: $('fGroupBaseDn').value.trim(),
        ldap_group_filter: $('fGroupFilter').value.trim(),
        ldap_analyst_group: $('fAnalystGroup').value.trim(),
        ldap_user_group: $('fUserGroup').value.trim(),
        sync_enabled: $('fSyncEnabled').checked ? 1 : 0,
        sync_base_dn: $('fSyncBaseDn').value.trim(),
        sync_filter: $('fSyncFilter').value.trim(),
        sync_on_conflict: $('fSyncOnConflict').value,
        sync_deactivate_after: parseInt($('fSyncDeactivateAfter').value, 10) || 0,
        sync_brake_percent: parseInt($('fSyncBrakePercent').value, 10) || 0,
        ldap_attr_job_title: $('fAttrJobTitle').value.trim(),
        ldap_attr_department: $('fAttrDepartment').value.trim(),
        ldap_attr_office: $('fAttrOffice').value.trim(),
        ldap_attr_phone: $('fAttrPhone').value.trim(),
        ldap_attr_mobile: $('fAttrMobile').value.trim(),
        ldap_attr_employee_id: $('fAttrEmployeeId').value.trim(),
        ldap_attr_manager: $('fAttrManager').value.trim()
    };
}

$('saveBtn').addEventListener('click', async function () {
    this.disabled = true;
    $('saveMsg').textContent = '';
    try {
        const d = await (await fetch(API + 'save_sso_provider.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload())
        })).json();
        if (!d.success) { showToast(d.error || 'Save failed', 'error'); return; }
        // The bind password field is emptied after a save so it goes back to
        // meaning "unchanged" — leaving the typed value in it would send it again
        // on the next save, which is harmless but misleading.
        $('fBindPassword').value = '';
        showToast(window.t('common.saved') || 'Saved', 'success');
    } catch (e) {
        showToast(String(e.message || e), 'error');
    } finally { this.disabled = false; }
});

$('testBtn').addEventListener('click', async function () {
    const box = $('testResult');
    box.className = 'result'; box.textContent = window.t('system.sso.testing') || 'Testing…';
    box.classList.add('ok');
    try {
        const body = Object.assign(payload(), {
            test_user: $('fTestUser').value.trim(),
            test_pass: $('fTestPass').value
        });
        const d = await (await fetch(API + 'test_ldap_connection.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body)
        })).json();
        box.className = 'result ' + (d.success ? 'ok' : 'err');
        box.textContent = d.message || d.error || (d.success ? 'OK' : 'Failed');
    } catch (e) {
        box.className = 'result err'; box.textContent = String(e.message || e);
    }
});

async function runSync(mode) {
    const box = $('syncResult');
    if (mode === 'live') {
        const ok = await showConfirm({
            title:   window.t('system.sso.sync_run'),
            message: window.t('system.sso.sync_confirm'),
            okLabel: window.t('system.sso.sync_run')
        });
        if (!ok) return;
    }
    box.className = 'result ok'; box.textContent = window.t('system.sso.sync_running');
    try {
        const d = await (await fetch(API + 'run_directory_sync.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider_id: PROVIDER_ID, mode: mode })
        })).json();
        if (!d.success) { box.className = 'result err'; box.textContent = d.error || 'Failed'; return; }
        const r = d.run || {};
        const line = [
            window.t('system.sso.sync_found',   { n: r.seen_count }),
            window.t('system.sso.sync_created', { n: r.created_count }),
            window.t('system.sso.sync_updated', { n: Number(r.updated_count) + Number(r.adopted_count) }),
            window.t('system.sso.sync_left',    { n: r.deactivated_count })
        ].join(' · ');
        // 'stopped' is the brake: amber, not red. Nothing broke.
        box.className = 'result ' + (r.status === 'ok' ? 'ok' : (r.status === 'stopped' ? 'warn' : 'err'));
        box.textContent = (mode === 'preview' ? window.t('system.sso.sync_preview_prefix') + '\n' : '')
                        + line + (r.message ? '\n\n' + r.message : '');
        loadRuns();
    } catch (e) {
        box.className = 'result err'; box.textContent = String(e.message || e);
    }
}
$('previewBtn').addEventListener('click', () => runSync('preview'));
$('runBtn').addEventListener('click',   () => runSync('live'));

/* Fill the example column from one real person.
   Sends the values ON THE FORM, so you can check a mapping before committing to
   it. Testing the saved values would only ever confirm the last thing saved. */
$('mapTestBtn').addEventListener('click', async function () {
    const box = $('mapResult');
    box.className = 'result ok';
    box.textContent = window.t('system.sso.map_testing');
    this.disabled = true;
    try {
        const body = Object.assign(payload(), { provider_id: PROVIDER_ID, sample: $('fMapSample').value.trim() });
        const d = await (await fetch(API + 'test_directory_mapping.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body)
        })).json();
        if (!d.success) {
            box.className = 'result err';
            box.textContent = d.error || 'Failed';
            clearSamples();
            return;
        }
        (d.rows || []).forEach(r => {
            const cell = document.querySelector('tr[data-field="' + r.key + '"] .map-sample');
            if (!cell) return;
            if (!r.attribute) {
                // Not mapped at all: neither a success nor a problem.
                cell.className = 'map-sample';
                cell.textContent = window.t('system.sso.map_not_mapped');
            } else if (r.missing) {
                cell.className = 'map-sample missing';
                cell.textContent = window.t('system.sso.map_empty');
            } else {
                cell.className = 'map-sample filled';
                cell.textContent = r.value;
            }
        });
        const bad = (d.rows || []).filter(r => r.attribute && r.missing).length;
        if (d.skipped) {
            // Neither sign-in name nor unique id resolved: the importer would
            // pass this person over. Red, because it is not a gap, it is a miss.
            box.className = 'result err';
            box.textContent = window.t('system.sso.map_result_skipped', { name: d.sample });
        } else {
            box.className = 'result ' + (bad ? 'warn' : 'ok');
            box.textContent = bad
                ? window.t('system.sso.map_result_gaps', { name: d.sample, n: bad })
                : window.t('system.sso.map_result_ok',   { name: d.sample });
        }
        renderAvailable(d.available || []);
    } catch (e) {
        box.className = 'result err'; box.textContent = String(e.message || e);
    } finally { this.disabled = false; }
});

function clearSamples() {
    document.querySelectorAll('td.map-sample').forEach(c => { c.className = 'map-sample'; c.textContent = '—'; });
}

/* Everything the sample person actually carries. An empty example is ambiguous
   on its own — the attribute might be misspelt, or the directory might simply
   not hold that detail. This list is what tells the two apart. */
function renderAvailable(list) {
    const box = $('mapAvailable');
    if (!list.length) { box.innerHTML = ''; return; }
    box.innerHTML = '<details class="avail"><summary>'
        + esc(window.t('system.sso.map_available', { n: list.length }))
        + '</summary><div class="avail-grid">'
        + list.map(a => '<div><code>' + esc(a.name) + '</code> <span>' + esc(a.value) + '</span></div>').join('')
        + '</div></details>';
}

async function loadRuns() {
    const box = $('runsBox');
    box.innerHTML = '';
    try {
        const d = await (await fetch(API + 'get_directory_sync_log.php?provider_id=' + PROVIDER_ID)).json();
        if (!d.success || !(d.runs || []).length) {
            box.innerHTML = '<div class="hint">' + esc(window.t('system.sso.history_none')) + '</div>';
            return;
        }
        box.innerHTML = '<table class="runs"><thead><tr>'
            + ['when','mode','result','found','added','changed','left','issues','by']
                .map(h => '<th>' + esc(window.t('system.sso.hist_' + h) || h) + '</th>').join('')
            + '</tr></thead><tbody>' + d.runs.map(r => {
                const cls = r.status === 'ok' ? 'ok' : (r.status === 'stopped' ? 'stopped' : 'failed');
                return `<tr>
                    <td>${esc(new Date(String(r.started_datetime).replace(' ','T') + 'Z').toLocaleString())}</td>
                    <td>${esc(r.mode)}</td>
                    <td><span class="pill ${cls}">${esc(r.status)}</span></td>
                    <td>${r.seen_count}</td><td>${r.created_count}</td>
                    <td>${Number(r.updated_count) + Number(r.adopted_count)}</td>
                    <td>${r.deactivated_count}</td>
                    <td>${Number(r.conflict_count) + Number(r.error_count)}</td>
                    <td>${esc(r.triggered_by || '—')}</td>
                </tr>` + (r.message ? `<tr><td colspan="9" class="run-msg">${esc(r.message)}</td></tr>` : '');
            }).join('') + '</tbody></table>';
    } catch (e) {
        box.innerHTML = '<div class="hint">' + esc(String(e.message || e)) + '</div>';
    }
}

if (<?php echo json_encode($activeTab); ?> === 'history') loadRuns();
</script>
</body>
</html>
