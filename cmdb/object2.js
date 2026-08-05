/**
 * CMDB Object Detail — PROTOTYPE (v2 layout)
 *
 * Same four endpoints as object.js. Everything different is presentation:
 * what gets promoted, what gets merged, and what stops taking up a row when
 * it has nothing to say.
 *
 * Read the header comment in object2.php for what is deliberately not
 * reimplemented here.
 */

const API = '../api/cmdb/';
const OBJECT_ID = window.OBJECT_ID;

let obj = null;
let impact = null;            // {descendants, referenced_by_property, referenced_by_relationship}
let blastRadius = null;       // {nodes:[{id,name,class_name,depth,via_kind,via_label,via_name}], truncated, no_impact_edges_configured}
let activity = null;          // {open, closed, total_closed}
let classesById = {};         // class_id -> {name, icon_key}
let classesByName = {};       // class_name -> icon_key  (related objects only carry the name)
let allClasses = [];          // for the property-definition target-class picker
let relationshipTypes = [];   // the verb library, for the add-relationship modal
let showBlanks = false;
let summaryGenerating = false;
let acTimer = null;
let acHighlightedIdx = -1;

/* Words that mean "this matters more than that". A dropdown carrying one of
   these drives the hero rail colour, so a Critical CI does not render
   identically to a decommissioned wall switch. An explicit option colour set
   in class settings always wins over this list. */
/* Property names that mean "this is the field that says how much it matters".
   Checked before the value vocabulary below, so Criticality beats Environment. */
const SIGNAL_HINTS = ['critical', 'severity', 'impact', 'priority', 'tier'];

const SEVERITY = {
    critical: '#b91c1c',
    urgent:   '#b91c1c',
    high:     '#c2410c',
    major:    '#c2410c',
    medium:   '#a16207',
    moderate: '#a16207',
    low:      '#15803d',
    minor:    '#15803d'
};

const STALE_DAYS = 183; // the audit's six months, in days

/* ---------------- helpers ---------------- */

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/* showToast and showConfirm are the shared components, pulled in by
   includes/header.php -> waffle-menu.php. Not redefined here — a second toast
   implementation one module away from the real one is exactly the divergence
   worth avoiding. */
function toast(msg, isError) {
    showToast(msg, isError ? 'error' : 'success');
}
function confirmAction(opts) {
    return showConfirm(opts);
}

async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body || {})
    });
    return res.json();
}

/* created/updated are UTC and get converted; a `date` PROPERTY is naive and
   must never go through the timezone path or it shifts by a day. */
function fmtDateTime(s) {
    if (!s) return '';
    try { return parseUTCDate(s).toLocaleString(undefined, tzOpts()); } catch (e) { return s; }
}

/* "2026-04-15 00:00:00" -> "2026-04-15". Never parsed as a Date: a date
   property is naive, and round-tripping it through a timezone shifts it. */
function dateOnly(v) {
    const s = String(v ?? '');
    return /^\d{4}-\d{2}-\d{2}/.test(s) ? s.slice(0, 10) : s;
}

function daysSince(sqlUtc) {
    if (!sqlUtc) return null;
    try {
        const then = parseUTCDate(sqlUtc).getTime();
        return Math.max(0, Math.floor((Date.now() - then) / 86400000));
    } catch (e) { return null; }
}

function icon(key, size) {
    return window.nmRenderIcon ? window.nmRenderIcon(key || 'box', size || 20) : '';
}
function iconForClassName(name, size) {
    return icon(classesByName[name] || 'box', size);
}
function iconForClassId(id, size) {
    const c = classesById[id];
    return icon(c ? c.icon_key : 'box', size);
}

/* ---------------- load ---------------- */

document.addEventListener('DOMContentLoaded', () => {
    if (!OBJECT_ID) {
        document.querySelector('#o2Page .o2-wrap').innerHTML =
            '<div style="padding:60px;text-align:center;color:var(--danger-text,#b91c1c);">No object id in the URL.</div>';
        return;
    }
    Promise.all([loadObject(), loadImpact(), loadActivity(), loadClasses(), loadRelationshipTypes()]).then(() => {
        if (obj) render();
    });
    initPropDefModalDrag();
});

async function loadRelationshipTypes() {
    try {
        const data = await (await fetch(API + 'get_relationship_types.php')).json();
        if (data.success) relationshipTypes = (data.relationship_types || []).filter(r => r.is_active);
    } catch (e) { /* the add-relationship modal will say none are defined */ }
}

/* Everything that reloads after a write goes through here, so no caller can
   refresh the object but forget the impact panel and leave the blast radius
   and the connection tally disagreeing. */
async function reloadAndRender() {
    await Promise.all([loadObject(), loadImpact(), loadActivity()]);
    render();
}

async function loadObject() {
    try {
        const res = await fetch(API + 'get_object.php?id=' + OBJECT_ID);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Could not load this object.');
        obj = data.object;
    } catch (err) {
        document.querySelector('#o2Page .o2-wrap').innerHTML =
            '<div style="padding:60px;text-align:center;color:var(--danger-text,#b91c1c);">' + esc(err.message) + '</div>';
    }
}
async function loadImpact() {
    try {
        const data = await (await fetch(API + 'get_object_impact.php?id=' + OBJECT_ID)).json();
        if (data.success) { impact = data.impact; blastRadius = data.blast_radius || null; }
    } catch (e) { /* panel degrades to its empty state */ }
}
async function loadActivity() {
    try {
        const data = await (await fetch(API + 'get_object_tickets.php?id=' + OBJECT_ID)).json();
        if (data.success) activity = data;
    } catch (e) { /* panel degrades to its empty state */ }
}
async function loadClasses() {
    try {
        const data = await (await fetch(API + 'get_classes.php')).json();
        if (!data.success) return;
        allClasses = (data.classes || []).filter(c => c.is_active);
        (data.classes || []).forEach(c => {
            classesById[c.id] = { name: c.name, icon_key: c.icon_key };
            classesByName[c.name] = c.icon_key;
        });
    } catch (e) { /* falls back to the generic box glyph */ }
}

/* ---------------- derived facts ---------------- */

/* The object's own signal colour, read off its data rather than configured
   anywhere: an explicit option colour first, a severity word second. */
