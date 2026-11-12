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
 * Slot search modal ("Buscar hueco").
 *
 * Reception describes what the patient needs (treatment, duration, providers, dates, time window, preferred time) and
 * picks one of the free slots; the appointment form then opens with the provider, date, time, duration and treatment
 * filled in, so only the patient is left.
 */
App.Components.SlotSearchModal = (function () {
    const $modal = $('#slot-search-modal');
    const $form = $('#slot-search-form');
    const $service = $('#slot-search-service');
    const $duration = $('#slot-search-duration');
    const $providers = $('#slot-search-providers');
    const $preferredProvider = $('#slot-search-preferred-provider');
    const $dateFrom = $('#slot-search-date-from');
    const $dateTo = $('#slot-search-date-to');
    const $timeFrom = $('#slot-search-time-from');
    const $timeTo = $('#slot-search-time-to');
    const $preferredTime = $('#slot-search-preferred-time');
    const $margin = $('#slot-search-margin');
    const $message = $('#slot-search-message');
    const $results = $('#slot-search-results');
    const moment = window.moment;

    const DEFAULT_RANGE_DAYS = 14;

    /**
     * Set a date or time picker, or clear it when there is no value.
     *
     * @param {jQuery} $input Picker input.
     * @param {String} value Date (YYYY-MM-DD) or time (HH:mm).
     * @param {String} format Moment format of the value.
     */
    function setPicker($input, value, format) {
        if (value) {
            App.Utils.UI.setDateTimePickerValue($input, moment(value, format).toDate());
        } else {
            $input[0]._flatpickr.clear();
        }
    }

    /**
     * Read a date or time picker in the format the server expects.
     *
     * @param {jQuery} $input Picker input.
     * @param {String} format Moment format.
     *
     * @return {String} The value, or an empty string.
     */
    function getPicker($input, format) {
        const value = App.Utils.UI.getDateTimePickerValue($input);

        return value ? moment(value).format(format) : '';
    }

    /**
     * Show a message above the results.
     *
     * @param {String} text Message.
     * @param {String} type Bootstrap alert type.
     */
    function showMessage(text, type = 'info') {
        $message
            .removeClass('d-none alert-info alert-warning alert-danger')
            .addClass('alert-' + type)
            .text(text);
    }

    /**
     * Put the form back to its defaults: the first treatment, any provider, the next two weeks.
     */
    function reset() {
        $service.prop('selectedIndex', 0).trigger('change');
        $providers.val([]).trigger('change');
        $preferredProvider.val('');
        setPicker($dateFrom, moment().format('YYYY-MM-DD'), 'YYYY-MM-DD');
        setPicker(
            $dateTo,
            moment()
                .add(DEFAULT_RANGE_DAYS - 1, 'days')
                .format('YYYY-MM-DD'),
            'YYYY-MM-DD',
        );
        setPicker($timeFrom, '', 'HH:mm');
        setPicker($timeTo, '', 'HH:mm');
        setPicker($preferredTime, '', 'HH:mm');
        $margin.val('30');
        $modal.find('#slot-search-weekdays input').prop('checked', false);
        $message.addClass('d-none');
        $results.addClass('d-none').find('tbody').empty();
    }

    /**
     * Read the search criteria from the form.
     *
     * @return {Object}
     */
    function getCriteria() {
        return {
            service_id: $service.val(),
            duration: $duration.val(),
            provider_ids: $providers.val(),
            preferred_provider_id: $preferredProvider.val(),
            date_from: getPicker($dateFrom, 'YYYY-MM-DD'),
            date_to: getPicker($dateTo, 'YYYY-MM-DD'),
            time_from: getPicker($timeFrom, 'HH:mm'),
            time_to: getPicker($timeTo, 'HH:mm'),
            preferred_time: getPicker($preferredTime, 'HH:mm'),
            margin: $margin.val(),
            weekdays: $modal
                .find('#slot-search-weekdays input:checked')
                .toArray()
                .map((input) => input.value),
        };
    }

    /**
     * Render the slots found.
     *
     * @param {Object} response Search response.
     */
    function renderResults(response) {
        const $tbody = $results.find('tbody').empty();

        if (!response.slots.length) {
            $results.addClass('d-none');
            showMessage(lang('slot_search_no_results'), 'warning');

            return;
        }

        $message.addClass('d-none');

        response.slots.forEach((slot) => {
            const start = moment(slot.start);
            const end = moment(slot.end);
            const weekday = start.format('dddd');

            $('<tr/>', {
                'html': [
                    $('<td/>', {'text': weekday.charAt(0).toUpperCase() + weekday.slice(1)}),
                    $('<td/>', {'text': start.format('DD/MM/YYYY')}),
                    $('<td/>', {'class': 'fw-semibold', 'text': start.format('HH:mm') + '–' + end.format('HH:mm')}),
                    $('<td/>', {
                        'html': [
                            $('<span/>', {'text': slot.provider_name}),
                            slot.is_preferred_provider
                                ? $('<span/>', {
                                      'class': 'badge text-bg-light border ms-2',
                                      'text': lang('preferred_provider'),
                                  })
                                : null,
                        ],
                    }),
                    $('<td/>', {'text': response.duration + ' min'}),
                    $('<td/>', {'text': response.service.name}),
                    $('<td/>', {
                        'class': 'text-end',
                        'html': $('<button/>', {
                            'type': 'button',
                            'class': 'btn btn-primary btn-sm slot-search-book',
                            'text': lang('book'),
                            'data': {slot, serviceId: response.service.id},
                        }),
                    }),
                ],
            }).appendTo($tbody);
        });

        $results.removeClass('d-none');
    }

    /**
     * Open the appointment form with the chosen slot filled in.
     *
     * @param {Object} slot Slot.
     * @param {Number} serviceId Service ID.
     */
    function book(slot, serviceId) {
        $modal.one('hidden.bs.modal', () => {
            // The new appointment button resets and opens the appointment form.
            $('#insert-appointment').trigger('click');

            $('#select-service').val(serviceId).trigger('change');
            $('#select-provider').val(slot.provider_id).trigger('change');

            App.Utils.UI.setDateTimePickerValue($('#start-datetime'), moment(slot.start).toDate());
            App.Utils.UI.setDateTimePickerValue($('#end-datetime'), moment(slot.end).toDate());
        });

        $modal.modal('hide');
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        if (!$modal.length) {
            return;
        }

        // The dropdown is created while the modal is hidden, so its width must not be measured.
        App.Utils.UI.initializeDropdown($providers, {
            width: '100%',
            dropdownParent: $modal,
            placeholder: lang('any_provider'),
        });

        App.Utils.UI.initializeDatePicker($dateFrom);
        App.Utils.UI.initializeDatePicker($dateTo);
        App.Utils.UI.initializeTimePicker($timeFrom);
        App.Utils.UI.initializeTimePicker($timeTo);
        App.Utils.UI.initializeTimePicker($preferredTime);

        $('#open-slot-search').on('click', () => {
            reset();
            $modal.modal('show');
        });

        $('#open-new-appointment').on('click', () => $('#insert-appointment').trigger('click'));

        $service.on('change', () => $duration.val($service.find('option:selected').data('duration') || ''));

        $modal.on('click', '.slot-search-window', (event) => {
            setPicker($timeFrom, $(event.currentTarget).data('from'), 'HH:mm');
            setPicker($timeTo, $(event.currentTarget).data('to'), 'HH:mm');
        });

        $form.on('submit', (event) => {
            event.preventDefault();

            $('#slot-search-submit').prop('disabled', true);

            App.Http.SlotSearch.search(getCriteria())
                .done(renderResults)
                .fail((jqXHR) => {
                    $results.addClass('d-none');
                    showMessage(jqXHR.responseJSON?.message || lang('service_communication_error'), 'danger');
                })
                .always(() => $('#slot-search-submit').prop('disabled', false));
        });

        $results.on('click', '.slot-search-book', (event) => {
            const $button = $(event.currentTarget);

            book($button.data('slot'), $button.data('serviceId'));
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        reset,
    };
})();
