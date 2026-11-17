<?php defined('BASEPATH') or exit('No direct script access allowed');

class Attendance extends EA_Controller
{
    private bool $manager;
    private bool $worker;
    private int $actor;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('attendance_model');
        $this->actor = (int) session('user_id');
        $role = $this->actor ? $this->attendance_model->role($this->actor) : null;
        if (!$role) {
            if ($this->router->method === 'index') {
                redirect('login');
                exit();
            }
            abort(401, 'Unauthorized');
        }
        $this->manager = $role === DB_SLUG_ADMIN;
        $this->worker = !$this->manager && $this->attendance_model->requires_clock($this->actor);
        // Former employees keep access to the records of the time they were employees.
        if (
            !$this->manager &&
            !$this->worker &&
            !in_array($this->actor, array_column($this->attendance_model->employees(), 'id'), true)
        ) {
            abort(403, 'Forbidden');
        }
        $this->output->set_header('Cache-Control: no-store, private');
    }

    private function employee(): int
    {
        $id = $this->manager ? (int) request('employee_id') : $this->actor;
        if (!$this->manager && request('employee_id') && (int) request('employee_id') !== $this->actor) {
            abort(403, 'Forbidden');
        }
        if (!in_array($id, array_column($this->attendance_model->employees(), 'id'), true)) {
            abort(404, 'Not found');
        }
        return $id;
    }

    public function index(): void
    {
        method('get');
        script_vars([
            'attendance_admin' => $this->manager,
            'attendance_worker' => $this->worker,
            'attendance_timezone' => Attendance_rules::zone(),
            'attendance_user' => $this->actor,
            'attendance_employees' => $this->manager
                ? $this->attendance_model->employees()
                : [['id' => $this->actor, 'name' => $this->attendance_model->name($this->actor), 'current' => true]],
            'attendance_month' => (new DateTimeImmutable('now', new DateTimeZone(Attendance_rules::zone())))->format(
                'Y-m',
            ),
        ]);
        html_vars([
            'page_title' => lang('att_title'),
            'active_menu' => 'attendance',
            'attendance_admin' => $this->manager,
            'attendance_worker' => $this->worker,
            'attendance_retention_years' => $this->attendance_model->rules()['retention_years'],
            'attendance_legal_reference' => Attendance_rules::law()['attendance_legal_reference'],
            'attendance_legal_url' => Attendance_rules::law()['attendance_legal_url'],
            'user_display_name' => $this->accounts->get_user_display_name($this->actor),
        ]);
        $this->load->view('pages/attendance');
    }

    public function data(): void
    {
        method('get');
        $employee = $this->employee();
        try {
            $month = (string) request('month');
            $report = $this->attendance_model->month($employee, $month);
            $report['current'] = $this->attendance_model->current($employee);
            $report['server_now'] = gmdate('Y-m-d H:i:s');
            $report['requests'] = $this->db
                ->where('employee_id', $employee)
                ->group_start()
                ->where('status', 'pending')
                ->or_like('work_date', $month, 'after')
                ->group_end()
                ->order_by('id', 'DESC')
                ->get('attendance_requests')
                ->result_array();
            $report['reports'] = $this->db
                ->select('id, month, issued_at, delivered_at, delivery_note')
                ->where('employee_id', $employee)
                ->where('month', $month)
                ->order_by('id', 'DESC')
                ->get('attendance_reports')
                ->result_array();
            if ($this->manager) {
                $report['team'] = [];
                foreach ($this->attendance_model->employees() as $person) {
                    $report['team'][] = array_replace($person, [
                        'current' => $this->attendance_model->current($person['id']),
                        'pending' => $this->db
                            ->where('employee_id', $person['id'])
                            ->where('status', 'pending')
                            ->count_all_results('attendance_requests'),
                    ]);
                }
            }
            json_response($report);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function clock(): void
    {
        method('post');
        if (!$this->worker) {
            abort(403, 'Forbidden');
        }
        $this->employee();
        try {
            json_response(
                $this->attendance_model->clock(
                    $this->actor,
                    (string) request('action'),
                    (int) request('revision'),
                    (string) request('request_key'),
                    (int) request('entry_id'),
                ),
            );
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function change(): void
    {
        method('post');
        $operation = (string) request('operation');
        if (!$this->manager && $operation !== 'request') {
            abort(403, 'Forbidden');
        }
        $employee = $this->employee();
        try {
            $payload = json_decode((string) request('payload'), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new InvalidArgumentException(lang('att_invalid_segments'));
            }
            switch ($operation) {
                case 'request':
                    if ($this->manager) {
                        abort(403, 'Forbidden');
                    }
                    $this->attendance_model->request_correction(
                        $employee,
                        (string) ($payload['date'] ?? ''),
                        (string) ($payload['message'] ?? ''),
                    );
                    break;
                case 'correct':
                    $this->attendance_model->correct($employee, $this->actor, $payload);
                    break;
                case 'resolve':
                    $this->attendance_model->resolve_request(
                        $employee,
                        $this->actor,
                        (int) ($payload['id'] ?? 0),
                        (string) ($payload['resolution'] ?? ''),
                    );
                    break;
                case 'issue':
                    $this->attendance_model->issue_report(
                        $employee,
                        $this->actor,
                        (string) ($payload['month'] ?? ''),
                        $payload,
                    );
                    break;
                case 'deliver':
                    $this->attendance_model->deliver(
                        $employee,
                        $this->actor,
                        (int) ($payload['id'] ?? 0),
                        (string) ($payload['note'] ?? ''),
                    );
                    break;
                default:
                    throw new InvalidArgumentException(lang('att_failed'));
            }
            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function history(): void
    {
        method('get');
        try {
            $employee = $this->employee();
            $id = (int) request('entry_id');
            if (!$id || $this->attendance_model->entry($id)['employee_id'] !== $employee) {
                abort(403, 'Forbidden');
            }
            json_response($this->attendance_model->history($employee, $id));
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function export(): void
    {
        method('get');
        $employee = $this->employee();
        $month = (string) request('month');
        try {
            $report = $this->attendance_model->month($employee, $month);
            if (request('format') === 'json') {
                $report['history'] = $this->attendance_model->history($employee);
                $report['requests'] = $this->db
                    ->get_where('attendance_requests', ['employee_id' => $employee])
                    ->result_array();
                $report['issued_reports'] = $this->db
                    ->get_where('attendance_reports', ['employee_id' => $employee])
                    ->result_array();
                $this->output->set_header(
                    'Content-Disposition: attachment; filename="attendance-' . $employee . '-' . $month . '.json"',
                );
                json_response($report);
                return;
            }
            if (request('format') === 'print') {
                html_vars(['report' => $report, 'issued' => null]);
                $this->load->view('pages/attendance_report');
                return;
            }
            $this->output
                ->set_content_type('text/csv', 'utf-8')
                ->set_header(
                    'Content-Disposition: attachment; filename="attendance-' . $employee . '-' . $month . '.csv"',
                );
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, "\xEF\xBB\xBF");
            $write = static function (array $row) use ($stream) {
                fputcsv(
                    $stream,
                    array_map(
                        static fn($value) => preg_match('/^[=+@\-\t\r\n]/', (string) $value) ? "'" . $value : $value,
                        $row,
                    ),
                    ';',
                    '"',
                    '',
                );
            };
            $write([
                lang('att_employee'),
                lang('att_entry'),
                lang('att_revision'),
                lang('att_kind'),
                lang('att_policy'),
                lang('att_start'),
                lang('att_end'),
                lang('att_timezone'),
                lang('att_voided'),
                lang('att_provisional'),
            ]);
            foreach ($report['entries'] as $entry) {
                foreach ($entry['segments'] as $segment) {
                    $local = static fn($time) => $time === null
                        ? ''
                        : (new DateTimeImmutable($time, new DateTimeZone('UTC')))
                            ->setTimezone(new DateTimeZone(Attendance_rules::zone()))
                            ->format('Y-m-d H:i:s P');
                    $write([
                        $entry['employee_name'],
                        $entry['id'],
                        $entry['revision'],
                        lang('att_' . $segment['kind']),
                        lang('att_' . $segment['policy']),
                        $local($segment['start']),
                        $local($segment['end']),
                        Attendance_rules::zone(),
                        $entry['voided'],
                        (int) $report['summary']['provisional'],
                    ]);
                }
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);
            $this->output->set_output($csv);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function report(): void
    {
        method('get');
        $row = $this->db->get_where('attendance_reports', ['id' => (int) request('id')])->row_array();
        if (!$row) {
            abort(404, 'Not found');
        }
        if (!$this->manager && (int) $row['employee_id'] !== $this->actor) {
            abort(403, 'Forbidden');
        }
        html_vars(['report' => json_decode($row['snapshot'], true, 512, JSON_THROW_ON_ERROR), 'issued' => $row]);
        $this->load->view('pages/attendance_report');
    }
}
