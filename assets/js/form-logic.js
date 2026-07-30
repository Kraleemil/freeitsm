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
    var TYPES = ['text', 'textarea', 'email', 'number', 'checkbox', 'checkboxes', 'dropdown', 'radio', 'section'];
    var WITH_OPTIONS = ['dropdown', 'radio', 'checkboxes'];
    var MULTI_VALUE  = ['checkboxes'];

    function isAnswerable(type) { return type !== 'section'; }
    function hasOptions(type)   { return WITH_OPTIONS.indexOf(type) !== -1; }
    function isMultiValue(type) { return MULTI_VALUE.indexOf(type) !== -1; }

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

    global.FormLogic = {
        TYPES: TYPES,
        isAnswerable: isAnswerable,
        hasOptions: hasOptions,
        isMultiValue: isMultiValue,
        parseOptions: parseOptions,
        normaliseValue: normaliseValue,
        testRule: testRule,
        visibility: visibility,
        canReference: canReference
    };
})(window);
