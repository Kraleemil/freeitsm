/**
 * War room — the client half.
 *
 * 🔒 EVERY PIECE OF MESSAGE TEXT IS INSERTED WITH textContent. Never innerHTML,
 * never a template string built from a message body, never an attachment's
 * filename dropped into markup. A chat box that renders other people's text as
 * HTML is a stored-XSS hole that every analyst walks into during an incident,
 * which is the worst possible moment for it. There is no sanitiser here on
 * purpose: not producing HTML is stronger than cleaning it afterwards.
 *
 * ⚠️ NOTHING ON THIS PAGE MAY LOAD FROM THE INTERNET. No CDN, no font, no remote
 * image. If it cannot be served from this box, it does not belong here.
 *
 * The poll is the module's heartbeat: it fetches new messages, records presence,
 * reports who else is here, and refreshes the channel list with its unread
 * counts — one request rather than four. See api/war-room/poll.php for why it is
 * a poll and not an EventSource.
 */
(function () {
    'use strict';

    var POLL_MS = 3000;

    var api       = window.API_BASE;
    var activeId  = window.WR_ACTIVE || 0;
    var maxFiles  = window.WR_MAX_FILES || 5;
    var directory = window.WR_DIRECTORY || [];

    var lastId    = 0;
    var timer     = null;
    var offline   = false;
    var channels  = [];
    var pending   = [];      // files chosen but not yet sent

    var els = {};
    ['wrChannels', 'wrMessages', 'wrEmpty', 'wrComposer', 'wrBody', 'wrSend', 'wrPresence',
     'wrTitle', 'wrTopic', 'wrPanel', 'wrPanelTitle', 'wrPanelBody', 'wrPanelClose',
     'wrModal', 'wrModalTitle', 'wrModalBody', 'wrModalClose', 'wrSearchBtn', 'wrSitrepBtn',
     'wrManageBtn', 'wrNewChannel', 'wrNewDm', 'wrFiles', 'wrPending', 'wrArchivedNote',
     'wrAttachLabel'].forEach(function (id) { els[id] = document.getElementById(id); });

    function t(key, vars) {
        return (typeof window.t === 'function') ? window.t(key, vars) : key;
    }

    /* ── small DOM helpers ────────────────────────────────────────────────────
       el() takes TEXT, not markup, which is what keeps the XSS rule above from
       depending on anybody remembering it at each call site. */
    function el(tag, className, text) {
        var n = document.createElement(tag);
        if (className) n.className = className;
        if (text !== undefined && text !== null) n.textContent = String(text);
        return n;
    }
    function clear(node) { while (node.firstChild) node.removeChild(node.firstChild); }

    /** Local time from the server's UTC string, via the app's shared tz helper. */
    function localTime(utc) {
        if (window.TZ && typeof window.TZ.time === 'function') return window.TZ.time(utc);
        var d = new Date(String(utc).replace(' ', 'T') + 'Z');
        return isNaN(d) ? '' : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function bytes(n) {
        if (n < 1024) return n + ' B';
        if (n < 1048576) return Math.round(n / 1024) + ' KB';
        return (n / 1048576).toFixed(1) + ' MB';
    }

    /* ── channel list ──────────────────────────────────────────────────────── */

    var GROUPS = [
        { kind: 'team',   key: 'war-room.channel.teams' },
        { kind: 'custom', key: 'war-room.channel.channels' },
        { kind: 'dm',     key: 'war-room.channel.direct' }
    ];

    function renderChannels() {
        clear(els.wrChannels);

        channels.filter(function (c) { return c.kind === 'all'; }).forEach(addChannelButton);
        GROUPS.forEach(function (g) {
            var inGroup = channels.filter(function (c) { return c.kind === g.kind; });
            if (!inGroup.length) return;
            els.wrChannels.appendChild(el('div', 'wr-group', t(g.key)));
            inGroup.forEach(addChannelButton);
        });
    }

    function addChannelButton(c) {
        var btn = el('button', 'wr-channel' + (c.id === activeId ? ' active' : ''));
        btn.type = 'button';
        btn.setAttribute('data-channel-id', c.id);
        btn.setAttribute('data-kind', c.kind);
        btn.appendChild(el('span', 'wr-channel-name', c.name + (c.archived ? ' (' + t('war-room.channel.archived') + ')' : '')));
        if (c.is_private && c.kind === 'custom') btn.appendChild(el('span', 'wr-lock', '•'));
        // The unread count is suppressed for the channel you are looking at:
        // it would tick up and then vanish on every poll, which reads as a bug.
        if (c.unread > 0 && c.id !== activeId) btn.appendChild(el('span', 'wr-unread', c.unread > 99 ? '99+' : c.unread));
        els.wrChannels.appendChild(btn);
    }

    function currentChannel() {
        for (var i = 0; i < channels.length; i++) if (channels[i].id === activeId) return channels[i];
        return null;
    }

    function applyChannelHeader() {
        var c = currentChannel();
        if (!c) return;
        els.wrTitle.textContent = c.name;
        els.wrTopic.textContent = c.topic || '';
        els.wrTopic.hidden = !c.topic;

        // An archived channel stays readable; only posting stops. Showing the
        // composer greyed with an explanation beats hiding it, which would look
        // like the page had failed to load.
        var archived = !!c.archived;
        els.wrArchivedNote.hidden = !archived;
        els.wrComposer.hidden = archived;
        els.wrManageBtn.hidden = (c.kind !== 'custom');
    }

    function switchChannel(id) {
        if (id === activeId) return;
        activeId = id;
        lastId = 0;
        clear(els.wrMessages);
        els.wrMessages.appendChild(els.wrEmpty);
        els.wrEmpty.hidden = false;
        renderChannels();
        applyChannelHeader();
        closePanel();
        poll();
    }

    els.wrChannels.addEventListener('click', function (e) {
        var btn = e.target.closest('.wr-channel');
        if (btn) switchChannel(parseInt(btn.getAttribute('data-channel-id'), 10));
    });

    /* ── messages ──────────────────────────────────────────────────────────── */

    function renderMessage(m) {
        var row = el('div', 'wr-msg');
        var head = el('div', 'wr-msg-head');
        head.appendChild(el('span', 'wr-msg-author', m.author));
        head.appendChild(el('span', 'wr-msg-time', localTime(m.created)));
        row.appendChild(head);

        var body = el('div', 'wr-msg-body', m.body);   // textContent — see the header
        row.appendChild(body);

        if (m.attachments && m.attachments.length) {
            var wrap = el('div', 'wr-attachments');
            m.attachments.forEach(function (a) {
                var href = api + 'attachment.php?id=' + encodeURIComponent(a.id);
                if (a.inline && /^image\//.test(guessKind(a.name))) {
                    // Inline previews are only ever for types OUR OWN map marks
                    // renderable (see attachmentServeRules) — the flag comes from
                    // the server, never from the uploader.
                    var link = el('a', 'wr-thumb');
                    link.href = href; link.target = '_blank'; link.rel = 'noopener';
                    var img = document.createElement('img');
                    img.src = href; img.alt = a.name; img.loading = 'lazy';
                    link.appendChild(img);
                    wrap.appendChild(link);
                } else {
                    var f = el('a', 'wr-file');
                    f.href = href; f.target = '_blank'; f.rel = 'noopener';
                    f.appendChild(el('span', 'wr-file-name', a.name));
                    f.appendChild(el('span', 'wr-file-size', bytes(a.size)));
                    wrap.appendChild(f);
                }
            });
            row.appendChild(wrap);
        }
        return row;
    }

    /** Only used to decide thumbnail vs link; the server decides what is served. */
    function guessKind(name) {
        var ext = String(name).split('.').pop().toLowerCase();
        return ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'].indexOf(ext) >= 0 ? 'image/' + ext : 'other';
    }

    function appendMessages(list) {
        if (!list.length) return;
        els.wrEmpty.hidden = true;
        // "Was the reader at the bottom before we appended?" has to be asked
        // BEFORE appending, or the answer is always no and the view jumps away
        // from whatever they had scrolled back to read.
        var atBottom = (els.wrMessages.scrollHeight - els.wrMessages.scrollTop - els.wrMessages.clientHeight) < 80;
        list.forEach(function (m) {
            els.wrMessages.appendChild(renderMessage(m));
            if (m.id > lastId) lastId = m.id;
        });
        if (atBottom) els.wrMessages.scrollTop = els.wrMessages.scrollHeight;
    }

    /* ── presence ──────────────────────────────────────────────────────────── */

    function renderPresence(p) {
        clear(els.wrPresence);
        if (offline) {
            els.wrPresence.appendChild(el('div', 'wr-presence-offline', t('war-room.error.offline')));
            return;
        }
        var here = (p && p.here) || [], away = (p && p.elsewhere) || [];
        if (!here.length && !away.length) {
            els.wrPresence.appendChild(el('div', 'wr-presence-none', t('war-room.presence.nobody')));
            return;
        }
        if (here.length) {
            els.wrPresence.appendChild(el('div', 'wr-presence-label', t('war-room.presence.here')));
            here.forEach(function (n) { els.wrPresence.appendChild(el('div', 'wr-presence-name', n)); });
        }
        if (away.length) {
            els.wrPresence.appendChild(el('div', 'wr-presence-label', t('war-room.presence.elsewhere')));
            away.forEach(function (n) { els.wrPresence.appendChild(el('div', 'wr-presence-name wr-away', n)); });
        }
    }

    /* ── the poll ──────────────────────────────────────────────────────────── */

    function poll() {
        var url = api + 'poll.php?channel_id=' + encodeURIComponent(activeId) +
                  '&since_id=' + encodeURIComponent(lastId) + '&read=1';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) throw new Error('poll failed');
                offline = false;
                appendMessages(d.messages || []);
                channels = d.channels || [];
                renderChannels();
                applyChannelHeader();
                renderPresence(d.present);
            })
            .catch(function () {
                // ⚠️ Say so. The one thing this module must never do is let
                // somebody believe their message is being delivered when it is
                // not — silence would look exactly like a quiet channel.
                offline = true;
                renderPresence(null);
            });
    }

    function startPolling() {
        if (timer) clearInterval(timer);
        timer = setInterval(poll, POLL_MS);
    }

    /* ── sending ───────────────────────────────────────────────────────────── */

    function renderPending() {
        clear(els.wrPending);
        els.wrPending.hidden = !pending.length;
        pending.forEach(function (f, i) {
            var chip = el('span', 'wr-chip');
            chip.appendChild(el('span', null, f.name));
            var x = el('button', 'wr-chip-x', '×');
            x.type = 'button';
            x.addEventListener('click', function () { pending.splice(i, 1); renderPending(); });
            chip.appendChild(x);
            els.wrPending.appendChild(chip);
        });
    }

    els.wrFiles.addEventListener('change', function () {
        var chosen = Array.prototype.slice.call(els.wrFiles.files || []);
        if (pending.length + chosen.length > maxFiles) {
            alert(t('war-room.attach.too_many', { count: maxFiles }));
            chosen = chosen.slice(0, Math.max(0, maxFiles - pending.length));
        }
        pending = pending.concat(chosen);
        els.wrFiles.value = '';
        renderPending();
    });

    els.wrComposer.addEventListener('submit', function (e) {
        e.preventDefault();
        send();
    });

    // Enter sends, Shift+Enter starts a new line — the convention every chat tool
    // uses, so muscle memory works on the day somebody first opens this.
    els.wrBody.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });

    function send() {
        var body = els.wrBody.value;
        if (!body.trim() && !pending.length) return;

        els.wrSend.disabled = true;
        var done = function () { els.wrSend.disabled = false; };

        var request;
        if (pending.length) {
            var fd = new FormData();
            fd.append('channel_id', activeId);
            fd.append('body', body);
            pending.forEach(function (f) { fd.append('files[]', f); });
            request = fetch(api + 'send.php', { method: 'POST', credentials: 'same-origin', body: fd });
        } else {
            request = fetch(api + 'send.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ channel_id: activeId, body: body })
            });
        }

        request.then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) throw new Error(d && d.error || 'send failed');
                els.wrBody.value = '';
                pending = [];
                renderPending();
                // A file we refused is reported by name. Posting the message and
                // silently dropping the screenshot would be the worst outcome:
                // the sender believes everyone can see it.
                if (d.rejected && d.rejected.length) {
                    alert(t('war-room.attach.rejected', {
                        names: d.rejected.map(function (r) { return r.name + ' — ' + r.reason; }).join('; ')
                    }));
                }
                poll();
            })
            .catch(function () { alert(t('war-room.error.send')); })
            .then(done, done);
    }

    /* ── panels (search, situation report) ─────────────────────────────────── */

    function openPanel(title) {
        els.wrPanelTitle.textContent = title;
        clear(els.wrPanelBody);
        els.wrPanel.hidden = false;
        return els.wrPanelBody;
    }
    function closePanel() { els.wrPanel.hidden = true; }
    els.wrPanelClose.addEventListener('click', closePanel);

    els.wrSearchBtn.addEventListener('click', function () {
        var body = openPanel(t('war-room.search.heading'));

        var input = document.createElement('input');
        input.type = 'search';
        input.className = 'wr-search-input';
        input.placeholder = t('war-room.search.placeholder');
        body.appendChild(input);

        var scope = el('label', 'wr-scope');
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        scope.appendChild(cb);
        scope.appendChild(el('span', null, t('war-room.search.this_channel')));
        body.appendChild(scope);

        var results = el('div', 'wr-results');
        body.appendChild(results);

        var debounce = null;
        function run() {
            var q = input.value.trim();
            clear(results);
            if (!q) return;
            results.appendChild(el('div', 'wr-muted', t('war-room.search.searching')));
            var url = api + 'search.php?q=' + encodeURIComponent(q) +
                      (cb.checked ? '&channel_id=' + encodeURIComponent(activeId) : '');
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    clear(results);
                    if (!d || !d.success) throw new Error('search failed');
                    if (!d.results.length) { results.appendChild(el('div', 'wr-muted', t('war-room.search.no_results'))); return; }
                    results.appendChild(el('div', 'wr-muted', t('war-room.search.results', { count: d.results.length })));
                    d.results.forEach(function (r) {
                        var item = el('button', 'wr-result');
                        item.type = 'button';
                        var head = el('div', 'wr-result-head');
                        head.appendChild(el('span', 'wr-result-channel', r.channel));
                        head.appendChild(el('span', 'wr-result-meta', r.author + ' · ' + localTime(r.created)));
                        item.appendChild(head);
                        item.appendChild(el('div', 'wr-result-snippet', r.snippet));
                        item.addEventListener('click', function () { switchChannel(r.channel_id); });
                        results.appendChild(item);
                    });
                })
                .catch(function () { clear(results); results.appendChild(el('div', 'wr-muted', t('war-room.search.failed'))); });
        }
        input.addEventListener('input', function () { clearTimeout(debounce); debounce = setTimeout(run, 250); });
        cb.addEventListener('change', run);
        input.focus();
    });

    els.wrSitrepBtn.addEventListener('click', function () {
        var body = openPanel(t('war-room.sitrep.heading'));
        body.appendChild(el('p', 'wr-muted', t('war-room.sitrep.intro')));

        var form = el('div', 'wr-sitrep-form');
        form.appendChild(el('label', 'wr-field-label', t('war-room.sitrep.since')));
        var hours = document.createElement('select');
        [[0.5, t('war-room.sitrep.minutes', { count: 30 })],
         [1, t('war-room.sitrep.hour')],
         [2, t('war-room.sitrep.hours', { count: 2 })],
         [4, t('war-room.sitrep.hours', { count: 4 })],
         [8, t('war-room.sitrep.hours', { count: 8 })],
         [24, t('war-room.sitrep.hours', { count: 24 })]].forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o[0]; opt.textContent = o[1];
            if (o[0] === 4) opt.selected = true;
            hours.appendChild(opt);
        });
        form.appendChild(hours);

        var scope = document.createElement('select');
        [['', t('war-room.sitrep.scope_all')], ['this', t('war-room.sitrep.scope_this')]].forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o[0]; opt.textContent = o[1];
            scope.appendChild(opt);
        });
        form.appendChild(scope);

        var go = el('button', 'btn btn-primary wr-sitrep-go', t('war-room.sitrep.generate'));
        go.type = 'button';
        form.appendChild(go);
        body.appendChild(form);

        var out = el('div', 'wr-sitrep-out');
        body.appendChild(out);

        go.addEventListener('click', function () {
            clear(out);
            out.appendChild(el('div', 'wr-muted', t('war-room.sitrep.working')));
            go.disabled = true;
            fetch(api + 'sitrep.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    hours: parseFloat(hours.value),
                    channel_id: scope.value === 'this' ? activeId : null
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    clear(out);
                    if (d && d.success && d.empty) { out.appendChild(el('div', 'wr-muted', t('war-room.sitrep.empty'))); return; }
                    if (!d || !d.success) {
                        var key = d && d.error === 'not_configured' ? 'war-room.sitrep.not_configured'
                                : d && d.error === 'provider_unreachable' ? 'war-room.sitrep.unreachable'
                                : 'war-room.sitrep.failed';
                        out.appendChild(el('div', 'wr-warn', t(key)));
                        return;
                    }
                    // The report is the model's prose. It is inserted as TEXT in a
                    // pre-wrap block — no markdown rendering — because turning
                    // model output into HTML is exactly the shortcut that would
                    // undo the rule at the top of this file.
                    out.appendChild(el('div', 'wr-report', d.report));

                    var foot = el('div', 'wr-report-foot');
                    foot.appendChild(el('span', null, t('war-room.sitrep.footer', { messages: d.messages, model: d.model })));
                    var copy = el('button', 'wr-tool', t('war-room.sitrep.copy'));
                    copy.type = 'button';
                    copy.addEventListener('click', function () {
                        if (navigator.clipboard) navigator.clipboard.writeText(d.report);
                        copy.textContent = t('war-room.sitrep.copied');
                    });
                    foot.appendChild(copy);
                    out.appendChild(foot);
                })
                .catch(function () { clear(out); out.appendChild(el('div', 'wr-warn', t('war-room.sitrep.failed'))); })
                .then(function () { go.disabled = false; }, function () { go.disabled = false; });
        });
    });

    /* ── dialogs (new channel, new DM, manage) ─────────────────────────────── */

    function openModal(title) {
        els.wrModalTitle.textContent = title;
        clear(els.wrModalBody);
        els.wrModal.hidden = false;
        return els.wrModalBody;
    }
    function closeModal() { els.wrModal.hidden = true; }
    els.wrModalClose.addEventListener('click', closeModal);
    els.wrModal.addEventListener('click', function (e) { if (e.target === els.wrModal) closeModal(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeModal(); closePanel(); }
    });

    function field(parent, labelText, node) {
        parent.appendChild(el('label', 'wr-field-label', labelText));
        parent.appendChild(node);
        return node;
    }

    function post(action, payload, onOk, failKey) {
        payload.action = action;
        fetch(api + 'channels.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) throw new Error(d && d.error || 'failed');
                onOk(d);
            })
            .catch(function (err) {
                alert(err.message === 'name_required' ? t('war-room.create.name_required') : t(failKey));
            });
    }

    els.wrNewChannel.addEventListener('click', function () {
        var body = openModal(t('war-room.create.heading'));

        var name = document.createElement('input');
        name.type = 'text'; name.className = 'wr-input'; name.maxLength = 120;
        field(body, t('war-room.create.name'), name);
        body.appendChild(el('div', 'wr-hint', t('war-room.create.name_hint')));

        var topic = document.createElement('input');
        topic.type = 'text'; topic.className = 'wr-input'; topic.maxLength = 255;
        field(body, t('war-room.create.topic'), topic);

        var privWrap = el('label', 'wr-scope');
        var priv = document.createElement('input');
        priv.type = 'checkbox';
        privWrap.appendChild(priv);
        privWrap.appendChild(el('span', null, t('war-room.create.private')));
        body.appendChild(privWrap);

        // The member picker only appears for a private channel, because for a
        // public one the answer is "everybody" and offering a list would imply
        // otherwise.
        var members = el('div', 'wr-members');
        members.hidden = true;
        directory.forEach(function (p) {
            var row = el('label', 'wr-member');
            var cb = document.createElement('input');
            cb.type = 'checkbox'; cb.value = p.id;
            row.appendChild(cb);
            row.appendChild(el('span', null, p.name));
            if (p.here) row.appendChild(el('span', 'wr-here-dot', '•'));
            members.appendChild(row);
        });
        body.appendChild(members);
        priv.addEventListener('change', function () { members.hidden = !priv.checked; });

        var actions = el('div', 'wr-modal-actions');
        var create = el('button', 'btn btn-primary', t('war-room.create.create'));
        create.type = 'button';
        create.addEventListener('click', function () {
            var chosen = Array.prototype.slice.call(members.querySelectorAll('input:checked'))
                .map(function (c) { return parseInt(c.value, 10); });
            post('create', {
                name: name.value, topic: topic.value,
                is_private: priv.checked, members: chosen
            }, function (d) {
                closeModal();
                poll();
                switchChannel(d.channel_id);
            }, 'war-room.create.failed');
        });
        actions.appendChild(create);
        var cancel = el('button', 'btn', t('war-room.create.cancel'));
        cancel.type = 'button';
        cancel.addEventListener('click', closeModal);
        actions.appendChild(cancel);
        body.appendChild(actions);
        name.focus();
    });

    els.wrNewDm.addEventListener('click', function () {
        var body = openModal(t('war-room.dm.heading'));
        if (!directory.length) { body.appendChild(el('div', 'wr-muted', t('war-room.dm.nobody'))); return; }

        var search = document.createElement('input');
        search.type = 'search'; search.className = 'wr-input';
        search.placeholder = t('war-room.dm.search');
        body.appendChild(search);

        var list = el('div', 'wr-members');
        body.appendChild(list);

        function draw(filter) {
            clear(list);
            directory.filter(function (p) {
                return !filter || p.name.toLowerCase().indexOf(filter) >= 0;
            }).forEach(function (p) {
                var row = el('button', 'wr-member wr-member-btn');
                row.type = 'button';
                row.appendChild(el('span', null, p.name));
                if (p.here) row.appendChild(el('span', 'wr-here-dot', '• ' + t('war-room.dm.here_now')));
                row.addEventListener('click', function () {
                    post('dm', { analyst_id: p.id }, function (d) {
                        closeModal();
                        poll();
                        switchChannel(d.channel_id);
                    }, 'war-room.dm.failed');
                });
                list.appendChild(row);
            });
        }
        draw('');
        search.addEventListener('input', function () { draw(search.value.trim().toLowerCase()); });
        search.focus();
    });

    els.wrManageBtn.addEventListener('click', function () {
        var c = currentChannel();
        if (!c || c.kind !== 'custom') return;
        var body = openModal(t('war-room.manage.heading'));

        var name = document.createElement('input');
        name.type = 'text'; name.className = 'wr-input'; name.maxLength = 120; name.value = c.name;
        field(body, t('war-room.create.name'), name);

        var topic = document.createElement('input');
        topic.type = 'text'; topic.className = 'wr-input'; topic.maxLength = 255; topic.value = c.topic || '';
        field(body, t('war-room.create.topic'), topic);

        body.appendChild(el('div', 'wr-hint', t('war-room.manage.archive_hint')));

        var actions = el('div', 'wr-modal-actions');
        var save = el('button', 'btn btn-primary', t('war-room.manage.save'));
        save.type = 'button';
        save.addEventListener('click', function () {
            post('update', { channel_id: c.id, name: name.value, topic: topic.value },
                function () { closeModal(); poll(); }, 'war-room.manage.failed');
        });
        actions.appendChild(save);

        var arch = el('button', 'btn', c.archived ? t('war-room.manage.restore') : t('war-room.manage.archive'));
        arch.type = 'button';
        arch.addEventListener('click', function () {
            post(c.archived ? 'restore' : 'archive', { channel_id: c.id },
                function () { closeModal(); poll(); }, 'war-room.manage.failed');
        });
        actions.appendChild(arch);

        var cancel = el('button', 'btn', t('war-room.create.cancel'));
        cancel.type = 'button';
        cancel.addEventListener('click', closeModal);
        actions.appendChild(cancel);
        body.appendChild(actions);
    });

    /* ── go ────────────────────────────────────────────────────────────────── */

    poll();
    startPolling();

    // Polling stops while the tab is hidden and resumes with an immediate fetch,
    // so twenty backgrounded tabs are not quietly hammering the server through an
    // incident — the exact moment it can least afford it.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { clearInterval(timer); timer = null; }
        else { poll(); startPolling(); }
    });
})();
