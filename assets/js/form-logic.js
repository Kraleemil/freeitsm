/**
 * FormLogic — the one shared brain for the Forms module.
 *
 * The three places a form gets drawn (the builder preview, forms/fill.php and the
 * portal's self-service/catalogue.php) each keep their own markup and CSS, because
 * they genuinely look different and merging them would change how existing forms
 * render. What they must NOT each keep their own copy of is the *thinking*: which
 * types exist, which carry options, and whether a field is currently visible.
 * That all lives here.
 *
 * ⚠️ Mirrors includes/form_logic.php, which is the source of truth — the server
 * re-evaluates visibility on submit, because a browser can simply not run this.
 * Change one, change the other; tests/forms-logic runs the same cases through both.
 */
(function (global) {
    'use strict';

    // Every type the module knows. 'section' is presentational — a heading that owns
    // the fields below it until the next section, and never produces an answer.
    var TYPES = ['text', 'textarea', 'email', 'number', 'checkbox', 'checkboxes', 'dropdown', 'radio', 'datetime', 'lookup', 'section'];
    var WITH_OPTIONS = ['dropdown', 'radio', 'checkboxes'];
    var MULTI_VALUE  = ['checkboxes'];

    // What a 'datetime' field asks for, held in config.date_mode. ONE type with a mode
    // rather than three types, because field_type cannot be changed once a field exists.
    var DATE_MODES = ['date', 'time', 'datetime'];
    var DATE_MODE_DEFAULT = 'date';

    // A lookup answer is JSON: {"id":11,"label":"LT-001"}. The LABEL is what a
    // condition compares against, because 'is LT-001' is what a form builder
    // means — nobody writes a rule against a database id.
    function lookupLabel(raw) {
        if (raw === null || raw === undefined || raw === '') return '';
        try { var d = JSON.parse(raw); return (d && d.label) ? String(d.label) : ''; }
        catch (e) { return ''; }
    }
    function lookupSource(field) {
        var c = field && field.config;
        if (typeof c === 'string') { try { c = JSON.parse(c); } catch (e) { c = null; } }
        return (c && c.lookup_source) ? String(c.lookup_source) : '';
    }

    function isAnswerable(type) { return type !== 'section'; }
    function hasOptions(type)   { return WITH_OPTIONS.indexOf(type) !== -1; }
    function isMultiValue(type) { return MULTI_VALUE.indexOf(type) !== -1; }

    /** The mode of a datetime field, defaulting when unset. Mirrors FormsService::dateModeOf(). */
    function dateMode(field) {
        var cfg = field && field.config;
        if (typeof cfg === 'string') { try { cfg = JSON.parse(cfg); } catch (e) { cfg = null; } }
        var mode = cfg && cfg.date_mode;
        return DATE_MODES.indexOf(mode) !== -1 ? mode : DATE_MODE_DEFAULT;
    }

    /** The HTML input type a given mode needs. */
    function dateInputType(mode) {
        return mode === 'time' ? 'time' : (mode === 'datetime' ? 'datetime-local' : 'date');
    }

    /**
     * Render a stored value for READING (submissions table, CSV, detail drawer).
     *
     * ⚠️ Deliberately does NOT parse to a Date. These are naive local values — "needed
     * by 14 August" means the 14th to whoever typed it — and new Date() would apply the
     * reader's timezone, showing an analyst in another zone the 13th. The only change
     * made is swapping the ISO 'T' for a space so it reads as text rather than a
     * machine format. ISO order is also kept on purpose: dd/mm vs mm/dd is ambiguous
     * across the 21 locales this product ships in, and this is never ambiguous.
     */
    function formatDateValue(raw) {
        return String(raw == null ? '' : raw).replace('T', ' ');
    }

    /** Options come back as a JSON string from the API but as a live array in the builder. */
    function parseOptions(raw) {
        if (!raw) return [];
        if (Array.isArray(raw)) return raw;
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) { return []; }
    }

    /** Same as the PHP: a scalar string plus a list, so one operator handles both shapes. */
    function normaliseValue(raw) {
        var scalar = '', list = [];
        if (Array.isArray(raw)) {
            list = raw.map(String);
            scalar = list.join(', ');
        } else {
            scalar = (raw === null || raw === undefined) ? '' : String(raw).trim();
            if (scalar.charAt(0) === '[') {
                try {
                    var decoded = JSON.parse(scalar);
                    if (Array.isArray(decoded)) {
                        list = decoded.map(String);
                        scalar = list.join(', ');
                    }
                } catch (e) { /* not JSON — treat as plain text */ }
            } else if (scalar.charAt(0) === '{') {
                // A lookup answer. Compare against the LABEL, not the id —
                // mirrors formLogicNormaliseValue() in includes/form_logic.php,
                // which is the copy that actually decides.
                var lbl = lookupLabel(scalar);
                if (lbl !== '') scalar = lbl;
            }
        }
        if (!list.length && scalar !== '') list = [scalar];
        return { scalar: scalar, list: list };
    }

    function testRule(rule, valuesByFieldId) {
        var refId = parseInt(rule.field, 10) || 0;
        var op    = rule.op || 'equals';
        var want  = (rule.value === null || rule.value === undefined) ? '' : String(rule.value).trim();

        var norm   = normaliseValue(valuesByFieldId[refId]);
        var scalar = norm.scalar;
        var list   = norm.list;
        var inList = list.indexOf(want) !== -1;

        switch (op) {
            case 'is_empty':     return scalar === '' || scalar === '0';
            case 'is_not_empty': return scalar !== '' && scalar !== '0';
            case 'equals':       return inList || scalar === want;
            case 'not_equals':   return !(inList || scalar === want);
            case 'contains':
                if (inList) return true;
                return want !== '' && scalar.toLowerCase().indexOf(want.toLowerCase()) !== -1;
            case 'greater_than':
                return scalar !== '' && want !== '' && !isNaN(parseFloat(scalar)) && !isNaN(parseFloat(want))
                    && parseFloat(scalar) > parseFloat(want);
            case 'less_than':
                return scalar !== '' && want !== '' && !isNaN(parseFloat(scalar)) && !isNaN(parseFloat(want))
                    && parseFloat(scalar) < parseFloat(want);

            // Date-shaped comparison. A plain string compare is CORRECT rather than
            // lazy: stored date/time values are ISO-8601, whose lexical order IS
            // chronological order. new Date() would drag in a timezone these naive
            // values deliberately do not have.
            case 'is_after':  return scalar !== '' && want !== '' && scalar > want;
            case 'is_before': return scalar !== '' && want !== '' && scalar < want;
        }
        // Unknown operator must never silently hide a question.
        return true;
    }

    /**
     * fields: array of {id, field_type, config} IN sort_order.
     * values: { fieldId: answer }.
     * Returns { fieldId: true|false }.
     */
    function visibility(fields, values) {
        var out = {}, sectionOk = true;
        (fields || []).forEach(function (f) {
            var cfg = f.config;
            if (typeof cfg === 'string') {
                try { cfg = JSON.parse(cfg); } catch (e) { cfg = null; }
            }
            var own = true;
            var vif = cfg && cfg.visible_if;
            if (vif && Array.isArray(vif.rules) && vif.rules.length) {
                var results = vif.rules.map(function (r) { return testRule(r, values); });
                own = (vif.match === 'any')
                    ? results.indexOf(true) !== -1
                    : results.indexOf(false) === -1;
            }
            if (f.field_type === 'section') {
                sectionOk = own;
                out[f.id] = own;
                return;
            }
            out[f.id] = own && sectionOk;
        });
        return out;
    }

    /** True when a rule on `field` may point at `candidate` — earlier fields only, which is what stops cycles. */
    function canReference(fields, fieldIndex, candidateIndex) {
        return candidateIndex < fieldIndex && isAnswerable(fields[candidateIndex].field_type);
    }

    /**
     * Wire up every lookup box inside `root`.
     *
     * ⚠️ THE ANSWER IS ONLY EVER WRITTEN BY CHOOSING FROM THE LIST. Typing
     * "my laptop" and walking away leaves the hidden input empty, so the field
     * reads as unanswered rather than storing a name that matches no record.
     * That is the point of a lookup — the server refuses anything else anyway.
     *
     * Markup this expects (each surface supplies its own CSS):
     *   .lookup-wrap > input.lookup-search[data-lookup-field]
     *                > input[type=hidden]
     *                > .lookup-results
     *
     * @param root      element to search within
     * @param searchUrl endpoint taking ?field_id=&q=
     */
    function attachLookups(root, searchUrl) {
        var boxes = (root || document).querySelectorAll('input.lookup-search[data-lookup-field]');
        Array.prototype.forEach.call(boxes, function (box) {
            if (box.getAttribute('data-lookup-ready')) return;
            box.setAttribute('data-lookup-ready', '1');

            var wrap    = box.closest('.lookup-wrap');
            var hidden  = wrap.querySelector('input[type=hidden]');
            var results = wrap.querySelector('.lookup-results');
            var fieldId = box.getAttribute('data-lookup-field');
            var timer   = null;

            function close() { results.hidden = true; results.innerHTML = ''; }

            function choose(id, label) {
                hidden.value = JSON.stringify({ id: id, label: label });
                box.value = label;
                close();
                // Conditional visibility keys off answers, and this one just
                // changed — tell whatever is listening, the same way a native
                // input would.
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function search() {
                var q = box.value.trim();
                // Anything typed invalidates a previous choice: the box no
                // longer shows what was chosen, so the answer must not persist.
                if (hidden.value) {
                    hidden.value = '';
                    hidden.dispatchEvent(new Event('change', { bubbles: true }));
                }
                fetch(searchUrl + '?field_id=' + encodeURIComponent(fieldId) + '&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (!j.success || !j.results || !j.results.length) {
                            results.innerHTML = '<div class="lookup-empty">'
                                + ((global.t ? global.t('forms.fill.lookup_none') : 'No matches')) + '</div>';
                            results.hidden = false;
                            return;
                        }
                        results.innerHTML = j.results.map(function (r) {
                            var safe = String(r.label).replace(/[&<>"']/g, function (ch) {
                                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
                            });
                            return '<button type="button" class="lookup-option" data-id="' + r.id
                                 + '" data-label="' + safe + '">' + safe + '</button>';
                        }).join('');
                        results.hidden = false;
                    })
                    .catch(function () { close(); });
            }

            box.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(search, 220);   // one request per pause, not per keystroke
            });
            box.addEventListener('focus', function () { if (!results.innerHTML) search(); });

            results.addEventListener('click', function (e) {
                var opt = e.target.closest('.lookup-option');
                if (opt) choose(parseInt(opt.getAttribute('data-id'), 10), opt.getAttribute('data-label'));
            });

            document.addEventListener('click', function (e) {
                if (!wrap.contains(e.target)) close();
            });
        });
    }

    global.FormLogic = {
        TYPES: TYPES,
        DATE_MODES: DATE_MODES,
        DATE_MODE_DEFAULT: DATE_MODE_DEFAULT,
        isAnswerable: isAnswerable,
        hasOptions: hasOptions,
        isMultiValue: isMultiValue,
        dateMode: dateMode,
        dateInputType: dateInputType,
        formatDateValue: formatDateValue,
        parseOptions: parseOptions,
        normaliseValue: normaliseValue,
        testRule: testRule,
        visibility: visibility,
        canReference: canReference,
        lookupLabel: lookupLabel,
        lookupSource: lookupSource,
        attachLookups: attachLookups
    };
})(window);