function detectSignal() {
    if (!obj) return null;
    // Rank matters: a Server usually has several coloured dropdowns, and
    // "Environment: Production" would otherwise win over "Criticality:
    // Critical" purely by being defined first.
    const cands = (obj.properties || [])
        .filter(p => p.property_type === 'dropdown' && p.value)
        .map(p => {
            const opt = (p.options || []).find(o => o.value === p.value);
            const sev = SEVERITY[String(p.value).toLowerCase()];
            const colour = (opt && opt.colour) || sev || null;
            if (!colour) return null;
            const hay = ((p.property_key || '') + ' ' + (p.label || '')).toLowerCase();
            const named = SIGNAL_HINTS.some(h => hay.indexOf(h) !== -1);
            return { colour: colour, label: p.value, rank: named ? 0 : (sev ? 1 : 2) };
        })
        .filter(Boolean)
        .sort((a, b) => a.rank - b.rank);
    return cands.length ? cands[0] : null;
}

/* One place that counts each kind of connection, so the tally row, the
   headline number and the columns can never disagree with each other.
   `descendants` is the TRANSITIVE list from the impact endpoint, not just
   direct children — a Server → Instance → Database → Job chain must not
   report one descendant. */
function connectionTally() {
    const r = (obj && obj.relationships) || { outgoing: [], incoming: [] };
    const desc = (impact && impact.descendants) ? impact.descendants : [];
    const refsIn = (impact && impact.referenced_by_property) ? impact.referenced_by_property : [];
    const refsOut = (obj.properties || []).filter(p => p.property_type === 'object_ref' && p.value_object);
    return {
        descendants: desc,
        incoming: r.incoming || [],
        outgoing: r.outgoing || [],
        refsIn: refsIn,
        refsOut: refsOut,
        hasParent: !!obj.parent_id,
        total: desc.length + (r.incoming || []).length + (r.outgoing || []).length +
               refsIn.length + refsOut.length + (obj.parent_id ? 1 : 0)
    };
}

/* ---------------- render ---------------- */

function render() {
    const signal = detectSignal();
    const wrap = document.querySelector('#o2Page .o2-wrap');
    // The sections live in their own container so the stagger's :nth-child
    // counts sections, not "sections plus whatever else is in the wrapper".
    wrap.innerHTML =
        breadcrumbHtml() +
        '<div class="o2-secs">' +
            '<div class="o2-sec">' + heroHtml(signal) + '</div>' +
            '<div class="o2-sec">' + statsHtml() + '</div>' +
            '<div class="o2-sec">' + aiHtml() + '</div>' +
            '<div class="o2-sec">' + blastHtml() + '</div>' +
            '<div class="o2-sec">' + connectionsHtml() + '</div>' +
            '<div class="o2-sec">' + propsHtml() + '</div>' +
            '<div class="o2-sec">' + activityHtml() + '</div>' +
            '<div class="o2-sec">' + dangerHtml() + '</div>' +
        '</div>';

    if (signal) wrap.style.setProperty('--o2-signal', signal.colour);
    else wrap.style.removeProperty('--o2-signal');

    wireName();
    wireProps();
    animateStats();
    animateMeter();
}

function breadcrumbHtml() {
    return '<div class="o2-breadcrumb">' +
        '<a href="./">Browse</a><span class="sep">›</span>' +
        '<a href="./#class-' + obj.class_id + '">' + esc(obj.class_name) + '</a>' +
        (obj.parent_id ? '<span class="sep">›</span><a href="object2.php?id=' + obj.parent_id + '">' + esc(obj.parent_name) + '</a>' : '') +
        '<span class="sep">›</span><span class="here">' + esc(obj.name) + '</span>' +
        '</div>';
}

function heroHtml(signal) {
    const stale = daysSince(obj.updated_datetime);
    const chips = [];
    chips.push('<span class="o2-chip class">' + icon(classesById[obj.class_id] ? classesById[obj.class_id].icon_key : 'box', 13) + esc(obj.class_name) + '</span>');
    if (signal) chips.push('<span class="o2-chip signal">' + esc(signal.label) + '</span>');

    // The state is always stated, and clicking it flips it. When it is the
    // normal case it is quiet; when it is planned it is loud.
    chips.push('<button class="o2-chip act' + (obj.is_planned ? ' planned' : '') + '" onclick="togglePlanned()" ' +
        'title="' + (obj.is_planned ? 'Mark this as real and in service' : 'Mark this as planned — not yet in service') + '">' +
        (obj.is_planned ? 'Planned — not yet in service' : 'In service') + '</button>');

    chips.push(obj.parent_id
        ? '<span class="o2-chip">Part of <a href="object2.php?id=' + obj.parent_id + '">' + esc(obj.parent_name) + '</a>' +
          '<button class="o2-chip-x" onclick="openParentModal()" title="Change or clear the parent">edit</button></span>'
        : '<button class="o2-chip act" onclick="openParentModal()" title="Put this inside another object">+ Set parent</button>');
    if (stale !== null && stale > STALE_DAYS) {
        chips.push('<span class="o2-chip stale">Not touched in ' + Math.floor(stale / 30) + ' months</span>');
    }

    const actions =
        '<button class="o2-btn primary" id="o2GenBtn" onclick="generateSummary(this)">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/></svg>' +
            (obj.ai_summary ? 'Regenerate' : 'Summarise') +
        '</button>' +
        (window.CAN_MAKE_DIAGRAM
            ? '<button class="o2-btn" onclick="createImpactDiagram(this)">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="18" cy="18" r="3"/><path d="M9 6h4a2 2 0 0 1 2 2v7"/></svg>' +
                'Diagram</button>'
            : '') +
        '<a class="o2-btn" href="object.php?id=' + obj.id + '" title="The current production layout">Old view</a>';

    return '<div class="o2-hero' + (obj.is_planned ? ' is-planned' : '') + '">' +
        '<div class="o2-hero-icon">' + iconForClassId(obj.class_id, 40) + '</div>' +
        '<div class="o2-hero-main">' +
            '<input class="o2-name" id="o2Name" value="' + esc(obj.name) + '" aria-label="Object name">' +
            '<div class="o2-chips">' + chips.join('') + '</div>' +
        '</div>' +
        '<div class="o2-hero-actions">' + actions + '</div>' +
    '</div>';
}

