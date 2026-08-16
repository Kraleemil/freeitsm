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
    ['id' => 'history',    'cap' => null, 'label' => t('system.sso.tab_history')],
];
$activeTab = in_array($_GET['tab'] ?? '', ['connection','signin','import','history'], true)
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
        body { --accent: var(--sys-accent, #546e7a); --accent-hover: var(--sys-accent-hover, #37474f); --on-accent: var(--sys-on-accent, #fff); margin: 0; background: var(--app-bg, #f5f5f5); }
        .prov-wrap { padding: 24px 20px 60px; max-width: 980px; margin: 0 auto; }
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
        .save-bar { position: sticky; bottom: 0; margin-top: 20px; padding: 14px 0; background: var(--app-bg, #f5f5f5); display: flex; gap: 10px; align-items: center; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
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
            <div class="fld">
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
            <div class="fld">
                <label><?php echo htmlspecialchars(t('system.sso.field_ldap_attrs')); ?></label>
                <div class="hint"><?php echo htmlspecialchars(t('system.sso.field_ldap_attrs_hint')); ?></div>
                <div class="attr-grid">
                    <input type="text" id="fAttrUsername" value="<?php echo v($p, 'ldap_attr_username'); ?>" placeholder="sAMAccountName">
                    <input type="text" id="fAttrEmail"    value="<?php echo v($p, 'ldap_attr_email'); ?>"    placeholder="mail">
                    <input type="text" id="fAttrName"     value="<?php echo v($p, 'ldap_attr_name'); ?>"     placeholder="displayName">
                    <input type="text" id="fAttrGuid"     value="<?php echo v($p, 'ldap_attr_guid'); ?>"     placeholder="objectGUID">
                </div>
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
                    <label><?php echo htmlspecialchars(t('system.sso.sync_attrs')); ?></label>
                    <div class="hint"><?php echo htmlspecialchars(t('system.sso.sync_attrs_hint')); ?></div>
                    <div class="attr-grid">
                        <input type="text" id="fAttrJobTitle"   value="<?php echo v($p, 'ldap_attr_job_title'); ?>"   placeholder="title">
                        <input type="text" id="fAttrDepartment" value="<?php echo v($p, 'ldap_attr_department'); ?>" placeholder="department">
                        <input type="text" id="fAttrOffice"     value="<?php echo v($p, 'ldap_attr_office'); ?>"     placeholder="physicalDeliveryOfficeName">
                        <input type="text" id="fAttrPhone"      value="<?php echo v($p, 'ldap_attr_phone'); ?>"      placeholder="telephoneNumber">
                        <input type="text" id="fAttrMobile"     value="<?php echo v($p, 'ldap_attr_mobile'); ?>"     placeholder="mobile">
                        <input type="text" id="fAttrEmployeeId" value="<?php echo v($p, 'ldap_attr_employee_id'); ?>" placeholder="employeeID">
                        <input type="text" id="fAttrManager"    value="<?php echo v($p, 'ldap_attr_manager'); ?>"    placeholder="manager">
                    </div>
                </div>
                <div class="fld">
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
