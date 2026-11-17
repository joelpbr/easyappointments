<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

/**
 * ProgramaConsulta controller.
 *
 * @property EA_Benchmark $benchmark
 * @property EA_Cache $cache
 * @property EA_Calendar $calendar
 * @property EA_Config $config
 * @property EA_DB_forge $dbforge
 * @property EA_DB_query_builder $db
 * @property EA_DB_utility $dbutil
 * @property EA_Email $email
 * @property EA_Encrypt $encrypt
 * @property EA_Encryption $encryption
 * @property EA_Exceptions $exceptions
 * @property EA_Hooks $hooks
 * @property EA_Input $input
 * @property EA_Lang $lang
 * @property EA_Loader $load
 * @property EA_Log $log
 * @property EA_Migration $migration
 * @property EA_Output $output
 * @property EA_Profiler $profiler
 * @property EA_Router $router
 * @property EA_Security $security
 * @property EA_Session $session
 * @property EA_Upload $upload
 * @property EA_URI $uri
 *
 * @property Admins_model $admins_model
 * @property Appointments_model $appointments_model
 * @property Consents_model $consents_model
 * @property Customers_model $customers_model
 * @property Providers_model $providers_model
 * @property Roles_model $roles_model
 * @property Secretaries_model $secretaries_model
 * @property Services_model $services_model
 * @property Settings_model $settings_model
 * @property Unavailabilities_model $unavailabilities_model
 * @property Users_model $users_model
 * @property Webhooks_model $webhooks_model
 * @property Blocked_periods_model $blocked_periods_model
 * @property Working_plan_exceptions_model $working_plan_exceptions_model
 *
 * @property Accounts $accounts
 * @property Api $api
 * @property Cleanup $cleanup
 * @property Availability $availability
 * @property Email_messages $email_messages
 * @property Google_Sync $google_sync
 * @property Caldav_Sync $caldav_sync
 * @property Ics_file $ics_file
 * @property Instance $instance
 * @property Notifications $notifications
 * @property Permissions $permissions
 * @property Synchronization $synchronization
 * @property Timezones $timezones
 * @property Webhooks_client $webhooks_client
 */
class EA_Controller extends CI_Controller
{
    /**
     * EA_Controller constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('accounts');

        $this->check_storage_writable();
        $this->check_pending_migrations();
        $this->ensure_user_exists();
        $this->configure_timezone();
        $this->configure_language();
        $this->require_clock_in();
        $this->load_common_html_vars();
        $this->load_common_script_vars();

        rate_limit($this->input->ip_address());
    }

    private function ensure_user_exists()
    {
        $user_id = session('user_id');

        if (!$user_id || !$this->db->table_exists('users')) {
            return;
        }

        $current = $this->db->select('roles.slug, user_settings.password')->from('users')
            ->join('roles', 'roles.id = users.id_roles')
            ->join('user_settings', 'user_settings.id_users = users.id')
            ->where('users.id', $user_id)->get()->row_array();

        if (!$current || $current['slug'] !== session('role_slug') ||
            !hash_equals(hash('sha256', $current['password']), (string) session('credential_stamp'))) {
            $this->session->sess_destroy();
            if ($this->input->method() === 'get' && !$this->input->is_ajax_request()
                && $this->router->method !== 'session_status') {
                redirect('login');
            }
            abort(401, 'Authentication required');
        }
    }

    /**
     * Employees who clock in can use the application only while they are working: with no open workday, or during a
     * pause, every page sends them to the clock and any other request is refused. Who clocks in follows the
     * employment relationship of each user and the roles of config/attendance.php.
     */
    private function require_clock_in(): void
    {
        $open = ['attendance', 'login', 'logout', 'user', 'localization', 'recovery', 'captcha', 'branding'];
        $open = array_merge($open, ['update', 'installation', 'console']);
        html_vars(['attendance_menu' => false]);
        if (
            !session('user_id') ||
            in_array(strtolower($this->router->class), ['update', 'installation', 'console'], true) ||
            !$this->db->table_exists('attendance_entries') ||
            !$this->db->field_exists('employment', 'users')
        ) {
            return;
        }
        $this->load->library('attendance_rules');
        $user = $this->db
            ->select('users.employment, roles.slug')
            ->from('users')
            ->join('roles', 'roles.id = users.id_roles')
            ->where('users.id', (int) session('user_id'))
            ->get()
            ->row_array();
        $clocks =
            ($user['employment'] ?? null) === 'employee' &&
            in_array($user['slug'], Attendance_rules::law()['attendance_roles'], true);
        html_vars(['attendance_menu' => $clocks || ($user['slug'] ?? null) === DB_SLUG_ADMIN]);
        if (!$clocks || in_array(strtolower($this->router->class), $open, true)) {
            return;
        }
        $entry = $this->db
            ->select('segments')
            ->where(['employee_id' => (int) session('user_id'), 'voided' => 0, 'end_at' => null])
            ->order_by('id', 'DESC')
            ->get('attendance_entries')
            ->row_array();
        $segments = $entry ? json_decode($entry['segments'], true) : [];
        if ($segments && end($segments)['kind'] === 'work') {
            return;
        }
        if ($this->input->method() === 'get' && !$this->input->is_ajax_request()) {
            redirect('attendance');
        }
        json_response(['success' => false, 'message' => lang('att_clock_first')], 403);
        $this->output->_display();
        exit();
    }

