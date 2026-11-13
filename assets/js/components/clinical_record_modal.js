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
    const $age = $('#clinical-age');
    const $patientSave = $('#clinical-record-save');
    const $visitEmpty = $('#clinical-visit-empty');
    const $visitDetail = $('#clinical-visit-detail');
    const $visitTitle = $('#clinical-visit-title');
    const $visitSubtitle = $('#clinical-visit-subtitle');
    const $orthosis = $('#clinical-visit-orthosis');
    const $surgeryPrice = $('#clinical-visit-surgery-price');
    const $observations = $('#clinical-visit-observations');
    const $observationsField = $observations.closest('[data-field="observations"]');
    const $observationsHome = $('#clinical-observations-home');
    const $feetBoard = $('#clinical-feet-board');
    const $feetPicture = $('#clinical-feet-picture');
    const $feetMarkers = $('#clinical-feet-markers');
    const $feetViews = $('#clinical-feet-views');
    const $feetPathologies = $('#clinical-feet-pathologies');
    const $feetObservations = $('#clinical-feet-observations');

    let customerId = null;
    let currentAppointmentId = null;
    let visitAppointmentId = null;
    let visitPrice = null;
    let onChange = () => {};
    let savedPatient = '';
    let savedVisit = '';
    let closingConfirmed = false;

    // Foot diagrams of the visit shown: {diagrams, pathologies, markers} and the diagram on screen.
    let feet = null;
    let diagramId = null;

    // Size of a placed pathology on the original picture, in its pixels.
    const MARKER_SIZE = 24;

    /**
     * Open the record of a patient.
     *
     * Opened from a visit of the calendar, it shows the visits tab with that visit; otherwise the details tab.
     *
     * @param {Number} patientId Patient ID.
     * @param {Object} [options]
     * @param {Number} [options.appointmentId] Visit from which the record is opened.
     * @param {Function} [options.onChange] Called with the patient ID after the record changed something that the
     * page shows (the patient, or the price of a visit).
     */
    function open(patientId, options = {}) {
        currentAppointmentId = options.appointmentId ? Number(options.appointmentId) : null;
        onChange = options.onChange || (() => {});

        App.Http.ClinicalRecords.findPatient(Number(patientId)).done((response) => {
            customerId = Number(patientId);
            show(response);
        });
    }

    /**
     * Open an empty record to create a new patient. Saving it creates the patient, after checking that it does not
     * look like an existing one.
     *
     * @param {Object} [options]
     * @param {Function} [options.onChange] Called with the new patient ID once created.
     */
    function openNew(options = {}) {
        currentAppointmentId = null;
        onChange = options.onChange || (() => {});
        customerId = 0;

        show({customer: {}, record: {}, document_type: '', document_number: '', appointments: []});
    }

    /**
     * Show the record of a patient (or an empty one for a new patient).
     *
     * @param {Object} response
     */
    function show(response) {
        closingConfirmed = false;
        visitAppointmentId = null;
        savedVisit = '';
        $visitDetail.prop('hidden', true);
        $visitEmpty.prop('hidden', false);

        fillPatient(response);
        renderVisits(response.appointments);

        // A new patient has no visits until it is saved.
        $modal.find('[data-tab="visits"]').prop('disabled', customerId === 0);

        showTab(currentAppointmentId ? 'visits' : 'details');

        if (currentAppointmentId) {
            loadVisit(currentAppointmentId);
        }

        bootstrap.Modal.getOrCreateInstance($modal[0]).show();
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
        const age = data.record.birth_date
            ? lang('clinical_years_old').replace('%s', moment().diff(data.record.birth_date, 'years'))
            : '';

        $age.val(age);

        if (age) {
            parts.push(age);
        }

        if (data.document_number) {
            const type = $documentType.find('option:selected').text();

            parts.push((data.document_type ? type + ' ' : '') + data.document_number);
        }

        if (data.customer.phone_number) {
            parts.push(data.customer.phone_number);
        }

        $title.text(name || (customerId === 0 ? lang('clinical_new_patient') : lang('clinical_record')));
        $summary.text(parts.join(' · '));

        const allergies = (data.record.allergies || '').trim();

        // "No known allergies" and the like ("No refereix al·lèrgies", "Sin alergias", "Cap", "Ninguna") is no warning.
        const noAllergies = /^(no\b|sense\b|sin\b|cap\b|ninguna|ningú|nada|res\b|-+$)/i.test(allergies);

        $allergiesAlert
            .prop('hidden', !allergies || noAllergies)
            .find('span')
            .text(allergies);
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
                        text: [appointment.provider_name, getStatusName(appointment.status)]
                            .filter(Boolean)
                            .join(' · '),
                    }),
                ],
            }).appendTo($visits);
        });
    }

    /**
     * Get the name of an appointment status, from the statuses of the page.
     *
     * @param {String} code
     *
     * @return {String}
     */
    function getStatusName(code) {
        const status = (vars('appointment_statuses') || []).find((status) => status.code === code);

        return status ? status.name : code || '';
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

        if (customerId === 0) {
            createPatient(data);

            return;
        }

        App.Http.ClinicalRecords.savePatient(customerId, data).done(() => {
            savedPatient = JSON.stringify(data);
            updateHeader();
            App.Layouts.Backend.displayNotification(lang('clinical_record_saved'));
            onChange(customerId);
        });
    }

    /**
     * Create the patient of an empty record, or open the existing patient that it looks like when the user prefers.
     *
     * @param {Object} data
     */
    function createPatient(data) {
        const create = () =>
            App.Http.ClinicalRecords.savePatient(0, data).done((response) => {
                customerId = Number(response.id);
                savedPatient = JSON.stringify(data);
                updateHeader();
                $modal.find('[data-tab="visits"]').prop('disabled', false);
                App.Layouts.Backend.displayNotification(lang('clinical_record_saved'));
                onChange(customerId);
            });

        if (!App.Components.PatientDuplicates) {
            create();

            return;
        }

        App.Components.PatientDuplicates.confirmNewPatient(data.customer).then((choice) => {
            if (choice?.create) {
                create();
            } else if (choice?.id) {
                savedPatient = JSON.stringify(data);
                open(choice.id, {onChange});
                onChange(choice.id);
            }
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

        // With the foot diagrams, the observations go under the pathologies; otherwise they keep their place.
        if (response.has_feet) {
            renderFeet(response.feet);
            $feetObservations.append($observationsField);
        } else {
            $observationsHome.after($observationsField);
        }

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
     * Show the foot diagrams of a visit: the four views, the catalog of pathologies and the pathologies placed on the
     * visit. Choosing a pathology and clicking on the picture places it there (see placeMarker()).
     *
     * @param {Object} visitFeet {diagrams, pathologies, markers}
     */
    function renderFeet(visitFeet) {
        feet = visitFeet;
        $feetViews.empty();

        feet.diagrams.forEach((diagram, index) => {
            $('<button/>', {
                'type': 'button',
                'class': 'ea-feet-view',
                'data-diagram-id': diagram.id,
                'title': diagram.name || lang('clinical_feet_view').replace('%s', index + 1),
                'html': [$('<img/>', {src: diagram.image_url, alt: ''}), $('<span/>', {class: 'ea-feet-view-count'})],
            }).appendTo($feetViews);
        });

        $feetPathologies.empty();

        if (!feet.pathologies.length) {
            $('<div/>', {class: 'ea-feet-empty', text: lang('clinical_no_pathologies')}).appendTo($feetPathologies);
        }

        feet.pathologies.forEach((pathology) => {
            $('<button/>', {
                'type': 'button',
                'class': 'list-group-item list-group-item-action ea-feet-pathology',
                'data-pathology-id': pathology.id,
                'title': pathology.description || pathology.name,
                'html': [$('<img/>', {src: pathology.image_url, alt: ''}), $('<span/>', {text: pathology.name})],
            }).appendTo($feetPathologies);
        });

        updateFeetCursor();
        showDiagram(feet.diagrams[0]?.id ?? null);
    }

    /**
     * Show one of the four foot diagrams with the pathologies placed on it.
     *
     * @param {Number} id Diagram ID.
     */
    function showDiagram(id) {
        const diagram = feet.diagrams.find((diagram) => diagram.id === id);

        diagramId = id;
        $feetViews.children().each((index, button) => {
            button.classList.toggle('active', Number(button.dataset.diagramId) === id);
        });

        if (!diagram) {
            return;
        }

        // The markers are placed once the picture knows its original size.
        $feetPicture.off('load.markers').on('load.markers', renderMarkers);
        $feetPicture.attr('src', diagram.image_url);

        if ($feetPicture[0].complete && $feetPicture[0].naturalWidth) {
            renderMarkers();
        }
    }

    /**
     * Draw the pathologies placed on the diagram on screen, from their coordinates on the original picture, and count
     * the ones of each view on its thumbnail.
     */
    function renderMarkers() {
        const picture = $feetPicture[0];
        const width = picture.naturalWidth;
        const height = picture.naturalHeight;

        $feetMarkers.empty();

        if (width && height) {
            feet.markers
                .filter((marker) => marker.diagram_id === diagramId)
                .forEach((marker) => {
                    const pathology = feet.pathologies.find((pathology) => pathology.id === marker.pathology_id);

                    $('<button/>', {
                        'type': 'button',
                        'class': 'ea-feet-marker',
                        'data-marker-id': marker.id,
                        'title': pathology?.name || '',
                        'css': {
                            left: (marker.x1 / width) * 100 + '%',
                            top: (marker.y1 / height) * 100 + '%',
                            width: ((marker.x2 - marker.x1) / width) * 100 + '%',
                            height: ((marker.y2 - marker.y1) / height) * 100 + '%',
                        },
                        'html': pathology ? $('<img/>', {src: pathology.image_url, alt: pathology.name}) : null,
                    }).appendTo($feetMarkers);
                });
        }

        $feetViews.children().each((index, button) => {
            const count = feet.markers.filter(
                (marker) => marker.diagram_id === Number(button.dataset.diagramId),
            ).length;

            $(button)
                .find('.ea-feet-view-count')
                .text(count || '')
                .prop('hidden', !count);
        });
    }

    /**
     * Place the chosen pathology where the picture was clicked: the coordinates are those of the original picture,
     * whatever its size on screen, and the marker is saved at once.
     *
     * @param {jQuery.Event} event
     */
    function placeMarker(event) {
        const pathologyId = Number($feetPathologies.find('.ea-feet-pathology.active').data('pathologyId'));
        const picture = $feetPicture[0];

        if (!pathologyId || !picture.naturalWidth || diagramId === null) {
            return;
        }

        const rect = picture.getBoundingClientRect();
        const x = ((event.clientX - rect.left) * picture.naturalWidth) / rect.width;
        const y = ((event.clientY - rect.top) * picture.naturalHeight) / rect.height;
        const half = MARKER_SIZE / 2;

        const marker = {
            diagram_id: diagramId,
            pathology_id: pathologyId,
            x1: Math.round(x - half),
            y1: Math.round(y - half),
            x2: Math.round(x - half) + MARKER_SIZE,
            y2: Math.round(y - half) + MARKER_SIZE,
        };

        App.Http.ClinicalRecords.addMarker(visitAppointmentId, marker).done((response) => {
            feet.markers.push(response.marker);
            renderMarkers();
        });
    }

    /**
     * Remove a placed pathology, after asking.
     *
     * @param {Number} markerId
     */
    function removeMarker(markerId) {
        const marker = feet.markers.find((marker) => marker.id === markerId);
        const pathology = feet.pathologies.find((pathology) => pathology.id === marker?.pathology_id);

        App.Utils.Message.show(
            lang('clinical_pathologies'),
            lang('clinical_remove_marker').replace('%s', pathology?.name || ''),
            [
                {
                    text: lang('cancel'),
                    className: 'btn btn-secondary',
                    click: (event, messageModal) => messageModal.hide(),
                },
                {
                    text: lang('clinical_remove'),
                    className: 'btn btn-danger',
                    click: (event, messageModal) => {
                        messageModal.hide();
                        App.Http.ClinicalRecords.deleteMarker(visitAppointmentId, markerId).done(() => {
                            feet.markers = feet.markers.filter((marker) => marker.id !== markerId);
                            renderMarkers();
                        });
                    },
                },
            ],
        );
    }

    /**
     * The picture shows a crosshair while a pathology is chosen, to place it.
     */
    function updateFeetCursor() {
        $feetBoard.toggleClass('ea-feet-placing', $feetPathologies.find('.ea-feet-pathology.active').length > 0);
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

                onChange(customerId);
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

        // The age next to the date of birth follows it while it is typed or picked.
        App.Utils.UI.initializeDatePicker($birthDate, {maxDate: 'today', onChange: () => updateHeader()});

        $patientSave.on('click', savePatient);
        $('#clinical-visit-save').on('click', saveVisit);

        $feetViews.on('click', '.ea-feet-view', (event) => showDiagram(Number(event.currentTarget.dataset.diagramId)));

        $feetPicture.on('click', placeMarker);

        $feetMarkers.on('click', '.ea-feet-marker', (event) => {
            event.stopPropagation();
            removeMarker(Number(event.currentTarget.dataset.markerId));
        });

        // The chosen pathology, to be placed on the picture.
        $feetPathologies.on('click', '.ea-feet-pathology', (event) => {
            const $pathology = $(event.currentTarget);
            const isActive = $pathology.hasClass('active');

            $feetPathologies.children().removeClass('active');
            $pathology.toggleClass('active', !isActive);
            updateFeetCursor();
        });

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
        openNew,
    };
})();
