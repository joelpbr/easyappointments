<?php extend('layouts/account_layout'); ?>

<?php section('content'); ?>

<div class="text-center mb-4">
    <img src="<?= e(branding_logo()) ?>"
        alt="<?= e(branding_name()) ?>" class="shadow mb-3" width="72" height="72">
    <h4 class="text-primary fw-semibold mb-1"><?= lang('log_out') ?></h4>
    <p class=" small mb-0"><?= lang('logout_success') ?></p>
</div>

<div class="d-grid gap-2 mb-3">
    <a href="<?= site_url('login') ?>" class="btn btn-primary">
        <i class="fas fa-sign-in-alt me-2"></i>
        <?= lang('login') ?>
    </a>
</div>

<?php end_section('content'); ?>


