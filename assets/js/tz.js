/**
 * Shared per-analyst timezone helpers (Phase 2 of the per-user timezone rollout).
 *
 * `window.USER_TIMEZONE` is published server-side by Tz::scriptTag() (see
 * includes/timezone.php). Every datetime is stored UTC; these helpers render it
 * in the analyst's chosen zone. When USER_TIMEZONE is unset the `timeZone`
 * option is simply omitted, so dates fall back to the browser's own zone.
 *
 * Load this file BEFORE any module JS that formats dates. It exposes globals
 * that mirror the proven tickets/inbox.js implementation, so a module's date
 * formatters become timezone-aware by:
 *   1. parsing the DB string as UTC:      const d = parseUTCDate(str);
 *   2. spreading tzOpts() into Intl opts:  d.toLocaleString(locale, tzOpts({ ... }))
 *   3. bucketing Today/Yesterday via:      ymdInZone(d) === ymdInZone(new Date())
 */
(function () {
    // Parse a DB datetime string as UTC (append Z if it carries no zone marker),
    // returning an absolute-instant Date. Returns null for empty input.
    window.parseUTCDate = function (dateStr) {
        if (!dateStr) return null;
        if (!/[Z+\-]\d{0,4}$/.test(dateStr)) {
            dateStr = String(dateStr).replace(' ', 'T') + 'Z';
        }
        return new Date(dateStr);
    };

    // Merge the analyst's display zone into an Intl.DateTimeFormat / toLocale*
    // options object. Pass the options you'd normally use; the timeZone is added
    // only when the analyst has chosen one.
    window.tzOpts = function (extra) {
        var o = Object.assign({}, extra || {});
        if (window.USER_TIMEZONE) o.timeZone = window.USER_TIMEZONE;
        return o;
    };

    // 'YYYY-MM-DD' for a Date, evaluated in the analyst's display zone. Use for
    // Today/Yesterday bucketing against the same zone the time is shown in.
    window.ymdInZone = function (date) {
        if (!date) return '';
        return date.toLocaleDateString('en-CA', window.tzOpts());
    };

    // Parse a NAIVE wall-clock datetime (a user-entered scheduling value stored
    // WITHOUT a zone — change plan windows, ticket work-start, calendar events,
    // PIR actuals) into a Date built from its literal components, with NO zone
    // interpretation. Format the result WITHOUT tzOpts, so "2pm" reads 2pm for
    // every analyst. These values are NOT UTC instants — never run them through
    // parseUTCDate/tzOpts. See the "Timezones and Time Handling" design note.
    window.parseNaiveDate = function (str) {
        if (!str) return null;
        var s = String(str).replace('T', ' ');
        var m = s.match(/(\d{4})-(\d{2})-(\d{2})[ ](\d{1,2}):(\d{2})/);
        if (m) return new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]);
        // DATE-ONLY ('2026-08-05', e.g. a CMDB date property or a task due date).
        // `new Date('2026-08-05')` parses as UTC midnight, which reads back as
        // the PREVIOUS day for anyone west of Greenwich. Build it from the
        // literal components instead, like the branch above.
        m = s.match(/^\s*(\d{4})-(\d{2})-(\d{2})\s*$/);
        if (m) return new Date(+m[1], +m[2] - 1, +m[3]);
        return new Date(str);
    };
})();

/**
 * Date & time FORMAT helpers (GH #105) — the browser half of includes/timezone.php.
 *
 * `window.DATE_FORMAT` is published server-side by Tz::scriptTag() and carries
 * the analyst's chosen templates plus the month/weekday names for the interface
 * language. PHP and JS render through the SAME token templates, so the two can
 * never drift.
 *
 * Format is orthogonal to zone. tzOpts()/parseUTCDate decide WHICH INSTANT is
 * being shown; these decide WHAT IT LOOKS LIKE. Every function here accepts
 * either a DB datetime string or a Date, and returns '' for empty input.
 *
 *   fmtDate('2026-08-25 13:30:00')      -> '25 Aug 2026'
 *   fmtTime('2026-08-25 13:30:00')      -> '14:30'   (Europe/London)
 *   fmtDateTime('2026-08-25 13:30:00')  -> '25 Aug 2026 14:30'
 *
 * DISPLAY ONLY. For machine output — sort keys, bucketing, <input type="date">,
 * anything compared or round-tripped — use ymdInZone() below, which is pinned to
 * ISO and never consults the format setting. Routing a sort key through the
 * display family would reorder tables the moment somebody picks a new format.
 */
