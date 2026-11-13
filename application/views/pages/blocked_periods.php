<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="blocked-periods-page">

    <div class="row" id="blocked-periods">
        <div id="filter-blocked-periods" class="filter-records column col-12 mb-4">
            <div class="mb-4">
                <a href="<?= site_url('business_settings') ?>" class="btn btn-outline-primary me-2">
                    <i class="fas fa-chevron-left me-2"></i>
                    <?= lang('back') ?>
                </a>
                <button id="add-blocked-period" class="btn btn-primary add-record-btn">
                    <i class="fas fa-plus-square me-2"></i>
                    <?= lang('add') ?>
                </button>
                <a href="<?= site_url('holidays') ?>" class="btn btn-outline-primary ms-2">
                    <i class="fas fa-calendar-day me-2"></i>
                    <?= lang('holidays') ?>
                </a>
            </div>

            <form class="input-append mb-4">
                <div class="input-group">
                    <input type="text" class="key form-control" aria-label="keyword">

                    <button class="filter btn btn-outline-secondary" type="submit"
                            data-tippy-content="<?= lang('filter') ?>">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <h4 class="mb-3 fw-light">
                <?= lang('blocked_periods') ?>
            </h4>

            <div class="results overflow-auto" style="max-height: 650px;">
                <!-- JS -->
            </div>
        </div>

        <div class="record-details col-12 mb-4">
            <div class="btn-toolbar mb-4">
                <div class="add-edit-delete-group btn-group">
                    <button id="edit-blocked-period" class="btn btn-outline-secondary" disabled="disabled">
                        <i class="fas fa-edit me-2"></i>
                        <?= lang('edit') ?>
                    </button>
                </div>

                <div class="save-cancel-group" style="display:none;">
                    <button id="save-blocked-period" class="btn btn-primary">
                        <i class="fas fa-check-square me-2"></i>
                        <?= lang('save') ?>
                    </button>
                    <button id="cancel-blocked-period" class="btn btn-outline-secondary">
                        <?= lang('cancel') ?>
                    </button>
                    <button id="delete-blocked-period" class="btn btn-outline-danger ms-2">
                        <i class="fas fa-trash-alt me-2"></i>
                        <?= lang('delete') ?>
                    </button>
                </div>

            </div>

            <h4 class="mb-3 fw-light">
                <?= lang('details') ?>
            </h4>

            <div class="form-message alert" style="display:none;"></div>

            <input type="hidden" id="id">

            <div class="mb-3">
                <label class="form-label" for="name">
                    <?= lang('name') ?>
                    <span class="text-danger" hidden>*</span>
                </label>
                <input id="name" class="form-control required" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label" for="type">
                    <?= lang('blocked_period_type') ?>
                </label>
                <select id="type" class="form-select" disabled>
                    <?php foreach (Blocked_periods_model::TYPES as $type): ?>
                        <option value="<?= $type ?>"><?= lang('blocked_period_type_' . $type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <span class="form-label d-block"><?= lang('blocked_period_providers') ?></span>
                <div id="provider-ids" class="border rounded p-2">
                    <?php foreach (vars('provider_options') as $option): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="<?= $option['value'] ?>"
                                   id="provider-<?= $option['value'] ?>" disabled>
                            <label class="form-check-label" for="provider-<?= $option['value'] ?>">
                                <?= e($option['label']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-text"><?= lang('blocked_period_providers_hint') ?></div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="start-date-time">
                    <?= lang('start') ?>
                    <span class="text-danger" hidden>*</span>
                </label>
                <input id="start-date-time" class="form-control required" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label" for="end-date-time">
                    <?= lang('end') ?>
                    <span class="text-danger" hidden>*</span>
                </label>
                <input id="end-date-time" class="form-control required" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label" for="recurrence">
                    <?= lang('blocked_period_recurrence') ?>
                </label>
                <select id="recurrence" class="form-select" disabled>
                    <?php foreach (Blocked_periods_model::RECURRENCES as $recurrence): ?>
                        <option value="<?= $recurrence ?>">
                            <?= lang('blocked_period_recurrence_' . $recurrence) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3" id="recurrence-until-group" hidden>
                <label class="form-label" for="recurrence-until">
                    <?= lang('blocked_period_recurrence_until') ?>
                </label>
                <input id="recurrence-until" type="date" class="form-control" disabled>
                <div class="form-text"><?= lang('blocked_period_recurrence_until_hint') ?></div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="notes">
                    <?= lang('notes') ?>
                </label>
                <textarea id="notes" rows="4" class="form-control" disabled></textarea>
            </div>

        </div>
    </div>

</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/utils/ui.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/blocked_periods_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/blocked_periods.js') ?>"></script>

<?php end_section('scripts'); ?>
