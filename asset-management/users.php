<?php
/**
 * Who holds what — every person with equipment assigned to them, and a
 * handover document for any of them (discussion #56).
 *
 * Deliberately mirrors tickets/users.php: search a list of people on the left,
 * click one, see their things on the right. That page already taught everybody
 * this shape, so this is the same shape with assets in it rather than tickets.
 */
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/i18n.php';
require_once '../includes/theme.php';
I18n::initFromSession();

requireModuleAccess('assets');

$current_page = 'users';
$translationNamespaces = ['common', 'asset-management'];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('asset-management.users.page_title')); ?></title>
    <link rel="stylesheet" href="../assets/css/theme.css?v=23">
    <link rel="stylesheet" href="../assets/css/inbox.css?v=37">
    <script>window.translations = <?php echo json_encode(I18n::exportForJs($translationNamespaces), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script src="../assets/js/i18n.js?v=2"></script>
    <style>
        body { margin: 0; background: var(--bg, #f4f6f8); }
        .au-wrap {
            display: grid;
            grid-template-columns: 330px 1fr;
            gap: 16px;
            padding: 16px;
            align-items: start;
        }
        .au-panel {
            background: var(--surface, #fff);
            border: 1px solid var(--border, #e0e0e0);
            border-radius: 8px;
            overflow: hidden;
        }
        .au-panel-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border, #eee);
            font-weight: 600;
            color: var(--text, #333);
        }
        .au-search { padding: 12px 16px; border-bottom: 1px solid var(--border, #eee); }
        .au-search input {
            width: 100%; box-sizing: border-box;
            padding: 8px 10px;
            border: 1px solid var(--border, #d5dbe1);
            border-radius: 5px;
            background: var(--input-bg, #fff);
            color: var(--text, #333);
            font-size: 14px;
        }
        .au-list { max-height: calc(100vh - 220px); overflow-y: auto; }
        .au-person {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-soft, #f2f2f2);
            cursor: pointer;
            transition: background 150ms ease;
        }
        .au-person:hover { background: var(--surface-hover, #f6f6f6); }
        .au-person.selected { background: var(--surface-hover, #eef4fb); box-shadow: inset 3px 0 0 var(--accent, #0078d4); }
        .au-person-name { font-size: 14px; font-weight: 600; color: var(--text, #333); }
        .au-person-email { font-size: 12px; color: var(--text-muted, #666); overflow-wrap: anywhere; }
        .au-count {
            flex-shrink: 0;
            min-width: 22px; padding: 1px 7px; border-radius: 10px;
            background: var(--accent, #0078d4); color: #fff;
            font-size: 11px; font-weight: 700; text-align: center;
        }
        .au-detail-head {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
            padding: 16px;
            border-bottom: 1px solid var(--border, #eee);
        }
        .au-detail-name { font-size: 18px; font-weight: 700; color: var(--text, #333); }
        .au-detail-sub { font-size: 13px; color: var(--text-muted, #666); }
        .au-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .au-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 5px; border: 1px solid var(--border, #d5dbe1);
            background: var(--surface, #fff); color: var(--text, #333);
            font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
            transition: background 150ms ease, transform 140ms cubic-bezier(0.23,1,0.32,1);
        }
        .au-btn:hover { background: var(--surface-hover, #f6f6f6); }
        .au-btn:active { transform: scale(0.97); }
        .au-btn.primary { background: var(--accent, #0078d4); border-color: var(--accent, #0078d4); color: #fff; }
        table.au-table { width: 100%; border-collapse: collapse; }
        table.au-table th {
            text-align: left; padding: 10px 16px;
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px;
            color: var(--text-muted, #666); border-bottom: 1px solid var(--border, #eee);
            white-space: nowrap;
        }
        table.au-table td {
            padding: 10px 16px; font-size: 13px;
            border-bottom: 1px solid var(--border-soft, #f4f4f4);
            color: var(--text, #333);
        }
        .au-type-pill {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            background: var(--surface-hover, #eef1f4); color: var(--text-muted, #555);
            font-size: 11px; font-weight: 600;
        }
        .au-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
        .au-empty { padding: 44px 20px; text-align: center; color: var(--text-muted, #666); }
        .au-empty-title { font-size: 15px; font-weight: 600; margin-bottom: 4px; color: var(--text, #333); }
        @media (max-width: 900px) {
            /* One column below tablet — a 330px sidebar beside a table does not
               fit, and a horizontally scrolling page is worse than stacking. */
            .au-wrap { grid-template-columns: 1fr; }
            .au-list { max-height: 320px; }
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="au-wrap">
    <div class="au-panel">
        <div class="au-panel-head"><?php echo htmlspecialchars(t('asset-management.users.list_title')); ?></div>
        <div class="au-search">
            <input type="search" id="auSearch" placeholder="<?php echo htmlspecialchars(t('asset-management.users.search_placeholder')); ?>" autocomplete="off">
        </div>
        <div class="au-list" id="auList"></div>
    </div>

    <div class="au-panel" id="auDetail">
        <div class="au-empty">
            <div class="au-empty-title"><?php echo htmlspecialchars(t('asset-management.users.select_title')); ?></div>
            <div><?php echo htmlspecialchars(t('asset-management.users.select_hint')); ?></div>
        </div>
    </div>
</div>

<script>
const API = '../api/assets/';
let people = [];
let selectedId = null;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));

function fmtDate(d) {
    if (!d) return '—';
    // Stored as UTC without a zone marker; without the Z, Safari and Firefox
    // read it as local and the date can slip by a day.
    const dt = new Date(String(d).replace(' ', 'T') + 'Z');
    return isNaN(dt) ? '—' : dt.toLocaleDateString();
}

async function loadPeople(search) {
    const list = document.getElementById('auList');
    list.innerHTML = '<div class="au-empty">' + esc(window.t('asset-management.users.loading')) + '</div>';
    try {
        const url = API + 'get_users_with_assets.php' + (search ? '?search=' + encodeURIComponent(search) : '');
        const d = await (await fetch(url)).json();
        if (!d.success) throw new Error(d.error || 'error');
        people = d.users || [];
        renderPeople();
    } catch (e) {
        list.innerHTML = '<div class="au-empty">' + esc(window.t('asset-management.users.load_failed')) + '</div>';
    }
}

function renderPeople() {
    const list = document.getElementById('auList');
    if (!people.length) {
        list.innerHTML = '<div class="au-empty">' + esc(window.t('asset-management.users.none')) + '</div>';
        return;
    }
    list.innerHTML = people.map(p => `
        <div class="au-person ${selectedId === p.id ? 'selected' : ''}" onclick="selectPerson(${p.id})">
            <div>
                <div class="au-person-name">${esc(p.name)}</div>
                <div class="au-person-email">${esc(p.email || '')}</div>
            </div>
            <span class="au-count">${p.asset_count}</span>
        </div>`).join('');
}

async function selectPerson(id) {
    selectedId = id;
    renderPeople();
    const panel = document.getElementById('auDetail');
    panel.innerHTML = '<div class="au-empty">' + esc(window.t('asset-management.users.loading')) + '</div>';
    try {
        const d = await (await fetch(API + 'get_user_assets.php?user_id=' + encodeURIComponent(id))).json();
        if (!d.success) throw new Error(d.error || 'error');
        renderDetail(d.user, d.assets || []);
    } catch (e) {
        panel.innerHTML = '<div class="au-empty">' + esc(window.t('asset-management.users.load_failed')) + '</div>';
    }
}

function renderDetail(user, assets) {
    const panel = document.getElementById('auDetail');
    const rows = assets.length ? assets.map(a => `
        <tr>
            <td>${a.asset_type ? '<span class="au-type-pill">' + esc(a.asset_type) + '</span>' : '—'}</td>
            <td><strong>${esc(a.hostname || '—')}</strong></td>
            <td>${esc([a.manufacturer, a.model].filter(Boolean).join(' ') || '—')}</td>
            <td class="au-mono">${esc(a.service_tag || '—')}</td>
            <td class="au-mono">${esc(a.asset_tag || '—')}</td>
            <td>${esc(fmtDate(a.assigned_datetime))}</td>
        </tr>`).join('')
        : `<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted,#666);">
             ${esc(window.t('asset-management.users.no_assets'))}</td></tr>`;

    panel.innerHTML = `
        <div class="au-detail-head">
            <div>
                <div class="au-detail-name">${esc(user.name)}</div>
                <div class="au-detail-sub">${esc(user.email || '')}</div>
                <div class="au-detail-sub">${esc(window.t('asset-management.users.holding', { n: assets.length }))}</div>
            </div>
            <div class="au-actions">
                <a class="au-btn primary" href="handover.php?user_id=${user.id}" target="_blank" rel="noopener">
                    ${esc(window.t('asset-management.users.handover'))}
                </a>
            </div>
        </div>
        <table class="au-table">
            <thead><tr>
                <th>${esc(window.t('asset-management.users.col_type'))}</th>
                <th>${esc(window.t('asset-management.users.col_name'))}</th>
                <th>${esc(window.t('asset-management.users.col_model'))}</th>
                <th>${esc(window.t('asset-management.users.col_serial'))}</th>
                <th>${esc(window.t('asset-management.users.col_tag'))}</th>
                <th>${esc(window.t('asset-management.users.col_assigned'))}</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
}

// Debounced so typing does not fire a request per keystroke.
let searchTimer = null;
document.getElementById('auSearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const v = this.value.trim();
    searchTimer = setTimeout(() => loadPeople(v), 250);
});

loadPeople('');
</script>
</body>
</html>
