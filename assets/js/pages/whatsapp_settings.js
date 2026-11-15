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
 * Saves the settings of the WhatsApp reminders and sends a test message with the saved ones. The access token and the
 * app secret are never shown: an empty field keeps the saved value.
 */
App.Pages.WhatsappSettings = (function () {
    const $page = $('#whatsapp-settings');

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
     * Check that an active integration has what it needs to send.
     *
     * @return {Boolean}
     */
    function validate() {
        $page.find('.is-invalid').removeClass('is-invalid');

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

        $('#save-settings').on('click', save);
        $('#whatsapp-send-test').on('click', sendTest);

        // The webhook details are copied with a click.
        $('#whatsapp-webhook-url, #whatsapp-verify-token').on('focus click', (event) => event.currentTarget.select());
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
