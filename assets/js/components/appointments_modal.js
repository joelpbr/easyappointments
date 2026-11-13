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
 * Appointments modal component.
 *
 * This module implements the appointments modal functionality.
 *
 * Old Name: BackendCalendarAppointmentsModal
 */
App.Components.AppointmentsModal = (function () {
    const $appointmentsModal = $('#appointments-modal');
    const $startDatetime = $('#start-datetime');
    const $endDatetime = $('#end-datetime');
    const $customerId = $('#customer-id');
    const $firstName = $('#first-name');
    const $lastName = $('#last-name');
    const $email = $('#email');
    const $phoneNumber = $('#phone-number');
    const $address = $('#address');
    const $city = $('#city');
    const $zipCode = $('#zip-code');
    const $language = $('#language');
    const $timezone = $('#timezone');
    const $customerNotes = $('#customer-notes');
    const $saveAppointment = $('#save-appointment');
    const $appointmentId = $('#appointment-id');
    const $appointmentLocation = $('#appointment-location');
    const $appointmentMeetingLink = $('#appointment-meeting-link');
    const $appointmentStatus = $('#appointment-status');
    const $appointmentColor = $('#appointment-color');
    const $appointmentNotes = $('#appointment-notes');
    const $reloadAppointments = $('#reload-appointments');
    const $selectFilterItem = $('#select-filter-item');
    const $selectService = $('#select-service');
    const $selectProvider = $('#select-provider');
    const $insertAppointment = $('#insert-appointment');
    const $newCustomer = $('#new-customer');
    const $customField1 = $('#custom-field-1');
    const $customField2 = $('#custom-field-2');
    const $customField3 = $('#custom-field-3');
    const $customField4 = $('#custom-field-4');
    const $customField5 = $('#custom-field-5');
    const $appointmentDuration = $('#appointment-duration');
    const $appointmentPrice = $('#appointment-price');
    const $noShowWarning = $('#no-show-warning');
    const $appointmentFirstVisit = $('#appointment-first-visit');
    const $toggleMoreFields = $('#toggle-more-fields');
    const $usualProviderHint = $('#usual-provider-hint');

    const MIN_DURATION = 5; // Minutes, matches EVENT_MINIMUM_DURATION.

    let patientSearch = null;
    let newPatientConfirmed = false;
    let usualProviderId = null;

    /**
     * Show the duration of the start and end currently selected.
     */
    function updateDurationField() {
        const start = App.Utils.UI.getDateTimePickerValue($startDatetime);
        const end = App.Utils.UI.getDateTimePickerValue($endDatetime);

        if (start && end) {
            $appointmentDuration.val(Math.max(0, Math.round((end - start) / 60000)));
        }
    }

    /**
     * Move the end so the appointment lasts the minutes of the duration field.
     *
     * @param {Number} [fallbackMinutes] Used when the duration field has no valid value.
     */
    function applyDuration(fallbackMinutes) {
        const start = App.Utils.UI.getDateTimePickerValue($startDatetime);
        const minutes = parseInt($appointmentDuration.val(), 10);
        const duration = minutes >= MIN_DURATION ? minutes : fallbackMinutes;

        if (!start || !duration) {
            return;
        }

        App.Utils.UI.setDateTimePickerValue($endDatetime, new Date(start.getTime() + duration * 60000));
        $appointmentDuration.val(duration);
    }

    /**
     * Show or hide the secondary fields of the form.
     *
     * @param {Boolean} show
     */
    function toggleMoreFields(show) {
        $appointmentsModal.toggleClass('ea-show-more', show);
        $toggleMoreFields.attr('aria-expanded', String(show));
        $toggleMoreFields.find('i').toggleClass('fa-chevron-down', !show).toggleClass('fa-chevron-up', show);
        $toggleMoreFields.find('span').text(lang(show ? 'fewer_details' : 'more_details'));
    }

    const moment = window.moment;

    /**
     * Update the displayed timezone.
     */
    function updateTimezone() {
        const providerId = $selectProvider.val();

        const provider = vars('available_providers').find(
            (availableProvider) => Number(availableProvider.id) === Number(providerId),
        );

        if (provider && provider.timezone) {
            $('.provider-timezone').text(vars('timezones')[provider.timezone]);
        }
    }

    /**
     * Add the component event listeners.
     */
    function addEventListeners() {
        /**
         * Event: Invalid Field "Input"
         *
         * Clear the invalid state as soon as the user corrects the field, instead of on the next save attempt.
         */
        $appointmentsModal.on('input change', '.is-invalid', (event) => {
            $(event.target).removeClass('is-invalid');
        });

        /**
         * Event: Duration "Input"
         *
         * The end follows the duration, so any number of minutes can be used, even outside the calendar grid.
         */
        $appointmentDuration.on('input', () => applyDuration());

        /**
         * Event: More Details Button "Click"
         */
        $toggleMoreFields.on('click', () => toggleMoreFields(!$appointmentsModal.hasClass('ea-show-more')));

        /**
         * Event: Appointments Modal "Shown"
         *
         * The start and end are set from many places (calendar selection, edit, search), so the duration is read once
         * the modal is visible. The secondary fields are expanded when one of them has a value.
         */
        $appointmentsModal.on('shown.bs.modal', () => {
            updateDurationField();

            const hasMoreData = $appointmentsModal
                .find('.ea-more-field')
                .find('input[type=text], input:not([type]), textarea')
                .toArray()
                .some((field) => field.value.trim() !== '');

            toggleMoreFields(hasMoreData);
        });

        /**
         * Event: Manage Appointments Dialog Save Button "Click"
         *
         * Stores the appointment changes or inserts a new appointment depending on the dialog mode.
         */
        $saveAppointment.on('click', () => {
            // Before doing anything the appointment data need to be validated.
            if (!App.Components.AppointmentsModal.validateAppointmentForm()) {
                return;
            }

            // A new patient might already exist: ask first, then save again with the answer.
            if (!$customerId.val() && !newPatientConfirmed) {
                App.Components.PatientDuplicates.confirmNewPatient({
                    first_name: $firstName.val(),
                    last_name: $lastName.val(),
                    phone_number: $phoneNumber.val(),
                }).then((choice) => {
                    if (choice?.id) {
                        App.Http.Customers.find(choice.id).done((customer) => {
                            fillCustomer(customer);

                            // A patient who missed appointments is not saved at once, so that the warning is seen.
                            if (Number(customer.no_shows) > 0) {
                                $noShowWarning[0].scrollIntoView({block: 'center'});

                                return;
                            }

                            $saveAppointment.trigger('click');
                        });
                    } else if (choice?.create) {
                        newPatientConfirmed = true;
                        $saveAppointment.trigger('click');
                    }
                });

                return;
            }

            // ID must exist on the object in order for the model to update the record and not to perform
            // an insert operation.

            const startDateTimeObject = App.Utils.UI.getDateTimePickerValue($startDatetime);
            const startDatetime = moment(startDateTimeObject).format('YYYY-MM-DD HH:mm:ss');

            const endDateTimeObject = App.Utils.UI.getDateTimePickerValue($endDatetime);
            const endDatetime = moment(endDateTimeObject).format('YYYY-MM-DD HH:mm:ss');

            const appointment = {
                id_services: $selectService.val(),
                id_users_provider: $selectProvider.val(),
                start_datetime: startDatetime,
                end_datetime: endDatetime,
                location: $appointmentLocation.val(),
                meeting_link: $appointmentMeetingLink.val(),
                color: App.Components.ColorSelection.getColor($appointmentColor),
                status: $appointmentStatus.val(),
                is_first_visit: $appointmentFirstVisit.val(),
                price: $appointmentPrice.val(),
                notes: $appointmentNotes.val(),
                is_unavailability: Number(false),
            };

            if ($appointmentId.val() !== '') {
                // Set the id value, only if we are editing an appointment.
                appointment.id = $appointmentId.val();
            }

            const customer = {
                first_name: $firstName.val(),
                last_name: $lastName.val(),
                email: $email.val(),
                phone_number: $phoneNumber.val(),
                address: $address.val(),
                city: $city.val(),
                zip_code: $zipCode.val(),
                language: $language.val(),
                timezone: $timezone.val(),
                notes: $customerNotes.val(),
                custom_field_1: $customField1.val(),
                custom_field_2: $customField2.val(),
                custom_field_3: $customField3.val(),
                custom_field_4: $customField4.val(),
                custom_field_5: $customField5.val(),
            };

            if ($customerId.val() !== '') {
                // Set the id value, only if we are editing an appointment.
                customer.id = $customerId.val();
                appointment.id_users_customer = customer.id;
            }

            // Define success callback.
            const successCallback = () => {
                // Display success message to the user.
                App.Layouts.Backend.displayNotification(lang('appointment_saved'));

                // Close the modal dialog and refresh the calendar appointments.
                $appointmentsModal.find('.alert').addClass('d-none');
                $appointmentsModal.modal('hide');
                $reloadAppointments.trigger('click');
            };

            // Define error callback, with the reason given by the server when it refused the appointment.
            const errorCallback = (response) => {
                $appointmentsModal
                    .find('.modal-message')
                    .text(response?.message || lang('service_communication_error'));
                $appointmentsModal.find('.modal-message').addClass('alert-danger').removeClass('d-none');
                $appointmentsModal.find('.modal-body').scrollTop(0);
            };

            // Check if this is an update (appointment has an ID)
            const isUpdate = Boolean(appointment.id);

            // Ask for the notification preference and save. The notification question comes last, so that the user
            // does not commit to it for an appointment that might never be created.
            const promptNotifyUsersAndSave = (forceSave) => {
                const save = (notifyUsers) => {
                    if (forceSave) {
                        App.Http.Calendar.saveAppointment(
                            appointment,
                            customer,
                            successCallback,
                            errorCallback,
                            notifyUsers,
                            true,
                        );

                        return;
                    }

                    // No conflict was reported, but another user might have booked the slot in the meanwhile.
                    App.Http.Calendar.saveAppointmentWithConflictHandling(
                        appointment,
                        customer,
                        successCallback,
                        errorCallback,
                        notifyUsers,
                    );
                };

                App.Utils.Message.show(
                    isUpdate ? lang('appointment_update') : lang('new_appointment_title'),
                    isUpdate ? lang('notify_users_on_update_question') : lang('notify_users_on_create_question'),
                    [
                        {
                            text: lang('no'),
                            click: (event, messageModal) => {
                                messageModal.hide();
                                save(false);
                            },
                        },
                        {
                            text: lang('yes'),
                            click: (event, messageModal) => {
                                messageModal.hide();
                                save(true);
                            },
                        },
                    ],
                );
            };

            // Prompt for a conflicting event first, so that the user does not answer the notification question for an
            // appointment that will not be created after all.
            const conflictCallback = (response) => {
                if (!response.conflict) {
                    promptNotifyUsersAndSave(false);

                    return;
                }

                // Some rules cannot be confirmed (e.g. an inactive provider): the appointment must be changed.
                if (response.forceable === false) {
                    errorCallback(response);

                    return;
                }

                App.Utils.Message.show(
                    lang('scheduling_conflict'),
                    response.message + ' ' + lang('would_you_like_to_proceed'),
                    [
                        {
                            text: lang('close'),
                            click: (event, messageModal) => {
                                messageModal.hide();
                            },
                        },
                        {
                            text: lang('proceed'),
                            click: (event, messageModal) => {
                                messageModal.hide();
                                promptNotifyUsersAndSave(true);
                            },
                        },
                    ],
                );
            };

            App.Http.Calendar.checkProviderConflict(appointment, conflictCallback, errorCallback);
        });

        /**
         * Event: Insert Appointment Button "Click"
         *
         * When the user presses this button, the manage appointment dialog opens and lets the user create a new
         * appointment.
         */
        $insertAppointment.on('click', () => {
            $('.popover').remove();

            App.Components.AppointmentsModal.resetModal();

            // Set the selected filter item and find the next appointment time as the default modal values.
            if ($selectFilterItem.find('option:selected').attr('type') === 'provider') {
                const providerId = $('#select-filter-item').val();

                const providers = vars('available_providers').filter(
                    (provider) => Number(provider.id) === Number(providerId),
                );

                if (providers.length) {
                    const activeServiceId = providers[0].services.find(
                        (serviceId) => !$selectService.find('option[value="' + serviceId + '"]').prop('hidden'),
                    );

                    $selectService.val(activeServiceId).trigger('change');
                    $selectProvider.val(providerId);
                }
            } else if ($selectFilterItem.find('option:selected').attr('type') === 'service') {
                $selectService.find('option[value="' + $selectFilterItem.val() + '"]').prop('selected', true);
            } else {
                $selectService.find('option:not([hidden]):first').prop('selected', true).trigger('change');
            }

            $selectProvider.trigger('change');

            const serviceId = $selectService.val();

            const service = vars('available_services').find(
                (availableService) => Number(availableService.id) === Number(serviceId),
            );

            const duration = service ? service.duration : 60;

            // Start at the next slot of the calendar grid, without seconds.
            const interval = Number(vars('calendar_slot_minutes')) || 15;

            const startMoment = moment().set({seconds: 0, milliseconds: 0});

            const remainder = startMoment.minutes() % interval;

            if (remainder) {
                startMoment.add(interval - remainder, 'minutes');
            }

            App.Utils.UI.setDateTimePickerValue($startDatetime, startMoment.toDate());
            App.Utils.UI.setDateTimePickerValue($endDatetime, startMoment.add(duration, 'minutes').toDate());

            // Display modal form.
            $appointmentsModal.find('.modal-header h3').text(lang('new_appointment_title'));

            $appointmentsModal.modal('show');
        });

        /**
         * Event: Selected Service "Change"
         *
         * When the user clicks on a service, its available providers should become visible. We also need to
         * update the start and end time of the appointment.
         */
        $selectService.on('change', () => {
            const serviceId = $selectService.val();

            const providerId = $selectProvider.val();

            $selectProvider.empty();

            // Automatically update the service duration.
            const service = vars('available_services').find((availableService) => {
                return Number(availableService.id) === Number(serviceId);
            });

            if (service?.color) {
                App.Components.ColorSelection.setColor($appointmentColor, service.color);
            }

            // A new appointment takes the price of its service, which can then be changed for it.
            if (!$appointmentId.val()) {
                $appointmentPrice.val(service?.price ?? '');
            }

            const duration = service ? service.duration : 60;

            const startDateTimeObject = App.Utils.UI.getDateTimePickerValue($startDatetime);
            const endDateTimeObject = new Date(startDateTimeObject.getTime() + duration * 60000);
            App.Utils.UI.setDateTimePickerValue($endDatetime, endDateTimeObject);
            $appointmentDuration.val(duration);

            // Update the providers select box.

            vars('available_providers').forEach((provider) => {
                provider.services.forEach((providerServiceId) => {
                    if (
                        vars('role_slug') === App.Layouts.Backend.DB_SLUG_PROVIDER &&
                        Number(provider.id) !== vars('user_id')
                    ) {
                        return; // continue
                    }

                    if (
                        vars('role_slug') === App.Layouts.Backend.DB_SLUG_SECRETARY &&
                        vars('secretary_providers').indexOf(Number(provider.id)) === -1
                    ) {
                        return; // continue
                    }

                    // If the current provider is able to provide the selected service, add him to the list box.
                    if (Number(providerServiceId) === Number(serviceId)) {
                        $selectProvider.append(new Option(provider.first_name + ' ' + provider.last_name, provider.id));
                    }
                });

                if ($selectProvider.find(`option[value="${providerId}"]`).length) {
                    $selectProvider.val(providerId);
                }
            });

            updateUsualProviderHint();
        });

        /**
         * Event: Provider "Change"
         */
        $selectProvider.on('change', () => {
            updateTimezone();
            updateUsualProviderHint();
        });

        /**
         * Event: Choose The Usual Provider "Click"
         */
        $usualProviderHint.on('click', 'button', () => {
            $selectProvider.val(usualProviderId).trigger('change');
        });

        /**
         * Event: Enter New Customer Button "Click"
         */
        $newCustomer.on('click', () => {
            markAsNewPatient();
            patientSearch.reset();
            newPatientConfirmed = false;
            usualProviderId = null;
            updateUsualProviderHint();
            showNoShowWarning(null);

            $customerId.val('');
            $firstName.val('');
            $lastName.val('');
            $email.val('');
            $phoneNumber.val('');
            $address.val('');
            $city.val('');
            $zipCode.val('');
            $language.val(vars('default_language'));
            $timezone.val(vars('default_timezone'));
            $customerNotes.val('');
            $customField1.val('');
            $customField2.val('');
            $customField3.val('');
            $customField4.val('');
            $customField5.val('');
        });
    }

    /**
     * Reset Appointment Dialog
     *
     * This method resets the manage appointment dialog modal to its initial state. After that you can make
     * any modification might be necessary in order to bring the dialog to the desired state.
     */
    function resetModal() {
        newPatientConfirmed = false;
        usualProviderId = null;
        updateUsualProviderHint();
        showNoShowWarning(null);

        // Empty form fields.
        $appointmentsModal.find('input, textarea').val('');
        $appointmentsModal.find('.modal-message').addClass('.d-none');
        $appointmentsModal.find('.is-invalid').removeClass('is-invalid');

        const defaultStatusValue = $appointmentStatus.find('option:first').val();
        $appointmentStatus.val(defaultStatusValue);
        $appointmentFirstVisit.val('');
        toggleMoreFields(false);

        $language.val(vars('default_language'));
        $timezone.val(vars('default_timezone'));

        // Reset color.
        $appointmentColor.find('.color-selection-option:first').trigger('click');

        // Prepare service and provider select boxes.
        $selectService.val($selectService.eq(0).attr('value'));

        // Fill the providers list box with providers that can serve the appointment's service and then select the
        // user's provider.
        $selectProvider.empty();
        vars('available_providers').forEach((provider) => {
            const serviceId = $selectService.val();

            const canProvideService =
                provider.services.filter((providerServiceId) => {
                    return Number(providerServiceId) === Number(serviceId);
                }).length > 0;

            if (canProvideService) {
                // Add the provider to the list box.
                $selectProvider.append(new Option(provider.first_name + ' ' + provider.last_name, provider.id));
            }
        });

        patientSearch.reset();

        // Setup start and datetimepickers.
        // Get the selected service duration. It will be needed in order to calculate the appointment end datetime.
        const serviceId = $selectService.val();

        const service = vars('available_services').forEach((service) => Number(service.id) === Number(serviceId));

        const duration = service ? service.duration : 0;

        const startDatetime = new Date();
        const endDatetime = moment().add(duration, 'minutes').toDate();

        App.Utils.UI.initializeDateTimePicker($startDatetime, {
            onClose: () => {
                const serviceId = $selectService.val();

                const service = vars('available_services').find(
                    (availableService) => Number(availableService.id) === Number(serviceId),
                );

                // Moving the start keeps the chosen duration, or uses the service one when there is none yet.
                applyDuration(service?.duration);
            },
        });

        App.Utils.UI.setDateTimePickerValue($startDatetime, startDatetime);

        App.Utils.UI.initializeDateTimePicker($endDatetime, {
            onClose: () => updateDurationField(),
        });
        App.Utils.UI.setDateTimePickerValue($endDatetime, endDatetime);
        $appointmentsModal.find('.modal-message').removeClass('alert-danger').text('').addClass('d-none');
    }

    /**
     * Validate the manage appointment dialog data.
     *
     * Validation checks need to run every time the data are going to be saved.
     *
     * @return {Boolean} Returns the validation result.
     */
    function validateAppointmentForm() {
        // Reset previous validation css formatting.
        $appointmentsModal.find('.is-invalid').removeClass('is-invalid');
        $appointmentsModal.find('.modal-message').addClass('d-none');

        try {
            // Check required fields.
            let missingRequiredField = false;

            $appointmentsModal.find('.required').each((index, requiredField) => {
                if ($(requiredField).val() === '' || $(requiredField).val() === null) {
                    $(requiredField).addClass('is-invalid');
                    missingRequiredField = true;
                }
            });

            if (missingRequiredField) {
                throw new Error(lang('fields_are_required'));
            }

            if (!(parseInt($appointmentDuration.val(), 10) >= MIN_DURATION)) {
                $appointmentDuration.addClass('is-invalid');
                throw new Error(lang('appointment_duration_invalid'));
            }

            // Check email address.
            if (
                $appointmentsModal.find('#email').val() &&
                !App.Utils.Validation.email($appointmentsModal.find('#email').val())
            ) {
                $appointmentsModal.find('#email').addClass('is-invalid');
                throw new Error(lang('invalid_email'));
            }

            // Check phone number.
            if (
                $appointmentsModal.find('#phone-number').val() &&
                !App.Utils.Validation.phone($appointmentsModal.find('#phone-number').val())
            ) {
                $appointmentsModal.find('#phone-number').addClass('is-invalid');
                throw new Error(lang('invalid_phone'));
            }

            // Check appointment start and end time.
            const startDateTimeObject = App.Utils.UI.getDateTimePickerValue($startDatetime);
            const endDateTimeObject = App.Utils.UI.getDateTimePickerValue($endDatetime);

            if (startDateTimeObject > endDateTimeObject) {
                $startDatetime.addClass('is-invalid');
                $endDatetime.addClass('is-invalid');
                throw new Error(lang('start_date_before_end_error'));
            }

            return true;
        } catch (error) {
            $appointmentsModal
                .find('.modal-message')
                .addClass('alert-danger')
                .text(error.message)
                .removeClass('d-none');

            // The message is rendered on top of the modal, while the save button is at the bottom of it.
            $appointmentsModal.find('.modal-body').scrollTop(0);

            return false;
        }
    }

    /**
     * Initialize the module.
     */
    /**
     * A patient that is registered now has not visited the clinic before, so a new appointment is their first visit.
     */
    function markAsNewPatient() {
        if (!$appointmentId.val()) {
            $appointmentFirstVisit.val('1');
        }
    }

    /**
     * Fill the patient fields with an existing patient.
     *
     * @param {Object} customer
     */
    function fillCustomer(customer) {
        $customerId.val(customer.id);
        $firstName.val(customer.first_name);
        $lastName.val(customer.last_name);
        $email.val(customer.email);
        $phoneNumber.val(customer.phone_number);
        $address.val(customer.address);
        $city.val(customer.city);
        $zipCode.val(customer.zip_code);
        $language.val(customer.language);
        $timezone.val(customer.timezone);
        $customerNotes.val(customer.notes);
        $customField1.val(customer.custom_field_1);
        $customField2.val(customer.custom_field_2);
        $customField3.val(customer.custom_field_3);
        $customField4.val(customer.custom_field_4);
        $customField5.val(customer.custom_field_5);
        $appointmentsModal.find('.is-invalid').removeClass('is-invalid');

        usualProviderId = customer.id_users_preferred_provider || null;
        updateUsualProviderHint();
        showNoShowWarning(customer);

        // For a new appointment, the visits of the patient tell whether it is the first one.
        if (!$appointmentId.val() && customer.visits !== undefined) {
            $appointmentFirstVisit.val(customer.visits > 0 ? '0' : '1');
        }
    }

    /**
     * Warn when the chosen patient missed appointments before, so that reception can remind them.
     *
     * @param {Object|null} customer Patient with no_shows and last_no_show, or null to hide the warning.
     */
    function showNoShowWarning(customer) {
        const count = Number(customer?.no_shows || 0);

        $noShowWarning.prop('hidden', !count).empty();

        if (!count) {
            return;
        }

        $noShowWarning.append(
            $('<i/>', {class: 'fas fa-user-slash me-2'}),
            document.createTextNode(
                lang('no_show_warning')
                    .replace('%count', count)
                    .replace('%date', moment(customer.last_no_show).format('DD/MM/YYYY')),
            ),
        );
    }

    /**
     * Show the usual provider of the selected patient under the provider field, with a button to choose them when
     * another provider is selected. The selected provider is never changed by itself: it may come from the column
     * where the appointment was created.
     */
    function updateUsualProviderHint() {
        const provider = vars('available_providers').find(
            (availableProvider) => Number(availableProvider.id) === Number(usualProviderId),
        );

        $usualProviderHint.empty().prop('hidden', !provider);

        if (!provider) {
            return;
        }

        $usualProviderHint.append(
            $('<i/>', {class: 'fas fa-user-check me-1'}),
            document.createTextNode(
                lang('usual_provider') + ': ' + [provider.first_name, provider.last_name].filter(Boolean).join(' '),
            ),
        );

        if (Number($selectProvider.val()) === Number(provider.id)) {
            return;
        }

        if ($selectProvider.find('option[value="' + provider.id + '"]').length) {
            $('<button/>', {
                type: 'button',
                class: 'btn btn-link btn-sm p-0 ms-2 align-baseline',
                text: lang('use_usual_provider'),
            }).appendTo($usualProviderHint);
        } else {
            $usualProviderHint.append(document.createTextNode(' · ' + lang('usual_provider_without_service')));
        }
    }

    function initialize() {
        addEventListeners();

        patientSearch = App.Components.PatientSearch.attach({
            $firstName,
            $lastName,
            $phoneNumber,
            isEnabled: () => !$customerId.val(),
            onSelect: (customerId) => App.Http.Customers.find(customerId).done(fillCustomer),
            onCreate: markAsNewPatient,
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        resetModal,
        validateAppointmentForm,
    };
})();
