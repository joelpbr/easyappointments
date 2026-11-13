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
 * Clinical record modal component.
 *
 * Shows the record of a patient (details, medical history, allergies) with their visits. Each visit opens its own
 * record in a side panel, whose fields depend on the record template of its treatment (see the
 * Clinical_record_templates library). Saving a silicone or surgery visit can change its price, so the caller is told
 * to reload the calendar.
 */
App.Components.ClinicalRecordModal = (function () {
    const $modal = $('#clinical-record-modal');
    const $title = $('#clinical-record-title');
    const $visits = $('#clinical-visits');
    const $documentType = $('#clinical-document-type');
    const $documentNumber = $('#clinical-document-number');
    const $birthDate = $('#clinical-birth-date');
    const $panel = $('#clinical-visit-panel');
    const $visitTitle = $('#clinical-visit-title');
    const $visitSubtitle = $('#clinical-visit-subtitle');
    const $orthosis = $('#clinical-visit-orthosis');
    const $surgeryPrice = $('#clinical-visit-surgery-price');
    const $observations = $('#clinical-visit-observations');

    let customerId = null;
    let currentAppointmentId = null;
    let visitAppointmentId = null;
    let onChange = () => {};
    let savedPatient = '';
    let savedVisit = '';
    let closingConfirmed = false;

    /**
     * Open the record of a patient.
     *
     * @param {Number} patientId Patient ID.
     * @param {Object} [options]
     * @param {Number} [options.appointmentId] Visit from which the record is opened, marked in the list.
     * @param {Function} [options.onChange] Called after a visit record changed the price of its visit.
     */
    function open(patientId, options = {}) {
        customerId = Number(patientId);
        currentAppointmentId = options.appointmentId ? Number(options.appointmentId) : null;
        onChange = options.onChange || (() => {});

        App.Http.ClinicalRecords.findPatient(customerId).done((response) => {
            fillPatient(response);
            closingConfirmed = false;
            bootstrap.Modal.getOrCreateInstance($modal[0]).show();
        });
    }

    /**
     * Fill the patient part of the record.
     *
     * @param {Object} response
     */
    function fillPatient(response) {
        const customer = response.customer;

        $title.text(
            lang('clinical_record') + ': ' + [customer.first_name, customer.last_name].filter(Boolean).join(' '),
        );

        $modal.find('[data-customer]').each((index, element) => {
            $(element).val(customer[element.dataset.customer] ?? '');
        });

        $modal.find('.modal-body > .row [data-record]').each((index, element) => {
            $(element).val(response.record[element.dataset.record] ?? '');
        });

        $documentType.val(response.document_type || '');
        $documentNumber.val(response.document_number || '');

        $birthDate[0]._flatpickr.setDate(response.record.birth_date || null, false, 'Y-m-d');

        renderVisits(response.appointments);

        savedPatient = JSON.stringify(getPatientData());
    }

    /**
     * Render the list of visits, the latest first.
     *
     * @param {Array} appointments
     */
    function renderVisits(appointments) {
        $visits.empty();

        if (!appointments.length) {
            $('<div/>', {class: 'text-muted', text: lang('clinical_no_visits')}).appendTo($visits);

            return;
        }

        appointments.forEach((appointment) => {
            const isCurrent = appointment.id === currentAppointmentId;
            const price = appointment.price !== null ? ' (' + formatPrice(appointment.price) + ')' : '';

            $('<button/>', {
                'type': 'button',
                'class':
                    'list-group-item list-group-item-action d-flex align-items-center gap-2 ea-clinical-visit' +
                    (isCurrent ? ' active' : ''),
                'data-appointment-id': appointment.id,
                'html': [
                    $('<div/>', {
                        class: 'flex-grow-1',
                        html: [
                            $('<div/>', {
                                html: [
                                    $('<strong/>', {text: appointment.service_name + price}),
                                    isCurrent
                                        ? $('<span/>', {
                                              class: 'badge bg-light text-dark ms-2',
                                              text: lang('clinical_current_visit'),
                                          })
                                        : null,
                                ],
                            }),
                            $('<small/>', {
                                text: [
                                    App.Utils.Date.format(
                                        appointment.start_datetime,
                                        vars('date_format'),
                                        vars('time_format'),
                                        true,
                                    ),
                                    appointment.provider_name,
                                    App.Utils.CalendarEventPopover?.getStatusName(appointment.status) ||
                                        appointment.status,
                                ]
                                    .filter(Boolean)
                                    .join(' · '),
                            }),
                        ],
                    }),
                    appointment.has_record
                        ? $('<i/>', {'class': 'fas fa-file-medical', 'title': lang('clinical_has_notes')})
                        : null,
                    $('<span/>', {class: 'btn btn-sm btn-outline-primary', text: lang('clinical_open_visit')}),
                ],
            }).appendTo($visits);
        });
    }

    /**
     * Format a price like the calendar cards ("30€", "27,5€").
     *
     * @param {Number} price
     *
     * @return {String}
     */
    function formatPrice(price) {
        return Number(price).toLocaleString(vars('language_code'), {maximumFractionDigits: 2}) + '€';
    }

    /**
     * Get the patient part of the record from the form.
     *
     * @return {Object}
     */
    function getPatientData() {
        const customer = {};
        const record = {};

        $modal.find('[data-customer]').each((index, element) => {
            customer[element.dataset.customer] = $(element).val();
        });

        $modal.find('.modal-body > .row [data-record]').each((index, element) => {
            record[element.dataset.record] = $(element).val();
        });

        const birthDate = $birthDate[0]._flatpickr.selectedDates[0];

        record.birth_date = birthDate ? moment(birthDate).format('YYYY-MM-DD') : '';

        return {
            customer,
            record,
            document_type: $documentType.val(),
            document_number: $documentNumber.val(),
        };
    }

    /**
     * Save the patient part of the record.
     */
    function savePatient() {
        const data = getPatientData();

        App.Http.ClinicalRecords.savePatient(customerId, data).done(() => {
            savedPatient = JSON.stringify(data);
            App.Layouts.Backend.displayNotification(lang('clinical_record_saved'));
            onChange();
        });
    }

    /**
     * Open the record of a visit in the side panel.
     *
     * @param {Number} appointmentId
     */
    function openVisit(appointmentId) {
        const show = () =>
            App.Http.ClinicalRecords.findAppointment(appointmentId).done((response) => {
                fillVisit(response);
                bootstrap.Offcanvas.getOrCreateInstance($panel[0]).show();
            });

        if (isVisitDirty()) {
            confirmClose(show);

            return;
        }

        show();
    }

    /**
     * Fill the side panel with the record of a visit, showing only the fields of its template.
     *
     * @param {Object} response
     */
    function fillVisit(response) {
        const appointment = response.appointment;
        const fields = response.fields;

        visitAppointmentId = appointment.id;

        $visitTitle.text(appointment.service_name);
        $visitSubtitle.text(
            App.Utils.Date.format(appointment.start_datetime, vars('date_format'), vars('time_format'), true) +
                (appointment.price !== null ? ' · ' + formatPrice(appointment.price) : ''),
        );

        $panel.find('[data-section="feet"]').prop('hidden', !response.has_feet);

        $panel.find('[data-field]').each((index, element) => {
            const field = element.dataset.field;
            const visible = field === 'surgery_price' ? response.template === 'surgery' : fields.includes(field);

            element.hidden = !visible;
        });

        $panel.find('[data-visit]').each((index, element) => {
            $(element).val(response.record[element.dataset.visit] ?? '');
        });

        $surgeryPrice.val(
            response.template === 'surgery' && appointment.price !== null ? String(appointment.price) : '',
        );

        // A visit with only observations gets a large box.
        $observations.attr('rows', response.template === 'notes' ? 12 : 4);

        $panel.find('.offcanvas-body').scrollTop(0);

        savedVisit = JSON.stringify(getVisitData());
    }

    /**
     * Get the visit record from the side panel.
     *
     * @return {Object}
     */
    function getVisitData() {
        const record = {};

        $panel.find('[data-field]:not([hidden]) [data-visit]').each((index, element) => {
            record[element.dataset.visit] = $(element).val();
        });

        return {
            record,
            surgeryPrice: $panel.find('[data-field="surgery_price"]').prop('hidden') ? '' : $surgeryPrice.val(),
        };
    }

    /**
     * Save the visit record of the side panel.
     */
    function saveVisit() {
        const data = getVisitData();

        App.Http.ClinicalRecords.saveAppointment(visitAppointmentId, data.record, data.surgeryPrice).done(() => {
            savedVisit = JSON.stringify(data);
            App.Layouts.Backend.displayNotification(lang('clinical_visit_saved'));
            bootstrap.Offcanvas.getOrCreateInstance($panel[0]).hide();

            // The list shows the new price and which visits have notes.
            App.Http.ClinicalRecords.findPatient(customerId).done((response) => renderVisits(response.appointments));

            onChange();
        });
    }

    /**
     * Whether the patient part has changes that are not saved.
     *
     * @return {Boolean}
     */
    function isPatientDirty() {
        return customerId !== null && JSON.stringify(getPatientData()) !== savedPatient;
    }

    /**
     * Whether the visit panel has changes that are not saved.
     *
     * @return {Boolean}
     */
    function isVisitDirty() {
        return $panel.hasClass('show') && JSON.stringify(getVisitData()) !== savedVisit;
    }

    /**
     * Ask before losing the changes that are not saved.
     *
     * @param {Function} close Called when the user chooses to close anyway.
     */
    function confirmClose(close) {
        App.Utils.Message.show(lang('clinical_record'), lang('clinical_unsaved_changes'), [
            {
                text: lang('cancel'),
                className: 'btn btn-secondary',
                click: (event, messageModal) => messageModal.hide(),
            },
            {
                text: lang('clinical_close_anyway'),
                className: 'btn btn-danger',
                click: (event, messageModal) => {
                    messageModal.hide();
                    close();
                },
            },
        ]);
    }

    /**
     * Initialize the component.
     */
    function initialize() {
        if (!$modal.length) {
            return;
        }

        App.Utils.UI.initializeDatePicker($birthDate, {maxDate: 'today'});

        $('#clinical-record-save').on('click', savePatient);
        $('#clinical-visit-save').on('click', saveVisit);

        $visits.on('click', '.ea-clinical-visit', (event) => {
            openVisit(Number(event.currentTarget.dataset.appointmentId));
        });

        // The visit panel lives inside the record, so it closes with it.
        $panel.on('hide.bs.offcanvas', (event) => {
            if (isVisitDirty() && !closingConfirmed) {
                event.preventDefault();
                confirmClose(() => {
                    savedVisit = JSON.stringify(getVisitData());
                    bootstrap.Offcanvas.getOrCreateInstance($panel[0]).hide();
                });
            }
        });

        $modal.on('hide.bs.modal', (event) => {
            if (event.target !== $modal[0] || closingConfirmed) {
                return;
            }

            if (isPatientDirty() || isVisitDirty()) {
                event.preventDefault();
                confirmClose(() => {
                    closingConfirmed = true;
                    bootstrap.Modal.getOrCreateInstance($modal[0]).hide();
                });
            }
        });

        $modal.on('hidden.bs.modal', (event) => {
            if (event.target !== $modal[0]) {
                return;
            }

            bootstrap.Offcanvas.getOrCreateInstance($panel[0]).hide();
            customerId = null;
        });

        // The silicone orthosis shows the price that the visit will have.
        $orthosis.on('change', () => {
            const price = $orthosis.find('option:selected').data('price');

            if (price !== undefined) {
                $visitSubtitle.text($visitSubtitle.text().replace(/ · [^·]*€$/, '') + ' · ' + formatPrice(price));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        open,
    };
})();
