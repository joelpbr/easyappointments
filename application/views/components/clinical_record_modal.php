<?php
/**
 * Local variables.
 *
 * Clinical record of a patient (see clinical_record_modal.js): a header with the patient, and two tabs: details
 * (with the medical history and allergies) and visits. The visits tab lists the visits on the left and shows the
 * record of the chosen one on the right. Only rendered for admins and providers.
 */
?>

<div id="clinical-record-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-lg-down modal-xl">
        <div class="modal-content">
            <!-- One low line: the name of the patient and, when there are some, the allergies. -->
            <div class="modal-header ea-clinical-header">
                <h3 class="modal-title text-truncate" id="clinical-record-title"></h3>
                <div id="clinical-record-allergies-alert" class="ea-clinical-allergies" hidden>
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    <strong><?= lang('clinical_allergies') ?>:</strong>
                    <span class="ea-clinical-allergies-text"></span>
                </div>
                <div class="ea-clinical-header-actions">
                    <?php if (can('edit', PRIV_CUSTOMERS)): ?>
                    <button type="button" id="patient-consent-open" class="btn btn-warning btn-sm" hidden disabled>
                        <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= lang('consent_signature_pending') ?>
                    </button>
                    <?php endif; ?>
                    <button type="button" id="clinical-document-history" class="btn btn-outline-light btn-sm" hidden>
                        <i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i><?= lang(
                            'visit_document_history',
                        ) ?>
                    </button>
                    <button type="button" id="clinical-customer-details" class="btn btn-link text-white p-1 lh-1"
                            title="<?= e(lang('customer_details_title')) ?>"
                            aria-label="<?= e(lang('customer_details_title')) ?>" hidden>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="16" rx="2" />
                            <circle cx="9" cy="10" r="2" />
                            <path d="M6 16c0-3 6-3 6 0M15 9h3M15 13h3M15 16h3" />
                        </svg>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="<?= e(lang('close')) ?>"></button>
                </div>
            </div>

            <ul class="nav nav-tabs ea-clinical-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#clinical-tab-details"
                            type="button" role="tab" data-tab="details">
                        <i class="fas fa-id-card me-2"></i><?= lang('clinical_patient_data') ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#clinical-tab-visits" type="button"
                            role="tab" data-tab="visits">
                        <i class="fas fa-calendar-check me-2"></i><?= lang('clinical_visits') ?>
                        <span class="badge rounded-pill ms-1" id="clinical-visits-count"></span>
                    </button>
                </li>
                <!-- Saves the details of the patient; a visit has its own button. -->
                <li class="nav-item ms-auto align-self-center">
                    <button type="button" id="clinical-record-save" class="btn btn-primary btn-sm">
                        <i class="fas fa-check-square me-2"></i>
                        <?= lang('save') ?>
                    </button>
                </li>
            </ul>

            <div id="clinical-save-error" class="alert alert-danger m-2" role="alert" hidden></div>
            <div class="modal-body tab-content ea-clinical-body">
                <div class="tab-pane fade show active" id="clinical-tab-details" role="tabpanel">
                    <div class="row g-2 ea-clinical-details">
                        <div class="col-md-3">
                            <label class="form-label" for="clinical-first-name"><?= lang('first_name') ?></label>
                            <input id="clinical-first-name" class="form-control" data-customer="first_name"
                                   maxlength="256">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="clinical-last-name"><?= lang('last_name') ?></label>
                            <input id="clinical-last-name" class="form-control" data-customer="last_name"
                                   maxlength="512">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="clinical-document-number"><?= lang(
                                'clinical_document',
                            ) ?></label>
                            <div class="input-group">
                                <select id="clinical-document-type" class="form-select ea-clinical-document-type"
                                        aria-label="<?= e(lang('fiscal_document_type')) ?>">
                                    <option value="">—</option>
                                    <?php foreach (FISCAL_ID_TYPES as $type): ?>
                                        <option value="<?= $type ?>"><?= lang('fiscal_document_' . $type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input id="clinical-document-number" class="form-control" maxlength="64">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="clinical-birth-date"><?= lang(
                                'clinical_birth_date',
                            ) ?></label>
                            <input id="clinical-birth-date" class="form-control" data-record="birth_date">
                        </div>
                        <!-- Calculated from the date of birth, not saved. -->
                        <div class="col-md-1">
                            <label class="form-label" for="clinical-age"><?= lang('clinical_age') ?></label>
                            <input id="clinical-age" class="form-control ea-clinical-age" readonly tabindex="-1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="clinical-phone-number"><?= lang('phone_number') ?></label>
                            <input id="clinical-phone-number" class="form-control" data-customer="phone_number"
                                   maxlength="128">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="clinical-address"><?= lang('address') ?></label>
                            <input id="clinical-address" class="form-control" data-customer="address" maxlength="256">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="clinical-city"><?= lang('city') ?></label>
                            <input id="clinical-city" class="form-control" data-customer="city" maxlength="256">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="clinical-zip-code"><?= lang('zip_code') ?></label>
                            <input id="clinical-zip-code" class="form-control" data-customer="zip_code"
                                   maxlength="64" inputmode="numeric">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="clinical-referral-source"><?= lang(
                                'clinical_referral_source',
                            ) ?></label>
                            <input id="clinical-referral-source" class="form-control" data-record="referral_source"
                                   maxlength="256">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="clinical-profession"><?= lang(
                                'clinical_profession',
                            ) ?></label>
                            <input id="clinical-profession" class="form-control" data-record="profession"
                                   maxlength="256">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="clinical-physical-activity"><?= lang(
                                'clinical_physical_activity',
                            ) ?></label>
                            <input id="clinical-physical-activity" class="form-control"
                                   data-record="physical_activity" maxlength="256">
                        </div>
                    </div>

                    <!-- Compact text fields; patient observations are shared with the booking form. -->
                    <div class="row g-2 ea-clinical-notes">
                        <div class="col-md-6">
                            <label class="form-label" for="clinical-medical-history"><?= lang(
                                'clinical_medical_history',
                            ) ?></label>
                            <textarea id="clinical-medical-history" class="form-control" rows="3"
                                      data-record="medical_history"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="clinical-allergies"><?= lang('clinical_allergies') ?></label>
                            <textarea id="clinical-allergies" class="form-control" rows="3"
                                      data-record="allergies"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="clinical-patient-notes"><?= lang(
                                'administrative_notes',
                            ) ?></label>
                            <textarea id="clinical-patient-notes" class="form-control" rows="3"
                                      data-customer="notes"></textarea>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="clinical-tab-visits" role="tabpanel">
                    <div id="clinical-visits" class="list-group list-group-flush ea-clinical-visits"></div>
                </div>
            </div>

            <!-- The chosen visit: in the visits tab, a column on the right from under the header, next to the tabs and the
                 list of visits, so that it gets the whole height (see the ea-visits-mode styles). -->
            <section id="clinical-visit-column" class="ea-clinical-visit-column">
                <div id="clinical-visit-empty" class="ea-clinical-visit-empty">
                    <i class="fas fa-file-medical mb-2"></i>
                    <div><?= lang('clinical_choose_visit') ?></div>
                </div>

                <div id="clinical-visit-detail" hidden>
                    <div class="ea-clinical-visit-heading">
                        <h4 id="clinical-visit-title"></h4>
                        <span class="text-muted small" id="clinical-visit-subtitle"></span>
                        <div id="clinical-documents-dropdown" class="dropdown ms-auto">
                            <button type="button" id="clinical-visit-documents" class="btn btn-outline-primary btn-sm dropdown-toggle"
                                    data-bs-toggle="dropdown" aria-expanded="false" aria-controls="clinical-documents-menu">
                                <i class="fas fa-file-pdf me-2" aria-hidden="true"></i><?= lang('visit_documents') ?>
                            </button>
                            <ul id="clinical-documents-menu" class="dropdown-menu dropdown-menu-end ea-visit-documents-menu"
                                aria-labelledby="clinical-visit-documents"></ul>
                        </div>
                        <button type="button" id="clinical-visit-save" class="btn btn-primary btn-sm">
                            <i class="fas fa-check-square me-2"></i>
                            <?= lang('clinical_save_visit') ?>
                        </button>
                    </div>

                    <!-- Foot diagrams: the picture of the chosen view on the left, with the four views under it, and
                         the pathologies on the right with the observations under them (clinical_record_modal.js). -->
                    <div class="ea-clinical-feet mb-3" data-section="feet">
                        <div class="ea-feet-diagram">
                            <div class="ea-feet-picture" id="clinical-feet-board">
                                <img id="clinical-feet-picture" alt="" draggable="false">
                                <div id="clinical-feet-markers" class="ea-feet-markers"></div>
                                <!-- Floats on the top right corner of the picture, as it only changes how it is shown. -->
                                <button type="button" id="clinical-feet-xray" class="btn btn-sm ea-feet-xray-toggle"
                                        aria-pressed="false" aria-controls="clinical-feet-board"
                                        aria-label="<?= e(lang('clinical_feet_xray')) ?>"
                                        title="<?= e(lang('clinical_feet_xray')) ?>">
                                    <i class="fas fa-arrow-right-arrow-left" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div id="clinical-feet-views" class="ea-feet-views" role="group"></div>
                        </div>

                        <div class="ea-feet-side">
                            <div class="form-label mb-0"><?= lang('clinical_pathologies') ?></div>
                            <div id="clinical-feet-pathologies" class="list-group ea-feet-pathologies"></div>
                            <div id="clinical-feet-observations"></div>
                        </div>
                    </div>

                    <div class="mb-3" data-field="reason">
                        <label class="form-label" for="clinical-visit-reason"><?= lang('clinical_reason') ?></label>
                        <textarea id="clinical-visit-reason" class="form-control" rows="2"
                                  data-visit="reason"></textarea>
                    </div>

                    <div class="mb-3" data-field="diagnosis">
                        <label class="form-label" for="clinical-visit-diagnosis"><?= lang(
                            'clinical_diagnosis',
                        ) ?></label>
                        <textarea id="clinical-visit-diagnosis" class="form-control" rows="2"
                                  data-visit="diagnosis"></textarea>
                    </div>

                    <div class="mb-3" data-field="treatment">
                        <label class="form-label" for="clinical-visit-treatment"><?= lang(
                            'clinical_treatment',
                        ) ?></label>
                        <textarea id="clinical-visit-treatment" class="form-control" rows="2"
                                  data-visit="treatment"></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-8" data-field="orthosis">
                            <label class="form-label" for="clinical-visit-orthosis"><?= lang(
                                'clinical_orthosis',
                            ) ?></label>
                            <select id="clinical-visit-orthosis" class="form-select" data-visit="orthosis">
                                <option value="">—</option>
                                <?php foreach (Clinical_record_templates::ORTHOSES as $orthosis => $price): ?>
                                    <?php $price_label = rtrim(rtrim(number_format($price, 2, ',', ''), '0'), ','); ?>
                                    <!-- Shown without its price while the amounts are hidden (backend_layout.js). -->
                                    <option value="<?= $orthosis ?>" data-price="<?= $price ?>"
                                            data-money-label="<?= e(lang('clinical_orthosis_' . $orthosis)) ?>">
                                        <?= lang('clinical_orthosis_' . $orthosis) ?> (<?= $price_label ?>€)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><?= lang('clinical_price_hint') ?></div>
                        </div>

                        <div class="col-sm-5 ea-money" data-field="surgery_price">
                            <label class="form-label" for="clinical-visit-surgery-price"><?= lang(
                                'clinical_surgery_price',
                            ) ?></label>
                            <div class="input-group">
                                <input id="clinical-visit-surgery-price" class="form-control"
                                       inputmode="decimal">
                                <span class="input-group-text">€</span>
                            </div>
                            <div class="form-text"><?= lang('clinical_price_hint') ?></div>
                        </div>
                    </div>

                    <!-- The observations come back here when the visit has no foot diagrams. -->
                    <div id="clinical-observations-home" hidden></div>

                    <div class="mb-3" data-field="observations">
                        <label class="form-label" for="clinical-visit-observations"><?= lang(
                            'clinical_observations',
                        ) ?></label>
                        <textarea id="clinical-visit-observations" class="form-control" rows="4"
                                  data-visit="observations"></textarea>
                    </div>
                </div>
            </section>

        </div>
    </div>
</div>

<!-- Document cards: native dialogs above the visit, filled in by visit_documents.js and visit_document_history.js. -->
<dialog id="clinical-document-panel" class="ea-document-dialog" aria-labelledby="clinical-document-heading">
    <div class="ea-document-dialog-header">
        <h5 id="clinical-document-heading" class="m-0"><?= lang('visit_document_generate') ?></h5>
        <button type="button" id="clinical-document-close" class="btn-close"
                aria-label="<?= e(lang('close')) ?>"></button>
    </div>
    <div class="ea-document-dialog-body">
        <div id="clinical-document-status" role="status" aria-live="polite"></div>
        <form id="clinical-document-form" hidden>
            <!-- A group the menu shows as a single entry (the prescriptions): its document types are chosen here. -->
            <div id="clinical-document-variant-wrap" class="mb-3" hidden>
                <label class="form-label" for="clinical-document-variant"></label>
                <select id="clinical-document-variant" class="form-select"></select>
            </div>
            <div id="clinical-document-professional-wrap" class="mb-3" hidden>
                <label class="form-label" for="clinical-document-professional"><?= lang('provider') ?></label>
                <select id="clinical-document-professional" class="form-select"></select>
            </div>
            <p id="clinical-document-signer" class="small" hidden></p>
            <div id="clinical-document-fields" class="row g-3"></div>
            <details id="clinical-document-known" class="mt-3" hidden>
                <summary class="text-primary"><?= lang('visit_document_known_data') ?></summary>
                <div id="clinical-document-known-fields" class="row g-3 mt-0"></div>
            </details>
            <div id="clinical-document-review-wrap" class="mt-3" hidden>
                <div id="clinical-document-allergies" class="alert alert-warning py-2" hidden></div>
                <div class="form-check">
                    <input id="clinical-document-reviewed" type="checkbox" class="form-check-input">
                    <label for="clinical-document-reviewed" class="form-check-label">
                        <?= lang('visit_document_review') ?>
                    </label>
                </div>
            </div>
            <button id="clinical-document-download" type="submit" class="btn btn-primary mt-3" disabled>
                <i class="fas fa-file-pdf me-2" aria-hidden="true"></i><?= lang('visit_document_download') ?>
            </button>
        </form>
    </div>
</dialog>

<dialog id="clinical-document-history-dialog" class="ea-document-dialog"
        aria-labelledby="clinical-document-history-title">
    <div class="ea-document-dialog-header">
        <h5 id="clinical-document-history-title" class="m-0"><?= lang('visit_document_history') ?></h5>
        <button type="button" id="clinical-document-history-close" class="btn-close"
                aria-label="<?= e(lang('close')) ?>"></button>
    </div>
    <div class="ea-document-dialog-body">
        <p id="clinical-document-history-patient" class="fw-semibold"></p>
        <div id="clinical-document-history-status" role="status" aria-live="polite"></div>
        <div id="clinical-document-history-list" class="list-group list-group-flush"></div>
        <button type="button" id="clinical-document-history-more" class="btn btn-outline-primary btn-sm mt-3" hidden>
            <?= lang('visit_document_history_more') ?>
        </button>
    </div>
</dialog>

<dialog id="clinical-document-delete-dialog" class="ea-document-dialog"
        aria-labelledby="clinical-document-delete-title">
    <div class="ea-document-dialog-header">
        <h5 id="clinical-document-delete-title" class="m-0"><?= lang('delete') ?></h5>
        <button type="button" id="clinical-document-delete-close" class="btn-close"
                aria-label="<?= e(lang('close')) ?>"></button>
    </div>
    <div class="ea-document-dialog-body">
        <p><?= lang('visit_document_delete_confirm') ?></p>
        <p id="clinical-document-delete-name" class="fw-semibold"></p>
        <div id="clinical-document-delete-error" role="alert" class="text-danger mb-3"></div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" id="clinical-document-delete-cancel" class="btn btn-outline-secondary">
                <?= lang('cancel') ?>
            </button>
            <button type="button" id="clinical-document-delete-confirm" class="btn btn-danger">
                <?= lang('delete') ?>
            </button>
        </div>
    </div>
</dialog>

<?php if (can('edit', PRIV_CUSTOMERS)): ?>
    <?php component('patient_consent_modal'); ?>
<?php endif; ?>

<?php if (empty($standalone)):
    section('scripts');
endif; ?>

<script src="<?= asset_url('assets/js/http/clinical_records_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/components/clinical_record_modal.js') ?>"></script>
<script src="<?= asset_url('assets/js/components/document_dialog.js') ?>"></script>
<?php if (can('edit', PRIV_CUSTOMERS)): ?>
    <script src="<?= asset_url('assets/js/components/patient_consent.js') ?>"></script>
<?php endif; ?>
<script src="<?= asset_url('assets/js/components/visit_documents.js') ?>"></script>
<script src="<?= asset_url('assets/js/components/visit_document_history.js') ?>"></script>

<?php if (empty($standalone)):
    end_section('scripts');
endif; ?>
