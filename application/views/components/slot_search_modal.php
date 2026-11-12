<?php
/**
 * Local variables.
 *
 * @var array $available_services
 * @var array $available_providers
 */

// Only the providers the current user can book for (e.g. the ones assigned to reception).
$available_providers = array_filter(
    $available_providers,
    fn(array $provider): bool => can_manage_provider((int) $provider['id']),
); ?>

<div id="slot-search-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?= lang('find_slot') ?></h3>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="slot-search-form" class="row g-3 mb-3" novalidate>
                    <div class="col-12 col-md-5">
                        <label for="slot-search-service" class="form-label"><?= lang('service') ?></label>
                        <select id="slot-search-service" class="form-select">
                            <?php foreach ($available_services as $service): ?>
                                <?php if ($service['is_active']): ?>
                                    <option value="<?= (int) $service['id'] ?>"
                                            data-duration="<?= (int) $service['duration'] ?>">
                                        <?= e($service['name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="slot-search-duration" class="form-label"><?= lang('duration_minutes') ?></label>
                        <input id="slot-search-duration" class="form-control" type="number" min="5" max="1440">
                    </div>

                    <div class="col-12 col-md-5">
                        <label for="slot-search-providers" class="form-label"><?= lang('providers') ?></label>
                        <select id="slot-search-providers" class="form-select" multiple
                                aria-describedby="slot-search-providers-hint">
                            <?php foreach ($available_providers as $provider): ?>
                                <option value="<?= (int) $provider['id'] ?>">
                                    <?= e(trim($provider['first_name'] . ' ' . $provider['last_name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="slot-search-providers-hint" class="form-text">
                            <?= lang('slot_search_any_provider_hint') ?>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <label for="slot-search-date-from" class="form-label"><?= lang('from') ?></label>
                        <input id="slot-search-date-from" class="form-control" type="text">
                    </div>

                    <div class="col-6 col-md-3">
                        <label for="slot-search-date-to" class="form-label"><?= lang('until') ?></label>
                        <input id="slot-search-date-to" class="form-control" type="text">
                    </div>

                    <div class="col-12 col-md-6">
                        <span class="form-label d-block"><?= lang('weekdays') ?></span>
                        <div id="slot-search-weekdays" class="btn-group flex-wrap" role="group">
                            <?php $weekdays = [
                                'monday',
                                'tuesday',
                                'wednesday',
                                'thursday',
                                'friday',
                                'saturday',
                                'sunday',
                            ]; ?>
                            <?php foreach ($weekdays as $index => $weekday): ?>
                                <?php $weekday_id = 'slot-search-weekday-' . ($index + 1); ?>
                                <input type="checkbox" class="btn-check" id="<?= $weekday_id ?>"
                                       value="<?= $index + 1 ?>" autocomplete="off">
                                <label class="btn btn-outline-secondary btn-sm" for="<?= $weekday_id ?>">
                                    <?= mb_substr(lang($weekday), 0, 3) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12 col-md-5">
                        <span class="form-label d-block"><?= lang('time_window') ?></span>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm slot-search-window"
                                        data-from="" data-to=""><?= lang('any_time') ?></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm slot-search-window"
                                        data-from="00:00" data-to="14:00"><?= lang('morning') ?></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm slot-search-window"
                                        data-from="14:00" data-to="23:59"><?= lang('afternoon') ?></button>
                            </div>
                            <div class="d-flex align-items-center gap-1 slot-search-time-range">
                                <input id="slot-search-time-from" class="form-control form-control-sm" type="text"
                                       aria-label="<?= lang('from') ?>">
                                <span>–</span>
                                <input id="slot-search-time-to" class="form-control form-control-sm" type="text"
                                       aria-label="<?= lang('until') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="slot-search-preferred-time" class="form-label"><?= lang('preferred_time') ?></label>
                        <input id="slot-search-preferred-time" class="form-control" type="text">
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="slot-search-margin" class="form-label"><?= lang('margin') ?></label>
                        <select id="slot-search-margin" class="form-select">
                            <option value="0"><?= lang('exact_time') ?></option>
                            <option value="15">± 15 min</option>
                            <option value="30" selected>± 30 min</option>
                            <option value="60">± 1 h</option>
                            <option value="120">± 2 h</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="slot-search-preferred-provider" class="form-label">
                            <?= lang('preferred_provider') ?>
                        </label>
                        <select id="slot-search-preferred-provider" class="form-select">
                            <option value=""><?= lang('none') ?></option>
                            <?php foreach ($available_providers as $provider): ?>
                                <option value="<?= (int) $provider['id'] ?>">
                                    <?= e(trim($provider['first_name'] . ' ' . $provider['last_name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-flex align-items-end">
                        <button id="slot-search-submit" class="btn btn-primary w-100" type="submit">
                            <i class="fas fa-search me-2"></i>
                            <?= lang('search') ?>
                        </button>
                    </div>
                </form>

                <div id="slot-search-message" class="alert d-none" role="status"></div>

                <div class="table-responsive">
                    <table id="slot-search-results" class="table table-hover align-middle d-none">
                        <thead>
                        <tr>
                            <th><?= lang('day') ?></th>
                            <th><?= lang('date') ?></th>
                            <th><?= lang('time') ?></th>
                            <th><?= lang('provider') ?></th>
                            <th><?= lang('duration') ?></th>
                            <th><?= lang('service') ?></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/slot_search_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/components/slot_search_modal.js') ?>"></script>

<?php end_section('scripts'); ?>
