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
 * WhatsApp messages page.
 *
 * Sends a message of the list again. When it cannot be sent, says why and offers to cancel it, try again or mark it
 * as sent by hand. Messages can also be removed from the list. The list is loaded again after every change.
 */
App.Pages.WhatsappMessages = (function () {
    const $page = $('#whatsapp-messages-page');

    /**
     * Do something with a message.
     *
     * @param {String} action retry, mark_sent, cancel or destroy.
     * @param {Number} id
     *
     * @return {jQuery.jqXHR}
     */
    function request(action, id) {
        return $.post(App.Utils.Url.siteUrl('whatsapp_messages/' + action), {
            csrf_token: vars('csrf_token'),
            id,
        });
    }

    /**
     * Load the list again, with the statuses as they are now.
     */
    function reload() {
        window.location.reload();
    }

    /**
     * Escape a text to show it as HTML.
     *
     * @param {String} text
     *
     * @return {String}
     */
    function escape(text) {
        return $('<div/>')
            .text(text || '')
            .html();
    }

    /**
     * Say that a message could not be sent, and what can be done with it.
     *
     * @param {Number} id
     * @param {String} error What went wrong, in plain words.
     * @param {String|null} detail What Meta answered exactly, when it is explained.
     */
    function showFailure(id, error, detail) {
        let chosen = false;

        const then = (action) => (event, messageModal) => {
            chosen = true;
            messageModal.hide();
            request(action, id).done(reload);
        };

        App.Utils.Message.show(
            lang('whatsapp_retry_failed'),
            '<p class="mb-0">' +
                escape(error) +
                '</p>' +
                (detail ? '<p class="small text-muted mt-2 mb-0">' + escape(detail) + '</p>' : ''),
            [
                {text: lang('whatsapp_cancel_message'), className: 'btn btn-outline-danger', click: then('cancel')},
                {text: lang('whatsapp_mark_sent'), className: 'btn btn-outline-primary', click: then('mark_sent')},
                {
                    text: lang('whatsapp_try_again'),
                    className: 'btn btn-primary',
                    click: (event, messageModal) => {
                        chosen = true;
                        messageModal.hide();
                        retry(id);
                    },
                },
            ],
        );

        // Closed without choosing: the list shows what happened (the attempt and its error).
        $('#message-modal').one('hidden.bs.modal', () => {
            if (!chosen) {
                reload();
            }
        });
    }

    /**
     * Send a message again.
     *
     * @param {Number} id
     */
    function retry(id) {
        const $buttons = $page.find('tr[data-id="' + id + '"] button').prop('disabled', true);

        request('retry', id)
            .done((response) => {
                if (response.ok) {
                    App.Layouts.Backend.displayNotification(lang('whatsapp_message_sent'));
                    reload();

                    return;
                }

                showFailure(id, response.error, response.detail);
            })
            .always(() => $buttons.prop('disabled', false));
    }

    /**
     * Ask before sending again a message that already went out, then send it.
     *
     * @param {Number} id
     * @param {String} status
     */
    function confirmRetry(id, status) {
        if (['failed', 'review', 'cancelled'].includes(status)) {
            retry(id);

            return;
        }

        App.Utils.Message.show(lang('whatsapp_retry'), '<p class="mb-0">' + lang('whatsapp_resend_question') + '</p>', [
            {
                text: lang('cancel'),
                className: 'btn btn-outline-primary',
                click: (event, messageModal) => messageModal.hide(),
            },
            {
                text: lang('whatsapp_retry'),
                className: 'btn btn-primary',
                click: (event, messageModal) => {
                    messageModal.hide();
                    retry(id);
                },
            },
        ]);
    }

    /**
     * Ask before removing a message from the list, then remove it.
     *
     * @param {Number} id
     */
    function confirmDelete(id) {
        App.Utils.Message.show(lang('delete'), '<p class="mb-0">' + lang('whatsapp_delete_question') + '</p>', [
            {
                text: lang('cancel'),
                className: 'btn btn-outline-primary',
                click: (event, messageModal) => messageModal.hide(),
            },
            {
                text: lang('delete'),
                className: 'btn btn-danger',
                click: (event, messageModal) => {
                    messageModal.hide();
                    request('destroy', id).done(reload);
                },
            },
        ]);
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        $page.on('click', '.ea-whatsapp-retry', (event) => {
            const $row = $(event.currentTarget).closest('tr');

            confirmRetry(Number($row.data('id')), String($row.data('status')));
        });

        $page.on('click', '.ea-whatsapp-delete', (event) => {
            confirmDelete(Number($(event.currentTarget).closest('tr').data('id')));
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
