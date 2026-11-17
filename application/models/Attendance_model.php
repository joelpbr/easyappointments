<?php defined('BASEPATH') or exit('No direct script access allowed');

class Attendance_model extends EA_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('attendance_rules');
    }

    public function role(int $id): ?string
    {
        return $this->db
            ->select('roles.slug')
            ->from('users')
            ->join('roles', 'roles.id=users.id_roles')
            ->where('users.id', $id)
            ->get()
            ->row('slug');
    }

    public function name(int $id): string
    {
        $user = $this->db->get_where('users', ['id' => $id])->row_array();
        return $user ? trim($user['first_name'] . ' ' . $user['last_name']) : '#' . $id;
    }

    public function employees(): array
    {
        $users = $this->db
            ->select('users.id, users.first_name, users.last_name')
            ->from('users')
            ->join('roles', 'roles.id=users.id_roles')
            ->where('roles.slug', DB_SLUG_SECRETARY)
            ->get()
            ->result_array();
        $employees = [];
        foreach ($users as $user) {
            $employees[(int) $user['id']] = [
                'id' => (int) $user['id'],
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'current' => true,
            ];
        }
        foreach ($this->db->get('attendance_profiles')->result_array() as $profile) {
            $id = (int) $profile['employee_id'];
            $employees[$id] ??= ['id' => $id, 'name' => $profile['employee_name'], 'current' => false];
        }
        return array_values($employees);
    }

    public function profile(int $id): array
    {
        return $this->db->get_where('attendance_profiles', ['employee_id' => $id])->row_array() ?: [
                'employee_id' => $id,
                'employee_name' => $this->name($id),
                'contract_type' => 'unknown',
                'weekly_minutes' => null,
                'agreement' => '',
                'pause_policy' => 'unknown',
            ];
    }

    private function lock(int $employee, callable $operation): mixed
    {
        $key = 'attendance:' . $this->db->database . ':' . $employee;
        if ((int) $this->db->query('SELECT GET_LOCK(?, 5) AS acquired', [$key])->row('acquired') !== 1) {
            throw new RuntimeException(lang('att_retry'));
        }
        $this->db->trans_begin();
        try {
            $result = $operation();
            if (!$this->db->trans_status()) {
                throw new RuntimeException(lang('att_failed'));
            }
            $this->db->trans_commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$key]);
        }
    }

    private function journal(
        int $employee,
        int $actor,
        string $action,
        string $reason,
        mixed $before,
        mixed $after,
        ?int $entry = null,
        ?string $key = null,
    ): void {
        $this->db->insert('attendance_journal', [
            'employee_id' => $employee,
            'entry_id' => $entry,
            'actor_id' => $actor,
            'actor_name' => $this->name($actor),
            'action' => $action,
            'reason' => $reason,
            'before_json' => json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE),
            'request_key' => $key,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function decode(array $entry): array
    {
        $entry['segments'] = json_decode($entry['segments'], true, 512, JSON_THROW_ON_ERROR);
        $entry['policy_snapshot'] = json_decode($entry['policy_snapshot'], true, 512, JSON_THROW_ON_ERROR);
        foreach (['id', 'employee_id', 'revision', 'voided'] as $key) {
            $entry[$key] = (int) $entry[$key];
        }
        return $entry;
    }

    public function entry(int $id): array
    {
        $entry = $this->db->get_where('attendance_entries', ['id' => $id])->row_array();
        if (!$entry) {
            throw new InvalidArgumentException(lang('att_not_found'));
        }
        return $this->decode($entry);
    }

    public function current(int $employee): ?array
    {
        $entry = $this->db
            ->where('employee_id', $employee)
            ->where('voided', 0)
            ->where('end_at', null)
            ->order_by('id', 'DESC')
            ->get('attendance_entries')
            ->row_array();
        return $entry ? $this->decode($entry) : null;
    }

    public function clock(int $employee, string $action, int $revision, string $key, int $entry_id = 0): array
    {
        if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/', $key)) {
            throw new InvalidArgumentException(lang('att_retry'));
        }
        return $this->lock($employee, function () use ($employee, $action, $revision, $key, $entry_id) {
            $duplicate = $this->db
                ->get_where('attendance_journal', ['actor_id' => $employee, 'request_key' => $key])
                ->row_array();
            if ($duplicate) {
                return ['success' => true];
            }
            $current = $this->current($employee);
            if ($revision !== ($current['revision'] ?? 0) || $entry_id !== ($current['id'] ?? 0)) {
                throw new RuntimeException(lang('att_stale'));
            }
            $now = gmdate('Y-m-d H:i:s');
            if ($action === 'in') {
                if ($current) {
                    throw new RuntimeException(lang('att_stale'));
                }
                $profile = $this->profile($employee);
                if (!$this->db->get_where('attendance_profiles', ['employee_id' => $employee])->num_rows()) {
                    $this->db->insert('attendance_profiles', $profile + ['updated_at' => $now]);
                }
                $entry = [
                    'employee_id' => $employee,
                    'employee_name' => $this->name($employee),
                    'start_at' => $now,
                    'end_at' => null,
                    'timezone' => Attendance_rules::ZONE,
                    'segments' => [['kind' => 'work', 'policy' => 'work', 'start' => $now, 'end' => null]],
                    'policy_snapshot' => $profile,
                    'revision' => 1,
                    'voided' => 0,
                    'created_at' => $now,
                ];
            } else {
                if (!$current || !in_array($action, ['pause', 'resume', 'out'], true)) {
                    throw new RuntimeException(lang('att_stale'));
                }
                $entry = $current;
                $last = count($entry['segments']) - 1;
                $kind = $entry['segments'][$last]['kind'];
                if (($action === 'pause' && $kind !== 'work') || ($action === 'resume' && $kind !== 'break')) {
                    throw new RuntimeException(lang('att_stale'));
                }
                $entry['segments'][$last]['end'] = $now;
                if ($action === 'out') {
                    $entry['end_at'] = $now;
                } else {
                    $entry['segments'][] = [
                        'kind' => $action === 'pause' ? 'break' : 'work',
                        'policy' => $action === 'pause' ? $entry['policy_snapshot']['pause_policy'] : 'work',
                        'start' => $now,
                        'end' => null,
                    ];
                }
                $entry['revision']++;
            }
            Attendance_rules::validate($entry['segments'], time());
            $this->persist($entry, $current, $employee, $action, '', $key);
            return ['success' => true];
        });
    }

    private function persist(
        array $entry,
        ?array $before,
        int $actor,
        string $action,
        string $reason,
        ?string $key = null,
    ): int {
        $encoded = $entry;
        $encoded['segments'] = json_encode($entry['segments'], JSON_UNESCAPED_UNICODE);
        $encoded['policy_snapshot'] = json_encode($entry['policy_snapshot'], JSON_UNESCAPED_UNICODE);
        if ($before) {
            $this->db->update('attendance_entries', $encoded, ['id' => $entry['id']]);
        } else {
            $this->db->insert('attendance_entries', $encoded);
            $entry['id'] = (int) $this->db->insert_id();
        }
        $this->journal($entry['employee_id'], $actor, $action, $reason, $before, $entry, $entry['id'], $key);
        return $entry['id'];
    }

    public function correct(int $employee, int $actor, array $data): int
    {
        return $this->lock($employee, function () use ($employee, $actor, $data) {
            $reason = trim((string) ($data['reason'] ?? ''));
            if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
                throw new InvalidArgumentException(lang('att_reason_required'));
            }
            $id = (int) ($data['id'] ?? 0);
            $before = $id ? $this->entry($id) : null;
            if (
                $before &&
                ($before['employee_id'] !== $employee || $before['revision'] !== (int) ($data['revision'] ?? 0))
            ) {
                throw new RuntimeException(lang('att_stale'));
            }
            $segments = $data['segments'] ?? [];
            if (!is_array($segments)) {
                throw new InvalidArgumentException(lang('att_invalid_segments'));
            }
            $segments = array_map(
                fn($segment) => [
                    'kind' => (string) ($segment['kind'] ?? ''),
                    'policy' => (string) ($segment['policy'] ?? ''),
                    'start' => Attendance_rules::local_to_utc((string) ($segment['start'] ?? '')),
                    'end' => empty($segment['end']) ? null : Attendance_rules::local_to_utc((string) $segment['end']),
                ],
                $segments,
            );
            Attendance_rules::validate($segments, time());
            $start = $segments[0]['start'];
            $end = $segments[count($segments) - 1]['end'];
            $voided = !empty($data['voided']) ? 1 : 0;
            if (!$voided) {
                $others = $this->db
                    ->where('employee_id', $employee)
                    ->where('voided', 0)
                    ->where('id !=', $id)
                    ->get('attendance_entries')
                    ->result_array();
                foreach ($others as $other) {
                    if (
                        $other['start_at'] < ($end ?? '9999-12-31 23:59:59') &&
                        ($other['end_at'] ?? '9999-12-31 23:59:59') > $start
                    ) {
                        throw new InvalidArgumentException(lang('att_overlap'));
                    }
                }
            }
            $entry = $before ?: [
                'employee_id' => $employee,
                'employee_name' => $this->profile($employee)['employee_name'],
                'timezone' => Attendance_rules::ZONE,
                'policy_snapshot' => $this->profile($employee),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];
            $entry['segments'] = $segments;
            $entry['start_at'] = $start;
            $entry['end_at'] = $end;
            $entry['voided'] = $voided;
            $entry['revision'] = ($before['revision'] ?? 0) + 1;
            if (!$this->db->get_where('attendance_profiles', ['employee_id' => $employee])->num_rows()) {
                $this->db->insert(
                    'attendance_profiles',
                    $this->profile($employee) + ['updated_at' => gmdate('Y-m-d H:i:s')],
                );
            }
            return $this->persist($entry, $before, $actor, 'correction', $reason);
        });
    }

    public function save_profile(int $employee, int $actor, array $data): void
    {
        $this->lock($employee, function () use ($employee, $actor, $data) {
            if (
                !in_array($data['contract_type'] ?? '', ['unknown', 'full', 'part'], true) ||
                !in_array($data['pause_policy'] ?? '', ['unknown', 'paid', 'unpaid'], true)
            ) {
                throw new InvalidArgumentException(lang('att_invalid_profile'));
            }
            $minutes = $data['weekly_minutes'] ?? null;
            if (
                $minutes !== null &&
                $minutes !== '' &&
                (filter_var($minutes, FILTER_VALIDATE_INT) === false || $minutes < 1 || $minutes > 10080)
            ) {
                throw new InvalidArgumentException(lang('att_invalid_profile'));
            }
            $before = $this->profile($employee);
            $profile = [
                'employee_id' => $employee,
                'employee_name' => $before['employee_name'],
                'contract_type' => $data['contract_type'],
                'pause_policy' => $data['pause_policy'],
                'weekly_minutes' => $minutes === '' || $minutes === null ? null : (int) $minutes,
                'agreement' => mb_substr(trim((string) ($data['agreement'] ?? '')), 0, 512),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ];
            $this->db->replace('attendance_profiles', $profile);
            $this->journal($employee, $actor, 'profile', '', $before, $profile);
        });
    }

    public function month(int $employee, string $month): array
    {
        [$from, $to] = Attendance_rules::month_bounds($month);
        $entries = $this->db
            ->where('employee_id', $employee)
            ->where('start_at <', gmdate('Y-m-d H:i:s', $to))
            ->group_start()
            ->where('end_at >=', gmdate('Y-m-d H:i:s', $from))
            ->or_where('end_at', null)
            ->group_end()
            ->order_by('start_at')
            ->get('attendance_entries')
            ->result_array();
        $entries = array_map(fn($entry) => $this->decode($entry), $entries);
        return [
            'month' => $month,
            'timezone' => Attendance_rules::ZONE,
            'employee' => $this->profile($employee),
            'entries' => $entries,
            'summary' => Attendance_rules::summarize($entries, $month, time()),
            'generated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    public function history(int $employee, ?int $entry = null): array
    {
        $this->db->where('employee_id', $employee);
        if ($entry) {
            $this->db->where('entry_id', $entry);
        }
        $rows = $this->db->order_by('id')->get('attendance_journal')->result_array();
        foreach ($rows as &$row) {
            $row['before'] = json_decode($row['before_json'], true);
            $row['after'] = json_decode($row['after_json'], true);
            unset($row['before_json'], $row['after_json'], $row['request_key']);
        }
        return $rows;
    }

    public function request_correction(int $employee, string $date, string $message): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (
            !$parsed ||
            $parsed->format('Y-m-d') !== $date ||
            $date > date('Y-m-d') ||
            mb_strlen(trim($message)) < 5 ||
            mb_strlen($message) > 2000
        ) {
            throw new InvalidArgumentException(lang('att_reason_required'));
        }
        $this->lock($employee, function () use ($employee, $date, $message) {
            if (!$this->db->get_where('attendance_profiles', ['employee_id' => $employee])->num_rows()) {
                $this->db->insert(
                    'attendance_profiles',
                    $this->profile($employee) + ['updated_at' => gmdate('Y-m-d H:i:s')],
                );
            }
            $record = [
                'employee_id' => $employee,
                'employee_name' => $this->name($employee),
                'work_date' => $date,
                'message' => trim($message),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];
            $this->db->insert('attendance_requests', $record);
            $record['id'] = $this->db->insert_id();
            $this->journal($employee, $employee, 'request', $message, null, $record);
        });
    }

    public function resolve_request(int $employee, int $actor, int $id, string $resolution): void
    {
        $this->lock($employee, function () use ($employee, $actor, $id, $resolution) {
            $before = $this->db
                ->get_where('attendance_requests', ['id' => $id, 'employee_id' => $employee, 'status' => 'pending'])
                ->row_array();
            if (!$before || mb_strlen(trim($resolution)) < 5 || mb_strlen($resolution) > 2000) {
                throw new InvalidArgumentException(lang('att_reason_required'));
            }
            $after = array_replace($before, [
                'status' => 'resolved',
                'resolution' => trim($resolution),
                'resolved_by' => $actor,
                'resolved_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->db->update('attendance_requests', $after, ['id' => $id]);
            $this->journal($employee, $actor, 'resolve', $resolution, $before, $after);
        });
    }

    public function issue_report(int $employee, int $actor, string $month, array $classification): int
    {
        return $this->lock($employee, function () use ($employee, $actor, $month, $classification) {
            $classification = array_intersect_key(
                $classification,
                array_flip(['ordinary', 'complementary', 'overtime']),
            );
            $report = $this->month($employee, $month);
            if ($report['summary']['provisional'] || Attendance_rules::month_bounds($month)[1] > time()) {
                throw new InvalidArgumentException(lang('att_report_not_ready'));
            }
            $profile = $report['employee'];
            if ($profile['contract_type'] === 'unknown' || !$profile['weekly_minutes'] || !$profile['agreement']) {
                throw new InvalidArgumentException(lang('att_profile_required'));
            }
            $sum = 0;
            foreach (['ordinary', 'complementary', 'overtime'] as $kind) {
                $value = $classification[$kind] ?? '';
                if (!preg_match('/^\d{1,4}:[0-5]\d:[0-5]\d$/', $value)) {
                    throw new InvalidArgumentException(lang('att_classification_invalid'));
                }
                [$h, $m, $s] = array_map('intval', explode(':', $value));
                $classification[$kind] = $h * 3600 + $m * 60 + $s;
                $sum += $classification[$kind];
            }
            if (
                $sum !== $report['summary']['counted_seconds'] ||
                ($profile['contract_type'] === 'full' && $classification['complementary'] > 0) ||
                ($profile['contract_type'] === 'part' && $classification['overtime'] > 0)
            ) {
                throw new InvalidArgumentException(lang('att_classification_invalid'));
            }
            $report['classification'] = $classification;
            $report['company'] = [
                'name' => branding_name(),
                'address' => setting('company_address'),
                'email' => setting('company_email'),
            ];
            $report['history'] = array_values(
                array_filter(
                    $this->history($employee),
                    fn($event) => in_array((int) $event['entry_id'], array_column($report['entries'], 'id'), true),
                ),
            );
            $next_month = (new DateTimeImmutable($month . '-01'))->modify('+1 month')->format('Y-m-d');
            $report['requests'] = $this->db
                ->get_where('attendance_requests', [
                    'employee_id' => $employee,
                    'work_date >=' => $month . '-01',
                    'work_date <' => $next_month,
                ])
                ->result_array();
            if (array_filter($report['requests'], fn($r) => $r['status'] === 'pending')) {
                throw new InvalidArgumentException(lang('att_report_not_ready'));
            }
            $this->db->insert('attendance_reports', [
                'employee_id' => $employee,
                'employee_name' => $profile['employee_name'],
                'month' => $month,
                'snapshot' => json_encode($report, JSON_UNESCAPED_UNICODE),
                'issued_by' => $actor,
                'issued_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $id = (int) $this->db->insert_id();
            $this->journal($employee, $actor, 'report', '', null, ['report_id' => $id, 'month' => $month]);
            return $id;
        });
    }

    public function deliver(int $employee, int $actor, int $id, string $note): void
    {
        $this->lock($employee, function () use ($employee, $actor, $id, $note) {
            $before = $this->db
                ->get_where('attendance_reports', ['id' => $id, 'employee_id' => $employee])
                ->row_array();
            if (!$before || $before['delivered_at'] || mb_strlen(trim($note)) < 5 || mb_strlen($note) > 2000) {
                throw new InvalidArgumentException(lang('att_reason_required'));
            }
            $change = ['delivered_at' => gmdate('Y-m-d H:i:s'), 'delivery_note' => trim($note)];
            $this->db->update('attendance_reports', $change, ['id' => $id]);
            $this->journal($employee, $actor, 'delivery', $note, null, ['report_id' => $id] + $change);
        });
    }
}
