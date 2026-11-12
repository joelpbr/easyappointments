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
 * Calendar event content.
 *
 * Renders the appointment blocks of both calendar views: patient and treatment on the first line, which is the one
 * that stays visible in short appointments, then the time, the duration and the status when it is not the default
 * one. The text color is black or white, whichever contrasts more with the treatment color.
 */
App.Utils.CalendarEventContent = (function () {
    const moment = window.moment;

    const DARK_TEXT = '#1f2937';
    const LIGHT_TEXT = '#ffffff';
    const DEFAULT_STATUS = 'scheduled';

    /**
     * Get the relative luminance of a hex color (WCAG 2).
     *
     * @param {String} hex Color such as "#4a90d9".
     *
     * @return {Number|null} Luminance between 0 and 1, or null for an invalid color.
     */
    function getLuminance(hex) {
        const match = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());

        if (!match) {
            return null;
        }

        const [r, g, b] = [0, 2, 4].map((offset) => {
            const channel = parseInt(match[1].substring(offset, offset + 2), 16) / 255;

            return channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
        });

        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    }

    /**
     * Get the text color with the best contrast on the provided background color.
     *
     * @param {String} backgroundColor Hex color.
     *
     * @return {String} Dark or light text color.
     */
    function getTextColor(backgroundColor) {
        const luminance = getLuminance(backgroundColor);

        if (luminance === null) {
            return DARK_TEXT;
        }

        const darkContrast = (luminance + 0.05) / (getLuminance(DARK_TEXT) + 0.05);
        const lightContrast = 1.05 / (luminance + 0.05);

        return darkContrast >= lightContrast ? DARK_TEXT : LIGHT_TEXT;
    }

    /**
     * Render the content of a calendar event (FullCalendar "eventContent" hook).
     *
     * @param {Object} arg FullCalendar content argument.
     *
     * @return {Object|Boolean} The custom content for appointments, true for the default rendering of other events.
     */
    function render(arg) {
        const data = arg.event.extendedProps?.data;

        if (!data || !data.customer || Number(data.is_unavailability)) {
            return true;
        }

        const patient = [data.customer.first_name, data.customer.last_name].filter(Boolean).join(' ');
        const treatment = data.service_name || data.service?.name || '';
        const start = moment(arg.event.start);
        const end = moment(arg.event.end);
        const timeFormat = vars('time_format') === 'regular' ? 'h:mm a' : 'HH:mm';

        const meta = [start.format(timeFormat) + '–' + end.format(timeFormat), end.diff(start, 'minutes') + ' min'];

        if (Number(data.is_first_visit) === 1) {
            meta.push(lang('first_visit_short'));
        }

        if (data.status && data.status !== DEFAULT_STATUS) {
            meta.push(App.Utils.CalendarEventPopover.getStatusName(data.status));
        }

        const $content = $('<div/>', {
            'class': 'ea-event',
            'title': [patient, treatment, meta.join(' · ')].filter(Boolean).join('\n'),
            'html': [
                $('<div/>', {
                    'class': 'ea-event-line',
                    'html': [
                        $('<strong/>', {'text': patient}),
                        treatment ? $('<span/>', {'text': ' · ' + treatment}) : null,
                    ],
                }),
                $('<div/>', {
                    'class': 'ea-event-line ea-event-meta',
                    'text': meta.join(' · '),
                }),
            ],
        });

        return {domNodes: [$content[0]]};
    }

    return {
        getTextColor,
        render,
    };
})();
