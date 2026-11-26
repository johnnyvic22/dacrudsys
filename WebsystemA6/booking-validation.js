(function () {
    "use strict";

    const MAX_TICKETS = 10;
    const WINDOW_DAYS = 90;                 
    const STORAGE_KEY = "mcs-app-state-v1";
    const LEGACY_STORAGE_KEY = "mcs-bookings-v1";
    const SCHEMA_VERSION = 1;


    const TYPE_INFO = {
        REGULAR: { label: "Regular",        price: 350, idLabel: "" },
        STUDENT: { label: "Student",        price: 280, idLabel: "Student ID number" },
        SENIOR:  { label: "Senior Citizen", price: 280, idLabel: "Senior Citizen ID number" },
        PWD:     { label: "PWD",            price: 280, idLabel: "PWD ID number" }
    };

    const FIELDS = [
        { key: "name",       id: "customer-name" },
        { key: "email",      id: "email" },
        { key: "mobile",     id: "mobile" },
        { key: "date",       id: "date" },
        { key: "showtime",   id: "showtime" },
        { key: "tickets",    id: "tickets" },
        { key: "ticketType", id: "type-regular" },
        { key: "concession", id: "concession-id" },
        { key: "seats",      id: "seat-A1" },
        { key: "notes",      id: "notes" },
        { key: "terms",      id: "terms" },
        { key: "movie",      id: "movie-id" }
    ];


    const RULES = [
        { id: "N1", type: "Native",      text: "Name: required, 2-60 characters" },
        { id: "N2", type: "Native",      text: "Email: required, valid format (type=email)" },
        { id: "N3", type: "Native",      text: "Mobile: required, pattern 09XXXXXXXXX or +639XXXXXXXXX" },
        { id: "N4", type: "Native",      text: "Date: required, min = today, max = today + 90 days" },
        { id: "N5", type: "Native",      text: "Showtime: required" },
        { id: "N6", type: "Native",      text: "Tickets: required, whole number 1-10 (min / max / step)" },
        { id: "N7", type: "Native",      text: "Terms checkbox: required" },
        { id: "N8", type: "Native",      text: "Special requests: max 200 characters" },
        { id: "J1", type: "JavaScript",  text: "Name: not whitespace-only; 2+ characters after trimming" },
        { id: "J2", type: "JavaScript",  text: "Name: letters, spaces, apostrophes, hyphens, periods only" },
        { id: "J3", type: "JavaScript",  text: "Email: domain needs a dot and a 2+ letter ending" },
        { id: "J4", type: "JavaScript",  text: "Date: real calendar date inside the booking window (re-checked in JS)" },
        { id: "J5", type: "JavaScript",  text: "Special requests: no < or > characters" },
        { id: "J6", type: "JavaScript",  text: "Concession ID: 5-20 letters, digits or hyphens" },
        { id: "J7", type: "JavaScript",  text: "Movie: must be a valid movie chosen from the Movies page" },
        { id: "C1", type: "Conditional", text: "Concession ID required only for Student / Senior / PWD tickets" },
        { id: "X1", type: "Cross-field", text: "Seats selected must equal number of tickets" },
        { id: "X2", type: "Cross-field", text: "Showtime must be in the future when the date is today" },
        { id: "X3", type: "Cross-field", text: "Date must be on/after the movie's release date" },
        { id: "D1", type: "Duplicate",   text: "Seat already booked for same movie + date + showtime" },
        { id: "D2", type: "Duplicate",   text: "Same email already booked same movie + date + showtime" }
    ];

    
    function isUsableBooking(b) {
        return !!b && typeof b.id === "string" && typeof b.movieId === "string" &&
            typeof b.date === "string" && typeof b.showtime === "string" &&
            Array.isArray(b.seats) && typeof b.status === "string";
    }

    function emptyPayload() {
        return {
            schemaVersion: SCHEMA_VERSION,
            savedAt: new Date().toISOString(),
            bookings: [],
            managementRecords: null,
            preferences: {},
            activeSession: null,
            mockUsers: null,
            auditLog: []
        };
    }

    // All pages share one versioned payload so saving one section does not erase another.
    function readAppPayload() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                if (parsed && parsed.schemaVersion === SCHEMA_VERSION &&
                    typeof parsed.savedAt === "string" && Array.isArray(parsed.bookings) &&
                    Array.isArray(parsed.auditLog)) {
                    return { ...emptyPayload(), ...parsed };
                }
            }
        } catch (error) {
            // Start from safe defaults. Detailed recovery feedback is handled in the next Unit 8 task.
        }

        // Migrate bookings saved by the earlier version of the project.
        try {
            const legacyRaw = localStorage.getItem(LEGACY_STORAGE_KEY);
            if (legacyRaw) {
                const legacy = JSON.parse(legacyRaw);
                if (Array.isArray(legacy)) {
                    const migrated = emptyPayload();
                    migrated.bookings = legacy.filter(isUsableBooking);
                    return migrated;
                }
            }
        } catch (error) {
            // Ignore malformed legacy storage and use safe defaults.
        }
        return emptyPayload();
    }

    function writeAppPayload(payload) {
        try {
            payload.schemaVersion = SCHEMA_VERSION;
            payload.savedAt = new Date().toISOString();
            localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            return true;
        } catch (error) {
            return false;
        }
    }

    function loadSaved() {
        const payload = readAppPayload();
        return payload.bookings.filter(isUsableBooking);
    }

    let saved = [];   // the database checks duplicate seats, so no browser-stored bookings are used here

    function persistSaved(auditEntry) {
        const payload = readAppPayload();
        payload.bookings = saved;
        if (auditEntry) {
            payload.auditLog = Array.isArray(payload.auditLog) ? payload.auditLog : [];
            payload.auditLog.push({
                timestamp: new Date().toISOString(),
                action: auditEntry.action,
                recordId: auditEntry.recordId,
                details: auditEntry.details || ""
            });
            payload.auditLog = payload.auditLog.slice(-500);
        }
        return writeAppPayload(payload);
    }

    function allBookings() {
        return saved.slice();
    }

    function makeContext() {
        return {
            now: new Date(),
            bookings: allBookings(),
            movies: typeof movies !== "undefined" ? movies : {}   // `movies` is defined in booking.html
        };
    }

    const MONTHS = ["january", "february", "march", "april", "may", "june", "july",
        "august", "september", "october", "november", "december"];

    function pad(n) { return String(n).padStart(2, "0"); }

    function toISO(dt) {
        return dt.getFullYear() + "-" + pad(dt.getMonth() + 1) + "-" + pad(dt.getDate());
    }

    function startOfDay(dt) {
        return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());
    }

    function addDays(dt, n) {
        return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate() + n);
    }

    function parseISO(s) {                   // "2026-10-05" -> Date, or null if not a real date
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || "");
        if (!m) return null;
        const y = +m[1], mo = +m[2], d = +m[3];
        const dt = new Date(y, mo - 1, d);
        const real = dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d;
        return real ? dt : null;
    }

    function parseTime(s) {                  // "7:00 PM" -> minutes since midnight, or null
        const m = /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i.exec(s || "");
        if (!m) return null;
        let h = (+m[1]) % 12;
        if (m[3].toUpperCase() === "PM") h += 12;
        return h * 60 + (+m[2]);
    }

    function parseRelease(s) {               // "18 December 2026" -> Date, or null
        const m = /^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/.exec(s || "");
        if (!m) return null;
        const mo = MONTHS.indexOf(m[2].toLowerCase());
        return mo === -1 ? null : new Date(+m[3], mo, +m[1]);
    }

    function normalizeSpaces(s) { return String(s).trim().replace(/\s+/g, " "); }

    function plural(n, word) { return n + " " + word + (n === 1 ? "" : "s"); }

    function collect(root) {
        const val = function (id) {
            const el = root.querySelector("#" + id);
            return el ? el.value : "";
        };
        const type = root.querySelector('input[name="ticketType"]:checked');
        const terms = root.querySelector("#terms");

        return {
            movie: val("movie-id"),
            name: val("customer-name"),                         // raw: whitespace-only must be detectable
            email: val("email").trim().toLowerCase(),
            mobile: val("mobile").trim(),
            date: val("date"),
            showtime: val("showtime"),
            tickets: val("tickets"),
            ticketType: type ? type.value : "REGULAR",
            concession: val("concession-id").trim().toUpperCase(),
            seats: Array.from(root.querySelectorAll('input[name="seat"]:checked')).map(function (c) { return c.value; }),
            notes: val("notes"),
            terms: !!(terms && terms.checked)
        };
    }

    /* Turn a control's native ValidityState into one of OUR messages.
       Note: browsers only raise tooShort/tooLong for user-typed edits, so the
       length attributes are also checked by hand (covers pasted/scripted values). */
    function nativeMessage(el, msgs) {
        if (!el) return null;
        const v = el.validity;
        let state = null;

        if (v.valueMissing) state = "valueMissing";
        else if (v.badInput) state = "badInput";
        else if (v.typeMismatch) state = "typeMismatch";
        else if (v.patternMismatch) state = "patternMismatch";
        else if (v.rangeUnderflow) state = "rangeUnderflow";
        else if (v.rangeOverflow) state = "rangeOverflow";
        else if (v.stepMismatch) state = "stepMismatch";
        else if (el.minLength > 0 && el.value.length > 0 && el.value.length < el.minLength) state = "tooShort";
        else if (el.maxLength >= 0 && el.value.length > el.maxLength) state = "tooLong";

        if (!state) return null;
        return msgs[state] || "This value is not valid.";
    }

    /* ---------------------------------------------------------------------
       THE VALIDATION ENGINE
       --------------------------------------------------------------------- */
    function validateAll(root, ctx) {
        ctx = ctx || makeContext();
        const d = collect(root);
        const errors = [];
        const add = function (field, rule, message) { errors.push({ field: field, rule: rule, message: message }); };
        const $ = function (id) { return root.querySelector("#" + id); };

        const movie = Object.prototype.hasOwnProperty.call(ctx.movies, d.movie) ? ctx.movies[d.movie] : null;
        const dateObj = parseISO(d.date);
        const timeMins = parseTime(d.showtime);
        const today = startOfDay(ctx.now);
        const nTickets = Number(d.tickets);
        const ticketsOk = d.tickets !== "" && Number.isInteger(nTickets) && nTickets >= 1 && nTickets <= MAX_TICKETS;

        
        [
            ["N1", "name", "customer-name", {
                valueMissing: "Enter your full name.",
                tooShort: "Use at least 2 characters.",
                tooLong: "Use at most 60 characters."
            }],
            ["N2", "email", "email", {
                valueMissing: "Enter your email address.",
                typeMismatch: "Enter a valid email address, e.g. name@example.com."
            }],
            ["N3", "mobile", "mobile", {
                valueMissing: "Enter your mobile number.",
                patternMismatch: "Use 09XXXXXXXXX or +639XXXXXXXXX, with no spaces or dashes.",
                tooLong: "Use 09XXXXXXXXX or +639XXXXXXXXX, with no spaces or dashes."
            }],
            ["N4", "date", "date", {
                valueMissing: "Choose a date.",
                badInput: "Enter a complete, valid date.",
                rangeUnderflow: "Choose today or a later date.",
                rangeOverflow: "Bookings open up to " + WINDOW_DAYS + " days ahead."
            }],
            ["N5", "showtime", "showtime", {
                valueMissing: "Select a showtime."
            }],
            ["N6", "tickets", "tickets", {
                valueMissing: "Enter the number of tickets.",
                badInput: "Enter a whole number.",
                rangeUnderflow: "Book at least 1 ticket.",
                rangeOverflow: "You can book at most " + MAX_TICKETS + " tickets at a time.",
                stepMismatch: "Enter a whole number of tickets."
            }],
            ["N7", "terms", "terms", {
                valueMissing: "You must accept the booking terms to continue."
            }],
            ["N8", "notes", "notes", {
                tooLong: "Special requests are limited to 200 characters."
            }]
        ].forEach(function (r) {
            const message = nativeMessage($(r[2]), r[3]);
            if (message) add(r[1], r[0], message);
        });

        /* ---- JavaScript field rules (J1-J7) ---- */
        const name = normalizeSpaces(d.name);

        // J1: whitespace-only text counts as empty; judge length after trimming.
        if (d.name.length > 0 && name.length < 2) {
            add("name", "J1", name.length === 0
                ? "Name cannot be only spaces."
                : "Use at least 2 characters (spaces do not count).");
        }

        // J2: letters (any language), spaces, apostrophes, hyphens, periods.
        if (name && !/^\p{L}[\p{L}\s.'\u2019-]*$/u.test(name)) {
            add("name", "J2", "Use letters only. Numbers and symbols are not allowed in a name.");
        }

        // J3: type=email accepts "maria@example"; require a real domain ending.
        if (d.email && !/^[^\s@]+@([A-Za-z0-9-]+\.)+[A-Za-z]{2,}$/.test(d.email)) {
            add("email", "J3", "Add a full domain after the @, e.g. example.com.");
        }

        // J4: defence in depth. min/max can be bypassed (dev tools, old browsers),
        //     so the date is re-checked here as a real, in-window calendar date.
        if (d.date) {
            if (!dateObj) {
                add("date", "J4", "That is not a real calendar date.");
            } else if (dateObj < today) {
                add("date", "J4", "That date has already passed.");
            } else if (dateObj > addDays(today, WINDOW_DAYS)) {
                add("date", "J4", "Bookings open up to " + WINDOW_DAYS + " days ahead.");
            }
        }

        // J5: keep markup out of free text.
        if (/[<>]/.test(d.notes)) {
            add("notes", "J5", "Please remove the < and > characters from your request.");
        }

        // J6: ID format, only checked when the concession field applies.
        if (d.ticketType !== "REGULAR" && d.concession && !/^[A-Za-z0-9-]{5,20}$/.test(d.concession)) {
            add("concession", "J6", "Use 5-20 letters, numbers or hyphens.");
        }

        // J7: the booking must belong to a real movie.
        if (!movie) {
            add("movie", "J7", "No movie selected. Choose a movie from the Movies page before booking.");
        }

        /* ---- Conditional rule (C1) ---- */
        const typeInfo = TYPE_INFO[d.ticketType];
        if (typeInfo && d.ticketType !== "REGULAR" && !d.concession) {
            add("concession", "C1", "Enter your " + typeInfo.idLabel + " to get the " + typeInfo.label + " price.");
        }

        /* ---- Cross-field rules (X1-X3) ---- */
        // X1: one seat per ticket.
        if (ticketsOk && d.seats.length !== nTickets) {
            add("seats", "X1", d.seats.length === 0
                ? "Select " + plural(nTickets, "seat") + " to match your tickets."
                : "You booked " + plural(nTickets, "ticket") + " but picked " + plural(d.seats.length, "seat") +
                  ". Pick exactly " + nTickets + ".");
        }

        // X2: today's showtime must not have started yet.
        if (dateObj && timeMins !== null && toISO(dateObj) === toISO(ctx.now)) {
            const nowMins = ctx.now.getHours() * 60 + ctx.now.getMinutes();
            if (timeMins <= nowMins) {
                add("showtime", "X2", "That showtime has already started. Choose a later showtime or another date.");
            }
        }

        // X3: no showings before the movie opens.
        if (movie && dateObj) {
            const release = parseRelease(movie.release);
            if (release && dateObj < release) {
                add("date", "X3", movie.title + " opens on " + movie.release + ". Choose that date or later.");
            }
        }

        /* ---- Duplicate rules (D1-D2) ---- */
        if (movie && d.date && d.showtime) {
            const sameShow = ctx.bookings.filter(function (b) {
                return b.status !== "CANCELLED" && b.movieId === d.movie &&
                    b.date === d.date && b.showtime === d.showtime;
            });

            // D1: any chosen seat already taken for this showing.
            const taken = d.seats.filter(function (seat) {
                return sameShow.some(function (b) { return b.seats.indexOf(seat) !== -1; });
            });
            if (taken.length) {
                add("seats", "D1", (taken.length === 1 ? "Seat " : "Seats ") + taken.join(", ") +
                    (taken.length === 1 ? " is" : " are") + " already booked for this showing. Choose different seats.");
            }

            // D2: same customer booking the same showing twice.
            const mine = d.email ? sameShow.filter(function (b) { return b.email && b.email === d.email; }) : [];
            if (mine.length) {
                add("email", "D2", "This email already has a booking for this showing (" + mine[0].id + ").");
            }
        }

        return errors;
    }

   
    const form = document.getElementById("booking-form");
    if (!form) return;                       // not on the booking page

    const summaryBox = document.getElementById("error-summary");
    const summaryTitle = document.getElementById("error-summary-title");
    const summaryList = document.getElementById("error-summary-list");
    const successBox = document.getElementById("booking-success");
    const seatSection = document.getElementById("seat-section");
    const seatCounter = document.getElementById("seat-counter");
    const totalOutput = document.getElementById("est-total");
    const concessionWrap = document.getElementById("concession-wrap");
    const concessionInput = document.getElementById("concession-id");
    const concessionLabel = document.getElementById("concession-label");
    const dateInput = document.getElementById("date");

    const touched = new Set();               // fields whose errors may be shown
    let submitted = false;

    function keyOf(el) {
        if (el.name === "seat") return "seats";
        if (el.name === "ticketType") return "ticketType";
        const f = FIELDS.find(function (x) { return x.id === el.id; });
        return f ? f.key : null;
    }

    function firstErrorPerField(errors) {
        const first = {};
        errors.forEach(function (e) { if (!first[e.field]) first[e.field] = e; });
        return first;
    }

    /* ---- inline errors ---- */
    function renderInline(errors) {
        const first = firstErrorPerField(errors);

        FIELDS.forEach(function (f) {
            const show = touched.has(f.key) && first[f.key];
            const box = form.querySelector('[data-error-for="' + f.key + '"]');
            if (box) box.textContent = show ? first[f.key].message : "";

            if (f.key === "seats") {
                seatSection.classList.toggle("is-invalid", !!show);
            } else if (f.key !== "ticketType") {
                const control = document.getElementById(f.id);
                if (control) control.setAttribute("aria-invalid", show ? "true" : "false");
            }
        });
    }

    /* ---- error summary: every item links to / focuses its control ---- */
    function renderSummary(errors) {
        if (!submitted || errors.length === 0) {
            summaryBox.hidden = true;
            return;
        }
        const first = firstErrorPerField(errors);
        const items = [];

        FIELDS.forEach(function (f) {
            if (!first[f.key]) return;
            const li = document.createElement("li");
            const a = document.createElement("a");
            if (f.key === "movie") {
                a.href = "movies.html";
            } else {
                a.href = "#" + f.id;
                a.dataset.focus = f.key;
            }
            a.textContent = first[f.key].message;
            li.appendChild(a);
            items.push(li);
        });

        summaryTitle.textContent = "Please fix " + plural(items.length, "problem") + " before confirming your booking.";
        summaryList.replaceChildren.apply(summaryList, items);
        summaryBox.hidden = false;
    }

    function focusField(key) {
        const f = FIELDS.find(function (x) { return x.key === key; });
        const el = f && document.getElementById(f.id);
        if (el) el.focus();
    }

    /* ---- live helpers ---- */
    function updateSeatCounter() {
        const picked = form.querySelectorAll('input[name="seat"]:checked').length;
        const n = Number(document.getElementById("tickets").value);
        const ok = Number.isInteger(n) && n >= 1 && n <= MAX_TICKETS;

        if (!ok) {
            seatCounter.textContent = plural(picked, "seat") + " selected. Enter your number of tickets first.";
        } else if (picked > n) {
            seatCounter.textContent = picked + " of " + plural(n, "seat") + " selected. Remove " + (picked - n) + ".";
        } else {
            seatCounter.textContent = picked + " of " + plural(n, "seat") + " selected.";
        }
    }

    function updateTotal() {
        const n = Number(document.getElementById("tickets").value);
        const ok = Number.isInteger(n) && n >= 1 && n <= MAX_TICKETS;
        const info = TYPE_INFO[collect(form).ticketType];
        totalOutput.textContent = ok && info ? "\u20b1" + (n * info.price).toLocaleString() : "\u2014";
    }

    // Conditional field: only exists (and only matters) for discounted ticket types.
    function syncConditional() {
        const type = collect(form).ticketType;
        const info = TYPE_INFO[type];
        const needed = type !== "REGULAR";

        concessionWrap.hidden = !needed;
        concessionInput.disabled = !needed;
        concessionInput.setAttribute("aria-required", needed ? "true" : "false");
        if (needed && info) concessionLabel.textContent = info.idLabel + " *";
        if (!needed) touched.delete("concession");
    }

    function refresh() {
        const errors = validateAll(form);
        renderInline(errors);
        renderSummary(errors);
        updateSeatCounter();
        return errors;
    }

    /* ---- events (delegated, so they survive any re-render) ---- */
    form.addEventListener("focusout", function (e) {
        const key = keyOf(e.target);
        if (!key || key === "seats") return;          // seats are judged on change / submit
        touched.add(key);
        refresh();
    });

    form.addEventListener("change", function (e) {
        const key = keyOf(e.target);
        if (!key) return;

        if (key === "ticketType") syncConditional();
        if (key === "tickets" || key === "ticketType") updateTotal();

        if (key === "seats") {
            // Show seat errors once the selection is complete (or after a submit try),
            // not while the person is still part-way through choosing.
            const n = Number(document.getElementById("tickets").value);
            if (submitted || form.querySelectorAll('input[name="seat"]:checked').length === n) touched.add("seats");
        } else {
            touched.add(key);
        }
        refresh();
    });

    form.addEventListener("input", function (e) {
        const key = keyOf(e.target);
        if (!key) return;
        if (key === "tickets") updateTotal();
        if (touched.has(key) || key === "tickets") refresh();
    });

    summaryList.addEventListener("click", function (e) {
        const link = e.target.closest("a[data-focus]");
        if (!link) return;
        e.preventDefault();
        focusField(link.dataset.focus);
    });

    /* ---- saving ---- */
    function nextBookingId() {
        const nums = allBookings()
            .map(function (b) { return parseInt(String(b.id).replace(/\D/g, ""), 10); })
            .filter(Number.isFinite);
        return "BK-" + (Math.max.apply(null, [1000].concat(nums)) + 1);
    }

    function normalizeMobile(m) {
        return m.indexOf("+63") === 0 ? "0" + m.slice(3) : m;
    }

    function saveBooking() {
        const d = collect(form);
        const ctx = makeContext();
        const movie = ctx.movies[d.movie];
        const info = TYPE_INFO[d.ticketType];
        const now = ctx.now;

        const record = {
            id: nextBookingId(),
            movieId: d.movie,
            movieTitle: movie.title,
            date: d.date,
            showtime: d.showtime,
            seats: d.seats.slice(),
            ticketCount: d.seats.length,
            customerName: normalizeSpaces(d.name),
            email: d.email,
            mobile: normalizeMobile(d.mobile),
            ticketType: d.ticketType,
            concessionId: d.ticketType === "REGULAR" ? "" : d.concession,
            notes: d.notes.trim(),
            pricePerTicket: info.price,
            status: "PENDING",
            createdAt: toISO(now) + "T" + pad(now.getHours()) + ":" + pad(now.getMinutes()) + ":00"
        };

        saved.push(record);
        const stored = persistSaved({ action: "CREATE", recordId: record.id, details: "New booking submitted from the booking form." });

        form.reset();                        // only after a successful save; failures keep input
        touched.clear();
        submitted = false;
        syncConditional();
        updateTotal();
        refresh();

        const title = document.createElement("strong");
        title.textContent = "Booking " + record.id + " saved!";
        const detail = document.createElement("span");
        detail.textContent = " " + record.movieTitle + " on " + record.date + " at " + record.showtime +
            ". Seats: " + record.seats.join(", ") + ". Total: \u20b1" +
            (record.ticketCount * record.pricePerTicket).toLocaleString() + ". Status: Pending." +
            (stored ? "" : " (Your browser blocked saving, so this booking is kept for this visit only.)");
        successBox.replaceChildren(title, detail);
        successBox.hidden = false;
        successBox.focus();
    }

    // Valid form: send the chosen seats to the server (PHP saves it to the database).
    function submitToServer() {
        const picked = Array.from(form.querySelectorAll('input[name="seat"]:checked')).map(function (c) { return c.value; });
        document.getElementById("seats-value").value = picked.join(",");
        form.submit();
    }

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        successBox.hidden = true;
        submitted = true;
        FIELDS.forEach(function (f) { touched.add(f.key); });

        const errors = refresh();
        if (errors.length) {
            summaryBox.focus();              // keyboard / screen-reader users land on the list
            return;                          // nothing is created or changed
        }
        submitToServer();
    });

    /* ======================================================================
       VALIDATION TEST TABLE
       Each case runs on a detached CLONE of the real form, with a fixed "now"
       (today, 3:00 PM) and fixture bookings, so it never disturbs the visitor's
       input or their saved bookings.
       ====================================================================== */
    function setValues(root, p) {
        const set = function (id, v) {
            const el = root.querySelector("#" + id);
            if (el) el.value = v;
        };
        if ("movie" in p) set("movie-id", p.movie);
        if ("name" in p) set("customer-name", p.name);
        if ("email" in p) set("email", p.email);
        if ("mobile" in p) set("mobile", p.mobile);
        if ("date" in p) set("date", p.date);
        if ("showtime" in p) set("showtime", p.showtime);
        if ("tickets" in p) set("tickets", p.tickets);
        if ("concession" in p) set("concession-id", p.concession);
        if ("notes" in p) set("notes", p.notes);
        if ("terms" in p) root.querySelector("#terms").checked = p.terms;
        if ("ticketType" in p) {
            root.querySelectorAll('input[name="ticketType"]').forEach(function (r) { r.checked = r.value === p.ticketType; });
        }
        if ("seats" in p) {
            root.querySelectorAll('input[name="seat"]').forEach(function (c) { c.checked = p.seats.indexOf(c.value) !== -1; });
        }
    }

    function runTests() {
        const real = new Date();
        const fixedNow = new Date(real.getFullYear(), real.getMonth(), real.getDate(), 15, 0, 0);
        const showDate = toISO(addDays(fixedNow, 3));
        const yesterday = toISO(addDays(fixedNow, -1));

        const base = {
            movie: "avgen", name: "Maria Santos", email: "maria.santos@example.com",
            mobile: "09171234567", date: showDate, showtime: "7:00 PM", tickets: "2",
            ticketType: "REGULAR", concession: "", seats: ["A1", "A2"], notes: "", terms: true
        };

        const fixtures = [
            { id: "FX-1", movieId: "avgen", date: showDate, showtime: "7:00 PM", seats: ["B2"], email: "taken@example.com", status: "CONFIRMED" },
            { id: "FX-2", movieId: "avgen", date: showDate, showtime: "7:00 PM", seats: ["C3"], email: "cancelled@example.com", status: "CANCELLED" }
        ];

        const ctx = { now: fixedNow, bookings: fixtures, movies: typeof movies !== "undefined" ? movies : {} };

        const cases = [
            { id: "T01", rule: null, title: "All fields valid (baseline)", patch: {}, expect: null },
            { id: "T02", rule: "N1", title: "Name left empty", patch: { name: "" }, expect: "N1" },
            { id: "T03", rule: "N2", title: "Email \"maria@\" has no domain", patch: { email: "maria@" }, expect: "N2" },
            { id: "T04", rule: "N3", title: "Mobile \"12345\" does not match pattern", patch: { mobile: "12345" }, expect: "N3" },
            { id: "T05", rule: "N4", title: "Date is yesterday (below min)", patch: { date: yesterday }, expect: "N4" },
            { id: "T06", rule: "N5", title: "No showtime selected", patch: { showtime: "" }, expect: "N5" },
            { id: "T07", rule: "N6", title: "11 tickets (above max of 10)", patch: { tickets: "11" }, expect: "N6" },
            { id: "T08", rule: "N7", title: "Terms checkbox not ticked", patch: { terms: false }, expect: "N7" },
            { id: "T09", rule: "N8", title: "Special request of 201 characters", patch: { notes: new Array(202).join("a") }, expect: "N8" },
            { id: "T10", rule: "J1", title: "Name is only spaces", patch: { name: "     " }, expect: "J1" },
            { id: "T11", rule: "J2", title: "Name \"Maria 123\" contains digits", patch: { name: "Maria 123" }, expect: "J2" },
            { id: "T12", rule: "J3", title: "Email \"maria@example\" has no domain ending", patch: { email: "maria@example" }, expect: "J3" },
            {
                id: "T13", rule: "J4", title: "Past date with min/max stripped (simulates bypass)",
                patch: { date: yesterday }, expect: "J4",
                after: function (clone) {
                    const el = clone.querySelector("#date");
                    el.removeAttribute("min");
                    el.removeAttribute("max");
                }
            },
            { id: "T14", rule: "J5", title: "Special request contains <script>", patch: { notes: "<script>alert(1)</script>" }, expect: "J5" },
            { id: "T15", rule: "J6", title: "Student ID \"!!\" has an invalid format", patch: { ticketType: "STUDENT", concession: "!!" }, expect: "J6" },
            { id: "T16", rule: "J7", title: "No movie selected", patch: { movie: "" }, expect: "J7" },
            { id: "T17", rule: "C1", title: "Student ticket with no ID number", patch: { ticketType: "STUDENT", concession: "" }, expect: "C1" },
            { id: "T18", rule: "X1", title: "2 tickets but only 1 seat chosen", patch: { seats: ["A1"] }, expect: "X1" },
            { id: "T19", rule: "X2", title: "Today at 1:00 PM when it is 3:00 PM", patch: { date: toISO(fixedNow), showtime: "1:00 PM" }, expect: "X2" },
            { id: "T20", rule: "X3", title: "Dune: Part Three booked before its release date", patch: { movie: "dnp3" }, expect: "X3" },
            { id: "T21", rule: "D1", title: "Seat B2 already booked for this showing", patch: { seats: ["A1", "B2"] }, expect: "D1" },
            { id: "T22", rule: "D2", title: "Same email already booked this showing", patch: { email: "taken@example.com" }, expect: "D2" },
            { id: "T23", rule: null, title: "Seat C3 only held by a CANCELLED booking is allowed", patch: { seats: ["A1", "C3"] }, expect: null },
            { id: "T24", rule: null, title: "Student ticket with a valid ID number", patch: { ticketType: "STUDENT", concession: "STU-20261234" }, expect: null }
        ];

        const results = cases.map(function (c) {
            const clone = form.cloneNode(true);
            setValues(clone, Object.assign({}, base, c.patch));
            if (c.after) c.after(clone);

            const errors = validateAll(clone, ctx);
            const ruleIds = Array.from(new Set(errors.map(function (e) { return e.rule; })));
            const pass = c.expect === null ? errors.length === 0 : ruleIds.indexOf(c.expect) !== -1;

            return {
                id: c.id,
                rule: c.rule,
                title: c.title,
                expected: c.expect === null ? "No errors" : "Rule " + c.expect + " fires",
                actual: ruleIds.length ? ruleIds.join(", ") : "No errors",
                pass: pass
            };
        });

        // Validation Completeness (%) = passed required rules / total required rules x 100
        const passedRules = RULES.filter(function (rule) {
            const own = results.filter(function (r) { return r.rule === rule.id; });
            return own.length > 0 && own.every(function (r) { return r.pass; });
        }).length;
        const completeness = Math.round((passedRules / RULES.length) * 1000) / 10;

        return { results: results, passedRules: passedRules, totalRules: RULES.length, completeness: completeness };
    }

    function renderTestTable(report) {
        const tbody = document.getElementById("validation-test-body");
        const summary = document.getElementById("validation-test-summary");
        if (!tbody || !summary) return;

        const rows = report.results.map(function (r) {
            const tr = document.createElement("tr");
            [r.id, r.rule || "\u2014", r.title, r.expected, r.actual, r.pass ? "\u2713 PASS" : "\u2717 FAIL"]
                .forEach(function (text) {
                    const td = document.createElement("td");
                    td.textContent = text;
                    tr.appendChild(td);
                });
            return tr;
        });
        tbody.replaceChildren.apply(tbody, rows);

        summary.textContent = "Validation Completeness = (" + report.passedRules + " / " + report.totalRules +
            ") \u00d7 100 = " + report.completeness + "%  |  " +
            report.results.filter(function (r) { return r.pass; }).length + " of " + report.results.length + " test cases passed.";
    }

    function runAndReport() {
        const report = runTests();
        renderTestTable(report);

        console.table(report.results.map(function (r) {
            return { ID: r.id, Rule: r.rule || "-", Case: r.title, Expected: r.expected, Actual: r.actual, Result: r.pass ? "PASS" : "FAIL" };
        }));
        report.results.forEach(function (r) {
            console.assert(r.pass, "VALIDATION TEST " + r.id + " FAILED: " + r.title);
        });
        console.log("Unit 4 validation completeness: " + report.passedRules + "/" + report.totalRules +
            " rules = " + report.completeness + "%");
        return report;
    }

    window.runValidationTests = runAndReport;   // callable from the browser console

    /* ---------------------------------------------------------------------
       INITIALISE
       --------------------------------------------------------------------- */
    const today = startOfDay(new Date());
    dateInput.min = toISO(today);
    dateInput.max = toISO(addDays(today, WINDOW_DAYS));

    form.querySelectorAll('input[name="seat"]').forEach(function (c) {
        c.setAttribute("aria-label", "Seat " + c.value);
    });

    const runButton = document.getElementById("run-validation-tests");
    if (runButton) runButton.addEventListener("click", runAndReport);

    syncConditional();
    updateTotal();
    touched.add("movie");                    // a missing/invalid movie is reported immediately
    refresh();
    runAndReport();
})();