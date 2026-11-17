<?php extend('layouts/backend_layout'); ?>
<?php section('content'); ?>
<div class="container py-4" id="attendance-page">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div><h3><?= lang('att_title') ?></h3><p class="text-muted mb-0"><?= lang('att_intro') ?></p></div>
        <a class="btn btn-outline-secondary" href="<?= site_url('calendar') ?>"><?= lang('calendar') ?></a>
    </div>
    <div id="att-feedback" role="status" aria-live="polite"></div>
    <?php if (!vars('attendance_admin')): ?>
        <section class="card shadow-sm mb-4"><div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3"><div>
                <span class="text-muted"><?= lang('att_now') ?></span><h4 id="att-state" class="mt-1">…</h4>
                <div id="att-since" class="text-muted"></div>
            </div><div class="d-flex flex-wrap gap-2 align-items-center" id="att-clock-actions"></div></div>
            <p id="att-open-warning" class="text-danger mt-3 mb-0" hidden><?= lang('att_open_warning') ?></p>
            <small class="text-muted d-block mt-3"><?= lang('att_clock_hint') ?></small>
        </div></section>
    <?php else: ?>
        <div id="att-team" class="row g-2 mb-4"></div>
    <?php endif; ?>
    <div class="row g-3 align-items-end mb-4">
        <div class="col-sm-5"><label for="att-employee" class="form-label"><?= lang('att_employee') ?></label><select id="att-employee" class="form-select"></select></div>
        <div class="col-sm-3"><label for="att-month" class="form-label"><?= lang('att_month') ?></label><input id="att-month" type="month" class="form-control"></div>
        <div class="col-sm-4 d-flex flex-wrap gap-2"><button id="att-refresh" class="btn btn-outline-secondary"><?= lang('reload') ?></button>
            <a id="att-csv" class="btn btn-outline-primary">CSV</a><a id="att-print" target="_blank" rel="noopener" class="btn btn-outline-primary"><?= lang('att_print') ?></a>
            <a id="att-json" class="btn btn-outline-secondary" title="<?= e(lang('att_evidence')) ?>">JSON</a>
        </div>
    </div>
    <div id="att-summary" class="row g-2 mb-3"></div>
    <div id="att-provisional" class="alert alert-warning" hidden><?= lang('att_provisional_hint') ?></div>
    <div id="att-review" class="alert alert-warning" hidden></div>
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h5><?= lang('att_entries') ?></h5>
        <?php if (vars('attendance_admin')): ?><button id="att-add" class="btn btn-outline-primary"><?= lang('att_add_missing') ?></button>
        <?php else: ?><button id="att-request" class="btn btn-outline-primary"><?= lang('att_request') ?></button><?php endif; ?>
    </div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr>
        <th><?= lang('att_start') ?></th><th><?= lang('att_end') ?></th><th><?= lang('att_work') ?></th><th><?= lang('att_break') ?></th><th><?= lang('att_status') ?></th><th><?= lang('actions') ?></th>
    </tr></thead><tbody id="att-entries"></tbody></table></div>
    <h5 class="mt-4"><?= lang('att_daily') ?></h5>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th><?= lang('date') ?></th><th><?= lang('att_work') ?></th><th><?= lang('att_paid') ?></th><th><?= lang('att_unpaid') ?></th><th><?= lang('att_unknown') ?></th></tr></thead><tbody id="att-days"></tbody></table></div>
    <section class="mt-4"><h5><?= lang('att_requests') ?></h5><div id="att-requests"></div></section>
    <section class="mt-4"><h5><?= lang('att_reports') ?></h5><p class="text-muted"><?= lang('att_reports_hint') ?></p><div id="att-reports"></div></section>
    <?php if (vars('attendance_admin')): ?>
        <details class="card mt-4"><summary class="card-header"><?= lang('att_profile') ?></summary><div class="card-body">
            <p class="text-muted"><?= lang('att_profile_hint') ?></p>
            <form id="att-profile-form" class="row g-3">
                <div class="col-md-4"><label class="form-label" for="att-contract"><?= lang('att_contract') ?></label><select id="att-contract" class="form-select"><option value="unknown"><?= lang('att_unknown') ?></option><option value="full"><?= lang('att_full') ?></option><option value="part"><?= lang('att_part') ?></option></select></div>
                <div class="col-md-4"><label class="form-label" for="att-weekly"><?= lang('att_weekly') ?></label><input id="att-weekly" type="number" min="1" max="10080" step="1" class="form-control"></div>
                <div class="col-md-4"><label class="form-label" for="att-pause-policy"><?= lang('att_policy') ?></label><select id="att-pause-policy" class="form-select"><option value="unknown"><?= lang('att_unknown') ?></option><option value="paid"><?= lang('att_paid') ?></option><option value="unpaid"><?= lang('att_unpaid') ?></option></select></div>
                <div class="col-12"><label class="form-label" for="att-agreement"><?= lang('att_agreement') ?></label><input id="att-agreement" maxlength="512" class="form-control"></div>
                <div class="col-12"><button class="btn btn-primary"><?= lang('save') ?></button></div>
            </form>
        </div></details>
        <details class="card mt-3"><summary class="card-header"><?= lang('att_issue') ?></summary><div class="card-body">
            <p><?= lang('att_issue_hint') ?></p><form id="att-issue-form" class="row g-3">
                <?php foreach (['ordinary', 'complementary', 'overtime'] as $kind): ?>
                    <div class="col-sm-4"><label class="form-label" for="att-<?= $kind ?>"><?= lang('att_'.$kind) ?> (HH:MM:SS)</label><input id="att-<?= $kind ?>" class="form-control" pattern="[0-9]{1,4}:[0-5][0-9]:[0-5][0-9]" value="0:00:00" required></div>
                <?php endforeach; ?>
                <div class="col-12"><button class="btn btn-primary"><?= lang('att_issue') ?></button></div>
            </form>
        </div></details>
    <?php endif; ?>
    <details class="mt-4"><summary><?= lang('att_legal_title') ?></summary><div class="small text-muted pt-2">
        <p><?= lang('att_legal') ?></p><p><?= lang('att_privacy') ?></p>
        <a href="https://www.boe.es/buscar/act.php?id=BOE-A-2015-11430#a34" target="_blank" rel="noopener">Estatuto de los Trabajadores · 34.9, 12.4.c, 35.5</a>
    </div></details>
</div>
<div class="modal fade" id="att-modal" tabindex="-1" aria-labelledby="att-modal-title"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="att-modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(lang('close')) ?>"></button></div>
    <div class="modal-body" id="att-modal-body"></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('cancel') ?></button><button id="att-modal-save" type="button" class="btn btn-primary"><?= lang('save') ?></button></div>
</div></div></div>
<?php end_section('content'); ?>
<?php section('scripts'); ?><script src="<?= asset_url('assets/js/pages/attendance.js') ?>"></script><?php end_section('scripts'); ?>
