<?php
/**
 * Local variables.
 *
 * @var array $available_services
 * @var array $appointment_statuses
 * @var array $timezones
 * @var array $require_first_name
 * @var array $require_last_name
 * @var array $require_email
 * @var array $require_phone_number
 * @var array $require_address
 * @var array $require_city
 * @var array $require_zip_code
 * @var array $require_notes
 */
?>
<div id="appointments-modal" class="modal fade">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-lg-down modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?= lang('edit_appointment_title') ?></h3>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="modal-message alert d-none"></div>

                <form>
                    <fieldset>
                        <h5 class="mb-3 fw-light"><?= lang('appointment_details_title') ?></h5>

                        <input id="appointment-id" type="hidden">

                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="mb-3">
                                    <label for="select-service" class="form-label">
                                        <?= lang('service') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select id="select-service" class="required form-select">
                                        <?php
                                        // Inactive services stay hidden for new appointments, but must exist as
                                        // options so that appointments already using them can still be saved.
                                        $service_option = function (array $service): string {
                                            $label = e($service['name']);
                                            $hidden = '';

                                            if (!$service['is_active']) {
                                                $label .= ' (' . lang('inactive') . ')';
                                                $hidden = ' hidden';
                                            }

                                            return '<option value="' .
                                                $service['id'] .
                                                '"' .
                                                $hidden .
                                                '>' .
                                                $label .
                                                '</option>';
                                        };

                                        // Group services by category, only if there is at least one service
                                        // with a parent category.
                                        $has_category = false;

                                        foreach ($available_services as $service) {
                                            if (!empty($service['service_category_id'])) {
                                                $has_category = true;
                                                break;
                                            }
                                        }

                                        if ($has_category) {
                                            $grouped_services = [];

                                            foreach ($available_services as $service) {
                                                if (!empty($service['service_category_id'])) {
                                                    if (!isset($grouped_services[$service['service_category_name']])) {
                                                        $grouped_services[$service['service_category_name']] = [];
                                                    }

                                                    $grouped_services[$service['service_category_name']][] = $service;
                                                }
                                            }

                                            // We need the uncategorized services at the end of the list, so we will use
                                            // another iteration only for the uncategorized services.
                                            $grouped_services['uncategorized'] = [];

                                            foreach ($available_services as $service) {
                                                if ($service['service_category_id'] == null) {
                                                    $grouped_services['uncategorized'][] = $service;
                                                }
                                            }

                                            foreach ($grouped_services as $key => $group) {
                                                $group_label =
                                                    $key !== 'uncategorized'
                                                        ? e($group[0]['service_category_name'])
                                                        : 'Uncategorized';

                                                if (count($group) > 0) {
                                                    echo '<optgroup label="' . $group_label . '">';

                                                    foreach ($group as $service) {
                                                        echo $service_option($service);
                                                    }

                                                    echo '</optgroup>';
                                                }
                                            }
                                        } else {
                                            foreach ($available_services as $service) {
                                                echo $service_option($service);
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="select-provider" class="form-label">
                                        <?= lang('provider') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select id="select-provider" class="required form-select"></select>
                                    <div id="usual-provider-hint" class="form-text" hidden></div>
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-status" class="form-label">
                                        <?= lang('status') ?>
                                    </label>
                                    <select id="appointment-status" class="form-select">
                                        <?php foreach ($appointment_statuses as $appointment_status): ?>
                                            <option value="<?= e($appointment_status['code']) ?>">
                                                <?= e($appointment_status['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-first-visit" class="form-label">
                                        <?= lang('first_visit') ?>
                                    </label>
                                    <select id="appointment-first-visit" class="form-select">
                                        <option value=""><?= lang('not_specified') ?></option>
                                        <option value="1"><?= lang('yes') ?></option>
                                        <option value="0"><?= lang('no') ?></option>
                                    </select>
                                </div>

                                <div class="mb-3 ea-more-field">
                                    <?php component('color_selection', ['attributes' => 'id="appointment-color"']); ?>
                                </div>

                                <div class="mb-3 ea-more-field">
                                    <label for="appointment-location" class="form-label">
                                        <?= lang('location') ?>
                                    </label>
                                    <input id="appointment-location" class="form-control">
                                </div>

                                <div class="mb-3 ea-more-field">
                                    <label for="appointment-meeting-link" class="form-label">
                                        <?= lang('meeting_link') ?>
                                    </label>
                                    <input id="appointment-meeting-link" class="form-control" placeholder="https://">
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <div class="mb-3">
                                    <label for="start-datetime"
                                           class="form-label"><?= lang('start_date_time') ?></label>
                                    <input id="start-datetime" class="required form-control">
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-duration" class="form-label">
                                        <?= lang('duration_minutes') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input id="appointment-duration" class="required form-control" type="number" min="5"
                                           max="1440" step="1">
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-price" class="form-label">
                                        <?= lang('appointment_price') ?>
                                    </label>
                                    <input id="appointment-price" class="form-control" type="number" min="0"
                                           step="0.01" inputmode="decimal">
                                </div>

                                <div class="mb-3">
                                    <label for="end-datetime" class="form-label"><?= lang('end_date_time') ?></label>
                                    <input id="end-datetime" class="required form-control">
                                </div>

                                <div class="mb-3 ea-more-field">
                                    <label class="form-label">
                                        <?= lang('timezone') ?>
                                    </label>

                                    <div
                                        class="border rounded d-flex justify-content-between align-items-center bg-light timezone-info">
                                        <div class="border-end w-50 p-1 text-center">
                                            <small>
                                                <?= lang('provider') ?>:
                                                <span class="provider-timezone">
                                                    -
                                                </span>
                                            </small>
                                        </div>
                                        <div class="w-50 p-1 text-center">
                                            <small>
                                                <?= lang('current_user') ?>:
                                                <span>
                                                    <?= $timezones[session('timezone', 'UTC')] ?>
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-notes" class="form-label">
                                        <?= lang('appointment_comment') ?>
                                        <?php if ($require_notes): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <textarea id="appointment-notes" class="<?= $require_notes
                                        ? 'required'
                                        : '' ?> form-control" rows="3"></textarea>
                                </div>

                            </div>
                        </div>
                    </fieldset>

                    <div class="mb-3">
                        <button id="toggle-more-fields" class="btn btn-link btn-sm px-0" type="button"
                                aria-expanded="false">
                            <i class="fas fa-chevron-down me-1"></i>
                            <span><?= lang('more_details') ?></span>
                        </button>
                    </div>

                    <fieldset>
                        <h5 class="mb-3 fw-light">
                            <?= lang('customer_details_title') ?>
                            <button id="new-customer" class="btn btn-outline-secondary btn-sm" type="button"
                                    data-tippy-content="<?= lang('clear_fields_add_existing_customer_hint') ?>">
                                <i class="fas fa-plus-square me-2"></i>
                                <?= lang('new') ?>
                            </button>
                        </h5>

                        <p class="form-text mt-n2 mb-3">
                            <i class="fas fa-search me-1"></i>
                            <?= lang('patient_search_hint') ?>
                        </p>

                        <div id="no-show-warning" class="alert alert-warning py-2" role="alert" hidden></div>

                        <input id="customer-id" type="hidden">

                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="mb-3">
                                    <label for="first-name" class="form-label">
                                        <?= lang('first_name') ?>
                                        <?php if ($require_first_name): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="first-name"
                                           class="<?= $require_first_name ? 'required' : '' ?> form-control"
                                           maxlength="100"/>
                                </div>

                                <div class="mb-3">
                                    <label for="last-name" class="form-label">
                                        <?= lang('last_name') ?>
                                        <?php if ($require_last_name): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="last-name"
                                           class="<?= $require_last_name ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <?= lang('email') ?>
                                        <?php if ($require_email): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="email"
                                           class="<?= $require_email ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3">
                                    <label for="phone-number" class="form-label">
                                        <?= lang('phone_number') ?>
                                        <?php if ($require_phone_number): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="phone-number" maxlength="60"
                                           class="<?= $require_phone_number ? 'required' : '' ?> form-control"/>
                                </div>

                                <div class="mb-3 ea-more-field">
                                    <label class="form-label" for="language">
                                        <?= lang('language') ?>
                                        <span class="text-danger" hidden>*</span>
                                    </label>
                                    <select id="language" class="form-select required">
                                        <?php foreach (vars('available_languages') as $available_language): ?>
                                            <option value="<?= $available_language ?>">
                                                <?= ucfirst($available_language) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="ea-more-field">
                                    <?php component('custom_fields'); ?>
                                </div>

                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="mb-3 <?= $require_address ? '' : 'ea-more-field' ?>">
                                    <label for="address" class="form-label">
                                        <?= lang('address') ?>
                                        <?php if ($require_address): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="address"
                                           class="<?= $require_address ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3 <?= $require_city ? '' : 'ea-more-field' ?>">
                                    <label for="city" class="form-label">
                                        <?= lang('city') ?>
                                        <?php if ($require_city): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="city"
                                           class="<?= $require_city ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3 <?= $require_zip_code ? '' : 'ea-more-field' ?>">
                                    <label for="zip-code" class="form-label">
                                        <?= lang('zip_code') ?>
                                        <?php if ($require_zip_code): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="zip-code"
                                           class="<?= $require_zip_code ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3 ea-more-field">
                                    <label class="form-label" for="timezone">
                                        <?= lang('timezone') ?>
                                        <span class="text-danger" hidden>*</span>
                                    </label>
                                    <?php component('timezone_dropdown', [
                                        'attributes' => 'id="timezone" class="form-select required"',
                                        'grouped_timezones' => vars('grouped_timezones'),
                                    ]); ?>
                                </div>

                                <div class="mb-3">
                                    <label for="customer-notes" class="form-label">
                                        <?= lang('administrative_notes') ?>
                                    </label>
                                    <textarea id="customer-notes" rows="3" class="form-control"></textarea>
                                </div>

                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <div class="modal-footer">

                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= lang('close') ?>
                </button>
                <button id="save-appointment" class="btn btn-primary">
                    <i class="fas fa-check-square me-2"></i>
                    <?= lang('save') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/components/patient_search.js') ?>"></script>
<script src="<?= asset_url('assets/js/components/patient_duplicates.js') ?>"></script>
<script src="<?= asset_url('assets/js/components/appointments_modal.js') ?>"></script>

<?php end_section('scripts'); ?>
