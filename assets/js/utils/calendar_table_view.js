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
 * Calendar Table View Utility
 *
 * This module implements the table calendar view functionality, displaying
 * multiple provider columns side by side for easy comparison and scheduling.
 */
App.Utils.CalendarTableView = (function () {
    // Constants

    const EVENT_COLORS = {
        unavailability: '#879DB4',
        notWorking: '#BEBEBE',
        workingPlanException: '#879DB4',
        default: '#7cbae8',
    };

    // DOM Elements Cache

    const $calendar = $('#calendar');
    const $calendarToolbar = $('#calendar-toolbar');
    const $calendarFilter = $('#calendar-filter');
    const $notification = $('#notification');
    const $reloadAppointments = $('#reload-appointments');
    const $selectFilterItem = $('#select-filter-item');
    const $selectService = $('#select-service');
    const $selectProvider = $('#select-provider');
    const $appointmentsModal = $('#appointments-modal');
    const $unavailabilitiesModal = $('#unavailabilities-modal');
    const $header = $('#header');

    // Module State

    const moment = window.moment;

    let $filterProvider;
    let $filterService;
    let $selectDate;
    let $popoverTarget = null;
    let lastFocusedEventData = null;
    let lastResponseSignature = null;

    const MIN_PROVIDER_COLUMN_WIDTH = 200; // Pixels
    const START_TIME = '08:30:00'; // First time shown when a period is opened

    /**
     * Get the last filters used by the current user in this browser.
     *
     * @return {Object} {providers: String[], services: String[], days: String}, empty when nothing is stored.
     */
    function loadPreferences() {
        try {
            return JSON.parse(localStorage.getItem('ea-table-view-' + vars('user_id'))) || {};
        } catch (error) {
            return {}; // Storage disabled or invalid value.
        }
    }

    /**
     * Remember the current filters of the user in this browser.
     */
    function savePreferences() {
        try {
            localStorage.setItem(
                'ea-table-view-' + vars('user_id'),
                JSON.stringify({
                    providers: $filterProvider.val(),
                    services: $filterService.val(),
                    days: $selectFilterItem.val(),
                }),
            );
        } catch (error) {
            // Storage disabled, the filters are simply not remembered.
        }
    }

    /**
     * Select the stored values that still exist as options.
     *
     * @param {jQuery} $select Select element.
     * @param {String[]|String|undefined} values Stored values.
     */
    function restoreSelection($select, values) {
        const options = $select
            .find('option')
            .toArray()
            .map((option) => option.value);

        const restored = [].concat(values ?? []).filter((value) => options.includes(String(value)));

        if (restored.length) {
            $select.val($select.prop('multiple') ? restored : restored[0]);
        }
    }

    /**
     * Show next to the provider name what their appointments of the column day add up to, e.g. "Sebas (330€)". It
     * follows the treatment filter, like the appointments shown.
     *
     * @param {jQuery} $providerColumn
     * @param {Array} appointments Appointments of the displayed period.
     */
    function showProviderDayTotal($providerColumn, appointments) {
        const providerId = Number($providerColumn.data('provider').id);
        const day = moment($providerColumn.closest('.date-column').data('date')).format('YYYY-MM-DD');
        const serviceId = Number($filterService.val()) || null;

        const total = App.Utils.CalendarDayTotal.sum(
            (appointments || []).filter(
                (appointment) =>
                    Number(appointment.id_users_provider) === providerId &&
                    appointment.start_datetime.startsWith(day) &&
                    (!serviceId || Number(appointment.id_services) === serviceId),
            ),
        );

        $providerColumn.find('.ea-provider-total').text(' (' + App.Utils.CalendarDayTotal.format(total) + ')');
    }

    /**
     * Name a day in relation to today: "Today, Friday 13/11", "Tomorrow, …", "Day after tomorrow, …", "next Thursday
     * 19/11" for the rest of the coming week, and just "Thursday 26/11" for other days.
     *
     * @param {Date} date
     *
     * @return {String}
     */
    function getRelativeDayLabel(date) {
        const text = getRelativeDayText(date);

        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    /**
     * Get the label of getRelativeDayLabel(), before its first letter is turned into a capital.
     *
     * @param {Date} date
     *
     * @return {String}
     */
    function getRelativeDayText(date) {
        const day = moment(date).startOf('day');
        const days = day.diff(moment().startOf('day'), 'days');
        const label = day.format('dddd D/M');

        if (days === 0) {
            return lang('today') + ', ' + label;
        }

        if (days === 1) {
            return lang('tomorrow') + ', ' + label;
        }

        if (days === 2) {
            return lang('day_after_tomorrow') + ', ' + label;
        }

        // Only within the coming week is it really the next day with that name.
        if (days > 2 && days < 7) {
            return lang('next_weekday').replace('%s', label);
        }

        return label;
    }

    /**
     * Get the display name of a provider, whose last name is optional.
     *
     * @param {Object} provider
     *
     * @return {String}
     */
    function getProviderName(provider) {
        return [provider.first_name, provider.last_name].filter(Boolean).join(' ');
    }

    // Helper Functions

    /**
     * Get the calendar height based on window size.
     *
     * @returns {number} Calendar height in pixels (minimum 700px).
     */
    function getCalendarHeight() {
        const offset = $header.outerHeight() + $calendarToolbar.outerHeight() + 50;
        const height = window.innerHeight - offset;

        return Math.max(height, 700);
    }

    /**
     * Close the current popover if open.
     */
    function closePopover() {
        if ($popoverTarget) {
            $popoverTarget.popover('dispose');
            $popoverTarget = null;
        }
    }

    /**
     * Check if the event is an unavailability.
     *
     * @param {Object} eventData - Event data object.
     * @returns {boolean}
     */
    function isUnavailability(eventData) {
        return Boolean(Number(eventData?.is_unavailability));
    }

    /**
     * Check if the event is a working plan exception.
     *
     * @param {Object} eventData - Event data object.
     * @returns {boolean}
     */
    function isWorkingPlanException(eventData) {
        return eventData?.workingPlanException !== undefined;
    }

    /**
     * Check if the event is a blocked period.
     *
     * @param {Object} eventData - Event data object.
     * @returns {boolean}
     */
    function isBlockedPeriod(eventData) {
        return eventData?.hasOwnProperty('name') && !eventData?.hasOwnProperty('is_unavailability');
    }

    /**
     * Get available providers based on user role.
     *
     * @returns {Array} Filtered providers array.
     */
    function getAvailableProviders() {
        const roleSlug = vars('role_slug');
        const userId = vars('user_id');

        return vars('available_providers').filter((provider) => {
            if (roleSlug === App.Layouts.Backend.DB_SLUG_ADMIN) {
                return true;
            }

            if (roleSlug === App.Layouts.Backend.DB_SLUG_SECRETARY) {
                return vars('secretary_providers').indexOf(Number(provider.id)) !== -1;
            }

            if (roleSlug === App.Layouts.Backend.DB_SLUG_PROVIDER) {
                return Number(provider.id) === Number(userId);
            }

            return false;
        });
    }

    /**
     * Get date/time format settings for FullCalendar.
     *
     * @returns {Object} Format configuration object.
     */
    function getFormatSettings() {
        let columnFormat, timeFormat, slotTimeFormat;
        switch (vars('date_format')) {
            case 'DMY':
                columnFormat = 'dddd D/M';
                break;
            case 'MDY':
            case 'YMD':
                columnFormat = 'ddd M/D';
                break;
            default:
                throw new Error('Invalid date format setting: ' + vars('date_format'));
        }

        switch (vars('time_format')) {
            case 'military':
                timeFormat = 'HH:mm';
                slotTimeFormat = 'HH:mm';
                break;
            case 'regular':
                timeFormat = 'h:mm a';
                slotTimeFormat = 'h a';
                break;
            default:
                throw new Error('Invalid time format setting: ' + vars('time_format'));
        }

        return {columnFormat, timeFormat, slotTimeFormat};
    }

    // Modal Population Helpers

    /**
     * Populate the appointments modal with appointment data.
     *
     * @param {Object} appointment - Appointment data object.
     */
    function populateAppointmentModal(appointment) {
        const customer = appointment.customer;

        App.Components.AppointmentsModal.resetModal();
        $appointmentsModal.find('.modal-header h3').text(lang('edit_appointment_title'));
        $appointmentsModal.find('#appointment-id').val(appointment.id);
        $appointmentsModal.find('#select-service').val(appointment.id_services).trigger('change');
        $appointmentsModal.find('#select-provider').val(appointment.id_users_provider);
        App.Utils.UI.setDateTimePickerValue(
            $appointmentsModal.find('#start-datetime'),
            moment(appointment.start_datetime).toDate(),
        );
        App.Utils.UI.setDateTimePickerValue(
            $appointmentsModal.find('#end-datetime'),
            moment(appointment.end_datetime).toDate(),
        );

        // Customer fields

        $appointmentsModal.find('#customer-id').val(appointment.id_users_customer);
        $appointmentsModal.find('#first-name').val(customer.first_name);
        $appointmentsModal.find('#last-name').val(customer.last_name);
        $appointmentsModal.find('#email').val(customer.email);
        $appointmentsModal.find('#phone-number').val(customer.phone_number);
        $appointmentsModal.find('#address').val(customer.address);
        $appointmentsModal.find('#city').val(customer.city);
        $appointmentsModal.find('#zip-code').val(customer.zip_code);
        $appointmentsModal.find('#language').val(customer.language);
        $appointmentsModal.find('#timezone').val(customer.timezone);
        $appointmentsModal.find('#customer-notes').val(customer.notes);
        $appointmentsModal.find('#custom-field-1').val(customer.custom_field_1);
        $appointmentsModal.find('#custom-field-2').val(customer.custom_field_2);
        $appointmentsModal.find('#custom-field-3').val(customer.custom_field_3);
        $appointmentsModal.find('#custom-field-4').val(customer.custom_field_4);
        $appointmentsModal.find('#custom-field-5').val(customer.custom_field_5);

        // Appointment fields

        $appointmentsModal.find('#appointment-location').val(appointment.location);
        $appointmentsModal.find('#appointment-meeting-link').val(appointment.meeting_link);
        $appointmentsModal.find('#appointment-status').val(appointment.status);
        $appointmentsModal.find('#appointment-price').val(appointment.price ?? '');
        $appointmentsModal.find('#appointment-notes').val(appointment.notes);
        App.Components.ColorSelection.setColor($appointmentsModal.find('#appointment-color'), appointment.color);
        $appointmentsModal.modal('show');
    }

    /**
     * Populate the unavailabilities modal with data.
     *
     * @param {Object} unavailability - Unavailability data object.
     */
    function populateUnavailabilityModal(unavailability) {
        App.Components.UnavailabilitiesModal.resetModal();
        $unavailabilitiesModal.find('.modal-header h3').text(lang('edit_unavailability_title'));
        App.Utils.UI.setDateTimePickerValue(
            $unavailabilitiesModal.find('#unavailability-start'),
            moment(unavailability.start_datetime).toDate(),
        );
        App.Utils.UI.setDateTimePickerValue(
            $unavailabilitiesModal.find('#unavailability-end'),
            moment(unavailability.end_datetime).toDate(),
        );
        $unavailabilitiesModal.find('#unavailability-id').val(unavailability.id);
        $unavailabilitiesModal.find('#unavailability-provider').val(unavailability.id_users_provider);
        $unavailabilitiesModal.find('#unavailability-notes').val(unavailability.notes);
        $unavailabilitiesModal.modal('show');
    }

    // Working Plan Exception Handlers

    /**
     * Update provider's working plan exceptions in memory.
     *
     * @param {number} providerId - Provider ID.
     * @param {Object} workingPlanExceptions - Updated exceptions object.
     */
    function updateProviderWorkingPlanExceptions(providerId, workingPlanExceptions) {
        for (const provider of vars('available_providers')) {
            if (Number(provider.id) === Number(providerId)) {
                provider.settings.working_plan_exceptions = JSON.stringify(workingPlanExceptions);
                break;
            }
        }
    }

    /**
     * Handle editing a working plan exception.
     *
     * @param {Object} data - Working plan exception data.
     */
    function handleEditWorkingPlanException(data) {
        const workingPlanException = data.workingPlanException;
        const provider = data.provider;

        App.Components.WorkingPlanExceptionsModal.edit(workingPlanException).done((updatedException) => {
            const successCallback = (response) => {
                App.Layouts.Backend.displayNotification(lang('working_plan_exception_saved'));

                // Update the in-memory provider data
                let exceptions = JSON.parse(provider.settings.working_plan_exceptions || '[]');
                if (!Array.isArray(exceptions)) {
                    exceptions = [];
                }

                // Find and update the exception, or add if new
                const existingIndex = exceptions.findIndex((e) => e.id === updatedException.id);
                if (existingIndex >= 0) {
                    exceptions[existingIndex] = updatedException;
                } else {
                    if (response && response.id) {
                        updatedException.id = response.id;
                    }
                    exceptions.push(updatedException);
                }
                provider.settings.working_plan_exceptions = JSON.stringify(exceptions);

                $reloadAppointments.trigger('click');
            };
            App.Http.Calendar.saveWorkingPlanException(updatedException, provider.id, successCallback, null);
        });
    }

    /**
     * Handle deleting a working plan exception.
     *
     * @param {Object} eventData - Event data containing provider info.
     */
    function handleDeleteWorkingPlanException(eventData) {
        const providerId = eventData.id;
        const data = lastFocusedEventData.extendedProps.data;
        const exception = data.workingPlanException;
        const provider = data.provider;

        if (!exception || !exception.id) {
            App.Layouts.Backend.displayNotification(lang('working_plan_exception_deleted'));
            $reloadAppointments.trigger('click');
            return;
        }

        const successCallback = () => {
            App.Layouts.Backend.displayNotification(lang('working_plan_exception_deleted'));

            // Update the in-memory provider data by removing the exception
            if (provider) {
                let exceptions = JSON.parse(provider.settings.working_plan_exceptions || '[]');
                if (Array.isArray(exceptions)) {
                    exceptions = exceptions.filter((e) => e.id !== exception.id);
                    provider.settings.working_plan_exceptions = JSON.stringify(exceptions);
                }
            }

            $reloadAppointments.trigger('click');
        };
        App.Http.Calendar.deleteWorkingPlanException(exception.id, providerId, successCallback);
    }

    /**
     * Show appointment deletion confirmation dialog.
     *
     * @param {number} appointmentId - Appointment ID to delete.
     */
    function handleDeleteAppointment(appointmentId) {
        App.Utils.Message.show(lang('delete_appointment_title'), lang('notify_users_on_delete_question'), [
            {
                text: lang('close'),
                click: (event, notifyModal) => notifyModal.hide(),
            },
            {
                text: lang('no'),
                click: (event, notifyModal) => {
                    notifyModal.hide();
                    App.Http.Calendar.deleteAppointment(appointmentId, null, false).done(() => {
                        $reloadAppointments.trigger('click');
                    });
                },
            },
            {
                text: lang('yes'),
                click: (event, notifyModal) => {
                    notifyModal.hide();

                    const reasonButtons = [
                        {
                            text: lang('close'),
                            click: (event, messageModal) => messageModal.hide(),
                        },
                        {
                            text: lang('delete'),
                            click: (event, messageModal) => {
                                const reason = $('#cancellation-reason').val();
                                messageModal.hide();
                                App.Http.Calendar.deleteAppointment(appointmentId, reason, true).done(() => {
                                    $reloadAppointments.trigger('click');
                                });
                            },
                        },
                    ];

                    App.Utils.Message.show(
                        lang('delete_appointment_title'),
                        lang('write_appointment_removal_reason'),
                        reasonButtons,
                    );

                    $('<textarea/>', {
                        class: 'form-control w-100',
                        id: 'cancellation-reason',
                        rows: '3',
                    }).appendTo('#message-modal .modal-body');
                },
            },
        ]);
    }

    // Event Handlers - Popover Actions

    /**
     * Handle edit popover button click.
     */
    function onEditPopoverClick() {
        closePopover();

        const data = lastFocusedEventData.extendedProps.data;

        if (isWorkingPlanException(data)) {
            handleEditWorkingPlanException(data);
        } else if (!isUnavailability(data)) {
            populateAppointmentModal(data);
        } else {
            const unavailability = {
                ...data,
                start_datetime: moment(lastFocusedEventData.start).format('YYYY-MM-DD HH:mm:ss'),
                end_datetime: moment(lastFocusedEventData.end).format('YYYY-MM-DD HH:mm:ss'),
            };

            populateUnavailabilityModal(unavailability);
        }
    }

    /**
     * Handle delete popover button click.
     */
    function onDeletePopoverClick() {
        closePopover();
        const data = lastFocusedEventData.extendedProps.data;

        if (data.hasOwnProperty('id_roles')) {
            // Working plan exception

            handleDeleteWorkingPlanException(data);
        } else if (!isUnavailability(data)) {
            App.Utils.CalendarAppointmentActions.confirmDelete(() => handleDeleteAppointment(data.id));
        } else {
            App.Http.Calendar.deleteUnavailability(data.id).done(() => {
                $reloadAppointments.trigger('click');
            });
        }
    }

    /**
     * Handle cancel appointment popover button click.
     */
    function onCancelAppointmentPopoverClick() {
        closePopover();

        App.Utils.CalendarAppointmentActions.cancel(lastFocusedEventData.extendedProps.data, () =>
            $reloadAppointments.trigger('click'),
        );
    }

    // Calendar Event Callbacks

    /**
     * Handle calendar event click - show popover.
     *
     * @param {Object} info - FullCalendar event info.
     */
    function onEventClick(info) {
        closePopover();
        const $target = $(info.el);

        let $html;
        let displayEdit;
        let displayDelete;

        if ($target.hasClass('fc-blocked-period')) {
            // Blocked periods are read-only

            $html = App.Utils.CalendarEventPopover.buildBlockedPeriodPopover(info);
        } else if ($target.hasClass('fc-working-plan-exception')) {
            displayEdit = $target.hasClass('fc-custom') && vars('privileges').appointments.edit ? '' : 'd-none';
            displayDelete = $target.hasClass('fc-custom') && vars('privileges').appointments.delete ? 'me-2' : 'd-none';
            $html = App.Utils.CalendarEventPopover.buildWorkingPlanExceptionPopover(info, displayEdit, displayDelete);
        } else if ($target.hasClass('fc-unavailability')) {
            const isCustom = $target.hasClass('fc-custom');

            displayEdit = isCustom && vars('privileges').appointments.edit ? '' : 'd-none';
            displayDelete = isCustom && vars('privileges').appointments.delete ? 'me-2' : 'd-none';
            $html = App.Utils.CalendarEventPopover.buildUnavailabilityPopover(info, displayEdit, displayDelete);
        } else {
            displayEdit = vars('privileges').appointments.edit ? '' : 'd-none';
            displayDelete = vars('privileges').appointments.delete ? 'me-2' : 'd-none';
            $html = App.Utils.CalendarEventPopover.buildAppointmentPopover(info, displayEdit, displayDelete);
        }

        $target.popover({
            placement: 'top',
            title: App.Utils.String.escapeHtml(info.event.title),
            content: $html,
            html: true,
            container: '#calendar',
            trigger: 'manual',
        });
        lastFocusedEventData = info.event;
        $target.popover('show');
        $popoverTarget = $target;

        // Fix popover position if too high

        const $popover = $calendar.find('.popover');

        if ($popover.length && $popover.position().top < 200) {
            $popover.css('top', '200px');
        }
    }

    /**
     * Handle calendar event resize.
     *
     * @param {Object} info - FullCalendar event info.
     */
    function onEventResize(info) {
        if (!vars('privileges').appointments.edit) {
            info.revert();
            App.Layouts.Backend.displayNotification(lang('no_privileges_edit_appointments'));
            return;
        }

        if ($notification.is(':visible')) {
            $notification.hide('bind');
        }

        const eventData = info.event.extendedProps.data;

        if (!isUnavailability(eventData)) {
            handleAppointmentResize(info, eventData);
        } else {
            handleUnavailabilityResize(info, eventData);
        }
    }

    /**
     * Handle appointment resize operation.
     *
     * @param {Object} info - FullCalendar resize info.
     * @param {Object} eventData - Event data.
     */
    function handleAppointmentResize(info, eventData) {
        eventData.end_datetime = moment(eventData.end_datetime)
            .add({days: info.endDelta.days, milliseconds: info.endDelta.milliseconds})
            .format('YYYY-MM-DD HH:mm:ss');

        const appointment = prepareAppointmentForSave(eventData);

        const successCallback = (notifyUsers) => {
            const undoFunction = () => {
                appointment.end_datetime = eventData.end_datetime = moment(appointment.end_datetime)
                    .add({days: -info.endDelta.days, milliseconds: -info.endDelta.milliseconds})
                    .format('YYYY-MM-DD HH:mm:ss');

                App.Http.Calendar.saveAppointment(appointment, null, null, null, notifyUsers).done(() =>
                    $notification.hide('blind'),
                );
                info.revert();
            };

            App.Layouts.Backend.displayNotification(lang('appointment_updated'), [
                {label: lang('undo'), function: undoFunction},
            ]);
            info.event.setProp('data', eventData);
        };
        showNotifyUsersDialog(appointment, successCallback, () => info.revert());
    }

    /**
     * Handle unavailability resize operation.
     *
     * @param {Object} info - FullCalendar resize info.
     * @param {Object} eventData - Event data.
     */
    function handleUnavailabilityResize(info, eventData) {
        const unavailability = {
            id: eventData.id,
            start_datetime: moment(info.event.start).format('YYYY-MM-DD HH:mm:ss'),
            end_datetime: moment(info.event.end).format('YYYY-MM-DD HH:mm:ss'),
            id_users_provider: eventData.id_users_provider,
        };

        eventData.end_datetime = unavailability.end_datetime;

        const successCallback = () => {
            const undoFunction = () => {
                unavailability.end_datetime = eventData.end_datetime = moment(unavailability.end_datetime)
                    .add({days: -info.endDelta.days, milliseconds: -info.endDelta.milliseconds})
                    .format('YYYY-MM-DD HH:mm:ss');

                App.Http.Calendar.saveUnavailability(unavailability, null, null, true).done(() =>
                    $notification.hide('blind'),
                );
                info.revert();
            };

            App.Layouts.Backend.displayNotification(lang('unavailability_updated'), [
                {label: lang('undo'), function: undoFunction},
            ]);

            info.event.setProp('data', eventData);
        };
        App.Http.Calendar.saveUnavailability(unavailability, successCallback, null, false, () => info.revert());
    }

    /**
     * Handle calendar event drop (drag and drop).
     *
     * @param {Object} info - FullCalendar event info.
     */
    function onEventDrop(info) {
        if (!vars('privileges').appointments.edit) {
            info.revert();
            App.Layouts.Backend.displayNotification(lang('no_privileges_edit_appointments'));
            return;
        }

        if ($notification.is(':visible')) {
            $notification.hide('bind');
        }

        const eventData = info.event.extendedProps.data;

        if (!isUnavailability(eventData)) {
            handleAppointmentDrop(info, eventData);
        } else {
            handleUnavailabilityDrop(info, eventData);
        }
    }

    /**
     * Handle appointment drop operation.
     *
     * @param {Object} info - FullCalendar drop info.
     * @param {Object} eventData - Event data.
     */
    function handleAppointmentDrop(info, eventData) {
        const appointment = prepareAppointmentForSave(eventData);

        appointment.start_datetime = moment(appointment.start_datetime)
            .add({days: info.delta.days, milliseconds: info.delta.milliseconds})
            .format('YYYY-MM-DD HH:mm:ss');
        appointment.end_datetime = moment(appointment.end_datetime)
            .add({days: info.delta.days, milliseconds: info.delta.milliseconds})
            .format('YYYY-MM-DD HH:mm:ss');
        appointment.is_unavailability = 0;
        eventData.start_datetime = appointment.start_datetime;
        eventData.end_datetime = appointment.end_datetime;
        const successCallback = (notifyUsers) => {
            const undoFunction = () => {
                const delta = {days: -info.delta.days, milliseconds: -info.delta.milliseconds};

                appointment.start_datetime = moment(appointment.start_datetime)
                    .add(delta)
                    .format('YYYY-MM-DD HH:mm:ss');
                appointment.end_datetime = moment(appointment.end_datetime).add(delta).format('YYYY-MM-DD HH:mm:ss');
                eventData.start_datetime = appointment.start_datetime;
                eventData.end_datetime = appointment.end_datetime;
                App.Http.Calendar.saveAppointment(appointment, null, null, null, notifyUsers).done(() =>
                    $notification.hide('blind'),
                );
                info.revert();
            };
            App.Layouts.Backend.displayNotification(lang('appointment_updated'), [
                {label: lang('undo'), function: undoFunction},
            ]);
        };
        showNotifyUsersDialog(appointment, successCallback, () => info.revert());
    }

    /**
     * Handle unavailability drop operation.
     *
     * @param {Object} info - FullCalendar drop info.
     * @param {Object} eventData - Event data.
     */
    function handleUnavailabilityDrop(info, eventData) {
        const unavailability = {
            id: eventData.id,
            start_datetime: moment(info.event.start).format('YYYY-MM-DD HH:mm:ss'),
            end_datetime: moment(info.event.end).format('YYYY-MM-DD HH:mm:ss'),
            id_users_provider: eventData.id_users_provider,
        };

        const successCallback = () => {
            const undoFunction = () => {
                const delta = {days: -info.delta.days, milliseconds: -info.delta.milliseconds};

                unavailability.start_datetime = moment(unavailability.start_datetime)
                    .add(delta)
                    .format('YYYY-MM-DD HH:mm:ss');
                unavailability.end_datetime = moment(unavailability.end_datetime)
                    .add(delta)
                    .format('YYYY-MM-DD HH:mm:ss');
                unavailability.is_unavailability = 1;
                eventData.start_datetime = unavailability.start_datetime;
                eventData.end_datetime = unavailability.end_datetime;
                App.Http.Calendar.saveUnavailability(unavailability, null, null, true).done(() =>
                    $notification.hide('blind'),
                );
                info.revert();
            };
            App.Layouts.Backend.displayNotification(lang('unavailability_updated'), [
                {label: lang('undo'), function: undoFunction},
            ]);
        };
        App.Http.Calendar.saveUnavailability(unavailability, successCallback, null, false, () => info.revert());
    }

    /**
     * Prepare appointment object for saving (remove related data).
     *
     * @param {Object} eventData - Event data object.
     * @returns {Object} Clean appointment object.
     */
    function prepareAppointmentForSave(eventData) {
        const appointment = {...eventData};

        appointment.is_unavailability = Number(appointment.is_unavailability) || 0;
        delete appointment.customer;
        delete appointment.provider;
        delete appointment.service;

        return appointment;
    }

    /**
     * Show dialog asking whether to notify users.
     *
     * @param {Object} appointment - Appointment data.
     * @param {Function} successCallback - Callback on success.
     * @param {Function} revertCallback - Callback to revert changes.
     */
    function showNotifyUsersDialog(appointment, successCallback, revertCallback) {
        App.Utils.Message.show(lang('appointment_update'), lang('notify_users_on_update_question'), [
            {
                text: lang('no'),
                click: (event, messageModal) => {
                    messageModal.hide();
                    App.Http.Calendar.saveAppointmentWithConflictHandling(
                        appointment,
                        null,
                        () => successCallback(false),
                        null,
                        false,
                        revertCallback,
                    );
                },
            },
            {
                text: lang('yes'),
                click: (event, messageModal) => {
                    messageModal.hide();
                    App.Http.Calendar.saveAppointmentWithConflictHandling(
                        appointment,
                        null,
                        () => successCallback(true),
                        null,
                        true,
                        revertCallback,
                    );
                },
            },
        ]);
    }

    /**
     * Handle calendar selection (drag to create).
     *
     * @param {Object} info - FullCalendar selection info.
     * @param {Object} fullCalendar - FullCalendar instance.
     */
    function onSelect(info, fullCalendar) {
        if (info.allDay) {
            return;
        }

        const $providerColumn = $(info.jsEvent.target).parents('.provider-column');
        const providerId = $providerColumn.data('provider').id;

        const buttons = [
            {
                text: lang('unavailability'),
                click: (event, messageModal) => {
                    $('#insert-unavailability').trigger('click');

                    if (providerId) {
                        $('#unavailability-provider').val(providerId);
                    } else {
                        $('#unavailability-provider option:first').prop('selected', true);
                    }

                    $('#unavailability-provider').trigger('change');
                    App.Utils.UI.setDateTimePickerValue($('#unavailability-start'), info.start);
                    App.Utils.UI.setDateTimePickerValue($('#unavailability-end'), info.end);
                    messageModal.hide();
                },
            },
            {
                text: lang('appointment'),
                click: (event, messageModal) => {
                    $('#insert-appointment').trigger('click');
                    const provider = vars('available_providers').find(
                        (provider) => Number(provider.id) === Number(providerId),
                    );

                    const service = vars('available_services').find(
                        (service) => provider.services.indexOf(service.id) !== -1,
                    );

                    if (service) {
                        $selectService.val(service.id);
                    }

                    if (!$selectService.val()) {
                        $selectService.find('option:first').prop('selected', true);
                    }

                    $selectService.trigger('change');
                    if (provider) {
                        $selectProvider.val(provider.id);
                    }

                    if (!$selectProvider.val()) {
                        $('#select-provider option:first').prop('selected', true);
                    }

                    $selectProvider.trigger('change');

                    // Preselect time

                    App.Utils.UI.setDateTimePickerValue($('#start-datetime'), info.start);
                    App.Utils.UI.setDateTimePickerValue(
                        $('#end-datetime'),
                        App.Pages.Calendar.getSelectionEndDate(info),
                    );
                    messageModal.hide();
                },
            },
        ];
        App.Utils.Message.show(lang('add_new_event'), lang('what_kind_of_event'), buttons);
        $('#message-modal .modal-footer')
            .addClass('justify-content-between')
            .find('.btn')
            .css('width', 'calc(50% - 10px)');
        fullCalendar.unselect();

        return false;
    }

    // Calendar Event Source Creation

    /**
     * Create appointment calendar events.
     *
     * @param {jQuery} $providerColumn - Provider column element.
     * @param {Array} appointments - Appointment data array.
     */
    function createAppointments($providerColumn, appointments) {
        $providerColumn.data('middayAppointments', appointments);
        App.Utils.CalendarMidday.schedule();
        showProviderDayTotal($providerColumn, appointments);
        if (!appointments.length) {
            return;
        }

        const providerId = $providerColumn.data('provider').id;

        const filterServiceIds = [$filterService.val()].filter(Boolean).map(Number);

        const calendarEvents = appointments
            .filter((appointment) => {
                if (Number(appointment.id_users_provider) !== Number(providerId)) {
                    return false;
                }

                return !filterServiceIds.length || filterServiceIds.includes(appointment.id_services);
            })
            .map((appointment) => {
                const customerName = [appointment.customer.first_name, appointment.customer.last_name]
                    .filter(Boolean)
                    .join(' ');

                const title = customerName ? customerName + ' - ' + appointment.service.name : appointment.service.name;

                return {
                    id: appointment.id,
                    title: title,
                    start: moment(appointment.start_datetime).toDate(),
                    end: moment(appointment.end_datetime).toDate(),
                    allDay: false,
                    color: appointment.color,
                    textColor: App.Utils.CalendarEventContent.getTextColor(appointment.color),
                    display: 'block',
                    data: appointment,
                    classNames: App.Utils.CalendarEventPopover.statusFreesSlot(appointment.status)
                        ? ['fc-appointment-freed']
                        : [],
                };
            });
        $providerColumn.find('.calendar-wrapper').data('fullCalendar').addEventSource(calendarEvents);
    }

    /**
     * Create unavailability calendar events.
     *
     * @param {jQuery} $providerColumn - Provider column element.
     * @param {Array} unavailabilities - Unavailability data array.
     */
    function createUnavailabilities($providerColumn, unavailabilities) {
        if (!unavailabilities.length) {
            return;
        }

        const providerId = $providerColumn.data('provider').id;

        const calendarEvents = unavailabilities
            .filter((u) => Number(u.id_users_provider) === Number(providerId))
            .map((unavailability) => ({
                title: lang('unavailability'),
                start: moment(unavailability.start_datetime).toDate(),
                end: moment(unavailability.end_datetime).toDate(),
                allDay: false,
                color: EVENT_COLORS.unavailability,
                display: 'block',
                editable: true,
                className: 'fc-unavailability fc-custom',
                data: unavailability,
            }));

        $providerColumn.find('.calendar-wrapper').data('fullCalendar').addEventSource(calendarEvents);
    }

    /**
     * Create blocked period calendar events.
     *
     * @param {jQuery} $providerColumn - Provider column element.
     * @param {Array} blockedPeriods - Blocked period data array.
     */
    function createBlockedPeriods($providerColumn, blockedPeriods) {
        // Only the periods of the whole clinic and those of this provider.
        const calendarEvents = App.Utils.CalendarBlockedPeriods.events(
            blockedPeriods,
            $providerColumn.data('provider').id,
        );

        if (calendarEvents.length) {
            $providerColumn.find('.calendar-wrapper').data('fullCalendar').addEventSource(calendarEvents);
        }
    }

    /**
     * Create non-working hours background events.
     *
     * @param {jQuery} $calendar - Calendar wrapper element.
     * @param {Object} provider - Provider data.
     */
    function createNonWorkingHours($calendar, provider) {
        const workingPlan = JSON.parse(provider.settings.working_plan);

        const rawExceptions = JSON.parse(provider.settings.working_plan_exceptions) || [];

        // Convert array format to date-keyed lookup, expanding date ranges
        const workingPlanExceptions = {};
        const exceptionsMap = {}; // Maps dates to their original exception object

        if (Array.isArray(rawExceptions)) {
            rawExceptions.forEach((exception) => {
                const startDate = moment(exception.startDate);
                const endDate = moment(exception.endDate);

                while (startDate.isSameOrBefore(endDate)) {
                    const dateStr = startDate.format('YYYY-MM-DD');
                    workingPlanExceptions[dateStr] = {
                        start: exception.startTime,
                        end: exception.endTime,
                        breaks: exception.breaks || [],
                    };
                    exceptionsMap[dateStr] = exception;
                    startDate.add(1, 'day');
                }
            });
        } else {
            // Handle legacy date-keyed format
            Object.assign(workingPlanExceptions, rawExceptions);
        }

        const view = $calendar.data('fullCalendar').view;

        const start = moment(view.currentStart).clone();

        const end = moment(view.currentEnd).clone();

        // The working plan uses English weekday names, whatever the user language.
        const selDayName = start.clone().locale('en').format('dddd').toLowerCase();

        const selDayDate = start.format('YYYY-MM-DD');

        const calendarEventSource = [];

        // Apply working plan exception if exists

        if (workingPlanExceptions.hasOwnProperty(selDayDate)) {
            const exceptionData = workingPlanExceptions[selDayDate];
            // A non-working exception has no start/end times; treat the day as non-working.
            workingPlan[selDayName] = exceptionData && exceptionData.start ? exceptionData : null;
            const originalException = exceptionsMap[selDayDate] || {
                startDate: selDayDate,
                endDate: selDayDate,
                startTime: exceptionData?.start || null,
                endTime: exceptionData?.end || null,
                breaks: exceptionData?.breaks || [],
            };
            const startTime = exceptionData?.start || '00:00';
            const endTime = exceptionData?.end || '00:00';
            calendarEventSource.push({
                title: lang('working_plan_exception'),
                start: moment(selDayDate + ' ' + startTime, 'YYYY-MM-DD HH:mm', true).toDate(),
                end: moment(selDayDate + ' ' + endTime, 'YYYY-MM-DD HH:mm', true)
                    .add(1, 'day')
                    .toDate(),
                allDay: true,
                color: EVENT_COLORS.workingPlanException,
                display: 'block',
                editable: false,
                className: 'fc-working-plan-exception fc-custom',
                extendedProps: {
                    data: {
                        workingPlanException: originalException,
                        provider: provider,
                    },
                },
            });
        }

        // Non-working day

        if (workingPlan[selDayName] === null) {
            calendarEventSource.push({
                title: lang('not_working'),
                start: start.toDate(),
                end: end.toDate(),
                allDay: false,
                color: EVENT_COLORS.notWorking,
                editable: false,
                display: 'background',
                className: 'fc-unavailability fc-out-of-hours',
            });
            $calendar.data('fullCalendar').addEventSource(calendarEventSource);
            App.Utils.CalendarPastTime.update($calendar.data('fullCalendar'));
            return;
        }

        // Before work starts

        const workDateStart = moment(start.format('YYYY-MM-DD') + ' ' + workingPlan[selDayName].start);

        if (start < workDateStart) {
            calendarEventSource.push({
                title: lang('not_working'),
                start: start.toDate(),
                end: workDateStart.toDate(),
                allDay: false,
                color: EVENT_COLORS.notWorking,
                editable: false,
                display: 'background',
                className: 'fc-unavailability fc-out-of-hours',
            });
        }

        // After work ends

        const workDateEnd = moment(start.format('YYYY-MM-DD') + ' ' + workingPlan[selDayName].end);

        if (end > workDateEnd) {
            calendarEventSource.push({
                title: lang('not_working'),
                start: workDateEnd.toDate(),
                end: end.toDate(),
                allDay: false,
                color: EVENT_COLORS.notWorking,
                editable: false,
                display: 'background',
                className: 'fc-unavailability fc-out-of-hours',
            });
        }

        // Breaks

        workingPlan[selDayName].breaks.forEach((breakPeriod) => {
            const breakStart = moment(start.format('YYYY-MM-DD') + ' ' + breakPeriod.start);

            const breakEnd = moment(start.format('YYYY-MM-DD') + ' ' + breakPeriod.end);

            const isMidday = App.Utils.CalendarMidday.isMiddayBreak(breakPeriod);

            calendarEventSource.push({
                title: isMidday ? lang('midday') : lang('not_working'),
                middayBreak: isMidday,
                start: breakStart.toDate(),
                end: breakEnd.toDate(),
                allDay: false,
                color: EVENT_COLORS.notWorking,
                editable: false,
                display: 'background',
                className: 'fc-unavailability fc-out-of-hours',
            });
        });
        $calendar.data('fullCalendar').addEventSource(calendarEventSource);
        App.Utils.CalendarPastTime.update($calendar.data('fullCalendar'));
    }

    /**
     * Create break events in the table view (legacy support).
     *
     * @param {jQuery} $providerColumn - Provider column element.
     * @param {Array} breaks - Break periods array.
     */
    function createBreaks($providerColumn, breaks) {
        if (!breaks.length) {
            return;
        }

        const currentDate = new Date($providerColumn.parents('.date-column').data('date'));

        const $tbody = $providerColumn.find('table tbody');

        breaks.forEach((entry) => {
            const startHour = entry.start.split(':');

            const eventDate = new Date(
                currentDate.getFullYear(),
                currentDate.getMonth(),
                currentDate.getDate(),
                startHour[0],
                startHour[1],
            );

            const endHour = entry.end.split(':');

            const endDate = new Date(
                currentDate.getFullYear(),
                currentDate.getMonth(),
                currentDate.getDate(),
                endHour[0],
                endHour[1],
            );

            const eventDuration = Math.round((endDate - eventDate) / 60000);

            const $event = $('<div/>', {class: 'event unavailability break'});

            $event.html(
                lang('break') +
                    ' <span class="hour">' +
                    moment(eventDate).format('HH:mm') +
                    '</span> (' +
                    eventDuration +
                    "')",
            );
            $event.data(entry);
            $tbody.find('tr').each((index, tr) => {
                const $td = $(tr).find('td:first');

                const cellDate = moment(currentDate)
                    .set({
                        hour: parseInt($td.text().split(':')[0]),
                        minute: parseInt($td.text().split(':')[1]),
                    })
                    .toDate();

                if (eventDate < cellDate) {
                    if (moment(eventDate).format('HH:mm') === $(tr).prev().find('td').eq(0).text()) {
                        $event.find('.hour').remove();
                    }

                    $(tr)
                        .prev()
                        .find('td:gt(0)')
                        .each((index, td) => {
                            $event.clone().appendTo($(td));
                        });
                    return false;
                }
            });
        });
    }

    // View Construction

    /**
     * Create table view header container.
     */
    function createHeader() {
        const preferences = loadPreferences();

        $calendarFilter
            .find('select')
            .empty()
            .append(new Option('1 ' + lang('day'), '1'))
            .append(new Option('3 ' + lang('days'), '3'))
            .append(new Option(lang('week'), '7'));

        restoreSelection($selectFilterItem, preferences.days);

        // Keep navigation and filters inside the same toolbar as the actions.
        $calendarToolbar.removeClass('row py-3').addClass('ea-unified-toolbar').prependTo($calendar);
        const $calendarHeader = $('<div/>', {class: 'calendar-header'}).insertBefore('#calendar-actions');

        // Previous button

        $('<button/>', {
            class: 'btn btn-xs btn-outline-secondary previous me-2',
            html: [$('<span/>', {class: 'fas fa-chevron-left'})],
        }).appendTo($calendarHeader);

        // Date picker

        $selectDate = $('<input/>', {
            type: 'text',
            class: 'form-control d-inline-block select-date me-2',
            value: App.Utils.Date.format(new Date(), vars('date_format'), vars('time_format'), false),
        }).appendTo($calendarHeader);

        // Next button

        $('<button/>', {
            class: 'btn btn-xs btn-outline-secondary next',
            html: [$('<span/>', {class: 'fas fa-chevron-right'})],
        }).appendTo($calendarHeader);
        App.Utils.UI.initializeDatePicker($calendarHeader.find('.select-date'), {
            // A "Today" button under the month goes straight to today.
            onReady(selectedDates, dateString, instance) {
                $('<button/>', {
                    type: 'button',
                    class: 'btn btn-sm btn-primary w-100 mt-1 ea-picker-today',
                    text: lang('today'),
                    on: {
                        click: () => {
                            instance.setDate(new Date(), true);
                            instance.close();
                        },
                    },
                }).appendTo(instance.calendarContainer);
            },
            onChange(selectedDates) {
                const startDate = selectedDates[0];

                const endDate = moment(startDate)
                    .add(parseInt($selectFilterItem.val()) - 1, 'days')
                    .toDate();

                createView(startDate, endDate);
            },
        });
        $calendarHeader.find('.flatpickr-wrapper').addClass('w-auto');
        const providers = getAvailableProviders();

        // Provider filter

        const $providerFilterGroup = $('<div/>', {class: 'filter-group'}).appendTo($calendarHeader);
        $filterProvider = $('<select/>', {
            id: 'filter-provider',
            'aria-label': lang('provider'),
            class: 'form-select ea-simple-filter',
            on: {
                change: () => {
                    const firstColumnDate = $('.calendar-view .date-column:first').data('date');

                    const startDateMoment = moment(firstColumnDate);

                    const endDateMoment = moment(firstColumnDate).add(parseInt($selectFilterItem.val()) - 1, 'day');

                    savePreferences();

                    createView(startDateMoment.toDate(), endDateMoment.toDate());
                },
            },
        }).appendTo($providerFilterGroup);

        if (vars('role_slug') !== App.Layouts.Backend.DB_SLUG_PROVIDER) {
            providers.forEach((provider) => {
                $filterProvider.append(new Option(getProviderName(provider), provider.id));
            });
        } else {
            providers.forEach((provider) => {
                if (Number(provider.id) === Number(vars('user_id'))) {
                    $filterProvider.append(new Option(getProviderName(provider), provider.id));
                }
            });
        }

        $filterProvider.prepend(new Option(lang('filter_all_providers'), '', true, true));
        restoreSelection($filterProvider, preferences.providers);

        // Service filter

        const services = vars('available_services').filter((service) => {
            const provider = providers.find((p) => p.services.indexOf(service.id) !== -1);

            return vars('role_slug') === App.Layouts.Backend.DB_SLUG_ADMIN || provider;
        });
        const $serviceFilterGroup = $('<div/>', {class: 'filter-group'}).appendTo($calendarHeader);
        $filterService = $('<select/>', {
            id: 'filter-service',
            'aria-label': lang('service'),
            class: 'form-select ea-simple-filter',
            on: {
                change: () => {
                    const firstColumnDate = $('.calendar-view .date-column:first').data('date');

                    const startDateMoment = moment(firstColumnDate);

                    const endDateMoment = moment(firstColumnDate).add({days: parseInt($selectFilterItem.val()) - 1});

                    savePreferences();

                    createView(startDateMoment.toDate(), endDateMoment.toDate());
                },
            },
        }).appendTo($serviceFilterGroup);
        services.forEach((service) => {
            $filterService.append(new Option(service.name, service.id));
        });
        $filterService.prepend(new Option(lang('filter_all_services'), '', true, true));
        restoreSelection($filterService, preferences.services);
    }

    /**
     * Create table schedule view.
     *
     * @param {Date} startDate - Start date to be displayed.
     * @param {Date} endDate - End date to be displayed.
     * @param {Function} [onReady] - Called once the columns are rendered.
     */
    function createView(startDate, endDate, onReady) {
        // Disable date navigation

        $('#calendar .calendar-header .btn').addClass('disabled').prop('disabled', true);

        // Remember provider calendar view mode

        const providerView = {};

        $('.provider-column').each((index, providerColumn) => {
            const $providerColumn = $(providerColumn);

            const providerId = $providerColumn.data('provider').id;

            providerView[providerId] = $providerColumn.find('.calendar-wrapper').data('fullCalendar').view.type;
        });
        $('#calendar .calendar-view').remove();
        const $calendarView = $('<div/>', {class: 'calendar-view'}).appendTo('#calendar');

        $calendarView.data({
            startDate: moment(startDate).format('YYYY-MM-DD'),
            endDate: moment(endDate).format('YYYY-MM-DD'),
        });
        const $wrapper = $('<div/>').appendTo($calendarView);

        App.Http.Calendar.getCalendarAppointmentsForTableView(startDate, endDate).done((response) => {
            lastResponseSignature = JSON.stringify(response);

            let currentDate = startDate;
            while (currentDate <= endDate) {
                createDateColumn($wrapper, currentDate, response);
                currentDate = moment(currentDate).add({days: 1}).toDate();
            }

            // Activate calendar navigation

            $('#calendar .calendar-header .btn').removeClass('disabled').prop('disabled', false);

            // Apply provider calendar view mode

            $('.provider-column').each((index, providerColumn) => {
                const $providerColumn = $(providerColumn);

                const providerId = $providerColumn.data('provider').id;

                $providerColumn
                    .find('.calendar-wrapper')
                    .data('fullCalendar')
                    .changeView(providerView[providerId] || 'timeGridDay');
            });

            requestAnimationFrame(() => {
                setCalendarViewSize();

                if (onReady) {
                    onReady();
                } else {
                    scrollToStartTime();
                }
            });
        });
    }

    /**
     * Reload the displayed period when its data changed (automatic refresh).
     *
     * The table view rebuilds every column on reload, so the data is compared first to avoid flickering, and the
     * scroll position of the columns is kept.
     */
    function refresh() {
        const $calendarView = $('#calendar .calendar-view');

        if (!$calendarView.length) {
            return;
        }

        const startDate = moment($calendarView.data('startDate')).toDate();
        const endDate = moment($calendarView.data('endDate')).toDate();

        App.Http.Calendar.getCalendarAppointmentsForTableView(startDate, endDate, true).done((response) => {
            if (JSON.stringify(response) === lastResponseSignature) {
                return;
            }

            const {scrollTop, scrollLeft} = $calendarView[0];

            createView(startDate, endDate, () => {
                const calendarView = document.querySelector('#calendar .calendar-view');

                calendarView.scrollTop = scrollTop;
                calendarView.scrollLeft = scrollLeft;
            });
        });
    }

    /**
     * Create date column container.
     *
     * @param {jQuery} $wrapper - Wrapper element.
     * @param {Date} date - Selected date for the column.
     * @param {Object} events - Events to be displayed.
     */
    function createDateColumn($wrapper, date, events) {
        const $dateColumn = $('<div/>', {class: 'date-column'}).appendTo($wrapper);

        $dateColumn.data('date', date.getTime());
        $('<h5/>', {
            class: 'date-column-title',
            text: App.Utils.Date.format(date, vars('date_format'), vars('time_format')),
        }).appendTo($dateColumn);
        const filterProviderIds = [$filterProvider.val()].filter(Boolean).map(Number);

        const filterServiceIds = [$filterService.val()].filter(Boolean).map(Number);

        let providers = vars('available_providers').filter((provider) => {
            const servedServiceIds = provider.services.filter((serviceId) => {
                return filterServiceIds.some((filterId) => Number(serviceId) === Number(filterId));
            });
            return (
                (!filterProviderIds.length && !filterServiceIds.length) ||
                (filterProviderIds.length && !filterServiceIds.length && filterProviderIds.includes(provider.id)) ||
                (!filterProviderIds.length && filterServiceIds.length && servedServiceIds.length) ||
                (filterProviderIds.length &&
                    filterServiceIds.length &&
                    servedServiceIds.length &&
                    filterProviderIds.includes(provider.id))
            );
        });
        // Role-based filtering

        if (vars('role_slug') === 'provider') {
            providers = vars('available_providers').filter((p) => Number(p.id) === Number(vars('user_id')));
        }

        if (vars('role_slug') === 'secretary') {
            providers = vars('available_providers').filter((p) => vars('secretary_providers').indexOf(p.id) > -1);
        }

        providers.forEach((provider) => {
            createProviderColumn($dateColumn, date, provider, events);
        });
    }

    /**
     * Create provider column container.
     *
     * @param {jQuery} $dateColumn - Date column element.
     * @param {Date} date - Selected date.
     * @param {Object} provider - Provider data.
     * @param {Object} events - Events data.
     */
    function createProviderColumn($dateColumn, date, provider, events) {
        if (provider.services.length === 0) {
            return;
        }

        const $providerColumn = $('<div/>', {class: 'provider-column'}).appendTo($dateColumn);

        $providerColumn.data('provider', provider);
        createCalendar($providerColumn, date, provider);
        createNonWorkingHours($providerColumn.find('.calendar-wrapper'), provider);
        createAppointments($providerColumn, events.appointments);
        createUnavailabilities($providerColumn, events.unavailabilities);
        createBlockedPeriods($providerColumn, events.blocked_periods);
    }

    /**
     * Create FullCalendar instance for a provider column.
     *
     * @param {jQuery} $providerColumn - Provider column element.
     * @param {Date} goToDate - Date to navigate to.
     * @param {Object} provider - Provider data.
     */
    function createCalendar($providerColumn, goToDate, provider) {
        const $wrapper = $('<div/>', {class: 'calendar-wrapper'}).appendTo($providerColumn);

        const {columnFormat, timeFormat, slotTimeFormat} = getFormatSettings();
        const firstWeekdayNumber = App.Utils.Date.getWeekdayId(vars('first_weekday'));

        const fullCalendar = new FullCalendar.Calendar($wrapper[0], {
            locale: vars('language_code'),
            initialView: 'timeGridDay',
            nowIndicator: true,
            height: 'auto',
            editable: true,
            firstDay: firstWeekdayNumber,
            slotDuration: {minutes: vars('calendar_slot_minutes')},
            snapDuration: {minutes: vars('calendar_slot_minutes')},
            slotLabelInterval: '01:00',
            eventTimeFormat: timeFormat,
            eventTextColor: '#333',
            eventContent: App.Utils.CalendarEventContent.render,
            eventColor: EVENT_COLORS.default,
            slotLabelFormat: slotTimeFormat,
            allDaySlot: false,
            allDayContent: lang('all_day'),
            dayHeaderFormat: columnFormat,
            dayHeaders: false,
            selectable: true,
            selectHelper: true,
            themeSystem: 'bootstrap5',
            selectLongPressDelay: 100,
            headerToolbar: false,
            buttonText: {
                today: lang('today'),
                day: lang('day'),
                week: lang('week'),
                month: lang('month'),
                timeGridDay: lang('calendar'),
                listDay: lang('list'),
            },
            eventClick: onEventClick,
            eventResize: onEventResize,
            eventDrop: onEventDrop,
            select: (info) => onSelect(info, fullCalendar),
            selectAllow: App.Utils.CalendarMidday.allows,
            eventAllow: App.Utils.CalendarMidday.allows,
            eventsSet: () => {
                syncAllDaySlot();
                App.Utils.CalendarMidday.schedule();
            },
            datesSet: (info) => {
                updateViewButtons($providerColumn, info.view.type);
                fitListView(info.view.calendar);
                App.Utils.CalendarMidday.schedule();
            },
        });

        fullCalendar.render();
        $wrapper.data('fullCalendar', fullCalendar);
        fullCalendar.gotoDate(goToDate);
        const providerName = getProviderName(provider);
        const dayLabel = getRelativeDayLabel(goToDate);
        // The list and calendar switch lives in the heading, which stays visible while the view scrolls.
        const $viewButtons = $('<div/>', {class: 'btn-group ea-provider-views', role: 'group'});

        [
            ['listDay', lang('list')],
            ['timeGridDay', lang('calendar')],
        ].forEach(([viewType, text]) => {
            $('<button/>', {
                type: 'button',
                class: 'btn btn-primary',
                text,
                'data-view': viewType,
                on: {
                    click: () => {
                        fullCalendar.changeView(viewType);
                        setCalendarViewSize();
                    },
                },
            }).appendTo($viewButtons);
        });

        $('<h6/>', {
            class: 'ea-provider-heading',
            title: providerName + ' · ' + dayLabel,
            html: [
                $('<span/>', {
                    class: 'ea-provider-name',
                    html: [document.createTextNode(providerName), $('<span/>', {class: 'ea-provider-total'})],
                }),
                $('<span/>', {class: 'ea-provider-date', text: dayLabel}),
                $viewButtons,
            ],
        }).prependTo($providerColumn);

        updateViewButtons($providerColumn, fullCalendar.view.type);
    }

    /**
     * Mark the active view in the heading of a provider column.
     *
     * @param {jQuery} $providerColumn
     * @param {String} viewType
     */
    function updateViewButtons($providerColumn, viewType) {
        $providerColumn.find('.ea-provider-views .btn').each((index, button) => {
            button.classList.toggle('active', button.dataset.view === viewType);
        });
    }

    /**
     * Scroll the view to START_TIME, or to the first time after it on the grid.
     */
    function scrollToStartTime() {
        const calendarView = document.querySelector('#calendar .calendar-view');

        const lane = [...(calendarView?.querySelectorAll('.fc-timegrid-slot-lane[data-time]') ?? [])].find(
            (slot) => slot.dataset.time >= START_TIME,
        );

        if (!lane) {
            return;
        }

        const headingHeight = calendarView.querySelector('.ea-provider-heading')?.offsetHeight ?? 0;

        calendarView.scrollTop +=
            lane.getBoundingClientRect().top - calendarView.getBoundingClientRect().top - headingHeight;
    }

    /**
     * Set calendar view size to fit the page.
     */
    function setCalendarViewSize() {
        // The provider columns share the available width, so two or three providers fill the screen, and only scroll
        // horizontally when they would become narrower than MIN_PROVIDER_COLUMN_WIDTH (e.g. a week of every provider).
        const $providerColumns = $('.provider-column');

        if ($providerColumns.length) {
            const columnWidth = Math.max(
                MIN_PROVIDER_COLUMN_WIDTH,
                // The width inside the scroll, without its scrollbar.
                Math.floor($('#calendar .calendar-view').prop('clientWidth') / $providerColumns.length) - 1,
            );

            $providerColumns.css({width: columnWidth, minWidth: columnWidth, maxWidth: columnWidth});

            $providerColumns.find('.calendar-wrapper').each((index, wrapper) => {
                $(wrapper).data('fullCalendar')?.updateSize();
            });
        }

        $('.date-column').each((index, column) => {
            const count = $(column).find('.provider-column').length;
            column.style.gridTemplateColumns = 'repeat(' + Math.max(1, count) + ', auto)';
        });
        $('.calendar-view > div').css('min-width', '100%');

        // One scroll for every column, as high as the calendars used to be plus the provider headings.
        const $calendarView = $('#calendar .calendar-view');

        $calendarView.outerHeight(
            getCalendarHeight() + ($calendarView.find('.ea-provider-heading').outerHeight() || 0),
        );

        getCalendars().forEach(fitListView);
    }

    /**
     * Give a column in list mode the height of the visible area, so that it scrolls by itself, while the columns in
     * calendar mode grow to their whole height and scroll together.
     *
     * @param {Object} calendar FullCalendar instance of a provider column.
     */
    function fitListView(calendar) {
        const calendarView = document.querySelector('#calendar .calendar-view');

        if (!calendarView) {
            return;
        }

        const headingHeight = calendarView.querySelector('.ea-provider-heading')?.offsetHeight ?? 0;
        const height = calendar.view.type === 'listDay' ? calendarView.clientHeight - headingHeight : 'auto';

        if (calendar.getOption('height') !== height) {
            calendar.setOption('height', height);
        }
    }

    // Event Listeners

    /**
     * Add all page event listeners.
     */
    function addEventListeners() {
        // Previous day button

        $calendar.on('click', '.calendar-header .btn.previous', () => {
            const dayInterval = $selectFilterItem.val();

            const currentDate = App.Utils.UI.getDateTimePickerValue($selectDate);

            const startDate = moment(currentDate).subtract(1, 'days');

            const endDate = startDate.clone().add(dayInterval - 1, 'days');

            App.Utils.UI.setDateTimePickerValue($selectDate, startDate.toDate());
            createView(startDate.toDate(), endDate.toDate());
        });

        // Next day button

        $calendar.on('click', '.calendar-header .btn.next', () => {
            const dayInterval = $selectFilterItem.val();

            const currentDate = App.Utils.UI.getDateTimePickerValue($selectDate);

            const startDate = moment(currentDate).add(1, 'days');

            const endDate = startDate.clone().add(dayInterval - 1, 'days');

            App.Utils.UI.setDateTimePickerValue($selectDate, startDate.toDate());
            createView(startDate.toDate(), endDate.toDate());
        });

        // Day interval change

        $calendarToolbar.on('change', '#select-filter-item', () => {
            const dayInterval = $selectFilterItem.val();

            savePreferences();

            const currentDate = App.Utils.UI.getDateTimePickerValue($selectDate);

            const startDate = moment(currentDate);

            const endDate = startDate.clone().add(dayInterval - 1, 'days');

            createView(startDate.toDate(), endDate.toDate());
        });

        // Reload appointments button

        $calendarToolbar.on('click', '#reload-appointments', () => {
            const dayInterval = $selectFilterItem.val();

            const currentDate = App.Utils.UI.getDateTimePickerValue($selectDate);

            const startDate = moment(currentDate).toDate();

            const endDate = moment(currentDate)
                .add(dayInterval - 1, 'days')
                .toDate();

            App.Http.Calendar.getCalendarAppointmentsForTableView(startDate, endDate).done((response) => {
                let currentDateIter = startDate;
                while (currentDateIter <= endDate) {
                    $('.calendar-view .date-column').each((index, dateColumn) => {
                        const $dateColumn = $(dateColumn);

                        const date = new Date($dateColumn.data('date'));

                        if (moment(currentDateIter).format('YYYY-MM-DD') !== moment(date).format('YYYY-MM-DD')) {
                            return true;
                        }

                        $dateColumn
                            .find('.date-column-title')
                            .text(App.Utils.Date.format(date, vars('date_format'), vars('time_format')));
                        $dateColumn.find('.provider-column').each((index, providerColumn) => {
                            const $providerColumn = $(providerColumn);

                            const provider = $providerColumn.data('provider');

                            $providerColumn
                                .find('.calendar-wrapper')
                                .data('fullCalendar')
                                .getEventSources()
                                .forEach((eventSource) => eventSource.remove());
                            createNonWorkingHours($providerColumn.find('.calendar-wrapper'), provider);
                            createAppointments($providerColumn, response.appointments);
                            createUnavailabilities($providerColumn, response.unavailabilities);
                            createBlockedPeriods($providerColumn, response.blocked_periods);

                            // Add provider breaks

                            const workingPlan = JSON.parse(provider.settings.working_plan);

                            // The working plan uses English weekday names, whatever the user language.
                            const day = moment(date).locale('en').format('dddd').toLowerCase();

                            if (workingPlan[day]) {
                                createBreaks($providerColumn, workingPlan[day].breaks);
                            }
                        });
                    });
                    currentDateIter = moment(currentDateIter).add({days: 1}).toDate();
                }
            });
        });

        // The mouse wheel over a column in list mode only scrolls that list, never the calendar columns around it.
        document.querySelector('#calendar').addEventListener(
            'wheel',
            (event) => {
                const list = event.target.closest?.('.provider-column:has(.fc-list)');

                if (!list) {
                    return;
                }

                event.preventDefault();

                const scroller = list.querySelector('.fc-scroller');

                if (scroller) {
                    scroller.scrollTop += event.deltaY;
                }
            },
            {passive: false},
        );

        // Window resize

        $(window).on('resize', () => {
            setCalendarViewSize();
        });

        // Popover close button

        $calendar.on('click', '.close-popover', closePopover);

        // Popover edit button

        $calendar.on('click', '.edit-popover', onEditPopoverClick);

        // Popover delete button

        $calendar.on('click', '.delete-popover', onDeletePopoverClick);
        $calendar.on('click', '.cancel-appointment-popover', onCancelAppointmentPopoverClick);
        $calendar.on('click', '.no-show-popover', () => {
            closePopover();
            App.Utils.CalendarAppointmentActions.markNoShow(lastFocusedEventData.extendedProps.data, () =>
                $reloadAppointments.trigger('click'),
            );
        });
    }

    // Initialization

    /**
     * Initialize the calendar page.
     */
    function initialize() {
        createHeader();
        const startDate = moment().toDate();

        const endDate = moment()
            .add(Number($selectFilterItem.val()) - 1, 'days')
            .toDate();

        createView(startDate, endDate);
        $('#insert-working-plan-exception').hide();
        addEventListeners();
        // Hide Google Calendar Sync buttons (not supported in table view)
        $('#enable-sync, #google-sync').hide();

        // Load modified appointment if provided

        if (vars('edit_appointment')) {
            const appointment = vars('edit_appointment');

            const startDateTimeObject = moment(appointment.start_datetime)
                .set({hour: 0, minutes: 0, seconds: 0})
                .toDate();

            const endDateTimeObject = moment(appointment.end_datetime)
                .set({hour: 23, minutes: 59, seconds: 59})
                .toDate();

            App.Utils.UI.setDateTimePickerValue($selectDate, startDateTimeObject);
            createView(startDateTimeObject, endDateTimeObject);
            populateAppointmentModal(appointment);
        }
    }

    /**
     * Show the all day row (blocked periods and working plan exceptions) only when a column has all day events, in
     * every column so that their hours stay aligned.
     */
    function syncAllDaySlot() {
        const calendars = getCalendars();
        const show = calendars.some((calendar) => calendar.getEvents().some((event) => event.allDay));

        calendars
            .filter((calendar) => calendar.getOption('allDaySlot') !== show)
            .forEach((calendar) => calendar.setOption('allDaySlot', show));
    }

    // Public API

    /**
     * Get the FullCalendar instances of the provider columns.
     *
     * @return {Array}
     */
    function getCalendars() {
        return $('#calendar .calendar-wrapper')
            .toArray()
            .map((wrapper) => $(wrapper).data('fullCalendar'))
            .filter(Boolean);
    }

    return {
        initialize,
        refresh,
        getCalendars,
    };
})();
