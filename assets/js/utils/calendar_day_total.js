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
 * Calendar day total.
 *
 * Shows in the toolbar the sum of the prices of the appointments displayed, for each day: cancelled appointments,
 * no-shows and any status that frees the slot do not count.
 */
App.Utils.CalendarDayTotal = (function () {
    const $dayTotal = $('#day-total');
    const money = new Intl.NumberFormat('es-ES', {style: 'currency', currency: 'EUR', useGrouping: 'always'});

    /**
     * Check whether an appointment counts for the total.
     *
     * @param {Object} appointment
     *
     * @return {Boolean}
     */
    function counts(appointment) {
        if (Number(appointment.is_unavailability) || appointment.status === 'no_show') {
            return false;
        }

        const status = (vars('appointment_statuses') || []).find((item) => item.code === appointment.status);

        return !status?.frees_slot;
    }

    /**
     * Show the totals of some appointments.
     *
     * @param {Array} appointments Appointments of the displayed period, with price, status and start_datetime.
     * @param {Function|null} filter Optional extra condition (e.g. only the displayed providers).
     */
    function show(appointments, filter = null) {
        const totals = {};

        (appointments || [])
            .filter((appointment) => counts(appointment) && (!filter || filter(appointment)))
            .forEach((appointment) => {
                const day = appointment.start_datetime.slice(0, 10);

                totals[day] = (totals[day] || 0) + Number(appointment.price || 0);
            });

        const days = Object.keys(totals).sort();
        const sum = days.reduce((total, day) => total + totals[day], 0);

        $dayTotal.prop('hidden', !$dayTotal.length);
        $dayTotal.text(lang('day_total') + ': ' + money.format(sum));
        $dayTotal.attr(
            'title',
            days.map((day) => moment(day).format('ddd D/M') + ': ' + money.format(totals[day])).join('\n'),
        );
    }

    return {
        show,
    };
})();
