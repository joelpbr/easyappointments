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
 * Pathologies page.
 *
 * The catalog of pathologies placed on the foot diagrams of the visits: the list on the left, filtered while typing,
 * and the chosen or new pathology on the right, with its name, description and icon. A pathology placed on some visit
 * cannot be removed (Pathologies_model::delete()).
 */
App.Pages.Pathologies = (function () {
    const $list = $('#pathologies-list');
    const $filter = $('#pathologies-filter');
    const $form = $('#pathology-form');
    const $title = $('#pathology-title');
    const $message = $('#pathology-message');
    const $id = $('#pathology-id');
    const $name = $('#pathology-name');
    const $description = $('#pathology-description');
    const $image = $('#pathology-image');
    const $preview = $('#pathology-image-preview');
    const $imageRequired = $('#pathology-image-required');
    const $usage = $('#pathology-usage');
    const $save = $('#save-pathology');
    const $cancel = $('#cancel-pathology');
    const $delete = $('#delete-pathology');

    let pathologies = [];
    // The icon chosen for the pathology shown, as a data URL, until it is saved.
    let newImage = null;

    /**
     * Whether the user can do something with the catalog.
     *
     * @param {String} action add, edit or delete.
     *
     * @return {Boolean}
     */
    function can(action) {
        return Boolean(vars('privileges')?.services?.[action]);
    }

    /**
     * Get the pathology shown, or null for a new one or none.
     *
     * @return {Object|null}
     */
    function current() {
        return pathologies.find((pathology) => pathology.id === Number($id.val())) || null;
    }

    /**
     * Load the catalog and show it.
     */
    function load() {
        $.getJSON(App.Utils.Url.siteUrl('pathologies/search')).done((response) => {
            pathologies = response;
            renderList();
        });
    }

    /**
     * Show the catalog, filtered by the text of the search box.
     */
    function renderList() {
        const keyword = $filter.val().trim().toLowerCase();
        const shown = pathologies.filter(
            (pathology) =>
                !keyword ||
                pathology.name.toLowerCase().includes(keyword) ||
                (pathology.description || '').toLowerCase().includes(keyword),
        );

        $list.empty();

        if (!shown.length) {
            $('<em/>', {
                class: 'text-muted',
                text: lang(pathologies.length ? 'no_records_found' : 'clinical_no_pathologies'),
            }).appendTo($list);

            return;
        }

        shown.forEach((pathology) => {
            $('<button/>', {
                'type': 'button',
                'class':
                    'list-group-item list-group-item-action d-flex align-items-center gap-3' +
                    (pathology.id === Number($id.val()) ? ' active' : ''),
                'data-pathology-id': pathology.id,
                'html': [
                    $('<img/>', {class: 'ea-pathology-icon', src: pathology.image_url, alt: ''}),
                    $('<span/>', {
                        class: 'flex-grow-1 text-start',
                        html: [
                            $('<strong/>', {class: 'd-block', text: pathology.name}),
                            pathology.description
                                ? $('<small/>', {
                                      class: 'd-block text-truncate ea-pathology-description',
                                      text: pathology.description,
                                  })
                                : null,
                        ],
                    }),
                    pathology.marker_count
                        ? $('<span/>', {
                              'class': 'badge rounded-pill text-bg-secondary',
                              'title': lang('pathology_used_times').replace('%s', pathology.marker_count),
                              'text': pathology.marker_count,
                          })
                        : null,
                ],
            }).appendTo($list);
        });
    }

    /**
     * Show a pathology on the form, a new one (empty object) or none (null).
     *
     * @param {Object|null} pathology
     */
    function show(pathology) {
        const isNew = Boolean(pathology) && !pathology.id;

        // Like the other pages of the menu, the form takes the place of the list while a pathology is shown.
        $('#pathologies-page').toggleClass('editing', Boolean(pathology));
        newImage = null;
        $message.prop('hidden', true).text('');
        $form.find('.is-invalid').removeClass('is-invalid');
        $image.val('');

        $id.val(pathology?.id || '');
        $name.val(pathology?.name || '');
        $description.val(pathology?.description || '');
        $preview.attr('src', pathology?.image_url || '').prop('hidden', !pathology?.image_url);
        $imageRequired.prop('hidden', !isNew);

        $title.text(isNew ? lang('add') : pathology ? pathology.name : lang('details'));

        $usage
            .text(pathology?.marker_count ? lang('pathology_used_times').replace('%s', pathology.marker_count) : '')
            .prop('hidden', !pathology?.marker_count);

        const editable = Boolean(pathology) && can(isNew ? 'add' : 'edit');

        $form.prop('disabled', !editable);
        $save.prop('disabled', !editable);
        $cancel.prop('disabled', !pathology);
        // One that is placed on some visit cannot be removed.
        $delete.prop('disabled', !pathology?.id || pathology.marker_count > 0);
        $delete.attr(
            'title',
            pathology?.marker_count ? lang('pathology_in_use').replace('%s', pathology.marker_count) : '',
        );

        $list.children().each((index, item) => {
            item.classList.toggle('active', Number(item.dataset.pathologyId) === Number(pathology?.id));
        });

        if (editable) {
            $name.trigger('focus');
        }
    }

    /**
     * Show a problem of the form.
     *
     * @param {String} text
     * @param {jQuery} [$field]
     */
    function showError(text, $field = null) {
        $message.text(text).prop('hidden', false);
        $field?.addClass('is-invalid');
    }

    /**
     * Read the icon chosen on the file field.
     */
    function readImage() {
        const file = $image[0].files[0];

        $image.removeClass('is-invalid');
        $message.prop('hidden', true);

        if (!file) {
            newImage = null;
            $preview.attr('src', current()?.image_url || '').prop('hidden', !current()?.image_url);

            return;
        }

        if (!file.type.startsWith('image/') || file.size > vars('pathology_max_image_bytes')) {
            newImage = null;
            $image.val('');
            showError(lang('pathology_image_invalid'), $image);

            return;
        }

        const reader = new FileReader();

        reader.onload = () => {
            newImage = reader.result;
            $preview.attr('src', newImage).prop('hidden', false);
        };

        reader.readAsDataURL(file);
    }

    /**
     * Save the pathology of the form.
     */
    function save() {
        $form.find('.is-invalid').removeClass('is-invalid');
        $message.prop('hidden', true);

        const pathology = {
            name: $name.val().trim(),
            description: $description.val().trim(),
        };

        if ($id.val()) {
            pathology.id = $id.val();
        }

        if (!pathology.name) {
            showError(lang('fields_are_required'), $name);

            return;
        }

        if (!pathology.id && !newImage) {
            showError(lang('pathology_image_required'), $image);

            return;
        }

        if (newImage) {
            pathology.image = newImage;
        }

        $.post(App.Utils.Url.siteUrl('pathologies/save'), {
            csrf_token: vars('csrf_token'),
            pathology,
        }).done((response) => {
            App.Layouts.Backend.displayNotification(lang('pathology_saved'));
            // Back to the list, which shows the saved pathology.
            show(null);
            load();
        });
    }

    /**
     * Remove the pathology of the form, after asking.
     */
    function remove() {
        const pathology = current();

        if (!pathology) {
            return;
        }

        App.Utils.Message.show(
            lang('pathologies'),
            lang('pathology_delete_question').replace('%s', App.Utils.String.escapeHtml(pathology.name)),
            [
                {
                    text: lang('cancel'),
                    className: 'btn btn-outline-secondary',
                    click: (event, messageModal) => messageModal.hide(),
                },
                {
                    text: lang('delete'),
                    className: 'btn btn-danger',
                    click: (event, messageModal) => {
                        messageModal.hide();

                        $.post(App.Utils.Url.siteUrl('pathologies/destroy'), {
                            csrf_token: vars('csrf_token'),
                            pathology_id: pathology.id,
                        }).done(() => {
                            App.Layouts.Backend.displayNotification(lang('pathology_deleted'));
                            show(null);
                            load();
                        });
                    },
                },
            ],
        );
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        $('#add-pathology').on('click', () => show({}));

        $list.on('click', '[data-pathology-id]', (event) => {
            show(pathologies.find((pathology) => pathology.id === Number(event.currentTarget.dataset.pathologyId)));
        });

        $filter.on('input', renderList);
        $image.on('change', readImage);
        $save.on('click', save);
        $cancel.on('click', () => show(null));
        $delete.on('click', remove);

        $form.on('keydown', 'input', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                save();
            }
        });

        show(null);
        load();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
