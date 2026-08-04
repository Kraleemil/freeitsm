/**
 * CMDB data-quality audit page.
 *
 * Renders one card per check. Every card is shown even when it finds nothing —
 * a clean check is information ("references are sound"), and hiding it would
 * leave the reader unable to tell a passing check from one that never ran.
 */

const AUDIT_API = '../api/cmdb/get_audit.php';

/** Checks in the order they matter: broken data, then holes, then rot. */
const CHECK_ORDER = [
    'no_impact_edges',
    'broken_reference',
    'required_missing',
    'dependency_blank',
    'disconnected',
    'stale'
];

function esc(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function tr(key, params) { return window.t('cmdb.audit.' + key, params || {}); }

async function loadAudit() {
    const body = document.getElementById('auditBody');
    try {
        const res = await fetch(AUDIT_API);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'failed');
        render(data.audit);
    } catch (e) {
        body.innerHTML = `<div class="audit-loading">${esc(tr('error', { message: e.message }))}</div>`;
    }
}

function render(audit) {
    const byKey = {};
    (audit.checks || []).forEach(c => { byKey[c.key] = c; });
    const ordered = CHECK_ORDER.map(k => byKey[k]).filter(Boolean);

    const clean = audit.total_findings === 0;
    const summary = `
        <div class="audit-summary">
            <div class="audit-stat"><span class="n">${audit.objects_examined}</span>
                <span class="l">${esc(tr('stat_examined'))}</span></div>
            <div class="audit-stat ${clean ? 'is-clean' : 'has-findings'}">
                <span class="n">${audit.total_findings}</span>
                <span class="l">${esc(tr('stat_findings'))}</span></div>
        </div>`;

    document.getElementById('auditBody').className = '';
    document.getElementById('auditBody').innerHTML =
        summary + `<div class="audit-grid">${ordered.map(card).join('')}</div>`;
}

function card(c) {
    const isClean = c.count === 0;
    const cls = isClean ? 'is-clean' : 'sev-' + c.severity;

    // The install-level check has no object list — it is a yes/no about config.
    if (c.key === 'no_impact_edges') {
        return `
        <div class="audit-card ${cls}">
            <h3><span>${esc(tr('check_' + c.key))}</span>
                <span class="audit-count">${isClean ? esc(tr('ok')) : '!'}</span></h3>
            <p class="audit-why">${esc(tr('why_' + c.key))}</p>
            ${isClean
                ? `<div class="audit-clean-msg">${esc(tr('clean_' + c.key))}</div>`
                : `<div class="audit-fix"><a href="settings/">${esc(tr('fix_' + c.key))}</a></div>`}
        </div>`;
    }

    const items = (c.items || []).map(i => `
        <li><a href="object.php?id=${i.object_id}">${esc(i.object_name)}</a>
            <span class="meta">${esc(i.class_name)}${i.detail ? ' · ' + esc(i.detail) : ''}</span></li>`).join('');

    // Never present a capped list as the whole answer.
    const capped = c.capped
        ? `<div class="audit-capped">${esc(tr('capped', { shown: c.items.length, total: c.count }))}</div>`
        : '';

    return `
        <div class="audit-card ${cls}">
            <h3><span>${esc(tr('check_' + c.key))}</span>
                <span class="audit-count">${c.count}</span></h3>
            <p class="audit-why">${esc(tr('why_' + c.key))}</p>
            ${isClean
                ? `<div class="audit-clean-msg">${esc(tr('clean_' + c.key))}</div>`
                : `<ul class="audit-items">${items}</ul>${capped}`}
        </div>`;
}

document.addEventListener('DOMContentLoaded', loadAudit);
