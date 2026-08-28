<?php
/**
 * Service Status Module - Dashboard
 * Shows service board with worst current impact + recent incidents
 */
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/i18n.php';
require_once '../includes/theme.php';
require_once '../includes/timezone.php';
requireModuleAccess('service-status');
I18n::initFromSession();
Tz::init();

$current_page = 'dashboard';
$path_prefix = '../';
$translationNamespaces = ['common', 'service-status'];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - <?php echo htmlspecialchars(t('service-status.title')); ?></title>
    <script>window.translations = <?php echo json_encode(I18n::exportForJs($translationNamespaces), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
    <?php echo Tz::scriptTag(); ?>
    <script src="../assets/js/tz.js?v=5"></script>
    <script src="../assets/js/i18n.js?v=2"></script>
    <link rel="stylesheet" href="../assets/css/theme.css?v=23">
    <link rel="stylesheet" href="../assets/css/inbox.css?v=62">
    <style>
        /* Pin the shared --accent to the module's emerald so modals, focus
           rings and the secondary button read on-brand. */
        body { --accent: var(--ss-accent, #10b981); }

        .status-layout {
            height: calc(100vh - 48px);
            overflow-y: auto;
            padding: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text, #333);
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title .count {
            font-size: 13px;
            font-weight: 400;
            color: var(--text-dim, #888);
        }

        /* Service Board Grid */
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 36px;
        }


        /* Incident update thread (discussion #59, phase 2) */
        .inc-updates-toggle { margin-left: 10px; background: none; border: none; padding: 0;
            color: var(--ss-accent, #10b981); font-size: 11px; cursor: pointer; text-decoration: underline; }
        .inc-updates-row[hidden] { display: none !important; }
        .inc-updates { padding: 4px 0 8px; }
        .inc-update { padding: 8px 0; border-top: 1px solid var(--border-soft, #f1f5f9); }
        .inc-update:first-child { border-top: none; }
        .inc-update-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
        .inc-update-when { font-size: 12px; color: var(--text-muted, #6b7280); }
        .inc-update-who  { font-size: 11px; color: var(--text-dim, #9ca3af); }
        .inc-update-comment { font-size: 13px; margin-bottom: 6px; }
        .inc-update-clear { font-size: 11px; color: var(--text-dim, #9ca3af); font-style: italic; }

        /* ─── Service history + uptime (discussion #59) ────────────────────── */
        /* ⚠️ The badge and the History link have to line up ACROSS cards, and two
           separate things stopped them.
           1. Descriptions are one line or two ("Email" wraps, "VPN" does not), so
              the badge started at a different height in each card.
           2. Badge and link on one line wrapped unpredictably — "Operational"
              left room for the link beside it, "Degraded Performance" did not —
              so some cards showed one row and others two.
           The card is a flex column with the description absorbing the slack, and
           the pair is always stacked. Consistent beats compact here: the eye is
           scanning DOWN a row of cards, so the badges must share a baseline. */
        .service-card { display: flex; flex-direction: column; }
        .service-card .service-desc { flex: 1 1 auto; }
        .service-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            margin-top: auto;       /* pinned to the bottom, whatever the description did */
        }

        .svc-history-toggle {
            background: none; border: none; padding: 0;
            color: var(--ss-accent, #10b981); font-size: 12px; cursor: pointer;
            text-decoration: underline;
        }
        .svc-history[hidden] { display: none !important; }
        /* ⚠️ An open history SPANS THE WHOLE GRID. The service cards are a
           minmax(200px, 1fr) grid, and a four-column table plus a 90-cell strip
           inside 200px is unreadable: the incident titles were clipped and the
           strip rendered as a barcode of 1px slivers. Screenshot caught both.
           Spanning gives the table room and makes each strip cell ~10px. */
        .service-card.is-expanded { grid-column: 1 / -1; text-align: left; }
        .service-card.is-expanded .service-name,
        .service-card.is-expanded .service-desc { text-align: left; }
        /* Expanded there is width to spare, so the pair goes back on one row. */
        .service-card.is-expanded .service-actions {
            flex-direction: row; align-items: center; justify-content: flex-start; gap: 12px;
        }
        .svc-history { margin-top: 12px; border-top: 1px solid var(--border, #e5e7eb); padding-top: 12px; }
        .svc-history-loading { font-size: 12px; color: var(--text-muted, #6b7280); padding: 6px 0; }

        .svc-history-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }
        .svc-uptime-figure { font-size: 20px; font-weight: 700; color: var(--text, #111); }
        .svc-uptime-label  { font-size: 11px; color: var(--text-muted, #6b7280); margin-left: 6px; }
        .svc-win-group { display: flex; gap: 4px; }
        .svc-win {
            border: 1px solid var(--border, #e5e7eb); background: var(--surface, #fff);
            color: var(--text-muted, #6b7280); font-size: 11px; padding: 3px 8px;
            border-radius: 5px; cursor: pointer;
            transition: background 150ms ease, color 150ms ease, transform 140ms cubic-bezier(0.23, 1, 0.32, 1);
        }
        .svc-win:hover { border-color: var(--ss-accent, #10b981); }
        .svc-win:active { transform: scale(0.94); }
        .svc-win.is-on { background: var(--ss-accent, #10b981); border-color: var(--ss-accent, #10b981); color: #fff; font-weight: 600; }

        /* The strip. flex with min-width 0 cells so 7, 30, 90 or 365 days all fit
           the same width without a horizontal scrollbar appearing at 365. */
        .svc-strip { display: flex; gap: 1px; align-items: stretch; height: 30px; }
        .svc-day { flex: 1 1 0; min-width: 0; border-radius: 1px; background: var(--ok-bg, #d1fae5); }
        .svc-day-ok   { background: var(--ok-bg, #d1fae5); }
        /* ⚠️ NOT var(--border). That token is a divider colour and it is #343b45 in
           dark mode, so an excluded day rendered as a near-black gap in the strip —
           it read as "broken", which is the one thing it is not. A literal mid-grey
           is legible against both the light and dark card backgrounds, and this is
           a data colour rather than chrome, so it should not follow a chrome token. */
        .svc-day-info { background: #94a3b8; }   /* logged, but not counted as downtime */
        .svc-day-down { background: #dc2626; }
        .svc-strip-ends { display: flex; justify-content: space-between; font-size: 10px; color: var(--text-muted, #9ca3af); margin-top: 4px; }

        .svc-history-table { width: 100%; margin-top: 12px; border-collapse: collapse; font-size: 12px; }
        .svc-history-table td { padding: 6px 8px; border-top: 1px solid var(--border-soft, #f1f5f9); vertical-align: middle; }
        .svc-excluded { font-size: 10px; color: var(--text-muted, #9ca3af); font-style: italic; }

        @media (prefers-reduced-motion: reduce) { .svc-win:active { transform: none; } }
        .service-card {
            background: var(--surface, #fff);
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            transition: box-shadow 0.2s;
        }

        .service-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }

        .service-card .service-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text, #333);
            margin-bottom: 8px;
        }

        .service-card .service-desc {
            font-size: 12px;
            color: var(--text-dim, #888);
            margin-bottom: 10px;
            min-height: 16px;
        }

        /* Impact badges */
        .impact-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .impact-major-outage { background: #fee2e2; color: #991b1b; }
        .impact-partial-outage { background: #fff1f2; color: #be123c; }
        .impact-degraded { background: #fff7ed; color: #c2410c; }
        .impact-maintenance { background: #dbeafe; color: #1e40af; }
        .impact-operational { background: #d1fae5; color: #065f46; }
        .impact-no-disruption { background: #f3f4f6; color: #6b7280; }

        /* Status badges for incident status */
        .incident-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .incident-status-3rd-party { background: #fef3c7; color: #92400e; }
        .incident-status-identified { background: #e0e7ff; color: #3730a3; }
        .incident-status-investigating { background: #fff7ed; color: #c2410c; }
        .incident-status-monitoring { background: #dbeafe; color: #1e40af; }
        .incident-status-resolved { background: #d1fae5; color: #065f46; }

        /* Incidents list */
        .incidents-section { margin-bottom: 30px; }

        .incident-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--surface, #fff);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border, #e5e7eb);
        }

        .incident-table th {
            background: var(--surface-2, #f9fafb);
            padding: 10px 14px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted, #666);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border, #e5e7eb);
        }

        .incident-table td {
            padding: 12px 14px;
            font-size: 13px;
            color: var(--text, #333);
            border-bottom: 1px solid var(--border-soft, #f3f4f6);
        }

        .incident-table tr:last-child td { border-bottom: none; }

        .incident-table tr.resolved td { color: var(--text-dim, #999); }

        .incident-title {
            font-weight: 500;
            cursor: pointer;
        }

        .incident-title:hover { color: var(--ss-accent, #10b981); }

        .incident-services-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .incident-svc-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
        }

        .new-btn {
            padding: 8px 18px;
            background: var(--ss-accent, #10b981);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .new-btn:hover { background: var(--ss-accent-hover, #059669); }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-dim, #999);
            font-size: 14px;
        }

        /* Incident modal */
        .modal-content { padding: 20px; max-width: 600px; }
        .modal-header { font-size: 20px; font-weight: 600; margin-bottom: 20px; color: var(--text, #333); padding: 0; border-bottom: none; }

        .modal .form-group { margin-bottom: 15px; }
        .modal .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; color: var(--text, #333); }
        .modal .form-group input,
        .modal .form-group textarea,
        .modal .form-group select { width: 100%; padding: 8px 12px; border: 1px solid var(--border, #ddd); border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        .modal .form-group textarea { height: 80px; resize: vertical; }
        .modal .form-group input:focus,
        .modal .form-group textarea:focus,
        .modal .form-group select:focus { outline: none; border-color: var(--ss-accent, #10b981); box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1); }

        .modal-actions { margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end; }

        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.15s; }
        .btn-primary { background-color: var(--ss-accent, #10b981); color: white; }
        .btn-primary:hover { background-color: var(--ss-accent-hover, #059669); }
        .btn-danger { background-color: #ef4444; color: white; }
        .btn-danger:hover { background-color: #dc2626; }

        /* Affected services rows in modal */
        .affected-services { margin-top: 5px; }

        .affected-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }

        .affected-row select { flex: 1; }

        .affected-row .remove-svc {
            background: none;
            border: none;
            color: var(--danger-accent, #d13438);
            cursor: pointer;
            font-size: 18px;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .affected-row .remove-svc:hover { background: #fdf3f3; }

        .add-svc-btn {
            background: none;
            border: 1px dashed var(--border, #ccc);
            color: var(--text-muted, #666);
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }

        .add-svc-btn:hover { border-color: var(--ss-accent, #10b981); color: var(--ss-accent, #10b981); }

        .incident-date {
            font-size: 12px;
            color: var(--text-dim, #999);
            white-space: nowrap;
        }

        /* Pale-red remove-service hover wash → dark red in dark mode so it
           doesn't glow. Impact/incident-status badges stay hardcoded (data). */
        [data-theme-mode="dark"] .affected-row .remove-svc:hover { background: #3a1a1a; }
    </style>
    <!-- Mobile: LAYER 18 — board grid two-up, incidents as a card feed. -->
    <link rel="stylesheet" href="../assets/css/mobile.css?v=78">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="status-layout">
        <!-- Service Board -->
        <div class="section-title">
            <?php echo htmlspecialchars(t('service-status.board.services')); ?>
            <span class="count" id="serviceCount"></span>
        </div>
        <div class="service-grid" id="serviceGrid">
            <div class="empty-state"><?php echo htmlspecialchars(t('service-status.board.loading')); ?></div>
        </div>

        <!-- Incidents -->
        <div class="incidents-section">
            <div class="section-title">
                <?php echo htmlspecialchars(t('service-status.board.incidents')); ?>
                <button class="new-btn" onclick="openIncidentModal()"><?php echo htmlspecialchars(t('service-status.board.new')); ?></button>
            </div>
            <table class="incident-table" id="incidentTable" style="display: none;">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars(t('service-status.board.col_title')); ?></th>
                        <th><?php echo htmlspecialchars(t('service-status.board.col_status')); ?></th>
                        <th><?php echo htmlspecialchars(t('service-status.board.col_affected')); ?></th>
                        <th><?php echo htmlspecialchars(t('service-status.board.col_updated')); ?></th>
                    </tr>
                </thead>
                <tbody id="incidentList"></tbody>
            </table>
            <div class="empty-state" id="incidentEmpty" style="display: none;"><?php echo htmlspecialchars(t('service-status.board.no_incidents')); ?></div>
        </div>
    </div>

    <!-- Incident Modal -->
    <div class="modal" id="incidentModal">
        <div class="modal-content">
            <div class="modal-header" id="incidentModalTitle"><?php echo htmlspecialchars(t('service-status.modal.new_incident')); ?></div>
            <form id="incidentForm" autocomplete="off">
                <input type="hidden" id="incidentId">
                <div class="form-group">
                    <label for="incidentTitle"><?php echo htmlspecialchars(t('service-status.modal.title')); ?></label>
                    <input type="text" id="incidentTitle" required placeholder="<?php echo htmlspecialchars(t('service-status.modal.title_placeholder')); ?>">
                </div>
                <div class="form-group">
                    <label for="incidentStatus"><?php echo htmlspecialchars(t('service-status.modal.status')); ?></label>
                    <select id="incidentStatus"></select>
                </div>
                <div class="form-group">
                    <label for="incidentComment"><?php echo htmlspecialchars(t('service-status.modal.comment')); ?></label>
                    <textarea id="incidentComment" placeholder="<?php echo htmlspecialchars(t('service-status.modal.comment_placeholder')); ?>"></textarea>
                </div>
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('service-status.modal.affected_services')); ?></label>
                    <div class="affected-services" id="affectedServices"></div>
                    <button type="button" class="add-svc-btn" onclick="addServiceRow()"><?php echo htmlspecialchars(t('service-status.modal.add_service')); ?></button>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger" id="deleteIncidentBtn" onclick="deleteIncident()" style="display: none; margin-right: auto;"><?php echo htmlspecialchars(t('service-status.modal.delete')); ?></button>
                    <button type="button" class="btn btn-secondary" onclick="closeIncidentModal()"><?php echo htmlspecialchars(t('service-status.modal.cancel')); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(t('service-status.modal.save')); ?></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = '../api/service-status/';
        let allServices = [];
        let dashboardData = { services: [], incidents: [] };
        // Loaded from new lookup endpoints — drives dropdowns and badge colours
        let incidentStatuses = [];   // [{id, name, colour, is_resolved, is_default}]
        let impactLevels = [];       // [{id, name, colour, severity_order, is_default}]

        const statusByName = (name) => incidentStatuses.find(s => s.name === name);
        const impactByName = (name) => impactLevels.find(l => l.name === name);

        document.addEventListener('DOMContentLoaded', loadDashboard);

        async function loadDashboard() {
            try {
                // Load lookup tables (for dropdowns + badge colours)
                const [stsResp, ilResp, svcResp] = await Promise.all([
                    fetch(API_BASE + 'get_incident_statuses.php').then(r => r.json()),
                    fetch(API_BASE + 'get_impact_levels.php').then(r => r.json()),
                    fetch(API_BASE + 'get_services.php').then(r => r.json())
                ]);
                if (stsResp.success) incidentStatuses = stsResp.statuses.filter(s => s.is_active);
                if (ilResp.success)  impactLevels    = ilResp.impact_levels.filter(l => l.is_active);
                if (svcResp.success) allServices     = svcResp.services.filter(s => s.is_active);

                // Populate the incident-status dropdown
                const stsSelect = document.getElementById('incidentStatus');
                stsSelect.innerHTML = incidentStatuses.map(s =>
                    `<option value="${escapeHtml(s.name)}">${escapeHtml(s.name)}</option>`
                ).join('');

                // Load dashboard data
                const resp = await fetch(API_BASE + 'get_dashboard.php');
                const data = await resp.json();
                if (data.success) {
                    dashboardData = data;
                    renderServiceGrid(data.services);
                    renderIncidents(data.incidents);
                }
            } catch (error) {
                console.error('Failed to load dashboard:', error);
            }
        }

        function renderServiceGrid(services) {
            const grid = document.getElementById('serviceGrid');
            document.getElementById('serviceCount').textContent = window.t('service-status.board.service_count', { count: services.length });

            if (services.length === 0) {
                grid.innerHTML = '<div class="empty-state">' + escapeHtml(window.t('service-status.board.no_services')) + '</div>';
                return;
            }

            grid.innerHTML = services.map(svc => {
                // Colour comes from the row the API resolved, not from a name lookup
                // here: a level that was renamed OR deactivated still has to paint its
                // badge (GH #70). impactByName is the fallback for older payloads.
                const colour = svc.current_status_colour || impactByName(svc.current_status)?.colour;
                const style = colour ? `style="background:${colour}; color:#fff;"` : '';
                // History is loaded on demand (discussion #59): a dashboard with
                // twenty services should not fire twenty history queries to draw a
                // page most people are only glancing at.
                return `
                <div class="service-card">
                    <div class="service-name">${escapeHtml(svc.name)}</div>
                    <div class="service-desc">${escapeHtml(svc.description || '')}</div>
                    <div class="service-actions">
                        <span class="impact-badge" ${style}>${escapeHtml(svc.current_status)}</span>
                        <button type="button" class="svc-history-toggle" onclick="toggleServiceHistory(${svc.id}, this)">
                            ${escapeHtml(window.t('service-status.board.history_show'))}
                        </button>
                    </div>
                    <div class="svc-history" id="svcHistory${svc.id}" hidden></div>
                </div>`;
            }).join('');
        }

        /* ─── Service history + uptime (discussion #59) ───────────────────────
           Everything shown here is derived from incidents; there is no history
           table. See includes/services/service_uptime.php for what that can and
           cannot see (changes made DURING an incident are not yet recorded). */
        const svcHistoryCache = {};

        async function toggleServiceHistory(serviceId, btn) {
            const box = document.getElementById('svcHistory' + serviceId);
            if (!box) return;
            const card = box.closest('.service-card');
            if (!box.hidden) {
                box.hidden = true;
                if (card) card.classList.remove('is-expanded');
                btn.textContent = window.t('service-status.board.history_show');
                return;
            }
            box.hidden = false;
            if (card) card.classList.add('is-expanded');
            btn.textContent = window.t('service-status.board.history_hide');
            if (svcHistoryCache[serviceId]) { renderServiceHistory(serviceId, svcHistoryCache[serviceId]); return; }
            box.innerHTML = `<div class="svc-history-loading">${escapeHtml(window.t('service-status.board.history_loading'))}</div>`;
            await loadServiceHistory(serviceId);
        }

        async function loadServiceHistory(serviceId, days) {
            const box = document.getElementById('svcHistory' + serviceId);
            try {
                const q = days ? ('&days=' + encodeURIComponent(days)) : '';
                const res = await fetch(`../api/service-status/get_service_history.php?service_id=${serviceId}${q}`);
                const data = await res.json();
                if (!data.success) {
                    box.innerHTML = `<div class="svc-history-loading">${escapeHtml(data.error || '')}</div>`;
                    return;
                }
                svcHistoryCache[serviceId] = data;
                renderServiceHistory(serviceId, data);
            } catch (e) {
                box.innerHTML = `<div class="svc-history-loading">${escapeHtml(e.message)}</div>`;
            }
        }

        function renderServiceHistory(serviceId, data) {
            const box = document.getElementById('svcHistory' + serviceId);
            const s = data.summary;

            const windowPicker = data.windows.map(w =>
                `<button type="button" class="svc-win${w === s.window_days ? ' is-on' : ''}"
                         onclick="loadServiceHistory(${serviceId}, ${w})">${w}d</button>`).join('');

            // One cell per day, oldest first. title carries the detail rather than a
            // tooltip component: it is a 90-cell strip and a hover card per cell
            // would be a lot of DOM for something read at a glance.
            const strip = data.strip.map(d => {
                // ⚠️ Name the actual impact level. This used to say "maintenance"
                // for any day whose only incident was excluded from downtime — but
                // "excluded" covers Operational, No Disruption and anything an
                // administrator adds, so a day was reporting a level it never had.
                const label = d.impact
                    ? `${d.date} — ${d.impact}`
                    : `${d.date} — ${window.t('service-status.board.history_no_issues')}`;
                const bg = (d.state === 'down' && d.colour) ? ` style="background:${d.colour}"` : '';
                return `<span class="svc-day svc-day-${d.state}"${bg} title="${escapeHtml(label)}"></span>`;
            }).join('');

            const rows = data.incidents.length
                ? data.incidents.map(i => `
                    <tr>
                        <td>${escapeHtml(i.started)}</td>
                        <td><span class="impact-badge"${i.colour ? ` style="background:${i.colour};color:#fff;"` : ''}>${escapeHtml(i.impact)}</span></td>
                        <td>${i.ongoing ? escapeHtml(window.t('service-status.board.history_ongoing')) : escapeHtml(i.duration)}</td>
                        <td>${escapeHtml(i.title)}${i.counts ? '' : ` <span class="svc-excluded">${escapeHtml(window.t('service-status.board.history_excluded'))}</span>`}</td>
                    </tr>`).join('')
                : `<tr><td colspan="4" class="svc-history-loading">${escapeHtml(window.t('service-status.board.history_none'))}</td></tr>`;

            box.innerHTML = `
                <div class="svc-history-head">
                    <div class="svc-uptime">
                        <span class="svc-uptime-figure">${s.uptime_percent.toFixed(2)}%</span>
                        <span class="svc-uptime-label">${escapeHtml(window.t('service-status.board.history_uptime'))}</span>
                    </div>
                    <div class="svc-win-group">${windowPicker}</div>
                </div>
                <div class="svc-strip">${strip}</div>
                <div class="svc-strip-ends">
                    <span>${s.window_days}${escapeHtml(window.t('service-status.board.history_days_ago'))}</span>
                    <span>${escapeHtml(window.t('service-status.board.history_today'))}</span>
                </div>
                <table class="svc-history-table"><tbody>${rows}</tbody></table>
                <div id="svcDocuments${serviceId}" style="margin-top:18px;"></div>`;

            // Attached documents (discussion #76) — the runbook, the recovery
            // procedure, the supplier's SLA. Inside the expanded card, because
            // that is where a service already tells its longer story.
            if (window.FreeITSMDocuments) {
                FreeITSMDocuments.mount(document.getElementById('svcDocuments' + serviceId), {
                    parentType: 'status_service',
                    parentId:   serviceId,
                    apiBase:    '../api/documents/',
                    showHeading: true
                });
            }
        }

        function renderIncidents(incidents) {
            const table = document.getElementById('incidentTable');
            const empty = document.getElementById('incidentEmpty');
            const tbody = document.getElementById('incidentList');

            if (incidents.length === 0) {
                table.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            table.style.display = 'table';
            empty.style.display = 'none';

            tbody.innerHTML = incidents.map(inc => {
                const sts = statusByName(inc.status);
                const isResolved = !!(sts && sts.is_resolved);
                const statusStyle = sts && sts.colour ? `style="background:${sts.colour}; color:#fff;"` : '';
                const svcs = (inc.services || []).map(s => {
                    const il = impactByName(s.impact_level);
                    const tagStyle = il && il.colour ? `style="background:${il.colour}; color:#fff;"` : '';
                    return `<span class="incident-svc-tag" ${tagStyle}>${escapeHtml(s.service_name)}</span>`;
                }).join('');

                const date = inc.updated_datetime || inc.created_datetime;
                const dateStr = date ? formatDate(date) : '';

                return `
                    <tr class="${isResolved ? 'resolved' : ''}">
                        <td>
                            <span class="incident-title" onclick="editIncident(${inc.id})">${escapeHtml(inc.title)}</span>
                            <button type="button" class="inc-updates-toggle" onclick="toggleIncidentUpdates(${inc.id}, this)">${escapeHtml(window.t('service-status.board.updates_show'))}</button>
                        </td>
                        <td><span class="incident-status" ${statusStyle}>${escapeHtml(inc.status)}</span></td>
                        <td><div class="incident-services-list">${svcs || `<span style="color:var(--text-dim, #999)">${escapeHtml(window.t('service-status.board.none'))}</span>`}</div></td>
                        <td><span class="incident-date">${dateStr}</span></td>
                    </tr>
                    <tr class="inc-updates-row" id="incUpdatesRow${inc.id}" hidden>
                        <td colspan="4"><div class="inc-updates" id="incUpdates${inc.id}"></div></td>
                    </tr>
                `;
            }).join('');
        }

        /* ─── Incident update thread (discussion #59, phase 2) ────────────────
           The rows behind the per-service history: what was said, when, by whom,
           and which services were at which impact at that moment. Loaded on
           demand — most people reading the board never open one. */
        async function toggleIncidentUpdates(incidentId, btn) {
            const row = document.getElementById('incUpdatesRow' + incidentId);
            const box = document.getElementById('incUpdates' + incidentId);
            if (!row) return;
            if (!row.hidden) {
                row.hidden = true;
                btn.textContent = window.t('service-status.board.updates_show');
                return;
            }
            row.hidden = false;
            btn.textContent = window.t('service-status.board.updates_hide');
            box.innerHTML = `<div class="svc-history-loading">${escapeHtml(window.t('service-status.board.history_loading'))}</div>`;
            try {
                const res = await fetch(API_BASE + 'get_incident_updates.php?incident_id=' + incidentId);
                const data = await res.json();
                // ⚠️ "None recorded" and "could not load" are different facts and
                // must not share a message. Collapsing them says an incident has
                // no history when the request simply failed — the same shape of
                // lie as a strip cell that named a level it never had.
                if (!data.success) {
                    box.innerHTML = `<div class="svc-history-loading">${escapeHtml(data.error || window.t('service-status.board.updates_failed'))}</div>`;
                    return;
                }
                if (!data.updates.length) {
                    // An incident raised before phase 2 legitimately has none.
                    box.innerHTML = `<div class="svc-history-loading">${escapeHtml(window.t('service-status.board.updates_none'))}</div>`;
                    return;
                }
                box.innerHTML = data.updates.map(u => {
                    const tags = (u.services || []).map(s =>
                        `<span class="incident-svc-tag"${s.colour ? ` style="background:${s.colour};color:#fff;"` : ''}>${escapeHtml(s.service)}${s.impact ? ' · ' + escapeHtml(s.impact) : ''}</span>`
                    ).join('');
                    return `<div class="inc-update">
                        <div class="inc-update-meta">
                            <span class="inc-update-when">${escapeHtml(formatDate(u.created_datetime))}</span>
                            ${u.status ? `<span class="incident-status"${u.status_colour ? ` style="background:${u.status_colour};color:#fff;"` : ''}>${escapeHtml(u.status)}</span>` : ''}
                            ${u.author ? `<span class="inc-update-who">${escapeHtml(u.author)}</span>` : ''}
                        </div>
                        ${u.comment ? `<div class="inc-update-comment">${escapeHtml(u.comment)}</div>` : ''}
                        ${tags ? `<div class="incident-services-list">${tags}</div>` : `<div class="inc-update-clear">${escapeHtml(window.t('service-status.board.updates_all_clear'))}</div>`}
                    </div>`;
                }).join('');
            } catch (e) {
                box.innerHTML = `<div class="svc-history-loading">${escapeHtml(e.message)}</div>`;
            }
        }

        // Incident timestamps come from the API as UTC strings ("YYYY-MM-DD HH:MM:SS",
        // stamped with UTC_TIMESTAMP()). Render them in the analyst's chosen display
        // zone via the shared tz.js helpers (parseUTCDate marks the value UTC; tzOpts
        // injects window.USER_TIMEZONE, falling back to the browser zone when unset).
        function formatDate(dateStr) {
            try {
                const d = parseUTCDate(dateStr);
                if (!d || isNaN(d.getTime())) return dateStr;
                return fmtDateTime(d);
            } catch (e) {
                return dateStr;
            }
        }

        // --- Incident Modal ---

        function openIncidentModal() {
            document.getElementById('incidentModalTitle').textContent = window.t('service-status.modal.new_incident');
            document.getElementById('incidentId').value = '';
            document.getElementById('incidentTitle').value = '';
            const defaultSts = incidentStatuses.find(s => s.is_default) || incidentStatuses[0];
            document.getElementById('incidentStatus').value = defaultSts ? defaultSts.name : '';
            document.getElementById('incidentComment').value = '';
            document.getElementById('affectedServices').innerHTML = '';
            document.getElementById('deleteIncidentBtn').style.display = 'none';
            addServiceRow();
            document.getElementById('incidentModal').classList.add('active');
        }

        function editIncident(id) {
            const inc = dashboardData.incidents.find(i => i.id == id);
            if (!inc) return;

            document.getElementById('incidentModalTitle').textContent = window.t('service-status.modal.edit_incident');
            document.getElementById('incidentId').value = inc.id;
            document.getElementById('incidentTitle').value = inc.title;
            document.getElementById('incidentStatus').value = inc.status;
            document.getElementById('incidentComment').value = inc.comment || '';
            document.getElementById('deleteIncidentBtn').style.display = 'inline-flex';

            const container = document.getElementById('affectedServices');
            container.innerHTML = '';

            if (inc.services && inc.services.length > 0) {
                inc.services.forEach(s => addServiceRow(s.service_id, s.impact_level));
            } else {
                addServiceRow();
            }

            document.getElementById('incidentModal').classList.add('active');
        }

        function addServiceRow(serviceId, impactLevel) {
            const container = document.getElementById('affectedServices');
            const row = document.createElement('div');
            row.className = 'affected-row';

            const svcOptions = allServices.map(s =>
                `<option value="${s.id}" ${s.id == serviceId ? 'selected' : ''}>${escapeHtml(s.name)}</option>`
            ).join('');

            // Default impact for a freshly added row. This used to name 'Degraded'
            // literally, which broke the same way GH #70 did — rename the level and
            // the row silently fell back to the baseline, i.e. an incident that
            // affects nothing. Ask by MEANING instead: the mildest level that still
            // counts as downtime. On the stock lookup that IS Degraded, and it stays
            // right whatever the levels are called.
            const mildestDowntime = impactLevels
                .filter(l => l.counts_as_downtime && !l.is_default)
                .sort((a, b) => b.severity_order - a.severity_order)[0];
            const defaultImpact = impactLevel
                || mildestDowntime?.name
                || impactLevels.find(l => l.is_default)?.name
                || impactLevels[0]?.name || '';
            const impactOptions = impactLevels.map(level =>
                `<option value="${escapeHtml(level.name)}" ${level.name === defaultImpact ? 'selected' : ''}>${escapeHtml(level.name)}</option>`
            ).join('');

            row.innerHTML = `
                <select class="svc-select">${svcOptions}</select>
                <select class="impact-select">${impactOptions}</select>
                <button type="button" class="remove-svc">&times;</button>
            `;

            // Removing an affected service used to happen on the first tap with
            // no way back — easy to do by accident on a phone, where the × sits
            // next to two dropdowns. Uses the app-wide showConfirm (not the
            // browser's confirm()) so it matches every other destructive action,
            // and the generic delete_title / delete_message pair so no new
            // translation key is needed.
            row.querySelector('.remove-svc').addEventListener('click', async function () {
                const name = row.querySelector('.svc-select')?.selectedOptions[0]?.textContent || '';
                const ok = await showConfirm({
                    title: window.t('service-status.confirm.delete_title'),
                    message: window.t('service-status.confirm.delete_message', { name: name }),
                    okLabel: window.t('service-status.confirm.delete_label'),
                    okClass: 'danger'
                });
                if (ok) row.remove();
            });

            container.appendChild(row);
        }

        function closeIncidentModal() {
            document.getElementById('incidentModal').classList.remove('active');
        }

        document.getElementById('incidentForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const rows = document.querySelectorAll('#affectedServices .affected-row');
            const services = [];
            rows.forEach(row => {
                const svcId = row.querySelector('.svc-select').value;
                const impact = row.querySelector('.impact-select').value;
                if (svcId) {
                    services.push({ service_id: parseInt(svcId), impact_level: impact });
                }
            });

            const payload = {
                id: document.getElementById('incidentId').value || null,
                title: document.getElementById('incidentTitle').value,
                status: document.getElementById('incidentStatus').value,
                comment: document.getElementById('incidentComment').value,
                services: services
            };

            try {
                const response = await fetch(API_BASE + 'save_incident.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (data.success) {
                    closeIncidentModal();
                    showToast(window.t('service-status.toast.incident_saved'), 'success');
                    loadDashboard();
                } else {
                    showToast(data.error || window.t('service-status.toast.save_failed'), 'error');
                }
            } catch (error) {
                showToast(window.t('service-status.toast.save_incident_failed'), 'error');
            }
        });

        async function deleteIncident() {
            const id = document.getElementById('incidentId').value;
            if (!id) return;
            const ok = await showConfirm({
                title: window.t('service-status.confirm.delete_incident_title'),
                message: window.t('service-status.confirm.delete_incident_message'),
                okLabel: window.t('service-status.confirm.delete_label'),
                okClass: 'danger'
            });
            if (!ok) return;

            try {
                const response = await fetch(API_BASE + 'delete_incident.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(id) })
                });
                const data = await response.json();
                if (data.success) {
                    closeIncidentModal();
                    showToast(window.t('service-status.toast.incident_deleted'), 'success');
                    loadDashboard();
                } else {
                    showToast(data.error || window.t('service-status.toast.delete_failed'), 'error');
                }
            } catch (error) {
                showToast(window.t('service-status.toast.delete_incident_failed'), 'error');
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // Close modal on outside click
        document.getElementById('incidentModal').addEventListener('click', function(e) {
            if (e.target === this) closeIncidentModal();
        });
    </script>
    <script src="../assets/js/mobile.js?v=30"></script>
</body>
</html>
