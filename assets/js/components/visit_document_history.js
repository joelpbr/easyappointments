/**
 * Saved documents of the patient of the clinical record: the list, 25 at a time, and the removal of one of them
 * after a confirmation. Opened from the header of the record.
 */
App.Components.VisitDocumentHistory = (function () {
    const {show, close, label} = App.Components.DocumentDialog;
    let $button;
    let $history;
    let $confirmation;
    // Each load gets a number: the answer of an older one (another patient, a closed card) is dropped.
    let request = 0;
    let loading = false;
    let cursor = 0;
    let pendingDeletion = null;
    let deleting = false;

    const customerId = () => Number($button.attr('data-customer-id'));

    const formatDate = (value) => moment(value).format('DD/MM/YYYY HH:mm');

    function row(item, customer) {
        const title = label(item.titles);
        const $row = $('<div/>', {class: 'list-group-item px-0 py-3'});
        $('<div/>', {class: 'fw-semibold', text: title}).appendTo($row);
        [
            formatDate(item.created_at) + ' · ' + item.professional_name,
            lang('clinical_visits') + ': ' + formatDate(item.visit_datetime),
            lang('visit_document_created_by') + ': ' + item.created_by_name,
        ].forEach((text) => $('<div/>', {class: 'small text-muted', text}).appendTo($row));
        const $actions = $('<div/>', {class: 'd-flex gap-2 mt-2'}).appendTo($row);
        $('<a/>', {
            class: 'btn btn-outline-primary btn-sm',
            href: App.Http.ClinicalRecords.savedDocumentUrl(item.id),
            'data-pdf-actions': title,
            target: '_blank',
            rel: 'noopener',
            text: lang('visit_document_view'),
        }).appendTo($actions);
        $('<button/>', {
            type: 'button',
            class: 'btn btn-outline-danger btn-sm ms-auto',
            title: lang('delete'),
            'aria-label': lang('delete'),
        })
            .append($('<i/>', {class: 'fas fa-trash-can', 'aria-hidden': 'true'}))
            .on('click', () => {
                pendingDeletion = {id: item.id, customer};
                $('#clinical-document-delete-name').text(title + ' · ' + formatDate(item.created_at));
                $('#clinical-document-delete-error').text('');
                show($confirmation[0]);
                $('#clinical-document-delete-cancel').trigger('focus');
            })
            .appendTo($actions);
        return $row;
    }

    async function load(fromStart = false) {
        if (loading && !fromStart) return;
        const customer = customerId();
        if (!customer) return;
        const current = ++request;
        loading = true;
        const $list = $('#clinical-document-history-list');
        const $status = $('#clinical-document-history-status').text(lang('loading'));
        const $more = $('#clinical-document-history-more').prop('disabled', true);
        if (fromStart) {
            cursor = 0;
            $list.empty();
            $more.prop('hidden', true);
        }
        try {
            const response = await App.Http.ClinicalRecords.findDocumentHistory(customer, cursor);
            if (current !== request || !$history[0].open) return;
            response.documents.forEach((item) => {
                row(item, customer).appendTo($list);
                cursor = Number(item.id);
            });
            $status.text($list.children().length ? '' : lang('visit_document_history_empty'));
            $more.prop('hidden', !response.has_more);
        } catch (error) {
            if (current === request) $status.text(error.responseJSON?.message || lang('visit_document_load_error'));
        } finally {
            if (current === request) {
                loading = false;
                $more.prop('disabled', false);
            }
        }
    }

    async function deleteDocument() {
        if (deleting || !pendingDeletion) return;
        const deletion = pendingDeletion;
        if (deletion.customer !== customerId()) return;
        deleting = true;
        const $buttons = $confirmation.find('button').prop('disabled', true);
        try {
            await App.Http.ClinicalRecords.deleteDocument(deletion.customer, deletion.id);
            close($confirmation[0]);
            if ($history[0].open && deletion.customer === customerId()) await load(true);
        } catch (error) {
            $('#clinical-document-delete-error').text(
                error.responseJSON?.message || lang('visit_document_delete_error'),
            );
        } finally {
            deleting = false;
            $buttons.prop('disabled', false);
        }
    }

    /** Another patient, or the record closed: nothing of the previous one stays on screen or arrives late. */
    function dismiss() {
        ++request;
        loading = false;
        close($confirmation[0]);
        close($history[0]);
    }

    document.addEventListener('DOMContentLoaded', () => {
        $button = $('#clinical-document-history');
        $history = $('#clinical-document-history-dialog');
        $confirmation = $('#clinical-document-delete-dialog');

        $button
            .on('click', () => {
                $('#clinical-document-history-patient').text($button.attr('data-patient-name'));
                show($history[0]);
                load(true);
            })
            .on('clinical:patient-changed', dismiss);
        $('#clinical-record-modal').on('hidden.bs.modal', dismiss);

        // The native dialogs own the focus while the Bootstrap modal of the record stays open underneath.
        $history
            .on('focusin', (event) => event.stopPropagation())
            .on('cancel', (event) => {
                event.preventDefault();
                close($history[0]);
            })
            .on('close', () => {
                ++request;
                loading = false;
                $button.trigger('focus');
            });
        $('#clinical-document-history-close').on('click', () => close($history[0]));
        $('#clinical-document-history-more').on('click', () => load());

        $confirmation
            .on('focusin', (event) => event.stopPropagation())
            .on('cancel', (event) => {
                event.preventDefault();
                if (!deleting) close($confirmation[0]);
            })
            .on('close', () => {
                pendingDeletion = null;
                $('#clinical-document-history-close').trigger('focus');
            });
        $('#clinical-document-delete-close, #clinical-document-delete-cancel').on('click', () =>
            close($confirmation[0]),
        );
        $('#clinical-document-delete-confirm').on('click', deleteDocument);
    });

    return {};
})();