function statsHtml() {
    const blast = (blastRadius && blastRadius.nodes) ? blastRadius.nodes.length : 0;
    const open = (activity && activity.open) ? activity.open.length : 0;
    const conns = connectionTally().total;
    const days = daysSince(obj.updated_datetime);

    function tile(val, unit, label, cls, target) {
        const jump = target
            ? ' onclick="document.getElementById(\'' + target + '\').scrollIntoView({behavior:\'smooth\',block:\'start\'})"'
            : '';
        return '<button class="o2-stat ' + cls + (target ? '' : ' is-quiet') + '"' + jump + '>' +
            '<div class="o2-stat-val"><span data-count="' + val + '">0</span>' + (unit ? '<span class="unit">' + esc(unit) + '</span>' : '') + '</div>' +
            '<div class="o2-stat-lbl">' + esc(label) + '</div>' +
        '</button>';
    }

    return '<div class="o2-stats">' +
        tile(blast, '', blast === 1 ? 'thing breaks if this fails' : 'things break if this fails', blast > 0 ? 'hot' : 'zero', 'o2Blast') +
        tile(open, '', open === 1 ? 'open ticket' : 'open tickets', open > 0 ? 'warm' : 'zero', 'o2Activity') +
        tile(conns, '', conns === 1 ? 'connection' : 'connections', conns > 0 ? '' : 'zero', 'o2Conn') +
        tile(days === null ? 0 : days, 'd', 'since anyone touched it', (days !== null && days > STALE_DAYS) ? 'warm' : '', null) +
    '</div>';
}

function aiHtml() {
    if (obj.ai_summary) {
        return '<div class="o2-ai">' +
            '<div class="o2-ai-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/></svg></div>' +
            '<div><div class="o2-ai-txt" id="o2AiTxt">' + esc(obj.ai_summary) + '</div>' +
            (obj.ai_summary_generated_at ? '<div class="o2-ai-when">Written ' + esc(fmtDateTime(obj.ai_summary_generated_at)) + '</div>' : '') +
            '</div></div>';
    }
    return '<div class="o2-ai">' +
        '<div class="o2-ai-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/></svg></div>' +
        '<div class="o2-ai-txt muted" id="o2AiTxt">Nobody has asked the AI to describe this one yet — <b>Summarise</b> writes two or three sentences from its class, its place in the estate, its owner and what depends on it.</div>' +
    '</div>';
}

/* The blast radius as a left-to-right chain: root, then one column per hop.
   The grouped list said the same thing; the chain shows the direction a
   failure travels, which is the point of the panel. */
function blastHtml() {
    const head = '<div class="o2-card-head"><span class="o2-card-title">If this failed</span>' +
        (blastRadius && blastRadius.truncated ? '<span class="o2-card-sub">list is capped — the estate is larger</span>' : '') + '</div>';

    if (!blastRadius) return '<div class="o2-card" id="o2Blast">' + head + '<div class="o2-empty">Working it out…</div></div>';

    const nodes = blastRadius.nodes || [];
    if (!nodes.length) {
        const msg = blastRadius.no_impact_edges_configured
            ? 'Nothing in this install is set up to carry a failure yet, so this cannot be answered. Set which relationship types spread impact in <a href="settings/">Settings → Relationship types</a>.'
            : 'Nothing depends on this. If it failed, nothing else in the CMDB goes with it.';
        return '<div class="o2-card" id="o2Blast">' + head + '<div class="o2-empty">' + msg + '</div></div>';
    }

    const maxDepth = Math.max(...nodes.map(n => n.depth));
    let chain = '<div class="o2-hop"><div class="o2-hop-lbl">Starts here</div><div class="o2-hop-items">' +
        '<div class="o2-node root">' +
            '<span class="o2-node-ico">' + iconForClassId(obj.class_id, 20) + '</span>' +
            '<span class="o2-node-txt"><span class="o2-node-name">' + esc(obj.name) + '</span>' +
            '<span class="o2-node-sub">' + esc(obj.class_name) + '</span></span>' +
        '</div></div></div>';

    for (let d = 1; d <= maxDepth; d++) {
        const at = nodes.filter(n => n.depth === d);
        if (!at.length) continue;
        chain += '<div class="o2-arrow"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="12" x2="19" y2="12"/><polyline points="13 6 19 12 13 18"/></svg></div>';
        chain += '<div class="o2-hop"><div class="o2-hop-lbl">' + (d === 1 ? '1 step away' : d + ' steps away') + '</div><div class="o2-hop-items">' +
            at.map(n =>
                '<div>' +
                '<a class="o2-node" href="object2.php?id=' + n.id + '">' +
                    '<span class="o2-node-ico">' + iconForClassName(n.class_name, 20) + '</span>' +
                    '<span class="o2-node-txt"><span class="o2-node-name">' + esc(n.name) + '</span>' +
                    '<span class="o2-node-sub">' + esc(n.class_name) + '</span></span>' +
                '</a>' +
                '<div class="o2-via">' + esc(viaText(n)) + '</div>' +
                '</div>'
            ).join('') +
        '</div></div>';
    }

    const lede = nodes.length === 1
        ? '1 other thing would be affected.'
        : nodes.length + ' other things would be affected, up to ' + maxDepth + ' step' + (maxDepth === 1 ? '' : 's') + ' away.';

    return '<div class="o2-card" id="o2Blast">' + head +
        '<div class="o2-lede">' + esc(lede) + '</div>' +
        '<div class="o2-chain">' + chain + '</div>' +
    '</div>';
}

/* Every row says how it was reached, so a surprising entry can be traced
   rather than doubted. */
function viaText(n) {
    // The engine emits 'child' / 'relationship' / 'property'. Containment
    // carries no verb (via_label is NULL), so it needs its own sentence or it
    // renders as the literal "null <name>".
    if (n.via_kind === 'child') return 'contained by ' + n.via_name;
    if (n.via_kind === 'property') return n.via_label + ' points at ' + n.via_name;
    return n.via_label + ' ' + n.via_name;
}

/* ONE panel where object.php had four. Impact, Map, Hierarchy and
   Relationships are four framings of "what is this attached to", and on a
   sparse object they produced four separate empty states. */
