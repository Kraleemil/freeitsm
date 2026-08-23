/**
 * Tickets Calendar JavaScript
 * Month / Week / Day views, matching the standalone Calendar module's UX.
 */

// API base path - can be overridden by page before loading this script
const API_BASE = window.API_BASE || 'api/';

// Locale for Intl date/time formatting — sourced from <html lang> so it matches
// the user's selected interface language. Falls back to en-GB if the bridge
// hasn't run or the page didn't set <html lang>.
const PAGE_LOCALE = (document.documentElement.lang || 'en-GB');

// Translation lookup with a graceful fallback when the i18n.js bridge isn't loaded.
function tr(key, params) {
    return (typeof window.t === 'function') ? window.t(key, params) : key;
}

// State
let currentView = 'month';
let currentDate = new Date();
let scheduledTickets = [];

// Whose scheduled work is on screen: 'mine' or 'all'. Server-rendered from the
// analyst's saved preference (see tickets/calendar.php) so the first paint is
// already correct rather than flipping a moment after load.
let currentScope = (window.CALENDAR_SCOPE === 'all') ? 'all' : 'mine';

/**
 * A stable colour for an owner.
 *
 * Keyed on the analyst ID, not the name, so it survives a rename and two people
 * called J. Smith never collide. The palette is fixed rather than generated from
 * a hash of the whole colour space: random hues land on unreadable colours and
 * on near-neighbours that look the same, and these ten were picked to stay
 * legible on both the light and dark surfaces.
 *
 * Unassigned is deliberately grey and outside the palette — "nobody" is a real
 * answer here (a ticket can be scheduled with no owner) and it should not look
 * like just another person.
 */
const OWNER_COLOURS = ['#2b88d8', '#107c41', '#8764b8', '#c239b3', '#d83b01',
                       '#00838f', '#7a7574', '#498205', '#a4262c', '#8e562e'];
const OWNER_COLOUR_UNASSIGNED = '#9aa0a6';

// owner_id -> palette colour, rebuilt from the tickets on screen each load.
let ownerColourMap = new Map();

/**
 * Assign colours to the owners actually on screen.
 *
 * 🔴 NOT `id % palette.length`, which is what this was first. Analyst ids 1 and
 * 41 both landed on the same green, so two people were drawn identically while
 * the legend confidently showed them as separate entries — the exact thing the
 * colours exist to prevent. Any two ids ten apart collided, which on a real
 * install is not a rare accident.
 *
 * Assigning by POSITION in the set on screen guarantees distinct colours for up
 * to ten owners. The trade is that somebody's colour can change when the set
 * changes — acceptable because the legend is on screen naming everyone, so it is
 * always readable; an ambiguous colour is not. Sorted by id so the assignment is
 * deterministic for a given set rather than depending on load order.
 */
function assignOwnerColours(tickets) {
    const ids = [...new Set(tickets.map(t => t.owner_id).filter(Boolean))]
        .map(Number)
        .sort((a, b) => a - b);
    ownerColourMap = new Map(ids.map((id, i) => [id, OWNER_COLOURS[i % OWNER_COLOURS.length]]));
}

function ownerColour(ownerId) {
    if (!ownerId) return OWNER_COLOUR_UNASSIGNED;   // "nobody" is a real answer
    const id = Number(ownerId);
    // The fallback only reaches an owner who is not on screen, so it cannot
    // collide with anything being drawn.
    return ownerColourMap.get(id) || OWNER_COLOURS[Math.abs(id) % OWNER_COLOURS.length];
}

/** The owner stripe, only in "Everyone" — in "Mine" every ticket is yours. */
function ownerStripeStyle(ticket) {
    if (currentScope !== 'all') return '';
    return `border-left: 4px solid ${ownerColour(ticket.owner_id)};`;
}

/**
 * The hover tooltip for an event.
 *
 * In "Everyone" it names the owner, because a colour alone makes you look away
 * to the legend to answer "whose is that?" — and a month cell shows a stripe
 * about 4px wide. In "Mine" the answer is always you, so it stays the subject.
 */
function calendarTicketTitle(ticket) {
    const subject = String(ticket.subject || '');
    if (currentScope !== 'all') return escapeHtml(subject);
    const owner = ticket.owner_name || tr('tickets.calendar.unassigned');
    return escapeHtml(`${owner} — ${subject}`);
}

