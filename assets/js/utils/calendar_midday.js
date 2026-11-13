/** Collapsible shared lunch break for the reception calendar. */
App.Utils.CalendarMidday = (() => {
    // A break that overlaps this window (in minutes of the day) is the midday break.
    const MIDDAY_FROM = 12 * 60;
    const MIDDAY_TO = 16 * 60;
    const expanded = new Set();
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

    function schedule() {
        if (pending) return;
        pending = true;
        requestAnimationFrame(() => {
            pending = false;
            document.querySelectorAll('.ea-midday-toggle').forEach((button) => {
                if (!button.middayColumn?.isConnected) button.remove();
            });
            document.querySelectorAll('#calendar .date-column').forEach(update);
        });
    }

    function update(column) {
        const providers = [...column.querySelectorAll('.provider-column')];
        const calendars = providers.map((provider) => $(provider).find('.calendar-wrapper').data('fullCalendar'));
        if (!calendars.length || calendars.some((calendar) => !calendar)) return;

        // Only fold a break shared by every displayed provider, keeping their time axes aligned.
        let ranges = [[0, 1440]];
        calendars.forEach((calendar) => {
            const breaks = calendar
                .getEvents()
                .filter((event) => event.extendedProps.middayBreak && event.end)
                .map((event) => [minutes(event.start), minutes(event.end)])
                .filter(([start, end]) => start < end);
            ranges = ranges
                .flatMap(([start, end]) => breaks.map(([a, b]) => [Math.max(start, a), Math.min(end, b)]))
                .filter(([start, end]) => end > start);
        });
        const slot = Number(vars('calendar_slot_minutes')) || 15;
        const range = ranges
            .map(([a, b]) => [Math.ceil(a / slot) * slot, Math.floor(b / slot) * slot])
            .filter(([a, b]) => b - a >= slot * 2)
            .sort((a, b) => b[1] - b[0] - (a[1] - a[0]))[0];
        let button = $(column).data('middayButton');
        let folded = false;
        if (range && calendars.every((calendar) => calendar.view.type === 'timeGridDay')) {
            const date = new Date($(column).data('date'));
            const from = new Date(date);
            from.setHours(0, range[0], 0, 0);
            const to = new Date(date);
            to.setHours(0, range[1], 0, 0);
            // Use the unfiltered response too: hiding a treatment must never hide its visit by folding.
            const occupied = providers.some((provider, index) => {
                const id = Number($(provider).data('provider').id);
                return (
                    ($(provider).data('middayAppointments') || []).some(
                        (visit) =>
                            Number(visit.id_users_provider) === id &&
                            overlap(
                                moment(visit.start_datetime).toDate(),
                                moment(visit.end_datetime).toDate(),
                                from,
                                to,
                            ),
                    ) ||
                    calendars[index]
                        .getEvents()
                        .some(
                            (event) =>
                                event.display !== 'background' &&
                                !event.extendedProps.data?.workingPlanException &&
                                event.start &&
                                overlap(event.start, event.end || event.start, from, to),
                        )
                );
            });
            const key = date.toDateString() + ':' + range.join('-');
            if (occupied) expanded.add(key);
            folded = !occupied && !expanded.has(key);
            if (!button) {
                button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-outline-secondary ea-midday-toggle';
                document.querySelector('#calendar-toolbar .calendar-header').append(button);
                button.middayColumn = column;
                $(column).data('middayButton', button);
            }
            button.disabled = occupied;
            button.setAttribute('aria-expanded', String(!folded));
            const hint = occupied
                ? lang('midday_has_appointments')
                : folded
                  ? lang('open_midday')
                  : lang('fold_midday');
            // Every day has its own button in the toolbar, so they name the day when several are shown.
            const day =
                document.querySelectorAll('#calendar .date-column').length > 1 ? moment(date).format('ddd') : '';
            button.textContent = [lang('midday'), day, folded ? '▾' : '▴'].filter(Boolean).join(' ');
            button.title = [moment(date).format('DD/MM/YYYY'), clock(range[0]) + '–' + clock(range[1]), hint].join(
                ' · ',
            );
            button.setAttribute('aria-label', button.title);
            button.onclick = () => {
                if (folded) expanded.add(key);
                else expanded.delete(key);
                update(column);
            };
            $(column).data('middayFold', folded ? {from, to} : null);
        } else {
            button?.remove();
            $(column).removeData('middayButton');
            $(column).data('middayFold', null);
        }
        calendars.forEach((calendar) => {
            let changed = false;
            calendar.el.querySelectorAll('.fc-timegrid-slots tr').forEach((row) => {
                const time = row.querySelector('[data-time]')?.getAttribute('data-time');
                if (!time) return;
                const [hour, minute] = time.split(':').map(Number);
                const collapse = folded && hour * 60 + minute >= range[0] && hour * 60 + minute < range[1];
                if (row.classList.contains('ea-midday-folded') !== Boolean(collapse)) changed = true;
                row.classList.toggle('ea-midday-folded', Boolean(collapse));
            });
            // FullCalendar must recalculate hit coordinates after changing the slot heights.
            if (changed) {
                calendar.updateSize();
                calendar.setOption('slotLaneClassNames', folded ? ['ea-midday-layout-folded'] : []);
            }
        });
    }

    function allows(provider, info) {
        const range = $(provider).closest('.date-column').data('middayFold');
        return !range || !overlap(info.start, info.end, range.from, range.to);
    }

    return {schedule, allows, isMiddayBreak};
})();
