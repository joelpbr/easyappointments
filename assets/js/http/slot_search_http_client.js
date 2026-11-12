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
 * Slot search HTTP client.
 *
 * This module implements the slot search related HTTP requests.
 */
App.Http.SlotSearch = (function () {
    /**
     * Search the free slots.
     *
     * @param {Object} criteria Search criteria (service_id, duration, provider_ids, preferred_provider_id, date_from,
     * date_to, time_from, time_to, preferred_time, margin, weekdays).
     *
     * @return {jQuery.jqXHR}
     */
    function search(criteria) {
        const url = App.Utils.Url.siteUrl('slot_search/search');

        // The errors are shown inside the search form, not in the generic error dialog.
        return $.ajax({url, method: 'POST', data: {csrf_token: vars('csrf_token'), ...criteria}, global: false});
    }

    return {
        search,
    };
})();