    /**
     * Configure the language.
     */
    private function configure_language()
    {
        $session_language = session('language');
        $query_language = request('language');
        $available_languages = config('available_languages');

        // Priority: supported session preference > supported query parameter > configured default (Catalan).
        $language = normalize_language(config('language'));

        if ($session_language && in_array($session_language, $available_languages)) {
            $language = $session_language;
        } elseif ($query_language && in_array($query_language, $available_languages)) {
            $language = $query_language;
        }

        if ($language) {
            $language_codes = config('language_codes');

            config([
                'language' => $language,
                'language_code' => array_search($language, $language_codes, true) ?: 'ca',
            ]);
        }

        $this->lang->load('translations');
    }

    /**
     * Load common script vars for all requests.
     */
    private function load_common_html_vars()
    {
        html_vars([
            'shared_clinic_access' => has_shared_clinic_access(),
            'base_url' => config('base_url'),
            'index_page' => config('index_page'),
            'available_languages' => config('available_languages'),
            'language' => $this->lang->language,
            'csrf_token' => $this->security->get_csrf_hash(),
        ]);
    }

    /**
     * Load common script vars for all requests.
     */
    private function load_common_script_vars()
    {
        script_vars([
            'shared_clinic_access' => has_shared_clinic_access(),
            'base_url' => config('base_url'),
            'index_page' => config('index_page'),
            'available_languages' => config('available_languages'),
            'csrf_token' => $this->security->get_csrf_hash(),
            'language' => config('language'),
            'language_code' => config('language_code'),
        ]);
    }

    /**
     * Set the default timezone of the app, based on the selected setting.
     */
    private function configure_timezone(): void
    {
        if (!$this->db->table_exists('settings')) {
            return;
        }

        $default_timezone = setting('default_timezone');

        if (!empty($default_timezone)) {
            date_default_timezone_set($default_timezone);
        }
    }

    /**
     * Redirect to the update page if there are pending database migrations.
     *
     * This prevents cryptic database errors (e.g. "Table doesn't exist") when a user
     * upgrades the application files without running the DB migration step.
     */
    private function check_pending_migrations(): void
    {
        $excluded_controllers = ['update', 'installation', 'console'];

        if (in_array($this->router->class, $excluded_controllers, true)) {
            return;
        }

        // A fresh install has no tables yet, let the controller redirect to the installation page.
        if (!$this->db->table_exists('migrations') || !is_app_installed()) {
            return;
        }

        $this->load->library('migration');

        $migrations = $this->migration->find_migrations();

        if (empty($migrations)) {
            return;
        }

        $latest_version = (int) max(array_keys($migrations));
        $current_version = (int) $this->migration->current_version();

        if ($current_version < $latest_version) {
            show_error(
                'There are pending database migrations that must be run before using the application. ' .
                    'Please log in as an administrator and visit <a href="' .
                    site_url('update') .
                    '">' .
                    site_url('update') .
                    '</a> to apply them, or run <code>php index.php console migrate</code> from the terminal.',
                503,
                'Database Migration Required',
            );
        }
    }

    /**
     * Check if the storage folder is writable.
     */
    private function check_storage_writable(): void
    {
        $storage_path = APPPATH . '../storage';

        if (!is_dir($storage_path)) {
            show_error(
                'The storage folder does not exist: ' .
                    $storage_path .
                    '. ' .
                    'Please create this directory and ensure it is writable by the web server.',
                500,
                'Storage Configuration Error',
            );
        }

        if (!is_writable($storage_path)) {
            show_error(
                'The storage folder is not writable: ' .
                    $storage_path .
                    '. ' .
                    'Please ensure the web server has write permissions to this directory and its subdirectories (cache, logs, sessions, uploads).',
                500,
                'Storage Configuration Error',
            );
        }
    }
}
