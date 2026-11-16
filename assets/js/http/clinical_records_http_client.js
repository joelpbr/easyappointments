/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

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
     * @param {Array|null} markers Every pathology placed on the foot diagrams of the visit (null: no diagrams).
     *
     * @return {Object} {price, markers}
     */
    function saveAppointment(appointmentId, record, surgeryPrice, markers = null) {
        const url = App.Utils.Url.siteUrl('clinical_records/save_appointment');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            appointment_id: appointmentId,
            record,
            surgery_price: surgeryPrice,
            // As JSON, so that an empty list (every marker removed) is sent too.
            markers: markers === null ? undefined : JSON.stringify(markers),
        });
    }

    /**
     * Get the documents a visit can generate: the catalog of the clinic and what its records already say.
     *
     * @param {Number} appointmentId
     *
     * @return {Object} {groups, templates, defaults, professionals, current_professional, provider_name, allergies}
     */
    function findDocumentOptions(appointmentId) {
        const url = App.Utils.Url.siteUrl('clinical_records/document_options');

        return $.get(url, {appointment_id: appointmentId});
    }

    /**
     * Generate a document of a visit and keep it in the history of the patient.
     *
     * @param {Number} appointmentId
     * @param {Object} data With "document_type", "professional", "reviewed" and "fields".
     *
     * @return {Object} {document_id}
     */
    function generateDocument(appointmentId, data) {
        const url = App.Utils.Url.siteUrl('clinical_records/generate_document');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            appointment_id: appointmentId,
            ...data,
        });
    }

    /**
     * Get a page of the saved documents of a patient, the latest first.
     *
     * @param {Number} customerId
     * @param {Number} beforeId The last document of the previous page (0 for the first page).
     *
     * @return {Object} {documents, has_more}
     */
    function findDocumentHistory(customerId, beforeId = 0) {
        const url = App.Utils.Url.siteUrl('clinical_records/document_history');

        return $.get(url, {customer_id: customerId, before_id: beforeId});
    }

    /**
     * Remove a saved document of a patient.
     *
     * @param {Number} customerId
     * @param {Number} documentId
     *
     * @return {Object}
     */
    function deleteDocument(customerId, documentId) {
        const url = App.Utils.Url.siteUrl('clinical_records/delete_document');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            customer_id: customerId,
            document_id: documentId,
        });
    }

    /**
     * Address that shows the PDF of a saved document.
     *
     * @param {Number} documentId
     *
     * @return {String}
     */
    function savedDocumentUrl(documentId) {
        return (
            App.Utils.Url.siteUrl('clinical_records/saved_document') + '?document_id=' + encodeURIComponent(documentId)
        );
    }

    return {
        findPatient,
        savePatient,
        findAppointment,
        saveAppointment,
        findDocumentOptions,
        generateDocument,
        findDocumentHistory,
        deleteDocument,
        savedDocumentUrl,
    };
})();
