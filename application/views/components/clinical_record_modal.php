<?php
/**
 * Local variables.
 *
 * Clinical record of a patient, with a side panel for the record of each visit (see clinical_record_modal.js). Only
 * rendered for admins and providers.
 */
?>

<div id="clinical-record-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-lg-down modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-notes-medical me-2"></i>
                    <span id="clinical-record-title"><?= lang('clinical_record') ?></span>
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="modal-message alert d-none"></div>

                <div class="row">
                    <div class="col-lg-6">
                        <h4 class="text-black-50 mb-3 fw-light"><?= lang('clinical_patient_data') ?></h4>

                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label" for="clinical-first-name"><?= lang('first_name') ?></label>
                                <input id="clinical-first-name" class="form-control" data-customer="first_name"
                                       maxlength="256">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="clinical-last-name"><?= lang('last_name') ?></label>
                                <input id="clinical-last-name" class="form-control" data-customer="last_name"
                                       maxlength="512">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="clinical-document-number"><?= lang(
                                    'clinical_document',
                                ) ?></label>
                                <div class="input-group">
                                    <select id="clinical-document-type" class="form-select ea-clinical-document-type"
                                            aria-label="<?= e(lang('fiscal_document_type')) ?>">
                                        <option value="">—</option>
                                        <?php foreach (FISCAL_ID_TYPES as $type): ?>
                                            <option value="<?= $type ?>"><?= lang(
    'fiscal_document_' . $type,
) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input id="clinical-document-number" class="form-control" maxlength="64">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="clinical-birth-date"><?= lang(
                                    'clinical_birth_date',
                                ) ?></label>
                                <input id="clinical-birth-date" class="form-control" data-record="birth_date">
                            </div>
                            <div class="col-sm-8">
                                <label class="form-label" for="clinical-address"><?= lang('address') ?></label>
                                <input id="clinical-address" class="form-control" data-customer="address"
                                       maxlength="256">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="clinical-phone-number"><?= lang(
                                    'phone_number',
                                ) ?></label>
                                <input id="clinical-phone-number" class="form-control" data-customer="phone_number"
                                       maxlength="128">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="clinical-referral-source"><?= lang(
                                    'clinical_referral_source',
                                ) ?></label>
                                <input id="clinical-referral-source" class="form-control" data-record="referral_source"
                                       maxlength="256">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="clinical-profession"><?= lang(
                                    'clinical_profession',
                                ) ?></label>
                                <input id="clinical-profession" class="form-control" data-record="profession"
                                       maxlength="256">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="clinical-physical-activity"><?= lang(
                                    'clinical_physical_activity',
                                ) ?></label>
                                <input id="clinical-physical-activity" class="form-control"
                                       data-record="physical_activity" maxlength="256">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="clinical-medical-history"><?= lang(
                                    'clinical_medical_history',
                                ) ?></label>
                                <textarea id="clinical-medical-history" class="form-control" rows="3"
                                          data-record="medical_history"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="clinical-allergies"><?= lang(
                                    'clinical_allergies',
                                ) ?></label>
                                <textarea id="clinical-allergies" class="form-control" rows="2"
                                          data-record="allergies"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mt-4 mt-lg-0">
                        <h4 class="text-black-50 mb-3 fw-light"><?= lang('clinical_visits') ?></h4>

                        <div id="clinical-visits" class="list-group ea-clinical-visits"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= lang('close') ?>
                </button>
                <button type="button" id="clinical-record-save" class="btn btn-primary">
                    <i class="fas fa-check-square me-2"></i>
                    <?= lang('save') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- The record of a visit opens in a side panel inside the record, so that the record stays visible behind it. -->
    <div id="clinical-visit-panel" class="offcanvas offcanvas-end ea-clinical-visit-panel" tabindex="-1"
         data-bs-backdrop="false" data-bs-scroll="true">
        <div class="offcanvas-header">
            <div>
                <h4 class="offcanvas-title mb-0" id="clinical-visit-title"></h4>
                <small class="text-muted" id="clinical-visit-subtitle"></small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="offcanvas-body">
            <div class="modal-message alert d-none"></div>

            <div class="ea-clinical-feet mb-3" data-section="feet">[DIBUJO PIES]</div>

            <div class="mb-3" data-field="reason">
                <label class="form-label" for="clinical-visit-reason"><?= lang('clinical_reason') ?></label>
                <textarea id="clinical-visit-reason" class="form-control" rows="3" data-visit="reason"></textarea>
            </div>

            <div class="mb-3" data-field="diagnosis">
                <label class="form-label" for="clinical-visit-diagnosis"><?= lang('clinical_diagnosis') ?></label>
                <textarea id="clinical-visit-diagnosis" class="form-control" rows="3" data-visit="diagnosis"></textarea>
            </div>

            <div class="mb-3" data-field="treatment">
                <label class="form-label" for="clinical-visit-treatment"><?= lang('clinical_treatment') ?></label>
                <textarea id="clinical-visit-treatment" class="form-control" rows="3" data-visit="treatment"></textarea>
            </div>

            <div class="mb-3" data-field="orthosis">
                <label class="form-label" for="clinical-visit-orthosis"><?= lang('clinical_orthosis') ?></label>
                <select id="clinical-visit-orthosis" class="form-select" data-visit="orthosis">
                    <option value="">—</option>
                    <?php foreach (Clinical_record_templates::ORTHOSES as $orthosis => $price): ?>
                        <option value="<?= $orthosis ?>" data-price="<?= $price ?>">
                            <?= lang('clinical_orthosis_' . $orthosis) ?> (<?= rtrim(
     rtrim(number_format($price, 2, ',', ''), '0'),
     ',',
 ) ?>€)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= lang('clinical_price_hint') ?></div>
            </div>

            <div class="mb-3" data-field="surgery_price">
                <label class="form-label" for="clinical-visit-surgery-price"><?= lang(
                    'clinical_surgery_price',
                ) ?></label>
                <div class="input-group">
                    <input id="clinical-visit-surgery-price" class="form-control" inputmode="decimal">
                    <span class="input-group-text">€</span>
                </div>
                <div class="form-text"><?= lang('clinical_price_hint') ?></div>
            </div>

            <div class="mb-3" data-field="observations">
                <label class="form-label" for="clinical-visit-observations"><?= lang('clinical_observations') ?></label>
                <textarea id="clinical-visit-observations" class="form-control" rows="4"
                          data-visit="observations"></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">
                    <?= lang('close') ?>
                </button>
                <button type="button" id="clinical-visit-save" class="btn btn-primary">
                    <i class="fas fa-check-square me-2"></i>
                    <?= lang('save') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/clinical_records_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/components/clinical_record_modal.js') ?>"></script>

<?php end_section('scripts'); ?>
