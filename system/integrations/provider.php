<?php
/**
 * System — one integration provider's connections.
 *
 * Reached as /system/integrations/jira (see .htaccess) or, without mod_rewrite,
 * provider.php?provider=jira.
 *
 * ONE page for every provider rather than a folder each: the form is rendered
 * from the registry's `credential_fields`, so a provider whose auth looks nothing
 * like Jira's needs no change here.
 *
 * ⚠️ The connection list is deliberately NOT filtered by company. This is a
 * CONNECTION-shaped table (tenant_id NULL = shared with every company, set =
 * pinned to one), the same shape as mailboxes and messaging channels — an admin
 * configuring routing needs to see them all. What is gated is writing. See the
 * wiki, Multi-Tenancy-Developer-Guide §1.
 *
 * ⚠️ Secrets never reach this page. The API returns has_credentials as a boolean
 * and never the token, which is what makes the unfiltered list defensible.
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
require_once '../../includes/integrations/integrations.php';

$current_page = 'integrations';
$path_prefix  = '../../';
$translationNamespaces = ['common', 'system'];

$conn = connectToDatabase();

$providerKey = strtolower(trim((string)($_GET['provider'] ?? '')));
$meta        = integrationsProviderMeta($providerKey);
if (!$meta) {
    header('Location: ./');
    exit;
}

$schemaOk     = integrationsSchemaReady($conn);
$multiCompany = function_exists('isMultiTenant') ? isMultiTenant($conn) : false;
$companies    = $multiCompany ? getAllTenants($conn, true) : [];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - <?php echo htmlspecialchars($meta['name']); ?></title>
    <link rel="stylesheet" href="../../assets/css/theme.css?v=22">
    <link rel="stylesheet" href="../../assets/css/inbox.css">
    <style>
        /* Full width, like every other System page. ⚠️ max-width alone is not
           enough — an inherited `margin: … auto` would still centre it, so there
           is deliberately no auto margin here either. */
        .int-container { height: calc(100vh - 48px); overflow-y: auto; padding: 30px 20px; }
        .page-title    { font-size: 24px; font-weight: 600; color: var(--text); margin: 0 0 6px; }
        .page-subtitle { font-size: 14px; color: var(--text-muted); margin: 0 0 8px; line-height: 1.5; }
        .back-link     { display: inline-block; margin-bottom: 18px; font-size: 13px;
                         color: var(--text-muted); text-decoration: none; }
        .back-link:hover { color: var(--sys-accent); }
        .help-link {
            display: inline-flex; align-items: center; gap: 7px; margin: 4px 0 18px;
            font-size: 13px; text-decoration: none; color: var(--sys-accent);
            border: 1px solid var(--sys-accent); border-radius: 20px; padding: 6px 14px;
        }
        .help-link:hover { background: var(--sys-accent-soft); }

        .settings-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 22px; margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px; gap: 12px; flex-wrap: wrap;
        }
        .section-header h3 { margin: 0; font-size: 17px; font-weight: 600; color: var(--text); }
        .card-desc { font-size: 13px; color: var(--text-muted); margin: 0 0 16px; line-height: 1.55; }

        table.int-table { width: 100%; border-collapse: collapse; }
        table.int-table th {
            text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .04em; color: var(--text-faint);
            padding: 8px 10px; border-bottom: 1px solid var(--border);
        }
        table.int-table td {
            padding: 11px 10px; font-size: 14px; color: var(--text);
            border-bottom: 1px solid var(--border-soft);
        }
        .status-badge {
            display: inline-block; font-size: 12px; font-weight: 600;
            padding: 3px 9px; border-radius: 20px;
        }
        .status-badge.on  { background: var(--success-bg); color: var(--text); }
        .status-badge.off { background: var(--surface-2);  color: var(--text-faint); }
        .badge-shared {
            display: inline-block; font-size: 12px; padding: 2px 8px; border-radius: 20px;
            background: var(--sys-accent-soft); color: var(--sys-accent);
        }
        /* Icon actions, matching tickets → settings so the two feel like one app. */
        .action-btn {
            background: none; border: 1px solid var(--border, #ddd);
            color: var(--text-muted, #666); cursor: pointer; padding: 6px;
            margin-right: 4px; border-radius: 4px;
            display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .action-btn:hover {
            background: var(--surface-hover, #f0f0f0);
            border-color: var(--sys-accent); color: var(--sys-accent);
        }
        .action-btn.delete { color: var(--danger-accent, #d13438); }
        .action-btn.delete:hover {
            background: var(--danger-bg, #fdf3f3);
            border-color: var(--danger-accent, #d13438);
            color: var(--danger-text, #a00);
        }
        .action-btn svg { width: 16px; height: 16px; }
        .add-btn {
            background: var(--sys-accent); color: var(--on-accent); border: none;
            border-radius: 6px; padding: 8px 16px; font-size: 14px;
            font-weight: 500; cursor: pointer;
        }
        .empty-row { text-align: center; color: var(--text-faint); padding: 26px 10px; font-size: 14px; }

        /* ⚠️ NOT called .form-row: inbox.css defines that as `display: flex`, which
           puts the label beside the input and squeezes it into a narrow column
           that wraps mid-phrase. Own class, own semantics — cheaper and clearer
           than overriding a shared one. */
        .int-field { margin-bottom: 15px; }
        .int-field label {
            display: block; font-size: 13px; font-weight: 600;
            color: var(--text); margin-bottom: 5px; white-space: nowrap;
        }
        .int-field .hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; line-height: 1.5; }
        .int-field input[type=text], .int-field input[type=password], .int-field select {
            width: 100%; padding: 9px 11px; font-size: 14px;
            background: var(--surface-2); color: var(--text);
            border: 1px solid var(--border); border-radius: 6px;
        }
        .int-field input:focus, .int-field select:focus { outline: none; border-color: var(--sys-accent); }
        .checkbox-row { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; }

        .modal-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
            z-index: 900; align-items: flex-start; justify-content: center; overflow-y: auto;
        }
        .modal-backdrop.open { display: flex; }
        .modal-box {
            background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
            width: 680px; max-width: calc(100% - 24px); margin: 50px 0;
            padding: 24px; box-shadow: var(--shadow);
        }
        .modal-box h3 { margin: 0 0 18px; font-size: 18px; color: var(--text); }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

        /* Mapping screen. Wider than the connection modal because every row is a
           pair — "our thing" on the left, "their thing" on the right — and that
           reads badly squeezed. */
        .map-box { width: 820px; }
        .map-section { margin-bottom: 26px; }
        .map-section h4 {
            margin: 0 0 4px; font-size: 15px; color: var(--text); font-weight: 600;
        }
        .map-section .map-hint {
            font-size: 12px; color: var(--text-muted); margin-bottom: 12px; line-height: 1.5;
        }
        .map-row {
            display: grid; grid-template-columns: 1fr 24px 1fr; align-items: center;
            gap: 10px; margin-bottom: 8px;
        }
        .map-row .map-local { font-size: 14px; color: var(--text); }
        .map-row .map-arrow { text-align: center; color: var(--text-faint); }
        .map-row select, .map-row input[type=text] {
            width: 100%; padding: 7px 10px; border: 1px solid var(--border);
            border-radius: 6px; background: var(--surface); color: var(--text); font-size: 14px;
        }
        .map-row.map-default .map-local { font-style: italic; color: var(--text-muted); }
        .map-empty { font-size: 13px; color: var(--text-muted); }
        /* Sub-headings inside a section. Departments and companies are different
           kinds of rule and one overrides the other, so they are never one flat
           list — the grouping is what makes the precedence visible. */
        .map-group {
            font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
            color: var(--text-muted); font-weight: 600;
            margin: 16px 0 6px; padding-bottom: 4px; border-bottom: 1px solid var(--border-soft);
        }
        .map-group:first-child { margin-top: 0; }
        .btn-primary {
            background: var(--sys-accent); color: var(--on-accent); border: none;
            border-radius: 6px; padding: 9px 18px; font-size: 14px; cursor: pointer;
        }
        .btn-secondary {
            background: var(--surface-2); color: var(--text); border: 1px solid var(--border);
            border-radius: 6px; padding: 9px 18px; font-size: 14px; cursor: pointer;
        }
        .setup-warning {
            background: var(--warning-bg); border: 1px solid var(--border); border-radius: 8px;
            padding: 14px 16px; margin-bottom: 22px; font-size: 14px; color: var(--text);
        }

        /* Mobile: the connection table's columns are known, so it becomes a card
           feed rather than a horizontal scroller (the wide-table rule). */
        @media (max-width: 700px) {
            .int-container { padding: 16px 12px; }
            table.int-table thead { display: none; }
            table.int-table, table.int-table tbody, table.int-table tr, table.int-table td { display: block; width: 100%; }
            table.int-table tr {
                border: 1px solid var(--border); border-radius: 8px;
                margin-bottom: 12px; padding: 10px; background: var(--surface-2);
            }
            table.int-table td { border: none; padding: 5px 2px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="int-container">
        <a class="back-link" href="./">&larr; <?php echo htmlspecialchars(t('system.integrations.title')); ?></a>
        <h1 class="page-title"><?php echo htmlspecialchars($meta['name']); ?></h1>
        <p class="page-subtitle"><?php echo htmlspecialchars(t($meta['blurb'])); ?></p>

        <?php /* Prominent rather than tucked away: somebody who does not already
                 know this tracker can read the mapping screen and have no idea
                 what a project key is. The pretty URL is /<provider>/help. */ ?>
        <a class="help-link" href="./<?php echo htmlspecialchars($providerKey); ?>/help">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true"><circle cx="12" cy="12" r="10"></circle>
                 <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <?php echo htmlspecialchars(t('system.integrations.help_link', ['name' => $meta['name']])); ?>
        </a>

        <?php if (!$schemaOk): ?>
            <div class="setup-warning"><?php echo htmlspecialchars(t('system.integrations.needs_db_verify')); ?></div>
        <?php endif; ?>

        <div class="settings-card">
            <div class="section-header">
                <h3><?php echo htmlspecialchars(t('system.integrations.connections_heading')); ?></h3>
                <button class="add-btn" id="addBtn"><?php echo htmlspecialchars(t('common.add')); ?></button>
            </div>
            <p class="card-desc"><?php echo htmlspecialchars(t('system.integrations.connections_desc')); ?></p>

            <table class="int-table">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars(t('system.integrations.col_name')); ?></th>
                        <th><?php echo htmlspecialchars(t('system.integrations.col_url')); ?></th>
                        <?php if ($multiCompany): ?>
                            <th><?php echo htmlspecialchars(t('system.integrations.col_company')); ?></th>
                        <?php endif; ?>
                        <th><?php echo htmlspecialchars(t('system.integrations.col_status')); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="connRows">
                    <tr><td class="empty-row" colspan="5"><?php echo htmlspecialchars(t('common.loading')); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add / edit connection -->
    <div class="modal-backdrop" id="connModal">
        <div class="modal-box">
            <h3 id="modalTitle"><?php echo htmlspecialchars(t('system.integrations.add_heading')); ?></h3>
            <input type="hidden" id="connId" value="">

            <div class="int-field">
                <label for="connName"><?php echo htmlspecialchars(t('system.integrations.col_name')); ?></label>
                <input type="text" id="connName" placeholder="<?php echo htmlspecialchars($meta['name']); ?>">
            </div>

            <div class="int-field">
                <label for="connUrl"><?php echo htmlspecialchars(t($meta['url_label'])); ?></label>
                <input type="text" id="connUrl" placeholder="<?php echo htmlspecialchars($meta['url_hint']); ?>">
            </div>

            <?php foreach ($meta['credential_fields'] as $f): ?>
                <div class="int-field">
                    <label for="cred_<?php echo htmlspecialchars($f['key']); ?>">
                        <?php echo htmlspecialchars(t($f['label'])); ?>
                    </label>
                    <input type="<?php echo $f['type'] === 'password' ? 'password' : 'text'; ?>"
                           id="cred_<?php echo htmlspecialchars($f['key']); ?>"
                           data-cred="<?php echo htmlspecialchars($f['key']); ?>"
                           autocomplete="new-password">
                    <?php if ($f['key'] === 'email'): ?>
                        <div class="hint"><?php echo htmlspecialchars(t('system.integrations.field_email_hint')); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="hint" id="credKeepHint" style="display:none;margin:-8px 0 14px;font-size:12px;color:var(--text-muted);">
                <?php echo htmlspecialchars(t('system.integrations.creds_keep_hint')); ?>
            </div>

            <?php if ($multiCompany): ?>
                <div class="int-field">
                    <label for="connTenant"><?php echo htmlspecialchars(t('system.integrations.col_company')); ?></label>
                    <select id="connTenant">
                        <option value=""><?php echo htmlspecialchars(t('system.integrations.company_shared')); ?></option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint"><?php echo htmlspecialchars(t('system.integrations.company_hint')); ?></div>
                </div>
            <?php endif; ?>

            <div class="checkbox-row">
                <input type="checkbox" id="connActive" checked>
                <label for="connActive" style="margin:0;"><?php echo htmlspecialchars(t('system.integrations.active_label')); ?></label>
            </div>

            <div class="checkbox-row">
                <input type="checkbox" id="connInbound">
                <label for="connInbound" style="margin:0;"><?php echo htmlspecialchars(t('system.integrations.inbound_label', ['name' => $meta['name']])); ?></label>
            </div>
            <div class="hint" style="margin:-10px 0 15px 26px;">
                <?php echo htmlspecialchars(t('system.integrations.inbound_hint', ['name' => $meta['name']])); ?>
            </div>

            <div class="checkbox-row">
                <input type="checkbox" id="connAttach" checked>
                <label for="connAttach" style="margin:0;"><?php echo htmlspecialchars(t('system.integrations.attach_label', ['name' => $meta['name']])); ?></label>
            </div>
            <div class="hint" style="margin:-10px 0 15px 26px;">
                <?php echo htmlspecialchars(t('system.integrations.attach_hint')); ?>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" id="cancelBtn"><?php echo htmlspecialchars(t('common.cancel')); ?></button>
                <button class="btn-secondary" id="testBtn"><?php echo htmlspecialchars(t('system.integrations.test')); ?></button>
                <button class="btn-primary"   id="saveBtn"><?php echo htmlspecialchars(t('common.save')); ?></button>
            </div>
        </div>
    </div>

    <?php /* Mapping (V3). Its own modal rather than a tab inside the connection
             one: it is only reachable once a connection exists and has been
             tested, since every dropdown here is filled from the tracker's own
             API. Putting it in the same modal would mean a half-created
             connection showing empty dropdowns it cannot explain. */ ?>
    <div class="modal-backdrop" id="mapModal">
        <div class="modal-box map-box">
            <h3 id="mapTitle"><?php echo htmlspecialchars(t('system.integrations.mapping_title')); ?></h3>
            <div class="hint" style="margin:-8px 0 20px;">
                <?php echo htmlspecialchars(t('system.integrations.mapping_intro', ['name' => $meta['name']])); ?>
                <?php /* Deep-linked to the mapping step: this modal is precisely
                         where somebody who does not know the tracker's vocabulary
                         gets stuck, so the way out is offered in place. */ ?>
                <a href="./<?php echo htmlspecialchars($providerKey); ?>/help#step5" target="_blank" rel="noopener"
                   style="color:var(--sys-accent);text-decoration:none;white-space:nowrap;">
                    <?php echo htmlspecialchars(t('system.integrations.mapping_help_link')); ?> &rarr;
                </a>
            </div>

            <div id="mapBody"><div class="map-empty"><?php echo htmlspecialchars(t('common.loading')); ?></div></div>

            <div class="modal-actions">
                <button class="btn-secondary" id="mapCancelBtn"><?php echo htmlspecialchars(t('common.cancel')); ?></button>
                <button class="btn-primary"   id="mapSaveBtn"><?php echo htmlspecialchars(t('common.save')); ?></button>
            </div>
        </div>
    </div>

<script>
const PROVIDER   = <?php echo json_encode($providerKey); ?>;
const MULTI      = <?php echo $multiCompany ? 'true' : 'false'; ?>;
const API        = '../../api/integrations/';
const CRED_KEYS  = <?php echo json_encode(array_column($meta['credential_fields'], 'key')); ?>;
const T = <?php echo json_encode([
    'shared'    => t('system.integrations.company_shared'),
    'active'    => t('system.integrations.active_label'),
    'inactive'  => t('system.integrations.inactive_label'),
    'inboundOn' => t('system.integrations.inbound_badge'),
    'loading'      => t('common.loading'),
    'mapping'      => t('system.integrations.mapping_title'),
    'mapProjects'  => t('system.integrations.map_projects'),
    'mapProjectsHint' => t('system.integrations.map_projects_hint'),
    'mapTypes'     => t('system.integrations.map_types'),
    'mapTypesHint' => t('system.integrations.map_types_hint'),
    'mapPriorities'     => t('system.integrations.map_priorities'),
    'mapPrioritiesHint' => t('system.integrations.map_priorities_hint'),
    'mapDefault'   => t('system.integrations.map_default'),
    'mapGroupDefault' => t('system.integrations.map_group_default'),
    'mapGroupDept'    => t('system.integrations.map_group_dept'),
    'mapGroupCompany' => t('system.integrations.map_group_company'),
    'mapNone'      => t('system.integrations.map_none'),
    'mapSaved'     => t('system.integrations.map_saved'),
    'mapNeedsVerify' => t('system.integrations.map_needs_verify'),
    'mapLoadFailed'  => t('system.integrations.map_load_failed'),
    'edit'      => t('common.edit'),
    'delete'    => t('common.delete'),
    'none'      => t('system.integrations.no_connections'),
    'cancel'    => t('common.cancel'),
    'confirmDel'      => t('system.integrations.confirm_delete'),
    'confirmDelNamed' => t('system.integrations.confirm_delete_named'),
    'deleteTitle'     => t('system.integrations.delete_title'),
    'deleted'   => t('system.integrations.deleted'),
    'saved'     => t('system.integrations.saved'),
    'addTitle'  => t('system.integrations.add_heading'),
    'editTitle' => t('system.integrations.edit_heading'),
    'saveFail'  => t('system.integrations.save_failed'),
]); ?>;

// Same pencil and bin as tickets → settings.
const ICON_EDIT = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
const ICON_DELETE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
// Two arrows crossing — "our word becomes theirs".
const ICON_MAP = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>';

const $ = id => document.getElementById(id);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

let rows = [];

async function load() {
    const r = await fetch(API + 'list_connections.php?provider=' + encodeURIComponent(PROVIDER));
    const j = await r.json().catch(() => ({}));
    rows = (j && j.success && j.connections) ? j.connections : [];
    render();
}

function render() {
    const tb = $('connRows');
    const cols = MULTI ? 5 : 4;
    if (!rows.length) {
        tb.innerHTML = '<tr><td class="empty-row" colspan="' + cols + '">' + esc(T.none) + '</td></tr>';
        return;
    }
    tb.innerHTML = rows.map(c => {
        const company = c.tenant_id
            ? esc(c.tenant_name || ('#' + c.tenant_id))
            : '<span class="badge-shared">' + esc(T.shared) + '</span>';
        return '<tr>'
            + '<td>' + esc(c.name) + '</td>'
            + '<td>' + esc(c.base_url) + '</td>'
            + (MULTI ? '<td>' + company + '</td>' : '')
            // Whether updates are accepted was previously only visible by opening
            // the connection, so "is it even on?" meant a click per row. It is a
            // status, so it belongs in the status cell.
            + '<td><span class="status-badge ' + (c.is_active ? 'on' : 'off') + '">'
                + esc(c.is_active ? T.active : T.inactive) + '</span>'
                + (c.inbound_enabled ? ' <span class="status-badge on">' + esc(T.inboundOn) + '</span>' : '')
            + '</td>'
            + '<td style="text-align:right;white-space:nowrap;">'
                + '<button class="action-btn" data-map="' + c.id + '" title="' + esc(T.mapping) + '" aria-label="' + esc(T.mapping) + '">'
                    + ICON_MAP + '</button>'
                + '<button class="action-btn" data-edit="' + c.id + '" title="' + esc(T.edit) + '" aria-label="' + esc(T.edit) + '">'
                    + ICON_EDIT + '</button>'
                + '<button class="action-btn delete" data-del="' + c.id + '" title="' + esc(T.delete) + '" aria-label="' + esc(T.delete) + '">'
                    + ICON_DELETE + '</button>'
            + '</td>'
        + '</tr>';
    }).join('');
}

function openModal(conn) {
    // Never carry one connection's discovered identity onto another.
    lastTest = {account_identity: null, flavour: null};
    $('connId').value   = conn ? conn.id : '';
    $('connName').value = conn ? conn.name : '';
    $('connUrl').value  = conn ? conn.base_url : '';
    if (MULTI) $('connTenant').value = (conn && conn.tenant_id) ? String(conn.tenant_id) : '';
    $('connActive').checked = conn ? !!conn.is_active : true;
    // Inbound writes to tickets, so it stays OFF on a new connection until an
    // admin deliberately turns it on.
    $('connInbound').checked = conn ? !!conn.inbound_enabled : false;
    // Defaults ON for a new connection: the screenshot is usually the bug report.
    $('connAttach').checked = conn ? !!conn.send_attachments : true;
    CRED_KEYS.forEach(k => { const el = document.querySelector('[data-cred="' + k + '"]'); if (el) el.value = ''; });
    // Editing never re-sends the stored secret, so an empty box means "keep what
    // is there" rather than "clear it" — say so instead of leaving them guessing.
    $('credKeepHint').style.display = (conn && conn.has_credentials) ? 'block' : 'none';
    $('modalTitle').textContent = conn ? T.editTitle : T.addTitle;
    $('connModal').classList.add('open');
}

// What a successful Test on this form discovered. Held here so Save can carry it
// through — testing before saving is the natural flow, and without this the
// identity found there would be discarded and the saved connection would have
// none. Cleared whenever the modal reopens, so it can never leak between rows.
let lastTest = {account_identity: null, flavour: null};

function payload() {
    const creds = {};
    CRED_KEYS.forEach(k => {
        const el = document.querySelector('[data-cred="' + k + '"]');
        if (el && el.value !== '') creds[k] = el.value;
    });
    return {
        id: $('connId').value || null,
        provider: PROVIDER,
        name: $('connName').value,
        base_url: $('connUrl').value,
        credentials: creds,
        tenant_id: MULTI ? ($('connTenant').value || null) : null,
        is_active: $('connActive').checked ? 1 : 0,
        inbound_enabled: $('connInbound').checked ? 1 : 0,
        send_attachments: $('connAttach').checked ? 1 : 0,
        account_identity: lastTest.account_identity,
        flavour: lastTest.flavour
    };
}

async function post(endpoint, body) {
    const r = await fetch(API + endpoint, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    });
    return await r.json().catch(() => ({success: false, error: T.saveFail}));
}

$('addBtn').addEventListener('click', () => openModal(null));
$('cancelBtn').addEventListener('click', () => $('connModal').classList.remove('open'));
$('connModal').addEventListener('click', e => { if (e.target.id === 'connModal') $('connModal').classList.remove('open'); });

// ---------------------------------------------------------------- mapping
//
// Three sections, each the same shape: a fixed list of OUR values down the left,
// and what each becomes in the tracker on the right. The right-hand options are
// fetched from the tracker on demand — projects and priorities are site-wide,
// but issue types are per project, so they are loaded from whichever project the
// default row points at and offered as suggestions rather than a closed list.

let mapConnectionId = null;
let mapState = {};          // {map_type: {local_key: external_key}}
let mapOptions = {projects: [], priorities: [], issue_types: []};

async function getJSON(qs) {
    const r = await fetch(API + qs);
    return await r.json().catch(() => ({success: false, error: T.mapLoadFailed}));
}

async function openMapping(connectionId) {
    mapConnectionId = connectionId;
    $('mapBody').innerHTML = '<div class="map-empty">' + esc(T.loading) + '</div>';
    $('mapModal').classList.add('open');

    const data = await getJSON('get_mapping.php?connection_id=' + encodeURIComponent(connectionId));
    if (!data.success) { $('mapBody').innerHTML = '<div class="map-empty">' + esc(data.error) + '</div>'; return; }
    if (!data.schema_ready) { $('mapBody').innerHTML = '<div class="map-empty">' + esc(T.mapNeedsVerify) + '</div>'; return; }

    mapState = data.maps || {};
    const local = data.local || {};

    // Ask the tracker what it offers. A failure here is not fatal — the row
    // falls back to a free-text box, so an unreachable tracker still lets an
    // admin type a project key rather than blocking the screen entirely.
    const [projRes, priRes] = await Promise.all([
        getJSON('tracker_options.php?what=projects&connection_id=' + connectionId),
        getJSON('tracker_options.php?what=priorities&connection_id=' + connectionId)
    ]);
    mapOptions.projects   = projRes.success ? projRes.items : [];
    mapOptions.priorities = priRes.success  ? priRes.items  : [];

    // Issue types depend on a project, so use the default routing row's project
    // as the sample. Suggestions, never a closed list — another project on the
    // same site may legitimately offer different types.
    const sampleProject = (mapState.project && mapState.project['*']) || (mapOptions.projects[0] || {}).key || '';
    if (sampleProject) {
        const itRes = await getJSON('tracker_options.php?what=issue_types&project='
            + encodeURIComponent(sampleProject) + '&connection_id=' + connectionId);
        mapOptions.issue_types = itRes.success ? itRes.items : [];
    }
    renderMapping(local);
}

/** One row: our label on the left, their value on the right. */
function mapRow(type, key, label, options, isDefault) {
    const current = (mapState[type] && mapState[type][key]) || '';
    let control;
    if (options.length) {
        control = '<select data-map-type="' + type + '" data-map-key="' + esc(key) + '">'
                + '<option value="">' + esc(T.mapNone) + '</option>'
                + options.map(o => {
                      const v = o.key || o.name;
                      return '<option value="' + esc(v) + '"' + (v === current ? ' selected' : '') + '>'
                           + esc(o.name + (o.key && o.key !== o.name ? ' (' + o.key + ')' : '')) + '</option>';
                  }).join('')
                // A value saved earlier that the tracker no longer offers must not
                // vanish silently on the next save — keep it selectable.
                + (current && !options.some(o => (o.key || o.name) === current)
                    ? '<option value="' + esc(current) + '" selected>' + esc(current) + '</option>' : '')
                + '</select>';
    } else {
        control = '<input type="text" data-map-type="' + type + '" data-map-key="' + esc(key) + '"'
                + ' value="' + esc(current) + '" placeholder="' + esc(T.mapNone) + '">';
    }
    return '<div class="map-row' + (isDefault ? ' map-default' : '') + '">'
         + '<span class="map-local">' + esc(label) + '</span>'
         + '<span class="map-arrow">→</span>' + control + '</div>';
}

function renderMapping(local) {
    const section = (title, hint, rows) =>
        '<div class="map-section"><h4>' + esc(title) + '</h4>'
        + '<div class="map-hint">' + esc(hint) + '</div>' + rows + '</div>';

    // ⚠️ Departments and companies are DIFFERENT KINDS of thing and one beats
    // the other, so they are never rendered as one flat list — that reads as a
    // single set of equals and hides the precedence the whole design rests on.
    // Grouped and labelled instead: the default first (the only row most
    // installs ever fill in), then the exceptions that override it.
    const group = title => '<div class="map-group">' + esc(title) + '</div>';

    let projRows = group(T.mapGroupDefault)
                 + mapRow('project', '*', T.mapDefault, mapOptions.projects, true);

    if ((local.departments || []).length) {
        projRows += group(T.mapGroupDept);
        local.departments.forEach(d =>
            projRows += mapRow('project', 'dept:' + d.id, d.name, mapOptions.projects, false));
    }
    if (MULTI && (local.companies || []).length) {
        projRows += group(T.mapGroupCompany);
        local.companies.forEach(c =>
            projRows += mapRow('project', 'tenant:' + c.id, c.name, mapOptions.projects, false));
    }

    let typeRows = mapRow('issue_type', '*', T.mapDefault, mapOptions.issue_types, true);
    (local.ticket_types || []).forEach(tt => {
        // Ticket types are the only company-scoped list here (tenant_id NULL =
        // every company). Say which company a scoped one belongs to, or two
        // companies with a similarly named type are indistinguishable. Pointless
        // noise on a single-company install, so only when MULTI.
        const label = (MULTI && tt.tenant_name) ? tt.name + ' (' + tt.tenant_name + ')' : tt.name;
        typeRows += mapRow('issue_type', String(tt.id), label, mapOptions.issue_types, false);
    });

    // ⚠️ No default row for priorities, on purpose — "every priority is Highest"
    // would mark a dev team's whole backlog urgent. An unmapped priority simply
    // travels as text in the description, as it always did.
    let priRows = (local.priorities || [])
        .map(p => mapRow('priority', String(p.id), p.name, mapOptions.priorities, false)).join('');
    if (!priRows) priRows = '<div class="map-empty">' + esc(T.mapNone) + '</div>';

    $('mapBody').innerHTML =
          section(T.mapProjects,   T.mapProjectsHint,   projRows)
        + section(T.mapTypes,      T.mapTypesHint,      typeRows)
        + section(T.mapPriorities, T.mapPrioritiesHint, priRows);
}

$('mapCancelBtn').addEventListener('click', () => $('mapModal').classList.remove('open'));
$('mapModal').addEventListener('click', e => { if (e.target.id === 'mapModal') $('mapModal').classList.remove('open'); });

$('mapSaveBtn').addEventListener('click', async () => {
    const maps = {project: {}, issue_type: {}, priority: {}};
    $('mapBody').querySelectorAll('[data-map-type]').forEach(el => {
        const v = (el.value || '').trim();
        if (v) maps[el.getAttribute('data-map-type')][el.getAttribute('data-map-key')] = v;
    });
    const res = await post('save_mapping.php', {connection_id: mapConnectionId, maps: maps});
    if (!res.success) { showToast(res.error || T.saveFail, 'error'); return; }
    showToast(T.mapSaved, 'success');
    $('mapModal').classList.remove('open');
});

$('connRows').addEventListener('click', e => {
    // closest(), not e.target: the button now contains an <svg>, so a click lands
    // on a <path> and reading the attribute off e.target would find nothing.
    const mapBtn  = e.target.closest('[data-map]');
    if (mapBtn) { openMapping(mapBtn.getAttribute('data-map')); return; }
    const editBtn = e.target.closest('[data-edit]');
    const delBtn  = e.target.closest('[data-del]');
    if (editBtn) {
        openModal(rows.find(r => String(r.id) === editBtn.getAttribute('data-edit')));
        return;
    }
    if (!delBtn) return;

    const id  = delBtn.getAttribute('data-del');
    const row = rows.find(r => String(r.id) === id);

    // The app-wide confirm (assets/js/confirm.js, loaded by the waffle menu),
    // not the browser's — so it is themed, translated and looks like the rest of
    // FreeITSM.
    showConfirm({
        title: T.deleteTitle,
        message: (row ? T.confirmDelNamed.replace('{name}', row.name) : T.confirmDel),
        okLabel: T.delete,
        cancelLabel: T.cancel,
        okClass: 'danger',
        onConfirm: async () => {
            const j = await post('delete_connection.php', {id: id});
            // The endpoint refuses while tickets are still linked, and its reason
            // is the useful part — surface it rather than a generic failure.
            if (j.success) showToast(T.deleted, 'success');
            else           showToast(j.error || T.saveFail, 'error');
            load();
        }
    });
});

$('saveBtn').addEventListener('click', async () => {
    const j = await post('save_connection.php', payload());
    if (j.success) {
        $('connModal').classList.remove('open');
        showToast(T.saved, 'success');
        load();
    } else {
        showToast(j.error || T.saveFail, 'error');
    }
});

$('testBtn').addEventListener('click', async () => {
    const btn = $('testBtn');
    btn.disabled = true;
    try {
        const j = await post('test_connection.php', payload());
        if (j.success) {
            lastTest = {account_identity: j.account_identity || null, flavour: j.flavour || null};
        }
        // The provider's own message is the useful part — "Connected to Jira Cloud
        // as FreeITSM Bot", or "Jira rejected the credentials". Pass it straight
        // through instead of flattening it to pass/fail.
        showToast(j.success ? j.detail : (j.error || T.saveFail), j.success ? 'success' : 'error');
    } finally {
        btn.disabled = false;
    }
});

load();
</script>
</body>
</html>
