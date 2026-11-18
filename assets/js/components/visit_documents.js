/**
 * Documents of the selected visit: the menu of the document catalog of the clinic and the card that asks for what a
 * document still needs and generates its PDF.
 *
 * Nothing about the documents themselves is decided here: groups, document types, fields, limits and which ones
 * must be reviewed all come from the server (Visit_documents::client_catalog()).
 */
App.Components.VisitDocuments = (function () {
    const {show, close, label} = App.Components.DocumentDialog;
    // Fields the records usually fill in: once they have a value they wait folded under "data", out of the way.
    const RECORD_FIELDS = new Set([
        'patient_name',
        'document_number',
        'birth_date',
        'date',
        'visit_date',
        'visit_time',
        'dispensing_date',
    ]);
    const CUSTOM_OPTION = '__custom__';
    const MONEY_PATTERN = '[0-9]{1,7}([.,][0-9]{1,2})?';
    let $toggle;
    let $panel;
    let $form;
    let $status;
    // What the server said about the visit of the menu; null until its menu is opened.
    let context = null;
    let appointmentId = null;
    let templateId = null;
    // Each load of the menu gets a number: the answer of an older one (another visit) is dropped.
    let menuRequest = 0;
    let generating = false;
    let formRevision = 0;

    function closeCard() {
        formRevision++;
        close($panel[0]);
    }

    const visitId = () => Number($toggle.attr('data-appointment-id'));

    const templatesOf = (group) => context.templates.filter((template) => template.group === group.id);

    function message(content, error = false) {
        $status.empty();
        if (content)
            $('<div/>', {class: error ? 'alert alert-danger py-2' : 'small text-muted mb-2', text: content}).appendTo(
                $status,
            );
    }

    function menuItem(content) {
        return $('<li/>').append(content);
    }

    function menuNote(text, danger = false) {
        return menuItem($('<span/>', {class: 'dropdown-item-text ' + (danger ? 'text-danger' : 'text-muted'), text}));
    }

    async function loadMenu() {
        const id = visitId();
        if (!id) return;
        const request = ++menuRequest;
        const $menu = $('#clinical-documents-menu')
            .empty()
            .append(menuNote(lang('loading')));
        try {
            const response = await App.Http.ClinicalRecords.findDocumentOptions(id);
            if (request !== menuRequest || id !== visitId()) return;
            context = response;
            appointmentId = id;
            $menu.empty();
            context.groups.forEach((group) => {
                // A group with a single entry opens the card with its document types to choose from there.
                const entries = group.single_entry
                    ? [{label: group.single_entry.label, data: {'data-document-group': group.id}}]
                    : templatesOf(group).map((template) => ({
                          label: template.label,
                          data: {'data-document-type': template.id},
                      }));
                if (!entries.length) return;
                if ($menu.children().length) menuItem($('<hr/>', {class: 'dropdown-divider'})).appendTo($menu);
                menuItem($('<h6/>', {class: 'dropdown-header', text: label(group.label)})).appendTo($menu);
                entries.forEach((entry) =>
                    menuItem(
                        $('<button/>', {
                            type: 'button',
                            class: 'dropdown-item',
                            text: label(entry.label),
                            ...entry.data,
                        }),
                    ).appendTo($menu),
                );
            });
            bootstrap.Dropdown.getInstance($toggle[0])?.update();
        } catch (error) {
            if (request === menuRequest) $menu.empty().append(menuNote(lang('visit_document_load_error'), true));
        }
    }

    /**
     * @param {String} id Document type.
     * @param {Object|null} group The group whose document types can be chosen on the card, if it came from one.
     */
    function openCard(id, group = null) {
        if (!context) return;
        const $variant = $('#clinical-document-variant').empty();
        $('#clinical-document-variant-wrap').prop('hidden', !group);
        if (group) {
            $('label[for="clinical-document-variant"]').text(label(group.single_entry.choice_label));
            templatesOf(group).forEach((template) =>
                $('<option/>', {value: template.id, text: label(template.label)}).appendTo($variant),
            );
            $variant.val(id);
        }
        $form.prop('hidden', false);
        selectTemplate(id);
        show($panel[0]);
        (group ? $variant : $form.find('input:visible, textarea:visible, select:visible').first()).trigger('focus');
    }

    function resetCard() {
        formRevision++;
        $('#clinical-document-fields, #clinical-document-known-fields').empty();
        $('#clinical-document-known').prop('hidden', true).prop('open', false);
        $('#clinical-document-professional-wrap, #clinical-document-signer, #clinical-document-review-wrap').prop(
            'hidden',
            true,
        );
        $('#clinical-document-professional').prop('required', false).val('');
        $('#clinical-document-reviewed').prop('checked', false).prop('required', false);
        $('#clinical-document-download').prop('disabled', true);
        message('');
    }

    function selectTemplate(id) {
        resetCard();
        templateId = id;
        const template = context.templates.find((item) => item.id === id);
        if (!template) return;
        $('#clinical-document-heading').text(label(template.label));
        showSigner(template);
        const values = {...template.defaults, ...context.defaults, ...openRecordValues()};
        template.fields.forEach((field) => {
            const value = values[field.source ?? field.key] ?? '';
            const known = RECORD_FIELDS.has(field.key) && String(value).trim() !== '';
            fieldControl(field, value).appendTo(
                known ? '#clinical-document-known-fields' : '#clinical-document-fields',
            );
            if (known) $('#clinical-document-known').prop('hidden', false);
        });
        $('#clinical-document-review-wrap').prop('hidden', !template.requires_review);
        $('#clinical-document-reviewed').prop('required', template.requires_review);
        const allergies = $('#clinical-record-modal [data-record="allergies"]').val() ?? context.allergies;
        $('#clinical-document-allergies')
            .text(lang('clinical_allergies') + ': ' + allergies)
            .prop('hidden', !template.requires_review || !allergies);
        $('#clinical-document-download').prop('disabled', false);
    }

    /** A restricted document is signed by the professional who is signed in, or by the one chosen here. */
    function showSigner(template) {
        if (template.restricted && !context.current_professional) {
            $('#clinical-document-professional-wrap').prop('hidden', false);
            const $select = $('#clinical-document-professional').empty().prop('required', true);
            $('<option/>', {value: '', text: lang('visit_document_choose_professional')}).appendTo($select);
            Object.entries(context.professionals).forEach(([id, name]) =>
                $('<option/>', {value: id, text: name}).appendTo($select),
            );
            return;
        }
        const name = template.restricted ? context.professionals[context.current_professional] : context.provider_name;
        $('#clinical-document-signer')
            .text(lang('provider') + ': ' + name)
            .prop('hidden', false);
    }

    /**
     * What is typed on the open record and not saved yet: the document uses it, without silently changing the
     * patient or saving the visit.
     */
    function openRecordValues() {
        const values = {};
        const patientName = ['first_name', 'last_name']
            .map((name) => $('#clinical-record-modal [data-customer="' + name + '"]').val())
            .filter(Boolean)
            .join(' ');
        if (patientName) values.patient_name = patientName;
        if ($('#clinical-document-number').length) values.document_number = $('#clinical-document-number').val();
        const birthPicker = $('#clinical-birth-date')[0]?._flatpickr;
        if (birthPicker)
            values.birth_date = birthPicker.selectedDates[0]
                ? moment(birthPicker.selectedDates[0]).format('YYYY-MM-DD')
                : '';
        for (const key of ['reason', 'diagnosis', 'treatment']) {
            const $field = $('#clinical-visit-detail [data-visit="' + key + '"]');
            if ($field.length && !$field.closest('[data-field]').prop('hidden')) values[key] = $field.val();
        }
        return values;
    }

    function fieldControl(field, value) {
        const id = 'visit-document-' + field.key;
        const multiline = field.type === 'textarea' && !field.options?.length;
        const $group = $('<div/>', {class: multiline ? 'col-12' : 'col-12 col-md-6'});
        $('<label/>', {
            for: id,
            class: 'form-label',
            text: label(field.label) + (field.required ? ' *' : ''),
        }).appendTo($group);
        return $group.append(
            field.options?.length ? choiceControl(field, id, value) : inputControl(field, id, value, multiline),
        );
    }

    function inputControl(field, id, value, multiline) {
        const $input = $(multiline ? '<textarea/>' : '<input/>', {
            id,
            class: 'form-control',
            name: field.key,
            required: field.required,
            maxlength: field.max_length,
            'data-document-field': field.key,
        }).val(value ?? '');
        if (multiline) return $input.attr('rows', field.key === 'instructions' ? 6 : 2);
        $input.attr('type', {date: 'date', time: 'time', integer: 'number'}[field.type] ?? 'text');
        if (field.type === 'integer') $input.attr({min: field.min, max: field.max, step: 1});
        if (field.type === 'money') $input.attr({inputmode: 'decimal', pattern: MONEY_PATTERN});
        if (field.not_future) $input.attr('max', context.defaults.date);
        return $input;
    }

    /** The options of the catalog plus "other", which shows a free input that takes the place of the select. */
    function choiceControl(field, id, value) {
        const $select = $('<select/>', {id, class: 'form-select', required: field.required});
        $('<option/>', {value: '', text: 'â€”'}).appendTo($select);
        field.options.forEach((option) =>
            $('<option/>', {
                value: option,
                text:
                    field.type === 'money'
                        ? Number(option).toLocaleString(vars('language_code'), {style: 'currency', currency: 'EUR'})
                        : option,
            }).appendTo($select),
        );
        $('<option/>', {value: CUSTOM_OPTION, text: lang('visit_document_other')}).appendTo($select);
        const $custom = $(field.type === 'textarea' ? '<textarea/>' : '<input/>', {
            id: id + '-custom',
            class: 'form-control mt-2',
            maxlength: field.max_length,
            'aria-label': label(field.label),
        });
        if (field.type === 'textarea') $custom.attr('rows', 2);
        if (field.type === 'money') $custom.attr({inputmode: 'decimal', pattern: MONEY_PATTERN});
        // Whichever of the two is in use carries the field; the other one is left out of the request.
        const sync = (focus = false) => {
            const custom = $select.val() === CUSTOM_OPTION;
            $custom.prop({hidden: !custom, disabled: !custom, required: custom && field.required});
            (custom ? $select : $custom).removeAttr('data-document-field');
            (custom ? $custom : $select).attr('data-document-field', field.key);
            if (custom && focus) $custom.trigger('focus');
        };
        const initial = String(value ?? '');
        if (initial && !field.options.includes(initial)) {
            $select.val(CUSTOM_OPTION);
            $custom.val(initial);
        } else $select.val(initial);
        sync();
        $select.on('change', () => sync(true));
        return [$select, $custom];
    }

    async function generate(event) {
        event.preventDefault();
        if (generating || !context || appointmentId !== visitId()) return;
        // Browsers cannot focus an invalid control inside closed details.
        if ($('#clinical-document-known-fields :invalid').length) $('#clinical-document-known').prop('open', true);
        if (!$form[0].reportValidity()) return;
        const revision = formRevision;
        const fields = {};
        $form.find('[data-document-field]').each((index, input) => {
            fields[input.dataset.documentField] = input.value;
        });
        const visit = context;
        generating = true;
        const $button = $('#clinical-document-download').prop('disabled', true);
        message(lang('visit_document_preparing'));
        try {
            const response = await App.Http.ClinicalRecords.generateDocument(appointmentId, {
                document_type: templateId,
                professional: $('#clinical-document-professional').val() || '',
                reviewed: $('#clinical-document-reviewed').prop('checked') ? '1' : '0',
                fields,
            });
            if (!Number(response?.document_id)) throw new Error();
            const url = App.Http.ClinicalRecords.savedDocumentUrl(response.document_id);
            if (context === visit && revision === formRevision && $panel[0].open) {
                $panel[0].close();
                document.getElementById('pdf-actions').showPdf(url, $('#clinical-document-heading').text());
            }
            if (context === visit) message('');
        } catch (error) {
            if (context === visit) message(error.responseJSON?.message || lang('visit_document_generate_error'), true);
        } finally {
            generating = false;
            $button.prop('disabled', false);
        }
    }

    /** Another visit, or the record closed: its menu and its card no longer apply. */
    function dismiss() {
        ++menuRequest;
        context = null;
        appointmentId = null;
        close($panel[0]);
        bootstrap.Dropdown.getInstance($toggle[0])?.hide();
    }

    document.addEventListener('DOMContentLoaded', () => {
        $toggle = $('#clinical-visit-documents');
        $panel = $('#clinical-document-panel');
        $form = $('#clinical-document-form');
        $status = $('#clinical-document-status');

        // Escape the visit column's scrolling box so the menu remains fully visible on narrow screens.
        bootstrap.Dropdown.getOrCreateInstance($toggle[0], {
            popperConfig: (configuration) => ({
                ...configuration,
                strategy: 'fixed',
                modifiers: [
                    ...configuration.modifiers,
                    {name: 'preventOverflow', options: {boundary: document.body, padding: 12}},
                ],
            }),
        });
        $('#clinical-documents-dropdown').on('show.bs.dropdown', loadMenu);
        $('#clinical-documents-menu')
            .on('click', '[data-document-type]', (event) => openCard(event.currentTarget.dataset.documentType))
            .on('click', '[data-document-group]', (event) => {
                const group = context?.groups.find((item) => item.id === event.currentTarget.dataset.documentGroup);
                const first = group && templatesOf(group)[0];
                if (first) openCard(first.id, group);
            });
        $toggle.on('clinical:visit-changed', dismiss);
        $('#clinical-record-modal').on('hidden.bs.modal', dismiss);

        $('#clinical-document-variant').on('change', (event) => selectTemplate(event.currentTarget.value));
        // Let reportValidity open the saved-data section before browser validation runs.
        $form.attr('novalidate', 'novalidate').on('submit', generate).on('input change', () => { formRevision++; });
        $('#clinical-document-close').on('click', closeCard);
        // The native dialog owns the focus while the Bootstrap modal of the record stays open underneath.
        $panel
            .on('focusin', (event) => event.stopPropagation())
            .on('cancel', (event) => {
                event.preventDefault();
                closeCard();
            })
            .on('close', () => $toggle.trigger('focus'));
    });

    return {};
})();
