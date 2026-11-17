<?php
/**
 * Employment relationship of a staff member: employees clock in (registro de jornada), self-employed professionals and
 * partners do not.
 */
?>
<div class="mb-3">
    <label class="form-label" for="employment"><?= lang('employment') ?></label>
    <select id="employment" class="form-select" aria-describedby="employment-hint" disabled>
        <option value=""><?= lang('employment_undefined') ?></option>
        <option value="employee"><?= lang('employment_employee') ?></option>
        <option value="self_employed"><?= lang('employment_self_employed') ?></option>
    </select>
    <div id="employment-hint" class="form-text"><?= lang('employment_hint') ?></div>
</div>
