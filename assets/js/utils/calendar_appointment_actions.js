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
 * Calendar appointment actions.
 *
 * Shared by the default and the table calendar views: cancelling an appointment keeps it with the cancelled status
 * (its slot becomes free), while deleting removes it for good and is only meant to fix mistakes.
 */
App.Utils.CalendarAppointmentActions = (function () {
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';

    /**
     * Cancel an appointment after asking whether the users must be notified.
     *
     * @param {Object} eventData Appointment data of the calendar event.
     * @param {Function} onDone Called once the appointment is cancelled.
     */
    function cancel(eventData, onDone) {
        const appointment = {...eventData, status: STATUS_CANCELLED};

        appointment.is_unavailability = Number(appointment.is_unavailability) || 0;
        delete appointment.customer;
        delete appointment.provider;
        delete appointment.service;

        const save = (notifyUsers) => App.Http.Calendar.saveAppointment(appointment, null, onDone, null, notifyUsers);

        App.Utils.Message.show(lang('cancel_appointment'), lang('cancel_appointment_question'), [
            {
                text: lang('close'),
                className: 'btn btn-outline-secondary',
                click: (event, messageModal) => messageModal.hide(),
            },
            {
                text: lang('cancel_without_notifying'),
                className: 'btn btn-outline-danger',
                click: (event, messageModal) => {
                    messageModal.hide();
                    save(false);
                },
            },
            {
                text: lang('cancel_and_notify'),
                className: 'btn btn-danger',
                click: (event, messageModal) => {
                    messageModal.hide();
                    save(true);
                },
            },
        ]);
    }

    /**
     * Mark that the patient did not come to an appointment, after confirming it. No message is sent to anybody.
     *
     * The appointment is kept with the no-show status, which warns about it the next time the patient is booked.
     *
     * @param {Object} eventData Appointment data of the calendar event.
     * @param {Function} onDone Called once the appointment is saved.
     */
    function markNoShow(eventData, onDone) {
        const appointment = {...eventData, status: STATUS_NO_SHOW};

        appointment.is_unavailability = Number(appointment.is_unavailability) || 0;
        delete appointment.customer;
        delete appointment.provider;
        delete appointment.service;

        App.Utils.Message.show(lang('mark_no_show'), lang('mark_no_show_question'), [
            {
                text: lang('close'),
                className: 'btn btn-outline-secondary',
                click: (event, messageModal) => messageModal.hide(),
            },
            {
                text: lang('mark_no_show'),
                className: 'btn btn-warning',
                click: (event, messageModal) => {
                    messageModal.hide();
                    App.Http.Calendar.saveAppointment(appointment, null, onDone, null, false);
                },
            },
        ]);
    }

    /**
     * Ask for confirmation before an appointment is deleted for good.
     *
     * @param {Function} onConfirm Called when the user confirms the deletion.
     */
    function confirmDelete(onConfirm) {
        App.Utils.Message.show(lang('delete_appointment_title'), lang('delete_appointment_warning'), [
            {
                text: lang('close'),
                className: 'btn btn-outline-secondary',
                click: (event, messageModal) => messageModal.hide(),
            },
            {
                text: lang('delete'),
                className: 'btn btn-danger',
                click: (event, messageModal) => {
                    messageModal.hide();
                    onConfirm();
                },
            },
        ]);
    }

    return {
        cancel,
        markNoShow,
        confirmDelete,
    };
})();
