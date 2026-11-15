<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * WhatsApp settings controller.
 *
 * The settings of the WhatsApp reminders (Integrations): the details of the Meta app and number, the approved template,
 * the webhook to register in Meta, a test message, and the panel of the reminders with their status.
 *
 * @package Controllers
 */
class Whatsapp_settings extends EA_Controller
{
    /**
     * Settings that the page edits. The access token and the app secret are never sent back to the page: an empty
     * value keeps the saved one.
     */
    private const FIELDS = [
        'whatsapp_enabled',
        'whatsapp_phone_number_id',
        'whatsapp_template_name',
        'whatsapp_template_language',
    ];

    private const SECRET_FIELDS = ['whatsapp_access_token', 'whatsapp_app_secret'];

    /**
     * Whatsapp_settings constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('whatsapp_reminders');

        if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
            if (session('user_id')) {
                abort(403, 'Forbidden');
            }

            redirect('login');
        }
    }

    /**
     * Render the settings page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('whatsapp_settings')]);

        $user_id = session('user_id');

        $settings = [];

        foreach (self::FIELDS as $field) {
            $settings[] = ['name' => $field, 'value' => setting($field, '')];
        }

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
            'whatsapp_settings' => $settings,
        ]);

        html_vars([
            'page_title' => lang('whatsapp_reminders'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'webhook_url' => site_url('whatsapp_webhook'),
            'webhook_verify_token' => setting('whatsapp_webhook_verify_token', ''),
            'has_access_token' => (bool) setting('whatsapp_access_token'),
            'has_app_secret' => (bool) setting('whatsapp_app_secret'),
            'overview' => $this->whatsapp_reminders->overview(20),
            'message_price' => Whatsapp_reminders::MESSAGE_PRICE,
            'template_body' => Whatsapp_reminders::TEMPLATE_BODY,
        ]);

        $this->load->view('pages/whatsapp_settings');
    }

    /**
     * Save the settings.
     */
    public function save(): void
    {
        try {
            method('post');

            check('whatsapp_settings', 'array|null');

            foreach (request('whatsapp_settings', []) as $item) {
                $name = (string) ($item['name'] ?? '');
                $value = trim((string) ($item['value'] ?? ''));

                if (in_array($name, self::FIELDS, true)) {
                    setting([$name => $value]);
                } elseif (in_array($name, self::SECRET_FIELDS, true) && $value !== '') {
                    setting([$name => $value]);
                }
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Send the template to a number, to try the settings that are saved.
     */
    public function test(): void
    {
        try {
            method('post');

            check('phone', 'string');

            if (!setting('whatsapp_phone_number_id') || !setting('whatsapp_access_token')) {
                throw new InvalidArgumentException(lang('whatsapp_not_configured'));
            }

            $result = $this->whatsapp_reminders->send_test((string) request('phone'));

            if (!$result['ok']) {
                throw new InvalidArgumentException(lang('whatsapp_test_failed') . ' ' . $result['error']);
            }

            json_response(['success' => true, 'message_id' => $result['message_id']]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
