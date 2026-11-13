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
 * Calendar amounts.
 *
 * Formats the prices of the appointments ("30€", "45,50€") and adds up those of a provider in a day, shown next to
 * their name in the split view: cancelled appointments, no-shows and any status that frees the slot do not count.
 */
App.Utils.CalendarDayTotal = (function () {
    const number = new Intl.NumberFormat('es-ES', {maximumFractionDigits: 2, useGrouping: 'always'});

    /**
     * Format an amount in euros, without decimals when it has none (e.g. "30€", "1.080€", "45,5€").
     *
     * @param {Number|String|null} amount
     *
     * @return {String}
     */
    function format(amount) {
        return number.format(Number(amount || 0)) + '€';
    }

    /**
     * Check whether an appointment counts for the totals.
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
     * Add up the prices of some appointments, leaving out those that do not count.
     *
     * @param {Array} appointments
     *
     * @return {Number}
     */
    function sum(appointments) {
        return (appointments || [])
            .filter(counts)
            .reduce((total, appointment) => total + Number(appointment.price || 0), 0);
    }

    return {
        format,
        sum,
    };
})();
