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
 * WhatsApp settings page.
 *
 * Saves the settings of the WhatsApp reminders, also the rules of when they go out (said in words under them), and
 * sends a test message with the saved ones. Whether they go out is said at the top, as its switch is folded under
 * "Advanced" with the rest of what is set once. The access token and the app secret are never shown: an empty field keeps
 * the saved value.
 */
App.Pages.WhatsappSettings = (function () {
    const $page = $('#whatsapp-settings');

    // Whether the reminders go out, as saved (the switch may have been changed without saving).
    let savedEnabled = false;

    /**
     * Show the saved settings on the form.
     *
     * @param {Array} settings
     */
    function deserialize(settings) {
        settings.forEach((setting) => {
            const $field = $page.find('[data-field="' + setting.name + '"]');

            $field.is(':checkbox') ? $field.prop('checked', Boolean(Number(setting.value))) : $field.val(setting.value);
        });
    }

    /**
     * Get the settings of the form.
     *
     * @return {Array}
     */
    function serialize() {
        return $page
            .find('[data-field]')
            .toArray()
            .map((field) => ({
                name: field.dataset.field,
                value: field.type === 'checkbox' ? Number(field.checked) : field.value.trim(),
            }));
    }

    /**
     * Say at the top whether the reminders go out, as saved: their switch is folded under "Advanced". A switch
     * changed and not saved yet is said too, as nothing changes until it is saved.
     */
    function showState() {
        const unsaved = $('#whatsapp-enabled').prop('checked') !== savedEnabled;

        $('#whatsapp-state')
            .toggleClass('alert-success', savedEnabled && !unsaved)
            .toggleClass('alert-secondary', !savedEnabled && !unsaved)
            .toggleClass('alert-warning', unsaved)
            .empty()
            .append(
                $('<i/>', {class: 'fas me-2 ' + (savedEnabled ? 'fa-check-circle' : 'fa-pause-circle')}),
                $('<span/>', {text: lang(savedEnabled ? 'whatsapp_reminders_on' : 'whatsapp_reminders_off')}),
                unsaved ? $('<strong/>', {class: 'ms-1', text: lang('whatsapp_state_unsaved')}) : null,
            );
    }

    /**
     * Get minutes in words: "2 h", "1 h 30 min" or "45 min".
     *
     * @param {Number} minutes
     *
     * @return {String}
     */
    function formatMinutes(minutes) {
        const hours = Math.floor(minutes / 60);
        const rest = minutes % 60;

        if (!hours) {
            return rest + ' min';
        }

        return hours + ' h' + (rest ? ' ' + rest + ' min' : '');
    }

    /**
     * Say in words when the reminders go out, with the rules of the form.
     */
    function showScheduleSummary() {
        const days = Number($('#whatsapp-days-before').val());
        const time = $('#whatsapp-send-time').val();
        const delay = Number($('#whatsapp-booking-delay').val());
        const notice = Number($('#whatsapp-min-notice').val());

        if (!time || [days, delay, notice].some((value) => Number.isNaN(value) || value < 0)) {
            $('#whatsapp-schedule-summary').text('');

            return;
        }

        const when =
            days === 0
                ? lang('whatsapp_same_day')
                : days === 1
                  ? lang('whatsapp_day_before')
                  : lang('whatsapp_days_before_n').replace('%s', days);

        $('#whatsapp-schedule-summary').text(
            lang('whatsapp_schedule_summary')
                .replace('%1$s', when)
                .replace('%2$s', time)
                .replace('%3$s', formatMinutes(delay))
                .replace('%4$s', formatMinutes(notice)),
        );
    }

    /**
     * Check that an active integration has what it needs to send.
     *
     * @return {Boolean}
     */
    function validate() {
        $page.find('.is-invalid').removeClass('is-invalid');

        // The rules are checked always: they are saved whether the integration is on or not.
        const $wrongRules = $(
            '#whatsapp-send-time, #whatsapp-days-before, #whatsapp-booking-delay, #whatsapp-min-notice',
        )
            .filter((index, field) => !field.value.trim() || !field.checkValidity())
            .addClass('is-invalid');

        if ($wrongRules.length) {
            App.Layouts.Backend.displayNotification(lang('whatsapp_rules_invalid'));

            return false;
        }

        if (!$('#whatsapp-enabled').prop('checked')) {
            return true;
        }

        const $missing = $('#whatsapp-phone-number-id, #whatsapp-template-name, #whatsapp-template-language').filter(
            (index, field) => !field.value.trim(),
        );

        // The secrets count as filled in when they are already saved.
        $('#whatsapp-access-token')
            .filter((index, field) => !field.value.trim() && !field.placeholder)
            .each((index, field) => $missing.push(field));

        $missing.each((index, field) => field.classList.add('is-invalid'));

        // A missing field of the folded section is shown.
        if ($missing.closest('details').length) {
            $page.find('details').prop('open', true);
        }

        if ($missing.length) {
            App.Layouts.Backend.displayNotification(lang('fields_are_required'));

            return false;
        }

        return true;
    }

    /**
     * Save the settings.
     */
    function save() {
        if (!validate()) {
            return;
        }

        $.post(App.Utils.Url.siteUrl('whatsapp_settings/save'), {
            csrf_token: vars('csrf_token'),
            whatsapp_settings: serialize(),
        }).done(() => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));

            savedEnabled = $('#whatsapp-enabled').prop('checked');
            showState();

            // The secrets typed are saved: they are no longer shown.
            $('#whatsapp-access-token, #whatsapp-app-secret').each((index, field) => {
                if (field.value) {
                    field.value = '';
                    field.placeholder = lang('whatsapp_secret_saved');
                }
            });
        });
    }

    /**
     * Send the template to the number typed, with the saved settings.
     */
    function sendTest() {
        const $phone = $('#whatsapp-test-phone');

        if (!$phone.val().trim()) {
            $phone.addClass('is-invalid').trigger('focus');

            return;
        }

        $phone.removeClass('is-invalid');

        $.post(App.Utils.Url.siteUrl('whatsapp_settings/test'), {
            csrf_token: vars('csrf_token'),
            phone: $phone.val().trim(),
        }).done(() => App.Layouts.Backend.displayNotification(lang('whatsapp_test_sent')));
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        deserialize(vars('whatsapp_settings'));
        savedEnabled = $('#whatsapp-enabled').prop('checked');
        showScheduleSummary();
        showState();

        $('#whatsapp-enabled').on('change', showState);

        $page.on(
            'input change',
            '#whatsapp-send-time, #whatsapp-days-before, #whatsapp-booking-delay, #whatsapp-min-notice',
            showScheduleSummary,
        );

        $('#save-settings').on('click', save);
        $('#whatsapp-send-test').on('click', sendTest);

        // The webhook details are copied with a click.
        $('#whatsapp-webhook-url, #whatsapp-verify-token').on('focus click', (event) => event.currentTarget.select());
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
