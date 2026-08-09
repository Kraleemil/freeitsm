/**
 * War room — fallback chat.
 *
 * POLLING, NOT SSE. Apache + mod_php holds a process per open connection, so a
 * roomful of analysts each keeping an EventSource open would sit on a worker
 * each — during an incident, when the app is busiest. A short poll is stateless
 * and costs one indexed lookup that usually returns nothing.
 *
 * The poll doubles as the presence heartbeat, so there is no second request and
 * nothing to keep alive separately.
 */
(function () {
    'use strict';

    var POLL_MS = 3000;
    var API     = window.API_BASE || 'api/war-room/';

    var elMessages = document.getElementById('wrMessages');
    var elEmpty    = document.getElementById('wrEmpty');
    var elPresence = document.getElementById('wrPresence');
    var elComposer = document.getElementById('wrComposer');
    var elBody     = document.getElementById('wrBody');
    var elSend     = document.getElementById('wrSend');
    if (!elMessages || !elComposer) return;

    var teamId    = '';      // '' is the all-hands room
    var lastId    = 0;
    var timer     = null;
    var failures  = 0;

    function t(key, fallback) {
        if (typeof window.t !== 'function') return fallback;
        var v = window.t(key);
        return (!v || v === key) ? fallback : v;
    }

    /* Messages are other people's text. They are inserted with textContent, never
       innerHTML — this is a chat box, so anything else is a stored-XSS hole that
       every analyst walks into during an incident. */
    function renderMessage(m) {
        var row = document.createElement('div');
        row.className = 'wr-msg';
        row.dataset.id = m.id;

        var head = document.createElement('div');
        head.className = 'wr-msg-head';

        var who = document.createElement('span');
        who.className = 'wr-msg-author';
        who.textContent = m.author;

        var when = document.createElement('span');
        when.className = 'wr-msg-time';
        when.textContent = formatTime(m.created);

        head.appendChild(who);
        head.appendChild(when);

        var body = document.createElement('div');
        body.className = 'wr-msg-body';
        body.textContent = m.body;

        row.appendChild(head);
        row.appendChild(body);
        return row;
    }

    /* Server timestamps are UTC. tz.js turns them into the analyst's own zone,
       the same as everywhere else in the app. */
    function formatTime(s) {
        try {
            var d = (typeof parseUTCDate === 'function') ? parseUTCDate(s) : new Date(s.replace(' ', 'T') + 'Z');
            if (!d || isNaN(d.getTime())) return s;
            var opts = { hour: '2-digit', minute: '2-digit' };
            return d.toLocaleTimeString(document.documentElement.lang || undefined,
                (typeof tzOpts === 'function') ? tzOpts(opts) : opts);
        } catch (e) { return s; }
    }

    function atBottom() {
        return elMessages.scrollHeight - elMessages.scrollTop - elMessages.clientHeight < 60;
    }

    function append(messages) {
        if (!messages.length) return;
        // Only auto-scroll if the reader was already at the bottom — yanking the
        // view while somebody is reading back through an incident is worse than
        // making them scroll down themselves.
        var stick = atBottom();
        messages.forEach(function (m) {
            elMessages.appendChild(renderMessage(m));
            if (m.id > lastId) lastId = m.id;
        });
        if (elEmpty) elEmpty.style.display = 'none';
        if (stick) elMessages.scrollTop = elMessages.scrollHeight;
    }

    function renderPresence(names) {
        if (failures > 1) {
            elPresence.textContent = t('war-room.error.offline', 'Lost contact with the server');
            elPresence.classList.add('wr-presence-offline');
            return;
        }
        elPresence.classList.remove('wr-presence-offline');
        elPresence.textContent = names.length
            ? t('war-room.presence.here', 'Here now: {names}').replace('{names}', names.join(', '))
            : t('war-room.presence.nobody', 'Nobody else is here right now');
    }

    function poll() {
        var url = API + 'poll.php?team_id=' + encodeURIComponent(teamId) + '&since_id=' + lastId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) throw new Error('poll failed');
                failures = 0;
                append(d.messages || []);
                renderPresence(d.present || []);
            })
            .catch(function () {
                /* Say so rather than sitting there looking fine. The one thing
                   this module must never do is let somebody believe the room is
                   quiet when actually nothing is getting through. */
                failures++;
                renderPresence([]);
            });
    }

    function startPolling() {
        if (timer) clearInterval(timer);
        poll();
        timer = setInterval(poll, POLL_MS);
    }

    function switchChannel(newTeamId) {
        teamId = newTeamId;
        lastId = 0;
        elMessages.innerHTML = '';
        if (elEmpty) { elMessages.appendChild(elEmpty); elEmpty.style.display = ''; }
        startPolling();
    }

    document.getElementById('wrChannels').addEventListener('click', function (e) {
        var btn = e.target.closest('.wr-channel');
        if (!btn) return;
        [].forEach.call(this.querySelectorAll('.wr-channel'), function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        switchChannel(btn.dataset.teamId || '');
    });

    function send() {
        var body = elBody.value.trim();
        if (!body) return;
        elSend.disabled = true;
        fetch(API + 'send.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_id: teamId === '' ? null : parseInt(teamId, 10), body: body })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) throw new Error('send failed');
                elBody.value = '';
                /* Deliberately NOT rendered locally: the next poll brings it
                   back from the server, so what you see is what everybody else
                   sees rather than an optimistic copy that might not have
                   saved. In a fallback tool, "it looked like it sent" is the
                   failure mode to design out. */
                poll();
            })
            .catch(function () {
                if (typeof showToast === 'function') showToast(t('war-room.error.send', 'Could not send that message'), 'error');
                else alert(t('war-room.error.send', 'Could not send that message'));
            })
            .then(function () { elSend.disabled = false; elBody.focus(); });
    }

    elComposer.addEventListener('submit', function (e) { e.preventDefault(); send(); });

    // Enter sends, Shift+Enter makes a new line — the convention everybody
    // already has in their fingers from every other chat tool.
    elBody.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });

    startPolling();
})();
