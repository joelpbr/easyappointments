<?php defined('BASEPATH') or exit('No direct script access allowed');

class Attendance_model extends EA_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('attendance_rules');
        Attendance_rules::set_zone(setting('default_timezone'));
    }

    /** Staff of the roles that can clock in, with their employment relationship (null while undefined). */
    public function staff(): array
    {
        $users = $this->db
            ->select('users.id, users.first_name, users.last_name, users.employment, roles.slug AS role')
            ->from('users')
            ->join('roles', 'roles.id=users.id_roles')
            ->where_in('roles.slug', Attendance_rules::law()['attendance_roles'])
            ->order_by('users.first_name')
            ->get()
            ->result_array();
        return array_map(
            fn($user) => [
                'id' => (int) $user['id'],
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'role' => $user['role'],
                'employment' => $user['employment'],
            ],
            $users,
        );
    }

    /** Employees clock in; self-employed professionals and partners do not (art. 34.9 ET covers employees). */
    public function requires_clock(int $user): bool
    {
        foreach ($this->staff() as $person) {
            if ($person['id'] === $user) {
                return $person['employment'] === 'employee';
            }
        }
        return false;
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
        $employees = [];
        foreach ($this->staff() as $person) {
            if ($person['employment'] === 'employee') {
                $employees[$person['id']] = ['id' => $person['id'], 'name' => $person['name'], 'current' => true];
            }
        }
        foreach ($this->db->get('attendance_profiles')->result_array() as $profile) {
            $id = (int) $profile['employee_id'];
            $employees[$id] ??= ['id' => $id, 'name' => $profile['employee_name'], 'current' => false];
        }
        return array_values($employees);
    }

    /** Working time rules from the settings, with the legal defaults for any value not set. */
    public function rules(): array
    {
        $values = [];
        foreach (array_keys(Attendance_rules::defaults()) as $key) {
            $value = setting('attendance_' . $key);
            if ($value !== null) {
                $values[$key] = $value;
            }
        }
        return Attendance_rules::config($values);
    }

    public function save_rules(int $actor, array $data): void
    {
        $data = array_intersect_key($data, Attendance_rules::defaults());
        $rules = Attendance_rules::config($data, true);
        // The rules have their own chain in the journal, under employee 0.
        $this->lock(0, function () use ($actor, $rules) {
            $before = $this->rules();
            foreach ($rules as $key => $value) {
                $name = 'attendance_' . $key;
                if ($this->db->get_where('settings', ['name' => $name])->num_rows()) {
                    $this->db->update('settings', ['value' => (string) $value], ['name' => $name]);
                } else {
                    $this->db->insert('settings', ['name' => $name, 'value' => (string) $value]);
                }
            }
            $this->journal(0, $actor, 'rules', '', $before, $rules);
        });
    }

    public function profile(int $id): array
    {
        $profile = ($this->db->get_where('attendance_profiles', ['employee_id' => $id])->row_array() ?: [
                'employee_id' => $id,
                'employee_name' => $this->name($id),
                'contract_type' => 'unknown',
                'weekly_minutes' => null,
                'agreement' => '',
                'pause_policy' => 'agreement',
            ]) + ['contract_duration' => 'unknown', 'category' => '', 'schedule' => null, 'complementary_percent' => 0];
        $profile['employee_id'] = (int) $profile['employee_id'];
        $profile['weekly_minutes'] = $profile['weekly_minutes'] === null ? null : (int) $profile['weekly_minutes'];
        $profile['complementary_percent'] = (int) $profile['complementary_percent'];
        $profile['schedule'] = Attendance_rules::schedule($profile['schedule']);
        unset($profile['updated_at']);
        return $profile;
    }

    /** The profile row exists before the first record, so that its name survives the deletion of the login. */
    private function ensure_profile(int $employee): void
    {
        if (!$this->db->get_where('attendance_profiles', ['employee_id' => $employee])->num_rows()) {
            $this->db->insert('attendance_profiles', $this->profile_row($this->profile($employee)));
        }
    }

    private function profile_row(array $profile): array
    {
        $profile['schedule'] = json_encode($profile['schedule']);
        $profile['updated_at'] = gmdate('Y-m-d H:i:s');
        return $profile;
    }

    /** What applies to a record when it is created: the contract and the rules of that moment. */
    private function snapshot(int $employee): array
    {
        return $this->profile($employee) + ['rules' => $this->rules()];
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
        $row = [
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
        ];
        // Callers hold the lock of the employee, so the previous seal cannot change meanwhile.
        $previous = $this->db
            ->select('hash')
            ->where('employee_id', $employee)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('attendance_journal')
            ->row('hash');
        $row['hash'] = Attendance_rules::seal($previous, $row);
        $this->db->insert('attendance_journal', $row);
    }

    /**
     * Whether the records of an employee were changed outside the application: a broken seal in the journal, or an
     * entry that differs from the state its last journal row recorded.
     */
    public function tampered(int $employee): bool
    {
        $previous = null;
        $states = [];
        $rows = $this->db->where('employee_id', $employee)->order_by('id')->get('attendance_journal')->result_array();
        foreach ($rows as $row) {
            if ($row['hash'] !== Attendance_rules::seal($previous, $row)) {
                return true;
            }
            $previous = $row['hash'];
            if ($row['entry_id'] !== null) {
                $states[(int) $row['entry_id']] = json_decode($row['after_json'], true);
            }
        }
        foreach ($this->db->get_where('attendance_entries', ['employee_id' => $employee])->result_array() as $row) {
            $entry = $this->decode($row);
            $state = $states[$entry['id']] ?? null;
            if (
                !$state ||
                $state['segments'] !== $entry['segments'] ||
                $state['start_at'] !== $entry['start_at'] ||
                $state['end_at'] !== $entry['end_at'] ||
                (int) $state['voided'] !== $entry['voided'] ||
                (int) $state['revision'] !== $entry['revision']
            ) {
                return true;
            }
        }
        return false;
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
                $this->ensure_profile($employee);
                $profile = $this->snapshot($employee);
                $entry = [
                    'employee_id' => $employee,
                    'employee_name' => $this->name($employee),
                    'start_at' => $now,
                    'end_at' => null,
                    'timezone' => Attendance_rules::zone(),
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
                if (
                    $action === 'pause' &&
                    !Attendance_rules::pauses_allowed($this->profile($employee)['schedule'], $this->rules())
                ) {
                    throw new InvalidArgumentException(lang('att_no_pause'));
                }
                $entry['segments'][$last]['end'] = $now;
                if ($action === 'out') {
                    $entry['end_at'] = $now;
                } else {
                    $pause_policy = $entry['policy_snapshot']['pause_policy'] ?? 'unknown';
                    $entry['segments'][] = [
                        'kind' => $action === 'pause' ? 'break' : 'work',
                        'policy' =>
                            $action === 'pause' ? ($pause_policy === 'agreement' ? 'auto' : $pause_policy) : 'work',
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
            $this->ensure_profile($employee);
            $entry = $before ?: [
                'employee_id' => $employee,
                'employee_name' => $this->profile($employee)['employee_name'],
                'timezone' => Attendance_rules::zone(),
                'policy_snapshot' => $this->snapshot($employee),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];
            $entry['segments'] = $segments;
            $entry['start_at'] = $start;
            $entry['end_at'] = $end;
            $entry['voided'] = $voided;
            $entry['revision'] = ($before['revision'] ?? 0) + 1;
            return $this->persist($entry, $before, $actor, 'correction', $reason);
        });
    }

    /**
     * The contract of an employee. Part or full time follows from the weekly hours against the full-time workday
     * of the settings; complementary hours need a part-time contract of at least 10 h a week (art. 12.5 ET).
     */
    public function save_profile(int $employee, int $actor, array $data): void
    {
        $rules = $this->rules();
        $this->lock($employee, function () use ($employee, $actor, $data, $rules) {
            $minutes = $data['weekly_minutes'] ?? null;
            $minutes = $minutes === '' || $minutes === null ? null : $minutes;
            $percent = $data['complementary_percent'] ?? 0;
            if (
                !in_array($data['contract_duration'] ?? '', ['unknown', 'indefinite', 'temporary'], true) ||
                !in_array($data['pause_policy'] ?? '', ['agreement', 'paid', 'unpaid'], true) ||
                mb_strlen(trim((string) ($data['category'] ?? ''))) > 255 ||
                ($minutes !== null &&
                    (filter_var($minutes, FILTER_VALIDATE_INT) === false ||
                        $minutes < 1 ||
                        $minutes > $rules['full_time_minutes'])) ||
                filter_var($percent, FILTER_VALIDATE_INT) === false ||
                $percent < 0 ||
                $percent > $rules['complementary_max_percent']
            ) {
                throw new InvalidArgumentException(lang('att_invalid_profile'));
            }
            $contract =
                $minutes === null ? 'unknown' : ((int) $minutes < $rules['full_time_minutes'] ? 'part' : 'full');
            if (
                $percent > 0 &&
                ($contract !== 'part' ||
                    $minutes < Attendance_rules::law()['attendance_complementary_min_weekly_minutes'])
            ) {
                throw new InvalidArgumentException(lang('att_invalid_pact'));
            }
            $schedule = Attendance_rules::schedule($data['schedule'] ?? [], true);
            if ($contract === 'part' && !Attendance_rules::interruptions_allowed($schedule, $rules)) {
                throw new InvalidArgumentException(lang('att_too_many_interruptions'));
            }
            $before = $this->profile($employee);
            $profile = [
                'employee_id' => $employee,
                'employee_name' => $before['employee_name'],
                'contract_type' => $contract,
                'contract_duration' => $data['contract_duration'],
                'category' => trim((string) ($data['category'] ?? '')),
                'weekly_minutes' => $minutes === null ? null : (int) $minutes,
                'schedule' => $schedule,
                'complementary_percent' => (int) $percent,
                'pause_policy' => $data['pause_policy'],
                'agreement' => $before['agreement'],
            ];
            $this->db->replace('attendance_profiles', $this->profile_row($profile));
            $this->journal($employee, $actor, 'profile', '', $before, $profile);
        });
    }

    private function entries_between(int $employee, int $from, int $to): array
    {
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
        return array_map(fn($entry) => $this->decode($entry), $entries);
    }

    /** Totals from January to the given month, for the yearly limits. */
    private function year(int $employee, string $month, array $schedule, int $now): array
    {
        $year = ['counted' => 0, 'excess' => 0, 'planned' => 0];
        for ($number = 1; $number <= (int) substr($month, 5, 2); $number++) {
            $current = substr($month, 0, 4) . '-' . sprintf('%02d', $number);
            [$from, $to] = Attendance_rules::month_bounds($current);
            $summary = Attendance_rules::plan(
                Attendance_rules::summarize($this->entries_between($employee, $from, $to), $current, $now),
                $schedule,
            );
            $year['counted'] += $summary['counted_seconds'];
            $year['excess'] += $summary['excess_seconds'];
            $year['planned'] += Attendance_rules::planned_seconds($current, $schedule);
        }
        return $year;
    }

    public function month(int $employee, string $month): array
    {
        [$from, $to] = Attendance_rules::month_bounds($month);
        $now = time();
        $rules = $this->rules();
        $profile = $this->profile($employee);
        $entries = $this->entries_between($employee, $from, $to);
        $summary = Attendance_rules::plan(Attendance_rules::summarize($entries, $month, $now), $profile['schedule']);
        $summary['planned_seconds'] = Attendance_rules::planned_seconds($month, $profile['schedule']);
        $prior = array_filter(
            $this->entries_between($employee, $from - $rules['weekly_rest_days'] * 86400, $from),
            fn($entry) => Attendance_rules::instant($entry['start_at']) < $from,
        );
        $flags = array_merge(
            Attendance_rules::review_flags($entries, $summary, $now, $rules, array_values($prior), $month),
            Attendance_rules::limit_flags(
                $this->year($employee, $month, $profile['schedule'], $now),
                $summary,
                $profile,
                $rules,
            ),
        );
        if ($this->tampered($employee)) {
            array_unshift($flags, ['type' => 'integrity', 'date' => null, 'entry_id' => null]);
        }
        return [
            'month' => $month,
            'timezone' => Attendance_rules::zone(),
            'employee' => $profile,
            'rules' => $rules,
            'entries' => $entries,
            'summary' => $summary,
            'review_flags' => $flags,
            'suggestion' => Attendance_rules::suggest($summary, $profile['contract_type']),
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
            $this->ensure_profile($employee);
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
            if (
                $profile['contract_type'] === 'unknown' ||
                $profile['contract_duration'] === 'unknown' ||
                !$report['rules']['agreement']
            ) {
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
