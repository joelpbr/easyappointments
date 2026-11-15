<?php extend('layouts/backend_layout'); ?>

<?php
// Set by Whatsapp_messages::index().
$messages = vars('messages');
$only_problems = vars('only_problems');
$problem_count = vars('problem_count');
$can_edit = vars('can_edit');
?>

<?php section('content'); ?>

<!-- Every WhatsApp message, the ones that could not be sent marked (whatsapp_messages.js). -->
<div id="whatsapp-messages-page" class="container backend-page py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom mb-3 py-2">
        <h4 class="mb-0 fw-light"><?= lang('whatsapp_messages') ?></h4>

        <div class="btn-group" role="group">
            <a href="<?= site_url('whatsapp_messages') ?>"
               class="btn <?= $only_problems ? 'btn-outline-primary' : 'btn-primary' ?>">
                <?= lang('whatsapp_all_messages') ?>
            </a>
            <a href="<?= site_url('whatsapp_messages?problems=1') ?>"
               class="btn <?= $only_problems ? 'btn-danger' : 'btn-outline-danger' ?>">
                <?= lang('whatsapp_not_sent') ?>
                <span class="badge rounded-pill bg-light text-danger ms-1"><?= (int) $problem_count ?></span>
            </a>
        </div>
    </div>

    <?php if ($problem_count): ?>
        <div class="alert alert-danger py-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= e(sprintf(lang('whatsapp_problems_warning'), (int) $problem_count)) ?>
        </div>
    <?php endif; ?>

    <?php if (!$messages): ?>
        <p class="text-muted my-4"><?= lang('whatsapp_no_messages') ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle ea-whatsapp-table">
                <thead>
                <tr>
                    <th><?= lang('appointment') ?></th>
                    <th><?= lang('customer') ?></th>
                    <th><?= lang('phone_number') ?></th>
                    <th><?= lang('status') ?></th>
                    <th><?= lang('whatsapp_last_attempt') ?></th>
                    <?php if ($can_edit): ?>
                        <th class="text-end"><?= lang('actions') ?></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $message): ?>
                    <?php $is_problem = in_array($message['status'], Whatsapp_reminders::PROBLEM_STATUSES, true); ?>
                    <tr data-id="<?= (int) $message['id'] ?>" data-status="<?= e($message['status']) ?>"
                        class="<?= $is_problem ? 'table-danger' : '' ?>">
                        <td class="text-nowrap">
                            <?php if ($message['is_test']): ?>
                                <span class="badge text-bg-secondary"><?= lang('whatsapp_test') ?></span>
                            <?php else: ?>
                                <?= date('d/m/Y H:i', strtotime($message['appointment_datetime'])) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($message['customer_name'] ?: '—') ?></td>
                        <td class="text-nowrap">
                            <?= $message['phone']
                                ? e(format_phone(ctype_digit($message['phone']) ? '+' . $message['phone'] : $message['phone']))
                                : '—' ?>
                        </td>
                        <td>
                            <span class="badge ea-whatsapp-status ea-whatsapp-status-<?= e($message['status']) ?>">
                                <?= lang('whatsapp_state_' . $message['status']) ?>
                            </span>
                            <?php if ($message['error'] && $is_problem): ?>
                                <!-- In plain words when Meta's code is a known one; the exact answer on hover. -->
                                <div class="small text-danger" title="<?= e($message['error']) ?>">
                                    <?= e(Whatsapp_reminders::describe_error($message['error'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap small text-muted">
                            <?= $message['last_attempt_datetime']
                                ? date('d/m/Y H:i', strtotime($message['last_attempt_datetime']))
                                : '—' ?>
                        </td>
                        <?php if ($can_edit): ?>
                            <td class="text-end text-nowrap">
                                <?php if (!in_array($message['status'], ['pending', 'processing'], true)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary ea-whatsapp-retry">
                                        <i class="fas fa-redo me-1"></i><?= lang('whatsapp_retry') ?>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-danger ea-whatsapp-delete"
                                        title="<?= e(lang('delete')) ?>" aria-label="<?= e(lang('delete')) ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/pages/whatsapp_messages.js') ?>"></script>

<?php end_section('scripts'); ?>