(function () {
    // Built-in fallbacks, used when a page somehow renders without
    // Tz::scriptTag() (the browser extension, or a page yet to be wired up).
    // These reproduce the pre-#105 output, so an unwired page looks unchanged
    // rather than broken.
    var FALLBACK = {
        dateTemplate: 'DD MON YYYY',
        timeTemplate: 'HH:mi',
        dayMonthTemplate: 'DD MON',
        months: ['January', 'February', 'March', 'April', 'May', 'June',
                 'July', 'August', 'September', 'October', 'November', 'December'],
        monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        weekdays: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        weekdaysShort: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
    };

    function cfg() {
        var c = window.DATE_FORMAT;
        if (!c || !c.dateTemplate) return FALLBACK;
        return c;
    }

    // Coerce a call-site argument (DB string, Date, or null) to a Date.
    function toDate(value) {
        if (value === null || value === undefined || value === '') return null;
        var d = (value instanceof Date) ? value : window.parseUTCDate(value);
        return (d && !isNaN(d.getTime())) ? d : null;
    }

    // Numeric calendar components for a Date, evaluated in the analyst's display
    // zone. Intl does the zone arithmetic only — the locale is pinned to 'en-US'
    // and hourCycle to 'h23' so we always get plain digits and never a '24:30',
    // whatever the browser's own locale is. The digits are then assembled by our
    // own renderer, which is what makes the output identical to PHP's.
    var PART_FMT = null;
    function partsInZone(d) {
        var opts = {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
        };
        if (window.USER_TIMEZONE) opts.timeZone = window.USER_TIMEZONE;
        // Cache the formatter: constructing Intl.DateTimeFormat is the expensive
        // part, and list views call this once per row.
        if (!PART_FMT || PART_FMT.zone !== (window.USER_TIMEZONE || '')) {
            PART_FMT = { zone: window.USER_TIMEZONE || '', fmt: new Intl.DateTimeFormat('en-US', opts) };
        }
        var out = {};
        PART_FMT.fmt.formatToParts(d).forEach(function (p) {
            if (p.type !== 'literal') out[p.type] = parseInt(p.value, 10);
        });
        return {
            year: out.year, month: out.month, day: out.day,
            hour: out.hour % 24, minute: out.minute
        };
    }

    // Components of a NAIVE wall-clock Date exactly as parseNaiveDate built it —
    // no zone interpretation, so "2pm" reads 2pm for every analyst.
    function partsNaive(d) {
        return {
            year: d.getFullYear(), month: d.getMonth() + 1, day: d.getDate(),
            hour: d.getHours(), minute: d.getMinutes()
        };
    }

    // ISO weekday for a y/m/d triple: 1 = Monday .. 7 = Sunday, matching the
    // index order of DateFmt::weekdays(). Computed from the components rather
    // than the Date so it agrees with whichever zone produced them.
    function isoWeekday(p) {
        var day = new Date(Date.UTC(p.year, p.month - 1, p.day)).getUTCDay();
        return day === 0 ? 7 : day;
    }

    function pad2(n) { return (n < 10 ? '0' : '') + n; }

    // The single JS-side renderer — mirrors DateFmt::render(). One regex pass
    // with the longest tokens first, so MONTH is not eaten by MON nor YYYY by
    // YY, and so a substituted month name is never rescanned for tokens.
    var TOKENS = /MONTH|YYYY|MON|DD|MM|YY|mi|HH|D|h|A/g;
    function render(p, template) {
        var c = cfg();
        var hour12 = p.hour % 12; if (hour12 === 0) hour12 = 12;
        // A day number beside the month selects the in-date month form for the
        // languages that inflect — Russian "март" standing alone becomes
        // "5 марта" in a date; Polish "marzec" becomes "5 marca". Mirrors
        // DateFmt::render(). Locales that do not need it publish the same list.
        var monthNames = (String(template).indexOf('D') !== -1 && c.monthsInDate)
            ? c.monthsInDate : c.months;
        return String(template).replace(TOKENS, function (tok) {
            switch (tok) {
                case 'MONTH': return monthNames[p.month - 1];
                case 'YYYY':  return String(p.year);
                case 'MON':   return c.monthsShort[p.month - 1];
                case 'DD':    return pad2(p.day);
                case 'MM':    return pad2(p.month);
                case 'YY':    return pad2(p.year % 100);
                case 'mi':    return pad2(p.minute);
                case 'HH':    return pad2(p.hour);
                case 'D':     return String(p.day);
                case 'h':     return String(hour12);
                case 'A':     return p.hour < 12 ? 'AM' : 'PM';
            }
            return tok;
        });
    }

    // --- UTC-instant formatters (server-stamped timestamps) ------------------

    window.fmtDate = function (value) {
        var d = toDate(value); if (!d) return '';
        return render(partsInZone(d), cfg().dateTemplate);
    };

    window.fmtTime = function (value) {
        var d = toDate(value); if (!d) return '';
        return render(partsInZone(d), cfg().timeTemplate);
    };

    window.fmtDateTime = function (value) {
        var d = toDate(value); if (!d) return '';
        var p = partsInZone(d);
        return render(p, cfg().dateTemplate) + ' ' + render(p, cfg().timeTemplate);
    };

    /** '25 Aug' — compact chips and list rows where the year is implied. */
    window.fmtDayMonth = function (value) {
        var d = toDate(value); if (!d) return '';
        return render(partsInZone(d), cfg().dayMonthTemplate);
    };

    /** 'August 2026' — calendar headers. Always the full month name. */
    window.fmtMonthYear = function (value) {
        var d = toDate(value); if (!d) return '';
        return render(partsInZone(d), 'MONTH YYYY');
    };

    /** 'Tuesday' / 'Tue' */
    window.fmtWeekday = function (value, short) {
        var d = toDate(value); if (!d) return '';
        var c = cfg();
        return (short ? c.weekdaysShort : c.weekdays)[isoWeekday(partsInZone(d)) - 1];
    };

    // --- NAIVE wall-clock formatters (user-entered scheduling values) --------
    // Same output shape as the pair above, so the two are drop-in interchangeable
    // at a call site. See the parseNaiveDate note above for which is which —
    // these must NEVER be pointed at a server-stamped UTC timestamp.

    function naive(value) {
        if (value === null || value === undefined || value === '') return null;
        var d = (value instanceof Date) ? value : window.parseNaiveDate(value);
        return (d && !isNaN(d.getTime())) ? d : null;
    }

    window.fmtNaiveDate = function (value) {
        var d = naive(value); if (!d) return '';
        return render(partsNaive(d), cfg().dateTemplate);
    };

    window.fmtNaiveTime = function (value) {
        var d = naive(value); if (!d) return '';
        return render(partsNaive(d), cfg().timeTemplate);
    };

    window.fmtNaiveDateTime = function (value) {
        var d = naive(value); if (!d) return '';
        var p = partsNaive(d);
        return render(p, cfg().dateTemplate) + ' ' + render(p, cfg().timeTemplate);
    };

    window.fmtNaiveDayMonth = function (value) {
        var d = naive(value); if (!d) return '';
        return render(partsNaive(d), cfg().dayMonthTemplate);
    };

    /** 'Tuesday' / 'Tue' for a naive wall-clock value. */
    window.fmtNaiveWeekday = function (value, short) {
        var d = naive(value); if (!d) return '';
        var c = cfg();
        return (short ? c.weekdaysShort : c.weekdays)[isoWeekday(partsNaive(d)) - 1];
    };

    /** 'August 2026' for a naive wall-clock value — calendar headers. */
    window.fmtNaiveMonthYear = function (value) {
        var d = naive(value); if (!d) return '';
        return render(partsNaive(d), 'MONTH YYYY');
    };

    // --- Escape hatch for genuinely bespoke labels ---------------------------
    // A few places need a shape none of the functions above produce - a calendar
    // week title that compresses "5 - 11 May 2026", say. Before #105 those wrote
    // their own toLocale* call with a hardcoded locale, which is exactly the
    // drift this work removes. These render an arbitrary TOKEN TEMPLATE (see the
    // token list in includes/timezone.php) through the same renderer and the
    // same localised month and weekday names, so a bespoke label still follows
    // the analyst's language.
    //
    // They do NOT follow the analyst's chosen date FORMAT - the caller is
    // choosing the arrangement itself. Use them only where the shape is dictated
    // by the layout rather than by preference, and prefer fmtDate/fmtDateTime
    // everywhere else.
    window.fmtTemplate = function (value, template) {
        var d = toDate(value); if (!d) return '';
        return render(partsInZone(d), template);
    };

    window.fmtNaiveTemplate = function (value, template) {
        var d = naive(value); if (!d) return '';
        return render(partsNaive(d), template);
    };
})();
