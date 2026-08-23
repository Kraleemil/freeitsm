/**
 * Behaviour for the shared "add this calendar to your phone" dialogue.
 *
 * Markup comes from includes/subscribe_modal.php; both the Calendar module and
 * Preferences (an analyst's own scheduled work) mount one. Keyed by the same id
 * prefix the markup was rendered with, so a page could carry more than one.
 *
 * Each mount names the endpoint that mints its feed URL. That endpoint returns
 * { scheme, host, path, url, insecure } — the components as well as the finished
 * URL, so the host can be swapped in the browser without another round trip.
 *
 * ⚠️ Needs qrcode.min.js. Without it everything still works; only the QR is
 * missing, which is why its failure is swallowed rather than reported.
 */
(function () {
    'use strict';

    if (window.FreeITSMSubscribe) return;

    var mounts = {};      // id -> { endpoint, state, onReset }

    function $(id) { return document.getElementById(id); }

    function t(key, fallback) {
        if (typeof window.t === 'function') {
            var got = window.t(key);
            if (got && got !== key) return got;
        }
        return fallback;
    }

    /** Rebuild the URL + QR from whatever host is currently typed. */
    function refresh(id) {
        var m = mounts[id];
        if (!m || !m.state) return;
        var hostEl = $(id + 'Host');
        var host   = (hostEl && hostEl.value.trim()) ? hostEl.value.trim() : m.state.host;
        var url    = m.state.scheme + '://' + host + m.state.path;

        var input = $(id + 'Url');
        if (input) input.value = url;

        var qr = $(id + 'Qr');
        if (qr) {
            qr.innerHTML = '';
            try {
                // The QR encodes the webcal:// form, so an iPhone camera scan
                // offers "Subscribe" rather than opening a download.
                var q = qrcode(0, 'M');
                q.addData('webcal://' + host + m.state.path);
                q.make();
                qr.innerHTML = q.createImgTag(4, 0);
            } catch (e) {
                // QR is a convenience; the copyable link is the actual feature.
            }
        }
    }

    function apply(id, d) {
        var m = mounts[id];
        m.state = { scheme: d.scheme, host: d.host, path: d.path };

        var hostEl = $(id + 'Host');
        if (hostEl) {
            // Reached on loopback? Default to the detected LAN address, because a
            // phone cannot resolve localhost — the URL would be useless as shown.
            var isLocal = /^(localhost|127\.|\[?::1\]?)/i.test(d.host || '');
            hostEl.value = (isLocal && d.suggestedHost) ? d.suggestedHost : d.host;
            hostEl.oninput = function () { refresh(id); };
        }

        var warn = $(id + 'Insecure');
        if (warn) warn.style.display = d.insecure ? '' : 'none';

        refresh(id);
    }

    /**
     * Endpoints differ in shape: the calendar's returns scheme/host/path, the
     * work-calendar one returns a finished url. Accept either, so a caller is
     * not forced to change its API to use this.
     */
    function normalise(d) {
        if (d.path) return d;
        var a = document.createElement('a');
        a.href = d.url || '';
        return {
            scheme: (a.protocol || 'http:').replace(':', ''),
            host: a.host,
            path: a.pathname + a.search,
            suggestedHost: d.suggestedHost || '',
            insecure: !!d.insecure
        };
    }

    window.FreeITSMSubscribe = {
        /** Register a dialogue. onReset is called after a successful rotation. */
        mount: function (id, endpoint, opts) {
            mounts[id] = { endpoint: endpoint, state: null, opts: opts || {} };
        },

        open: function (id) {
            var m = mounts[id];
            if (!m) return;
            var modal = $(id + 'Modal');
            var show = function () { if (modal) modal.classList.add('active'); };
            if (m.state) { show(); return; }
            fetch(m.endpoint, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.success) {
                        if (typeof showToast === 'function') showToast(d && d.error ? d.error : 'Error', 'error');
                        return;
                    }
                    apply(id, normalise(d));
                    show();
                })
                .catch(function () {});
        },

        close: function (id) {
            var modal = $(id + 'Modal');
            if (modal) modal.classList.remove('active');
        },

        copy: function (id) {
            var el = $(id + 'Url');
            if (!el) return;
            el.select();
            var done = function () {
                if (typeof showToast === 'function') {
                    showToast(t('common.subscribe.copied', 'Link copied'), 'success');
                }
            };
            if (navigator.clipboard) navigator.clipboard.writeText(el.value).then(done).catch(function () {});
            else { try { document.execCommand('copy'); done(); } catch (e) {} }
        },

        /**
         * Rotate the token. Confirmed first, because "Reset" does not convey that
         * every device already subscribed silently stops updating until it is
         * given the new link.
         */
        reset: function (id) {
            var m = mounts[id];
            if (!m) return;
            var go = function (ok) {
                if (!ok) return;
                fetch(m.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=reset'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || !d.success) return;
                        apply(id, normalise(d));
                        if (typeof showToast === 'function') {
                            showToast(t('common.subscribe.reset_done', 'Link reset — the old one no longer works'), 'success');
                        }
                        if (m.opts.onReset) m.opts.onReset(d);
                    })
                    .catch(function () {});
            };
            var msg = t('common.subscribe.reset_confirm',
                        'Every device already subscribed will stop updating until you give it the new link. Continue?');
            if (typeof showConfirm === 'function') {
                showConfirm({ title: t('common.subscribe.reset', 'Reset'), message: msg,
                              okLabel: t('common.subscribe.reset', 'Reset'), okClass: 'danger' }).then(go);
            } else {
                go(window.confirm(msg));
            }
        },

        /** Drop the cached URL so the next open() re-fetches (after a revoke). */
        forget: function (id) {
            if (mounts[id]) mounts[id].state = null;
        }
    };
})();
