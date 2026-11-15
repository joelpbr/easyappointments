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
    const $allergiesAlert = $('#clinical-record-allergies-alert');
    const $customerDetails = $('#clinical-customer-details');
    const $visits = $('#clinical-visits');
    const $visitsCount = $('#clinical-visits-count');
    const $documentType = $('#clinical-document-type');
    const $documentNumber = $('#clinical-document-number');
    const $birthDate = $('#clinical-birth-date');
    const $age = $('#clinical-age');
    const $patientSave = $('#clinical-record-save');
    const $visitSave = $('#clinical-visit-save');
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
    const $feetXray = $('#clinical-feet-xray');
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
    let showFeetXray = false;
    let feetBackgroundRequest = 0;

    // Whether the visit shown has foot diagrams, and a key for each marker, as the new ones have no id until saved.
    let visitHasFeet = false;
    let markerKey = 0;

    // The marker just placed, which does not grow under the pointer until the pointer has left it once.
    let freshMarkerKey = null;

    // Size of a placed pathology, as a share of the width of the picture: that of the imported ones (16 pixels on
    // their 360 pixels wide canvas). Every marker is shown at this size, whatever box it was saved with.
    const MARKER_SCALE = 16 / 360;

    // Size of the original picture of each diagram, in pixels. The saved coordinates refer to it, and the picture is
    // shown at its width, whatever the size of the file now in assets/img/fotos_pies.
    const ORIGINAL_SIZES = {
        0: {width: 688, height: 535},
        1: {width: 687, height: 535},
        2: {width: 688, height: 536},
        3: {width: 687, height: 536},
    };

    /**
     * Size of the original picture of the diagram on screen, or that of the file when the diagram is not known.
     *
     * @return {{width: Number, height: Number}}
     */
    function originalSize() {
        const picture = $feetPicture[0];
        const legacy = feet?.diagrams.find((diagram) => diagram.id === diagramId && diagram.background_image);

        if (legacy) {
            return {width: legacy.coordinate_width, height: legacy.coordinate_height};
        }

        return ORIGINAL_SIZES[diagramId] || {width: picture.naturalWidth, height: picture.naturalHeight};
    }

    /**
     * Show the picture at the width of the original one, keeping the proportions of the file.
     */
    function sizeFeetPicture() {
        const picture = $feetPicture[0];

        if (!picture.naturalWidth || !picture.naturalHeight) {
            return;
        }

        // Keep the normal display size even when imported coordinates use a smaller legacy canvas.
        $feetBoard[0].style.setProperty(
            '--feet-width',
            (ORIGINAL_SIZES[diagramId]?.width || picture.naturalWidth) + 'px',
        );
        $feetBoard[0].style.setProperty('--feet-ratio', String(picture.naturalWidth / picture.naturalHeight));
    }

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
     * The save button of the tabs saves the patient details; a visit has its own save button.
     *
     * @param {String} tab
     */
    function updateFooter(tab) {
        $patientSave.prop('hidden', tab === 'visits');

        // In the visits tab the chosen visit is a column on the right, from under the header.
        $modal.toggleClass('ea-visits-mode', tab === 'visits');
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
        updateSaveButtons();
    }

    /**
     * Show the patient in the header: the name, and the allergies when there are some; and the age next to the date
     * of birth.
     */
    function updateHeader() {
        const data = getPatientData();
        const name = [data.customer.first_name, data.customer.last_name].filter(Boolean).join(' ');
        const age = data.record.birth_date
            ? lang('clinical_years_old').replace('%s', moment().diff(data.record.birth_date, 'years'))
            : '';

        $age.val(age);

        $title.text(name || (customerId === 0 ? lang('clinical_new_patient') : lang('clinical_record')));
        $customerDetails.prop('hidden', !customerId);

        const allergies = (data.record.allergies || '').trim();

        // "No known allergies" and the like ("No refereix al·lèrgies", "Sin alergias", "Cap", "Ninguna") is no warning.
        const noAllergies = /^(no\b|sense\b|sin\b|cap\b|ninguna|ningú|nada|res\b|-+$)/i.test(allergies);

        $allergiesAlert
            .prop('hidden', !allergies || noAllergies)
            .find('.ea-clinical-allergies-text')
            .text(allergies);
    }

    /**
     * Show or hide the comment of a visit (the notes of its appointment), from the icon by its date.
     *
     * @param {HTMLElement} icon
     */
    function toggleComment(icon) {
        const popover = bootstrap.Popover.getOrCreateInstance(icon, {
            content: icon.dataset.notes,
            trigger: 'manual',
            placement: 'right',
            container: $modal[0],
            customClass: 'ea-clinical-visit-comment-popover',
        });
        const isShown = icon.getAttribute('aria-describedby');

        hideComments();

        if (!isShown) {
            popover.show();
        }
    }

    /**
     * Hide the comments of the visits that are shown.
     */
    function hideComments() {
        $visits.find('.ea-clinical-visit-comment').each((index, icon) => bootstrap.Popover.getInstance(icon)?.hide());
    }

    /**
     * Render the list of visits, the latest first.
     *
     * @param {Array} appointments
     */
    function renderVisits(appointments) {
        hideComments();
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
                                html: [
                                    document.createTextNode(
                                        App.Utils.Date.format(
                                            appointment.start_datetime,
                                            vars('date_format'),
                                            vars('time_format'),
                                            true,
                                        ),
                                    ),
                                    appointment.notes
                                        ? $('<span/>', {
                                              'class': 'ea-clinical-visit-comment ms-2',
                                              'role': 'button',
                                              'tabindex': 0,
                                              'title': lang('appointment_comment'),
                                              'aria-label': lang('appointment_comment'),
                                              'data-notes': appointment.notes,
                                              'html': $('<i/>', {class: 'fas fa-comment-dots'}),
                                          })
                                        : null,
                                ],
                            }),
                            appointment.price !== null
                                ? $('<span/>', {
                                      class: 'ea-clinical-visit-price ea-money',
                                      text: formatPrice(appointment.price),
                                  })
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

        return App.Http.ClinicalRecords.savePatient(customerId, data).done(() => {
            savedPatient = JSON.stringify(data);
            updateSaveButtons();
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
                updateSaveButtons();
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
            confirmUnsaved(
                () => saveVisit().done(() => loadVisit(appointmentId)),
                () => loadVisit(appointmentId),
            );

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
        visitHasFeet = Boolean(response.has_feet);
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
        $('#clinical-visit-column').scrollTop(0);
        App.Layouts.Backend.fadeIn($visitDetail);

        savedVisit = JSON.stringify(getVisitData());
        updateSaveButtons();
    }

    /**
     * Show the foot diagrams of a visit: the four views, the catalog of pathologies and the pathologies placed on the
     * visit. Choosing a pathology and clicking on the picture places it there (see placeMarker()).
     *
     * @param {Object} visitFeet {diagrams, pathologies, markers}
     */
    function renderFeet(visitFeet) {
        feet = visitFeet;
        showFeetXray = false;
        feet.markers.forEach((marker) => (marker.key = ++markerKey));
        $feetViews.empty();

        // On screen the second and third views swap places; the saved diagrams and their numbers stay as they are.
        const shownDiagrams = feet.diagrams.map((diagram, index) => ({diagram, number: index + 1}));

        if (shownDiagrams.length >= 3) {
            [shownDiagrams[1], shownDiagrams[2]] = [shownDiagrams[2], shownDiagrams[1]];
        }

        shownDiagrams.forEach(({diagram, number}) => {
            $('<button/>', {
                'type': 'button',
                'class': 'ea-feet-view',
                'data-diagram-id': diagram.id,
                'title': diagram.name || lang('clinical_feet_view').replace('%s', number),
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

        // A visit opens on the first view from the left with pathologies placed on it, or the first one when none has.
        const firstWithMarkers = shownDiagrams.find(({diagram}) =>
            feet.markers.some((marker) => marker.diagram_id === diagram.id),
        );

        showDiagram((firstWithMarkers ?? shownDiagrams[0])?.diagram.id ?? null);
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
        $feetBoard.removeClass('ea-feet-xray');
        $feetPicture.off('load.markers').on('load.markers', renderMarkers);
        $feetPicture.attr('src', diagram.image_url);
        updateFeetBackground();

        if ($feetPicture[0].complete && $feetPicture[0].naturalWidth) {
            renderMarkers();
        }
    }

    /**
     * Switch only the background. The normal picture keeps its dimensions and click target, so marker coordinates
     * stay the same even though the X-ray files have a different resolution. This preference is never saved.
     */
    function updateFeetBackground() {
        const request = ++feetBackgroundRequest;
        const diagram = feet?.diagrams.find((diagram) => diagram.id === diagramId);
        const xrayUrl = diagram?.image_url.replace(/\.png(?=[?#]|$)/i, '_x.png');

        $feetXray.attr('aria-pressed', String(showFeetXray)).toggleClass('active', showFeetXray);

        if (!showFeetXray || !xrayUrl) {
            // Leave the X-ray underneath while the normal image fades back in.
            $feetBoard.removeClass('ea-feet-xray');
            return;
        }

        const picture = new Image();
        picture.onload = () => {
            if (request !== feetBackgroundRequest) return;
            $feetBoard.css('background-image', 'url("' + xrayUrl + '")').addClass('ea-feet-xray');
        };
        picture.onerror = () => {
            if (request !== feetBackgroundRequest) return;
            showFeetXray = false;
            $feetBoard.removeClass('ea-feet-xray');
            $feetXray.attr('aria-pressed', 'false').removeClass('active');
        };
        picture.src = xrayUrl;
    }

    /**
     * Draw the pathologies placed on the diagram on screen, from their coordinates on the original picture, and count
     * the ones of each view on its thumbnail.
     */
    function renderMarkers() {
        const picture = $feetPicture[0];
        const {width, height} = originalSize();

        sizeFeetPicture();
        $feetMarkers.empty();

        if (picture.naturalWidth && width && height) {
            feet.markers
                .filter((marker) => marker.diagram_id === diagramId)
                .forEach((marker) => {
                    const pathology = feet.pathologies.find((pathology) => pathology.id === marker.pathology_id);

                    const name = pathology?.name || '';

                    // Centred where it was placed, at the same size as all the others.
                    const centerX = (marker.x1 + marker.x2) / 2 / width;
                    const centerY = (marker.y1 + marker.y2) / 2 / height;
                    const sizeX = MARKER_SCALE;
                    const sizeY = (MARKER_SCALE * width) / height;

                    $('<button/>', {
                        'type': 'button',
                        'class':
                            'ea-feet-marker' +
                            (marker.is_healed ? ' ea-feet-marker-healed' : '') +
                            (marker.key === freshMarkerKey ? ' ea-feet-marker-fresh' : ''),
                        'data-marker-key': marker.key,
                        'title': marker.is_healed ? name + ' (' + lang('clinical_marker_healed_state') + ')' : name,
                        'css': {
                            left: (centerX - sizeX / 2) * 100 + '%',
                            top: (centerY - sizeY / 2) * 100 + '%',
                            width: sizeX * 100 + '%',
                            height: sizeY * 100 + '%',
                        },
                        // A healed pathology is replaced by a smiling face in the same place; its name stays in the title.
                        'html': [
                            marker.is_healed
                                ? $('<i/>', {'class': 'fas fa-smile ea-feet-marker-healed-face'})
                                : pathology && $('<img/>', {src: pathology.image_url, alt: pathology.name}),
                        ].filter(Boolean),
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
     * whatever its size on screen. It is saved with the visit.
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
        const {width, height} = originalSize();
        const x = ((event.clientX - rect.left) * width) / rect.width;
        const y = ((event.clientY - rect.top) * height) / rect.height;
        const size = Math.max(1, Math.round(width * MARKER_SCALE));
        const half = size / 2;

        const marker = {
            diagram_id: diagramId,
            pathology_id: pathologyId,
            x1: Math.round(x - half),
            y1: Math.round(y - half),
            x2: Math.round(x - half) + size,
            y2: Math.round(y - half) + size,
            coordinate_width: feet.diagrams.find((diagram) => diagram.id === diagramId)?.coordinate_width || null,
            coordinate_height: feet.diagrams.find((diagram) => diagram.id === diagramId)?.coordinate_height || null,
            background_image: feet.diagrams.find((diagram) => diagram.id === diagramId)?.background_image || null,
        };

        // Saved with the visit ("Save visit").
        feet.markers.push({...marker, id: null, key: ++markerKey});
        freshMarkerKey = markerKey;
        renderMarkers();
        updateSaveButtons();
    }

    /**
     * Ask what to do with a placed pathology: mark it as healed (or not healed any more), remove it or leave it. Both
     * changes are saved with the visit.
     *
     * @param {Number} key Key of the marker.
     */
    function editMarker(key) {
        const marker = feet.markers.find((marker) => marker.key === key);

        if (!marker) {
            return;
        }

        const pathology = feet.pathologies.find((pathology) => pathology.id === marker.pathology_id);
        const name = App.Utils.String.escapeHtml(pathology?.name || '');

        App.Utils.Message.show(lang('clinical_pathologies'), lang('clinical_marker_question').replace('%s', name), [
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
                    feet.markers = feet.markers.filter((marker) => marker.key !== key);
                    renderMarkers();
                    updateSaveButtons();
                },
            },
            {
                text:
                    '<i class="fas fa-smile me-1"></i>' +
                    lang(marker.is_healed ? 'clinical_marker_not_healed' : 'clinical_marker_healed'),
                className: 'btn btn-success',
                click: (event, messageModal) => {
                    messageModal.hide();
                    marker.is_healed = !marker.is_healed;
                    renderMarkers();
                    updateSaveButtons();
                },
            },
        ]);
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

        $visitSubtitle.text(App.Utils.Date.format(startDatetime, vars('date_format'), vars('time_format'), true));
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
            // The pathologies placed on the foot diagrams are saved with the visit, so they count as its changes.
            markers:
                visitHasFeet && feet
                    ? feet.markers.map((marker) => ({
                          id: marker.id || null,
                          diagram_id: marker.diagram_id,
                          pathology_id: marker.pathology_id,
                          x1: marker.x1,
                          y1: marker.y1,
                          x2: marker.x2,
                          y2: marker.y2,
                          is_healed: Boolean(marker.is_healed),
                          annotation_title: marker.annotation_title || null,
                          annotation_text: marker.annotation_text || null,
                          coordinate_width: marker.coordinate_width || null,
                          coordinate_height: marker.coordinate_height || null,
                          background_image: marker.background_image || null,
                      }))
                    : null,
        };
    }

    /**
     * Save the visit record of the detail.
     */
    function saveVisit() {
        const data = getVisitData();

        return App.Http.ClinicalRecords.saveAppointment(
            visitAppointmentId,
            data.record,
            data.surgeryPrice,
            data.markers,
        ).done((response) => {
            // The new markers come back with their id.
            if (response.markers && feet) {
                feet.markers = response.markers.map((marker) => ({...marker, key: ++markerKey}));
                renderMarkers();
            }

            savedVisit = JSON.stringify(getVisitData());
            updateSaveButtons();
            visitPrice = response.price !== null ? Number(response.price) : null;
            updateVisitSubtitle($visitDetail.data('start'));
            App.Layouts.Backend.displayNotification(lang('clinical_visit_saved'));

            // The list shows the new price and which visits have notes.
            App.Http.ClinicalRecords.findPatient(customerId).done((patient) => renderVisits(patient.appointments));

            onChange(customerId);
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
     * Whether the shown visit has changes that are not saved.
     *
     * @return {Boolean}
     */
    function isVisitDirty() {
        return visitAppointmentId !== null && JSON.stringify(getVisitData()) !== savedVisit;
    }

    /**
     * Ask what to do with the changes that are not saved: save them, leave them or stay.
     *
     * @param {Function} save Called to save and go on.
     * @param {Function} discard Called to go on without saving.
     */
    function confirmUnsaved(save, discard) {
        App.Utils.Message.show(lang('clinical_record'), lang('clinical_unsaved_changes'), [
            {
                text: lang('cancel'),
                className: 'btn btn-secondary',
                click: (event, messageModal) => messageModal.hide(),
            },
            {
                text: lang('clinical_dont_save'),
                className: 'btn btn-outline-danger',
                click: (event, messageModal) => {
                    messageModal.hide();
                    discard();
                },
            },
            {
                text: lang('save'),
                className: 'btn btn-primary',
                click: (event, messageModal) => {
                    messageModal.hide();
                    save();
                },
            },
        ]);
    }

    /**
     * Open the original customer page, keeping the same unsaved-changes choices as closing the record.
     */
    function openCustomerDetails() {
        if (!customerId) {
            return;
        }

        const navigate = () => window.location.assign(App.Utils.Url.siteUrl('customers?customer_id=' + customerId));

        if (isPatientDirty() || isVisitDirty()) {
            confirmUnsaved(() => {
                $.when(isPatientDirty() ? savePatient() : null, isVisitDirty() ? saveVisit() : null).done(navigate);
            }, navigate);

            return;
        }

        navigate();
    }

    /**
     * The save buttons can only be pressed when there is something to save.
     */
    function updateSaveButtons() {
        $patientSave.prop('disabled', !isPatientDirty());
        $visitSave.prop('disabled', !isVisitDirty());
    }

    /**
     * Initialize the component.
     */
    function initialize() {
        if (!$modal.length) {
            return;
        }

        // The age next to the date of birth follows it while it is typed or picked.
        App.Utils.UI.initializeDatePicker($birthDate, {
            maxDate: 'today',
            onChange: () => {
                updateHeader();
                updateSaveButtons();
            },
        });

        // Every change of the patient or of the visit turns their save button on.
        $modal.on('input change', 'input, textarea, select', updateSaveButtons);

        $patientSave.on('click', savePatient);
        $customerDetails.on('click', openCustomerDetails);
        $('#clinical-visit-save').on('click', saveVisit);

        $feetViews.on('click', '.ea-feet-view', (event) => showDiagram(Number(event.currentTarget.dataset.diagramId)));

        $feetXray.on('click', () => {
            showFeetXray = !showFeetXray;
            updateFeetBackground();
        });

        $feetPicture.on('click', placeMarker);

        $feetMarkers.on('mouseleave', '.ea-feet-marker-fresh', (event) => {
            freshMarkerKey = null;
            event.currentTarget.classList.remove('ea-feet-marker-fresh');
        });

        $feetMarkers.on('click', '.ea-feet-marker', (event) => {
            event.stopPropagation();
            editMarker(Number(event.currentTarget.dataset.markerKey));
        });

        // The chosen pathology, to be placed on the picture.
        $feetPathologies.on('click', '.ea-feet-pathology', (event) => {
            const $pathology = $(event.currentTarget);
            const isActive = $pathology.hasClass('active');

            $feetPathologies.children().removeClass('active');
            $pathology.toggleClass('active', !isActive);
            updateFeetCursor();
        });

        // The comment of a visit opens by its date, without choosing that visit.
        $visits.on('click keydown', '.ea-clinical-visit-comment', (event) => {
            if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            toggleComment(event.currentTarget);
        });

        $visits.on('click', '.ea-clinical-visit', (event) => {
            chooseVisit(Number(event.currentTarget.dataset.appointmentId));
        });

        // A click anywhere else, or scrolling the list, closes the comment.
        $(document).on('click', (event) => {
            if (!$(event.target).closest('.ea-clinical-visit-comment, .popover').length) {
                hideComments();
            }
        });
        $visits.on('scroll', hideComments);
        $modal.on('hide.bs.modal', hideComments);

        // Switch the layout as the tab starts to change, so that the new tab fades in already in its place.
        $modal.on('show.bs.tab', '[data-tab]', (event) => updateFooter(event.target.dataset.tab));

        $orthosis.on('change', () => updateVisitSubtitle($visitDetail.data('start')));

        $modal.on('hide.bs.modal', (event) => {
            if (event.target !== $modal[0] || closingConfirmed) {
                return;
            }

            if (isPatientDirty() || isVisitDirty()) {
                event.preventDefault();

                const close = () => {
                    closingConfirmed = true;
                    bootstrap.Modal.getOrCreateInstance($modal[0]).hide();
                };

                confirmUnsaved(() => {
                    // A new patient may first ask about possible duplicates, so it is saved without closing.
                    if (customerId === 0) {
                        savePatient();

                        return;
                    }

                    $.when(isPatientDirty() ? savePatient() : null, isVisitDirty() ? saveVisit() : null).done(close);
                }, close);
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
