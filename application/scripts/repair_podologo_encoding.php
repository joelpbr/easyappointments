<?php
// Preview by default. --apply backs up and atomically repairs readable text only.
$apply = in_array('--apply', $_SERVER['argv'], true);
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir(dirname(__DIR__, 2));
require 'index.php';
require_once APPPATH . 'libraries/Podologo_text_encoding.php';
$ci = &get_instance();
$db = $ci->db->conn_id;
$p = $ci->db->dbprefix;
$fields = [
    'users' => ['first_name', 'last_name', 'address', 'city', 'state', 'zip_code', 'notes'],
    'patient_records' => ['referral_source', 'profession', 'physical_activity', 'medical_history', 'allergies'],
    'appointment_records' => ['reason', 'diagnosis', 'treatment', 'observations'],
    'appointments' => ['location', 'notes', 'service_name'],
    'appointment_foot_markers' => ['annotation_title', 'annotation_text'],
    'fiscal_parties' => ['document_number', 'name', 'address', 'zip_code', 'city', 'province', 'country'],
    'podology_pathologies' => ['name', 'description'],
    'podology_diagram_types' => ['name'],
    'services' => ['name', 'description', 'location'],
    'service_categories' => ['name', 'description'],
    'blocked_periods' => ['name', 'notes'],
    'holidays' => ['name'],
    'consents' => ['first_name', 'last_name'],
];
$hash = static function (string $table) use ($db, $p): string {
    $h = hash_init('sha256');
    $result = $db->query("SELECT * FROM `{$p}{$table}` ORDER BY id", MYSQLI_USE_RESULT);
    while ($row = $result->fetch_assoc()) {
        hash_update($h, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
    }
    $result->free();
    return hash_final($h);
};
if ($apply && (int) $db->query("SELECT GET_LOCK('podologo_import',0)")->fetch_row()[0] !== 1) {
    throw new RuntimeException('An import or repair is running.');
}
try {
    $changes = [];
    $expected = [];
    $summary = [
        'fields_to_update' => [],
        'rows_to_update' => 0,
        'remaining_replacement_characters' => 0,
        'remaining_c1_controls' => 0,
    ];
    foreach ($fields as $table => $columns) {
        $h = hash_init('sha256');
        $result = $db->query("SELECT * FROM `{$p}{$table}` ORDER BY id", MYSQLI_USE_RESULT);
        while ($row = $result->fetch_assoc()) {
            foreach ($columns as $column) {
                if ($row[$column] === null) {
                    continue;
                }
                $old = $row[$column];
                $new = Podologo_text_encoding::repair($old);
                $summary['remaining_replacement_characters'] += substr_count($new, "\u{fffd}");
                $summary['remaining_c1_controls'] += preg_match_all('/[\x{0080}-\x{009f}]/u', $new);
                if ($old === $new) {
                    continue;
                }
                $changes[$table][(int) $row['id']][$column] = ['old' => $old, 'new' => $new];
                $key = $table . '.' . $column;
                $summary['fields_to_update'][$key] = ($summary['fields_to_update'][$key] ?? 0) + 1;
                $row[$column] = $new;
            }
            hash_update($h, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
        }
        $result->free();
        $expected[$table] = hash_final($h);
        $summary['rows_to_update'] += count($changes[$table] ?? []);
    }
    if (!$apply || !$changes) {
        echo json_encode($summary, JSON_THROW_ON_ERROR) . PHP_EOL;
        exit();
    }
    $ci->load->library('database_backup');
    $backup = $ci->database_backup->create('before-text-encoding-repair');
    $db->begin_transaction();
    try {
        foreach ($changes as $table => $rows) {
            $statements = [];
            foreach ($rows as $id => $columns) {
                foreach ($columns as $column => $change) {
                    $write = $statements[$column] ??= $db->prepare(
                        "UPDATE `{$p}{$table}` SET `{$column}`=? WHERE id=? AND BINARY `{$column}` <=> BINARY ?",
                    );
                    $write->execute([$change['new'], $id, $change['old']]);
                    if ($write->affected_rows !== 1) {
                        throw new RuntimeException('Text was edited during repair; rolled back.');
                    }
                }
            }
            foreach ($statements as $write) {
                $write->close();
            }
        }
        foreach ($expected as $table => $digest) {
            if (!hash_equals($digest, $hash($table))) {
                throw new RuntimeException('Text/unchanged field verification failed: ' . $table);
            }
        }
        $db->commit();
        $summary += [
            'applied' => true,
            'all_other_fields_verified' => true,
            'drawing_coordinates_verified' => true,
            'backup' => basename($backup),
        ];
        audit_log('updated', 'database', null, $summary);
        echo json_encode($summary, JSON_THROW_ON_ERROR) . PHP_EOL;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
} finally {
    if ($apply) {
        $db->query("SELECT RELEASE_LOCK('podologo_import')");
    }
}
