<?php
/** Consent uses the same dialog, header, form controls and actions as the clinical document menus. */
?>
<style>
    #patient-consent-modal {
        width: 100vw; max-width: none; height: 100dvh; max-height: none;
        margin: 0; border-radius: 0;
    }
    #patient-consent-modal .ea-document-dialog-header h5 { min-width: 0; }
    #patient-consent-modal .consent-columns {
        display: grid; grid-template-columns: minmax(0, 1fr) minmax(370px, .85fr);
        flex: 1; min-height: 0;
    }
    #patient-consent-reading { min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
    #patient-consent-text { max-width: 760px; margin: 0 auto; line-height: 1.65; }
    #patient-consent-text h3 { font-size: 1.1rem; margin: 1.5rem 0 .75rem; color: inherit; }
    #patient-consent-text p { margin-bottom: 1rem; }
    #patient-consent-actions {
        display: flex; flex-direction: column; gap: 12px; min-height: 0; min-width: 0;
        overflow: visible; border-left: 1px solid var(--bs-border-color);
    }
    #patient-consent-actions > :not(#patient-consent-signature) { flex-shrink: 0; }
    #patient-consent-actions .form-label { margin-bottom: .25rem; }
    #patient-consent-actions .form-check { margin-bottom: 0; }
    #patient-consent-options .form-check + .form-check { margin-top: .5rem; }
    #patient-consent-modal .btn-check:checked + .btn {
        --bs-btn-active-bg: var(--bs-primary);
        --bs-btn-active-border-color: var(--bs-primary);
        --bs-btn-active-color: var(--ea-primary-contrast);
    }
    #patient-consent-signature { display: flex; flex-direction: column; gap: 8px; flex: 1; min-height: 130px; }
    #patient-consent-pad {
        width: 100%; height: 0; min-height: 80px; flex: 1;
        border: 2px dashed var(--bs-secondary-color); border-radius: var(--bs-border-radius);
        background: var(--bs-white); touch-action: none; cursor: crosshair; display: block;
    }
    #patient-consent-error:empty { display: none; }
    @media (max-height: 750px) {
        #patient-consent-actions { gap: 6px; padding: 12px 16px; font-size: .875rem; }
        #patient-consent-modal .ea-document-dialog-header { padding: 12px 20px; }
        #patient-consent-actions .form-control { padding-top: .3rem; padding-bottom: .3rem; }
    }
    @media (max-width: 700px) {
        #patient-consent-modal .consent-columns { grid-template-columns: 1fr; grid-template-rows: minmax(120px, 1fr) minmax(0, 2fr); }
        #patient-consent-actions { overflow-y: auto; border-left: 0; border-top: 1px solid var(--bs-border-color); }
    }
</style>
<dialog id="patient-consent-modal" class="ea-document-dialog" aria-labelledby="patient-consent-title">
    <div class="ea-document-dialog-header">
        <h5 id="patient-consent-title" class="m-0"></h5>
        <button type="button" class="btn-close" data-consent-close aria-label="<?= e(lang('close')) ?>"></button>
    </div>
    <div class="consent-columns">
        <section id="patient-consent-reading" class="ea-document-dialog-body" tabindex="0" aria-labelledby="patient-consent-title">
            <div id="patient-consent-text">
                <p id="patient-consent-patient" class="fw-semibold border-bottom pb-3"></p>
                <div id="patient-consent-sections"></div>
                <div id="patient-consent-options" class="mb-3"></div>
                <p id="patient-consent-declaration" class="mt-3"></p>
            </div>
        </section>
        <div id="patient-consent-actions" class="ea-document-dialog-body">
            <div id="patient-consent-warning" class="alert alert-warning py-2 mb-0" hidden></div>
            <div>
                <label class="form-label" for="patient-consent-role"><?= lang('consent_signer_role') ?></label>
                <select id="patient-consent-role" class="form-select">
                    <option value="patient"><?= lang('consent_signer_patient') ?></option>
                    <option value="representative"><?= lang('consent_signer_representative_short') ?></option>
                </select>
            </div>
            <div id="patient-consent-signer-fields" class="row g-2 align-items-end" hidden>
                <div class="col-6">
                    <label class="form-label" for="patient-consent-name"><?= lang('consent_signer_name') ?></label>
                    <input id="patient-consent-name" class="form-control" maxlength="200" autocomplete="off">
                </div>
                <div class="col-6">
                    <label class="form-label" for="patient-consent-document"><?= lang(
                        'consent_signer_document',
                    ) ?></label>
                    <input id="patient-consent-document" class="form-control" maxlength="20" autocomplete="off">
                </div>
                <div class="col-12" id="patient-consent-relationship-group">
                    <label class="form-label" for="patient-consent-relationship"><?= lang(
                        'consent_signer_relationship',
                    ) ?></label>
                    <input id="patient-consent-relationship" class="form-control" maxlength="60" autocomplete="off" placeholder="<?= e(
                        lang('consent_signer_relationship_hint'),
                    ) ?>">
                </div>
            </div>
            <div id="patient-consent-signature">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="form-label mb-0"><?= lang('consent_sign_here') ?></span>
                    <button type="button" id="patient-consent-clear" class="btn btn-sm btn-outline-secondary"><?= lang(
                        'consent_clear',
                    ) ?></button>
                </div>
                <canvas id="patient-consent-pad" aria-label="<?= e(lang('consent_sign_here')) ?>"></canvas>
            </div>
            <div id="patient-consent-error" class="text-danger small" role="alert"></div>
            <div class="d-flex justify-content-end gap-2 border-top pt-2">
                <button type="button" class="btn btn-outline-secondary" data-consent-close><?= lang(
                    'cancel',
                ) ?></button>
                <button type="button" id="patient-consent-save" class="btn btn-primary"><?= lang(
                    'consent_sign_save',
                ) ?></button>
            </div>
        </div>
    </div>
</dialog>
