<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * Simple controller.
 *
 * A simple version of the calendar: the appointments of a day, one below the other, with the patient's
 * phone at hand. It only reads: the appointments come from the calendar (get_calendar_appointments_for_table_view),
 * which already limits them to what each role may see. Phones are sent here from the calendar (see is_phone_user_agent),
 * and "Full version" goes back to the calendar and is remembered by the browser (FULL_CALENDAR_COOKIE).
 *
 * @package Controllers
 */
class Simple extends EA_Controller
{
    /**
     * Simple constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('providers_model');
        $this->load->model('secretaries_model');
        $this->load->library('clinical_record_templates');
    }

    /** Keep bookmarks for the former URL working, while making /simple canonical. */
    public function legacy_redirect(): void
    {
        method('get');
        redirect('simple', 'location', 301);
    }

    /**
     * Render the simple agenda.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('simple')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_APPOINTMENTS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        // Coming here on purpose brings the phone back to the simple version.
        if (isset($_COOKIE[FULL_CALENDAR_COOKIE])) {
            setcookie(FULL_CALENDAR_COOKIE, '', cookie_options(time() - 3600));
        }

        $role_slug = session('role_slug');

        $providers = $this->providers_model->get_available_providers();

        if ($role_slug === DB_SLUG_PROVIDER) {
            $providers = array_filter(
                $providers,
                fn(array $provider): bool => (int) $provider['id'] === (int) $user_id,
            );
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
                'name' => provider_display_name($provider),
            ],
            array_values($providers),
        );

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'clinical_records' => Clinical_record_templates::can_access($role_slug),
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'first_weekday' => setting('first_weekday'),
            'providers' => $providers,
            'appointment_statuses' => appointment_statuses(),
            // To separate the morning from the afternoon at the midday break.
            'company_working_plan' => setting('company_working_plan'),
        ]);

        html_vars([
            'page_title' => lang('calendar'),
            'clinical_records' => Clinical_record_templates::can_access($role_slug),
        ]);

        $this->load->view('pages/simple');
    }
}
