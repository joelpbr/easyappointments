<?php
/**
 * Local variables.
 *
 * Mobile version of the calendar (see the Mobile controller and mobile.js): the appointments of a day, one below the
 * other, with day navigation, a provider filter and the details of an appointment with the patient's phone at hand.
 * It has its own small layout, lighter than the backend one, made for phones.
 */
?>
<!doctype html>
<html lang="<?= config('language_code') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#063f7e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="google" content="notranslate">

    <title><?= lang('calendar') ?> | <?= e(setting('company_name')) ?></title>

    <link rel="icon" type="image/x-icon" href="<?= asset_url('assets/img/favicon.ico') ?>">
    <link rel="icon" sizes="192x192" href="<?= asset_url('assets/img/logo-192.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/img/logo-192.png') ?>">

    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" type="text/css"
          href="<?= asset_url('assets/css/themes/' . html_escape(setting('theme', 'default')) . '.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/mobile.css') ?>">
</head>
<body class="ea-mobile">

<header class="ea-mobile-header">
    <nav class="ea-mobile-day" aria-label="<?= e(lang('calendar')) ?>">
        <button type="button" class="ea-mobile-icon-button" id="mobile-previous" aria-label="‹">
            <i class="fas fa-chevron-left"></i>
        </button>
        <label class="ea-mobile-day-label">
            <span id="mobile-day-relative"></span>
            <strong id="mobile-day-date"></strong>
            <!-- Native date picker of the phone, opened by tapping the day. -->
            <input type="date" id="mobile-date" aria-label="<?= e(lang('calendar')) ?>">
        </label>
        <button type="button" class="ea-mobile-icon-button" id="mobile-next" aria-label="›">
            <i class="fas fa-chevron-right"></i>
        </button>
    </nav>

    <div class="ea-mobile-providers" id="mobile-providers" role="group"
         aria-label="<?= e(lang('provider')) ?>"></div>
</header>

<main class="ea-mobile-main">
    <div class="ea-mobile-summary" id="mobile-summary"></div>
    <ol class="ea-mobile-list" id="mobile-list" aria-live="polite"></ol>
    <p class="ea-mobile-empty" id="mobile-empty" hidden><?= lang('mobile_no_appointments') ?></p>
    <p class="ea-mobile-error" id="mobile-error" hidden><?= lang('mobile_load_failed') ?></p>
</main>

<footer class="ea-mobile-footer">
    <a href="<?= site_url('calendar?full=1') ?>"><i class="fas fa-desktop me-1"></i><?= lang('mobile_full_version') ?></a>
    <a href="<?= site_url('logout') ?>"><i class="fas fa-sign-out-alt me-1"></i><?= lang('log_out') ?></a>
</footer>

<!-- Details of the tapped appointment, as a sheet from the bottom. -->
<dialog class="ea-mobile-sheet" id="mobile-sheet">
    <div class="ea-mobile-sheet-handle"></div>
    <div id="mobile-sheet-content"></div>
    <button type="button" class="btn btn-light w-100 mt-3" id="mobile-sheet-close"><?= lang('close') ?></button>
</dialog>

<!-- Asks before calling or messaging a patient; "No" is the main button, so that a slip of the finger does nothing. -->
<dialog class="ea-mobile-confirm" id="mobile-confirm">
    <p id="mobile-confirm-text"></p>
    <div class="ea-mobile-confirm-buttons">
        <button type="button" class="btn btn-outline-secondary" id="mobile-confirm-yes"></button>
        <button type="button" class="btn btn-primary" id="mobile-confirm-no"><?= lang('no') ?></button>
    </div>
</dialog>

<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/fontawesome.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/solid.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/brands.min.js') ?>"></script>

<?php component('js_vars_script'); ?>
<?php component('js_lang_script'); ?>

<script src="<?= asset_url('assets/js/pages/mobile.js') ?>"></script>

</body>
</html>