/**
 * Repaint the legend from the tickets on screen.
 *
 * Built from what was actually loaded rather than from the analyst list: the
 * colours then explain themselves, nobody who has nothing scheduled clutters it,
 * and there is no second source to fall out of step with the events.
 */
function renderOwnerLegend() {
    const el = document.getElementById('ownerLegend');
    if (!el) return;
    if (currentScope !== 'all' || !scheduledTickets.length) {
        el.style.display = 'none';
        el.innerHTML = '';
        return;
    }
    const seen = new Map();
    scheduledTickets.forEach(t => {
        const key = t.owner_id || 0;
        if (!seen.has(key)) seen.set(key, t.owner_name || tr('tickets.calendar.unassigned'));
    });
    const items = [...seen.entries()].sort((a, b) => String(a[1]).localeCompare(String(b[1])));
    el.innerHTML = items.map(([id, name]) =>
        `<span class="owner-legend-item">
            <span class="owner-legend-swatch" style="background:${ownerColour(id)}"></span>
            ${escapeHtml(name)}
         </span>`).join('');
    el.style.display = '';
}

/** Switch between my scheduled work and everyone's, and remember the choice. */
function setScope(scope) {
    if (scope !== 'all') scope = 'mine';
    if (scope === currentScope) return;
    currentScope = scope;
    document.querySelectorAll('.scope-toggle .view-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.scope === scope);
    });
    // Saved per analyst, fire-and-forget: failing to remember the choice must not
    // stop the calendar reloading with it applied now.
    fetch('../api/system/set_user_preference.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'tickets_calendar_scope', value: scope })
    }).catch(() => {});
    renderCalendar();
}

// Day order: Monday-first to match UK conventions and the legacy tickets
// calendar behaviour. Index 0 = Monday, index 6 = Sunday.
const WEEKDAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
const MONTH_KEYS = ['january', 'february', 'march', 'april', 'may', 'june',
                    'july', 'august', 'september', 'october', 'november', 'december'];

function shortWeekdayLabel(weekdayIndex) {
    // Render short labels via Intl to respect locale; weekdayIndex is Monday=0..Sunday=6.
    // Build a reference date for that weekday and format it short.
    const refDayOfWeek = (weekdayIndex + 1) % 7; // Convert to Sun=0..Sat=6
    const ref = new Date(2024, 0, 7 + refDayOfWeek); // 7 Jan 2024 = Sunday
    return ref.toLocaleDateString(PAGE_LOCALE, { weekday: 'short' });
}

function monthLabel(monthIndex) {
    return tr('common.calendar.months.' + MONTH_KEYS[monthIndex]);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    renderCalendar();
});

// Switch view
function setView(view) {
    currentView = view;
    // ⚠️ Scoped to the VIEW toggle. A bare '.view-btn' also matches the Mine /
    // Everyone buttons beside it, whose dataset.view is undefined — so every view
    // change would quietly clear the scope selection and leave neither lit.
    document.querySelectorAll('.view-toggle:not(.scope-toggle) .view-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });
    renderCalendar();
}

// Navigate to today
function goToToday() {
    currentDate = new Date();
    renderCalendar();
}

// Navigate to previous period (depends on current view)
function navigatePrev() {
    if (currentView === 'month') {
        currentDate.setMonth(currentDate.getMonth() - 1);
    } else if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() - 7);
    } else {
        currentDate.setDate(currentDate.getDate() - 1);
    }
    renderCalendar();
}

// Navigate to next period (depends on current view)
function navigateNext() {
    if (currentView === 'month') {
        currentDate.setMonth(currentDate.getMonth() + 1);
    } else if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() + 7);
    } else {
        currentDate.setDate(currentDate.getDate() + 1);
    }
    renderCalendar();
}

// Legacy month nav (kept for backwards compatibility with anything still calling it)
function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    renderCalendar();
}

