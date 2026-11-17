<?php extend('layouts/backend_layout'); ?>
<?php section('content'); ?>
<?php $admin = vars('attendance_admin'); ?>
<style>
    #attendance-page { max-width: 960px; }
    .att-feedback { position: fixed; top: 84px; left: 50%; transform: translateX(-50%); z-index: 1080; min-width: 280px; text-align: center; box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .12); }
    .att-center { min-height: calc(100vh - 200px); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.5rem; }
    .att-clock-card { width: 100%; max-width: 400px; border-radius: 1.5rem; padding: 2.5rem 2rem; text-align: center; }
    .att-time { font-size: 4.5rem; font-weight: 300; line-height: 1; letter-spacing: -.03em; font-variant-numeric: tabular-nums; }
    .att-pill { align-self: center; display: inline-flex; align-items: center; gap: .5rem; padding: .4rem 1rem; border-radius: 999px; background: #f1f3f5; font-weight: 600; }
    .att-dot { width: .6rem; height: .6rem; border-radius: 50%; background: #adb5bd; flex: none; }
    [data-state="working"] .att-dot { background: #198754; box-shadow: 0 0 0 4px rgba(25, 135, 84, .18); }
    [data-state="paused"] .att-dot { background: #f0ad00; box-shadow: 0 0 0 4px rgba(240, 173, 0, .2); }
    #att-clock-actions .btn { padding: .9rem; border-radius: .85rem; font-weight: 600; }
    .att-team { width: 100%; max-width: 720px; }
    .att-person { border-radius: 1rem; transition: box-shadow .15s, transform .15s; }
    .att-person:hover { box-shadow: 0 .5rem 1.25rem rgba(0, 0, 0, .08); transform: translateY(-1px); }
    .att-avatar { width: 2.6rem; height: 2.6rem; border-radius: 50%; background: #e7f1ff; color: #0d6efd; display: grid; place-items: center; font-weight: 700; flex: none; }
    .att-total { font-size: 2.5rem; font-weight: 300; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .att-card { border-radius: 1rem; }
    #att-view-records .nav-pills .nav-link { border-radius: 999px; }
    #att-entries td, #att-days td { font-variant-numeric: tabular-nums; }
</style>
<div class="container py-4" id="attendance-page">
    <div id="att-feedback" class="att-feedback" role="status" aria-live="polite" hidden></div>

    <section id="att-view-clock" class="att-center">
        <?php if (vars('attendance_worker')): ?>
            <div class="card border-0 shadow-sm att-clock-card">
                <div id="att-time" class="att-time">--:--</div>
                <div id="att-date" class="att-date text-muted mt-2 mb-4"></div>
                <div id="att-state-pill" class="att-pill" data-state="off"><span class="att-dot"></span><span id="att-state">…</span></div>
                <div id="att-since" class="text-muted small mt-2" style="min-height: 1.25rem"></div>
                <div id="att-clock-actions" class="d-grid gap-2 mt-4"></div>
                <p id="att-open-warning" class="text-danger small mt-3 mb-0" hidden><?= lang('att_open_warning') ?></p>
            </div>
        <?php else: ?>
            <h3 class="mb-0"><?= lang('att_title') ?></h3>
            <div id="att-team" class="att-team row g-3 justify-content-center"></div>
        <?php endif; ?>
        <div class="d-flex gap-3">
            <a href="#registres" class="btn btn-link text-decoration-none"><?= lang('att_records') ?> →</a>
            <?php if ($admin): ?><a href="<?= site_url('attendance_settings') ?>" class="btn btn-link text-decoration-none text-muted"><i class="fas fa-cog me-1"></i><?= lang('att_settings') ?></a><?php endif; ?>
        </div>
    </section>

    <section id="att-view-records" hidden>
        <div class="d-flex align-items-center gap-2 mb-4">
            <?php if ($admin || vars('attendance_worker')): ?><a href="#" class="btn btn-light">← <?= lang($admin ? 'att_team' : 'att_title') ?></a><?php endif; ?>
            <h4 class="mb-0 mx-auto"><?= lang('att_records') ?></h4>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= lang('att_export') ?></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a id="att-print" class="dropdown-item" target="_blank" rel="noopener"><?= lang('att_print') ?></a></li>
                    <li><a id="att-csv" class="dropdown-item">CSV</a></li>
                    <li><a id="att-json" class="dropdown-item">JSON</a></li>
                </ul>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
            <div <?= $admin ? '' : 'hidden' ?>><select id="att-employee" class="form-select" aria-label="<?= e(lang('att_employee')) ?>"></select></div>
            <div><input id="att-month" type="month" class="form-control" aria-label="<?= e(lang('att_month')) ?>"></div>
        </div>

        <div class="card border-0 shadow-sm att-card text-center p-4 mb-3 mx-auto" style="max-width: 420px">
            <small class="text-muted"><?= lang('att_counted') ?></small>
            <div id="att-total" class="att-total">—</div>
            <div id="att-breakdown" class="small text-muted"></div>
            <div><span id="att-provisional" class="badge rounded-pill text-bg-warning mt-2" hidden><?= lang('att_provisional') ?></span></div>
        </div>
        <div id="att-review" class="alert alert-warning small text-center mx-auto" style="max-width: 560px" hidden></div>

        <ul class="nav nav-pills justify-content-center gap-1 my-4" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#att-tab-entries" type="button" role="tab"><?= lang('att_entries') ?></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#att-tab-requests" type="button" role="tab"><?= lang('att_requests') ?> <span id="att-pending-badge" class="badge rounded-pill text-bg-danger" hidden></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#att-tab-reports" type="button" role="tab"><?= lang('att_reports') ?></button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="att-tab-entries" role="tabpanel">
                <div class="card border-0 shadow-sm att-card">
                    <div class="table-responsive"><table class="table table-hover align-middle text-center mb-0"><thead class="small text-muted"><tr>
                        <th class="fw-normal"><?= lang('date') ?></th><th class="fw-normal"><?= lang('att_event_in') ?></th><th class="fw-normal"><?= lang('att_event_out') ?></th><th class="fw-normal"><?= lang('att_worked') ?></th><th class="fw-normal"><?= lang('att_break') ?></th><th></th>
                    </tr></thead><tbody id="att-entries"></tbody></table></div>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-3">
                    <details><summary class="small text-muted"><?= lang('att_daily') ?></summary>
                        <div class="table-responsive mt-2"><table class="table table-sm small text-center"><thead><tr><th><?= lang('date') ?></th><th><?= lang('att_planned') ?></th><th><?= lang('att_work') ?></th><th><?= lang('att_paid') ?></th><th><?= lang('att_unpaid') ?></th><th><?= lang('att_unknown') ?></th></tr></thead><tbody id="att-days"></tbody></table></div>
                    </details>
                    <?php if ($admin): ?><button id="att-add" class="btn btn-sm btn-outline-primary"><?= lang('att_add_missing') ?></button><?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="att-tab-requests" role="tabpanel">
                <div class="mx-auto" style="max-width: 640px">
                    <?php if (vars('attendance_worker')): ?>
                        <div class="text-center mb-3"><button id="att-request" class="btn btn-outline-primary"><?= lang('att_request') ?></button></div>
                    <?php endif; ?>
                    <div id="att-requests"></div>
                </div>
            </div>

            <div class="tab-pane fade" id="att-tab-reports" role="tabpanel">
                <div class="mx-auto" style="max-width: 640px">
                    <div id="att-reports"></div>
                    <?php if ($admin): ?>
                        <form id="att-issue-form" class="card border-0 shadow-sm att-card p-3 mt-3 row g-2 mx-0 align-items-end">
                            <?php foreach (['ordinary', 'complementary', 'overtime'] as $kind): ?>
                                <div class="col-sm-4"><label class="form-label small text-muted" for="att-<?= $kind ?>"><?= lang('att_'.$kind) ?></label><input id="att-<?= $kind ?>" class="form-control text-center" pattern="[0-9]{1,4}:[0-5][0-9]:[0-5][0-9]" placeholder="H:MM:SS" value="0:00:00" required></div>
                            <?php endforeach; ?>
                            <div class="col-12 text-center mt-3"><button class="btn btn-primary px-4"><?= lang('att_issue') ?></button></div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <details class="mt-5 pt-3 border-top text-center"><summary class="small text-muted"><?= lang('att_legal_title') ?></summary><div class="small text-muted pt-2 mx-auto" style="max-width: 640px">
            <p class="mb-1"><?= sprintf(lang('att_legal'), (int) vars('attendance_retention_years')) ?></p><p class="mb-1"><?= lang('att_privacy') ?></p>
            <a href="<?= e(vars('attendance_legal_url')) ?>" target="_blank" rel="noopener"><?= e(vars('attendance_legal_reference')) ?></a>
        </div></details>
    </section>
</div>
<div class="modal fade" id="att-modal" tabindex="-1" aria-labelledby="att-modal-title"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="att-modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(lang('close')) ?>"></button></div>
    <div class="modal-body" id="att-modal-body"></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('cancel') ?></button><button id="att-modal-save" type="button" class="btn btn-primary"><?= lang('save') ?></button></div>
</div></div></div>
<?php end_section('content'); ?>
<?php section('scripts'); ?><script src="<?= asset_url('assets/js/pages/attendance.js') ?>"></script><?php end_section('scripts'); ?>
