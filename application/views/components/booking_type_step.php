<?php
/**
 * Local variables.
 *
 * @var array $available_services
 */
?>

<div id="wizard-frame-1" class="wizard-frame p-3 p-md-4" style="visibility: hidden;">
    <div class="frame-container py-3" style="min-height: 500px;">
        <h2 class="frame-title fw-light text-center mb-4 text-muted mt-md-5"><?= lang('service_and_provider') ?></h2>

        <div class="row frame-content">
            <div class="col col-lg-8 offset-md-2">
                <div class="mb-3">
                    <label for="select-service" class="fs-5 mb-2">
                        <strong><?= lang('service') ?></strong>
                    </label>

                    <select id="select-service" class="form-select mb-4">
                        <option value="">
                            <?= lang('please_select') ?>
                        </option>
                        <?php
                        foreach ($available_services as $service) {
                            echo '<option value="' . $service['id'] . '">' . e($service['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3" hidden>
                    <label for="select-provider" class="fs-5 mb-2">
                        <strong><?= lang('provider') ?></strong>
                    </label>

                    <select id="select-provider" class="form-select mb-4">
                        <option value="">
                            <?= lang('please_select') ?>
                        </option>
                    </select>
                </div>

                <div id="service-description" class="small overflow-auto shadow-none" style="max-height: 153px;">
                    <!-- JS -->
                </div>

            </div>
        </div>
    </div>

    <div class="command-buttons text-center my-3 mx-auto d-md-flex justify-content-md-between">
        <span>&nbsp;</span>

        <button type="button" id="button-next-1" class="btn button-next btn-dark" style="min-width: 120px; margin-right: 10px;"
                data-step_index="1">
            <?= lang('next') ?>
            <i class="fas fa-chevron-right ms-2"></i>
        </button>
    </div>
</div>
