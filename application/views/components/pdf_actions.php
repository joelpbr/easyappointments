<dialog id="pdf-actions" class="border-0 rounded-4 shadow p-0" aria-labelledby="pdf-actions-title"
        style="width: min(96vw, 960px); height: 92vh;">
    <div class="d-flex flex-column h-100">
        <header class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 px-sm-4 py-3 border-bottom bg-body">
            <h5 id="pdf-actions-title" class="d-flex align-items-center gap-2 gap-sm-3 m-0 fw-semibold">
                <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary-emphasis"
                      style="width: 2.5rem; height: 2.5rem;" aria-hidden="true">
                    <i class="fas fa-file-lines"></i>
                </span>
                <span data-document-title class="text-break"></span>
            </h5>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <button type="button" class="btn btn-primary rounded-3" style="min-height: 44px;" data-print disabled>
                    <i class="fas fa-print me-1" aria-hidden="true"></i><span><?= lang('pdf_print') ?></span>
                </button>
                <a data-download download hidden class="btn btn-primary rounded-3 py-2"><?= lang('pdf_download') ?></a>
                <form method="dialog" class="d-flex m-0 ps-2 border-start">
                    <button class="btn btn-light rounded-3 d-flex align-items-center justify-content-center p-0"
                            style="width: 44px; height: 44px;"
                            aria-label="<?= e(lang('close')) ?>" title="<?= e(lang('close')) ?>">
                        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                            <path d="M4 4L14 14M14 4L4 14" fill="none" stroke="currentColor"
                                  stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </form>
            </div>
        </header>
        <div class="px-2" role="status"></div>
        <iframe class="w-100 flex-grow-1 border-0" title="<?= e(lang('visit_document_view')) ?>"></iframe>
    </div>
</dialog>
<script type="module">
    const dialog = document.getElementById('pdf-actions');
    dialog.loadRenderer = async () => {
        const renderer = await import(<?= json_encode(asset_url('assets/vendor/pdfjs/pdf.min.mjs')) ?>);
        renderer.GlobalWorkerOptions.workerSrc = <?= json_encode(asset_url('assets/vendor/pdfjs/pdf.worker.min.mjs')) ?>;
        return renderer;
    };
</script>
<script defer src="<?= asset_url('assets/js/components/pdf_actions.js') ?>"></script>
