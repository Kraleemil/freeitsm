/*
 * Command palette (⌘/Ctrl-K).
 *
 * A global launcher available on every analyst page. Opens with Cmd-K (Mac) or
 * Ctrl-K, lets you jump straight to any module you can access, run a couple of
 * quick actions, and search tickets / CMDB items / assets by name.
 *
 * The page injects two globals before this loads (see renderWaffleMenuJS in
 * includes/waffle-menu.php):
 *   window.CP_BASE     — BASE_URL, prefixed to every navigation target.
 *   window.CP_MODULES  — [{ key, name, path, icon }] already filtered to the
 *                        modules this analyst may see, so the palette never
 *                        offers a destination the waffle launcher wouldn't.
 *
 * Entity search goes to api/system/global_search.php, which applies the same
 * module + company scoping server-side.
 */
(function () {
    'use strict';

    if (window.__cmdpInit) return;      // guard against a double include
    window.__cmdpInit = true;

    var BASE = window.CP_BASE || '';
    var MODULES = Array.isArray(window.CP_MODULES) ? window.CP_MODULES : [];

    // Generic icons for the search-result types (modules carry their own).
    var ICONS = {
        ticket: '<path d="M22 12h-6l-2 3h-4l-2-3H2"></path><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>',
        change: '<polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line>',
        problem: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
        knowledge: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>',
        contract: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line>',
        ci: '<path d="M2 22V8l10-6 10 6v14"></path><path d="M2 12h20"></path><line x1="12" y1="2" x2="12" y2="22"></line>',
        asset: '<rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line>',
        command: '<polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line>',
        search: '<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>'
    };
    var TYPE_LABEL = {
        ticket: 'Ticket', change: 'Change', problem: 'Problem',
        knowledge: 'Article', contract: 'Contract', ci: 'Config item', asset: 'Asset'
    };

    // Static quick actions. Each has a matcher label and a run().
    var COMMANDS = [
        {
            label: 'Toggle dark mode',
            keywords: 'theme light dark appearance',
            run: function () {
                var cur = document.documentElement.getAttribute('data-theme');
                var next = cur === 'dark' ? 'default' : 'dark';
                // setTheme() (waffle-menu.php) persists the choice and reloads.
                if (typeof window.setTheme === 'function') window.setTheme(next);
            }
        },
        {
            label: 'Sign out',
            keywords: 'logout log out leave',
            run: function () { window.location.href = BASE + 'analyst_logout.php'; }
        }
    ];

    var overlay, input, resultsEl, searchWrap;
    var items = [];        // flat list of currently-shown {el, activate} entries
    var activeIx = -1;
    var searchTimer = null;
    var searchSeq = 0;     // guards against out-of-order async responses

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function build() {
        overlay = document.createElement('div');
        overlay.className = 'cmdp-overlay';
        overlay.innerHTML =
            '<div class="cmdp-box" role="dialog" aria-label="Command palette">' +
                '<div class="cmdp-search">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONS.search + '</svg>' +
                    '<input class="cmdp-input" type="text" autocomplete="off" spellcheck="false" placeholder="Search tickets, assets, items — or jump to a module…">' +
                    '<div class="cmdp-spinner"></div>' +
                '</div>' +
                '<div class="cmdp-results"></div>' +
                '<div class="cmdp-footer">' +
                    '<span class="cmdp-hint"><span class="cmdp-key">↑</span><span class="cmdp-key">↓</span> navigate</span>' +
                    '<span class="cmdp-hint"><span class="cmdp-key">↵</span> open</span>' +
                    '<span class="cmdp-hint"><span class="cmdp-key">esc</span> close</span>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        input = overlay.querySelector('.cmdp-input');
        resultsEl = overlay.querySelector('.cmdp-results');
        searchWrap = overlay.querySelector('.cmdp-search');

        overlay.addEventListener('mousedown', function (e) {
            if (e.target === overlay) close();       // click the backdrop to dismiss
        });
        input.addEventListener('input', onInput);
        input.addEventListener('keydown', onKeydown);
    }

    function open() {
        if (!overlay) build();
        overlay.classList.add('active');
        input.value = '';
        render();                 // initial view: modules + actions
        // focus after the paint so the caret lands reliably
        requestAnimationFrame(function () { input.focus(); });
    }

    function close() {
        if (!overlay) return;
        overlay.classList.remove('active');
        searchWrap.classList.remove('loading');
        if (searchTimer) { clearTimeout(searchTimer); searchTimer = null; }
    }

    function isOpen() { return overlay && overlay.classList.contains('active'); }

    // Substring match with a light prefix boost, so "tas" ranks "Tasks" above a
    // module that merely contains the letters.
    function score(text, q) {
        text = text.toLowerCase();
        var i = text.indexOf(q);
        if (i === -1) return -1;
        return i === 0 ? 2 : 1;
    }

    function matchedModules(q) {
        if (!q) return MODULES.slice();
        return MODULES
            .map(function (m) { return { m: m, s: score(m.name, q) }; })
            .filter(function (x) { return x.s >= 0; })
            .sort(function (a, b) { return b.s - a.s; })
            .map(function (x) { return x.m; });
    }

    function matchedCommands(q) {
        if (!q) return COMMANDS.slice();
        return COMMANDS.filter(function (c) {
            return score(c.label, q) >= 0 || (c.keywords && c.keywords.indexOf(q) !== -1);
        });
    }

    // Render the palette body from the current query + optional server results.
    function render(serverResults) {
        var q = input.value.trim().toLowerCase();
        var html = '';
        items = [];
        var pending = [];   // {activate} in DOM order, wired up after innerHTML

        var mods = matchedModules(q);
        if (mods.length) {
            html += '<div class="cmdp-group-label">Go to</div>';
            mods.forEach(function (m) {
                var idx = pending.length;
                html += row(idx,
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (m.icon || '') + '</svg>',
                    esc(m.name), '', '');
                pending.push(function () { window.location.href = BASE + m.path; });
            });
        }

        var cmds = matchedCommands(q);
        if (cmds.length) {
            html += '<div class="cmdp-group-label">Actions</div>';
            cmds.forEach(function (c) {
                var idx = pending.length;
                html += row(idx,
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONS.command + '</svg>',
                    esc(c.label), '', '');
                pending.push(function () { close(); c.run(); });
            });
        }

        if (serverResults && serverResults.length) {
            // Group the entity results by type, in a stable order.
            ['ticket', 'change', 'problem', 'knowledge', 'contract', 'asset', 'ci'].forEach(function (type) {
                var group = serverResults.filter(function (r) { return r.type === type; });
                if (!group.length) return;
                html += '<div class="cmdp-group-label">' + esc(pluralType(type)) + '</div>';
                group.forEach(function (r) {
                    var idx = pending.length;
                    html += row(idx,
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (ICONS[type] || '') + '</svg>',
                        esc(r.title), esc(r.subtitle || ''), TYPE_LABEL[type] || '');
                    pending.push(function () { window.location.href = BASE + r.url; });
                });
            });
        }

        if (!html) {
            html = '<div class="cmdp-empty">' +
                (q.length >= 2 ? 'No matches for “' + esc(q) + '”' : 'Type to search') +
                '</div>';
        }

        resultsEl.innerHTML = html;

        // Wire the rendered rows to their actions.
        var rowEls = resultsEl.querySelectorAll('.cmdp-item');
        rowEls.forEach(function (el, i) {
            var activate = pending[i];
            items.push({ el: el, activate: activate });
            el.addEventListener('mousemove', function () { setActive(i); });
            el.addEventListener('click', function () { if (activate) activate(); });
        });

        activeIx = items.length ? 0 : -1;
        paintActive();
    }

    function pluralType(type) {
        return {
            ticket: 'Tickets', change: 'Changes', problem: 'Problems',
            knowledge: 'Knowledge', contract: 'Contracts',
            asset: 'Assets', ci: 'Configuration items'
        }[type] || type;
    }

    function row(idx, iconSvg, title, sub, tag) {
        return '<div class="cmdp-item" data-ix="' + idx + '">' +
            '<div class="cmdp-item-icon">' + iconSvg + '</div>' +
            '<div class="cmdp-item-body">' +
                '<div class="cmdp-item-title">' + title + '</div>' +
                (sub ? '<div class="cmdp-item-sub">' + sub + '</div>' : '') +
            '</div>' +
            (tag ? '<span class="cmdp-item-tag">' + tag + '</span>' : '') +
        '</div>';
    }

    function setActive(ix) {
        activeIx = ix;
        paintActive();
    }

    function paintActive() {
        items.forEach(function (it, i) {
            if (i === activeIx) {
                it.el.classList.add('active');
                it.el.scrollIntoView({ block: 'nearest' });
            } else {
                it.el.classList.remove('active');
            }
        });
    }

    function move(delta) {
        if (!items.length) return;
        activeIx = (activeIx + delta + items.length) % items.length;
        paintActive();
    }

    function onKeydown(e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIx >= 0 && items[activeIx] && items[activeIx].activate) items[activeIx].activate();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();      // don't let the page's global Escape also fire
            close();
        }
    }

    function onInput() {
        var q = input.value.trim();
        render();                     // instant client-side view (modules + actions)
        if (searchTimer) clearTimeout(searchTimer);
        if (q.length < 2) {
            searchWrap.classList.remove('loading');
            return;
        }
        searchWrap.classList.add('loading');
        searchTimer = setTimeout(function () { doSearch(q); }, 180);
    }

    function doSearch(q) {
        var seq = ++searchSeq;
        fetch(BASE + 'api/system/global_search.php?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (seq !== searchSeq) return;      // a newer query has superseded this
                if (input.value.trim() !== q) return;
                searchWrap.classList.remove('loading');
                render(data && data.success ? data.results : []);
            })
            .catch(function () {
                if (seq !== searchSeq) return;
                searchWrap.classList.remove('loading');
            });
    }

    // Global trigger: Cmd-K / Ctrl-K toggles the palette from anywhere.
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && !e.altKey && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            isOpen() ? close() : open();
        }
    });
})();