function connectionsHtml() {
    const tally = connectionTally();

    // Only relationship rows carry an ×. A property reference is unlinked by
    // clearing the field it lives in, and a descendant by re-parenting it —
    // offering an × on those would imply a delete this panel cannot do.
    const left = []
        .concat(tally.incoming.map(x => nodeRow(x.other_id, x.other_name, x.other_class_name, x.inverse_verb + ' this', x.id)))
        .concat(tally.refsIn.map(x => nodeRow(x.id, x.name, x.class_name, (x.property_label || 'a property') + ' points here')));

    const right = []
        .concat(tally.outgoing.map(x => nodeRow(x.other_id, x.other_name, x.other_class_name, 'this ' + x.verb, x.id)))
        .concat(tally.refsOut.map(p => nodeRow(p.value_object.id, p.value_object.name, p.value_object.class_name || p.target_class_name, p.label)));

    const centre =
        (obj.parent_id
            ? '<div class="o2-updown">' + nodeRow(obj.parent_id, obj.parent_name, obj.parent_class_name, 'this is part of') + upArrow() + '</div>'
            : '') +
        '<div class="o2-centre">' + iconForClassId(obj.class_id, 30) +
            '<div class="o2-centre-name">' + esc(obj.name) + '</div>' +
            '<div class="o2-centre-cls">' + esc(obj.class_name) + '</div>' +
        '</div>' +
        (tally.descendants.length
            ? '<div class="o2-updown">' + downArrow() +
              tally.descendants.map(d => nodeRow(
                  d.id, d.name, d.class_name,
                  d.depth > 1 ? 'inside this, ' + d.depth + ' levels down' : 'contained by this'
              )).join('') + '</div>'
            : '');

    /* ⚠️ Always rendered, every figure, including the zeroes. "0 descendants"
       and "we never looked" are different facts and must not both render as
       silence — the same rule the data-quality audit is built on. Collapsing
       the four EMPTY panels the old page showed was the point; collapsing the
       COUNTS with them was a mistake. */
    const counts = [
        ['Descendants', tally.descendants.length],
        ['Points at this', left.length],
        ['This points at', right.length],
        ['Parent', tally.hasParent ? 1 : 0]
    ].map(([lbl, n]) =>
        '<span class="o2-tally-item' + (n ? '' : ' zero') + '">' +
            '<b>' + n + '</b> ' + esc(lbl.toLowerCase()) +
        '</span>'
    ).join('');

    const head = '<div class="o2-card-head"><span class="o2-card-title">Connections</span>' +
        '<span class="o2-card-sub">' + tally.total + ' in total</span>' +
        '<button class="o2-btn small" onclick="openRelModal()">+ Add relationship</button></div>';

    const tallyRow = '<div class="o2-tally">' + counts + '</div>';

    if (!tally.total) {
        return '<div class="o2-card" id="o2Conn">' + head + tallyRow +
            '<div class="o2-empty">Nothing is attached to this — no parent, no descendants, no relationships and nothing points at it. ' +
            'It can never appear in anyone’s blast radius, and nobody will reach it by navigating.</div></div>';
    }

    return '<div class="o2-card" id="o2Conn">' + head + tallyRow +
        '<div class="o2-conn">' +
            '<div class="o2-conn-col"><div class="o2-conn-lbl">Points at this</div><div class="o2-conn-list">' +
                (left.length ? left.join('') : '<div class="o2-empty">Nothing points at this</div>') +
            '</div></div>' +
            '<div class="o2-conn-col mid">' + centre +
                (tally.descendants.length ? '' : '<div class="o2-empty" style="text-align:center;">No descendants — nothing cascades</div>') +
            '</div>' +
            '<div class="o2-conn-col right"><div class="o2-conn-lbl">This points at</div><div class="o2-conn-list">' +
                (right.length ? right.join('') : '<div class="o2-empty">This points at nothing</div>') +
            '</div></div>' +
        '</div>' +
    '</div>';
}

function nodeRow(id, name, className, verb, relId) {
    return '<div class="o2-noderow">' +
        (verb ? '<div class="o2-rel-verb">' + esc(verb) + '</div>' : '') +
        '<div class="o2-node-wrap">' +
            '<a class="o2-node" href="object2.php?id=' + id + '">' +
                '<span class="o2-node-ico">' + iconForClassName(className, 18) + '</span>' +
                '<span class="o2-node-txt"><span class="o2-node-name">' + esc(name) + '</span>' +
                '<span class="o2-node-sub">' + esc(className || '') + '</span></span>' +
            '</a>' +
            (relId ? '<button class="o2-unlink" onclick="deleteRelationship(' + relId + ')" title="Remove this relationship">×</button>' : '') +
        '</div></div>';
}
function upArrow() {
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-faint,#d1d5db)"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="6 11 12 5 18 11"/></svg>';
}
function downArrow() {
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-faint,#d1d5db)"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="6 13 12 19 18 13"/></svg>';
}

/* Filled properties first as cards; blanks collapse to one line. A row per
   empty field is how the old page ended up mostly saying "(not set)". */
function propsHtml() {
    const props = obj.properties || [];
    const filled = props.filter(p => p.value !== null && p.value !== '' && p.value !== undefined);
    const blank = props.filter(p => !(p.value !== null && p.value !== '' && p.value !== undefined));

    const head = '<div class="o2-card-head"><span class="o2-card-title">Details</span>' +
        '<span class="o2-card-sub">' + filled.length + ' of ' + props.length + ' filled in</span>' +
        '<div class="o2-meter"><i id="o2Meter" style="transform:scaleX(0)"></i></div></div>';

    if (!props.length) {
        return '<div class="o2-card">' + head + '<div class="o2-empty">This class has no properties defined yet.</div></div>';
    }

    const cards = (showBlanks ? filled.concat(blank) : filled).map(propCard).join('');

    let blanksLine = '';
    if (blank.length) {
        blanksLine = '<div class="o2-blanks">' +
            (showBlanks
                ? '<button onclick="toggleBlanks()">Hide the ' + blank.length + ' empty field' + (blank.length === 1 ? '' : 's') + '</button>'
                : blank.length + ' field' + (blank.length === 1 ? ' is' : 's are') + ' empty — ' +
                  '<button onclick="toggleBlanks()">show ' + (blank.length === 1 ? 'it' : 'them') + '</button>') +
        '</div>';
    }

    return '<div class="o2-card">' + head +
        (cards ? '<div class="o2-props">' + cards + '</div>' : '<div class="o2-empty">Nothing has been filled in yet.</div>') +
        blanksLine +
        // The in-service state is stated positively, always. The hero only
        // chips PLANNED because that is the exception; saying nothing at all
        // for the normal case leaves the reader to assume it.
        '<div style="margin-top:14px;"><span class="o2-stat-lbl">' +
        (obj.is_planned ? 'Planned — not yet in service' : 'Real, in service') +
        ' · added ' + esc(fmtDateTime(obj.created_datetime)) +
        ' · last change ' + esc(fmtDateTime(obj.updated_datetime)) + '</span></div>' +
    '</div>';
}

