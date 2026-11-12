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
 * Calendar past time.
 *
 * Shades the time that has already passed (previous days and today until now) in the time grid views, so the free
 * slots that can still be booked stand out in white. The shade moves forward every minute.
 */
App.Utils.CalendarPastTime = (function () {
    const EVENT_ID = 'past-time';
    const UPDATE_INTERVAL = 60000; // Milliseconds

    /**
     * Get the past time event of a calendar view.
     *
     * @param {Object} view FullCalendar view.
     *
     * @return {Array} The background event, or nothing when the view is not a time grid or has no past time.
     */
    function events(view) {
        const now = new Date();

        if (!view.type.startsWith('timeGrid') || now <= view.activeStart) {
            return [];
        }

        return [
            {
                id: EVENT_ID,
                start: view.activeStart,
                end: now < view.activeEnd ? now : view.activeEnd,
                display: 'background',
                editable: false,
                className: 'fc-past-time',
            },
        ];
    }

    /**
     * Move the past time of a calendar to the current time.
     *
     * @param {Object} fullCalendar FullCalendar instance.
     */
    function update(fullCalendar) {
        // The event lives outside the event sources, so reloading the appointments keeps a single copy of it.
        fullCalendar.getEvents().forEach((event) => event.id === EVENT_ID && event.remove());

        events(fullCalendar.view).forEach((pastTime) => fullCalendar.addEvent(pastTime));
    }

    /**
     * Keep the past time of the calendars up to date.
     *
     * @param {Function} getCalendars Returns the FullCalendar instances of the page.
     */
    function start(getCalendars) {
        setInterval(() => getCalendars().forEach(update), UPDATE_INTERVAL);
    }

    return {
        update,
        start,
    };
})();
