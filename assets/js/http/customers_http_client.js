/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Customers HTTP client.
 *
 * This module implements the customers related HTTP requests.
 */
App.Http.Customers = (function () {
    /**
     * Save (create or update) a customer.
     *
     * @param {Object} customer
     *
     * @return {Object}
     */
    function save(customer) {
        return customer.id ? update(customer) : store(customer);
    }

    /**
     * Create a customer.
     *
     * @param {Object} customer
     *
     * @return {Object}
     */
    function store(customer) {
        const url = App.Utils.Url.siteUrl('customers/store');

        const data = {
            csrf_token: vars('csrf_token'),
            customer: customer,
        };

        return $.post(url, data);
    }

    /**
     * Update a customer.
     *
     * @param {Object} customer
     *
     * @return {Object}
     */
    function update(customer) {
        const url = App.Utils.Url.siteUrl('customers/update');

        const data = {
            csrf_token: vars('csrf_token'),
            customer: customer,
        };

        return $.post(url, data);
    }

    /**
     * Delete a customer.
     *
     * @param {Number} customerId
     *
     * @return {Object}
     */
    function destroy(customerId) {
        const url = App.Utils.Url.siteUrl('customers/destroy');

        const data = {
            csrf_token: vars('csrf_token'),
            customer_id: customerId,
        };

        return $.post(url, data);
    }

    /**
     * Search customers by keyword.
     *
     * @param {String} keyword
     * @param {Number} [limit]
     * @param {Number} [offset]
     * @param {String} [orderBy]
     *
     * @return {Object}
     */
    function search(keyword, limit = null, offset = null, orderBy = null) {
        const url = App.Utils.Url.siteUrl('customers/search');

        const data = {
            csrf_token: vars('csrf_token'),
            keyword,
            limit,
            offset,
            order_by: orderBy || undefined,
        };

        return $.post(url, data);
    }

    /**
     * Find the patients that match a specific search: the first name and the last name, or six digits of the phone.
     *
     * @param {String} keyword
     *
     * @return {Object} {specific, patients (up to 10, few fields), more}
     */
    function quickSearch(keyword) {
        const url = App.Utils.Url.siteUrl('customers/quick_search');

        const data = {
            csrf_token: vars('csrf_token'),
            keyword,
        };

        return $.post(url, data);
    }

    /**
     * Find the patients that might be the same person as a new patient (same phone number or same name).
     *
     * @param {Object} customer Patient data (first_name, last_name, phone_number and, when editing, id).
     *
     * @return {Object}
     */
    function duplicates(customer) {
        const url = App.Utils.Url.siteUrl('customers/duplicates');

        const data = {
            csrf_token: vars('csrf_token'),
            customer: {
                id: customer.id || '',
                first_name: customer.first_name || '',
                last_name: customer.last_name || '',
                phone_number: customer.phone_number || '',
            },
        };

        return $.post(url, data);
    }

    /**
     * Find a customer.
     *
     * @param {Number} customerId
     *
     * @return {Object}
     */
    function find(customerId) {
        const url = App.Utils.Url.siteUrl('customers/find');

        return $.get(url, {customer_id: customerId});
    }

    return {
        quickSearch,
        duplicates,
        save,
        store,
        update,
        destroy,
        search,
        find,
    };
})();