function propCard(p) {
    const empty = !(p.value !== null && p.value !== '' && p.value !== undefined);
    // The cog edits the property DEFINITION (label, type, options, required),
    // not this object's value — same modal as the current page. It is on hover
    // rather than permanent, because it is schema work and most visits are not.
    return '<div class="o2-prop">' +
        '<div class="o2-prop-lbl">' +
            '<span>' + esc(p.label) + (p.is_required ? '<span class="req" title="Required">*</span>' : '') + '</span>' +
            '<button class="o2-cog" onclick="openPropDefModal(' + p.property_id + ')" title="Edit this field’s definition">' +
                '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>' +
            '</button>' +
        '</div>' +
        '<div class="o2-prop-val" id="o2p_' + p.property_id + '" data-pid="' + p.property_id + '">' +
            (empty ? '<span style="color:var(--text-faint,#d1d5db);font-weight:400;">Not set</span>' : propValueHtml(p)) +
        '</div>' +
    '</div>';
}

function propValueHtml(p) {
    if (p.property_type === 'object_ref') {
        if (!p.value_object) return esc(p.value);
        return '<a class="o2-ref" href="object2.php?id=' + p.value_object.id + '">' +
            iconForClassName(p.value_object.class_name || p.target_class_name, 15) +
            esc(p.value_object.name) + '</a>';
    }
    if (p.property_type === 'boolean') return esc(String(p.value) === '1' || p.value === true ? 'Yes' : 'No');
    if (p.property_type === 'dropdown') {
        const opt = (p.options || []).find(o => o.value === p.value);
        const sev = SEVERITY[String(p.value).toLowerCase()];
        const col = (opt && opt.colour) || sev;
        const style = col ? ' style="background:' + esc(col) + ';color:#fff;"' : '';
        return '<span class="o2-pill"' + style + '>' + esc(p.value) + '</span>';
    }
    if (p.property_type === 'number') return esc(String(p.value));
    // A date is naive — never timezone-converted — but it can come back with a
    // 00:00:00 tail, which reads as spurious precision on a date-only field.
    if (p.property_type === 'date') return esc(dateOnly(p.value));
    return esc(p.value);
}

function toggleBlanks() {
    showBlanks = !showBlanks;
    render();
}

function activityHtml() {
    const head = '<div class="o2-card-head"><span class="o2-card-title">Activity</span></div>';
    if (!activity) return '<div class="o2-card" id="o2Activity">' + head + '<div class="o2-empty">Working it out…</div></div>';

    const open = activity.open || [];
    const closed = activity.closed || [];
    if (!open.length && !closed.length) {
        return '<div class="o2-card" id="o2Activity">' + head +
            '<div class="o2-empty">No ticket has ever mentioned this. Link one from a ticket’s reading pane → Affected CMDB Objects.</div></div>';
    }

    const row = (t, isOpen) =>
        '<a class="o2-tik" href="../tickets/?ticket_id=' + t.id + '">' +
            '<span class="o2-tik-ref">' + esc(t.reference || ('#' + t.id)) + '</span>' +
            '<span class="o2-tik-sub">' + esc(t.subject || '(no subject)') + '</span>' +
            '<span class="o2-tik-meta">' + esc(t.status_name || (isOpen ? 'Open' : 'Closed')) +
            (t.priority_name ? ' · ' + esc(t.priority_name) : '') + '</span>' +
        '</a>';

    return '<div class="o2-card" id="o2Activity">' + head +
        (open.length ? '<div class="o2-lede">' + open.length + ' open ticket' + (open.length === 1 ? '' : 's') + '</div>' +
            '<div class="o2-tix">' + open.map(t => row(t, true)).join('') + '</div>' : '') +
        (closed.length ? '<div class="o2-conn-lbl" style="margin-top:14px;">Recently closed' +
            (activity.total_closed > closed.length ? ' (' + closed.length + ' of ' + activity.total_closed + ')' : '') + '</div>' +
            '<div class="o2-tix">' + closed.map(t => row(t, false)).join('') + '</div>' : '') +
    '</div>';
}

function dangerHtml() {
    const kids = (obj.children || []).length;
    return '<div class="o2-danger">' +
        '<div class="o2-danger-txt">Deleting this removes it permanently' +
            (kids ? ', <b>along with everything inside it</b>' : '') + '.</div>' +
        '<button class="o2-btn danger" onclick="deleteObject()">Delete</button>' +
    '</div>';
}

/* ---------------- animation ---------------- */

/* A detail page is opened occasionally, not hundreds of times a day, so the
   numbers can afford to count up. Kept to 460ms and skipped entirely under
   prefers-reduced-motion. */
function animateStats() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('[data-count]').forEach(el => { el.textContent = el.dataset.count; });
        return;
    }
    document.querySelectorAll('[data-count]').forEach(el => {
        const target = parseInt(el.dataset.count, 10) || 0;
        if (target === 0) { el.textContent = '0'; return; }
        const dur = 460, start = performance.now();
        function step(now) {
            const t = Math.min(1, (now - start) / dur);
            const eased = 1 - Math.pow(1 - t, 3); // ease-out cubic
            el.textContent = Math.round(target * eased);
            if (t < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });
}

function animateMeter() {
    const el = document.getElementById('o2Meter');
    if (!el) return;
    const props = obj.properties || [];
    const filled = props.filter(p => p.value !== null && p.value !== '' && p.value !== undefined).length;
    const pct = props.length ? filled / props.length : 1;
    requestAnimationFrame(() => { el.style.transform = 'scaleX(' + pct + ')'; });
}

/* ---------------- editing ---------------- */

function wireName() {
    const el = document.getElementById('o2Name');
    if (!el) return;
    let original = obj.name;
    el.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
        if (e.key === 'Escape') { el.value = original; el.blur(); }
    });
    el.addEventListener('blur', async () => {
        const v = el.value.trim();
        if (!v || v === original) { el.value = original; return; }
        await savePartial({ name: v });
    });
}

