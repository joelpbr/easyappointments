<?php extend('layouts/backend_layout'); ?>

<?php
// Set by Backups::index().
$backups = vars('backups');
$max_upload_bytes = vars('max_upload_bytes');

$human_size = function (int $bytes): string {
    if ($bytes >= 1024 ** 2) {
        return number_format($bytes / 1024 ** 2, 1, ',', '.') . ' MB';
    }

    return max(1, (int) round($bytes / 1024)) . ' KB';
};
?>

<?php section('content'); ?>

<!-- Copies of the whole database: download and restore (backups.js). -->
<div id="backups-page" class="container backend-page py-3" data-max-upload-bytes="<?= (int) $max_upload_bytes ?>">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div class="col-sm-9">
            <h4 class="border-bottom py-3 mb-3 fw-light">
                <?= lang('backups') ?>
            </h4>

            <p class="form-text text-muted mb-4">
                <?= lang('backups_info') ?>
            </p>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <button type="button" id="backup-create" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>
                    <?= lang('backup_download_now') ?>
                </button>
            </div>

            <h5 class="fw-light mb-2"><?= lang('backup_list') ?></h5>

            <?php if (!$backups): ?>
                <p class="text-muted"><?= lang('backup_no_copies') ?></p>
            <?php else: ?>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th><?= lang('date') ?></th>
                            <th><?= lang('backup_size') ?></th>
                            <th class="text-end"><?= lang('actions') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td class="text-nowrap" title="<?= e($backup['name']) ?>">
                                    <?= date('d/m/Y H:i', strtotime($backup['date'])) ?>
                                    <?php if (str_contains($backup['name'], '-before-restore')): ?>
                                        <span class="badge text-bg-secondary ms-1"><?= lang('backup_before_restore') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= $human_size((int) $backup['size']) ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="<?= site_url('backups/download/' . rawurlencode($backup['name'])) ?>">
                                        <i class="fas fa-download me-1"></i>
                                        <?= lang('backup_download') ?>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger backup-restore-listed"
                                            data-name="<?= e($backup['name']) ?>"
                                            data-label="<?= e(date('d/m/Y H:i', strtotime($backup['date']))) ?>">
                                        <i class="fas fa-undo me-1"></i>
                                        <?= lang('backup_restore') ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="card border-danger">
                <div class="card-header">
                    <h5 class="fw-light mb-0"><?= lang('backup_restore_title') ?></h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted"><?= lang('backup_restore_info') ?></p>

                    <label class="form-label" for="backup-file"><?= lang('backup_upload_label') ?></label>
                    <div class="d-flex flex-wrap gap-2">
                        <input id="backup-file" type="file" class="form-control w-auto flex-grow-1"
                               accept=".gz,.sql,application/gzip,application/sql">
                        <button type="button" id="backup-upload" class="btn btn-danger" disabled>
                            <i class="fas fa-upload me-2"></i>
                            <?= lang('backup_restore') ?>
                        </button>
                    </div>
                    <?php if ($max_upload_bytes): ?>
                        <div class="form-text">
                            <?= e(sprintf(lang('backup_max_size'), $human_size((int) $max_upload_bytes))) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/pages/backups.js') ?>"></script>

<?php end_section('scripts'); ?>
