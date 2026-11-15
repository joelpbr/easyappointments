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
 * Calendar time labels.
 *
 * The times on the left of a day calendar choose the time of a new appointment: a click takes that time, and pressing
 * on a time and dragging up or down to another one takes the whole range, highlighted meanwhile. The range follows the
 * rows of the times, also when the mouse goes over the grid beside them, and never crosses the midday break.
 */
App.Utils.CalendarTimeLabels = (function () {
    const LABEL = '.fc-timegrid-slot-label[data-time]';

    /**
     * Get the row of the times that is at a height of the screen, or the first or last one when it is above or below.
     *
     * @param {Array} labels Time labels of the calendar, from the earliest.
     * @param {Number} y
     *
     * @return {HTMLElement}
     */
    function labelAt(labels, y) {
        return (
            labels.find((label) => {
                const rect = label.getBoundingClientRect();

                return y >= rect.top && y < rect.bottom;
            }) || (y < labels[0].getBoundingClientRect().top ? labels[0] : labels[labels.length - 1])
        );
    }

    /**
     * Listen to the time labels of some calendars.
     *
     * @param {jQuery} $container Element that holds the calendars.
     * @param {Function} getContext Called with the pressed label: {fullCalendar, day, scope} of its calendar (scope:
     * the element of that calendar), or null when its times choose nothing (e.g. a week view).
     * @param {Function} onChoose Called with the chosen range ({start, end, allDay, jsEvent}) and the calendar.
     */
    function listen($container, getContext, onChoose) {
        $container.on('mousedown', LABEL, (event) => {
            if (event.button !== 0) {
                return;
            }

            const context = getContext(event.currentTarget);

            if (!context) {
                return;
            }

            // No text selection while dragging.
            event.preventDefault();

            const labels = [...context.scope.querySelectorAll(LABEL)];
            const slotMinutes = Number(vars('calendar_slot_minutes')) || 15;
            const first = event.currentTarget;
            let last = first;

            const $highlight = $('<div/>', {class: 'ea-time-range'}).appendTo('body');

            const range = () => (labels.indexOf(last) < labels.indexOf(first) ? [last, first] : [first, last]);

            const paint = () => {
                const [from, to] = range();
                const scopeRect = context.scope.getBoundingClientRect();
                const top = from.getBoundingClientRect().top;

                $highlight.css({
                    top,
                    left: scopeRect.left,
                    width: scopeRect.width,
                    height: to.getBoundingClientRect().bottom - top,
                });
            };

            paint();

            $(document).on('mousemove.eaTimeLabels', (moveEvent) => {
                last = labelAt(labels, moveEvent.clientY);
                paint();
            });

            $(document).one('mouseup.eaTimeLabels', () => {
                $(document).off('mousemove.eaTimeLabels');
                $highlight.remove();

                const [from, to] = range();
                const day = moment(context.day).startOf('day');
                const info = {
                    start: day.clone().add(moment.duration(from.dataset.time)).toDate(),
                    end: day.clone().add(moment.duration(to.dataset.time)).add(slotMinutes, 'minutes').toDate(),
                    allDay: false,
                    jsEvent: event,
                };

                if (App.Utils.CalendarMidday.allows(info)) {
                    onChoose(info, context.fullCalendar);
                }
            });
        });
    }

    return {listen};
})();