function wireProps() {
    document.querySelectorAll('.o2-prop-val').forEach(cell => {
        cell.addEventListener('click', e => {
            if (e.target.closest('a')) return;          // following a reference, not editing
            if (cell.querySelector('input, select')) return;
            beginEdit(cell);
        });
    });
}

function beginEdit(cell) {
    const pid = parseInt(cell.dataset.pid, 10);
    const p = (obj.properties || []).find(x => x.property_id === pid);
    if (!p) return;
    if (p.property_type === 'object_ref') { beginEditObjectRef(cell, p); return; }

    let editor;
    if (p.property_type === 'dropdown') {
        editor = document.createElement('select');
        editor.innerHTML = '<option value=""></option>' +
            (p.options || []).map(o => '<option value="' + esc(o.value) + '"' + (o.value === p.value ? ' selected' : '') + '>' + esc(o.value) + '</option>').join('');
    } else if (p.property_type === 'boolean') {
        editor = document.createElement('select');
        const isYes = String(p.value) === '1' || p.value === true;
        editor.innerHTML = '<option value=""></option>' +
            '<option value="1"' + (isYes ? ' selected' : '') + '>Yes</option>' +
            '<option value="0"' + (p.value !== null && !isYes ? ' selected' : '') + '>No</option>';
    } else {
        editor = document.createElement('input');
        editor.type = p.property_type === 'number' ? 'number' : (p.property_type === 'date' ? 'date' : 'text');
        // <input type="date"> silently refuses a value with a time on it.
        editor.value = p.property_type === 'date' ? dateOnly(p.value) : (p.value ?? '');
    }

    const before = cell.innerHTML;
    cell.innerHTML = '';
    cell.appendChild(editor);
    editor.focus();
    if (editor.select) editor.select();

    let done = false;
    const commit = async () => {
        if (done) return;
        done = true;
        const raw = editor.value === '' ? null : editor.value;
        const unchanged = (raw === null && (p.value === null || p.value === '')) || String(raw) === String(p.value);
        if (unchanged) { cell.innerHTML = before; return; }
        await saveProperty(p, raw, cell, before);
    };
    editor.addEventListener('blur', commit);
    editor.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); editor.blur(); }
        if (e.key === 'Escape') { done = true; cell.innerHTML = before; }
    });
    if (editor.tagName === 'SELECT') editor.addEventListener('change', () => editor.blur());
}

async function saveProperty(p, raw, cell, before) {
    try {
        const data = await postJson(API + 'save_object.php', {
            id: obj.id,
            name: obj.name,
            parent_id: obj.parent_id,
            property_values: [{ property_id: p.property_id, value: raw }]
        });
        if (!data.success) throw new Error(data.error || 'Save failed.');
        await reloadAndRender();
        toast(p.label + ' saved');
    } catch (err) {
        cell.innerHTML = before;
        toast(err.message, true);
    }
}

async function savePartial(patch) {
    try {
        const payload = {
            id: obj.id,
            name: patch.name ?? obj.name,
            parent_id: patch.parent_id !== undefined ? patch.parent_id : obj.parent_id,
            property_values: []
        };
        if (patch.is_planned !== undefined) payload.is_planned = patch.is_planned;
        const data = await postJson(API + 'save_object.php', payload);
        if (!data.success) throw new Error(data.error || 'Save failed.');
        await reloadAndRender();
        toast('Saved');
    } catch (err) {
        await loadObject();
        render();
        toast(err.message, true);
    }
}

/* ---------------- actions ---------------- */

async function generateSummary(btn) {
    if (summaryGenerating) return;
    summaryGenerating = true;
    const txt = document.getElementById('o2AiTxt');
    if (btn) btn.disabled = true;
    if (txt) { txt.classList.add('muted'); txt.textContent = 'Thinking…'; }
    try {
        const data = await postJson(API + 'generate_object_summary.php', { id: OBJECT_ID });
        if (!data.success) throw new Error(data.error || 'Could not generate a summary.');
        await loadObject();
        render();
        toast('Summary written');
    } catch (err) {
        toast(err.message, true);
        await loadObject();
        render();
    } finally {
        summaryGenerating = false;
    }
}

async function createImpactDiagram(btn) {
    if (btn) { btn.disabled = true; }
    try {
        // NB the endpoint takes object_id (not id), and signals the empty case
        // through `error` rather than a separate code.
        const data = await postJson(API + 'create_impact_diagram.php', { object_id: OBJECT_ID });
        if (!data.success) {
            throw new Error(data.error === 'empty_blast_radius'
                ? 'Nothing depends on this, so there is no diagram to draw.'
                : (data.error || 'Could not create the diagram.'));
        }
        window.location.href = '../network-mapper/diagram.php?id=' + data.diagram_id + '&fit=1';
    } catch (err) {
        toast(err.message, true);
        if (btn) btn.disabled = false;
    }
}

/* ---------------- object_ref editing ---------------- */

/* Search is scoped to the property's target class and excludes this object, so
   a field can never be pointed at the thing that holds it. */
function beginEditObjectRef(cell, p) {
    const before = cell.innerHTML;
    cell.innerHTML =
        '<div class="autocomplete-wrap">' +
            '<input type="text" id="o2ref_' + p.property_id + '" autocomplete="off" ' +
                'placeholder="Search ' + esc(p.target_class_name || 'objects') + '…" ' +
                'value="' + (p.value_object ? esc(p.value_object.name) : '') + '">' +
            '<div class="autocomplete-results" id="o2refres_' + p.property_id + '"></div>' +
        '</div>';

    const input = document.getElementById('o2ref_' + p.property_id);
    const results = document.getElementById('o2refres_' + p.property_id);
    input.focus();
    input.select();

    let done = false;
    const finish = async raw => { if (done) return; done = true; await saveProperty(p, raw, cell, before); };
    const cancel = () => { if (done) return; done = true; cell.innerHTML = before; wireProps(); };

    wireAutocomplete(input, results, { class_id: p.target_class_id, exclude_id: obj.id }, picked => {
        input.value = picked.name;
        results.classList.remove('active');
        finish(picked.id);
    });

    input.addEventListener('keydown', e => { if (e.key === 'Escape') cancel(); });
    input.addEventListener('blur', () => {
        // Let a click on a result land before deciding what the blur meant.
        setTimeout(() => {
            if (done) return;
            if (input.value.trim() === '') finish(null);   // emptied = cleared
            else cancel();                                  // typed but picked nothing
        }, 200);
    });
}

