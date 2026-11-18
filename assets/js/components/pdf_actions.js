(() => {
    const dialog = document.getElementById('pdf-actions');
    const frame = dialog.querySelector('iframe');
    const print = dialog.querySelector('[data-print]');
    const status = dialog.querySelector('[role=status]');
    // iPadOS can request desktop sites and identify itself as a Mac.
    const appleMobile = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (/Mac/.test(navigator.platform) && navigator.maxTouchPoints > 1);
    const download = dialog.querySelector('[data-download]');
    let shareFile;
    let generation = 0;
    let loading;

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-pdf-actions][href]');
        if (!link || event.defaultPrevented) return;
        event.preventDefault();
        dialog.showPdf(link.href, link.dataset.pdfActions);
    });

    dialog.showPdf = async (url, title) => {
        const current = ++generation;
        dialog.querySelector('[data-document-title]').textContent = title;
        frame.title = title;
        dialog.showModal();
        shareFile = null;
        const attachment = new URL(url, document.baseURI);
        attachment.searchParams.set('download', '1');
        download.href = attachment.href;
        download.hidden = true;
        print.hidden = false;
        print.querySelector('span').textContent = lang(appleMobile ? 'pdf_share_print' : 'pdf_print');
        print.disabled = true;
        status.textContent = lang('loading');
        const doc = frame.contentDocument;
        doc.head.innerHTML = '<meta name="viewport" content="width=device-width,initial-scale=1">';
        doc.title = title;
        const style = doc.createElement('style');
        style.textContent = `
            html,body { margin:0; background:#ddd; }
            canvas { display:block; width:100%; height:auto; margin:0 auto 8px; background:white; }
            @page { margin:0; }
            @media print {
                html,body { background:white; }
                canvas { width:100%; margin:0; break-inside:avoid; break-after:page; }
                canvas:last-child { break-after:auto; }
            }`;
        doc.head.append(style);
        try {
            const renderer = await dialog.loadRenderer();
            if (current !== generation) return;
            // Safari's canvas resolves embedded fonts in the document that owns it.
            loading = renderer.getDocument({
                url,
                ownerDocument: doc,
                isEvalSupported: false,
                httpHeaders: {'X-Requested-With': 'XMLHttpRequest'},
            });
            const pdf = await loading.promise;
            for (let index = 1; index <= pdf.numPages; index++) {
                const page = await pdf.getPage(index);
                if (current !== generation) return;
                const canvas = doc.createElement('canvas');
                const viewport = page.getViewport({scale: 2});
                canvas.width = Math.ceil(viewport.width);
                canvas.height = Math.ceil(viewport.height);
                canvas.setAttribute('aria-label', index + ' / ' + pdf.numPages);
                if (index === 1) {
                    style.textContent += '@page { size:' + viewport.width / 2 + 'pt ' + viewport.height / 2 + 'pt; }';
                }
                await page.render({canvasContext: canvas.getContext('2d'), viewport}).promise;
                if (current !== generation) return;
                doc.body.append(canvas);
                page.cleanup();
            }
            if (appleMobile) {
                // Prepare the original file before the tap. Safari requires user activation for share().
                if (navigator.share && navigator.canShare) {
                    const data = await pdf.getData();
                    if (current !== generation) return;
                    const id = attachment.searchParams.get('document_id');
                    const name = id && /^\d+$/.test(id) ? 'document-' + id + '.pdf' : 'document.pdf';
                    const file = new File([data], name, {type: 'application/pdf'});
                    if (navigator.canShare({files: [file]})) shareFile = file;
                }
                print.hidden = !shareFile;
                download.hidden = !!shareFile;
            }
            status.textContent = '';
            print.disabled = false;
        } catch {
            if (current === generation) status.textContent = lang('pdf_open_failed');
        }
    };

    print.addEventListener('click', async () => {
        if (!appleMobile) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
            return;
        }
        const current = generation;
        try {
            await navigator.share({files: [shareFile]});
        } catch (error) {
            if (current === generation && error.name !== 'AbortError') {
                download.hidden = false;
                status.textContent = lang('pdf_share_failed');
            }
        }
    });
    dialog.addEventListener('close', () => {
        generation++;
        shareFile = null;
        download.removeAttribute('href');
        loading?.destroy();
        loading = null;
        frame.contentDocument.body.replaceChildren();
    });
})();
