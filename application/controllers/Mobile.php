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
 * Mobile controller.
 *
 * A very simple version of the calendar for phones: the appointments of a day, one below the other, with the patient's
 * phone at hand. It only reads: the appointments come from the calendar (get_calendar_appointments_for_table_view),
 * which already limits them to what each role may see. Phones are sent here from the calendar (see is_phone_user_agent),
 * and "Full version" goes back to the calendar and is remembered by the browser (MOBILE_FULL_VERSION_COOKIE).
 *
 * @package Controllers
 */
class Mobile extends EA_Controller
{
    /**
     * Mobile constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('providers_model');
        $this->load->model('secretaries_model');
    }

    /**
     * Render the mobile agenda.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('mobile')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_APPOINTMENTS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        // Coming here on purpose brings the phone back to the mobile version.
        if (isset($_COOKIE[MOBILE_FULL_VERSION_COOKIE])) {
            setcookie(MOBILE_FULL_VERSION_COOKIE, '', cookie_options(time() - 3600));
        }

        $role_slug = session('role_slug');

        $providers = $this->providers_model->get_available_providers();

        if ($role_slug === DB_SLUG_PROVIDER) {
            $providers = array_filter($providers, fn(array $provider): bool => (int) $provider['id'] === (int) $user_id);
        }

        if ($role_slug === DB_SLUG_SECRETARY) {
            $secretary_providers = $this->secretaries_model->find($user_id)['providers'];

            $providers = array_filter(
                $providers,
                fn(array $provider): bool => in_array($provider['id'], $secretary_providers),
            );
        }

        $providers = array_map(
            fn(array $provider): array => [
                'id' => (int) $provider['id'],
                'name' => trim($provider['first_name'] . ' ' . $provider['last_name']),
            ],
            array_values($providers),
        );

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'time_format' => setting('time_format'),
            'first_weekday' => setting('first_weekday'),
            'providers' => $providers,
            'appointment_statuses' => appointment_statuses(),
            // To separate the morning from the afternoon at the midday break.
            'company_working_plan' => setting('company_working_plan'),
        ]);

        html_vars([
            'page_title' => lang('calendar'),
        ]);

        $this->load->view('pages/mobile');
    }
}
