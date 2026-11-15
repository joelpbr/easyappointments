<?php
/** Remove a legacy form-copy flag from imported clinical prose, without rebuilding any record. */
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir(dirname(__DIR__, 2));
require 'index.php';
require_once APPPATH . 'libraries/Podologo_clinical.php';
$ci = &get_instance();
$db = $ci->db->conn_id;
$p = $ci->db->dbprefix;
$rows = $db
    ->query(
        "SELECT r.* FROM {$p}appointment_records r JOIN {$p}podologo_import_links l
    ON l.source_table='VISITA' AND l.target_id=r.id_appointments
    WHERE CONCAT_WS(' ',r.reason,r.diagnosis,r.treatment,r.observations) LIKE '%Piesigualesbio%'",
    )
    ->fetch_all(MYSQLI_ASSOC);
$changes = [];
foreach ($rows as $row) {
    $fields = [];
    foreach (['reason', 'diagnosis', 'treatment', 'observations'] as $field) {
        $clean = Podologo_clinical::remove_equal_feet_flag($row[$field]);
        if ($clean !== $row[$field]) {
            $fields[$field] = $clean;
        }
    }
    if ($fields) {
        $changes[(int) $row['id']] = $fields;
    }
}
if (!$changes) {
    echo "No imported flags remain.\n";
    exit();
}
$ci->load->library('database_backup');
$backup = $ci->database_backup->create('before-equal-feet-flag-cleanup');
$db->begin_transaction();
try {
    foreach ($changes as $id => $fields) {
        $columns = implode(',', array_map(static fn($key) => "`$key`=?", array_keys($fields)));
        $stmt = $db->prepare("UPDATE {$p}appointment_records SET $columns WHERE id=?");
        $stmt->execute([...array_values($fields), $id]);
        $stmt->close();
    }
    // Verify all columns of every selected record, including the columns this operation does not change.
    $read = $db->prepare("SELECT * FROM {$p}appointment_records WHERE id=?");
    foreach ($rows as $row) {
        $read->execute([$row['id']]);
        $actual = $read->get_result()->fetch_assoc();
        foreach ($changes[(int) $row['id']] ?? [] as $key => $value) {
            $row[$key] = $value;
        }
        foreach ($row as $column => $value) {
            $expected = $value === null ? null : (string) $value;
            $stored = $actual[$column] === null ? null : (string) $actual[$column];
            if ($expected !== $stored) {
                throw new RuntimeException('Clinical record verification failed: ' . $column);
            }
        }
    }
    $read->close();
    $db->commit();
    $result = [
        'cleaned_visits' => count($changes),
        'backup' => basename($backup),
        'all_other_columns_verified' => true,
    ];
    audit_log('updated', 'database', null, $result);
    echo json_encode($result) . PHP_EOL;
} catch (Throwable $error) {
    $db->rollback();
    throw $error;
}
