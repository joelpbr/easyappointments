/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Mobile agenda page (see the Mobile controller).
 *
 * The appointments of a day, one below the other, from the calendar endpoint that already limits them to what the user
 * may see. Plain JavaScript, so that the page stays light on a phone. It only reads: tapping an appointment shows its
 * details with buttons to call or message the patient.
 */
(function () {
    const STORAGE_PROVIDER = 'ea-mobile-provider';
    const REFRESH_SECONDS = 60;

    const $ = (id) => document.getElementById(id);

    const providers = vars('providers') || [];
    const statuses = vars('appointment_statuses') || [];
    const twelveHours = vars('time_format') === 'regular';

    let day = startOfDay(new Date());
    let providerId = readProvider();
    let appointments = [];
    let blockedPeriods = [];
    let request = 0;

    /**
     * @param {Date} date
     *
     * @return {Date} The same day at 00:00.
     */
    function startOfDay(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    /**
     * @param {Date} date
     *
     * @return {String} YYYY-MM-DD in local time.
     */
    function isoDate(date) {
        const pad = (number) => String(number).padStart(2, '0');

        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    /**
     * Read a "YYYY-MM-DD HH:mm:ss" date of the server as local time.
     *
     * @param {String} value
     *
     * @return {Date}
     */
    function parseDateTime(value) {
        const [date, time = '00:00:00'] = String(value).split(' ');
        const [year, month, dayOfMonth] = date.split('-').map(Number);
        const [hours, minutes] = time.split(':').map(Number);

        return new Date(year, month - 1, dayOfMonth, hours, minutes);
    }

    /**
     * @param {Date} date
     *
     * @return {String} The time in the format of the settings.
     */
    function formatTime(date) {
        const minutes = String(date.getMinutes()).padStart(2, '0');

        if (!twelveHours) {
            return String(date.getHours()).padStart(2, '0') + ':' + minutes;
        }

        const hours = date.getHours() % 12 || 12;

        return hours + ':' + minutes + (date.getHours() < 12 ? ' AM' : ' PM');
    }

    /**
     * @param {String} uri
     *
     * @return {String}
     */
    function siteUrl(uri) {
        return vars('base_url') + (vars('index_page') ? '/' + vars('index_page') : '') + '/' + uri;
    }

    /**
     * The provider chosen on this phone, when it still exists.
     *
     * @return {Number|null}
     */
    function readProvider() {
        try {
            const id = Number(localStorage.getItem(STORAGE_PROVIDER));

            return providers.some((provider) => provider.id === id) ? id : null;
        } catch (error) {
            return null;
        }
    }

    function saveProvider() {
        try {
            if (providerId) {
                localStorage.setItem(STORAGE_PROVIDER, String(providerId));
            } else {
                localStorage.removeItem(STORAGE_PROVIDER);
            }
        } catch (error) {
            // Without storage the choice is not remembered.
        }
    }

    /**
     * Show the chosen day: "Today", "Tomorrow"... and the date.
     */
    function renderDay() {
        const today = startOfDay(new Date());
        const offset = Math.round((day - today) / 86400000);
        const relative = {'-1': lang('mobile_yesterday'), 0: lang('today'), 1: lang('tomorrow')}[offset];
        const weekday = lang(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][day.getDay()]);
        const month = lang(
            [
                'january',
                'february',
                'march',
                'april',
                'may',
                'june',
                'july',
                'august',
                'september',
                'october',
                'november',
                'december',
            ][day.getMonth()],
        );

        $('mobile-day-relative').textContent = relative ? relative + ' · ' + weekday : weekday;
        $('mobile-day-date').textContent = day.getDate() + ' ' + month.toLowerCase() + ' ' + day.getFullYear();
        $('mobile-date').value = isoDate(day);
    }

    /**
     * The providers that have appointments on the chosen day.
     *
     * @return {Array}
     */
    function dayProviders() {
        return providers.filter((provider) =>
            appointments.some((appointment) => Number(appointment.id_users_provider) === provider.id),
        );
    }

    /**
     * The chosen provider, when they have appointments on the chosen day; otherwise everyone is shown (the choice is
     * kept for the days when they do).
     *
     * @return {Number|null}
     */
    function activeProvider() {
        return dayProviders().some((provider) => provider.id === providerId) ? providerId : null;
    }

    /**
     * The midday break of the chosen day, from the working plan of the clinic: the break between 12:00 and 16:00, as
     * in the calendar (CalendarMidday).
     *
     * @return {{start: Date, end: Date}|null}
     */
    function middayBreak() {
        let plan = vars('company_working_plan');

        try {
            plan = typeof plan === 'string' ? JSON.parse(plan) : plan;
        } catch (error) {
            return null;
        }

        const weekday = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][day.getDay()];
        const toMinutes = (time) => Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
        const midday = (plan?.[weekday]?.breaks || []).find(
            (breakPeriod) => toMinutes(breakPeriod.start) < 16 * 60 && toMinutes(breakPeriod.end) > 12 * 60,
        );

        if (!midday) {
            return null;
        }

        const at = (time) => new Date(day.getFullYear(), day.getMonth(), day.getDate(), 0, toMinutes(time));

        return {start: at(midday.start), end: at(midday.end)};
    }

    /**
     * The provider chips: "Everyone" and one per provider with appointments that day (only when there are two or
     * more of them).
     */
    function renderProviders() {
        const container = $('mobile-providers');
        const shown = dayProviders();
        const active = activeProvider();

        container.hidden = shown.length < 2;
        container.textContent = '';

        [{id: null, name: lang('mobile_everyone')}, ...shown].forEach((provider) => {
            const chip = document.createElement('button');

            chip.type = 'button';
            chip.className = 'ea-mobile-chip' + (provider.id === active ? ' active' : '');
            chip.textContent = provider.name;
            chip.setAttribute('aria-pressed', String(provider.id === active));
            chip.addEventListener('click', () => {
                providerId = provider.id;
                saveProvider();
                renderProviders();
                renderList();
            });

            container.append(chip);
        });
    }

    /**
     * @param {Object} appointment
     *
     * @return {Object|undefined} The status of the appointment.
     */
    function statusOf(appointment) {
        return statuses.find((status) => status.code === appointment.status);
    }

    /**
     * The name of a person: first name and last name.
     *
     * @param {Object|null} person
     *
     * @return {String}
     */
    function fullName(person) {
        return person ? [person.first_name, person.last_name].filter(Boolean).join(' ') : '';
    }

    /**
     * The appointments of the chosen day and provider, by time.
     *
     * @return {Array}
     */
    function visibleAppointments() {
        const active = activeProvider();

        return appointments
            .filter((appointment) => !active || Number(appointment.id_users_provider) === active)
            .sort((a, b) => parseDateTime(a.start_datetime) - parseDateTime(b.start_datetime));
    }

    /**
     * Draw the list of the day.
     */
    function renderList() {
        const list = $('mobile-list');
        const items = visibleAppointments();
        const active = items.filter((appointment) => !statusOf(appointment)?.frees_slot);
        const showProvider = !activeProvider() && dayProviders().length > 1;

        list.textContent = '';

        // Blocked periods of the day, as a grey line at their time.
        const blocks = blockedPeriods.map((period) => ({
            start: parseDateTime(period.start_datetime),
            end: parseDateTime(period.end_datetime),
            name: period.name || lang('blocked_period'),
        }));

        // The midday break separates the morning from the afternoon, when the day has appointments.
        const midday = items.length ? middayBreak() : null;

        const entries = [
            ...items.map((appointment) => ({start: parseDateTime(appointment.start_datetime), appointment})),
            ...blocks.map((block) => ({start: block.start < day ? day : block.start, block})),
            ...(midday ? [{start: midday.start, midday}] : []),
        ].sort((a, b) => a.start - b.start || Boolean(b.midday) - Boolean(a.midday));

        entries.forEach((entry) => {
            const item = document.createElement('li');

            if (entry.midday) {
                item.className = 'ea-mobile-midday';
                item.textContent =
                    formatTime(entry.midday.start) + ' – ' + formatTime(entry.midday.end) + ' · ' + lang('midday_button');
                list.append(item);

                return;
            }

            if (entry.block) {
                item.className = 'ea-mobile-block';
                item.innerHTML = '<i class="fas fa-ban"></i>';
                item.append(
                    document.createTextNode(
                        ' ' +
                            (entry.block.start < day ? '' : formatTime(entry.block.start)) +
                            (entry.block.start < day ? '' : '–') +
                            formatTime(entry.block.end) +
                            ' · ' +
                            entry.block.name,
                    ),
                );
                list.append(item);

                return;
            }

            const appointment = entry.appointment;
            const status = statusOf(appointment);
            const start = parseDateTime(appointment.start_datetime);
            const end = parseDateTime(appointment.end_datetime);
            const button = document.createElement('button');

            // Done: it has already ended or it is marked as done, striped like the ended appointments of the calendar.
            const done = end <= new Date() || appointment.status === 'completed';

            item.className =
                'ea-mobile-item' +
                (status?.frees_slot ? ' ea-mobile-item-cancelled' : '') +
                (done ? ' ea-mobile-item-done' : '');
            button.type = 'button';
            button.className = 'ea-mobile-card';

            const time = document.createElement('span');
            time.className = 'ea-mobile-time';
            time.innerHTML = '<strong></strong><small></small>';
            time.firstChild.textContent = formatTime(start);
            time.lastChild.textContent = formatTime(end);

            const body = document.createElement('span');
            body.className = 'ea-mobile-card-body';

            const name = document.createElement('strong');
            name.className = 'ea-mobile-patient';
            name.textContent = fullName(appointment.customer) || '—';

            const meta = document.createElement('span');
            meta.className = 'ea-mobile-meta';
            meta.textContent = [appointment.service?.name, showProvider ? fullName(appointment.provider) : null]
                .filter(Boolean)
                .join(' · ');

            body.append(name, meta);

            if (status && status.code !== 'scheduled') {
                const badge = document.createElement('span');
                badge.className = 'ea-mobile-status';
                badge.textContent = status.name;
                body.append(badge);
            }

            button.append(time, body);
            button.addEventListener('click', () => openSheet(appointment));
            item.append(button);
            list.append(item);
        });

        $('mobile-empty').hidden = items.length > 0;
        $('mobile-summary').textContent = summary(active);
    }

    /**
     * From when to when there are appointments in the morning and in the afternoon, e.g. "Morning 10:00–12:15 ·
     * Afternoon 15:00–18:30". The morning ends at the midday break, or at 14:00 when the day has none.
     *
     * @param {Array} items The appointments that take their time (not cancelled).
     *
     * @return {String}
     */
    function summary(items) {
        const midday = middayBreak();
        const split = midday ? midday.start : new Date(day.getFullYear(), day.getMonth(), day.getDate(), 14);

        const range = (label, part) => {
            if (!part.length) {
                return null;
            }

            const start = Math.min(...part.map((appointment) => parseDateTime(appointment.start_datetime)));
            const end = Math.max(...part.map((appointment) => parseDateTime(appointment.end_datetime)));

            return label + ' ' + formatTime(new Date(start)) + '–' + formatTime(new Date(end));
        };

        return [
            range(
                lang('mobile_morning'),
                items.filter((appointment) => parseDateTime(appointment.start_datetime) < split),
            ),
            range(
                lang('mobile_afternoon'),
                items.filter((appointment) => parseDateTime(appointment.start_datetime) >= split),
            ),
        ]
            .filter(Boolean)
            .join(' · ');
    }

    /**
     * Show the details of an appointment in the bottom sheet.
     *
     * @param {Object} appointment
     */
    function openSheet(appointment) {
        const content = $('mobile-sheet-content');
        const customer = appointment.customer || {};
        const status = statusOf(appointment);
        const start = parseDateTime(appointment.start_datetime);
        const end = parseDateTime(appointment.end_datetime);
        const phone = String(customer.phone_number || '').trim();

        content.textContent = '';

        const title = document.createElement('h2');
        title.textContent = fullName(customer) || '—';

        const when = document.createElement('p');
        when.className = 'ea-mobile-sheet-when';
        when.textContent = formatTime(start) + '–' + formatTime(end) + ' · ' + $('mobile-day-date').textContent;

        content.append(title, when);

        if (phone) {
            const digits = phone.replace(/[^\d+]/g, '');
            // WhatsApp needs the number with its country code and no +; Spanish numbers of 9 digits get 34.
            const whatsapp = digits.startsWith('+')
                ? digits.slice(1)
                : digits.startsWith('00')
                  ? digits.slice(2)
                  : digits.length === 9
                    ? '34' + digits
                    : digits;

            const actions = document.createElement('div');
            actions.className = 'ea-mobile-actions';
            actions.innerHTML =
                '<a class="btn btn-primary" id="mobile-call"><i class="fas fa-phone me-2"></i></a>' +
                '<a class="btn btn-success" id="mobile-whatsapp" target="_blank" rel="noopener">' +
                '<i class="fab fa-whatsapp me-2"></i></a>';
            const call = actions.querySelector('#mobile-call');
            const message = actions.querySelector('#mobile-whatsapp');
            const who = (fullName(customer) || phone) + ' (' + phone + ')';

            call.href = 'tel:' + digits;
            call.append(lang('mobile_call'));
            message.href = 'https://wa.me/' + whatsapp;
            message.append(lang('mobile_whatsapp'));

            // Both ask first.
            call.addEventListener('click', (event) => {
                event.preventDefault();
                confirmContact(lang('mobile_confirm_call').replace('%s', who), lang('mobile_call'), () => {
                    window.location.href = call.href;
                });
            });

            message.addEventListener('click', (event) => {
                event.preventDefault();
                confirmContact(lang('mobile_confirm_whatsapp').replace('%s', who), lang('mobile_whatsapp'), () => {
                    window.open(message.href, '_blank', 'noopener');
                });
            });

            content.append(actions);
        }

        const details = document.createElement('dl');
        details.className = 'ea-mobile-details';

        const add = (label, value) => {
            if (!value) {
                return;
            }

            const term = document.createElement('dt');
            const description = document.createElement('dd');

            term.textContent = label;
            description.textContent = value;
            details.append(term, description);
        };

        add(lang('phone_number'), phone);
        add(lang('service'), appointment.service?.name);
        add(lang('provider'), fullName(appointment.provider));
        add(lang('status'), status?.name);
        add(lang('appointment_comment'), appointment.notes);
        add(lang('administrative_notes'), customer.notes);

        content.append(details);

        const sheet = $('mobile-sheet');

        if (typeof sheet.showModal === 'function') {
            sheet.showModal();
        } else {
            sheet.setAttribute('open', '');
        }
    }

    /**
     * Ask before calling or messaging a patient. "No" is the main button and has the focus, so the safe choice is
     * the easy one.
     *
     * @param {String} question
     * @param {String} action Text of the button that goes ahead.
     * @param {Function} onConfirm
     */
    function confirmContact(question, action, onConfirm) {
        const dialog = $('mobile-confirm');
        const yes = $('mobile-confirm-yes');
        const no = $('mobile-confirm-no');

        $('mobile-confirm-text').textContent = question;
        yes.textContent = action;

        const close = () => {
            yes.onclick = null;
            no.onclick = null;

            if (typeof dialog.close === 'function') {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        };

        no.onclick = close;
        yes.onclick = () => {
            close();
            onConfirm();
        };

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }

        no.focus();
    }

    function closeSheet() {
        const sheet = $('mobile-sheet');

        if (typeof sheet.close === 'function') {
            sheet.close();
        } else {
            sheet.removeAttribute('open');
        }
    }

    /**
     * Load the appointments of the chosen day.
     */
    async function load() {
        const current = ++request;
        const body = new URLSearchParams({
            csrf_token: vars('csrf_token'),
            start_date: isoDate(day),
            end_date: isoDate(day),
        });

        document.body.classList.add('ea-mobile-loading');

        try {
            const response = await fetch(siteUrl('calendar/get_calendar_appointments_for_table_view'), {
                method: 'POST',
                body,
                credentials: 'same-origin',
            });

            // The session ended: log in again.
            if (response.status === 401 || response.redirected) {
                window.location.href = siteUrl('login');
                return;
            }

            const data = await response.json();

            if (current !== request) {
                return;
            }

            if (!data || !Array.isArray(data.appointments)) {
                throw new Error('Unexpected response');
            }

            const dayStart = day;
            const dayEnd = new Date(day.getTime() + 86400000);

            appointments = data.appointments.filter((appointment) => !Number(appointment.is_unavailability));
            blockedPeriods = (data.blocked_periods || []).filter(
                (period) => parseDateTime(period.start_datetime) < dayEnd && parseDateTime(period.end_datetime) > dayStart,
            );
            $('mobile-error').hidden = true;
            renderProviders();
            renderList();
        } catch (error) {
            if (current === request) {
                $('mobile-error').hidden = false;
            }
        } finally {
            if (current === request) {
                document.body.classList.remove('ea-mobile-loading');
            }
        }
    }

    /**
     * @param {Date} date
     */
    function goTo(date) {
        day = startOfDay(date);
        appointments = [];
        blockedPeriods = [];
        renderDay();
        renderProviders();
        renderList();
        load();
        window.scrollTo({top: 0});
    }

    function initialize() {
        renderDay();
        renderProviders();

        $('mobile-previous').addEventListener('click', () => goTo(new Date(day.getTime() - 86400000 + 3600000)));
        $('mobile-next').addEventListener('click', () => goTo(new Date(day.getTime() + 86400000 + 3600000)));
        $('mobile-date').addEventListener('change', (event) => {
            if (event.target.value) {
                const [year, month, dayOfMonth] = event.target.value.split('-').map(Number);
                goTo(new Date(year, month - 1, dayOfMonth));
            }
        });
        $('mobile-sheet-close').addEventListener('click', closeSheet);

        // Tapping outside the sheet closes it.
        $('mobile-sheet').addEventListener('click', (event) => {
            if (event.target === event.currentTarget) {
                closeSheet();
            }
        });

        // Swiping left or right changes the day.
        let touchStart = null;

        document.querySelector('.ea-mobile-main').addEventListener(
            'touchstart',
            (event) => {
                touchStart = {x: event.touches[0].clientX, y: event.touches[0].clientY};
            },
            {passive: true},
        );

        document.querySelector('.ea-mobile-main').addEventListener(
            'touchend',
            (event) => {
                if (!touchStart) {
                    return;
                }

                const dx = event.changedTouches[0].clientX - touchStart.x;
                const dy = event.changedTouches[0].clientY - touchStart.y;

                touchStart = null;

                if (Math.abs(dx) > 70 && Math.abs(dx) > Math.abs(dy) * 1.5) {
                    goTo(new Date(day.getTime() + (dx < 0 ? 1 : -1) * 86400000 + 3600000));
                }
            },
            {passive: true},
        );

        // Fresh data every minute and when coming back to the page.
        setInterval(() => document.visibilityState === 'visible' && load(), REFRESH_SECONDS * 1000);
        document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && load());

        load();
    }

    document.addEventListener('DOMContentLoaded', initialize);
})();
