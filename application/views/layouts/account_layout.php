<!doctype html>
<html lang="<?= config('language_code') ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="theme-color" content="<?= e(branding_color()) ?>">
    <?php component('web_app_meta'); ?>
    <meta name="google" content="notranslate">

    <?php slot('meta'); ?>

    <title><?= vars('page_title') ?? lang('account') ?> | <?= e(branding_name()) ?></title>

    <link rel="icon" href="<?= e(branding_logo()) ?>">
    <link rel="icon" sizes="192x192" href="<?= e(branding_logo()) ?>">
    <link rel="apple-touch-icon" href="<?= e(branding_logo()) ?>">

    <link rel="stylesheet" type="text/css" href="<?= asset_url(
        'assets/css/themes/' . html_escape(setting('theme', 'default')) . '.css',
    ) ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/general.css') ?>">

    <?php component('company_color_style'); ?>

    <?php slot('styles'); ?>
</head>
<body>

<div class="d-flex align-items-center justify-content-center min-vh-100">

    <div class="card w-100 shadow-sm min-vh-mobile" style="max-width: 500px;">
        <div class="card-body p-5">
            <?php slot('content'); ?>
        </div>

        <div class="card-footer text-center py-3">
            <small>
                <?= e(branding_name()) ?>
            </small>
        </div>
    </div>

</div>

<script src="<?= asset_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@popperjs-core/popper.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment/moment.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment-timezone/moment-timezone-with-data.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/fontawesome.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/solid.min.js') ?>"></script>

<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/date.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/file.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/http.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/lang.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/message.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/string.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/url.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/validation.js') ?>"></script>
<?php if (setting('altcha_enabled') === '1'): ?>
<script src="<?= asset_url('assets/js/utils/altcha.js') ?>"></script>
<?php endif; ?>
<script src="<?= asset_url('assets/js/layouts/account_layout.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/account_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/localization_http_client.js') ?>"></script>

<?php component('js_vars_script'); ?>
<?php component('js_lang_script'); ?>

<?php slot('scripts'); ?>

</body>
</html>
