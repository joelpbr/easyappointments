<?php
$apply = in_array('--apply', $_SERVER['argv'], true);
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir(dirname(__DIR__, 2));
require 'index.php';
require_once APPPATH . 'libraries/Podologo_clinical_repair.php';
require_once APPPATH . 'libraries/Podologo_providers.php';
$ci = &get_instance();
$db = $ci->db->conn_id;
$p = $ci->db->dbprefix;
$ids = [];
$r = $db->query(
    "SELECT u.id,u.first_name FROM {$p}users u JOIN {$p}roles r ON r.id=u.id_roles WHERE r.slug='provider' AND u.is_active=1",
);
while ($user = $r->fetch_assoc()) {
    $key =
        ['sara' => 'sara', 'sebas' => 'sebas', 'sebastian' => 'sebas', 'toni' => 'tonyi'][
            Podologo_unify::pathology_key($user['first_name'])
        ] ?? null;
    if ($key) {
        $ids[$key] = (int) $user['id'];
    }
}
if (count($ids) !== 3) {
    throw new RuntimeException('The existing clinic providers could not be identified.');
}
$links = [];
$r = $db->query(
    "SELECT l.source_id,a.id,a.id_users_provider FROM {$p}podologo_import_links l JOIN {$p}appointments a ON a.id=l.target_id WHERE l.source_table='VISITA'",
);
while ($row = $r->fetch_assoc()) {
    $links[(int) $row['source_id']] = $row;
}
$changes = [];
$reasons = [];
$missing_before = 0;
foreach ((new Podologo_clinical_repair())->rows('VISITA') as [, $row]) {
    $target = $links[$row['ID']] ?? null;
    if (!$target) {
        throw new RuntimeException('Missing original visit link.');
    }
    $current = $target['id_users_provider'] === null ? null : (int) $target['id_users_provider'];
    if (!$current) {
        $missing_before++;
    }
    $result = Podologo_providers::resolve($current, Podologo_providers::comments($row), $row['FECHAVISITA'], $ids);
    if ($result['id'] !== $current) {
        $changes[(int) $target['id']] = ['old' => $current, 'new' => $result['id']];
        $reasons[$result['reason']] = ($reasons[$result['reason']] ?? 0) + 1;
    }
}
$summary = ['missing_before' => $missing_before, 'to_change' => count($changes), 'reasons' => $reasons];
if (!$apply || !$changes) {
    echo json_encode($summary) . PHP_EOL;
    exit();
}
$hash = static function (string $sql, callable $transform) use ($db): string {
    $h = hash_init('sha256');
    $r = $db->query($sql, MYSQLI_USE_RESULT);
    while ($row = $r->fetch_assoc()) {
        hash_update($h, json_encode($transform($row), JSON_THROW_ON_ERROR) . "\n");
    }
    $r->free();
    return hash_final($h);
};
$appointment_sql = "SELECT * FROM {$p}appointments ORDER BY id";
$marker_sql = "SELECT * FROM {$p}appointment_foot_markers ORDER BY id";
$identity = static fn($row) => $row;
$expected = $hash($appointment_sql, static function ($row) use ($changes) {
    if (isset($changes[(int) $row['id']])) {
        $row['id_users_provider'] = (string) $changes[(int) $row['id']]['new'];
    }
    return $row;
});
$marker_expected = $hash($marker_sql, $identity);
if ((int) $db->query("SELECT GET_LOCK('podologo_import',0)")->fetch_row()[0] !== 1) {
    throw new RuntimeException('An import is running.');
}
try {
    $ci->load->library('database_backup');
    $backup = $ci->database_backup->create('before-provider-repair');
    $db->begin_transaction();
    try {
        $write = $db->prepare("UPDATE {$p}appointments SET id_users_provider=? WHERE id=? AND id_users_provider <=> ?");
        foreach ($changes as $id => $change) {
            $write->execute([$change['new'], $id, $change['old']]);
            if ($write->affected_rows !== 1) {
                throw new RuntimeException('An appointment changed during the repair; changes rolled back.');
            }
        }
        $write->close();
        if (!hash_equals($expected, $hash($appointment_sql, $identity))) {
            throw new RuntimeException('Appointment verification failed.');
        }
        if (!hash_equals($marker_expected, $hash($marker_sql, $identity))) {
            throw new RuntimeException('Drawing verification failed.');
        }
        if ((int) $db->query("SELECT COUNT(*) FROM {$p}appointments WHERE id_users_provider IS NULL")->fetch_row()[0]) {
            throw new RuntimeException('Unresolved providers remain.');
        }
        $db->commit();
        $summary += [
            'changed' => count($changes),
            'missing_after' => 0,
            'all_other_fields_verified' => true,
            'all_markers_verified' => true,
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