// Render the calendar for the current view
async function renderCalendar() {
    updateTitle();
    await loadScheduledTicketsForRange();

    // Before anything paints: the event stripes and the legend must agree, so
    // the assignment happens once, here, rather than inside either of them.
    assignOwnerColours(scheduledTickets);
    renderOwnerLegend();
    const grid = document.getElementById('calendarGrid');
    if (currentView === 'month') {
        renderMonthView(grid);
    } else if (currentView === 'week') {
        renderWeekView(grid);
    } else {
        renderDayView(grid);
    }
}

// Update the calendar title
function updateTitle() {
    const titleEl = document.getElementById('calendarTitle');
    if (currentView === 'month') {
        titleEl.textContent = `${monthLabel(currentDate.getMonth())} ${currentDate.getFullYear()}`;
    } else if (currentView === 'week') {
        const weekStart = getWeekStart(currentDate);
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekEnd.getDate() + 6);
        if (weekStart.getMonth() === weekEnd.getMonth()) {
            titleEl.textContent = `${monthLabel(weekStart.getMonth())} ${weekStart.getDate()} – ${weekEnd.getDate()}, ${weekStart.getFullYear()}`;
        } else {
            titleEl.textContent = `${monthLabel(weekStart.getMonth())} ${weekStart.getDate()} – ${monthLabel(weekEnd.getMonth())} ${weekEnd.getDate()}, ${weekEnd.getFullYear()}`;
        }
    } else {
        titleEl.textContent = `${monthLabel(currentDate.getMonth())} ${currentDate.getDate()}, ${currentDate.getFullYear()}`;
    }
}

// Compute the date range needed for the current view and load tickets
async function loadScheduledTicketsForRange() {
    let start, end;

    if (currentView === 'month') {
        // Start from the Monday on or before the 1st, span 6 weeks
        const first = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        start = getWeekStart(first);
        end = new Date(start);
        end.setDate(end.getDate() + 42);
    } else if (currentView === 'week') {
        start = getWeekStart(currentDate);
        end = new Date(start);
        end.setDate(end.getDate() + 7);
    } else {
        start = new Date(currentDate);
        start.setHours(0, 0, 0, 0);
        end = new Date(start);
        end.setDate(end.getDate() + 1);
    }

    const startStr = formatDateForCompare(start);
    const endStr = formatDateForCompare(end);

    try {
        const response = await fetch(`${API_BASE}get_scheduled_tickets.php?start=${startStr}&end=${endStr}&scope=${currentScope}&_t=${Date.now()}`);
        const data = await response.json();
        if (data.success) {
            scheduledTickets = data.tickets.map(t => ({
                ...t,
                date: t.work_start_datetime.split('T')[0],
                // An all-day ticket is stored as 00:00–23:59 so that anything
                // ignoring the flag still gets a sane block, but showing "12:00 AM"
                // against it would be the flag being ignored HERE.
                time: t.work_all_day
                    ? tr('tickets.calendar.all_day')
                    : new Date(t.work_start_datetime).toLocaleTimeString(PAGE_LOCALE, { hour: '2-digit', minute: '2-digit' })
            }));
        } else {
            console.error('Error loading tickets:', data.error);
            scheduledTickets = [];
        }
    } catch (error) {
        console.error('Error:', error);
        scheduledTickets = [];
    }
}

// Get start of week as Monday (Monday-first week)
/**
 * How tall a scheduled ticket draws, in pixels — the grid is one minute per pixel.
 *
 * The server always sends `duration_minutes`, resolving the default for a ticket
 * scheduled before end times existed, so there is nothing to guess here. The
 * clamps are about the GRID, not the data:
 *   - a minimum, or a 15-minute ticket renders as an unclickable hairline;
 *   - a cap at midnight, because a block running past the end of the day would
 *     otherwise overflow its column rather than stop at it.
 */
function scheduledBlockHeight(ticket, topMinutes) {
    // An all-day ticket is stored 00:00–23:59 so that anything ignoring the flag
    // still sees a sensible block — but drawn literally that is a 1440px column
    // that swallows the whole day and buries every timed ticket beside it. It is
    // "this day, no particular time", so it gets a slim bar at the top, the way
    // every calendar people already use shows one.
    if (ticket.work_all_day) return ALL_DAY_BAR_PX;
    const minutes = parseInt(ticket.duration_minutes, 10) || 60;
    return Math.max(20, Math.min(minutes, 1440 - topMinutes));
}

