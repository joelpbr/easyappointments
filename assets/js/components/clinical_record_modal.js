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
 * Shows the record of a patient in two tabs: details (with the medical history and allergies) and visits. The visits tab
 * lists the visits on the left and shows the record of the chosen one on the right, with the fields of the record
 * template of its treatment (see the Clinical_record_templates library). Saving a silicone or surgery visit can change
 * its price, so the caller is told to reload the calendar.
 */
App.Components.ClinicalRecordModal = (function () {
    const $modal = $('#clinical-record-modal');
    const $title = $('#clinical-record-title');
    const $summary = $('#clinical-record-summary');
    const $allergiesAlert = $('#clinical-record-allergies-alert');
    const $visits = $('#clinical-visits');
    const $visitsCount = $('#clinical-visits-count');
    const $documentType = $('#clinical-document-type');
    const $documentNumber = $('#clinical-document-number');
    const $birthDate = $('#clinical-birth-date');
    const $patientSave = $('#clinical-record-save');
    const $visitEmpty = $('#clinical-visit-empty');
    const $visitDetail = $('#clinical-visit-detail');
    const $visitTitle = $('#clinical-visit-title');
    const $visitSubtitle = $('#clinical-visit-subtitle');
    const $orthosis = $('#clinical-visit-orthosis');
    const $surgeryPrice = $('#clinical-visit-surgery-price');
    const $observations = $('#clinical-visit-observations');

    let customerId = null;
    let currentAppointmentId = null;
    let visitAppointmentId = null;
    let visitPrice = null;
    let onChange = () => {};
    let savedPatient = '';
    let savedVisit = '';
    let closingConfirmed = false;

    /**
     * Open the record of a patient.
     *
     * Opened from a visit of the calendar, it shows the visits tab with that visit; otherwise the details tab.
     *
     * @param {Number} patientId Patient ID.
     * @param {Object} [options]
     * @param {Number} [options.appointmentId] Visit from which the record is opened.
     * @param {Function} [options.onChange] Called after the record changed something the calendar shows.
     */
    function open(patientId, options = {}) {
        customerId = Number(patientId);
        currentAppointmentId = options.appointmentId ? Number(options.appointmentId) : null;
        onChange = options.onChange || (() => {});

        App.Http.ClinicalRecords.findPatient(customerId).done((response) => {
            closingConfirmed = false;
            visitAppointmentId = null;
            savedVisit = '';
            $visitDetail.prop('hidden', true);
            $visitEmpty.prop('hidden', false);

            fillPatient(response);
            renderVisits(response.appointments);

            showTab(currentAppointmentId ? 'visits' : 'details');

            if (currentAppointmentId) {
                loadVisit(currentAppointmentId);
            }

            bootstrap.Modal.getOrCreateInstance($modal[0]).show();
        });
    }

    /**
     * Show a tab of the record.
     *
     * @param {String} tab "details" or "visits".
     */
    function showTab(tab) {
        bootstrap.Tab.getOrCreateInstance($modal.find('[data-tab="' + tab + '"]')[0]).show();
        updateFooter(tab);
    }

    /**
     * The footer saves the patient details; a visit has its own save button.
     *
     * @param {String} tab
     */
    function updateFooter(tab) {
        $patientSave.prop('hidden', tab === 'visits');
    }

    /**
     * Fill the patient part of the record.
     *
     * @param {Object} response
     */
    function fillPatient(response) {
        const customer = response.customer;

        $modal.find('[data-customer]').each((index, element) => {
            $(element).val(customer[element.dataset.customer] ?? '');
        });

        $modal.find('.tab-pane [data-record]').each((index, element) => {
            $(element).val(response.record[element.dataset.record] ?? '');
        });

        $documentType.val(response.document_type || '');
        $documentNumber.val(response.document_number || '');

        $birthDate[0]._flatpickr.setDate(response.record.birth_date || null, false, 'Y-m-d');

        updateHeader();

        savedPatient = JSON.stringify(getPatientData());
    }

    /**
     * Show the patient in the header: name, age, document and phone, and the allergies when there are some.
     */
    function updateHeader() {
        const data = getPatientData();
        const name = [data.customer.first_name, data.customer.last_name].filter(Boolean).join(' ');
        const parts = [];

        if (data.record.birth_date) {
            parts.push(lang('clinical_years_old').replace('%s', moment().diff(data.record.birth_date, 'years')));
        }

        if (data.document_number) {
            const type = $documentType.find('option:selected').text();

            parts.push((data.document_type ? type + ' ' : '') + data.document_number);
        }

        if (data.customer.phone_number) {
            parts.push(data.customer.phone_number);
        }

        $title.text(name || lang('clinical_record'));
        $summary.text(parts.join(' · '));

        const allergies = (data.record.allergies || '').trim();

        $allergiesAlert.prop('hidden', !allergies).find('span').text(allergies);
    }

    /**
     * Render the list of visits, the latest first.
     *
     * @param {Array} appointments
     */
    function renderVisits(appointments) {
        $visits.empty();
        $visitsCount.text(appointments.length || '');

        if (!appointments.length) {
            $('<div/>', {class: 'text-muted p-3', text: lang('clinical_no_visits')}).appendTo($visits);

            return;
        }

        appointments.forEach((appointment) => {
            const isCurrent = appointment.id === currentAppointmentId;

            $('<button/>', {
                'type': 'button',
                'class':
                    'list-group-item list-group-item-action ea-clinical-visit' +
                    (appointment.id === visitAppointmentId ? ' active' : ''),
                'data-appointment-id': appointment.id,
                'html': [
                    $('<div/>', {
                        class: 'd-flex justify-content-between align-items-baseline gap-2',
                        html: [
                            $('<span/>', {
                                class: 'ea-clinical-visit-date',
                                text: App.Utils.Date.format(
                                    appointment.start_datetime,
                                    vars('date_format'),
                                    vars('time_format'),
                                    true,
                                ),
                            }),
                            appointment.price !== null
                                ? $('<span/>', {class: 'ea-clinical-visit-price', text: formatPrice(appointment.price)})
                                : null,
                        ],
                    }),
                    $('<div/>', {
                        class: 'd-flex align-items-center gap-2',
                        html: [
                            $('<strong/>', {class: 'text-truncate', text: appointment.service_name}),
                            appointment.has_record
                                ? $('<i/>', {
                                      'class': 'fas fa-file-medical ea-clinical-visit-notes',
                                      'title': lang('clinical_has_notes'),
                                  })
                                : null,
                            isCurrent
                                ? $('<span/>', {
                                      class: 'badge ea-clinical-visit-current ms-auto',
                                      text: lang('clinical_current_visit'),
                                  })
                                : null,
                        ],
                    }),
                    $('<small/>', {
                        class: 'ea-clinical-visit-meta',
                        text: [
                            appointment.provider_name,
                            App.Utils.CalendarEventPopover?.getStatusName(appointment.status) || appointment.status,
                        ]
                            .filter(Boolean)
                            .join(' · '),
                    }),
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

        $modal.find('.tab-pane [data-record]').each((index, element) => {
            record[element.dataset.record] = $(element).val();
        });

        const birthDate = $birthDate[0]._flatpickr?.selectedDates[0];

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
            updateHeader();
            App.Layouts.Backend.displayNotification(lang('clinical_record_saved'));
            onChange();
        });
    }

    /**
     * Choose a visit of the list, asking first when the shown one has changes that are not saved.
     *
     * @param {Number} appointmentId
     */
    function chooseVisit(appointmentId) {
        if (appointmentId === visitAppointmentId) {
            return;
        }

        if (isVisitDirty()) {
            confirmDiscard(() => loadVisit(appointmentId));

            return;
        }

        loadVisit(appointmentId);
    }

    /**
     * Load the record of a visit into the detail.
     *
     * @param {Number} appointmentId
     */
    function loadVisit(appointmentId) {
        App.Http.ClinicalRecords.findAppointment(appointmentId).done((response) => {
            fillVisit(response);

            $visits.find('.ea-clinical-visit').each((index, element) => {
                element.classList.toggle('active', Number(element.dataset.appointmentId) === appointmentId);
            });
        });
    }

    /**
     * Fill the detail with the record of a visit, showing only the fields of its template.
     *
     * @param {Object} response
     */
    function fillVisit(response) {
        const appointment = response.appointment;
        const fields = response.fields;

        visitAppointmentId = appointment.id;
        visitPrice = appointment.price;

        $visitTitle.text(appointment.service_name);
        updateVisitSubtitle(appointment.start_datetime);

        $visitDetail.find('[data-section="feet"]').prop('hidden', !response.has_feet);

        $visitDetail.find('[data-field]').each((index, element) => {
            const field = element.dataset.field;

            element.hidden = field === 'surgery_price' ? response.template !== 'surgery' : !fields.includes(field);
        });

        // The row of the orthosis and the surgery price only takes space when one of them is shown.
        $visitDetail.find('.row:has([data-field])').each((index, row) => {
            row.hidden = !$(row).children('[data-field]:not([hidden])').length;
        });

        $visitDetail.find('[data-visit]').each((index, element) => {
            $(element).val(response.record[element.dataset.visit] ?? '');
        });

        $surgeryPrice.val(
            response.template === 'surgery' && appointment.price !== null ? String(appointment.price) : '',
        );

        // A visit with only observations gets a large box.
        $observations.attr('rows', response.template === 'notes' ? 12 : 4);

        $visitEmpty.prop('hidden', true);
        $visitDetail.prop('hidden', false).data('start', appointment.start_datetime);
        $visitDetail.closest('.ea-clinical-visit-column').scrollTop(0);

        savedVisit = JSON.stringify(getVisitData());
    }

    /**
     * Show the date and the price of the visit under its treatment.
     *
     * @param {String} startDatetime
     */
    function updateVisitSubtitle(startDatetime) {
        const price = $orthosis.closest('[data-field]').prop('hidden')
            ? visitPrice
            : ($orthosis.find('option:selected').data('price') ?? visitPrice);

        $visitSubtitle.text(
            App.Utils.Date.format(startDatetime, vars('date_format'), vars('time_format'), true) +
                (price !== null && price !== undefined ? ' · ' + formatPrice(price) : ''),
        );
    }

    /**
     * Get the visit record from the detail.
     *
     * @return {Object}
     */
    function getVisitData() {
        const record = {};

        $visitDetail.find('[data-field]:not([hidden]) [data-visit]').each((index, element) => {
            record[element.dataset.visit] = $(element).val();
        });

        return {
            record,
            surgeryPrice: $surgeryPrice.closest('[data-field]').prop('hidden') ? '' : $surgeryPrice.val(),
        };
    }

    /**
     * Save the visit record of the detail.
     */
    function saveVisit() {
        const data = getVisitData();

        App.Http.ClinicalRecords.saveAppointment(visitAppointmentId, data.record, data.surgeryPrice).done(
            (response) => {
                savedVisit = JSON.stringify(data);
                visitPrice = response.price !== null ? Number(response.price) : null;
                updateVisitSubtitle($visitDetail.data('start'));
                App.Layouts.Backend.displayNotification(lang('clinical_visit_saved'));

                // The list shows the new price and which visits have notes.
                App.Http.ClinicalRecords.findPatient(customerId).done((patient) => renderVisits(patient.appointments));

                onChange();
            },
        );
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
     * Whether the shown visit has changes that are not saved.
     *
     * @return {Boolean}
     */
    function isVisitDirty() {
        return visitAppointmentId !== null && JSON.stringify(getVisitData()) !== savedVisit;
    }

    /**
     * Ask before losing the changes that are not saved.
     *
     * @param {Function} discard Called when the user chooses to go on without saving.
     */
    function confirmDiscard(discard) {
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
                    discard();
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

        $patientSave.on('click', savePatient);
        $('#clinical-visit-save').on('click', saveVisit);

        $visits.on('click', '.ea-clinical-visit', (event) => {
            chooseVisit(Number(event.currentTarget.dataset.appointmentId));
        });

        $modal.on('shown.bs.tab', '[data-tab]', (event) => updateFooter(event.target.dataset.tab));

        $orthosis.on('change', () => updateVisitSubtitle($visitDetail.data('start')));

        $modal.on('hide.bs.modal', (event) => {
            if (event.target !== $modal[0] || closingConfirmed) {
                return;
            }

            if (isPatientDirty() || isVisitDirty()) {
                event.preventDefault();
                confirmDiscard(() => {
                    closingConfirmed = true;
                    bootstrap.Modal.getOrCreateInstance($modal[0]).hide();
                });
            }
        });

        $modal.on('hidden.bs.modal', (event) => {
            if (event.target === $modal[0]) {
                customerId = null;
                visitAppointmentId = null;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        open,
    };
})();
