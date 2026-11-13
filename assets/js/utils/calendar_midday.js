/** Collapsible shared lunch break for the reception calendar. */
App.Utils.CalendarMidday = (() => {
    // A break that overlaps this window (in minutes of the day) is the midday break.
    const MIDDAY_FROM = 12 * 60;
    const MIDDAY_TO = 16 * 60;
    let open = false;
    let fold = null;
    let appliedFold = '';
    let pending = false;
    const minutes = (date) => date.getHours() * 60 + date.getMinutes();
    const overlap = (start, end, from, to) => start < to && end > from;
    const clock = (value) =>
        String(Math.floor(value / 60)).padStart(2, '0') + ':' + String(value % 60).padStart(2, '0');

    /**
     * Check whether a working plan break is the midday break.
     *
     * @param {Object} breakPeriod Break with 'HH:mm' start and end.
     *
     * @return {Boolean}
     */
    function isMiddayBreak(breakPeriod) {
        const toMinutes = (time) => Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));

        return overlap(toMinutes(breakPeriod.start), toMinutes(breakPeriod.end), MIDDAY_FROM, MIDDAY_TO);
    }

    /**
     * Update the fold on the next frame, once for all the columns that changed in the meantime.
     */
    function schedule() {
        if (pending) {
            return;
        }

        pending = true;

        requestAnimationFrame(() => {
            pending = false;
            update();
        });
    }

    /**
     * Get the minutes range of the midday break shared by every column, aligned to the calendar grid.
     *
     * @param {Array} calendars FullCalendar instances.
     *
     * @return {Array|undefined} [from, to] or undefined when there is no common break of at least two slots.
     */
    function getSharedRange(calendars) {
        let ranges = [[0, 1440]];

        calendars.forEach((calendar) => {
            const breaks = calendar
                .getEvents()
                .filter((event) => event.extendedProps.middayBreak && event.end)
                .map((event) => [minutes(event.start), minutes(event.end)]);

            ranges = ranges
                .flatMap(([start, end]) => breaks.map(([a, b]) => [Math.max(start, a), Math.min(end, b)]))
                .filter(([start, end]) => end > start);
        });

        const slot = Number(vars('calendar_slot_minutes')) || 15;

        return ranges
            .map(([a, b]) => [Math.ceil(a / slot) * slot, Math.floor(b / slot) * slot])
            .filter(([a, b]) => b - a >= slot * 2)
            .sort((a, b) => b[1] - b[0] - (a[1] - a[0]))[0];
    }

    /**
     * Check whether a provider column has an appointment, an unavailability or a blocked period in a range.
     *
     * The unfiltered appointments are checked too: hiding a treatment must never hide its visit by folding.
     *
     * @param {HTMLElement} providerColumn
     * @param {Array} range [from, to] in minutes of the day.
     *
     * @return {Boolean}
     */
    function isOccupied(providerColumn, range) {
        const $providerColumn = $(providerColumn);
        const date = new Date($providerColumn.closest('.date-column').data('date'));
        const from = new Date(date);
        const to = new Date(date);

        from.setHours(0, range[0], 0, 0);
        to.setHours(0, range[1], 0, 0);

        const providerId = Number($providerColumn.data('provider').id);

        const hasAppointments = ($providerColumn.data('middayAppointments') || []).some(
            (appointment) =>
                Number(appointment.id_users_provider) === providerId &&
                overlap(
                    moment(appointment.start_datetime).toDate(),
                    moment(appointment.end_datetime).toDate(),
                    from,
                    to,
                ),
        );

        return (
            hasAppointments ||
            $providerColumn
                .find('.calendar-wrapper')
                .data('fullCalendar')
                .getEvents()
                .some(
                    (event) =>
                        event.display !== 'background' &&
                        !event.extendedProps.data?.workingPlanException &&
                        event.start &&
                        overlap(event.start, event.end || event.start, from, to),
                )
        );
    }

    /**
     * Fold or unfold the midday break of every column and update the toolbar button.
     *
     * All the columns share one vertical scroll, so the break is folded in all of them or in none, keeping the hours
     * aligned.
     */
    function update() {
        const providerColumns = [...document.querySelectorAll('#calendar .provider-column')];
        const calendars = providerColumns.map((column) => $(column).find('.calendar-wrapper').data('fullCalendar'));
        const toolbar = document.querySelector('#calendar-toolbar .calendar-header');
        let button = document.querySelector('.ea-midday-toggle');

        if (!toolbar || calendars.some((calendar) => !calendar)) {
            return;
        }

        // Columns in list mode have no hours: the break is folded in the calendar columns only.
        const gridColumns = providerColumns.filter((column, index) => calendars[index].view.type === 'timeGridDay');
        const gridCalendars = calendars.filter((calendar) => calendar.view.type === 'timeGridDay');
        const range = gridCalendars.length ? getSharedRange(gridCalendars) : undefined;

        fold = null;

        if (range) {
            const occupied = gridColumns.some((column) => isOccupied(column, range));
            const folded = !occupied && !open;
            const hint = occupied
                ? lang('midday_has_appointments')
                : folded
                  ? lang('open_midday')
                  : lang('fold_midday');

            if (!button) {
                button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-outline-secondary ea-midday-toggle';
                button.addEventListener('click', () => {
                    open = !open;
                    update();
                });
            }

            toolbar.append(button);
            button.disabled = occupied;
            button.textContent = lang('midday') + ' ' + (folded ? '▾' : '▴');
            button.title = clock(range[0]) + '–' + clock(range[1]) + ' · ' + hint;
            button.setAttribute('aria-label', button.title);
            button.setAttribute('aria-expanded', String(!folded));

            fold = folded ? range : null;
        } else {
            button?.remove();
        }

        applyFold(gridCalendars);
    }

    /**
     * Fold the rows of the break with a style sheet that selects them by their time.
     *
     * The rows are not changed: FullCalendar draws them again whenever a column changes (e.g. from list to calendar
     * mode), which used to drop the fold and make the button flicker. A style keeps applying to the new rows.
     *
     * @param {Array} calendars FullCalendar instances in calendar (time grid) mode.
     */
    function applyFold(calendars) {
        const key = fold ? fold.join('-') : '';
        let style = document.getElementById('ea-midday-fold-style');

        if (!style) {
            style = document.createElement('style');
            style.id = 'ea-midday-fold-style';
            document.head.append(style);
        }

        if (key !== appliedFold) {
            const slot = Number(vars('calendar_slot_minutes')) || 15;
            const rows = [];

            for (let time = fold?.[0] ?? 0; fold && time < fold[1]; time += slot) {
                rows.push('#calendar .fc-timegrid-slots tr:has(> td[data-time="' + clock(time) + ':00"])');
            }

            style.textContent = rows.length
                ? rows.map((row) => row + ' > td').join(',\n') +
                  ' { height: 2px !important; min-height: 0 !important; padding: 0 !important; border-top: 0;' +
                  ' border-bottom: 0; line-height: 0; pointer-events: none; }\n' +
                  rows.map((row) => row + ' .fc-timegrid-slot-label-frame').join(',\n') +
                  ' { display: none; }'
                : '';

            appliedFold = key;

            // FullCalendar must measure the slots again, so that clicks and drags land on the right time.
            calendars.forEach((calendar) => calendar.updateSize());
        }
    }

    /**
     * Check whether a selection or a moved event stays out of the folded break.
     *
     * @param {Object} info FullCalendar selection or event drop info.
     *
     * @return {Boolean}
     */
    function allows(info) {
        return !fold || !overlap(minutes(info.start), minutes(info.end), fold[0], fold[1]);
    }

    return {schedule, allows, isMiddayBreak};
})();