/* ---------------- shared autocomplete ---------------- */

function wireAutocomplete(inputEl, resultsEl, params, onPick) {
    let lastQuery = '';
    let current = [];
    acHighlightedIdx = -1;

    inputEl.addEventListener('input', () => {
        const q = inputEl.value.trim();
        if (q === lastQuery) return;
        lastQuery = q;
        if (acTimer) clearTimeout(acTimer);
        if (q === '') { resultsEl.classList.remove('active'); return; }
        acTimer = setTimeout(async () => {
            try {
                const url = API + 'search_objects.php?q=' + encodeURIComponent(q) +
                    (params.class_id ? '&class_id=' + params.class_id : '') +
                    (params.exclude_id ? '&exclude_id=' + params.exclude_id : '');
                const data = await (await fetch(url)).json();
                if (!data.success) return;
                current = data.results || [];
                acHighlightedIdx = -1;
                draw();
            } catch (e) { /* silent — an empty dropdown is the failure state */ }
        }, 200);
    });

    inputEl.addEventListener('keydown', e => {
        if (!resultsEl.classList.contains('active')) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); acHighlightedIdx = Math.min(current.length - 1, acHighlightedIdx + 1); draw(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); acHighlightedIdx = Math.max(0, acHighlightedIdx - 1); draw(); }
        else if (e.key === 'Enter' && acHighlightedIdx >= 0) { e.preventDefault(); onPick(current[acHighlightedIdx]); }
        else if (e.key === 'Escape') { resultsEl.classList.remove('active'); }
    });

    function draw() {
        if (!current.length) {
            resultsEl.innerHTML = '<div class="ac-empty">No matches</div>';
            resultsEl.classList.add('active');
            return;
        }
        resultsEl.innerHTML = current.map((r, i) =>
            '<div class="ac-result' + (i === acHighlightedIdx ? ' highlighted' : '') + '" data-idx="' + i + '">' +
                '<span>' + esc(r.name) + '</span><span class="ac-class">' + esc(r.class_name) + '</span>' +
            '</div>').join('');
        resultsEl.classList.add('active');
        resultsEl.querySelectorAll('.ac-result').forEach(el => {
            // mousedown, so it beats the input's blur handler
            el.addEventListener('mousedown', e => { e.preventDefault(); onPick(current[parseInt(el.dataset.idx, 10)]); });
        });
    }
}

/* ---------------- planned / in service ---------------- */

async function togglePlanned() {
    await savePartial({ is_planned: !obj.is_planned });
}

/* ---------------- parent picker ---------------- */

function openParentModal() {
    document.getElementById('o2ParentInput').value = obj.parent_name || '';
    document.getElementById('o2ParentId').value = obj.parent_id || '';
    document.getElementById('o2ParentResults').classList.remove('active');
    document.getElementById('o2ParentModal').classList.add('active');
    setTimeout(() => document.getElementById('o2ParentInput').focus(), 0);

    wireAutocomplete(
        document.getElementById('o2ParentInput'),
        document.getElementById('o2ParentResults'),
        { exclude_id: obj.id },
        picked => {
            document.getElementById('o2ParentId').value = picked.id;
            document.getElementById('o2ParentInput').value = picked.name;
            document.getElementById('o2ParentResults').classList.remove('active');
        }
    );
}
function closeParentModal() { document.getElementById('o2ParentModal').classList.remove('active'); }

async function saveParent() {
    const newId = document.getElementById('o2ParentId').value;
    const text = document.getElementById('o2ParentInput').value.trim();
    if (text === '') { closeParentModal(); await savePartial({ parent_id: null }); return; }
    if (!newId) { toast('Pick one of the suggestions.', true); return; }
    closeParentModal();
    // The server cycle-checks this — a parent that is its own descendant comes
    // back as an error rather than being silently accepted.
    await savePartial({ parent_id: parseInt(newId, 10) });
}

async function clearParent() {
    closeParentModal();
    if (!obj.parent_id) return;
    await savePartial({ parent_id: null });
}

/* ---------------- relationships ---------------- */

function openRelModal() {
    if (!relationshipTypes.length) {
        toast('No relationship types are defined yet — add some in Settings.', true);
        return;
    }
    const sel = document.getElementById('o2RelType');
    sel.innerHTML = relationshipTypes.map(rt => '<option value="' + rt.id + '">' + esc(rt.verb) + '</option>').join('');
    sel.onchange = updateRelInverseHint;
    updateRelInverseHint();
    document.getElementById('o2RelTarget').value = '';
    document.getElementById('o2RelTargetId').value = '';
    document.getElementById('o2RelResults').classList.remove('active');
    document.getElementById('o2RelModal').classList.add('active');
    setTimeout(() => document.getElementById('o2RelTarget').focus(), 0);

    wireAutocomplete(
        document.getElementById('o2RelTarget'),
        document.getElementById('o2RelResults'),
        { exclude_id: obj.id },
        picked => {
            document.getElementById('o2RelTargetId').value = picked.id;
            document.getElementById('o2RelTarget').value = picked.name;
            document.getElementById('o2RelResults').classList.remove('active');
        }
    );
}
function closeRelModal() { document.getElementById('o2RelModal').classList.remove('active'); }

/* Shows the inverse live, because the verb reads one way from here and the
   other way from the object on the far end. */
function updateRelInverseHint() {
    const id = parseInt(document.getElementById('o2RelType').value, 10);
    const rt = relationshipTypes.find(r => r.id === id);
    document.getElementById('o2RelHint').textContent = rt
        ? 'From the other object’s side this reads: “' + rt.inverse_verb + ' ' + obj.name + '”.'
        : '';
}

async function saveRelationship() {
    const typeId = parseInt(document.getElementById('o2RelType').value, 10);
    const toId = document.getElementById('o2RelTargetId').value;
    if (!typeId || !toId) { toast('Pick a verb and an object.', true); return; }
    try {
        const data = await postJson(API + 'save_object_relationship.php', {
            from_object_id: obj.id,
            to_object_id: parseInt(toId, 10),
            relationship_type_id: typeId
        });
        if (!data.success) throw new Error(data.error || 'Could not add the relationship.');
        closeRelModal();
        await reloadAndRender();
        toast('Relationship added');
    } catch (err) { toast(err.message, true); }
}

