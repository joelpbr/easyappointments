<?php
$overview_groups = require APPPATH . 'config/settings_navigation.php';
$overview_admin = session('role_slug') === DB_SLUG_ADMIN;
?>
<section id="settings-overview" class="mb-5" aria-labelledby="settings-overview-title">
    <h4 id="settings-overview-title" class="fw-light pt-3"><?= lang('settings_overview') ?></h4>
    <p class="text-muted mb-4"><?= lang('settings_overview_hint') ?></p>
    <div class="row g-3">
        <?php foreach ($overview_groups as $overview_group): ?>
            <?php
            $overview_items = array_filter($overview_group['items'], fn($item) => empty($item[2]) || $overview_admin);
            if (!$overview_items) {
                continue;
            }
            ?>
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="d-flex align-items-center gap-2">
                            <i class="fas <?= $overview_group['icon'] ?> text-primary" aria-hidden="true"></i>
                            <?= lang($overview_group['label']) ?>
                            <span class="badge bg-secondary rounded-pill ms-auto"><?= count($overview_items) ?></span>
                        </h5>
                        <p class="small text-muted"><?= lang($overview_group['description']) ?></p>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($overview_items as [$route, $label]): ?>
                                <li>
                                    <a class="d-flex align-items-center justify-content-between gap-2 py-2 border-top text-decoration-none"
                                       href="<?=
                                       site_url($route) . ($route === 'general_settings' ? '#settings-company' : '')
                                       ?>">
                                        <?= lang($label) ?>
                                        <i class="fas fa-angle-right" aria-hidden="true"></i>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
