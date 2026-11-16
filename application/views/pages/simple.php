<?php
/**
 * Local variables.
 *
 * Simple version of the calendar (see the Simple controller and simple.js): the appointments of a day, one below the
 * other, with day navigation, a provider filter and the details of an appointment with the patient's phone at hand.
 * It has its own small layout, lighter than the backend one, made for phones.
 */
?>
<!doctype html>
<html lang="<?= config('language_code') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= e(branding_color()) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="google" content="notranslate">

    <title><?= lang('simple_version') ?> | <?= e(setting('company_name')) ?></title>

    <link rel="icon" href="<?= e(branding_logo()) ?>">
    <link rel="icon" sizes="192x192" href="<?= e(branding_logo()) ?>">
    <link rel="apple-touch-icon" href="<?= e(branding_logo()) ?>">

    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" type="text/css"
          href="<?= asset_url('assets/css/themes/' . html_escape(setting('theme', 'default')) . '.css') ?>">
    <?php if (vars('clinical_records')): ?>
        <link rel="stylesheet" type="text/css" href="<?= asset_url(
            'assets/vendor/air-datepicker/air-datepicker.min.css',
        ) ?>">
        <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/general.css') ?>">
        <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/backend.css') ?>">
    <?php endif; ?>
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/simple.css') ?>">
    <?php component('company_color_style', ['company_color' => setting('company_color')]); ?>
</head>
<body class="ea-simple">

<header class="ea-simple-header">
    <nav class="ea-simple-day" aria-label="<?= e(lang('calendar')) ?>">
        <button type="button" class="ea-simple-icon-button" id="simple-previous" aria-label="‹">
            <i class="fas fa-chevron-left"></i>
        </button>
        <label class="ea-simple-day-label">
            <span id="simple-day-relative"></span>
            <strong id="simple-day-date"></strong>
            <!-- Native date picker of the phone, opened by tapping the day. -->
            <input type="date" id="simple-date" aria-label="<?= e(lang('calendar')) ?>">
        </label>
        <button type="button" class="ea-simple-icon-button" id="simple-next" aria-label="›">
            <i class="fas fa-chevron-right"></i>
        </button>
    </nav>

    <div class="ea-simple-providers" id="simple-providers" role="group"
         aria-label="<?= e(lang('provider')) ?>"></div>
</header>

<main class="ea-simple-main">
    <div class="ea-simple-summary" id="simple-summary"></div>
    <ol class="ea-simple-list" id="simple-list" aria-live="polite"></ol>
    <p class="ea-simple-empty" id="simple-empty" hidden><?= lang('simple_no_appointments') ?></p>
    <p class="ea-simple-error" id="simple-error" hidden><?= lang('simple_load_failed') ?></p>
</main>

<footer class="ea-simple-footer">
    <a href="<?= site_url('calendar?full=1') ?>"><i class="fas fa-desktop me-1"></i><?= lang(
    'simple_full_version',
) ?></a>
</footer>

<!-- Details of the tapped appointment, as a sheet from the bottom. -->
<dialog class="ea-simple-sheet" id="simple-sheet">
    <div class="ea-simple-sheet-handle"></div>
    <div id="simple-sheet-content"></div>
    <button type="button" class="btn btn-light w-100 mt-3" id="simple-sheet-close"><?= lang('close') ?></button>
</dialog>

<!-- Asks before calling or messaging a patient; "No" is the main button, so that a slip of the finger does nothing. -->
<dialog class="ea-simple-confirm" id="simple-confirm">
    <p id="simple-confirm-text"></p>
    <div class="ea-simple-confirm-buttons">
        <button type="button" class="btn btn-outline-secondary" id="simple-confirm-yes"></button>
        <button type="button" class="btn btn-primary" id="simple-confirm-no"><?= lang('no') ?></button>
    </div>
</dialog>

<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/fontawesome.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/solid.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/brands.min.js') ?>"></script>

<?php component('js_vars_script'); ?>
<?php component('js_lang_script'); ?>

<script src="<?= asset_url('assets/vendor/@popperjs-core/popper.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.js') ?>"></script>

<?php if (vars('clinical_records')): ?>
    <script src="<?= asset_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
    <script src="<?= asset_url('assets/vendor/moment/moment.min.js') ?>"></script>
    <script src="<?= asset_url('assets/vendor/tippy.js/tippy-bundle.umd.min.js') ?>"></script>
    <script src="<?= asset_url('assets/vendor/air-datepicker/air-datepicker.min.js') ?>"></script>
    <script src="<?= asset_url('assets/js/app.js') ?>"></script>
    <script src="<?= asset_url('assets/js/utils/date.js') ?>"></script>
    <script src="<?= asset_url('assets/js/utils/message.js') ?>"></script>
    <script src="<?= asset_url('assets/js/utils/string.js') ?>"></script>
    <script src="<?= asset_url('assets/js/utils/url.js') ?>"></script>
    <script src="<?= asset_url('assets/js/utils/ui.js') ?>"></script>
    <script src="<?= asset_url('assets/js/layouts/backend_layout.js') ?>"></script>
    <?php component('clinical_record_modal', ['standalone' => true]); ?>
<?php endif; ?>

<script src="<?= asset_url('assets/js/pages/simple.js') ?>"></script>

</body>
</html>
