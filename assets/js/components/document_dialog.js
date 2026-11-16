/**
 * Document cards: native dialogs shown above the clinical record, with a short entrance and exit.
 *
 * Shared by the document form (visit_documents.js) and the document history (visit_document_history.js).
 */
App.Components.DocumentDialog = (function () {
    const animations = new WeakMap();

    const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * @param {HTMLDialogElement} dialog
     */
    function show(dialog) {
        animations.get(dialog)?.cancel();
        if (dialog.open) return;
        dialog.showModal();
        if (reducedMotion()) return;
        animations.set(
            dialog,
            dialog.animate(
                [
                    {opacity: 0, transform: 'translateY(-18px) scale(0.98)'},
                    {opacity: 1, transform: 'translateY(0) scale(1)'},
                ],
                {duration: 220, easing: 'cubic-bezier(0.16, 1, 0.3, 1)'},
            ),
        );
    }

    /**
     * @param {HTMLDialogElement} dialog
     */
    function close(dialog) {
        if (!dialog.open) return;
        animations.get(dialog)?.cancel();
        if (reducedMotion()) {
            dialog.close();
            return;
        }
        const animation = dialog.animate(
            [
                {opacity: 1, transform: 'translateY(0) scale(1)'},
                {opacity: 0, transform: 'translateY(-10px) scale(0.985)'},
            ],
            {duration: 160, easing: 'ease-in', fill: 'forwards'},
        );
        animations.set(dialog, animation);
        animation.finished
            .then(() => {
                dialog.close();
                animation.cancel();
            })
            .catch(() => {});
    }

    /**
     * The text of a catalog label in the language of the user, or in the first language the catalog has.
     *
     * @param {Object} labels {language: text}
     *
     * @return {String}
     */
    function label(labels) {
        return (labels && (labels[vars('language')] ?? Object.values(labels)[0])) || '';
    }

    return {show, close, label};
})();
