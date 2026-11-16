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
 * Activity log controller.
 *
 * The "Activity log" page, for the administrators: every change made in the application (appointments, patients,
 * staff, treatments, settings, WhatsApp messages, backups...), the latest first, with who made it, when, on what and
 * every field that changed (see the Audit_trail library). It can be searched (a patient, a phone, a setting, a date...)
 * and filtered by dates, user, kind of record and action.
 *
 * @package Controllers
 */
class Activity_log extends EA_Controller
{
    /**
     * Activity_log constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('audit_trail');
        $this->load->library('accounts');
    }

    /**
     * Render the page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('activity_log')]);

        $user_id = session('user_id');

        if (session('role_slug') !== DB_SLUG_ADMIN || cannot('view', PRIV_SYSTEM_SETTINGS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $date = static fn($value): string => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';

        $filters = [
            'q' => mb_substr(trim((string) request('q')), 0, 100),
            'from' => $date(request('from')),
            'to' => $date(request('to')),
            'user' => (int) request('user') ?: '',
            'entity' => preg_replace('/[^a-z_]/', '', (string) request('entity')),
            'action' => preg_replace('/[^a-z_]/', '', (string) request('action')),
        ];

        $page = max(1, (int) request('page'));

        $result = $this->audit_trail->page($filters, $page);

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
        ]);

        html_vars([
            'page_title' => lang('activity_log'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'entries' => $result['entries'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / Audit_trail::PER_PAGE)),
            'filters' => $filters,
            'options' => $this->audit_trail->filter_options(),
            'staff' => $this->audit_trail->staff(),
        ]);

        $this->load->view('pages/activity_log');
    }
}
