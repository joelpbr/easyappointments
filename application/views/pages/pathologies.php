<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<!-- The catalog of pathologies placed on the foot diagrams of the visits (pathologies.js). -->
<div class="container backend-page py-3" id="pathologies-page">

    <div class="row" id="pathologies">
        <div id="filter-pathologies" class="filter-records column col-12 mb-4">
            <?php if (can('add', PRIV_SERVICES)): ?>
                <button id="add-pathology" type="button" class="btn btn-primary add-record-btn mb-4">
                    <i class="fas fa-plus-square me-2"></i>
                    <?= lang('add') ?>
                </button>
            <?php endif; ?>

            <div class="input-group mb-4">
                <input type="search" id="pathologies-filter" class="form-control" aria-label="<?= e(
                    lang('filter'),
                ) ?>" placeholder="<?= e(lang('filter')) ?>">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
            </div>

            <h4 class="mb-3 fw-light">
                <?= lang('pathologies') ?>
            </h4>

            <div id="pathologies-list" class="list-group ea-pathologies-list">
                <!-- JS -->
            </div>
        </div>

        <div class="record-details col-12 mb-4">
            <div class="btn-toolbar mb-4 gap-2">
                <button id="save-pathology" type="button" class="btn btn-primary" disabled>
                    <i class="fas fa-check-square me-2"></i>
                    <?= lang('save') ?>
                </button>
                <button id="cancel-pathology" type="button" class="btn btn-outline-secondary" disabled>
                    <?= lang('cancel') ?>
                </button>
                <?php if (can('delete', PRIV_SERVICES)): ?>
                    <button id="delete-pathology" type="button" class="btn btn-outline-danger ms-auto" disabled>
                        <i class="fas fa-trash-alt me-2"></i>
                        <?= lang('delete') ?>
                    </button>
                <?php endif; ?>
            </div>

            <h4 class="mb-3 fw-light" id="pathology-title">
                <?= lang('details') ?>
            </h4>

            <div id="pathology-message" class="alert alert-danger" hidden></div>

            <fieldset id="pathology-form" disabled>
                <input type="hidden" id="pathology-id">

                <div class="mb-3">
                    <label class="form-label" for="pathology-name">
                        <?= lang('name') ?>
                        <span class="text-danger">*</span>
                    </label>
                    <input id="pathology-name" class="form-control" maxlength="256">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="pathology-description">
                        <?= lang('description') ?>
                    </label>
                    <textarea id="pathology-description" rows="3" class="form-control"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="pathology-image">
                        <?= lang('pathology_image') ?>
                        <span class="text-danger" id="pathology-image-required">*</span>
                    </label>
                    <div class="d-flex align-items-center gap-3">
                        <div class="ea-pathology-preview">
                            <img id="pathology-image-preview" alt="" hidden>
                        </div>
                        <div class="flex-grow-1">
                            <input id="pathology-image" type="file" class="form-control"
                                   accept="image/png,image/jpeg,image/webp,image/gif">
                            <div class="form-text"><?= lang('pathology_image_hint') ?></div>
                        </div>
                    </div>
                </div>

                <p id="pathology-usage" class="form-text" hidden></p>
            </fieldset>
        </div>
    </div>

</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/pages/pathologies.js') ?>"></script>

<?php end_section('scripts'); ?>
