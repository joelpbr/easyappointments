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
 * Clinical records HTTP client.
 *
 * This module implements the clinical records related HTTP requests.
 */
App.Http.ClinicalRecords = (function () {
    /**
     * Get the record of a patient, with their visits.
     *
     * @param {Number} customerId
     *
     * @return {Object}
     */
    function findPatient(customerId) {
        const url = App.Utils.Url.siteUrl('clinical_records/patient');

        return $.get(url, {customer_id: customerId});
    }

    /**
     * Save the record of a patient.
     *
     * @param {Number} customerId
     * @param {Object} data With "customer", "document_type", "document_number" and "record".
     *
     * @return {Object}
     */
    function savePatient(customerId, data) {
        const url = App.Utils.Url.siteUrl('clinical_records/save_patient');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            customer_id: customerId,
            ...data,
        });
    }

    /**
     * Get the record of a visit.
     *
     * @param {Number} appointmentId
     *
     * @return {Object}
     */
    function findAppointment(appointmentId) {
        const url = App.Utils.Url.siteUrl('clinical_records/appointment');

        return $.get(url, {appointment_id: appointmentId});
    }

    /**
     * Save the record of a visit.
     *
     * @param {Number} appointmentId
     * @param {Object} record
     * @param {String} surgeryPrice Price typed in a surgery record ('' to keep the price of the visit).
     *
     * @return {Object}
     */
    function saveAppointment(appointmentId, record, surgeryPrice) {
        const url = App.Utils.Url.siteUrl('clinical_records/save_appointment');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            appointment_id: appointmentId,
            record,
            surgery_price: surgeryPrice,
        });
    }

    return {
        findPatient,
        savePatient,
        findAppointment,
        saveAppointment,
    };
})();
