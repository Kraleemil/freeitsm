/**
 * FreeITSM Tasks — Shared right-click context menu
 *
 * One menu used by the dashboard (right-click a board / list card) and the
 * timeline (right-click a Gantt bar). Each page provides:
 *   - targetSelector: CSS selector that identifies a clickable task element
 *   - getTaskId(el):  return the numeric task id from that element
 *   - getTask(id):    return the task object (so submenus know the current
 *                     analyst/team/status/priority for the ✓ marker)
 *   - getLookups():   return { analysts, teams, statuses, priorities }
 *   - onUpdate():     called after a successful save (typically loadTasks())
 *   - apiBase:        base URL for save.php (e.g. '../api/tasks/')
 *   - onCreateSubtask(id) [optional]: dashboard wires this to open the detail
 *                     panel and focus the add-subtask input. Omit on pages
 *                     that don't have a detail panel — the menu hides the
 *                     "Create subtask" item when this is missing.
 *
 * Markup contract: a single .ctx-menu with id="ctxMenu" containing four
 * submenu containers (#ctxAnalyst / #ctxTeam / #ctxStatus / #ctxPriority).
 * The optional "Create subtask" item carries data-action="subtask".
 */
(function() {
    let cfg = null;
    let ctxTaskId = null;
    let wired = false;

    function init(config) {
        cfg = Object.assign({
            targetSelector: '.task-card',
            menuId: 'ctxMenu',
            apiBase: '',
            onUpdate: () => {},
            onCreateSubtask: null,
        }, config);

        if (!wired) {
            document.addEventListener('contextmenu', onDocCtx);
            document.addEventListener('click', closeCtx);
            document.addEventListener('scroll', closeCtx, true);
            window.addEventListener('resize', closeCtx);

            const menu = document.getElementById(cfg.menuId);
            if (menu) menu.addEventListener('click', onMenuClick);
            wired = true;
        }

        // Hide the Create-subtask item (and its preceding separator) on pages
        // that don't provide an onCreateSubtask callback.
        const subtaskItem = document.querySelector(`#${cfg.menuId} [data-action="subtask"]`);
        if (subtaskItem) {
            const show = !!cfg.onCreateSubtask;
            subtaskItem.style.display = show ? '' : 'none';
            const sep = subtaskItem.previousElementSibling;
            if (sep && sep.classList.contains('ctx-sep')) sep.style.display = show ? '' : 'none';
        }
    }

    function close() { closeCtx(); }
    function isOpen() {
        const menu = cfg && document.getElementById(cfg.menuId);
        return !!menu && menu.style.display === 'block';
    }

    function onDocCtx(e) {
        if (!cfg) return;
        const target = e.target.closest(cfg.targetSelector);
        if (target) openCtx(e, cfg.getTaskId(target));
        else closeCtx();
    }

    function openCtx(e, taskId) {
        e.preventDefault();
        const task = cfg.getTask(taskId);
        if (!task) return;
        ctxTaskId = taskId;
        buildSubmenus(task);

        const menu = document.getElementById(cfg.menuId);
        menu.style.display = 'block';
        const mw = menu.offsetWidth, mh = menu.offsetHeight;
        const x = Math.min(e.clientX, window.innerWidth - mw - 6);
        const y = Math.min(e.clientY, window.innerHeight - mh - 6);
        menu.style.left = Math.max(6, x) + 'px';
        menu.style.top  = Math.max(6, y) + 'px';
        menu.classList.toggle('flip-sub',   e.clientX + mw + 190 > window.innerWidth);
        menu.classList.toggle('flip-sub-v', e.clientY > window.innerHeight * 0.55);
    }

    function closeCtx() {
        if (!cfg) return;
        const menu = document.getElementById(cfg.menuId);
        if (menu) menu.style.display = 'none';
        ctxTaskId = null;
    }

    function buildSubmenus(task) {
        const lookups = cfg.getLookups();
        const T = (k) => window.t('tasks.' + k);

        const opt = (field, value, label, current, swatch) =>
            `<div class="ctx-sub-item${current ? ' current' : ''}" data-field="${field}" data-value="${escAttr(value)}">
                ${swatch || ''}<span class="ctx-sub-label">${esc(label)}</span>
                ${current ? '<span class="ctx-check">✓</span>' : ''}
            </div>`;
        const sw = c => `<span class="ctx-swatch" style="background:${escAttr(c || '#888')}"></span>`;

        const elA = document.getElementById('ctxAnalyst');
        if (elA) elA.innerHTML =
            opt('assigned_analyst_id', '', T('detail.unassigned'), !task.assigned_analyst_id) +
            (lookups.analysts || []).map(a =>
                opt('assigned_analyst_id', a.id, a.name, task.assigned_analyst_id == a.id)).join('');

        const elT = document.getElementById('ctxTeam');
        if (elT) elT.innerHTML =
            opt('assigned_team_id', '', T('detail.no_team'), !task.assigned_team_id) +
            (lookups.teams || []).map(tm =>
                opt('assigned_team_id', tm.id, tm.name, task.assigned_team_id == tm.id)).join('');

        const elS = document.getElementById('ctxStatus');
        if (elS) elS.innerHTML =
            (lookups.statuses || []).map(s =>
                opt('status', s.name, s.name, task.status === s.name, sw(s.colour))).join('')
            || `<div class="ctx-sub-empty">${esc(T('context.no_statuses'))}</div>`;

        const elP = document.getElementById('ctxPriority');
        if (elP) elP.innerHTML =
            (lookups.priorities || []).map(p =>
                opt('priority', p.name, p.name, task.priority === p.name, sw(p.colour))).join('')
            || `<div class="ctx-sub-empty">${esc(T('context.no_priorities'))}</div>`;

        // ── Due date shortcuts ────────────────────────────────────────────
        // ⚠️ Built from LOCAL date parts, never toISOString(): a due date is a
        // bare date, and toISOString() converts to UTC first, so anyone west of
        // Greenwich would get yesterday. Same trap as GH #116, different field.
        const ymd = (d) => d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
        const today = new Date();
        const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
        const nextMonday = new Date(today);
        // getDay(): 0 = Sunday. Always lands on the NEXT Monday, never today.
        nextMonday.setDate(today.getDate() + ((8 - today.getDay()) % 7 || 7));

        const elD = document.getElementById('ctxDue');
        if (elD) elD.innerHTML =
            opt('due_date', ymd(today), T('context.due_today'), task.due_date === ymd(today)) +
            opt('due_date', ymd(tomorrow), T('context.due_tomorrow'), task.due_date === ymd(tomorrow)) +
            opt('due_date', ymd(nextMonday), T('context.due_next_monday'), task.due_date === ymd(nextMonday)) +
            opt('due_date', '', T('context.due_clear'), !task.due_date);

        // ── Complete / reopen, and whether logging time is offered here ────
        const closed = !!task.status_is_closed;
        const lbl = document.getElementById('ctxCompleteLabel');
        if (lbl) lbl.textContent = T(closed ? 'context.reopen' : 'context.mark_complete');

        // Hidden rather than shown-and-refused when the administrator has
        // switched time off for this kind of task (GH #112).
        const lt = document.getElementById('ctxLogTime');
        if (lt) lt.style.display = (cfg.timeAllowedFor && cfg.timeAllowedFor(task)) ? '' : 'none';

        // Repeats (#94). Hidden on a subtask rather than shown and refused: the
        // series belongs to the piece of work, not a step inside it, and the API
        // rejects it too. The label says which way it will go, so a task that
        // already repeats offers to change it rather than to set it up again.
        const rp = document.getElementById('ctxRepeat');
        if (rp) {
            rp.style.display = task.parent_task_id ? 'none' : '';
            const rl = rp.querySelector('.ctx-item-label');
            if (rl) rl.textContent = T(task.recurrence_id ? 'context.change_repeat' : 'context.set_repeat');
        }
    }

    function onMenuClick(e) {
        const subOpt = e.target.closest('.ctx-sub-item');
        if (subOpt) {
            const field = subOpt.dataset.field;
            let value = subOpt.dataset.value;
            if (field === 'assigned_analyst_id' || field === 'assigned_team_id') {
                value = value === '' ? null : parseInt(value, 10);
            }
            setField(field, value);
            return;
        }
        const id = ctxTaskId;
        const task = id && cfg.getTask ? cfg.getTask(id) : null;

        if (e.target.closest('[data-action="subtask"]')) {
            closeCtx();
            if (id && cfg.onCreateSubtask) cfg.onCreateSubtask(id);
            return;
        }
        if (e.target.closest('[data-action="open"]')) {
            closeCtx();
            if (id && cfg.onOpen) cfg.onOpen(id);
            return;
        }
        // Repeats (#94). Opens the task with its Repeats editor already open,
        // rather than duplicating the editor into the menu: every option lives
        // in one place, and the menu is the shortcut to it.
        if (e.target.closest('[data-action="repeat"]')) {
            closeCtx();
            if (id && cfg.onSetRepeat) cfg.onSetRepeat(id);
            return;
        }
        if (e.target.closest('[data-action="assign-me"]')) {
            // Bare identifier: ANALYST_ID is a const in tasks.js, so the config
            // hands it over rather than this file reaching for window.
            setField('assigned_analyst_id', cfg.currentAnalystId ? parseInt(cfg.currentAnalystId, 10) : null);
            return;
        }
        if (e.target.closest('[data-action="complete"]')) {
            // By the is_closed FLAG, never by the status's name — an installation
            // that renamed "Done" would otherwise never be able to finish a task.
            const statuses = (cfg.getLookups() || {}).statuses || [];
            const closed = task && task.status_is_closed;
            const target = closed
                ? (statuses.find(s => !s.is_closed && s.is_default) || statuses.find(s => !s.is_closed))
                : statuses.find(s => s.is_closed);
            if (!target) {
                showToast(window.t('tasks.context.no_status_for_that'), 'error');
                closeCtx();
                return;
            }
            setField('status', target.name);
            return;
        }
        if (e.target.closest('[data-action="logtime"]')) {
            closeCtx();
            if (id && cfg.onLogTime) cfg.onLogTime(id);
            return;
        }
        if (e.target.closest('[data-action="copylink"]')) {
            closeCtx();
            const base = (window.APP_BASE || '/');
            const url = location.origin + base + 'tasks/?task=' + id;
            // ⚠️ copyToClipboard(), never navigator.clipboard directly: that is
            // undefined outside a secure context and throws SYNCHRONOUSLY, so a
            // .catch() fallback never runs. The helper also reports honestly
            // whether the copy happened, so we never claim one we did not make.
            copyToClipboard(url).then(ok => {
                showToast(window.t(ok ? 'tasks.context.link_copied' : 'tasks.context.link_copy_failed'),
                          ok ? 'success' : 'error');
            });
            return;
        }
        if (e.target.closest('[data-action="delete"]')) {
            closeCtx();
            if (id && cfg.onDelete) cfg.onDelete(id, task);
            return;
        }
    }

    async function setField(field, value) {
        const id = ctxTaskId;
        closeCtx();
        if (!id) return;
        try {
            const data = await fetch(cfg.apiBase + 'save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, [field]: value })
            }).then(r => r.json());
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast(window.t('tasks.toast.task_updated'), 'success');
                }
                if (cfg.onUpdate) cfg.onUpdate();
            } else if (typeof showToast === 'function') {
                showToast(window.t('tasks.toast.error_prefix', {
                    message: data.error || window.t('tasks.toast.update_failed')
                }), 'error');
            }
        } catch (e) {
            if (typeof showToast === 'function') {
                showToast(window.t('tasks.toast.update_failed'), 'error');
            }
        }
    }

    // ── Local helpers (mirrors of esc/escAttr in tasks.js + tasks-timeline.js) ──
    function esc(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }
    function escAttr(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    window.TasksCtxMenu = { init, close, isOpen };
})();
