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
 * Calendar blocked periods.
 *
 * Turns the blocked periods (holidays, closures, vacations, absences...) into background events with their reason as
 * label, in grey tones that never match a treatment color: dark grey when the clinic is closed, grey for vacations and
 * a lighter striped grey for other absences.
 */
App.Utils.CalendarBlockedPeriods = (function () {
    const CLOSURE_TYPES = ['holiday_national', 'holiday_regional', 'holiday_local', 'clinic_closure', 'bridge'];

    /**
     * Get the visual group of a blocked period type.
     *
     * @param {String} type
     *
     * @return {String} "closure", "vacation" or "absence".
     */
    function group(type) {
        if (CLOSURE_TYPES.includes(type)) {
            return 'closure';
        }

        return type === 'vacation' ? 'vacation' : 'absence';
    }

    /**
     * Create the background events of the blocked periods.
     *
     * @param {Array} blockedPeriods Occurrences from the server, with provider_ids (empty for the whole clinic).
     * @param {Number|null} providerId Only the periods that apply to this provider (null: all of them).
     *
     * @return {Array} FullCalendar events.
     */
    function events(blockedPeriods, providerId = null) {
        return (blockedPeriods || [])
            .filter(
                (blockedPeriod) =>
                    !providerId ||
                    !blockedPeriod.provider_ids?.length ||
                    blockedPeriod.provider_ids.map(Number).includes(Number(providerId)),
            )
            .map((blockedPeriod) => ({
                title: blockedPeriod.name,
                start: moment(blockedPeriod.start_datetime).toDate(),
                end: moment(blockedPeriod.end_datetime).toDate(),
                display: 'background',
                editable: false,
                className: 'fc-blocked-period fc-blocked-' + group(blockedPeriod.type),
                extendedProps: {data: blockedPeriod},
            }));
    }

    return {
        events,
    };
})();
