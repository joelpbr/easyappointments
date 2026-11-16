/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * Backups page.
 *
 * Takes a copy of the whole database and downloads it, and restores a copy (uploaded or from the list) after asking.
 * Restoring replaces every piece of data, so the question names the copy and the page waits for the answer of the server
 * with the loading layer in front.
 */
App.Pages.Backups = (function () {
    const $page = $('#backups-page');
    const $file = $('#backup-file');
    const $upload = $('#backup-upload');
    const maxUploadBytes = Number($page.data('max-upload-bytes')) || 0;

    /**
     * Show a message with a single close button.
     *
     * @param {String} message
     */
    function tell(message) {
        App.Utils.Message.show(lang('backups'), message, [
            {
                text: lang('close'),
                className: 'btn btn-outline-primary',
                click: (event, messageModal) => messageModal.hide(),
            },
        ]);
    }

    /**
     * Post to the page, with the loading layer and the errors of the server told in plain words.
     *
     * @param {String} action
     * @param {FormData} data
     *
     * @return {jQuery.Promise}
     */
    function post(action, data) {
        data.append('csrf_token', vars('csrf_token'));

        $('#loading').show();

        return $.ajax({
            url: App.Utils.Url.siteUrl('backups/' + action),
            method: 'POST',
            data,
            processData: false,
            contentType: false,
            dataType: 'json',
            // Errors are told here, not by the general "unexpected issues" message.
            global: false,
        })
            .fail((jqXHR) => {
                const message = jqXHR.responseJSON?.message || lang('backup_nothing_changed');

                tell(message);
            })
            .always(() => $('#loading').hide());
    }

    /**
     * Take a copy now and download it.
     */
    function create() {
        post('create', new FormData()).done((response) => {
            window.location.href = App.Utils.Url.siteUrl('backups/download/' + encodeURIComponent(response.name));

            // The new copy is also in the list.
            setTimeout(() => window.location.reload(), 1500);
        });
    }

    /**
     * Ask before replacing the data, then restore.
     *
     * @param {String} label What the question calls the copy (its date or the name of the file).
     * @param {FormData} data name of a copy of the list, or backup_file.
     */
    function restore(label, data, legacy = false) {
        const question = lang(legacy ? 'backup_podologo_confirm' : 'backup_restore_confirm');
        App.Utils.Message.show(
            lang('backup_restore_title'),
            question.replace('%s', App.Utils.String.escapeHtml(label)),
            [
                {
                    text: lang('cancel'),
                    className: 'btn btn-outline-secondary',
                    click: (event, messageModal) => messageModal.hide(),
                },
                {
                    text: lang('backup_restore_button'),
                    className: 'btn btn-danger',
                    click: (event, messageModal) => {
                        messageModal.hide();

                        post('restore', data).done((response) => {
                            if (response.logged_out) {
                                App.Utils.Message.show(lang('backups'), lang('backup_restored_logged_out'), [
                                    {
                                        text: lang('login'),
                                        className: 'btn btn-primary',
                                        click: () => (window.location.href = App.Utils.Url.siteUrl('login')),
                                    },
                                ]);

                                return;
                            }

                            App.Utils.Message.show(
                                lang('backups'),
                                lang('backup_restored').replace('%s', response.previous),
                                [
                                    {
                                        text: lang('close'),
                                        className: 'btn btn-primary',
                                        click: () => window.location.reload(),
                                    },
                                ],
                            );
                        });
                    },
                },
            ],
        );
    }

    $('#backup-create').on('click', create);

    $('#backup-delete').on('click', () => {
        const $confirmation = $('<input/>', {
            type: 'text',
            class: 'form-control mt-3',
            autocomplete: 'off',
            'aria-label': lang('backup_delete_type'),
            placeholder: 'DELETE',
        });
        const $message = $('<div/>')
            .append($('<p/>').text(lang('backup_delete_confirm')))
            .append($('<label/>').text(lang('backup_delete_type')).append($confirmation));

        const $modal = App.Utils.Message.show(lang('backup_delete'), ' ', [
            {
                text: lang('cancel'),
                className: 'btn btn-outline-secondary',
                click: (event, modal) => modal.hide(),
            },
            {
                text: lang('backup_delete'),
                className: 'btn btn-danger',
                click: (event, modal) => {
                    if ($confirmation.val() !== 'DELETE') {
                        $confirmation.addClass('is-invalid').trigger('focus');
                        return;
                    }
                    const data = new FormData();
                    data.append('confirmation', $confirmation.val());
                    $(event.currentTarget).prop('disabled', true);
                    modal.hide();
                    post('delete_current', data).done(() => window.location.reload());
                },
            },
        ]);
        $modal.find('.modal-body').empty().append($message);
        $modal.on('shown.bs.modal', () => $confirmation.trigger('focus'));
    });

    $page.on('click', '.backup-restore-listed', (event) => {
        const $button = $(event.currentTarget);
        const data = new FormData();

        data.append('name', $button.data('name'));

        restore($button.data('label'), data);
    });

    $file.on('change', () => {
        $upload.prop('disabled', !$file[0].files.length);
        $('#podologo-canvas').prop('hidden', !/\.db$/i.test($file[0].files[0]?.name || ''));
    });

    $upload.on('click', () => {
        const file = $file[0].files[0];

        if (!file) {
            tell(lang('backup_choose_file'));

            return;
        }

        // Bigger than PHP accepts, the request would arrive empty and fail without saying why.
        if (maxUploadBytes && file.size > maxUploadBytes) {
            tell(lang('backup_file_too_big'));

            return;
        }

        const data = new FormData();

        data.append('backup_file', file);
        data.append('canvas_width', $('#podologo-width').val());
        data.append('canvas_height', $('#podologo-height').val());

        restore(file.name, data, /\.db$/i.test(file.name));
    });

    return {};
})();
