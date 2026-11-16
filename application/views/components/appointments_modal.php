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
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-lg-down modal-xl">
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
                                            // Mix 65% white into the service color for readable black labels.
                                            $color = (string) ($service['color'] ?? '');
                                            if (!preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $color)) {
                                                $color = '#ffffff';
                                            }
                                            $hex = substr($color, 1);
                                            if (strlen($hex) === 3) {
                                                $hex = implode(
                                                    '',
                                                    array_map(fn($digit) => $digit . $digit, str_split($hex)),
                                                );
                                            }
                                            $background = vsprintf(
                                                '#%02x%02x%02x',
                                                array_map(
                                                    fn($channel) => (int) round(165.75 + hexdec($channel) * 0.35),
                                                    str_split($hex, 2),
                                                ),
                                            );

                                            if (!$service['is_active']) {
                                                $label .= ' (' . lang('inactive') . ')';
                                                $hidden = ' hidden';
                                            }

                                            return '<option value="' .
                                                $service['id'] .
                                                '" style="background-color:' .
                                                $background .
                                                ';color:#111111;"' .
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

                                <div id="appointment-status-group" class="mb-3">
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

                                <div class="mb-3 ea-more-field">
                                    <?php component('color_selection', ['attributes' => 'id="appointment-color"']); ?>
                                </div>

                                <div class="mb-3 ea-more-field">
                                    <label for="appointment-location" class="form-label">
                                        <?= lang('location') ?>
                                    </label>
                                    <input id="appointment-location" class="form-control">
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="mb-3 ea-appointment-timing">
                                    <div>
                                        <label for="start-datetime" class="form-label"><?= lang('start') ?></label>
                                        <input id="start-datetime" class="required form-control"
                                               aria-label="<?= e(lang('start_date_time')) ?>">
                                    </div>
                                    <div>
                                        <label for="end-datetime" class="form-label"><?= lang('end') ?></label>
                                        <input id="end-datetime" class="required form-control"
                                               aria-label="<?= e(lang('end_date_time')) ?>">
                                    </div>
                                    <div class="ea-appointment-minutes">
                                        <label for="appointment-duration" class="form-label">
                                            <?= ucfirst(lang('minutes')) ?>
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input id="appointment-duration" class="required form-control" type="number" min="5"
                                               max="1440" step="5" aria-label="<?= e(lang('duration_minutes')) ?>">
                                    </div>
                                </div>

                                <div class="mb-3 ea-money">
                                    <label for="appointment-price" class="form-label">
                                        <?= lang('appointment_price') ?>
                                    </label>
                                    <input id="appointment-price" class="form-control" type="number" min="0"
                                           step="0.01" inputmode="decimal">
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
                                        : '' ?> form-control" rows="2"></textarea>
                                </div>

                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <h5 class="mb-2 fw-light">
                            <?= lang('customer_details_title') ?>
                        </h5>

                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <div class="btn-group btn-group-sm" role="group"
                                 aria-label="<?= lang('customer_details_title') ?>">
                                <input type="radio" class="btn-check" name="patient-mode" id="patient-mode-existing"
                                       value="existing" autocomplete="off" checked>
                                <label class="btn btn-outline-primary" for="patient-mode-existing">
                                    <?= lang('existing_patient') ?>
                                </label>
                                <input type="radio" class="btn-check" name="patient-mode" id="patient-mode-new"
                                       value="new" autocomplete="off">
                                <label class="btn btn-outline-primary" for="patient-mode-new">
                                    <?= lang('new_patient') ?>
                                </label>
                            </div>

                            <div id="chosen-patient-actions" hidden>
                                <button id="unlock-patient" type="button" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-lock me-1"></i>
                                    <span><?= lang('edit_patient_data') ?></span>
                                </button>
                                <button id="change-patient" type="button" class="btn btn-sm btn-link">
                                    <?= lang('change_patient') ?>
                                </button>
                            </div>
                        </div>

                        <div id="no-show-warning" class="alert alert-warning py-2" role="alert" hidden></div>

                        <input id="customer-id" type="hidden">

                        <div class="row">
                            <div class="col-12">
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
                                    <label for="phone-number" class="form-label">
                                        <?= lang('phone_number') ?>
                                        <?php if ($require_phone_number): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="phone-number" maxlength="60"
                                           class="<?= $require_phone_number ? 'required' : '' ?> form-control"/>
                                </div>

                                <div class="mb-3">
                                    <label for="customer-notes" class="form-label">
                                        <?= lang('administrative_notes') ?>
                                    </label>
                                    <textarea id="customer-notes" rows="2" class="form-control"></textarea>
                                </div>

                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <div class="modal-footer">
                <!-- WhatsApp reminder, on by default (when it goes out: its settings); under it, for an appointment
                     that exists, when it will go out or how it went (appointments_modal.js). -->
                <div class="ea-whatsapp-reminder-group me-auto">
                    <label class="ea-whatsapp-reminder form-check"
                           for="send-whatsapp-reminder" title="<?= e(lang('send_whatsapp_reminder')) ?>">
                        <input class="form-check-input" type="checkbox" id="send-whatsapp-reminder"
                               aria-label="<?= e(lang('send_whatsapp_reminder')) ?>" checked>
                        <span><?= lang('send_whatsapp_reminder') ?></span>
                    </label>
                    <div id="whatsapp-reminder-status" class="ea-whatsapp-reminder-status" hidden></div>
                </div>

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
