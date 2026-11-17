<?php
/**
 * Local variables.
 *
 * @var string $active_menu
 * @var string $company_logo
 */
?>

<nav id="header" class="navbar navbar-expand-md navbar-dark bg-primary p-0">
    <div id="header-logo" class="navbar-brand d-flex align-items-center p-1 lh-1">
        <img src="<?= e(branding_logo()) ?>" alt="<?= e(branding_name()) ?>" class="me-2"
             style="width: 45px; height: 45px;">
        <h6 class="m-0 fw-bold text-white text-truncate" style="font-size: 15px;" title="<?= e(
            branding_name(),
        ) ?>"><?= e(branding_name()) ?></h6>
    </div>

    <!-- On phones: the simple version, the main menu and, on the calendar, the folded toolbar of the calendar
         (backend_layout.js). -->
    <div class="ea-header-compact-actions">
        <?php if (can('view', PRIV_APPOINTMENTS)): ?>
            <a href="<?= site_url('simple') ?>" class="ea-header-simple-button"
               title="<?= e(lang('simple_version')) ?>" aria-label="<?= e(lang('simple_version')) ?>">
                <i class="fa-solid fa-puzzle-piece"></i>
            </a>
        <?php endif; ?>

        <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#header-menu"
                aria-controls="header-menu" aria-expanded="false" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>

        <?php if (vars('calendar_toolbar')): ?>
            <button type="button" class="navbar-toggler" id="toggle-calendar-toolbar"
                    aria-controls="calendar-toolbar" aria-expanded="false"
                    title="<?= e(lang('calendar')) ?>" aria-label="<?= e(lang('calendar')) ?>">
                <i class="fas fa-bars-staggered"></i>
            </button>
        <?php endif; ?>
    </div>

    <div id="header-menu" class="collapse navbar-collapse flex-row-reverse px-2">
        <ul class="navbar-nav">
            <?php if (in_array(session('role_slug'), [DB_SLUG_ADMIN, DB_SLUG_SECRETARY], true)): ?>
                <li class="nav-item text-center <?= $active_menu === 'attendance' ? 'active' : '' ?>">
                    <a href="<?= site_url('attendance') ?>" class="nav-link text-white py-3 px-3"><i class="fas fa-clock me-2"></i><?= lang('att_title') ?></a>
                </li>
            <?php endif; ?>
            <!-- Notifications: only while some WhatsApp message could not be sent, with how many. -->
            <?php if (can('view', PRIV_APPOINTMENTS)) {
                $CI = &get_instance();
                $CI->load->library('whatsapp_reminders');
                $whatsapp_problems = $CI->whatsapp_reminders->problem_count();
            } else {
                $whatsapp_problems = 0;
            } ?>
            <?php if ($whatsapp_problems): ?>
                <li class="nav-item text-center d-flex align-items-center">
                    <a href="<?= site_url('whatsapp_messages?problems=1') ?>"
                       id="header-notifications" class="nav-link text-white py-3 px-3 position-relative"
                       title="<?= e(lang('whatsapp_notifications')) ?>"
                       aria-label="<?= e(lang('whatsapp_notifications')) ?>">
                        <i class="fas fa-bell"></i>
                        <span class="badge rounded-pill bg-danger ea-notifications-badge">
                            <?= $whatsapp_problems > 99 ? '99+' : (int) $whatsapp_problems ?>
                        </span>
                    </a>
                </li>
            <?php endif; ?>

            <?php $hidden = can('view', PRIV_APPOINTMENTS) ? '' : 'd-none'; ?>
            <?php $active = $active_menu == PRIV_APPOINTMENTS ? 'active' : ''; ?>
            <li class="nav-item text-center <?= $active . $hidden ?>" style="min-width: 100px;">
                <a href="<?= site_url(
                    'calendar' . (vars('calendar_view') === CALENDAR_VIEW_TABLE ? '?view=table' : ''),
                ) ?>"
                   class="nav-link text-white fw-light py-3 px-3"
                   data-tippy-content="<?= lang('manage_appointment_record_hint') ?>">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?= lang('calendar') ?>
                </a>
            </li>

            <?php $hidden = can('view', PRIV_CUSTOMERS) ? '' : 'd-none'; ?>
            <?php $active = $active_menu == PRIV_CUSTOMERS ? 'active' : ''; ?>
            <li class="nav-item text-center <?= $active . $hidden ?>" style="min-width: 100px;">
                <a href="<?= site_url('customers') ?>" class="nav-link text-white fw-light py-3 px-3"
                   data-tippy-content="<?= lang('manage_customers_hint') ?>">
                    <i class="fas fa-user-friends me-2"></i>
                    <?= lang('customers') ?>
                </a>
            </li>

            <?php $hidden = can('view', PRIV_SERVICES) ? '' : 'd-none'; ?>
            <?php $active = $active_menu == PRIV_SERVICES ? 'active' : ''; ?>
            <li class="nav-item dropdown text-center <?= $active . $hidden ?>" style="min-width: 100px;">
                <a class="nav-link dropdown-toggle text-white fw-light py-3 px-3" href="#" data-bs-toggle="dropdown"
                   data-tippy-content="<?= lang('manage_services_hint') ?>">
                    <i class="fas fa-business-time me-2"></i>
                    <?= lang('services') ?>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= site_url('services') ?>">
                        <?= lang('services') ?>
                    </a>
                    <a class="dropdown-item" href="<?= site_url('service_categories') ?>">
                        <?= lang('categories') ?>
                    </a>
                    <a class="dropdown-item" href="<?= site_url('pathologies') ?>">
                        <?= lang('pathologies') ?>
                    </a>
                </div>
            </li>

            <?php $hidden = can('view', PRIV_USERS) ? '' : 'd-none'; ?>
            <?php $active = $active_menu == PRIV_USERS ? 'active' : ''; ?>
            <li class="nav-item dropdown text-center <?= $active . $hidden ?>" style="min-width: 100px;">
                <a class="nav-link dropdown-toggle text-white fw-light py-3 px-3" href="#" data-bs-toggle="dropdown"
                   data-tippy-content="<?= lang('manage_users_hint') ?>">
                    <i class="fas fa-users me-2"></i>
                    <?= lang('users') ?>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= site_url('providers') ?>">
                        <?= lang('providers') ?>
                    </a>
                    <a class="dropdown-item" href="<?= site_url('secretaries') ?>">
                        <?= lang('secretaries') ?>
                    </a>
                    <a class="dropdown-item" href="<?= site_url('admins') ?>">
                        <?= lang('admins') ?>
                    </a>
                </div>
            </li>

            <?php $hidden = can('view', PRIV_SYSTEM_SETTINGS) || can('view', PRIV_USER_SETTINGS) ? '' : 'd-none'; ?>
            <?php $active = $active_menu == PRIV_SYSTEM_SETTINGS ? 'active' : ''; ?>
            <li class="nav-item dropdown text-center <?= $active . $hidden ?>" style="min-width: 100px;">
                <a class="nav-link dropdown-toggle text-white fw-light py-3 px-3" href="#" data-bs-toggle="dropdown"
                   data-tippy-content="<?= lang('settings_hint') ?>">
                    <i class="fas fa-user me-2"></i>
                    <?= e(vars('user_display_name')) ?>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if (can('view', PRIV_SYSTEM_SETTINGS)): ?>
                        <a class="dropdown-item" href="<?= site_url('general_settings') ?>">
                            <i class="fas fa-cogs me-2"></i>
                            <?= lang('settings') ?>
                        </a>
                    <?php endif; ?>

                    <a class="dropdown-item" href="<?= site_url('account') ?>">
                        <i class="fas fa-user me-2"></i>
                        <?= lang('account') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php foreach (['catalan' => 'Català', 'spanish' => 'Castellano'] as $code => $name): ?>
                        <a class="dropdown-item ea-change-language <?= config('language') === $code ? 'active' : '' ?>"
                           href="#" data-language="<?= $code ?>">
                            <i class="fas fa-language me-2"></i>
                            <?= $name ?>
                        </a>
                    <?php endforeach; ?>
                    <div class="dropdown-divider"></div>
                    <!-- Hides or shows every amount of money of the pages (backend_layout.js); the eye shows the
                         state and the text says what a click does. -->
                    <button type="button" id="toggle-money" class="dropdown-item" aria-pressed="false"
                            data-label-hide="<?= e(lang('hide_amounts')) ?>"
                            data-label-show="<?= e(lang('show_amounts')) ?>">
                        <span class="ea-money-icon-shown"><i class="fas fa-eye"></i></span>
                        <span class="ea-money-icon-hidden"><i class="fas fa-eye-slash"></i></span>
                        <span class="ea-money-toggle-label"><?= lang('hide_amounts') ?></span>
                    </button>
                    <?php if (can('view', PRIV_APPOINTMENTS)): ?>
                        <!-- The simple agenda for phones (Simple controller). -->
                        <a class="dropdown-item" href="<?= site_url('simple') ?>">
                            <i class="fa-solid fa-puzzle-piece me-2"></i>
                            <?= lang('simple_version') ?>
                        </a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?= site_url('logout') ?>">
                        <i class="fas fa-sign-out me-2"></i>
                        <?= lang('log_out') ?>
                    </a>
                </div>
            </li>
        </ul>
    </div>
</nav>

<div id="notification" style="display: none;"></div>

<div id="loading" class="position-fixed top-0 start-0 w-100 h-100" style="display: none; z-index: 999999; background: rgba(255, 255, 255, 0.75);">
    <div class="any-element animation is-loading d-block mx-auto">
        &nbsp;
    </div>
</div>
