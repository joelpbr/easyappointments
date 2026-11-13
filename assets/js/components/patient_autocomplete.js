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
 * Patient autocomplete.
 *
 * Suggests the existing patients while the name, the last name or the phone number of a new patient is typed, and
 * offers to keep creating a new one. Only a few fields of up to 10 patients are requested from the server.
 */
App.Components.PatientAutocomplete = (function () {
    const DELAY = 250; // Milliseconds after the last key press.

    /**
     * Attach the autocomplete to the patient fields of a form.
     *
     * @param {Object} options
     * @param {jQuery} options.$firstName
     * @param {jQuery} options.$lastName
     * @param {jQuery} options.$phoneNumber
     * @param {Function} options.isEnabled Whether to suggest patients (e.g. no patient is selected yet).
     * @param {Function} options.onSelect Called with the ID of the chosen patient.
     * @param {Function} options.onCreate Called when the user keeps creating a new patient.
     */
    function attach({$firstName, $lastName, $phoneNumber, isEnabled, onSelect, onCreate}) {
        const $list = $('<div/>', {class: 'list-group shadow ea-patient-suggestions', role: 'listbox'}).hide();
        let timeout = null;
        let request = null;
        let dismissed = false;

        const close = () => {
            clearTimeout(timeout);
            request?.abort();
            $list.hide().empty();
        };

        const items = () => $list.children('.list-group-item');

        const activate = (index) => {
            const $items = items();
            const target = (index + $items.length) % $items.length;

            $items.removeClass('active').eq(target).addClass('active');
        };

        const choose = ($item) => {
            const patientId = $item.data('patientId');

            close();

            if (patientId) {
                onSelect(patientId);
            } else {
                dismissed = true;
                onCreate();
            }
        };

        const render = ($input, patients) => {
            $list.empty();

            patients.forEach((patient) => {
                $('<button/>', {
                    type: 'button',
                    class: 'list-group-item list-group-item-action',
                    role: 'option',
                    data: {patientId: patient.id},
                    html: [
                        $('<span/>', {
                            class: 'fw-semibold',
                            text: [patient.first_name, patient.last_name].filter(Boolean).join(' '),
                        }),
                        $('<small/>', {class: 'ms-2 text-muted', text: patient.phone_number || ''}),
                    ],
                }).appendTo($list);
            });

            $('<button/>', {
                type: 'button',
                class: 'list-group-item list-group-item-action fst-italic',
                role: 'option',
                html: [$('<i/>', {class: 'fas fa-user-plus me-2'}), lang('create_new_patient')],
            }).appendTo($list);

            $list.appendTo($input.parent()).show();
        };

        const search = ($input) => {
            clearTimeout(timeout);

            if (!isEnabled() || dismissed) {
                close();

                return;
            }

            const keyword = $input.is($phoneNumber)
                ? $phoneNumber.val()
                : [$firstName.val(), $lastName.val()].join(' ').trim();

            if (keyword.length < 2) {
                close();

                return;
            }

            timeout = setTimeout(() => {
                request?.abort();

                request = App.Http.Customers.autocomplete(keyword).done((patients) => {
                    if ($input.is(':focus')) {
                        render($input, patients);
                    }
                });
            }, DELAY);
        };

        [$firstName, $lastName, $phoneNumber].forEach(($input) => {
            $input.attr('autocomplete', 'off');

            $input.on('input', () => search($input));

            $input.on('keydown', (event) => {
                if (!$list.is(':visible')) {
                    return;
                }

                const $active = items().filter('.active');

                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    activate(items().index($active) + (event.key === 'ArrowDown' ? 1 : -1));
                } else if (event.key === 'Enter' && $active.length) {
                    event.preventDefault();
                    choose($active);
                } else if (event.key === 'Escape') {
                    event.stopPropagation(); // Keep the modal open.
                    close();
                }
            });

            // Only hide the list: moving to the next field keeps its own search going.
            $input.on('blur', () => setTimeout(() => $list.hide().empty(), 150));
        });

        // Keep the focus in the field, so that clicking a suggestion does not close the list first.
        $list.on('mousedown', (event) => event.preventDefault());

        $list.on('click', '.list-group-item', (event) => choose($(event.currentTarget)));

        return {
            /**
             * Close the suggestions and suggest again for the next patient.
             */
            reset() {
                dismissed = false;
                close();
            },
        };
    }

    return {
        attach,
    };
})();