async function deleteRelationship(id) {
    const ok = await confirmAction({
        title: 'Remove relationship',
        message: 'This unlinks the two objects. Neither object is deleted.',
        okLabel: 'Remove',
        okClass: 'danger'
    });
    if (!ok) return;
    try {
        const data = await postJson(API + 'delete_object_relationship.php', { id: id });
        if (!data.success) throw new Error(data.error || 'Could not remove it.');
        await reloadAndRender();
        toast('Relationship removed');
    } catch (err) { toast(err.message, true); }
}

/* ---------------- property definition (floating modal) ---------------- */

let pdDrag = { active: false, dx: 0, dy: 0 };

function initPropDefModalDrag() {
    const head = document.getElementById('o2PdHeader');
    const modal = document.getElementById('o2PdModal');
    if (!head || !modal) return;
    head.addEventListener('mousedown', e => {
        pdDrag.active = true;
        const r = modal.getBoundingClientRect();
        // Drop the centring transform on first drag, or the modal jumps by half
        // its own width the moment it is grabbed.
        modal.style.transform = 'none';
        modal.style.left = r.left + 'px';
        modal.style.top = r.top + 'px';
        pdDrag.dx = e.clientX - r.left;
        pdDrag.dy = e.clientY - r.top;
        e.preventDefault();
    });
    document.addEventListener('mousemove', e => {
        if (!pdDrag.active) return;
        modal.style.left = (e.clientX - pdDrag.dx) + 'px';
        modal.style.top = (e.clientY - pdDrag.dy) + 'px';
    });
    document.addEventListener('mouseup', () => { pdDrag.active = false; });
}

function openPropDefModal(propertyId) {
    const p = (obj.properties || []).find(x => x.property_id === propertyId);
    if (!p) return;
    document.getElementById('o2PdTitle').textContent = 'Edit field — ' + p.label;
    document.getElementById('o2PdId').value = p.property_id;
    document.getElementById('o2PdLabel').value = p.label;
    document.getElementById('o2PdKey').value = p.property_key;
    document.getElementById('o2PdType').value = p.property_type;
    document.getElementById('o2PdOrder').value = p.display_order;
    document.getElementById('o2PdRequired').checked = !!p.is_required;
    document.getElementById('o2PdSpreads').checked = !!Number(p.spreads_impact);

    document.getElementById('o2PdTargetClass').innerHTML = '<option value="">Select…</option>' +
        allClasses.map(c => '<option value="' + c.id + '"' + (p.target_class_id === c.id ? ' selected' : '') + '>' + esc(c.name) + '</option>').join('');

    if (typeof renderOptionsEditor === 'function') renderOptionsEditor('o2PdOptions', p.options || []);
    onPropDefTypeChange();

    const modal = document.getElementById('o2PdModal');
    modal.style.left = '50%';
    modal.style.top = '90px';
    modal.style.transform = 'translateX(-50%)';
    modal.classList.add('active');
    setTimeout(() => document.getElementById('o2PdLabel').focus(), 0);
}
function closePropDefModal() { document.getElementById('o2PdModal').classList.remove('active'); }

function onPropDefTypeChange() {
    const t = document.getElementById('o2PdType').value;
    document.getElementById('o2PdTargetGroup').style.display = t === 'object_ref' ? 'block' : 'none';
    document.getElementById('o2PdSpreadsGroup').style.display = t === 'object_ref' ? 'block' : 'none';
    document.getElementById('o2PdOptionsGroup').style.display = t === 'dropdown' ? 'block' : 'none';
}

async function savePropDef() {
    const type = document.getElementById('o2PdType').value;
    const options = (type === 'dropdown' && typeof collectOptionsFromEditor === 'function')
        ? collectOptionsFromEditor('o2PdOptions') : [];
    const targetClassId = document.getElementById('o2PdTargetClass').value;

    if (type === 'object_ref' && !targetClassId) { toast('Pick which class this points at.', true); return; }
    if (type === 'dropdown' && !options.length) { toast('Add at least one option.', true); return; }

    const payload = {
        id: document.getElementById('o2PdId').value || null,
        class_id: obj.class_id,
        label: document.getElementById('o2PdLabel').value,
        property_key: document.getElementById('o2PdKey').value,
        property_type: type,
        target_class_id: type === 'object_ref' ? targetClassId : null,
        is_required: document.getElementById('o2PdRequired').checked,
        spreads_impact: type === 'object_ref' && document.getElementById('o2PdSpreads').checked,
        display_order: parseInt(document.getElementById('o2PdOrder').value, 10) || 0,
        options: options
    };
    try {
        const data = await postJson(API + 'save_class_property.php', payload);
        if (!data.success) throw new Error(data.error || 'Could not save the field.');
        closePropDefModal();
        // Reload rather than patch — a type or options change rewrites how every
        // object of this class renders, not just this one.
        await reloadAndRender();
        toast('Field updated');
    } catch (err) { toast(err.message, true); }
}

async function deleteObject() {
    // Count the WHOLE subtree, not just direct children — the delete cascades
    // all the way down, so a warning that says "2 objects" when it will remove
    // nine is worse than no warning.
    const desc = (impact && impact.descendants) ? impact.descendants.length : (obj.children || []).length;
    const msg = desc
        ? 'This also deletes the ' + desc + ' object' + (desc === 1 ? '' : 's') + ' inside it. This cannot be undone.'
        : 'This cannot be undone.';
    const ok = await confirmAction({
        title: 'Delete ' + obj.name + '?',
        message: msg,
        okLabel: 'Delete',
        okClass: 'danger'
    });
    if (!ok) return;
    try {
        const data = await postJson(API + 'delete_object.php', { id: obj.id });
        if (!data.success) throw new Error(data.error || 'Could not delete.');
        toast(data.deleted_descendants > 0
            ? 'Deleted, along with ' + data.deleted_descendants + ' object' + (data.deleted_descendants === 1 ? '' : 's') + ' inside it'
            : 'Deleted');
        setTimeout(() => { window.location.href = './'; }, 600);
    } catch (err) {
        toast(err.message, true);
    }
}
