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
 * Holidays controller.
 *
 * Lists the official holidays of a year (national, regional and local) and lets the administrator decide which ones
 * close the clinic: closing creates a blocked period for the whole clinic on that day.
 *
 * @package Controllers
 */
class Holidays extends EA_Controller
{
    /**
     * Holidays constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('blocked_periods_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
        $this->load->library('holiday_calendar');
    }

    /**
     * Render the holidays page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('holidays')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_BLOCKED_PERIODS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $year = (int) (request('year') ?: date('Y'));
        $year = max(2000, min(2100, $year));
        $region = setting('holiday_region') ?: null;

        $holidays = $this->holiday_calendar->get_for_year($year, $region);

        foreach ($holidays as &$holiday) {
            $holiday['closed'] = $this->is_clinic_closed($holiday['date']);
        }

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
        ]);

        html_vars([
            'page_title' => lang('holidays'),
            'active_menu' => PRIV_BLOCKED_PERIODS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'privileges' => $this->roles_model->get_permissions_by_slug(session('role_slug')),
            'year' => $year,
            'region' => $region,
            'regions' => Holiday_calendar::REGIONS,
            'holidays' => $holidays,
        ]);

        $this->load->view('pages/holidays');
    }

    /**
     * Close the whole clinic on a holiday, with a blocked period for that day.
     *
     * The appointments of that day are listed first, as with any blocked period, and never cancelled.
     */
    public function close(): void
    {
        try {
            method('post');

            if (cannot('add', PRIV_BLOCKED_PERIODS)) {
                abort(403, 'Forbidden');
            }

            check('date', 'date');
            check('name', 'string');
            check('level', 'string');

            $date = date('Y-m-d', strtotime(request('date')));
            $level = in_array(request('level'), ['national', 'regional', 'local'], true) ? request('level') : 'local';

            $blocked_period = [
                'name' => request('name'),
                'type' => 'holiday_' . $level,
                'start_datetime' => $date . ' 00:00:00',
                'end_datetime' => $date . ' 23:59:00',
                'recurrence' => 'none',
                'provider_ids' => [],
                'notes' => '',
            ];

            if (!filter_var(request('confirmed'), FILTER_VALIDATE_BOOLEAN)) {
                $affected = $this->blocked_periods_model->get_affected_appointments($blocked_period);

                if ($affected) {
                    json_response(['success' => false, 'affected' => $affected]);

                    return;
                }
            }

            $this->blocked_periods_model->save($blocked_period);

            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Add a local holiday (or a holiday that the official calendar moved this year).
     */
    public function store(): void
    {
        try {
            method('post');

            if (cannot('add', PRIV_BLOCKED_PERIODS)) {
                abort(403, 'Forbidden');
            }

            check('date', 'date');
            check('name', 'string');

            $name = trim((string) request('name'));

            if ($name === '') {
                throw new InvalidArgumentException(lang('holiday_name_missing'));
            }

            $this->db->insert('holidays', [
                'date' => date('Y-m-d', strtotime(request('date'))),
                'name' => $name,
                'level' => 'local',
            ]);

            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Remove a holiday added by hand. Blocked periods created from it are kept.
     */
    public function destroy(): void
    {
        try {
            method('post');

            if (cannot('delete', PRIV_BLOCKED_PERIODS)) {
                abort(403, 'Forbidden');
            }

            check('holiday_id', 'numeric');

            $this->db->delete('holidays', ['id' => (int) request('holiday_id')]);

            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Choose the region whose holidays are listed.
     */
    public function save_region(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_BLOCKED_PERIODS)) {
                abort(403, 'Forbidden');
            }

            check('region', 'string|null');

            $region = in_array(request('region'), Holiday_calendar::REGIONS, true) ? request('region') : '';

            setting(['holiday_region' => $region]);

            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Check whether a blocked period of the whole clinic covers a whole date.
     */
    private function is_clinic_closed(string $date): bool
    {
        foreach ($this->blocked_periods_model->get_for_period($date, $date) as $occurrence) {
            if (
                !$occurrence['provider_ids'] &&
                $occurrence['start_datetime'] <= $date . ' 00:00:00' &&
                $occurrence['end_datetime'] >= $date . ' 23:59:00'
            ) {
                return true;
            }
        }

        return false;
    }
}
