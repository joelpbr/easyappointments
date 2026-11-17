<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<style>
    #attendance-settings-page .att-unit { min-width: 3.5rem; justify-content: center; }
    #attendance-settings-page .att-day { display: grid; grid-template-columns: 7rem 1fr auto; gap: .5rem; align-items: center; padding: .4rem 0; border-bottom: 1px solid rgba(0, 0, 0, .06); }
    #attendance-settings-page .att-interval { display: inline-flex; align-items: center; gap: .25rem; margin: .15rem .5rem .15rem 0; }
    #attendance-settings-page .att-interval input { width: 7.5rem; }
    #attendance-settings-page .att-employee { border-radius: 1rem; }
</style>

<div id="attendance-settings-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div class="col-sm-9">
            <form id="att-rules-form" novalidate>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom mb-4 py-2">
                    <h4 class="mb-0 fw-light"><?= lang('att_settings') ?></h4>
                    <button type="submit" class="btn btn-primary text-nowrap">
                        <i class="fas fa-check-square me-2"></i><?= lang('save') ?>
                    </button>
                </div>
                <div id="att-rules"></div>
            </form>

            <h5 class="fw-light border-bottom pb-2 mt-5 mb-3"><?= lang('att_staff') ?></h5>
            <div id="att-employees"></div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/pages/attendance_settings.js') ?>"></script>
<?php end_section('scripts'); ?>
