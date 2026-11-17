<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Working time settings: the rules of the law or of the collective agreement, and the contract of each employee who
 * clocks in. Only the administrator manages them, like the review of the records.
 */
class Attendance_settings extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('attendance_model');
        $this->load->library('accounts');
    }

    private function allowed(): bool
    {
        return session('role_slug') === DB_SLUG_ADMIN;
    }

    public function index(): void
    {
        method('get');
        session(['dest_url' => site_url('attendance_settings')]);
        $user_id = session('user_id');
        if (!$this->allowed()) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }
            redirect('login');
            return;
        }
        $employees = array_map(
            fn($person) => $person + ['profile' => $this->attendance_model->profile($person['id'])],
            $this->attendance_model->employees(),
        );
        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
            'attendance_rules' => $this->attendance_model->rules(),
            'attendance_limits' => Attendance_rules::limits(),
            'attendance_statute' => Attendance_rules::defaults(),
            'attendance_agreements' => Attendance_rules::agreements(),
            'attendance_complementary_min' => Attendance_rules::law()['attendance_complementary_min_weekly_minutes'],
            'attendance_employees' => $employees,
            'attendance_undefined' => array_values(
                array_filter($this->attendance_model->staff(), fn($person) => $person['employment'] === null),
            ),
        ]);
        html_vars([
            'page_title' => lang('settings'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
        ]);
        $this->load->view('pages/attendance_settings');
    }

    public function save(): void
    {
        try {
            method('post');
            if (!$this->allowed()) {
                abort(403, 'Forbidden');
            }
            $rules = json_decode((string) request('rules'), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($rules)) {
                throw new InvalidArgumentException(lang('att_invalid_settings'));
            }
            $this->attendance_model->save_rules((int) session('user_id'), $rules);
            json_response(['success' => true, 'rules' => $this->attendance_model->rules()]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function employee(): void
    {
        try {
            method('post');
            if (!$this->allowed()) {
                abort(403, 'Forbidden');
            }
            $id = (int) request('employee_id');
            if (!in_array($id, array_column($this->attendance_model->employees(), 'id'), true)) {
                abort(404, 'Not found');
            }
            $profile = json_decode((string) request('profile'), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($profile)) {
                throw new InvalidArgumentException(lang('att_invalid_profile'));
            }
            $this->attendance_model->save_profile($id, (int) session('user_id'), $profile);
            json_response(['success' => true, 'profile' => $this->attendance_model->profile($id)]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