/** Height of the all-day bar, and the offset timed events start below it. */
const ALL_DAY_BAR_PX = 22;

/**
 * Where a ticket starts, in minutes-as-pixels down the day column.
 *
 * All-day pins to the very top. Timed events keep their real position, except
 * anything in the first few minutes of the day, which is nudged clear of the
 * all-day bar rather than being hidden underneath it.
 */
function scheduledBlockTop(ticket, startHour, startMinutes, dayHasAllDay) {
    if (ticket.work_all_day) return 0;
    const top = startHour * 60 + startMinutes;
    return dayHasAllDay ? Math.max(top, ALL_DAY_BAR_PX) : top;
}

function getWeekStart(date) {
    const d = new Date(date);
    const dayOfWeek = d.getDay(); // 0 = Sunday, 1 = Monday, …, 6 = Saturday
    const offsetToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
    d.setDate(d.getDate() + offsetToMonday);
    d.setHours(0, 0, 0, 0);
    return d;
}

// Format date as YYYY-MM-DD using local time
function formatDateForCompare(date) {
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

// Render month view
function renderMonthView(container) {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const firstDay = new Date(year, month, 1);
    const startDate = getWeekStart(firstDay);

    let html = '<div class="month-grid">';

    // Header row
    html += '<div class="month-header">';
    for (let i = 0; i < 7; i++) {
        html += `<div class="month-header-cell">${shortWeekdayLabel(i)}</div>`;
    }
    html += '</div>';

    // Days
    html += '<div class="month-body">';
    const current = new Date(startDate);
    for (let i = 0; i < 42; i++) {
        const isOtherMonth = current.getMonth() !== month;
        const isToday = current.getTime() === today.getTime();
        const dayOfWeek = current.getDay();
        const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
        const dateStr = formatDateForCompare(current);
        const dayTickets = scheduledTickets.filter(t => t.date === dateStr);

        let classes = 'month-day';
        if (isOtherMonth) classes += ' other-month';
        if (isToday) classes += ' today';
        if (isWeekend) classes += ' weekend';

        html += `<div class="${classes}">`;
        html += `<div class="day-number">${current.getDate()}</div>`;
        html += '<div class="day-tickets">';

        const maxDisplay = 3;
        dayTickets.slice(0, maxDisplay).forEach(ticket => {
            let priorityClass = '';
            if (ticket.priority === 'High') priorityClass = ' priority-high';
            else if (ticket.priority === 'Low') priorityClass = ' priority-low';

            html += `<div class="calendar-ticket${priorityClass}" style="${ownerStripeStyle(ticket)}" onclick="showTicketDetail(${ticket.id})" title="${calendarTicketTitle(ticket)}">
                        <span class="ticket-time">${ticket.time}</span>
                        ${escapeHtml(ticket.ticket_number)}
                     </div>`;
        });

        if (dayTickets.length > maxDisplay) {
            const moreCount = dayTickets.length - maxDisplay;
            html += `<div class="more-tickets" onclick="event.stopPropagation(); setView('day'); currentDate = new Date('${dateStr}T00:00:00'); renderCalendar();">${escapeHtml(tr('tickets.calendar.x_more', { count: moreCount }))}</div>`;
        }

        html += '</div></div>';
        current.setDate(current.getDate() + 1);
    }
    html += '</div></div>';

    container.innerHTML = html;
}

// Render week view (Mon–Sun across the top, 24-hour day on the side)
function renderWeekView(container) {
    const weekStart = getWeekStart(currentDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    let html = '<div class="week-grid">';

    // Header row (sticky)
    html += '<div class="week-header"><div class="week-header-time"></div><div class="week-header-days">';
    for (let i = 0; i < 7; i++) {
        const day = new Date(weekStart);
        day.setDate(day.getDate() + i);
        const isToday = day.getTime() === today.getTime();
        const isWeekend = i >= 5;
        html += `<div class="week-header-day${isToday ? ' today' : ''}${isWeekend ? ' weekend' : ''}">
                    <div class="week-day-name">${shortWeekdayLabel(i)}</div>
                    <div class="week-day-number">${day.getDate()}</div>
                 </div>`;
    }
    html += '</div></div>';

    // Body
    html += '<div class="week-body"><div class="week-time-column">';
    for (let hour = 0; hour < 24; hour++) {
        html += `<div class="week-time-slot-label">${formatHourLabel(hour)}</div>`;
    }
    html += '</div><div class="week-days-container">';

    for (let i = 0; i < 7; i++) {
        const day = new Date(weekStart);
        day.setDate(day.getDate() + i);
        const isToday = day.getTime() === today.getTime();
        const isWeekend = i >= 5;
        const dateStr = formatDateForCompare(day);

        html += `<div class="week-day-column${isToday ? ' today' : ''}${isWeekend ? ' weekend' : ''}">`;
        for (let hour = 0; hour < 24; hour++) {
            html += `<div class="week-time-slot"></div>`;
        }

        // Tickets in this day, each drawn at its real start and duration.
        const dayTickets = scheduledTickets.filter(t => t.date === dateStr);
        const dayHasAllDay = dayTickets.some(t => t.work_all_day);
        dayTickets.forEach(ticket => {
            const dt = new Date(ticket.work_start_datetime);
            const startHour = dt.getHours();
            const startMinutes = dt.getMinutes();
            const top = scheduledBlockTop(ticket, startHour, startMinutes, dayHasAllDay);
            const height = scheduledBlockHeight(ticket, top);

            let priorityClass = '';
            if (ticket.priority === 'High') priorityClass = ' priority-high';
            else if (ticket.priority === 'Low') priorityClass = ' priority-low';

            html += `<div class="week-event${priorityClass}${ticket.work_all_day ? ' is-allday' : ''}" style="top: ${top}px; height: ${height}px; ${ownerStripeStyle(ticket)}" title="${calendarTicketTitle(ticket)}"
                          onclick="showTicketDetail(${ticket.id})" title="${escapeHtml(ticket.subject)}">
                          <div class="week-event-title">${escapeHtml(ticket.ticket_number)}</div>
                          <div class="week-event-time">${ticket.time}</div>
                     </div>`;
        });

        html += '</div>';
    }
    html += '</div></div></div>';

    container.innerHTML = html;
}

// Render day view (single column, 24-hour day)
function renderDayView(container) {
    const viewDate = new Date(currentDate);
    viewDate.setHours(0, 0, 0, 0);
    const dateStr = formatDateForCompare(viewDate);
    const dayTickets = scheduledTickets.filter(t => t.date === dateStr);

    let html = '<div class="day-grid">';

    // Header
    html += '<div class="day-header"><div class="day-header-info">';
    html += `<div class="day-header-date">${currentDate.getDate()}</div>`;
    html += `<div class="day-header-weekday">${currentDate.toLocaleDateString(PAGE_LOCALE, { weekday: 'long', month: 'long', year: 'numeric' })}</div>`;
    html += '</div></div>';

    // Body
    html += '<div class="day-body"><div class="day-time-column">';
    for (let hour = 0; hour < 24; hour++) {
        html += `<div class="week-time-slot-label">${formatHourLabel(hour)}</div>`;
    }
    html += '</div><div class="day-events-column">';

    for (let hour = 0; hour < 24; hour++) {
        html += `<div class="day-time-slot"></div>`;
    }

    const dayHasAllDay = dayTickets.some(t => t.work_all_day);
    dayTickets.forEach(ticket => {
        const dt = new Date(ticket.work_start_datetime);
        const startHour = dt.getHours();
        const startMinutes = dt.getMinutes();
        const top = scheduledBlockTop(ticket, startHour, startMinutes, dayHasAllDay);
        const height = scheduledBlockHeight(ticket, top);

        let priorityClass = '';
        if (ticket.priority === 'High') priorityClass = ' priority-high';
        else if (ticket.priority === 'Low') priorityClass = ' priority-low';

        html += `<div class="day-event${priorityClass}${ticket.work_all_day ? ' is-allday' : ''}" style="top: ${top}px; height: ${height}px; ${ownerStripeStyle(ticket)}" title="${calendarTicketTitle(ticket)}"
                      onclick="showTicketDetail(${ticket.id})">
                      <div class="day-event-title">${escapeHtml(ticket.ticket_number)} — ${escapeHtml(ticket.subject)}</div>
                      <div class="day-event-time">${ticket.time}</div>
                 </div>`;
    });

    html += '</div></div></div>';

    container.innerHTML = html;
}

function formatHourLabel(hour) {
    // Localised hour label — uses 12h or 24h per locale conventions.
    const ref = new Date();
    ref.setHours(hour, 0, 0, 0);
    return ref.toLocaleTimeString(PAGE_LOCALE, { hour: 'numeric' });
}

// Show ticket detail modal
function showTicketDetail(ticketId) {
    const ticket = scheduledTickets.find(t => t.id === ticketId);
    if (!ticket) return;

    document.getElementById('ticketModalTitle').textContent = ticket.ticket_number;

    const body = document.getElementById('ticketModalBody');
    body.innerHTML = `
        <div class="ticket-detail-subject">${escapeHtml(ticket.subject)}</div>
        <div class="ticket-detail">
            <div class="ticket-detail-row">
                <div class="ticket-detail-label">${escapeHtml(tr('tickets.calendar.modal.scheduled'))}</div>
                <div class="ticket-detail-value">${escapeHtml(formatScheduleRange(ticket))}</div>
            </div>
            <div class="ticket-detail-row">
                <div class="ticket-detail-label">${escapeHtml(tr('tickets.calendar.modal.status'))}</div>
                <div class="ticket-detail-value">${ticket.status}</div>
            </div>
            <div class="ticket-detail-row">
                <div class="ticket-detail-label">${escapeHtml(tr('tickets.calendar.modal.priority'))}</div>
                <div class="ticket-detail-value">${ticket.priority}</div>
            </div>
            <div class="ticket-detail-row">
                <div class="ticket-detail-label">${escapeHtml(tr('tickets.calendar.modal.requester'))}</div>
                <div class="ticket-detail-value">${escapeHtml(ticket.requester_name || ticket.requester_email || tr('tickets.calendar.na'))}</div>
            </div>
            <div class="ticket-detail-row">
                <div class="ticket-detail-label">${escapeHtml(tr('tickets.calendar.modal.department'))}</div>
                <div class="ticket-detail-value">${escapeHtml(ticket.department_name || tr('tickets.calendar.unassigned'))}</div>
            </div>
            <div class="ticket-detail-row">
                <div class="ticket-detail-label">${escapeHtml(tr('tickets.calendar.modal.owner'))}</div>
                <div class="ticket-detail-value">${escapeHtml(ticket.owner_name || tr('tickets.calendar.unassigned'))}</div>
            </div>
        </div>
    `;

    const inboxUrl = window.INBOX_URL || 'inbox.php';
    document.getElementById('ticketModalLink').href = `${inboxUrl}?ticket=${ticket.id}`;

    document.getElementById('ticketModal').classList.add('active');
}

// Close ticket modal
function closeTicketModal() {
    document.getElementById('ticketModal').classList.remove('active');
}

// Utility functions
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

/**
 * "Tue 25 Aug 2026, 14:00 – 15:00", or the date alone when it is an all-day
 * ticket — a time range on something explicitly marked all-day reads as a
 * contradiction, and 00:00–23:59 is storage detail, not information.
 */
function formatScheduleRange(ticket) {
    if (!ticket.work_start_datetime) return '';
    if (ticket.work_all_day) {
        // The DATE is rebuilt from the Date object, never carved out of the
        // formatted "date at time" string: splitting that on its first comma
        // turned "Tue, Sep 1, 2026 at 02:00 PM" into "Tue" and threw the date
        // away — and it would have failed differently in every locale.
        const d = new Date(ticket.work_start_datetime).toLocaleDateString(PAGE_LOCALE, {
            weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
        });
        return `${d} · ${tr('tickets.calendar.all_day')}`;
    }
    const full = formatDateTime(ticket.work_start_datetime);
    if (!ticket.work_end_datetime) return full;
    const end = new Date(ticket.work_end_datetime)
        .toLocaleTimeString(PAGE_LOCALE, { hour: '2-digit', minute: '2-digit' });
    return `${full} – ${end}`;
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const datePart = date.toLocaleDateString(PAGE_LOCALE, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
    const timePart = date.toLocaleTimeString(PAGE_LOCALE, { hour: '2-digit', minute: '2-digit' });
    return tr('tickets.calendar.date_at_time', { date: datePart, time: timePart });
}
