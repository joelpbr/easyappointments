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
 * Appointment status options component.
 *
 * Edits the appointment statuses: the system ones can only be renamed, the custom ones can also be removed and decide
 * whether their appointments free the slot.
 */
App.Components.AppointmentStatusOptions = (function () {
    /**
     * Render an appointment status.
     *
     * @param {Object} status
     * @param {String} status.code
     * @param {String} status.name
     * @param {Boolean} status.frees_slot
     * @param {Boolean} status.is_system
     *
     * @return {jQuery} Returns the list group item.
     */
    function renderListGroupItem(status) {
        const freesSlotId = 'frees-slot-' + status.code;

        return $('<li/>', {
            'class': 'list-group-item d-flex align-items-center p-0 border-0 mb-3 appointment-status-option',
            'data-code': status.code,
            'data-system': status.is_system ? '1' : '0',
            'html': [
                $('<input/>', {
                    'class': 'form-control me-3',
                    'type': 'text',
                    'maxlength': 64,
                    'value': status.name,
                    'aria-label': lang('name'),
                }),
                $('<div/>', {
                    'class': 'form-check text-nowrap me-3',
                    'html': [
                        $('<input/>', {
                            'class': 'form-check-input',
                            'type': 'checkbox',
                            'id': freesSlotId,
                            'checked': Boolean(status.frees_slot),
                            'disabled': Boolean(status.is_system),
                        }),
                        $('<label/>', {
                            'class': 'form-check-label',
                            'for': freesSlotId,
                            'text': lang('status_frees_slot'),
                        }),
                    ],
                }),
                status.is_system
                    ? $('<span/>', {
                          'class': 'badge text-bg-light border',
                          'text': lang('system_status'),
                      })
                    : $('<button/>', {
                          'type': 'button',
                          'class': 'btn btn-outline-danger delete-appointment-status-option',
                          'aria-label': lang('delete'),
                          'html': [$('<i/>', {'class': 'fas fa-trash'})],
                      }),
            ],
        });
    }

    /**
     * Event: Delete Appointment Status Option "Click"
     *
     * @param {jQuery.Event} event
     */
    function onDeleteAppointmentStatusOptionClick(event) {
        $(event.currentTarget).closest('li').remove();
    }

    /**
     * Event: Add Appointment Status Option "Click"
     *
     * @param {jQuery.Event} event
     */
    function onAddAppointmentStatusOptionClick(event) {
        const $listGroup = $(event.currentTarget).closest('.appointment-status-options').find('.list-group');

        if (!$listGroup.length) {
            return;
        }

        const code = 'custom_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

        renderListGroupItem({code, name: '', frees_slot: false, is_system: false})
            .appendTo($listGroup)
            .find('input:text')
            .trigger('focus');
    }

    /**
     * Get the statuses.
     *
     * @param {jQuery} $target Component container.
     *
     * @return {Object[]} List of {code, name, frees_slot}.
     */
    function getOptions($target) {
        return $target
            .find('.list-group li')
            .toArray()
            .map((listGroupItemEl) => {
                const $listGroupItem = $(listGroupItemEl);

                return {
                    code: $listGroupItem.data('code'),
                    name: $listGroupItem.find('input:text').val().trim(),
                    frees_slot: $listGroupItem.find('input:checkbox').prop('checked'),
                };
            })
            .filter((status) => status.name !== '');
    }

    /**
     * Set the statuses.
     *
     * @param {jQuery} $target Component container.
     * @param {Object[]} statuses List of {code, name, frees_slot, is_system}.
     */
    function setOptions($target, statuses) {
        const $listGroup = $target.find('.list-group');

        if (!$listGroup.length || !statuses) {
            return;
        }

        $listGroup.empty();

        statuses.forEach((status) => {
            renderListGroupItem(status).appendTo($listGroup);
        });
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        $(document).on('click', '.delete-appointment-status-option', onDeleteAppointmentStatusOptionClick);
        $(document).on('click', '.add-appointment-status-option', onAddAppointmentStatusOptionClick);
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        getOptions,
        setOptions,
    };
})();
