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
 * Holidays page.
 *
 * Closing the clinic on a holiday creates a blocked period for that day; the appointments of the day are listed first
 * and never cancelled.
 */
App.Pages.Holidays = (function () {
    const post = (path, data) =>
        $.post(App.Utils.Url.siteUrl('holidays/' + path), {csrf_token: vars('csrf_token'), ...data});

    /**
     * Close the clinic on a holiday, asking first when it has appointments.
     *
     * @param {Object} holiday {date, name, level}
     * @param {Boolean} confirmed
     */
    function closeClinic(holiday, confirmed = false) {
        post('close', {...holiday, confirmed: confirmed ? 1 : 0}).done((response) => {
            if (!response.affected) {
                window.location.reload();

                return;
            }

            const $body = $('<div/>').append(
                $('<p/>', {text: lang('blocked_period_affected_intro')}),
                $('<ul/>', {class: 'list-unstyled mb-0'}).append(
                    response.affected.map((appointment) =>
                        $('<li/>', {class: 'py-1 border-bottom'}).append(
                            $('<strong/>', {text: moment(appointment.start_datetime).format('HH:mm')}),
                            document.createTextNode(
                                ' · ' +
                                    [appointment.customer_name, appointment.provider_name].filter(Boolean).join(' · ') +
                                    ' ',
                            ),
                            $('<a/>', {
                                href: App.Utils.Url.siteUrl('calendar/reschedule/' + appointment.hash),
                                target: '_blank',
                                text: lang('reschedule'),
                            }),
                        ),
                    ),
                ),
            );

            App.Utils.Message.show(lang('blocked_period_affected_title'), $body.html(), [
                {
                    text: lang('close'),
                    className: 'btn btn-outline-secondary',
                    click: (event, messageModal) => messageModal.hide(),
                },
                {
                    text: lang('blocked_period_block_anyway'),
                    click: (event, messageModal) => {
                        messageModal.hide();
                        closeClinic(holiday, true);
                    },
                },
            ]);
        });
    }

    function initialize() {
        $('#holidays-page').on('click', '.close-clinic', (event) => {
            const $button = $(event.currentTarget);

            closeClinic({date: $button.data('date'), name: $button.data('name'), level: $button.data('level')});
        });

        $('#holidays-page').on('click', '.delete-holiday', (event) => {
            post('destroy', {holiday_id: $(event.currentTarget).data('id')}).done(() => window.location.reload());
        });

        $('#add-holiday').on('submit', (event) => {
            event.preventDefault();

            post('store', {date: $('#holiday-date').val(), name: $('#holiday-name').val()}).done(() =>
                window.location.reload(),
            );
        });

        $('#holiday-region').on('change', (event) => {
            post('save_region', {region: $(event.target).val()}).done(() => window.location.reload());
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
