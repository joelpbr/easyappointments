<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Booking settings controller.
 *
 * Handles booking settings related operations.
 *
 * @package Controllers
 */
class Booking_settings extends EA_Controller
{
    public array $allowed_setting_fields = ['id', 'name', 'value'];

    public array $optional_setting_fields = [
        //
    ];

    /**
     * Booking_settings constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('providers_model');
        $this->load->model('roles_model');
        $this->load->model('settings_model');

        $this->load->library('accounts');
        $this->load->library('google_sync');
        $this->load->library('notifications');
        $this->load->library('synchronization');
        $this->load->library('timezones');
    }

    /**
     * Render the settings page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('booking_settings')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_SYSTEM_SETTINGS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'booking_settings' => $this->settings_model->get_for_page([
                'display_first_name',
                'require_first_name',
                'display_last_name',
                'require_last_name',
                'display_email',
                'require_email',
                'display_phone_number',
                'require_phone_number',
                'display_address',
                'require_address',
                'display_city',
                'require_city',
                'display_zip_code',
                'require_zip_code',
                'display_notes',
                'require_notes',
                'customer_notifications',
                'limit_customer_access',
                'require_captcha',
                'display_any_provider',
                'display_login_button',
                'display_delete_personal_information',
                'disable_booking',
                'disable_booking_message',
            ]),
        ]);

        html_vars([
            'page_title' => lang('settings'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
        ]);

        $this->load->view('pages/booking_settings');
    }

    /**
     * Save booking settings.
     */
    public function save(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('booking_settings', 'array|null');

            $rich_text_settings = ['disable_booking_message'];

            $settings = request('booking_settings', []);

            foreach ($settings as $setting) {
                $existing_setting = $this->settings_model->query()->where('name', $setting['name'])->get()->row_array();

                if (!empty($existing_setting)) {
                    $setting['id'] = $existing_setting['id'];
                }

                if (!empty($setting['name']) && in_array($setting['name'], $rich_text_settings, true)) {
                    $setting['value'] = pure_html($setting['value'] ?? '');
                }

                $this->settings_model->only($setting, $this->allowed_setting_fields);

                $this->settings_model->optional($setting, $this->optional_setting_fields);

                $this->settings_model->save($setting);
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
