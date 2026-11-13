<?php extend('layouts/message_layout'); ?>

<?php section('content'); ?>

<div>
    <img id="message-icon" class="mt-0 mb-5" src="<?= vars('message_icon') ?>" alt="warning">
</div>

<div class="mb-5">
    <h4 class="mb-5"><?= vars('message_title') ?></h4>

    <p><?= pure_html(vars('message_text')) ?></p>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>


<?php end_section('scripts'); ?>
