<!doctype html>
<html lang="<?= config('language_code') ?>" class="ea-backend">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no, viewport-fit=<?= vars('calendar_toolbar') ? 'contain' : 'cover' ?>">
    <meta name="theme-color" content="<?= e(branding_color()) ?>">
    <?php component('web_app_meta'); ?>
    <meta name="google" content="notranslate">

    <?php slot('meta'); ?>

    <title><?= vars('page_title') ?? lang('backend_section') ?> | <?= e(branding_name()) ?></title>

    <!-- The amounts are hidden from the first paint, unless someone chose to show them on this browser
         (backend_layout.js). -->
    <script>
        try {
            if (localStorage.getItem('ea-money-shown') !== '1') {
                document.documentElement.classList.add('ea-money-hidden');
            }
        } catch (error) {
            // Without storage the amounts stay hidden.
            document.documentElement.classList.add('ea-money-hidden');
        }
    </script>

    <link rel="icon" href="<?= e(branding_logo()) ?>">
    <link rel="icon" sizes="192x192" href="<?= e(branding_logo()) ?>">
    <link rel="apple-touch-icon" href="<?= e(branding_logo()) ?>">

    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/trumbowyg/trumbowyg.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/select2/select2.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/air-datepicker/air-datepicker.min.css') ?>">
    <link rel="stylesheet" type="text/css"
          href="<?= asset_url('assets/css/themes/' . html_escape(setting('theme', 'default')) . '.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/general.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/backend.css') ?>">

    <?php component('company_color_style', ['company_color' => setting('company_color')]); ?>

    <?php slot('styles'); ?>
    <link rel="stylesheet" href="<?= asset_url('assets/css/loading.css') ?>">
</head>
<body class="d-flex flex-column h-100">

<main class="flex-shrink-0">

    <?php component('backend_header', ['active_menu' => vars('active_menu')]); ?>

    <div id="connection-status" class="alert alert-warning m-2" role="status" aria-live="polite"
         data-user-id="<?= (int) session('user_id') ?>" hidden></div>

    <?php slot('content'); ?>

</main>

<script src="<?= asset_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@popperjs-core/popper.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment/moment.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment-timezone/moment-timezone-with-data.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/fontawesome.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/solid.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/tippy.js/tippy-bundle.umd.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/trumbowyg/trumbowyg.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/select2/select2.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/air-datepicker/air-datepicker.min.js') ?>"></script>

<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/date.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/file.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/http.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/lang.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/message.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/string.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/url.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/validation.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/loading.js') ?>"></script>
<script src="<?= asset_url('assets/js/layouts/backend_layout.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/localization_http_client.js') ?>"></script>

<?php component('js_vars_script'); ?>
<?php component('js_lang_script'); ?>

<script src="<?= asset_url('assets/js/utils/work_recovery.js') ?>"></script>

<?php slot('scripts'); ?>

</body>
</html>
