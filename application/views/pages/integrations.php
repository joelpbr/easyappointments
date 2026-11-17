<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="integrations-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div id="integrations" class="col-sm-9">
            <?php component('settings_overview'); ?>
        </div>
    </div>
</div>

<?php end_section('content'); ?>
