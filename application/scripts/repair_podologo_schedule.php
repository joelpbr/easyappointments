<?php
/** Round imported visits down to quarter-hours and use the existing assigned service duration. */
$apply = in_array('--apply', $_SERVER['argv'], true);
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir(dirname(__DIR__, 2));
require 'index.php';
require_once APPPATH . 'libraries/Podologo_schedule.php';
$ci = &get_instance();
$db = $ci->db->conn_id;
$p = $ci->db->dbprefix;
$query = "SELECT a.*,s.duration AS service_duration FROM {$p}appointments a
    JOIN {$p}podologo_import_links l ON l.source_table='VISITA' AND l.target_id=a.id
    JOIN {$p}services s ON s.id=a.id_services ORDER BY a.id";
$hash = static function (string $sql) use ($db): string {
    $h = hash_init('sha256');
    $r = $db->query($sql, MYSQLI_USE_RESULT);
    while ($row = $r->fetch_assoc()) {
        hash_update($h, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
    }
    $r->free();
    return hash_final($h);
};
if ((int) $db->query("SELECT GET_LOCK('podologo_import',0)")->fetch_row()[0] !== 1) {
    throw new RuntimeException('An import is running.');
}
try {
    $changes = [];
    $by_service = [];
    $count = 0;
    $expected = hash_init('sha256');
    $r = $db->query($query, MYSQLI_USE_RESULT);
    while ($row = $r->fetch_assoc()) {
        $times = Podologo_schedule::times($row['start_datetime'], $row['service_duration']);
        if ($times['start_datetime'] !== $row['start_datetime'] || $times['end_datetime'] !== $row['end_datetime']) {
            $changes[(int) $row['id']] = [
                'old_start' => $row['start_datetime'],
                'old_end' => $row['end_datetime'],
                'service' => $row['id_services'],
                'times' => $times,
            ];
            $by_service[$row['id_services']] = ($by_service[$row['id_services']] ?? 0) + 1;
        }
        foreach ($times as $key => $value) {
            $row[$key] = $value;
        }
        hash_update($expected, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
        $count++;
    }
    $r->free();
    $expected = hash_final($expected);
    $summary = ['imported_visits' => $count, 'to_adjust' => count($changes), 'changed_by_service' => $by_service];
    if (!$apply || !$changes) {
        echo json_encode($summary) . PHP_EOL;
        exit();
    }
    $markers_sql = "SELECT * FROM {$p}appointment_foot_markers ORDER BY id";
    $markers_before = $hash($markers_sql);
    $ci->load->library('database_backup');
    $backup = $ci->database_backup->create('before-visit-time-repair');
    $db->begin_transaction();
    try {
        $write = $db->prepare(
            "UPDATE {$p}appointments SET start_datetime=?,end_datetime=? WHERE id=? AND start_datetime=? AND end_datetime=? AND id_services=?",
        );
        foreach ($changes as $id => $change) {
            $write->execute([
                $change['times']['start_datetime'],
                $change['times']['end_datetime'],
                $id,
                $change['old_start'],
                $change['old_end'],
                $change['service'],
            ]);
            if ($write->affected_rows !== 1) {
                throw new RuntimeException('A visit changed during the repair; all time changes rolled back.');
            }
        }
        $write->close();
        if (!hash_equals($expected, $hash($query))) {
            throw new RuntimeException('Visit verification failed.');
        }
        if (!hash_equals($markers_before, $hash($markers_sql))) {
            throw new RuntimeException('Drawing verification failed.');
        }
        $db->commit();
        $summary += [
            'adjusted' => count($changes),
            'backup' => basename($backup),
            'all_other_appointment_fields_verified' => true,
            'all_markers_verified' => true,
        ];
        audit_log('updated', 'database', null, $summary);
        echo json_encode($summary) . PHP_EOL;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
} finally {
    $db->query("SELECT RELEASE_LOCK('podologo_import')");
}
