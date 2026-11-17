<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * General settings controller.
 *
 * Handles general settings related operations.
 *
 * @package Controllers
 */
class General_settings extends EA_Controller
{
    /**
     * Calendar constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('settings_model');
        $this->load->model('providers_model');

        $this->load->library('accounts');
        $this->load->library('timezones');
    }

    /**
     * Render the settings page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('general_settings')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_SYSTEM_SETTINGS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        $available_theme_files = glob(__DIR__ . '/../../assets/css/themes/*.min.css');

        $available_themes = array_map(function ($available_theme_file) {
            return str_replace('.min.css', '', basename($available_theme_file));
        }, $available_theme_files);

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'timezones' => $this->timezones->to_array(),
            'general_settings' => filter_sensitive_settings($this->settings_model->get()),
            'calendar_providers' => $this->calendar_providers(),
        ]);

        html_vars([
            'page_title' => lang('settings'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'grouped_timezones' => $this->timezones->to_grouped_array(),
            'available_themes' => $available_themes,
        ]);

        $this->load->view('pages/general_settings');
    }

    /**
     * Providers currently displayed in the calendar, in their saved order.
     */
    private function calendar_providers(): array
    {
        return array_map(
            fn($provider) => [
                'id' => (int) $provider['id'],
                'name' => provider_display_name($provider),
            ],
            $this->providers_model->get_available_providers(),
        );
    }

    public function save_calendar_order(): void
    {
        method('post');
        if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
            abort(403, 'Forbidden');
        }
        try {
            check('provider_ids', 'array');
            $ids = request('provider_ids');
            foreach ($ids as $id) {
                if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
                    throw new InvalidArgumentException(lang('provider_order_changed'));
                }
            }
            $ids = array_map('intval', $ids);
            $expected = array_column($this->calendar_providers(), 'id');
            $sorted = $ids;
            sort($sorted);
            sort($expected);
            if ($sorted !== $expected) {
                throw new InvalidArgumentException(lang('provider_order_changed'));
            }
            $this->db->trans_begin();
            foreach ($ids as $index => $id) {
                $before = $this->db->get_where('users', ['id' => $id])->row_array();
                $this->db->update('users', ['calendar_order' => $index + 1], ['id' => $id]);
                audit_log(
                    'updated',
                    'provider',
                    $id,
                    audit_changes($before, ['calendar_order' => $index + 1], ['calendar_order']),
                );
            }
            if (!$this->db->trans_status()) {
                throw new RuntimeException(lang('provider_order_failed'));
            }
            $this->db->trans_commit();
            json_response(['success' => true]);
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            json_exception($e);
        }
    }

    private array $allowed_settings = [
        'company_name',
        'company_email',
        'company_link',
        'company_address',
        'company_phone',
        'company_logo',
        'company_color',
        'company_accent_color',
        'company_working_plan',
        'book_advance_timeout',
        'default_timezone',
        'default_language',
        'theme',
        'header_logo_target',
        'date_format',
        'time_format',
        'first_weekday',
        'require_phone_number',
        'display_cookie_notice',
        'cookie_notice_content',
        'display_terms_and_conditions',
        'terms_and_conditions_content',
        'display_privacy_policy',
        'privacy_policy_content',
    ];

    /**
     * Save general settings.
     */
    public function save(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('general_settings', 'array|null');

            $settings = request('general_settings', []);

            // Validate settings is an array
            if (!is_array($settings)) {
                throw new InvalidArgumentException('Invalid settings data format.');
            }

            foreach ($settings as $setting) {
                if (
                    !is_array($setting) ||
                    !is_string($setting['name'] ?? null) ||
                    !is_scalar($setting['value'] ?? null)
                ) {
                    throw new InvalidArgumentException('Invalid settings data format.');
                }
                if (
                    in_array($setting['name'] ?? '', ['company_color', 'company_accent_color'], true) &&
                    !preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $setting['value'] ?? '')
                ) {
                    throw new InvalidArgumentException(lang('branding_invalid_color'));
                }
                if (
                    ($setting['name'] ?? '') === 'header_logo_target' &&
                    !array_key_exists((string) $setting['value'], branding_header_targets())
                ) {
                    throw new InvalidArgumentException('Invalid page for the header logo.');
                }
                if (
                    ($setting['name'] ?? '') === 'company_logo' &&
                    !branding_valid_logo((string) ($setting['value'] ?? ''))
                ) {
                    throw new InvalidArgumentException(lang('branding_logo_hint'));
                }
            }

            foreach ($settings as $setting) {
                // Validate each setting has required fields
                if (!isset($setting['name']) || !is_string($setting['name'])) {
                    continue;
                }

                // Only allow whitelisted settings to be modified
                if (!in_array($setting['name'], $this->allowed_settings, true)) {
                    log_message('error', 'Attempt to modify unauthorized setting: ' . $setting['name']);
                    continue;
                }

                $existing_setting = $this->settings_model->query()->where('name', $setting['name'])->get()->row_array();

                if (!empty($existing_setting)) {
                    $setting['id'] = $existing_setting['id'];
                }

                $this->settings_model->save($setting);
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
