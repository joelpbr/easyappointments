<?php extend('layouts/backend_layout'); ?>

<?php
// Set by Activity_log::index().
$entries = vars('entries');
$total = vars('total');
$page = vars('page');
$pages = vars('pages');
$filters = vars('filters');
$options = vars('options');
$staff = vars('staff');

// Colour of each kind of action.
$action_class = static fn(string $action): string => match ($action) {
    'created', 'backup', 'test_sent' => 'text-bg-success',
    'updated', 'resent', 'marked_sent', 'patient_told' => 'text-bg-primary',
    'deleted', 'cancelled', 'restore' => 'text-bg-danger',
    default => 'text-bg-secondary',
};

// Link to another page of the list, with the same filters.
$page_url = static fn(int $number): string => site_url('activity_log') .
    '?' .
    http_build_query(array_filter($filters + ['page' => $number > 1 ? $number : null]));
?>

<?php section('content'); ?>

<!-- Every change made in the application, the latest first (Activity_log controller, Audit_trail library). -->
<div id="activity-log-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div class="col-sm-9">
            <div class="d-flex justify-content-between align-items-center border-bottom mb-3 py-2">
                <h4 class="mb-0 fw-light"><?= lang('activity_log') ?></h4>
                <span class="text-muted small"><?= e(sprintf(lang('activity_log_count'), number_format($total, 0, ',', '.'))) ?></span>
            </div>

            <p class="form-text mt-0"><?= lang('activity_log_hint') ?></p>

            <form method="get" action="<?= site_url('activity_log') ?>" class="row g-2 align-items-end mb-3 ea-activity-filters">
                <div class="col-12">
                    <label class="visually-hidden" for="activity-search"><?= lang('activity_log_search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input id="activity-search" name="q" type="search" class="form-control"
                               value="<?= e($filters['q']) ?>" autocomplete="off"
                               placeholder="<?= e(lang('activity_log_search_placeholder')) ?>">
                        <button type="submit" class="btn btn-primary"><?= lang('activity_log_search') ?></button>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="activity-from"><?= lang('from') ?></label>
                    <input id="activity-from" name="from" type="date" class="form-control form-control-sm"
                           value="<?= e($filters['from']) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="activity-to"><?= lang('activity_log_to') ?></label>
                    <input id="activity-to" name="to" type="date" class="form-control form-control-sm"
                           value="<?= e($filters['to']) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1" for="activity-user"><?= lang('activity_log_user') ?></label>
                    <select id="activity-user" name="user" class="form-select form-select-sm">
                        <option value=""><?= lang('all') ?></option>
                        <?php foreach ($staff as $id => $name): ?>
                            <option value="<?= (int) $id ?>" <?= (int) $filters['user'] === (int) $id ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1" for="activity-entity"><?= lang('activity_log_entity') ?></label>
                    <select id="activity-entity" name="entity" class="form-select form-select-sm">
                        <option value=""><?= lang('all') ?></option>
                        <?php foreach ($options['entities'] as $entity): ?>
                            <option value="<?= e($entity) ?>" <?= $filters['entity'] === $entity ? 'selected' : '' ?>>
                                <?= e(Audit_trail::label('audit_entity_' . $entity, $entity)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="activity-action"><?= lang('activity_log_action') ?></label>
                    <select id="activity-action" name="action" class="form-select form-select-sm">
                        <option value=""><?= lang('all') ?></option>
                        <?php foreach ($options['actions'] as $action): ?>
                            <option value="<?= e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>>
                                <?= e(Audit_trail::label('audit_action_' . $action, $action)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-filter me-1"></i><?= lang('filter') ?>
                    </button>
                    <?php if (array_filter($filters)): ?>
                        <a href="<?= site_url('activity_log') ?>" class="btn btn-sm btn-outline-secondary">
                            <?= lang('activity_log_clear') ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!$entries): ?>
                <p class="text-muted my-4"><?= lang('activity_log_empty') ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-top ea-activity-table">
                        <thead>
                        <tr>
                            <th><?= lang('activity_log_when') ?></th>
                            <th><?= lang('activity_log_user') ?></th>
                            <th><?= lang('activity_log_action') ?></th>
                            <th><?= lang('activity_log_entity') ?></th>
                            <th><?= lang('activity_log_changes') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y H:i:s', strtotime($entry['datetime'])) ?></td>
                                <td>
                                    <?= e($entry['user'] ?? '—') ?>
                                    <?php if ($entry['source'] !== 'backend'): ?>
                                        <div>
                                            <span class="badge text-bg-light border">
                                                <?= e(Audit_trail::label('audit_source_' . $entry['source'], $entry['source'])) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $action_class($entry['action']) ?>">
                                        <?= e(Audit_trail::label('audit_action_' . $entry['action'], $entry['action'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        <?= e(Audit_trail::label('audit_entity_' . $entry['entity'], $entry['entity'])) ?>
                                    </div>
                                    <?php if ($entry['subject']): ?>
                                        <div class="small"><?= e($entry['subject']) ?></div>
                                    <?php elseif ($entry['entity_id']): ?>
                                        <div class="small text-muted">#<?= (int) $entry['entity_id'] ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php foreach ($entry['changes'] as $change): ?>
                                        <div class="ea-activity-change">
                                            <span class="text-muted"><?= e($change['label']) ?>:</span>
                                            <?php if ($entry['action'] === 'created' || $change['before'] === null): ?>
                                                <?= e($change['after'] ?? '—') ?>
                                            <?php elseif ($entry['action'] === 'deleted' || $change['after'] === null): ?>
                                                <span class="ea-activity-before"><?= e($change['before']) ?></span>
                                            <?php elseif ($change['before'] === $change['after']): ?>
                                                <?= e($change['after']) ?>
                                            <?php else: ?>
                                                <span class="ea-activity-before"><?= e($change['before']) ?></span>
                                                <i class="fas fa-arrow-right mx-1 text-muted small"></i>
                                                <?= e($change['after']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="d-flex justify-content-between align-items-center">
                        <a class="btn btn-sm btn-outline-primary <?= $page <= 1 ? 'disabled' : '' ?>"
                           href="<?= $page > 1 ? e($page_url($page - 1)) : '#' ?>">
                            <i class="fas fa-chevron-left me-1"></i><?= lang('previous') ?>
                        </a>
                        <span class="small text-muted"><?= e(sprintf(lang('activity_log_page'), $page, $pages)) ?></span>
                        <a class="btn btn-sm btn-outline-primary <?= $page >= $pages ? 'disabled' : '' ?>"
                           href="<?= $page < $pages ? e($page_url($page + 1)) : '#' ?>">
                            <?= lang('next') ?><i class="fas fa-chevron-right ms-1"></i>
                        </a>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php end_section('content'); ?>
