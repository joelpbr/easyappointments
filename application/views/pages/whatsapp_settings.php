<?php extend('layouts/backend_layout'); ?>

<?php
// Set by Whatsapp_settings::index().
$webhook_url = vars('webhook_url');
$webhook_verify_token = vars('webhook_verify_token');
$has_access_token = vars('has_access_token');
$has_app_secret = vars('has_app_secret');
$overview = vars('overview');
$message_price = vars('message_price');
$template_body = vars('template_body');

$counts = $overview['counts'];
$to_review = $counts['failed'] + $counts['review'];
?>

<?php section('content'); ?>

<!-- Settings and panel of the WhatsApp reminders (whatsapp_settings.js, Whatsapp_reminders library). What is only
     set once (template, webhook) stays folded under "Advanced". -->
<div id="whatsapp-settings-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div id="whatsapp-settings" class="col-sm-9">
            <form>
                <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
                    <h4 class="mb-0 fw-light"><?= lang('whatsapp_reminders') ?></h4>

                    <div>
                        <a href="<?= site_url('integrations') ?>" class="btn btn-outline-primary me-2">
                            <i class="fas fa-chevron-left me-2"></i>
                            <?= lang('back') ?>
                        </a>
                        <button type="button" id="save-settings" class="btn btn-primary">
                            <i class="fas fa-check-square me-2"></i>
                            <?= lang('save') ?>
                        </button>
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="whatsapp-enabled" data-field="whatsapp_enabled">
                    <label class="form-check-label" for="whatsapp-enabled"><?= lang('whatsapp_enabled') ?></label>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="whatsapp-phone-number-id">
                            <?= lang('whatsapp_phone_number_id') ?>
                        </label>
                        <input id="whatsapp-phone-number-id" class="form-control" data-field="whatsapp_phone_number_id"
                               inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="whatsapp-access-token"><?= lang('whatsapp_access_token') ?></label>
                        <input id="whatsapp-access-token" type="password" class="form-control"
                               data-field="whatsapp_access_token" autocomplete="new-password"
                               placeholder="<?= $has_access_token ? e(lang('whatsapp_secret_saved')) : '' ?>">
                    </div>
                </div>

                <div class="input-group mb-4">
                    <input id="whatsapp-test-phone" class="form-control" type="tel" placeholder="+34 600 000 000"
                           aria-label="<?= e(lang('phone_number')) ?>">
                    <button type="button" id="whatsapp-send-test" class="btn btn-outline-primary">
                        <i class="fas fa-paper-plane me-2"></i><?= lang('whatsapp_send_test') ?>
                    </button>
                </div>

                <!-- When the reminders go out (Whatsapp_reminders::rules()). -->
                <h5 class="fw-light mb-2"><?= lang('whatsapp_schedule') ?></h5>
                <p class="form-text mt-0 mb-3"><?= lang('whatsapp_schedule_hint') ?></p>

                <div class="row">
                    <div class="col-6 col-md-3 mb-3">
                        <label class="form-label" for="whatsapp-send-time"><?= lang('whatsapp_send_time') ?></label>
                        <input id="whatsapp-send-time" class="form-control" type="time" step="300" required
                               data-field="whatsapp_send_time">
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <label class="form-label" for="whatsapp-days-before"><?= lang('whatsapp_days_before') ?></label>
                        <input id="whatsapp-days-before" class="form-control" type="number" min="0" max="7" step="1"
                               required data-field="whatsapp_days_before">
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <label class="form-label" for="whatsapp-booking-delay">
                            <?= lang('whatsapp_booking_delay') ?>
                        </label>
                        <input id="whatsapp-booking-delay" class="form-control" type="number" min="0" max="1440"
                               step="5" required data-field="whatsapp_booking_delay">
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <label class="form-label" for="whatsapp-min-notice"><?= lang('whatsapp_min_notice') ?></label>
                        <input id="whatsapp-min-notice" class="form-control" type="number" min="0" max="2880" step="5"
                               required data-field="whatsapp_min_notice">
                    </div>
                </div>
                <p id="whatsapp-schedule-summary" class="small text-muted mb-4"></p>

                <!-- Set once: the template approved in Meta and the webhook of the delivery statuses. -->
                <details class="mb-4 ea-whatsapp-advanced">
                    <summary class="mb-3"><?= lang('whatsapp_advanced') ?></summary>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="whatsapp-template-name">
                                <?= lang('whatsapp_template_name') ?>
                            </label>
                            <input id="whatsapp-template-name" class="form-control" data-field="whatsapp_template_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="whatsapp-template-language">
                                <?= lang('whatsapp_template_language') ?>
                            </label>
                            <input id="whatsapp-template-language" class="form-control"
                                   data-field="whatsapp_template_language" maxlength="10">
                        </div>
                    </div>
                    <p class="form-text mb-2"><?= lang('whatsapp_template_hint') ?></p>
                    <textarea id="whatsapp-template-body" class="form-control font-monospace small mb-3" rows="8" readonly
                              aria-label="<?= e(lang('whatsapp_template_name')) ?>"><?= e($template_body) ?></textarea>

                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" id="whatsapp-template-says-tomorrow"
                               data-field="whatsapp_template_says_tomorrow">
                        <label class="form-check-label" for="whatsapp-template-says-tomorrow">
                            <?= lang('whatsapp_template_says_tomorrow') ?>
                        </label>
                    </div>
                    <p class="form-text mb-4"><?= lang('whatsapp_template_says_tomorrow_hint') ?></p>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="whatsapp-webhook-url"><?= lang('whatsapp_webhook_url') ?></label>
                            <input id="whatsapp-webhook-url" class="form-control" value="<?= e($webhook_url) ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="whatsapp-verify-token">
                                <?= lang('whatsapp_webhook_verify_token') ?>
                            </label>
                            <input id="whatsapp-verify-token" class="form-control"
                                   value="<?= e($webhook_verify_token) ?>" readonly>
                        </div>
                        <div class="col-12 mb-2">
                            <label class="form-label" for="whatsapp-app-secret"><?= lang('whatsapp_app_secret') ?></label>
                            <input id="whatsapp-app-secret" type="password" class="form-control"
                                   data-field="whatsapp_app_secret" autocomplete="new-password"
                                   placeholder="<?= $has_app_secret ? e(lang('whatsapp_secret_saved')) : '' ?>">
                        </div>
                    </div>
                    <p class="form-text"><?= lang('whatsapp_webhook_hint') ?></p>
                </details>
            </form>

            <!-- How the reminders are going. -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100">
                        <div class="small text-muted"><?= lang('whatsapp_tomorrow') ?></div>
                        <div class="fs-4"><?= (int) $overview['tomorrow'] ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100">
                        <div class="small text-muted"><?= lang('whatsapp_status_sent') ?></div>
                        <div class="fs-4">
                            <?= (int) ($counts['accepted'] + $counts['sent'] + $counts['delivered'] + $counts['read']) ?>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100 <?= $to_review ? 'border-danger' : '' ?>">
                        <div class="small text-muted"><?= lang('whatsapp_status_review') ?></div>
                        <div class="fs-4"><?= (int) $to_review ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100">
                        <div class="small text-muted"><?= lang('whatsapp_month_cost') ?></div>
                        <div class="fs-4 ea-money">
                            <?= number_format($overview['month_sent'] * $message_price, 2, ',', '.') ?>€
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($overview['latest']): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle ea-whatsapp-table">
                        <thead>
                        <tr>
                            <th><?= lang('appointment') ?></th>
                            <th><?= lang('customer') ?></th>
                            <th><?= lang('status') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($overview['latest'] as $reminder): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?= date('d/m/Y H:i', strtotime($reminder['appointment_datetime'])) ?>
                                </td>
                                <td><?= e($reminder['customer_name'] ?: '—') ?></td>
                                <td>
                                    <span class="badge ea-whatsapp-status ea-whatsapp-status-<?= e($reminder['status']) ?>">
                                        <?= lang('whatsapp_status_' . $reminder['status']) ?>
                                    </span>
                                    <?php if ($reminder['error']): ?>
                                        <div class="small text-danger"><?= e($reminder['error']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <a href="<?= site_url('whatsapp_messages') ?>" class="btn btn-outline-primary">
                <i class="fas fa-list me-2"></i><?= lang('whatsapp_see_all') ?>
            </a>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/pages/whatsapp_settings.js') ?>"></script>

<?php end_section('scripts'); ?>
