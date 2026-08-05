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
let showBlanks = false;
let summaryGenerating = false;

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

let toastTimer = null;
function toast(msg, isError) {
    const el = document.getElementById('o2Toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.toggle('err', !!isError);
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
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
    Promise.all([loadObject(), loadImpact(), loadActivity(), loadClasses()]).then(() => {
        if (obj) render();
    });
});

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

function connectionCount() {
    const r = (obj && obj.relationships) || { outgoing: [], incoming: [] };
    const refs = (impact && impact.referenced_by_property) ? impact.referenced_by_property.length : 0;
    const outRefs = (obj.properties || []).filter(p => p.property_type === 'object_ref' && p.value_object).length;
    return (r.outgoing || []).length + (r.incoming || []).length +
           (obj.children || []).length + (obj.parent_id ? 1 : 0) + refs + outRefs;
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
    if (obj.is_planned) chips.push('<span class="o2-chip planned">Planned — not yet in service</span>');
    if (obj.parent_id) {
        chips.push('<span class="o2-chip">Part of <a href="object2.php?id=' + obj.parent_id + '">' + esc(obj.parent_name) + '</a></span>');
    }
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
    const conns = connectionCount();
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
    const r = obj.relationships || { outgoing: [], incoming: [] };
    const refsIn = (impact && impact.referenced_by_property) ? impact.referenced_by_property : [];
    const refsOut = (obj.properties || []).filter(p => p.property_type === 'object_ref' && p.value_object);

    const left = []
        .concat((r.incoming || []).map(x => nodeRow(x.other_id, x.other_name, x.other_class_name, x.inverse_verb + ' this')))
        .concat(refsIn.map(x => nodeRow(x.id, x.name, x.class_name, (x.property_label || 'a property') + ' points here')));

    const right = []
        .concat((r.outgoing || []).map(x => nodeRow(x.other_id, x.other_name, x.other_class_name, 'this ' + x.verb)))
        .concat(refsOut.map(p => nodeRow(p.value_object.id, p.value_object.name, p.value_object.class_name || p.target_class_name, p.label)));

    const centre =
        (obj.parent_id
            ? '<div class="o2-updown">' + nodeRow(obj.parent_id, obj.parent_name, obj.parent_class_name, 'this is part of') + upArrow() + '</div>'
            : '') +
        '<div class="o2-centre">' + iconForClassId(obj.class_id, 30) +
            '<div class="o2-centre-name">' + esc(obj.name) + '</div>' +
            '<div class="o2-centre-cls">' + esc(obj.class_name) + '</div>' +
        '</div>' +
        ((obj.children || []).length
            ? '<div class="o2-updown">' + downArrow() +
              (obj.children || []).map(c => nodeRow(c.id, c.name, c.class_name, 'contained by this')).join('') + '</div>'
            : '');

    const total = left.length + right.length + (obj.children || []).length + (obj.parent_id ? 1 : 0);
    const head = '<div class="o2-card-head"><span class="o2-card-title">Connections</span>' +
        '<span class="o2-card-sub">' + total + ' in total</span></div>';

    if (!total) {
        return '<div class="o2-card" id="o2Conn">' + head +
            '<div class="o2-empty">Nothing is attached to this — no parent, no children, no relationships and nothing points at it. ' +
            'It can never appear in anyone’s blast radius, and nobody will reach it by navigating.</div></div>';
    }

    return '<div class="o2-card" id="o2Conn">' + head +
        '<div class="o2-conn">' +
            '<div class="o2-conn-col"><div class="o2-conn-lbl">Points at this</div><div class="o2-conn-list">' +
                (left.length ? left.join('') : '<div class="o2-empty">Nothing</div>') +
            '</div></div>' +
            '<div class="o2-conn-col mid">' + centre + '</div>' +
            '<div class="o2-conn-col right"><div class="o2-conn-lbl">This points at</div><div class="o2-conn-list">' +
                (right.length ? right.join('') : '<div class="o2-empty">Nothing</div>') +
            '</div></div>' +
        '</div>' +
    '</div>';
}

function nodeRow(id, name, className, verb) {
    return '<div>' +
        (verb ? '<div class="o2-rel-verb">' + esc(verb) + '</div>' : '') +
        '<a class="o2-node" href="object2.php?id=' + id + '">' +
            '<span class="o2-node-ico">' + iconForClassName(className, 18) + '</span>' +
            '<span class="o2-node-txt"><span class="o2-node-name">' + esc(name) + '</span>' +
            '<span class="o2-node-sub">' + esc(className || '') + '</span></span>' +
        '</a></div>';
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
        '<div style="margin-top:14px;"><span class="o2-stat-lbl">Added ' + esc(fmtDateTime(obj.created_datetime)) +
        ' · last change ' + esc(fmtDateTime(obj.updated_datetime)) + '</span></div>' +
    '</div>';
}

function propCard(p) {
    const empty = !(p.value !== null && p.value !== '' && p.value !== undefined);
    return '<div class="o2-prop">' +
        '<div class="o2-prop-lbl">' + esc(p.label) + (p.is_required ? '<span class="req" title="Required">*</span>' : '') + '</div>' +
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
    if (p.property_type === 'object_ref') {
        toast('Object references are picked on the current page — this prototype links them only.');
        return;
    }

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
        await Promise.all([loadObject(), loadImpact(), loadActivity()]);
        render();
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
        await Promise.all([loadObject(), loadImpact(), loadActivity()]);
        render();
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

async function deleteObject() {
    const kids = (obj.children || []).length;
    const msg = kids
        ? 'Delete "' + obj.name + '" and everything inside it? That is ' + kids + ' object' + (kids === 1 ? '' : 's') + ' plus anything below them. This cannot be undone.'
        : 'Delete "' + obj.name + '"? This cannot be undone.';
    if (!confirm(msg)) return;
    try {
        const data = await postJson(API + 'delete_object.php', { id: obj.id });
        if (!data.success) throw new Error(data.error || 'Could not delete.');
        window.location.href = './';
    } catch (err) {
        toast(err.message, true);
    }
}
