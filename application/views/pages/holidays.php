<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="holidays-page">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="<?= site_url('blocked_periods') ?>" class="btn btn-outline-primary">
            <i class="fas fa-chevron-left me-2"></i>
            <?= lang('blocked_periods') ?>
        </a>

        <div class="btn-group ms-sm-3">
            <a class="btn btn-outline-secondary" href="<?= site_url('holidays?year=' . (vars('year') - 1)) ?>"
               aria-label="<?= vars('year') - 1 ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
            <span class="btn btn-outline-secondary disabled fw-semibold"><?= vars('year') ?></span>
            <a class="btn btn-outline-secondary" href="<?= site_url('holidays?year=' . (vars('year') + 1)) ?>"
               aria-label="<?= vars('year') + 1 ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <div class="ms-sm-auto d-flex align-items-center gap-2">
            <label for="holiday-region" class="form-label mb-0"><?= lang('holiday_region') ?></label>
            <select id="holiday-region" class="form-select w-auto">
                <option value=""><?= lang('holiday_region_none') ?></option>
                <?php foreach (vars('regions') as $region): ?>
                    <option value="<?= $region ?>" <?= vars('region') === $region ? 'selected' : '' ?>>
                        <?= lang('holiday_region_' . strtolower($region)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <h3 class="fw-light"><?= lang('holidays') ?></h3>
    <p class="form-text"><?= lang('holidays_hint') ?></p>

    <table class="table align-middle">
        <tbody>
        <?php foreach (vars('holidays') as $holiday): ?>
            <tr>
                <td class="text-nowrap"><?= format_date($holiday['date']) ?></td>
                <td><?= e($holiday['name']) ?></td>
                <td><span class="badge text-bg-light"><?= lang('holiday_level_' . $holiday['level']) ?></span></td>
                <td class="text-end text-nowrap">
                    <?php if ($holiday['closed']): ?>
                        <span class="text-danger fw-semibold">
                            <i class="fas fa-ban me-1"></i><?= lang('holiday_clinic_closed') ?>
                        </span>
                    <?php elseif (can('add', PRIV_BLOCKED_PERIODS)): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger close-clinic"
                                data-date="<?= $holiday['date'] ?>" data-name="<?= e($holiday['name']) ?>"
                                data-level="<?= $holiday['level'] ?>">
                            <?= lang('holiday_close_clinic') ?>
                        </button>
                    <?php endif; ?>

                    <?php if ($holiday['id'] && can('delete', PRIV_BLOCKED_PERIODS)): ?>
                        <button type="button" class="btn btn-sm btn-link text-muted delete-holiday"
                                data-id="<?= $holiday['id'] ?>" aria-label="<?= lang('delete') ?>">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (can('add', PRIV_BLOCKED_PERIODS)): ?>
        <h5 class="fw-light mt-4"><?= lang('holiday_add_local') ?></h5>
        <p class="form-text"><?= lang('holiday_add_local_hint') ?></p>
        <form id="add-holiday" class="d-flex flex-wrap gap-2">
            <input type="date" id="holiday-date" class="form-control w-auto" required>
            <input type="text" id="holiday-name" class="form-control w-auto flex-grow-1" maxlength="256"
                   placeholder="<?= lang('name') ?>" required>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-square me-2"></i><?= lang('add') ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/pages/holidays.js') ?>"></script>

<?php end_section('scripts'); ?>
