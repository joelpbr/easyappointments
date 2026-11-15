<?php
$apply = in_array('--apply', $_SERVER['argv'], true);
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir(dirname(__DIR__, 2));
require 'index.php';
require_once APPPATH . 'libraries/Podologo_clinical_repair.php';
require_once APPPATH . 'libraries/Podologo_referrals.php';
$ci = &get_instance();
$db = $ci->db->conn_id;
$p = $ci->db->dbprefix;
$records = [];
$r = $db->query(
    "SELECT l.source_id,r.* FROM {$p}podologo_import_links l JOIN {$p}patient_records r ON r.id_users_customer=l.target_id WHERE l.source_table='PACIENTE'",
);
while ($row = $r->fetch_assoc()) {
    $records[(int) $row['source_id']] = $row;
}
$changes = [];
$source_count = 0;
$merged = 0;
$already = 0;
foreach ((new Podologo_clinical_repair())->rows('PACIENTE') as [, $row]) {
    $source = Podologo_clinical::text($row['EMAIL2'] ?? null);
    if ($source === '') {
        continue;
    }
    $source_count++;
    $record = $records[$row['ID']] ?? null;
    if (!$record) {
        throw new RuntimeException('Missing original patient record link.');
    }
    $next = Podologo_referrals::merge($record['referral_source'], $source);
    if ($next === ($record['referral_source'] ?? '')) {
        $already++;
        continue;
    }
    $changes[(int) $record['id']] = ['old' => $record['referral_source'], 'new' => $next];
    if (trim($record['referral_source'] ?? '') !== '') {
        $merged++;
    }
}
$summary = [
    'email2_provenance_values' => $source_count,
    'to_update' => count($changes),
    'preserved_existing_provenance' => $merged,
    'already_present' => $already,
];
if (!$apply || !$changes) {
    echo json_encode($summary) . PHP_EOL;
    exit();
}
$hash = static function (string $sql, callable $convert) use ($db): string {
    $h = hash_init('sha256');
    $r = $db->query($sql, MYSQLI_USE_RESULT);
    while ($row = $r->fetch_assoc()) {
        hash_update($h, json_encode($convert($row), JSON_THROW_ON_ERROR) . "\n");
    }
    $r->free();
    return hash_final($h);
};
$sql = "SELECT * FROM {$p}patient_records ORDER BY id";
$expected = $hash($sql, static function ($row) use ($changes) {
    if (isset($changes[(int) $row['id']])) {
        $row['referral_source'] = $changes[(int) $row['id']]['new'];
    }
    return $row;
});
$users_sql = "SELECT * FROM {$p}users ORDER BY id";
$identity = static fn($row) => $row;
$users_before = $hash($users_sql, $identity);
if ((int) $db->query("SELECT GET_LOCK('podologo_import',0)")->fetch_row()[0] !== 1) {
    throw new RuntimeException('An import is running.');
}
try {
    $ci->load->library('database_backup');
    $backup = $ci->database_backup->create('before-provenance-repair');
    $db->begin_transaction();
    try {
        $write = $db->prepare("UPDATE {$p}patient_records SET referral_source=? WHERE id=? AND referral_source <=> ?");
        foreach ($changes as $id => $change) {
            $write->execute([$change['new'], $id, $change['old']]);
            if ($write->affected_rows !== 1) {
                throw new RuntimeException('A patient was edited during repair; rolled back.');
            }
        }
        $write->close();
        if (!hash_equals($expected, $hash($sql, $identity))) {
            throw new RuntimeException('Patient record verification failed.');
        }
        if (!hash_equals($users_before, $hash($users_sql, $identity))) {
            throw new RuntimeException('Patient identity/email verification failed.');
        }
        $db->commit();
        $summary += [
            'updated' => count($changes),
            'all_other_fields_verified' => true,
            'emails_unchanged' => true,
            'backup' => basename($backup),
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
