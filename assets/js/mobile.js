/* ============================================================================
   mobile.js  —  Mobile-only inbox master-detail behaviour (Outlook-style pane
   stack). Paired with mobile.css (LAYER 2).

   HARD RULE mirror of the CSS: every behaviour here is gated on
   matchMedia('(max-width: 768px)'), so on desktop it is inert — no pane
   switching, and the injected sub-bar is display:none. Desktop is untouched.

   Loaded AFTER inbox.js so it can wrap the global selectEmail / selectFolder
   handlers that the list rows and folder items already call.
   ========================================================================== */
(function () {
    'use strict';

    var mq = window.matchMedia('(max-width: 768px)');
    var mc = document.querySelector('.main-container');

    /**
     * The views hamburger (top-right) -> right-side slide-in drawer.
     *
     * Shared, because every module header is the same component: `.header` with
     * a `.header-nav` of sub-views. Assets needs it as much as Tickets does, so
     * it is a function rather than a copy.
     */
    function injectViewsHamburger() {
        var headerEl = document.querySelector('.header');
        if (!headerEl || !document.querySelector('.header-nav')) return;
        if (document.querySelector('.mobile-views-btn')) return;   // idempotent

        var vBtn = document.createElement('button');
        vBtn.type = 'button';
        vBtn.className = 'mobile-views-btn';
        vBtn.setAttribute('aria-label', 'Views');
        vBtn.textContent = '☰';
        headerEl.appendChild(vBtn);

        var vOverlay = document.createElement('div');
        vOverlay.className = 'mobile-views-overlay';
        document.body.appendChild(vOverlay);

        vBtn.addEventListener('click', function () { document.body.classList.toggle('mobile-views-open'); });
        vOverlay.addEventListener('click', function () { document.body.classList.remove('mobile-views-open'); });
    }

    /** Company switcher into the waffle drawer — also shared, also a no-op at N=1. */
    function moveTenantIntoWaffle() {
        if (!mq.matches) return;
        var wafflePanel = document.getElementById('wafflePanel');
        var tenant = document.querySelector('.tenant-switcher');
        if (!wafflePanel || !tenant) return;
        var wHead = wafflePanel.querySelector('.waffle-panel-header');
        if (wHead) wHead.insertAdjacentElement('afterend', tenant);
        else wafflePanel.insertBefore(tenant, wafflePanel.firstChild);
    }

    // ------------------------------------------------------------------
    // The shell every opted-in page gets (#937).
    //
    // Modules via the waffle on the left, the module's own views via an
    // injected hamburger on the right, company switcher tucked into the waffle
    // drawer. That is true of the inbox, of Assets, and of the flat pages
    // (table view / dashboard / settings / servers) alike, so it runs before
    // any page-specific branch rather than inside each one.
    // ------------------------------------------------------------------
    injectViewsHamburger();
    moveTenantIntoWaffle();
    syncMorningChecksControls();

    /**
     * MORNING CHECKS (#1270) — the date picker, Today and Save to PDF move into
     * the views drawer on a phone.
     *
     * Measured on a 640px screen before this: the controls took **231px** and
     * the checks themselves got **177px**. The page is a checklist; the list is
     * the point, and it was the smallest thing on screen.
     *
     * The date HEADING stays put — you have to know which morning you are
     * looking at — and so does the All/Mine filter, which is 40px and changes
     * what the list contains. Only the 178px block of controls moves.
     *
     * ⚠️ Moved BACK on the way to desktop, not merely re-hidden: the drawer is
     * display:none above 768px, so a control left inside it would vanish
     * entirely for anyone who rotates a tablet or resizes a window.
     */
    function syncMorningChecksControls() {
        var sel = document.querySelector('.date-selector-container');
        var nav = document.querySelector('.header-nav');
        var home = document.querySelector('.date-display');
        if (!sel || !nav || !home) return;              // not this module

        if (mq.matches) {
            if (sel.parentElement !== nav) {
                sel.classList.add('mc-in-drawer');
                nav.appendChild(sel);
            }
        } else if (sel.parentElement === nav) {
            sel.classList.remove('mc-in-drawer');
            home.appendChild(sel);
        }
    }

    function syncShell() {
        var vb = document.querySelector('.mobile-views-btn');
        if (vb) vb.style.display = mq.matches ? '' : 'none';
        if (!mq.matches) document.body.classList.remove('mobile-views-open');
        syncMorningChecksControls();
    }
    syncShell();
    if (mq.addEventListener) { mq.addEventListener('change', syncShell); }
    else if (mq.addListener) { mq.addListener(syncShell); }

    // ------------------------------------------------------------------
    // ASSETS (#936) — the second module brought along.
    //
    // Two panes, not three, so the stack is list <-> detail with no folder
    // tree and no Folders button. Everything below the branch is inbox-only,
    // hence the early return: running the ticket wiring on this page would
    // wrap functions that don't exist and inject a Folders button that leads
    // nowhere.
    // ------------------------------------------------------------------
    if (document.querySelector('.assets-container')) { initAssetsMobile(); return; }

    // ------------------------------------------------------------------
    // CALENDAR (#998) — the third module.
    //
    // No pane stack at all: a calendar is one surface. The mobile job is to
    // get the sidebar off the screen (into a sheet), and to turn a tapped day
    // into an agenda, because LAYER 16b renders month events as dots with no
    // text. Guarded on #calendarGrid, not just .calendar-container, because
    // the module's other pages (table / settings) share the header but have
    // no grid to drive.
    // ------------------------------------------------------------------
    if (document.getElementById('calendarGrid')) { initCalendarMobile(); return; }

    // ------------------------------------------------------------------
    // KNOWLEDGE (#1000) — the fourth module.
    //
    // No pane stack either, and for a better reason than the calendar's:
    // `.knowledge-main` ALREADY shows one of three views at a time (list /
    // detail / editor), toggled by showView(). Nothing to slide. What that
    // state doesn't do is reach CSS, so the wrap below mirrors it onto
    // body[data-kb-view]. Guarded on .knowledge-container so the module's
    // other pages (review / assistant / settings / help) take the shell only.
    // ------------------------------------------------------------------
    if (document.querySelector('.knowledge-container')) { initKnowledgeMobile(); return; }

    // ------------------------------------------------------------------
    // SERVICE STATUS (#1003 shipped CSS-only; #1004 added this branch).
    //
    // The board needed no JS at first. Two of Ed's follow-ups do need it —
    // splitting Services and Incidents onto their own screens needs a
    // switcher that doesn't exist on desktop, and "tap anywhere on the card"
    // needs a delegated handler. Both are mq-gated, so desktop is untouched.
    // ------------------------------------------------------------------
    if (document.querySelector('.status-layout')) { initStatusMobile(); return; }

    // ------------------------------------------------------------------
    // PROBLEM MANAGEMENT (#1181).
    //
    // The module already swaps panes in place — pmOpenDetail hides #pmListView
    // and shows #pmDetailView — so it is master-detail before we touch it. The
    // only thing missing on a phone is that the sidebar (search, New, the
    // status chips) stays on screen while you are reading a problem, spending
    // ~140px of a 640px screen on controls for the list you just left.
    //
    // Wrapped rather than edited: both are top-level function declarations, so
    // they are properties of the global object and can be replaced from here.
    // A body attribute carries the state and mobile.css does the hiding — no
    // change to problem-management.js, and desktop never sees the attribute
    // because both wrappers check mq.matches before setting it.
    // ------------------------------------------------------------------
    if (document.querySelector('.pm-container')) { initProblemsMobile(); return; }

    // ------------------------------------------------------------------
    // CHANGE MANAGEMENT (#1184).
    //
    // Same need as Problem Management — the sidebar should step aside while
    // you read or edit a change — but a cleaner hook: showView('list' |
    // 'detail' | 'editor') is a single synchronous function, so one wrapper
    // covers every state and there is no promise to wait on.
    //
    // The approvals page has no view switching at all, so it falls through to
    // the shared shell with only CSS. Its own `.approvals-container` is caught
    // by the same test purely so the shell still initialises.
    // ------------------------------------------------------------------
    if (document.querySelector('.changes-container')) { initChangesMobile(); return; }
    if (document.querySelector('.approvals-container')) { return; }

    // Flat pages (Assets' table view, dashboard, settings, servers — #937) have
    // no pane stack: the shell above is the whole of their JS. The servers page
    // is the reason this test isn't just `!mc` — it DOES carry .main-container
    // (as .servers-container) but has no email list, and letting it fall into
    // the inbox wiring below would inject a Folders button onto a flat page.
    if (!mc || !document.querySelector('.email-list-container')) return;

    function initAssetsMobile() {
        function setPane(p) { document.body.setAttribute('data-mobile-pane', p); }
        function currentPane() { return document.body.getAttribute('data-mobile-pane') || 'list'; }
        function pushPane(p) {
            setPane(p);
            if (mq.matches) history.pushState({ nmPane: p }, '');
        }
        setPane('list');

        window.addEventListener('popstate', function (e) {
            if (!mq.matches) return;
            setPane((e.state && e.state.nmPane) ? e.state.nmPane : 'list');
        });

        // Sub-bar: Back only. The asset's name goes on the right so you can see
        // what you're looking at once the list has slid away.
        var aBar = document.createElement('div');
        aBar.className = 'mobile-subbar';
        aBar.innerHTML =
            '<button type="button" class="msb-back" aria-label="Back">‹ Back</button>' +
            '<span class="msb-ref" aria-label="Asset"></span>';
        mc.parentNode.insertBefore(aBar, mc);

        aBar.querySelector('.msb-back').addEventListener('click', function () {
            if (currentPane() === 'list') return;
            // Force the pane first so Back works even with nothing to pop.
            setPane('list');
            if (history.state && history.state.nmPane) history.back();
        });

        // Wrap selectAsset — never edit the module's own renderer.
        if (typeof window.selectAsset === 'function') {
            var _selectAsset = window.selectAsset;
            window.selectAsset = function (assetId) {
                var r = _selectAsset.apply(this, arguments);
                // Only when genuinely navigating list -> detail. selectAsset is
                // also called to re-render in place, and those must not stack
                // history entries.
                if (mq.matches && currentPane() !== 'detail') pushPane('detail');
                var show = function () {
                    var name = document.querySelector('.asset-detail-hostname');
                    var ref  = aBar.querySelector('.msb-ref');
                    if (ref) ref.textContent = name ? name.textContent.trim() : '';
                };
                if (r && typeof r.then === 'function') r.then(show); else show();
                return r;
            };
        }

        function syncAssetsBar() {
            var on = mq.matches;
            aBar.style.display = on ? 'flex' : 'none';
            var vb = document.querySelector('.mobile-views-btn');
            if (vb) vb.style.display = on ? '' : 'none';
            if (!on) {
                document.body.classList.remove('mobile-views-open');
                document.body.removeAttribute('data-mobile-pane');   // desktop shows both panes
            } else if (!document.body.getAttribute('data-mobile-pane')) {
                setPane('list');
            }
        }
        syncAssetsBar();
        if (mq.addEventListener) { mq.addEventListener('change', syncAssetsBar); }
        else if (mq.addListener) { mq.addListener(syncAssetsBar); }
    }

    /* ==================================================================
       CALENDAR (#998)

       Paired with mobile.css LAYER 16. Same wrap-don't-edit contract as the
       other two modules: itsm_calendar.js is never touched. It is a classic
       script, so its top-level `let`/`const` (currentView, events, MONTHS)
       are readable here as bare identifiers, and its `function` declarations
       (openEventModal, getEventsForDate, …) are window properties we can wrap.

       Three pieces:
         1. a sub-bar carrying the two actions the hidden sidebar owned;
         2. an OPTIONS sheet holding the relocated sidebar itself;
         3. an AGENDA sheet — the other half of the dots decision. A month
            cell shows coloured dots and no text, so tapping the day has to
            answer "what are they?". It replaces the desktop behaviour of
            tapping a day (which opens a blank New-event form) — that action
            moves to a button inside the agenda, pre-filled with the day.
       ================================================================== */
    function initCalendarMobile() {
        var container = document.querySelector('.calendar-container');
        if (!container) return;

        /* Prefer the module's own translations; fall back only if a key is
           missing (i18n's lookup echoes the key back when it can't resolve). */
        function tr(key, fallback) {
            if (typeof window.t !== 'function') return fallback;
            var v = window.t(key);
            return (!v || v === key) ? fallback : v;
        }

        // ---- sub-bar: the two actions the hidden sidebar used to carry ----
        var bar = document.createElement('div');
        bar.className = 'mobile-subbar';
        bar.style.display = 'none';          // @media CSS can't hide injected chrome
        var optLabel = tr('calendar.sidebar.categories', 'Categories');
        var newLabel = tr('calendar.sidebar.new_event', 'New event');
        bar.innerHTML =
            '<button type="button" class="msb-calopts">⚙ <span></span></button>' +
            '<button type="button" class="msb-new">+ <span></span></button>';
        bar.querySelector('.msb-calopts span').textContent = optLabel;
        bar.querySelector('.msb-new span').textContent = newLabel;
        bar.querySelector('.msb-calopts').setAttribute('aria-label', optLabel);
        bar.querySelector('.msb-new').setAttribute('aria-label', newLabel);
        container.parentNode.insertBefore(bar, container);

        // ---- sheet chrome (LAYER 7's .mobile-sheet, built twice) ----
        function buildSheet(cls, title) {
            var s = document.createElement('div');
            s.className = 'mobile-sheet mobile-sheet-' + cls;
            s.style.display = 'none';        // as above — inline, not @media
            s.innerHTML =
                '<div class="ms-head"><span class="ms-title"></span>' +
                '<button type="button" class="ms-close"></button></div>' +
                '<div class="ms-body"></div>';
            s.querySelector('.ms-title').textContent = title;
            s.querySelector('.ms-close').textContent = tr('calendar.subscribe.close', 'Close');
            s.querySelector('.ms-close').addEventListener('click', closeSheet);
            document.body.appendChild(s);
            return s;
        }
        var optsSheet = buildSheet('calopts', optLabel);
        var daySheet  = buildSheet('calday', '');

        /* Opening a sheet pushes a history entry so the DEVICE BACK BUTTON
           closes it, the same move that makes the ticket pane stack feel
           native rather than like a resized website. */
        function openSheet(el) {
            el.style.display = 'flex';
            history.pushState({ calSheet: true }, '');
        }
        function hideSheets() {
            optsSheet.style.display = 'none';
            daySheet.style.display = 'none';
        }
        function closeSheet() {
            if (history.state && history.state.calSheet) history.back();
            else hideSheets();
        }
        window.addEventListener('popstate', function () { hideSheets(); });

        // ---- 1. options sheet = the real sidebar, moved ----
        /* Relocated rather than rebuilt so `#categoryFilterList` keeps its id
           and renderCategoryFilters() still finds it, and so the subscribe
           block keeps its own wiring. Moved lazily on first open and moved
           BACK when the viewport leaves mobile, so resizing a desktop browser
           through the breakpoint can't strand the sidebar inside a hidden
           sheet (16a hides it in the container). */
        function sidebarIntoSheet() {
            var sb = container.querySelector('.calendar-sidebar');
            if (!sb) return;                                  // already moved
            // The sidebar's own New-event button duplicates the sub-bar's.
            var dup = sb.querySelector('.sidebar-section .btn-full[onclick*="openEventModal"]');
            if (dup && dup.parentNode) dup.parentNode.classList.add('mc-dup');
            optsSheet.querySelector('.ms-body').appendChild(sb);
        }
        function sidebarBackToPage() {
            var sb = optsSheet.querySelector('.calendar-sidebar');
            if (sb) container.insertBefore(sb, container.firstChild);
        }
        bar.querySelector('.msb-calopts').addEventListener('click', function () {
            sidebarIntoSheet();
            openSheet(optsSheet);
        });

        // ---- 2. New event: straight through to the module's own modal ----
        bar.querySelector('.msb-new').addEventListener('click', function () {
            if (typeof _openEventModal === 'function') _openEventModal();
        });

        // ---- 3. agenda sheet for a tapped day ----
        var agendaDate = null;

        function localDateLabel(dateStr) {
            var d = new Date(dateStr + 'T00:00:00');
            if (isNaN(d.getTime())) return dateStr;
            /* Renders through the shared formatters (assets/js/tz.js), so the
               weekday and month names follow the interface language and the
               arrangement follows the analyst's chosen date format - rather
               than the module's hardcoded English DAYS/MONTHS arrays. The
               value is date-only, so it is formatted NAIVELY. */
            try {
                return fmtNaiveWeekday(d, true) + ' ' + fmtNaiveTemplate(d, 'D MONTH');
            } catch (e) {
                return dateStr;
            }
        }

        function renderAgenda() {
            if (!agendaDate) return;
            var body = daySheet.querySelector('.ms-body');
            body.innerHTML = '';
            daySheet.querySelector('.ms-title').textContent = localDateLabel(agendaDate);

            var list = (typeof window.getEventsForDate === 'function')
                ? window.getEventsForDate(agendaDate) : [];

            list.forEach(function (ev) {
                var row = document.createElement('button');
                row.type = 'button';
                row.className = 'mc-ag-item';

                var dot = document.createElement('span');
                dot.className = 'mc-ag-dot';
                dot.style.backgroundColor = ev.category_color || '#ef6c00';
                row.appendChild(dot);

                var main = document.createElement('div');
                main.className = 'mc-ag-main';
                /* textContent throughout — no escapeHtml/innerHTML round trip. */
                var title = document.createElement('div');
                title.className = 'mc-ag-title';
                title.textContent = ev.title || '';
                main.appendChild(title);

                if (typeof window.formatEventTime === 'function') {
                    var time = document.createElement('div');
                    time.className = 'mc-ag-time';
                    // The module's own formatter, so the agenda reads exactly
                    // like the rest of the calendar (one formatter, not two).
                    time.textContent = window.formatEventTime(ev);
                    main.appendChild(time);
                }
                if (ev.location) {
                    var loc = document.createElement('div');
                    loc.className = 'mc-ag-loc';
                    loc.textContent = ev.location;
                    main.appendChild(loc);
                }
                if (ev.category_name) {
                    var cat = document.createElement('div');
                    cat.className = 'mc-ag-cat';
                    cat.textContent = ev.category_name;
                    main.appendChild(cat);
                }
                row.appendChild(main);

                // Tapping a row opens the module's edit modal ON TOP of the
                // sheet (.modal is z-index 2000 vs the sheet's 1500), so
                // closing it drops you back into the agenda you came from.
                row.addEventListener('click', function () {
                    if (typeof _openEventModal === 'function') _openEventModal(ev.id);
                });
                body.appendChild(row);
            });

            /* No "no events" line: it would be a new string, and an EN-only
               key falls back silently in the other 23 locales. On an empty day
               the date heading plus this button say it well enough. */
            var add = document.createElement('button');
            add.type = 'button';
            add.className = 'mc-ag-new';
            add.textContent = '+ ' + newLabel;
            add.addEventListener('click', function () {
                if (typeof _openEventModal === 'function') _openEventModal(null, agendaDate);
            });
            body.appendChild(add);
        }

        function openDaySheet(dateStr) {
            agendaDate = dateStr;
            renderAgenda();
            openSheet(daySheet);
        }

        // ---- wrap the module's globals (never edit itsm_calendar.js) ----
        var _openEventModal = window.openEventModal;
        if (typeof _openEventModal === 'function') {
            window.openEventModal = function (eventId, dateStr, hour) {
                /* Only the month grid's day-cell click is redirected: it is the
                   one call that means "I tapped a day", and on mobile that has
                   to answer the dots rather than open a blank form. Every other
                   caller passes an id (edit), an hour (a week/day time slot) or
                   nothing at all (New event) and goes straight through. */
                if (mq.matches && !eventId && dateStr &&
                    (hour === null || hour === undefined) &&
                    typeof currentView !== 'undefined' && currentView === 'month') {
                    openDaySheet(dateStr);
                    return;
                }
                return _openEventModal.apply(this, arguments);
            };
        }

        /* Week and day views are 24 rows of 60px and open at the top, so a
           phone lands on 12 AM — three screens above anything that happens in
           a working day. On a desktop pane you at least see through to ~10 AM;
           at 360px you see 12 AM to 6 AM and nothing else. Scroll to 7 AM after
           a render. Mobile only: the desktop start position is untouched. */
        function scrollToWorkingHours() {
            if (!mq.matches) return;
            var body = document.querySelector('.week-body, .day-body');
            if (body && body.scrollTop === 0) body.scrollTop = 7 * 60;
        }

        // Saving, deleting or filtering re-renders the calendar and reloads
        // `events`; if the agenda is open behind the modal it would still be
        // showing the old list, so refresh it off the same promise.
        if (typeof window.renderCalendar === 'function') {
            var _renderCalendar = window.renderCalendar;
            window.renderCalendar = function () {
                var r = _renderCalendar.apply(this, arguments);
                var after = function () {
                    if (mq.matches && daySheet.style.display === 'flex') renderAgenda();
                    scrollToWorkingHours();
                };
                if (r && typeof r.then === 'function') r.then(after); else after();
                return r;
            };
        }

        function syncCalendarBar() {
            var on = mq.matches;
            bar.style.display = on ? 'flex' : 'none';
            var vb = document.querySelector('.mobile-views-btn');
            if (vb) vb.style.display = on ? '' : 'none';
            if (!on) {
                document.body.classList.remove('mobile-views-open');
                hideSheets();
                sidebarBackToPage();
            }
        }
        syncCalendarBar();
        if (mq.addEventListener) { mq.addEventListener('change', syncCalendarBar); }
        else if (mq.addListener) { mq.addListener(syncCalendarBar); }
    }

    /* ==================================================================
       SERVICE STATUS (#1004)

       Two behaviours, both additive:
         1. a Services / Incidents switcher, because a board plus a feed on
            one scroll is a lot of thumb;
         2. the whole incident card opens the incident, not just its title.
       ================================================================== */
    function initChangesMobile() {
        /* Local, like every other module branch has: the sibling `tr`s are
           nested inside THEIR functions and are not in scope here. A missing
           one is a runtime ReferenceError that no parse check would catch. */
        function tr(key, fallback) {
            if (typeof window.t !== 'function') return fallback;
            var v = window.t(key);
            return (!v || v === key) ? fallback : v;
        }

        function setPane(name) {
            if (!mq.matches) { document.body.removeAttribute('data-cm-pane'); return; }
            document.body.setAttribute('data-cm-pane', name);
        }

        // Wrap, don't edit. showView is a top-level declaration, so it is a
        // property of the global object; the original does the real work and
        // this only records which pane won.
        var showView = window.showView;
        if (typeof showView === 'function') {
            window.showView = function (view) {
                var out = showView.apply(this, arguments);
                setPane(view === 'detail' || view === 'editor' ? view : 'list');
                return out;
            };
        }

        /* =================================================================
           Rich text on a phone: CARDS, and ONE editor at a time  (#1189)
           =================================================================
           #1187 put the whole tabbed widget full screen. That fixed the
           typing but left six TinyMCE instances living in the form, and Ed
           came back with "the tinymce editor is still causing a bit of havoc
           with the screen layout" — which it was: six iframes, each with its
           own toolbar, sizing themselves independently inside a 312px column.

           So on a phone the widget becomes a list of read-only CARDS — field
           name, a plain-text excerpt, Edit — and TinyMCE is initialised for
           ONE field only, on demand, straight into the full-screen panel.
           Nothing rich-text renders in the form itself.

           🔴 THE TRAP, and the reason this needs care rather than a display
           rule. `saveChange()` reads all six through `getEditorContent(id)`,
           which returns '' when `tinymce.get(id)` finds nothing — and
           `editorsReady` is set but NEVER READ, so nothing guards it. Simply
           not initialising the editors would make Save silently blank all six
           fields. The textarea therefore becomes the source of truth on
           mobile, and the two accessors are wrapped to use it.

           Everything here is wrap-don't-edit: initEditors, destroyEditors,
           setEditorContent and getEditorContent are all top-level
           declarations, so they are properties of the global object.
           `editorIds` is a top-level `const` and is NOT — the field list is
           read from the DOM instead. */
        function wireRichTextCards() {
            if (!mq.matches) return;
            var widget = document.getElementById('cmRichTextWidget');
            if (!widget || widget.dataset.cmCards) return;
            widget.dataset.cmCards = '1';

            /* All three keys already exist — the detail view says exactly
               these words about exactly these fields, so a phone borrows them
               rather than adding three more strings to 24 locales. */
            var editLabel = tr('change-management.detail.edit', 'Edit');
            var emptyLabel = tr('change-management.detail.not_provided', 'Not provided');
            var closeLabel = tr('common.close', 'Close');

            function panels() {
                return [].slice.call(widget.querySelectorAll('.rich-text-panel'));
            }
            function tabFor(key) {
                return widget.querySelector('.rich-text-tab[data-field-key="' + key + '"]');
            }
            function areaFor(key) {
                var p = widget.querySelector('.rich-text-panel[data-field-key="' + key + '"]');
                return p && p.querySelector('textarea');
            }
            function liveEditor(id) {
                return (id && window.tinymce && window.tinymce.get(id)) || null;
            }

            /* The card shows an EXCERPT, never the stored markup. Assigning it
               through textContent means no author-written HTML is ever parsed
               into this page, so the card needs no sanitiser — see the
               safe-html rule. `innerHTML` on a detached div is only used to
               let the browser do entity decoding and tag stripping for us. */
            function excerpt(html) {
                var box = document.createElement('div');
                box.innerHTML = html || '';
                var text = (box.textContent || '').replace(/\s+/g, ' ').trim();
                return text.length > 140 ? text.slice(0, 140) + '…' : text;
            }

            /* ---- the full-screen panel (kept from #1187, now single-field) -- */
            var barTitle = document.createElement('span');
            barTitle.className = 'cm-fs-title';
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'ms-close';
            closeBtn.textContent = closeLabel;
            var bar = document.createElement('div');
            bar.className = 'cm-fs-bar';
            bar.appendChild(barTitle);
            bar.appendChild(closeBtn);

            var list = document.createElement('div');
            list.className = 'cm-rt-cards';

            /* Both go INSIDE the widget. `refreshFormLayout()` re-parents it
               with `host.appendChild(richTextWidget)` so it follows the anchor
               section, and hides it outright when no section anchors it —
               anything left outside would be stranded in the old section, or
               left offering an editor for a widget that is `display: none`. */
            widget.insertBefore(bar, widget.firstChild);
            widget.insertBefore(list, bar.nextSibling);

            var openKey = null;

            function renderCards() {
                list.innerHTML = '';
                panels().forEach(function (panel) {
                    var key = panel.dataset.fieldKey;
                    var tab = tabFor(key);
                    /* Respect the module's own per-field visibility. A field
                       switched off in Form fields settings hides its TAB, and
                       that is the only place the flag is expressed. */
                    if (!tab || tab.style.display === 'none') return;
                    var area = areaFor(key);
                    if (!area) return;

                    var card = document.createElement('button');
                    card.type = 'button';                 // never submits the form
                    card.className = 'cm-rt-card';
                    card.dataset.fieldKey = key;

                    var head = document.createElement('span');
                    head.className = 'cm-rt-card-head';
                    var name = document.createElement('span');
                    name.className = 'cm-rt-card-name';
                    name.textContent = tab.textContent.trim();
                    var act = document.createElement('span');
                    act.className = 'cm-rt-card-edit';
                    act.textContent = editLabel;
                    head.appendChild(name);
                    head.appendChild(act);

                    var body = document.createElement('span');
                    var text = excerpt(currentValue(key));
                    body.className = 'cm-rt-card-body' + (text ? '' : ' cm-rt-card-empty');
                    body.textContent = text || emptyLabel;

                    card.appendChild(head);
                    card.appendChild(body);
                    /* The WHOLE card opens the field, not just the Edit word —
                       the same call made for the incident cards in LAYER 23. */
                    card.addEventListener('click', function () { openField(key); });
                    list.appendChild(card);
                });
            }

            function currentValue(key) {
                var area = areaFor(key);
                if (!area) return '';
                var ed = liveEditor(area.id);
                return ed ? ed.getContent() : area.value;
            }

            function openField(key) {
                var area = areaFor(key);
                if (!area) return;
                openKey = key;

                panels().forEach(function (p) { p.classList.toggle('active', p.dataset.fieldKey === key); });
                var tab = tabFor(key);
                barTitle.textContent = tab ? tab.textContent.trim() : '';

                document.body.classList.add('cm-editor-full');
                history.pushState({ cmFull: true }, '');

                /* One editor, created here and destroyed on the way out. The
                   init mirrors change-management.js's own so the phone gets
                   the same toolbar and the same dark-mode skin. */
                if (!liveEditor(area.id) && window.tinymce) {
                    var dark = (document.documentElement.getAttribute('data-theme-mode') || 'light') === 'dark';
                    window.tinymce.init({
                        selector: '#' + area.id,
                        license_key: 'gpl',
                        menubar: false,
                        statusbar: false,
                        skin: dark ? 'oxide-dark' : 'oxide',
                        content_css: dark ? 'dark' : 'default',
                        plugins: ['advlist', 'autolink', 'lists', 'link', 'wordcount'],
                        toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat',
                        content_style: 'body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; font-size: 16px; }',
                        setup: function (editor) {
                            editor.on('init', function () {
                                editor.setContent(area.value || '');
                                window.dispatchEvent(new Event('resize'));
                            });
                        }
                    });
                }
            }

            function closeField() {
                document.body.classList.remove('cm-editor-full');
                if (openKey) {
                    var area = areaFor(openKey);
                    var ed = area && liveEditor(area.id);
                    /* Write back BEFORE removing — `editor.remove()` is what
                       would otherwise be the last chance to read it. */
                    if (ed && area) { area.value = ed.getContent(); ed.remove(); }
                    openKey = null;
                }
                renderCards();
            }

            closeBtn.addEventListener('click', function () {
                if (history.state && history.state.cmFull) history.back();
                else closeField();
            });
            window.addEventListener('popstate', function () {
                if (document.body.classList.contains('cm-editor-full')) closeField();
            });

            /* ---- the four wrapped accessors ---------------------------------
               With no editors in the form, the textarea IS the field. */
            var _init = window.initEditors, _destroy = window.destroyEditors;
            var _set = window.setEditorContent, _get = window.getEditorContent;

            if (typeof _init === 'function') {
                window.initEditors = function (callback) {
                    if (!mq.matches) return _init.apply(this, arguments);
                    /* 🔴 The create path clears through `tinymce.get(id)`
                       directly rather than through setEditorContent, so with
                       no editors it would clear NOTHING and a new change would
                       open pre-filled with the last one's text. Clearing here
                       covers both callers. */
                    panels().forEach(function (p) {
                        var a = p.querySelector('textarea');
                        if (a) { var e = liveEditor(a.id); if (e) e.remove(); a.value = ''; }
                    });
                    if (callback) callback();
                    renderCards();
                    return undefined;
                };
            }
            if (typeof _destroy === 'function') {
                window.destroyEditors = function () {
                    if (!mq.matches) return _destroy.apply(this, arguments);
                    if (document.body.classList.contains('cm-editor-full')) closeField();
                    return _destroy.apply(this, arguments);   // safe: it no-ops when nothing is live
                };
            }
            if (typeof _set === 'function') {
                window.setEditorContent = function (id, content) {
                    if (!mq.matches || liveEditor(id)) return _set.apply(this, arguments);
                    var a = document.getElementById(id);
                    if (a) a.value = content || '';
                    renderCards();
                };
            }
            if (typeof _get === 'function') {
                window.getEditorContent = function (id) {
                    var ed = liveEditor(id);
                    if (ed) return ed.getContent();          // mid-edit: the editor is ahead of the textarea
                    if (!mq.matches) return _get.apply(this, arguments);
                    var a = document.getElementById(id);
                    return a ? a.value : '';
                };
            }

            /* refreshFormLayout() decides which fields are visible and runs on
               every editor open, so the cards are rebuilt behind it. */
            var _refresh = window.refreshFormLayout;
            if (typeof _refresh === 'function') {
                window.refreshFormLayout = function () {
                    var out = _refresh.apply(this, arguments);
                    if (mq.matches) renderCards();
                    return out;
                };
            }

            renderCards();
        }

        setPane('list');
        wireRichTextCards();
        var sync = function () {
            if (!mq.matches) {
                document.body.removeAttribute('data-cm-pane');
                document.body.classList.remove('cm-editor-full');   // never strand it on desktop
            }
        };
        if (mq.addEventListener) { mq.addEventListener('change', sync); }
        else if (mq.addListener) { mq.addListener(sync); }
    }

    function initProblemsMobile() {
        function setPane(name) {
            if (!mq.matches) { document.body.removeAttribute('data-pm-pane'); return; }
            document.body.setAttribute('data-pm-pane', name);
        }

        // Wrap, don't edit. Keep the original and call it, so every behaviour
        // the module already has — history, scroll reset, caching — is intact.
        var openDetail = window.pmOpenDetail;
        if (typeof openDetail === 'function') {
            window.pmOpenDetail = function () {
                var out = openDetail.apply(this, arguments);
                // pmOpenDetail is async and bails on a failed fetch, so the pane
                // is only marked once it has actually resolved. Marking it up
                // front would strand the sidebar hidden behind an error toast.
                Promise.resolve(out).then(function () {
                    var dv = document.getElementById('pmDetailView');
                    if (dv && dv.style.display !== 'none') setPane('detail');
                }).catch(function () { /* module already toasts */ });
                return out;
            };
        }

        var backToList = window.pmBackToList;
        if (typeof backToList === 'function') {
            window.pmBackToList = function () {
                setPane('list');
                return backToList.apply(this, arguments);
            };
        }

        setPane('list');
        // Rotating to a desktop width must not leave the sidebar hidden.
        var sync = function () { if (!mq.matches) document.body.removeAttribute('data-pm-pane'); };
        if (mq.addEventListener) { mq.addEventListener('change', sync); }
        else if (mq.addListener) { mq.addListener(sync); }
    }

    function initStatusMobile() {
        var layout = document.querySelector('.status-layout');
        if (!layout) return;

        function tr(key, fallback) {
            if (typeof window.t !== 'function') return fallback;
            var v = window.t(key);
            return (!v || v === key) ? fallback : v;
        }

        /* The services heading and grid are siblings with nothing wrapping
           them, so they are MARKED rather than restructured — CSS can then
           hide them as a unit. Marking beats `:first-of-type` here: a heading
           added above would silently re-point a positional selector, whereas
           a class says which nodes are meant. */
        var grid = layout.querySelector('.service-grid');
        var firstTitle = layout.querySelector('.section-title');
        if (grid) grid.classList.add('ss-services-part');
        if (firstTitle) firstTitle.classList.add('ss-services-part');

        var switcher = document.createElement('div');
        switcher.className = 'ss-switch';
        switcher.style.display = 'none';         // @media CSS can't hide injected chrome
        switcher.innerHTML = '<button type="button" data-ss="services"></button>' +
                             '<button type="button" data-ss="incidents"></button>';
        var btns = switcher.querySelectorAll('button');
        btns[0].textContent = tr('service-status.board.services', 'Services');
        btns[1].textContent = tr('service-status.board.incidents', 'Incidents');
        layout.insertBefore(switcher, layout.firstChild);

        function setTab(name) {
            document.body.setAttribute('data-ss-tab', name);
            btns.forEach(function (b) {
                var on = b.dataset.ss === name;
                b.classList.toggle('active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            layout.scrollTop = 0;
        }
        btns.forEach(function (b) {
            b.addEventListener('click', function () { setTab(b.dataset.ss); });
        });

        /* Tap anywhere on an incident card. Delegated, because the rows are
           re-rendered on every poll. It CLICKS THE TITLE rather than calling
           editIncident(id) directly: the id lives only in that element's
           inline handler, so going through it means there is still exactly
           one place that knows how to open an incident. */
        document.addEventListener('click', function (e) {
            if (!mq.matches) return;
            var row = e.target.closest && e.target.closest('#incidentList tr');
            if (!row) return;
            // The title has its own handler — let it do its job, don't double-fire.
            if (e.target.closest('.incident-title')) return;
            var title = row.querySelector('.incident-title');
            if (title) title.click();
        });

        function syncStatusBar() {
            var on = mq.matches;
            switcher.style.display = on ? 'flex' : 'none';
            var vb = document.querySelector('.mobile-views-btn');
            if (vb) vb.style.display = on ? '' : 'none';
            if (on) {
                if (!document.body.getAttribute('data-ss-tab')) setTab('services');
            } else {
                document.body.classList.remove('mobile-views-open');
                // Desktop shows both halves — never leave one hidden.
                document.body.removeAttribute('data-ss-tab');
            }
        }
        syncStatusBar();
        if (mq.addEventListener) { mq.addEventListener('change', syncStatusBar); }
        else if (mq.addListener) { mq.addListener(syncStatusBar); }
    }

    /* ==================================================================
       KNOWLEDGE (#1000)

       Paired with mobile.css LAYER 17. knowledge.js is not edited here — the
       one change it needed (16px inside the TinyMCE iframe, which CSS cannot
       reach) is a `@media (pointer: coarse)` block in its `content_style`,
       the same single justified edit inbox.js took in #766.

       Three pieces:
         1. the search box, MOVED into the sub-bar — on a phone the primary
            action in a knowledge base is finding one article, so it must not
            be behind a button. The tag filters and the two buttons go into a
            sheet; search does not.
         2. showView() mirrored onto body[data-kb-view] so CSS can react.
         3. the editor pop-out neutralised — a localStorage desktop mode, the
            exact shape of the #762 tickets bug.
       ================================================================== */
    function initKnowledgeMobile() {
        var container = document.querySelector('.knowledge-container');
        if (!container) return;

        function tr(key, fallback) {
            if (typeof window.t !== 'function') return fallback;
            var v = window.t(key);
            return (!v || v === key) ? fallback : v;
        }
        var tagsLabel = tr('knowledge.editor.field_tags', 'Tags');

        // ---- sub-bar: the real search input + the sheet button ----
        var bar = document.createElement('div');
        bar.className = 'mobile-subbar';
        bar.style.display = 'none';          // @media CSS can't hide injected chrome
        bar.innerHTML = '<button type="button" class="msb-kbopts">☰ <span></span></button>';
        bar.querySelector('.msb-kbopts span').textContent = tagsLabel;
        bar.querySelector('.msb-kbopts').setAttribute('aria-label', tagsLabel);
        container.parentNode.insertBefore(bar, container);

        // ---- sheet chrome (LAYER 7's .mobile-sheet) ----
        var sheet = document.createElement('div');
        sheet.className = 'mobile-sheet mobile-sheet-kbopts';
        sheet.style.display = 'none';
        sheet.innerHTML =
            '<div class="ms-head"><span class="ms-title"></span>' +
            '<button type="button" class="ms-close"></button></div>' +
            '<div class="ms-body"></div>';
        sheet.querySelector('.ms-title').textContent = tagsLabel;
        sheet.querySelector('.ms-close').textContent = tr('knowledge.modal.close', tr('common.close', 'Close'));
        sheet.querySelector('.ms-close').addEventListener('click', closeSheet);
        document.body.appendChild(sheet);

        function openSheet() {
            sheet.style.display = 'flex';
            history.pushState({ kbSheet: true }, '');
        }
        function hideSheet() { sheet.style.display = 'none'; }
        function closeSheet() {
            if (history.state && history.state.kbSheet) history.back();
            else hideSheet();
        }
        window.addEventListener('popstate', function () { hideSheet(); });

        /* The search box is MOVED, not copied — `#articleSearch` keeps its id
           and its inline `onkeyup="debounceSearch()"`, so the module's own
           search keeps working with no rewiring. Its now-empty section in the
           sidebar is marked rather than found by position. */
        function sidebarIntoPlace() {
            var sb = container.querySelector('.knowledge-sidebar');
            if (!sb) return;                                  // already moved
            var box = sb.querySelector('.search-box');
            if (box) {
                var sec = box.closest('.sidebar-section');
                if (sec) sec.classList.add('kb-dup');          // heading with nothing under it
                bar.insertBefore(box, bar.firstChild);
            }
            sheet.querySelector('.ms-body').appendChild(sb);
        }
        function sidebarBackToPage() {
            var sb = sheet.querySelector('.knowledge-sidebar');
            if (!sb) return;
            var box = bar.querySelector('.search-box');
            var sec = sb.querySelector('.sidebar-section.kb-dup');
            if (box && sec) { sec.classList.remove('kb-dup'); sec.appendChild(box); }
            container.insertBefore(sb, container.firstChild);
        }

        bar.querySelector('.msb-kbopts').addEventListener('click', function () {
            sidebarIntoPlace();
            openSheet();
        });
        // Picking a tag filters the list behind the sheet; close it so you can
        // see what you just did.
        sheet.addEventListener('click', function (e) {
            if (e.target.closest('.tag-filter, .btn-full')) closeSheet();
        });

        /* ---- "Back to list" -> "Back" ----
           Four buttons share one row on a phone, and the long label is what
           stops them fitting. `common.back` was added for this and harvested
           from each locale's existing translation of the same word, so no
           locale falls back to English. The desktop label is restored when
           the viewport leaves mobile — the element is shared, not duplicated. */
        var backLink = document.querySelector('.article-detail-header > .btn');
        var backLong = backLink ? backLink.textContent.trim() : '';
        var backShort = tr('common.back', backLong);
        function syncBackLabel() {
            if (!backLink) return;
            backLink.textContent = mq.matches ? backShort : backLong;
        }

        /* ---- the collapsible meta block (Gmail-style) ----
           The whole meta row is the control: a bigger target than a chevron,
           and its accessible name is the visible "Modified: …" text, so the
           toggle needs no label string in 24 languages. The reading pane is
           rebuilt on every article open, so this re-runs after each render
           and is idempotent. */
        function wireMetaToggle() {
            if (!mq.matches) return;
            var head = document.querySelector('.article-content-header');
            var meta = head && head.querySelector('.article-content-meta');
            if (!meta || meta.dataset.kbToggle) return;      // idempotent
            meta.dataset.kbToggle = '1';
            meta.setAttribute('role', 'button');
            meta.setAttribute('tabindex', '0');
            meta.setAttribute('aria-expanded', 'false');
            function toggle() {
                var open = head.classList.toggle('kb-meta-open');
                meta.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            meta.addEventListener('click', toggle);
            meta.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
            });
        }

        if (typeof window.renderArticleDetail === 'function') {
            var _renderArticleDetail = window.renderArticleDetail;
            window.renderArticleDetail = function () {
                var r = _renderArticleDetail.apply(this, arguments);
                wireMetaToggle();
                return r;
            };
        }
        wireMetaToggle();          // an article opened straight from a ?article= URL

        /* ---- full-screen text editing ----
           An "expand" control above the editor, and a Close bar inside it.
           Both labels reuse existing translated keys, so no new strings:
           `knowledge.editor.popout_title` already reads "Toggle full-screen
           view" in all 24 locales (it labels the desktop pop-out button,
           which is hidden on mobile), and the sheets' Close does the rest.
           Built after the editor exists, and idempotently — the editor view
           is not re-rendered, but syncKnowledgeBar can run again on resize. */
        function wireFullScreenEditor() {
            if (!mq.matches) return;
            var content = document.querySelector('.editor-content');
            if (!content || content.dataset.kbFs) return;
            content.dataset.kbFs = '1';

            var label = tr('knowledge.editor.popout_title', 'Full screen');
            var closeLabel = tr('knowledge.modal.close', tr('common.close', 'Close'));

            var openBtn = document.createElement('button');
            openBtn.type = 'button';
            openBtn.className = 'kb-fs-open';
            openBtn.textContent = '⤢  ' + label;

            /* The bar shows the ARTICLE's title rather than repeating the
               button's label — once you are in full screen, "Toggle
               full-screen view" tells you nothing you don't know, whereas
               what you are editing is genuinely useful. Read live from the
               Title field, so it is right for a new article too, and it
               needs no string of its own. */
            var barTitle = document.createElement('span');
            barTitle.className = 'kb-fs-title';
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'ms-close';
            closeBtn.textContent = closeLabel;
            var bar = document.createElement('div');
            bar.className = 'kb-fs-bar';
            bar.appendChild(barTitle);
            bar.appendChild(closeBtn);

            content.insertBefore(bar, content.firstChild);
            content.insertBefore(openBtn, bar);

            function setFull(on) {
                if (on) {
                    var titleField = document.getElementById('articleTitle');
                    var t = titleField && titleField.value.trim();
                    barTitle.textContent = t || label;
                }
                document.body.classList.toggle('kb-editor-full', on);
                openBtn.setAttribute('aria-expanded', on ? 'true' : 'false');
                /* TinyMCE lays the iframe out to a pixel height it worked out
                   when the container was small. Nudge it after the class flip
                   so it re-measures against the new box — without this the
                   editor is full-screen but the typing area is still 200px. */
                var ed = window.tinymce && window.tinymce.get('articleBody');
                if (ed) { setTimeout(function () { try { ed.execCommand('mceAutoResize'); } catch (e) {} window.dispatchEvent(new Event('resize')); }, 30); }
            }
            openBtn.addEventListener('click', function () {
                setFull(true);
                history.pushState({ kbFull: true }, '');
            });
            closeBtn.addEventListener('click', function () {
                if (history.state && history.state.kbFull) history.back();
                else setFull(false);
            });
            window.addEventListener('popstate', function () { setFull(false); });
        }

        // ---- mirror the current view onto <body> for CSS ----
        function readView() {
            var d = document.getElementById('articleDetailView');
            var e = document.getElementById('articleEditorView');
            if (e && e.style.display !== 'none' && e.style.display !== '') return 'editor';
            if (d && d.style.display !== 'none' && d.style.display !== '') return 'detail';
            return 'list';
        }
        // Named setKbView, not setView: the inbox branch has a top-level
        // setView-alike (`setPane`) and a plain `setView` here would read as
        // the calendar module's view toggle.
        function setKbView(v) { document.body.setAttribute('data-kb-view', v); }
        setKbView(readView());

        if (typeof window.showView === 'function') {
            var _showView = window.showView;
            window.showView = function (view) {
                var r = _showView.apply(this, arguments);
                setKbView(view || readView());
                stripEditorPopout();
                wireFullScreenEditor();
                // Leaving the editor must not strand the page in the overlay.
                if (view !== 'editor') document.body.classList.remove('kb-editor-full');
                return r;
            };
        }

        /* ⚠️ The #762 trap, second sighting. `applyEditorPopoutFromPref()`
           reads localStorage on every edit and re-applies `.editor-popout`,
           which gives the form a FIXED 340px property panel — the whole screen
           at 360px, leaving the editor itself nothing. Neutralise at the
           source (the CSS in 17d is only the backstop), and leave the stored
           preference alone so the desktop behaviour is unchanged. */
        function stripEditorPopout() {
            if (mq.matches) container.classList.remove('editor-popout');
        }
        ['applyEditorPopoutFromPref', 'toggleEditorPopout'].forEach(function (fn) {
            if (typeof window[fn] !== 'function') return;
            var _orig = window[fn];
            window[fn] = function () {
                var r = _orig.apply(this, arguments);
                stripEditorPopout();
                return r;
            };
        });
        stripEditorPopout();

        function syncKnowledgeBar() {
            var on = mq.matches;
            bar.style.display = on ? 'flex' : 'none';
            var vb = document.querySelector('.mobile-views-btn');
            if (vb) vb.style.display = on ? '' : 'none';
            syncBackLabel();
            if (on) { sidebarIntoPlace(); wireMetaToggle(); wireFullScreenEditor(); }
            else {
                // Leaving mobile: neither the meta block nor the full-screen
                // editor may be left in a mobile-only state on a desktop page.
                var head = document.querySelector('.article-content-header');
                if (head) head.classList.remove('kb-meta-open');
                document.body.classList.remove('kb-editor-full');
                document.body.classList.remove('mobile-views-open');
                hideSheet();
                sidebarBackToPage();
                document.body.removeAttribute('data-kb-view');
            }
        }
        syncKnowledgeBar();
        if (mq.addEventListener) { mq.addEventListener('change', syncKnowledgeBar); }
        else if (mq.addListener) { mq.addListener(syncKnowledgeBar); }
    }

    // ---- pane state, mirrored on <body> so CSS ancestor selectors can react ----
    function setPane(p) { document.body.setAttribute('data-mobile-pane', p); }
    function currentPane() { return document.body.getAttribute('data-mobile-pane') || 'list'; }

    // Navigate INTO a pane, pushing a history entry so the device Back button
    // (and our Back chevron) pops back out of it.
    function pushPane(p) {
        setPane(p);
        if (mq.matches) history.pushState({ nmPane: p }, '');
    }

    setPane('list');

    window.addEventListener('popstate', function (e) {
        if (!mq.matches) return;
        setPane((e.state && e.state.nmPane) ? e.state.nmPane : 'list');
    });

    // ---- wrap the globals inbox.js already exposes (don't edit inbox.js) ----
    if (typeof window.selectEmail === 'function') {
        var _selectEmail = window.selectEmail;
        window.selectEmail = function () {
            var r = _selectEmail.apply(this, arguments);
            // Push only when genuinely navigating list -> ticket. selectEmail is
            // also called to REFRESH an already-open ticket; those must not stack.
            if (mq.matches && currentPane() !== 'reading') pushPane('reading');
            // Once the ticket has rendered, move the link strips + properties
            // into their own sheets and apply the reading-pane refinements
            // (mobile only — see afterTicketRender).
            if (r && typeof r.then === 'function') r.then(afterTicketRender);
            else afterTicketRender();
            return r;
        };
    }

    if (typeof window.selectFolder === 'function') {
        var _selectFolder = window.selectFolder;
        window.selectFolder = function () {
            var r = _selectFolder.apply(this, arguments);
            // Picking a folder drops back to the list; pop the folders entry so
            // Back doesn't reopen the folder drawer.
            if (mq.matches && currentPane() === 'folders') history.back();
            return r;
        };
    }

    // The desktop "pop-out" (full-screen reading pane) mode is meaningless on a
    // phone — the reading pane is already full-screen via the master-detail
    // stack — and body.ticket-popout HIDES the email list (breaking Back) and
    // pads the reading pane by 340px. inbox.js re-applies it on every ticket
    // open when the saved pref is on, so strip it right after each sync here.
    if (typeof window.syncPopoutToTicketState === 'function') {
        var _syncPopout = window.syncPopoutToTicketState;
        window.syncPopoutToTicketState = function () {
            var r = _syncPopout.apply(this, arguments);
            if (mq.matches) document.body.classList.remove('ticket-popout');
            return r;
        };
    }

    // Attachments load async after the ticket renders; when the info bar is
    // (re)rendered, refresh the compact mobile badge that replaces it.
    if (typeof window.renderAttachmentInfoBar === 'function') {
        var _renderAttach = window.renderAttachmentInfoBar;
        window.renderAttachmentInfoBar = function () {
            var r = _renderAttach.apply(this, arguments);
            if (mq.matches) syncAttachBadge();
            return r;
        };
    }

    // ---- inject the sub-bar (Back / Folders), sitting above the pane area ----
    var bar = document.createElement('div');
    bar.className = 'mobile-subbar';
    bar.innerHTML =
        '<button type="button" class="msb-back" aria-label="Back">‹ Back</button>' +
        '<button type="button" class="msb-folders" aria-label="Folders">☰ Folders</button>' +
        '<span class="msb-ref" aria-label="Ticket reference"></span>';
    mc.parentNode.insertBefore(bar, mc);

    bar.querySelector('.msb-back').addEventListener('click', function () {
        if (currentPane() === 'list') return;
        // Force the list pane directly (guaranteed regardless of the history
        // stack), then pop the entry we pushed so the device Back button stays
        // in sync. Leading with setPane makes Back reliable even if history.back
        // has nothing to pop.
        setPane('list');
        if (history.state && history.state.nmPane) history.back();
    });
    bar.querySelector('.msb-folders').addEventListener('click', function () { pushPane('folders'); });

    // The views hamburger (top-right -> right drawer) and the company switcher
    // move are part of the shared shell now (#937) — see the top of the file.

    // ---- Gmail-style collapsible ticket header ----
    // The reading pane re-renders on each open, so delegate off the document.
    // The header starts collapsed (CSS default on mobile); tapping the subject
    // row toggles the full From / To / Date / Cc meta block.
    document.addEventListener('click', function (e) {
        if (!mq.matches || !e.target.closest) return;
        var line = e.target.closest('.email-subject-line');
        if (!line || e.target.closest('.ticket-popout-toggle')) return;
        var header = line.closest('.email-header');
        if (header) header.classList.toggle('meta-open');
    });

    // ---- Section sheets: crowded reading-pane sections get their own panel ----
    // On a phone, sections that don't fit (problem/change links, properties,
    // time entries, affected CMDB objects) are moved out of the ticket into a
    // full-screen sheet, each opened by a button added to the action toolbar.
    // Each sheet lives in the DOM (display:none until opened); on desktop nothing
    // is relocated or shown (relocateSections is mq-gated), so desktop is intact.
    var SECTIONS = [
        { cls: 'links', title: 'Links',            icon: '🔗', label: 'Links',      sel: '.problem-strip',             all: true  },
        { cls: 'props', title: 'Properties',       icon: '⚙',  label: 'Properties', sel: '#ticketPropertiesContainer', all: false },
        { cls: 'time',  title: 'Time',             icon: '⏱',  label: 'Time',       sel: '#timeEntriesContainer',      all: false },
        { cls: 'cmdb',  title: 'Objects',          icon: '🖥', label: 'Objects',    sel: '#cmdbObjectsContainer',      all: false }
    ];
    SECTIONS.forEach(function (def) {
        var sheet = document.createElement('div');
        sheet.className = 'mobile-sheet mobile-sheet-' + def.cls;
        sheet.style.display = 'none';
        sheet.innerHTML =
            '<div class="ms-head"><span>' + def.title + '</span>' +
            '<button type="button" class="ms-close" aria-label="Close">Close</button></div>' +
            '<div class="ms-body"></div>';
        document.body.appendChild(sheet);
        def.sheet = sheet;
        def.body = sheet.querySelector('.ms-body');
        sheet.querySelector('.ms-close').addEventListener('click', function () { sheet.style.display = 'none'; });
    });

    // Move each section's node(s) into its sheet and add its toolbar button.
    // Runs after every ticket render (the reading pane is rebuilt each time).
    // Time/CMDB containers may still be empty (populated async) — relocating the
    // container node is fine, its async loader finds it again by id.
    function relocateSections() {
        if (!mq.matches) return;
        var rp = document.getElementById('readingPane');
        if (!rp) return;
        var toolbar = rp.querySelector('.action-toolbar');
        if (!toolbar) return;
        SECTIONS.forEach(function (def) {
            var one = def.all ? null : rp.querySelector(def.sel);
            var nodes = def.all ? rp.querySelectorAll(def.sel) : (one ? [one] : []);
            if (!nodes.length) return;
            def.body.innerHTML = '';
            Array.prototype.forEach.call(nodes, function (n) { def.body.appendChild(n); });
            if (!toolbar.querySelector('.mobile-sheet-btn-' + def.cls)) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'action-btn mobile-sheet-btn mobile-sheet-btn-' + def.cls;
                btn.innerHTML = '<span class="action-btn-icon">' + def.icon + '</span><span>' + def.label + '</span>';
                btn.addEventListener('click', function () { def.sheet.style.display = 'flex'; });
                toolbar.appendChild(btn);
            }
        });
    }

    // ---- Opened-ticket refinements ----------------------------------------
    // Run after every ticket render: relocate the section sheets, then apply
    // the reading-pane tidy-ups (subject-only heading + reference in the sub-bar,
    // attachment badge, single-row action bar with a "…" overflow).
    function afterTicketRender() {
        relocateSections();
        decorateReadingPane();
    }

    // inbox.js keeps the open ticket in the top-level `currentEmail` binding
    // (shared across classic scripts). Read it defensively.
    function getCurrentEmail() {
        return (typeof currentEmail !== 'undefined') ? currentEmail : null;
    }

    function decorateReadingPane() {
        if (!mq.matches) return;
        var rp = document.getElementById('readingPane');
        if (!rp) return;
        var email = getCurrentEmail();

        // (1) Drop the "Ticket <ref> - " prefix from the heading (leave the bare
        //     subject) and pin the reference to the right of the sub-bar.
        var subj = rp.querySelector('.email-subject-text');
        if (subj && email) subj.textContent = email.subject || '';
        var ref = bar.querySelector('.msb-ref');
        if (ref) ref.textContent = email ? (email.ticket_number || '') : '';

        // (2) Attachment badge (also refreshed async once attachments arrive).
        syncAttachBadge();

        // (3) Collapse the action bar to five icons + a "…" overflow.
        buildToolbarOverflow();
    }

    // Compact yellow attachment badge on the subject row, replacing the full
    // "…has N attachments" bar (hidden on mobile). Tapping it opens the list.
    function syncAttachBadge() {
        if (!mq.matches) return;
        var rp = document.getElementById('readingPane');
        if (!rp) return;
        var line = rp.querySelector('.email-subject-line');
        if (!line) return;
        var atts = (typeof ticketAttachments !== 'undefined' && ticketAttachments) ? ticketAttachments : [];
        var badge = line.querySelector('.mobile-attach-badge');
        if (!atts.length) { if (badge) badge.style.display = 'none'; return; }
        var regular = atts.filter(function (a) { return !a.is_inline; }).length;
        var count = regular > 0 ? regular : atts.length;
        if (!badge) {
            badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'mobile-attach-badge';
            badge.addEventListener('click', function (e) {
                e.stopPropagation();          // don't toggle the header meta
                if (typeof showAttachmentList === 'function') showAttachmentList();
            });
            line.appendChild(badge);          // last real child → rides on the right
        }
        badge.style.display = 'inline-flex';
        badge.innerHTML = '<span class="mab-clip">📎</span><span class="mab-count">' + count + '</span>';
        badge.setAttribute('aria-label', count + ' attachment' + (count === 1 ? '' : 's'));
        badge.title = count + ' attachment' + (count === 1 ? '' : 's');
    }

    // Keep the action bar to a single row: five icons + a "…" button whose panel
    // holds the rest (with their word labels). The toolbar is rebuilt on every
    // render, so this re-collapses each time.
    function buildToolbarOverflow() {
        if (!mq.matches) return;
        var rp = document.getElementById('readingPane');
        if (!rp) return;
        var toolbar = rp.querySelector('.action-toolbar');
        if (!toolbar || toolbar.querySelector('.mobile-more-btn')) return;

        var btns = Array.prototype.filter.call(toolbar.children, function (el) {
            return el.classList && el.classList.contains('action-btn');
        });
        var KEEP = 5;
        if (btns.length <= KEEP + 1) return;   // already fits in one row

        var panel = document.createElement('div');
        panel.className = 'mobile-more-panel';
        panel.style.display = 'none';

        var moreBtn = document.createElement('button');
        moreBtn.type = 'button';
        moreBtn.className = 'action-btn mobile-more-btn';
        moreBtn.setAttribute('aria-label', 'More actions');
        moreBtn.innerHTML = '<span class="action-btn-icon">⋯</span>';
        moreBtn.addEventListener('click', function () {
            panel.style.display = (panel.style.display === 'none') ? 'flex' : 'none';
        });

        btns.slice(KEEP).forEach(function (b) {
            b.addEventListener('click', function () { panel.style.display = 'none'; });
            panel.appendChild(b);
        });

        toolbar.appendChild(moreBtn);
        toolbar.appendChild(panel);
    }

    // ---- Audit history: its own full-screen sheet (LAYER 10) ---------------
    // The desktop path (showAuditHistory) builds a 5-column table in a centred
    // .modal-overlay. On a phone that table is wider than the screen, which on
    // iOS makes Safari widen the layout to a desktop width — and at that width
    // the max-width:768px rules switch off, so the modal falls back to the
    // centred desktop box (the same "spills wide → reflows to desktop" failure
    // seen with the reply modal). Rather than fight that, mobile routes audit
    // through the SAME .mobile-sheet mechanism the Links/Properties/Time/Objects
    // sheets use — a position:fixed; inset:0 panel that's always full-screen —
    // and fills it with the narrow day-grouped feed, which can never spill.
    // Audit history isn't in the reading pane to relocate, so it's fetched on
    // demand (the same endpoint inbox.js uses). Desktop is untouched.
    var auditSheet = document.createElement('div');
    auditSheet.className = 'mobile-sheet mobile-sheet-audit';
    auditSheet.style.display = 'none';
    auditSheet.innerHTML =
        '<div class="ms-head"><span>History</span>' +
        '<button type="button" class="ms-close" aria-label="Close">Close</button></div>' +
        '<div class="ms-body"></div>';
    document.body.appendChild(auditSheet);
    var auditBody = auditSheet.querySelector('.ms-body');
    auditSheet.querySelector('.ms-close').addEventListener('click', function () { auditSheet.style.display = 'none'; });

    // On mobile, intercept the audit action entirely: open our sheet instead of
    // letting inbox.js build the desktop table modal. Desktop calls straight
    // through, unchanged.
    if (typeof window.showAuditHistory === 'function') {
        var _showAudit = window.showAuditHistory;
        window.showAuditHistory = function () {
            if (mq.matches) { openAuditSheet(); return; }
            return _showAudit.apply(this, arguments);
        };
    }

    function openAuditSheet() {
        var email = getCurrentEmail();
        if (!email || !email.ticket_id) return;
        auditBody.innerHTML = '<p class="ma-note">Loading…</p>';
        auditSheet.style.display = 'flex';
        var base = (typeof API_BASE !== 'undefined') ? API_BASE : 'api/';
        fetch(base + 'get_ticket_audit.php?ticket_id=' + encodeURIComponent(email.ticket_id))
            .then(function (r) { return r.json(); })
            .then(function (data) { renderAuditFeed((data && data.success && data.audit) ? data.audit : []); })
            .catch(function () { auditBody.innerHTML = '<p class="ma-note error">Failed to load history.</p>'; });
    }

    // Split "Mon, 14 Jul 2026 09:32 AM" (formatFullDateTime's shape) into the
    // day — said once, as a sticky heading — and the time, kept per entry. If
    // the format ever changes and the time can't be found, the whole stamp
    // rides in the time slot and the day headings simply don't appear.
    function splitStamp(text) {
        var m = /^(.*?)[\s,]*(\d{1,2}:\d{2}(?:\s?[AP]M)?)$/i.exec((text || '').trim());
        return m ? { day: m[1].trim(), time: m[2] } : { day: '', time: (text || '').trim() };
    }

    function span(cls, text) {
        var el = document.createElement('span');
        el.className = cls;
        el.textContent = text;         // textContent — safe, no manual escaping
        return el;
    }

    // Build the day-grouped card feed from the audit rows (newest first, as the
    // endpoint returns them). One card per change: field + time on top, old →
    // new beneath, who did it under that; the date is a sticky heading said
    // once per day.
    function renderAuditFeed(entries) {
        auditBody.innerHTML = '';
        if (!entries.length) {
            auditBody.appendChild(span('ma-note', 'No history for this ticket.'));
            return;
        }
        var lastDay = null;
        entries.forEach(function (e) {
            var stampText = (typeof formatFullDateTime === 'function')
                ? formatFullDateTime(e.created_datetime) : (e.created_datetime || '');
            var stamp = splitStamp(stampText);
            var field = (e.field_name || '').trim();
            var oldV  = (e.old_value || '').trim();
            var newV  = (e.new_value || '').trim();
            var who   = (e.analyst_name || 'Unknown').trim();

            if (stamp.day && stamp.day !== lastDay) {
                lastDay = stamp.day;
                auditBody.appendChild(span('ma-day', stamp.day));
            }

            var entry = document.createElement('div');
            entry.className = 'ma-entry';

            var top = document.createElement('div');
            top.className = 'ma-top';
            top.appendChild(span('ma-field', field));
            top.appendChild(span('ma-time', stamp.time));
            entry.appendChild(top);

            // A first-time set (old value "-") reads better as just the new
            // value than as "- → Open".
            var vals = document.createElement('div');
            vals.className = 'ma-vals';
            if (oldV && oldV !== '-' && oldV !== '') {
                vals.appendChild(span('ma-old', oldV));
                vals.appendChild(span('ma-arrow', '→'));
            }
            vals.appendChild(span('ma-new', (newV && newV !== '-') ? newV : '—'));
            entry.appendChild(vals);

            entry.appendChild(span('ma-who', who));
            auditBody.appendChild(entry);
        });
    }

    // Close the overflow panel when tapping outside it (or its button).
    document.addEventListener('click', function (e) {
        if (!mq.matches || !e.target.closest) return;
        var panel = document.querySelector('.mobile-more-panel');
        if (!panel || panel.style.display === 'none') return;
        if (e.target.closest('.mobile-more-panel') || e.target.closest('.mobile-more-btn')) return;
        panel.style.display = 'none';
    });

    // Injected chrome (sub-bar + views hamburger) is mobile-only; keep it out of
    // desktop entirely (belt-and-suspenders alongside the @media-only styling).
    function syncBar() {
        var on = mq.matches;
        bar.style.display = on ? 'flex' : 'none';
        var vb = document.querySelector('.mobile-views-btn');
        if (vb) vb.style.display = on ? '' : 'none';
        if (!on) document.body.classList.remove('mobile-views-open');   // reset on resize→desktop
    }
    syncBar();
    if (mq.addEventListener) { mq.addEventListener('change', syncBar); }
    else if (mq.addListener) { mq.addListener(syncBar); }

})();

/* ====================================================================
   TASKS — which board column am I on (#1205)

   A SEPARATE top-level IIFE, deliberately. The block above returns
   early on any page without an .email-list-container — it is the
   tickets inbox wiring — so anything appended inside it never runs
   anywhere else. The first version of this was written in there and
   simply did nothing: the dots were absent rather than broken, which
   is the quiet kind of failure. Its own IIFE, its own mq.
   ==================================================================== */
(function () {
    var mq = window.matchMedia('(max-width: 768px)');
    var board = document.getElementById('boardView');
    if (!board) return;                       // not the Tasks board page

    var dots = document.createElement('div');
    dots.className = 'tsk-board-dots';
    // ⚠️ Created hidden inline. mobile.css is @media-only, so it CANNOT
    // supply a desktop default of display:none — the element would show
    // on a wide screen. sync() below is what turns it on.
    dots.style.display = 'none';
    board.parentNode.insertBefore(dots, board.nextSibling);

    function columns() {
        return board.querySelectorAll('.board-column');
    }

    function build() {
        var n = columns().length;
        if (dots.childElementCount !== n) {
            dots.innerHTML = '';
            for (var i = 0; i < n; i++) {
                var d = document.createElement('span');
                d.className = 'tsk-board-dot';
                dots.appendChild(d);
            }
        }
        // One column is not a carousel — nothing to say, so say nothing.
        dots.style.display = (mq.matches && n > 1) ? 'flex' : 'none';
        mark();
    }

    function mark() {
        var cols = columns();
        if (!cols.length) return;
        // Which column is nearest the left edge of the scroller. Derived
        // from scrollLeft rather than a stored index, so it stays right
        // whether the board was swiped, scrolled or jumped to.
        var step = board.scrollWidth / cols.length;
        var at   = Math.round(board.scrollLeft / step);
        if (at < 0) at = 0;
        if (at > cols.length - 1) at = cols.length - 1;
        for (var i = 0; i < dots.children.length; i++) {
            dots.children[i].classList.toggle('is-current', i === at);
        }
    }

    var tick = null;
    board.addEventListener('scroll', function () {
        if (tick) return;
        tick = requestAnimationFrame(function () { tick = null; mark(); });
    }, { passive: true });

    // Wrap the global tasks.js already exposes. Guarded: if the board page
    // ever stops defining it, the dots simply never rebuild rather than
    // the whole mobile bundle throwing on load.
    if (typeof window.renderBoard === 'function') {
        var realRenderBoard = window.renderBoard;
        window.renderBoard = function () {
            var r = realRenderBoard.apply(this, arguments);
            build();
            return r;
        };
    }

    function sync() { build(); }
    sync();
    if (mq.addEventListener) { mq.addEventListener('change', sync); }
    else if (mq.addListener) { mq.addListener(sync); }
})();

/* ====================================================================
   FORMS — give a card feed its column labels back (#1289)

   ITS OWN top-level IIFE, for the reason the block above documents.

   Every card feed in this rollout so far has had to drop its column
   labels, because the only pure-CSS way to put one back is
   `td::before { content: "Submissions" }` — a hardcoded English string
   in a product that ships in 24 languages. So the rule became "reading
   order carries the meaning instead", which works when the values are
   self-describing (a name, a date, a status pill) and fails completely
   when they are not.

   The forms list is the case where it fails: two of its eight columns
   are BARE COUNTS, and "New Starter Request / v1 / Active / 7 / 10"
   tells you nothing about what 7 and 10 are.

   ⭐ But the labels are already on the page, already translated, in the
   `<thead>` the feed hides. So copy them onto the cells as a data
   attribute and let CSS print them with `attr()`. Zero invented
   strings, zero new locale keys, and it works for any table in any
   module — the same trick as harvesting `common.back` from a locale
   that already had the word, one level up.

   Desktop is untouched: the attribute is inert without the @media rule
   that prints it, and `thead` is only hidden below 768px.
   ==================================================================== */
(function () {
    var mq = window.matchMedia('(max-width: 768px)');

    /* Which columns get a label, per table. Deliberately a LIST rather
       than "all of them": a card whose every line is prefixed reads like
       a spreadsheet, and most of these columns are self-describing. Only
       the ones that are meaningless bare want one.

       Indexes are into the header row, zero-based:
         3 = the field count, 4 = the submission count. */
    var FEEDS = [
        { table: '#formsTable', columns: [3, 4] }
    ];

    function labelCardFeed(table, columns) {
        var head = table.tHead;
        if (!head || !head.rows.length) return;
        var headCells = head.rows[0].cells;
        var body = table.tBodies[0];
        if (!body) return;

        for (var r = 0; r < body.rows.length; r++) {
            var row = body.rows[r];
            for (var c = 0; c < columns.length; c++) {
                var i = columns[c];
                var cell = row.cells[i];
                if (!cell || !headCells[i]) continue;
                // ⚠️ The empty / loading / error row is a single
                // `<td colspan="8">`, so row.cells[3] on it is undefined
                // — but a two-cell variant would put the label on the
                // wrong thing. Skip any row that is not the full width.
                if (row.cells.length !== headCells.length) continue;

                // The header carries a sort-arrow span; take the text
                // nodes only so the arrow glyphs do not come with it.
                var text = '';
                var kids = headCells[i].childNodes;
                for (var k = 0; k < kids.length; k++) {
                    if (kids[k].nodeType === 3) text += kids[k].nodeValue;
                }
                text = text.replace(/\s+/g, ' ').trim();
                if (!text) continue;
                cell.setAttribute('data-mobile-label', text);
            }
        }
    }

    function apply() {
        if (!mq.matches) return;
        for (var i = 0; i < FEEDS.length; i++) {
            var t = document.querySelector(FEEDS[i].table);
            if (t) labelCardFeed(t, FEEDS[i].columns);
        }
    }

    // The list is re-rendered by the page's own renderForms() on load,
    // on search and on every sort, and it replaces the tbody's innerHTML
    // — so a one-shot pass at load would be undone by the first fetch
    // that resolves after it. Observing the table is cheaper and safer
    // than wrapping four call sites, and it cannot get out of step with
    // a renderer this file does not own.
    var watched = [];
    for (var i = 0; i < FEEDS.length; i++) {
        var el = document.querySelector(FEEDS[i].table);
        if (el) watched.push(el);
    }
    if (!watched.length) return;              // not a page with a labelled feed

    if (window.MutationObserver) {
        var obs = new MutationObserver(function () { apply(); });
        for (var j = 0; j < watched.length; j++) {
            obs.observe(watched[j], { childList: true, subtree: true });
        }
    }

    apply();
    if (mq.addEventListener) { mq.addEventListener('change', apply); }
    else if (mq.addListener) { mq.addListener(apply); }
})();

/* ====================================================================
   FORMS EDITOR — the four inspection tools join Save and Cancel in one
   bottom action bar, with a "…" overflow  (#1290, Ed's request)

   ITS OWN top-level IIFE, for the reason the blocks above document.

   On desktop the editor has two groups of controls: AI Assist / Versions
   / Save as new version / Properties in the top toolbar, and Cancel /
   Save in a sticky footer. That is a sound desktop split — inspection at
   the top, completion at the bottom. On a phone LAYER 27g stacked the
   top four into four full-width rows, which spent ~150px before a single
   form field. Ed asked for the tickets treatment instead: one row of
   icons at the bottom, and a "…" for whatever will not fit.

   What this does, and only when `mq.matches`:
     1. wraps each button's bare label text in a `.fm-label` span, so CSS
        can hide it on the bar and show it again in the overflow panel;
     2. moves the four toolbar buttons into the footer, before Save;
     3. measures, and pushes whatever does not fit into a
        `.mobile-more-panel` behind a "…" — LAYER 5's own class;
     4. puts every one of them back where it came from if the viewport
        goes wide again.

   Desktop is untouched: nothing runs, and step 4 makes a mid-session
   resize back to desktop exact rather than approximately right.
   ==================================================================== */
(function () {
    var mq = window.matchMedia('(max-width: 768px)');

    var footer = document.querySelector('.forms-edit-page .editor-footer');
    var toolbarActions = document.querySelector('.forms-edit-page .editor-toolbar-actions');
    if (!footer || !toolbarActions) return;          // not the form builder

    var saveBtn = footer.querySelector('.save-btn');

    // The four, in the order Ed asked for them. Each is looked up rather
    // than taken as "every child", because the toolbar also holds the
    // versions dropdown, which travels with its wrapper and must not be
    // treated as a button in its own right.
    var MOVERS = ['.btn-ai-assist', '#versionsWrap', '#newVersionBtn', '#propertiesBtn'];

    var moved = [];          // { el, parent, next } so the move is reversible

    // ---- 1. the label ---------------------------------------------------
    // ⚠️ These buttons carry their label as a BARE TEXT NODE beside an
    // <svg>, so `span:not(.action-btn-icon)` — LAYER 5's way of hiding a
    // label — has nothing to match. Wrap it once, idempotently.
    function wrapLabel(btn) {
        if (!btn || btn.querySelector('.fm-label')) return;
        var kids = Array.prototype.slice.call(btn.childNodes);
        var span = null;
        kids.forEach(function (n) {
            if (n.nodeType !== 3 || !n.nodeValue.trim()) return;
            if (!span) {
                span = document.createElement('span');
                span.className = 'fm-label';
                btn.insertBefore(span, n);
            }
            span.appendChild(n);          // moves the text node into the span
        });
        // Keep the accessible name intact: the label is now hidden by CSS on
        // the bar, so a button with no title would be an unlabelled icon.
        if (span && !btn.getAttribute('aria-label')) {
            btn.setAttribute('aria-label', span.textContent.trim());
        }
    }

    // ---- 3. the overflow -------------------------------------------------
    function clearOverflow() {
        var panel = footer.querySelector('.mobile-more-panel');
        var btn = footer.querySelector('.mobile-more-btn');
        if (panel) {
            // Put its contents back on the bar before removing it, or they
            // would be destroyed along with it.
            while (panel.firstChild) footer.insertBefore(panel.firstChild, panel);
            panel.parentNode.removeChild(panel);
        }
        if (btn) btn.parentNode.removeChild(btn);
    }

    function buildOverflow() {
        clearOverflow();

        // Only VISIBLE controls count. Versions is hidden for a brand-new
        // form and Save as new version for a frozen snapshot, and a hidden
        // button must not push a visible one into the overflow.
        var items = Array.prototype.filter.call(footer.children, function (el) {
            return el.offsetParent !== null && !el.classList.contains('mobile-more-panel');
        });
        if (items.length < 2) return;

        // Measure rather than assume a count: the bar's capacity depends on
        // the screen, and how many controls are showing depends on the form.
        var style = window.getComputedStyle(footer);
        var avail = footer.clientWidth
            - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        var gap = parseFloat(style.columnGap || style.gap) || 6;

        var used = 0, overflow = [];
        for (var i = 0; i < items.length; i++) {
            var w = items[i].getBoundingClientRect().width;
            var next = used + (used ? gap : 0) + w;
            // Reserve room for the "…" itself from the moment one is needed.
            var budget = (i === items.length - 1 && !overflow.length) ? avail : avail - (46 + gap);
            if (next > budget && items[i] !== saveBtn) {
                overflow.push(items[i]);
            } else {
                used = next;
            }
        }
        if (!overflow.length) return;      // everything fits — no "…" at all

        var panel = document.createElement('div');
        panel.className = 'mobile-more-panel';
        // ⚠️ Inline `display:none`. mobile.css is @media-only, so it cannot
        // give an injected node a desktop default — the documented trap.
        panel.style.display = 'none';

        var moreBtn = document.createElement('button');
        moreBtn.type = 'button';
        moreBtn.className = 'btn btn-secondary mobile-more-btn';
        moreBtn.innerHTML = '<span class="action-btn-icon">⋯</span>';
        // English, deliberately: LAYER 5's tickets bar labels its own "…" the
        // same way, there is no translated "More" anywhere in lang/ to
        // harvest, and inventing a key here would put ONE English string in
        // 23 locale files. Matching the existing button keeps it to one thing
        // to fix if a key is ever added. It is an aria-label, never rendered.
        moreBtn.setAttribute('aria-label', 'More actions');
        moreBtn.title = 'More actions';
        moreBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.style.display = (panel.style.display === 'none') ? 'flex' : 'none';
        });

        overflow.forEach(function (el) {
            el.addEventListener('click', function () { panel.style.display = 'none'; });
            panel.appendChild(el);
        });

        // Before Save, so Save stays the rightmost thing on the bar.
        footer.insertBefore(moreBtn, saveBtn || null);
        footer.appendChild(panel);
    }

    // ⚠️ LAYER 5's tap-outside-to-close handler lives in the main IIFE,
    // which returns early on any page without an .email-list-container. It
    // never runs here, so this block needs its own — the failure would have
    // been silent, the panel opening happily and never closing.
    document.addEventListener('click', function (e) {
        if (!mq.matches || !e.target.closest) return;
        var panel = footer.querySelector('.mobile-more-panel');
        if (!panel || panel.style.display === 'none') return;
        if (e.target.closest('.mobile-more-panel') || e.target.closest('.mobile-more-btn')) return;
        panel.style.display = 'none';
    });

    // ---- 2 & 4. move in, and put back ------------------------------------
    // ⚠️ Cancel is the ONE control here with no <svg> — it is a plain
    // `<button>Cancel</button>` — so hiding its label leaves an empty grey
    // rectangle on the bar. It looked like a broken button, because that is
    // exactly what it was. Anything without an icon gets one.
    // 🔑 The general rule: **before hiding a label, check every button
    // actually has something left to show.**
    var GLYPHS = { cancelEdit: '✕' };          // ✕
    function ensureIcon(btn) {
        if (!btn || btn.querySelector('svg') || btn.querySelector('.fm-glyph')) return;
        var on = btn.getAttribute('onclick') || '';
        var glyph = null;
        Object.keys(GLYPHS).forEach(function (fn) { if (on.indexOf(fn) === 0) glyph = GLYPHS[fn]; });
        if (!glyph) return;
        var s = document.createElement('span');
        s.className = 'fm-glyph';
        s.textContent = glyph;
        btn.insertBefore(s, btn.firstChild);
    }

    function moveIn() {
        // The first original child is Cancel; everything moved in goes BEFORE
        // it, in the order MOVERS lists, so the bar reads left to right the
        // way Ed asked for it. Inserting each at `firstChild` instead — which
        // is what the first version did — silently REVERSES the list.
        var anchor = footer.firstElementChild;
        MOVERS.forEach(function (sel) {
            var el = document.querySelector('.forms-edit-page ' + sel);
            if (!el || el.parentNode === footer) return;
            moved.push({ el: el, parent: el.parentNode, next: el.nextSibling });
            wrapLabel(el.classList.contains('versions-wrap') ? el.querySelector('.btn') : el);
            footer.insertBefore(el, anchor);
        });
        // Cancel and Save carry labels too, and Save's accent is what
        // identifies it once the bar is icons.
        Array.prototype.forEach.call(footer.querySelectorAll('.btn'), function (b) {
            wrapLabel(b);
            ensureIcon(b);
        });
    }

    function moveOut() {
        clearOverflow();
        moved.forEach(function (m) {
            if (m.next && m.next.parentNode === m.parent) m.parent.insertBefore(m.el, m.next);
            else m.parent.appendChild(m.el);
        });
        moved = [];
        // The .fm-label spans are left in place deliberately: they are inert
        // (the hiding rule is inside the @media block) and removing them
        // would mean unwrapping text nodes the page's own renderer may since
        // have replaced.
    }

    function sync() {
        if (mq.matches) {
            if (!moved.length) moveIn();
            buildOverflow();
        } else if (moved.length) {
            moveOut();
        }
    }

    sync();
    if (mq.addEventListener) { mq.addEventListener('change', sync); }
    else if (mq.addListener) { mq.addListener(sync); }

    // The Versions and Save-as-new-version buttons are shown/hidden by the
    // page's own JS once the form has loaded, which changes how many controls
    // the bar is carrying. Re-measure when that happens rather than guessing
    // at load time, when both are still hidden.
    if (window.MutationObserver) {
        var reflow = null;
        new MutationObserver(function () {
            if (!mq.matches || reflow) return;
            reflow = setTimeout(function () { reflow = null; buildOverflow(); }, 60);
        }).observe(footer, { attributes: true, attributeFilter: ['style'], subtree: true });
    }
})();
