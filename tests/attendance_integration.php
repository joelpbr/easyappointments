<?php
/** CLI integration checks against isolated, disposable tables in the configured database.
 * docker exec -i <app> php < tests/attendance_integration.php
 * Boots the app, then uses its own table prefix; never changes real employees or attendance.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
chdir('/var/www/html');
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
$_SERVER['argc'] = 3;
require 'index.php';
$ci = &get_instance();
$original_prefix = $ci->db->dbprefix;
$prefix = 'attendance_test_' . getmypid() . '_';
$ci->db->set_dbprefix($prefix);
$tables = [
    'attendance_reports',
    'attendance_requests',
    'attendance_journal',
    'attendance_entries',
    'attendance_profiles',
    'users',
    'roles',
    'settings',
];
$check = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$reject = static function (callable $operation) {
    try {
        $operation();
    } catch (InvalidArgumentException | RuntimeException $e) {
        return;
    }
    throw new LogicException('Expected rejection');
};
try {
    $ci->db->query(
        "CREATE TABLE `{$prefix}users` (id INT PRIMARY KEY, first_name VARCHAR(256), last_name VARCHAR(256), id_roles INT) ENGINE=InnoDB",
    );
    $ci->db->query("CREATE TABLE `{$prefix}roles` (id INT PRIMARY KEY, slug VARCHAR(64)) ENGINE=InnoDB");
    $ci->db->query(
        "CREATE TABLE `{$prefix}settings` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(256), value TEXT) ENGINE=InnoDB",
    );
    $ci->db->insert_batch('roles', [['id' => 1, 'slug' => 'admin'], ['id' => 2, 'slug' => 'secretary']]);
    $ci->db->insert_batch('users', [
        ['id' => 900001, 'first_name' => 'Test admin', 'last_name' => '', 'id_roles' => 1],
        ['id' => 900002, 'first_name' => 'Test worker', 'last_name' => '', 'id_roles' => 2],
    ]);
    require_once APPPATH . 'migrations/109_add_staff_attendance.php';
    (new ReflectionClass(Migration_Add_staff_attendance::class))->newInstanceWithoutConstructor()->up();
    require_once APPPATH . 'migrations/110_add_attendance_settings.php';
    (new ReflectionClass(Migration_Add_attendance_settings::class))->newInstanceWithoutConstructor()->up();
    $ci->load->dbforge();
    require_once APPPATH . 'migrations/111_add_attendance_employment.php';
    (new ReflectionClass(Migration_Add_attendance_employment::class))->newInstanceWithoutConstructor()->up();
    $ci->load->model('attendance_model');
    $m = $ci->attendance_model;
    $check($m->rules() === Attendance_rules::defaults(), 'statute defaults until the clinic sets its agreement');
    $worker = 900002;
    $admin = 900001;
    $check($m->requires_clock($worker) && !$m->requires_clock($admin), 'secretaries start as employees who clock in');
    $ci->db->insert('users', ['id' => 900003, 'first_name' => 'Test podiatrist', 'last_name' => '', 'id_roles' => 3]);
    $ci->db->insert('roles', ['id' => 3, 'slug' => 'provider']);
    $check(!$m->requires_clock(900003), 'staff with an undefined relationship does not clock in');
    $ci->db->update('users', ['employment' => 'employee'], ['id' => 900003]);
    $check($m->requires_clock(900003), 'employed podiatrists clock in');
    $ci->db->update('users', ['employment' => 'self_employed'], ['id' => 900003]);
    $check(!in_array(900003, array_column($m->employees(), 'id'), true), 'self-employed staff never clocks in');
    $m->clock($worker, 'in', 0, 'integration-request-0001');
    $entry = $m->current($worker);
    $check($entry['revision'] === 1, 'entry revision');
    $m->clock($worker, 'in', 0, 'integration-request-0001');
    $check($ci->db->count_all('attendance_entries') === 1, 'idempotent clock');
    $reject(fn() => $m->clock($worker, 'in', 0, 'integration-request-0002'));
    $reject(fn() => $m->clock($worker, 'pause', 1, 'integration-request-wrong', $entry['id'] + 1));
    $m->clock($worker, 'pause', 1, 'integration-request-0003', $entry['id']);
    $reject(fn() => $m->clock($worker, 'pause', 2, 'integration-request-0004', $entry['id']));
    $m->clock($worker, 'resume', 2, 'integration-request-0005', $entry['id']);
    $m->clock($worker, 'out', 3, 'integration-request-0006', $entry['id']);
    $check($m->current($worker) === null, 'closed after out');
    $check(count($m->history($worker, $entry['id'])) === 4, 'journal preserves every clock action');
    $segments = [
        [
            'kind' => 'work',
            'policy' => 'work',
            'start' => '2026-01-12T09:00:00+01:00',
            'end' => '2026-01-12T13:00:00+01:00',
        ],
    ];
    $id = $m->correct($worker, $admin, ['reason' => 'Test missing historical shift', 'segments' => $segments]);
    $reject(fn() => $m->correct($worker, $admin, ['reason' => 'Test overlap', 'segments' => $segments]));
    $record = $m->entry($id);
    $reject(
        fn() => $m->correct($worker, $admin, [
            'id' => $id,
            'revision' => 0,
            'reason' => 'Test stale correction',
            'segments' => $segments,
        ]),
    );
    $reject(fn() => $m->save_rules($admin, ['min_rest_minutes' => 600]));
    $m->save_rules($admin, Attendance_rules::agreements()['catalonia_health']['values']);
    $check($m->rules()['break_minutes'] === 20, 'agreement rules saved');
    $reject(
        fn() => $m->save_profile($worker, $admin, [
            'contract_duration' => 'indefinite',
            'weekly_minutes' => 2400,
            'pause_policy' => 'agreement',
            'complementary_percent' => 20,
            'schedule' => [],
        ]),
    );
    $m->save_profile($worker, $admin, [
        'category' => 'Test category',
        'contract_duration' => 'indefinite',
        'weekly_minutes' => 1200,
        'pause_policy' => 'agreement',
        'complementary_percent' => 20,
        'schedule' => ['1' => [['09:00', '13:00']], '3' => [['09:00', '13:00']]],
    ]);
    $reject(
        fn() => $m->save_profile($worker, $admin, [
            'contract_duration' => 'indefinite',
            'weekly_minutes' => 1200,
            'pause_policy' => 'agreement',
            'schedule' => ['2' => [['08:00', '09:00'], ['10:00', '11:00'], ['12:00', '13:00'], ['14:00', '15:00']]],
        ]),
    );
    $profile = $m->profile($worker);
    $check($profile['contract_type'] === 'part' && $profile['schedule'][1] === [['09:00', '13:00']], 'contract saved');
    $check($m->month($worker, '2026-01')['summary']['days'][0]['planned'] === 14400, 'scheduled time per day');
    $m->request_correction($worker, '2026-01-12', 'Please review the test time');
    $reject(
        fn() => $m->issue_report($worker, $admin, '2026-01', [
            'ordinary' => '4:00:00',
            'complementary' => '0:00:00',
            'overtime' => '0:00:00',
        ]),
    );
    $request = $ci->db->get('attendance_requests')->row_array();
    $m->resolve_request($worker, $admin, (int) $request['id'], 'Reviewed the test records');
    $report_id = $m->issue_report($worker, $admin, '2026-01', [
        'month' => '2026-01',
        'ordinary' => '4:00:00',
        'complementary' => '0:00:00',
        'overtime' => '0:00:00',
    ]);
    $snapshot = $ci->db->get_where('attendance_reports', ['id' => $report_id])->row('snapshot');
    $check(count(json_decode($snapshot, true)['classification']) === 3, 'only hour classifications included');
    $m->deliver($worker, $admin, $report_id, 'Test paper delivery recorded');
    $segments[0]['end'] = '2026-01-12T14:00:00+01:00';
    $m->correct($worker, $admin, [
        'id' => $id,
        'revision' => $record['revision'],
        'reason' => 'Test corrected end time',
        'segments' => $segments,
    ]);
    $check(
        $ci->db->get_where('attendance_reports', ['id' => $report_id])->row('snapshot') === $snapshot,
        'issued report immutable after correction',
    );
    $check(count($m->history($worker, $id)) === 2, 'original and corrected retained');
    $check($m->month($worker, '2026-01')['summary']['counted_seconds'] === 18000, 'corrected month total');
    $ci->db->delete('users', ['id' => $worker]);
    $check(in_array($worker, array_column($m->employees(), 'id'), true), 'deleted login retained in historic roster');
    $check($m->month($worker, '2026-01')['summary']['counted_seconds'] === 18000, 'deleted login retains hours');
    $check(!$m->tampered($worker) && !$m->tampered(0), 'journal seals intact after normal use');
    $ci->db->update('attendance_entries', ['end_at' => '2026-01-12 15:00:00'], ['id' => $id]);
    $check($m->tampered($worker), 'a direct change of a record in the database is detected');
    $check(
        in_array('integrity', array_column($m->month($worker, '2026-01')['review_flags'], 'type'), true),
        'the review shows the tampering',
    );
    echo "PASS: clock transitions, duplicate/stale submissions, overlap rejection, correction history, report validation and snapshots, delivery, user deletion retention.\n";
} finally {
    foreach ($tables as $table) {
        $ci->db->query('DROP TABLE IF EXISTS `' . $prefix . $table . '`');
    }
    $ci->db->set_dbprefix($original_prefix);
}
