<?php
$settings_groups = require APPPATH . 'config/settings_navigation.php';
$current_settings_route = get_instance()->uri->segment(1);
$is_settings_admin = session('role_slug') === DB_SLUG_ADMIN;
?>
<nav id="settings-nav" class="mb-4 pe-sm-3" aria-label="<?= lang('settings') ?>">
    <h4 class="py-3 mb-2 fw-light"><?= lang('settings') ?></h4>
    <a class="btn btn-outline-primary w-100 mb-4" href="<?= site_url('general_settings') ?>#settings-overview">
        <i class="fas fa-th-large me-2" aria-hidden="true"></i><?= lang('settings_overview') ?>
    </a>
    <?php foreach ($settings_groups as $settings_group): ?>
        <?php
        $settings_items = array_filter($settings_group['items'], fn($item) => empty($item[2]) || $is_settings_admin);
        if (!$settings_items) {
            continue;
        }
        ?>
        <h5 class="small fw-bold text-muted mb-2"><?= lang($settings_group['label']) ?></h5>
        <ul class="nav nav-pills flex-column mb-4 gap-1">
            <?php foreach ($settings_items as [$route, $label]): ?>
                <?php $is_current = $current_settings_route === $route; ?>
                <li class="nav-item">
                    <a class="nav-link py-2 px-3<?= $is_current ? ' active' : '' ?>"
                       href="<?=
                       site_url($route) . ($route === 'general_settings' ? '#settings-company' : '')
                       ?>"
                       <?= $is_current ? 'aria-current="page"' : '' ?>>
                        <?= lang($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</nav>
