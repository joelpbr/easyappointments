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
 * Patient search.
 *
 * Pressing Enter in the name, the last name or the phone field of a new patient looks for the existing patients. The
 * search needs three letters or six digits of the phone number, never runs while typing, and shows up to 10
 * patients, with the option to keep creating a new one.
 */
App.Components.PatientSearch = (function () {
    const MIN_LETTERS = 3; // Same rule as is_specific_patient_search() on the server.
    const MIN_PHONE_DIGITS = 6;

    /**
     * Check whether a search is worth running.
     *
     * @param {String} keyword
     *
     * @return {Boolean}
     */
    function isSpecific(keyword) {
        const letters = (keyword.match(/\p{L}/gu) || []).length;

        if (!letters) {
            return keyword.replace(/\D/g, '').length >= MIN_PHONE_DIGITS;
        }

        return letters >= MIN_LETTERS;
    }

    /**
     * Attach the search to the patient fields of a form.
     *
     * @param {Object} options
     * @param {jQuery} options.$firstName
     * @param {jQuery} options.$lastName
     * @param {jQuery} options.$phoneNumber
     * @param {Function} options.isEnabled Whether to search (e.g. no patient is selected yet).
     * @param {Function} options.onSelect Called with the ID of the chosen patient.
     * @param {Function} options.onCreate Called when the user keeps creating a new patient.
     *
     * @return {Object} {reset}
     */
    function attach({$firstName, $lastName, $phoneNumber, isEnabled, onSelect, onCreate}) {
        const $list = $('<div/>', {class: 'list-group shadow ea-patient-suggestions', role: 'listbox'}).hide();
        let request = null;

        const close = () => {
            request?.abort();
            $list.hide().empty();
        };

        const options = () => $list.children('[role="option"]');

        const activate = (index) => {
            const $options = options();

            $options
                .removeClass('active')
                .eq((index + $options.length) % $options.length)
                .addClass('active');
        };

        const choose = ($option) => {
            const patientId = $option.data('patientId');

            close();

            if (patientId) {
                onSelect(patientId);
            } else {
                onCreate();
            }
        };

        const message = (text) => $('<div/>', {class: 'list-group-item small text-muted', text});

        const option = (content, patientId = null) =>
            $('<button/>', {
                type: 'button',
                class: 'list-group-item list-group-item-action',
                role: 'option',
                data: {patientId},
                html: content,
            });

        const show = ($input, items) => {
            $list.empty().append(items).appendTo($input.parent()).show();
        };

        const createOption = () =>
            option([$('<i/>', {class: 'fas fa-user-plus me-2'}), lang('create_new_patient')]).addClass('fst-italic');

        const search = ($input) => {
            const keyword = $input.is($phoneNumber)
                ? $phoneNumber.val().trim()
                : [$firstName.val(), $lastName.val()].join(' ').trim();

            if (!isSpecific(keyword)) {
                show($input, [message(lang('patient_search_too_broad'))]);

                return;
            }

            request?.abort();

            request = App.Http.Customers.quickSearch(keyword).done((response) => {
                const items = response.patients.map((patient) =>
                    option(
                        [
                            $('<span/>', {
                                class: 'fw-semibold',
                                text: [patient.first_name, patient.last_name].filter(Boolean).join(' '),
                            }),
                            $('<small/>', {class: 'ms-2 text-muted', text: patient.phone_number || ''}),
                            Number(patient.no_show_count) > 0
                                ? $('<span/>', {
                                      class: 'badge text-bg-warning ms-2',
                                      text: lang('no_show_count_badge').replace('%count', patient.no_show_count),
                                  })
                                : null,
                        ],
                        patient.id,
                    ),
                );

                if (!items.length) {
                    items.push(message(lang('patient_search_no_results')));
                }

                if (response.more) {
                    items.push(message(lang('patient_search_more_results')));
                }

                items.push(createOption());

                show($input, items);
            });
        };

        [$firstName, $lastName, $phoneNumber].forEach(($input) => {
            $input.attr('autocomplete', 'off');

            $input.on('keydown', (event) => {
                const $active = options().filter('.active');

                if (event.key === 'Enter') {
                    event.preventDefault(); // Never submit the form.

                    if ($list.is(':visible') && $active.length) {
                        choose($active);
                    } else if (isEnabled()) {
                        search($input);
                    }
                } else if ($list.is(':visible') && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                    event.preventDefault();
                    activate(options().index($active) + (event.key === 'ArrowDown' ? 1 : -1));
                } else if ($list.is(':visible') && event.key === 'Escape') {
                    event.stopPropagation(); // Keep the modal open.
                    close();
                }
            });

            // The results belong to the text that was searched.
            $input.on('input', close);

            // Only hide the list of this field: a search started in another field keeps going.
            $input.on('blur', () =>
                setTimeout(() => {
                    if ($list.parent().is($input.parent()) && !$input.is(':focus')) {
                        $list.hide().empty();
                    }
                }, 150),
            );
        });

        // Keep the focus in the field, so that clicking an option does not close the list first.
        $list.on('mousedown', (event) => event.preventDefault());

        $list.on('click', '[role="option"]', (event) => choose($(event.currentTarget)));

        return {
            reset: close,
        };
    }

    return {
        attach,
    };
})();
