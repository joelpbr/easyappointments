<?php
/** CLI data maintenance, using existing catalogs and accounts. Dry run unless --apply is supplied. */
$apply = in_array('--apply', $_SERVER['argv'], true);
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir(dirname(__DIR__, 2));
require 'index.php';
require_once APPPATH . 'libraries/Podologo_unify.php';
require_once APPPATH . 'libraries/Podologo_clinical_repair.php';
$ci = &get_instance();
$db = $ci->db->conn_id;
$p = $ci->db->dbprefix;
$scalar = static fn($sql) => $db->query($sql)->fetch_row()[0];
$pathologies = $db->query("SELECT * FROM {$p}podology_pathologies ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$plan = Podologo_unify::pathology_plan($pathologies);
$staff = $db
    ->query(
        "SELECT u.*,s.username FROM {$p}users u JOIN {$p}roles r ON r.id=u.id_roles
    LEFT JOIN {$p}user_settings s ON s.id_users=u.id WHERE r.slug='provider'",
    )
    ->fetch_all(MYSQLI_ASSOC);
$destinations = [];
$merge_staff = [];
foreach ($staff as $user) {
    $name = Podologo_unify::pathology_key($user['first_name']);
    if ((int) $user['is_active'] && in_array($name, ['sara', 'sebas', 'toni'], true)) {
        $destinations[$name] = $user;
    }
}
if (count($destinations) !== 3) {
    throw new RuntimeException('Expected existing Sara, Sebas and Toñi providers.');
}
foreach ($staff as $user) {
    if (!str_starts_with($user['username'] ?? '', 'podologo-')) {
        continue;
    }
    $name = Podologo_unify::pathology_key($user['first_name']);
    $key = ['sara' => 'sara', 'sebastian' => 'sebas'][$name] ?? null;
    if ($key) {
        $merge_staff[(int) $user['id']] = (int) $destinations[$key]['id'];
    }
}
$tonyi = (int) $destinations['toni']['id'];
$t_visits = [];
$read = $db->query("SELECT a.id,a.start_datetime,a.notes,r.reason,r.diagnosis,r.treatment,r.observations
    FROM {$p}appointments a LEFT JOIN {$p}appointment_records r ON r.id_appointments=a.id WHERE a.start_datetime>='2024-01-01'");
while ($row = $read->fetch_assoc()) {
    foreach (['notes', 'reason', 'diagnosis', 'treatment', 'observations'] as $field) {
        if (Podologo_unify::tonyi_comment($row[$field], $row['start_datetime'])) {
            $t_visits[(int) $row['id']] = true;
            break;
        }
    }
}
$links = [];
$result = $db->query("SELECT source_id,target_id FROM {$p}podologo_import_links WHERE source_table='VISITA'");
while ($row = $result->fetch_assoc()) {
    $links[(int) $row['source_id']] = (int) $row['target_id'];
}
foreach ((new Podologo_clinical_repair())->rows('VISITA') as [, $row]) {
    if (($row['FECHAVISITA'] ?? '') < '2024-01-01' || !isset($links[$row['ID']])) {
        continue;
    }
    foreach ($row as $key => $value) {
        if ($key === 'FECHAVISITA' || !(is_string($value) || is_array($value))) {
            continue;
        }
        if (Podologo_unify::tonyi_comment(Podologo_clinical::text($value), $row['FECHAVISITA'])) {
            $t_visits[$links[$row['ID']]] = true;
            break;
        }
    }
}
$summary = [
    'pathology_merges' => $plan['merges'],
    'provider_merges' => $merge_staff,
    'tonyi_id' => $tonyi,
    'tonyi_visits_from_2024' => count($t_visits),
];
if (!$apply) {
    echo json_encode($summary, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit();
}

$hash = static function (string $sql, callable $convert) use ($db): string {
    $h = hash_init('sha256');
    $rows = $db->query($sql, MYSQLI_USE_RESULT);
    while ($row = $rows->fetch_assoc()) {
        hash_update($h, json_encode($convert($row), JSON_THROW_ON_ERROR) . "\n");
    }
    $rows->free();
    return hash_final($h);
};
$marker_sql = "SELECT * FROM {$p}appointment_foot_markers ORDER BY id";
$appointments_sql = "SELECT * FROM {$p}appointments ORDER BY id";
$marker_expected = $hash($marker_sql, static function ($row) use ($plan) {
    $row['id_podology_pathologies'] =
        (string) ($plan['merges'][(int) $row['id_podology_pathologies']] ?? $row['id_podology_pathologies']);
    return $row;
});
$appointments_expected = $hash($appointments_sql, static function ($row) use ($merge_staff, $tonyi, $t_visits) {
    $row['id_users_provider'] = isset($t_visits[(int) $row['id']])
        ? (string) $tonyi
        : (isset($merge_staff[(int) $row['id_users_provider']])
            ? (string) $merge_staff[(int) $row['id_users_provider']]
            : $row['id_users_provider']);
    return $row;
});
if ((int) $scalar("SELECT GET_LOCK('podologo_import',0)") !== 1) {
    throw new RuntimeException('An import is running.');
}
try {
    $ci->load->library('database_backup');
    $backup = $ci->database_backup->create('before-catalog-unification');
    $db->begin_transaction();
    try {
        $affected_markers = 0;
        foreach ($plan['merges'] as $old => $new) {
            $db->query(
                "UPDATE {$p}appointment_foot_markers SET id_podology_pathologies=$new WHERE id_podology_pathologies=$old",
            );
            $affected_markers += $db->affected_rows;
            // Keep a clinic description if the chosen canonical entry has none.
            $db->query(
                "UPDATE {$p}podology_pathologies dst JOIN {$p}podology_pathologies src ON src.id=$old SET dst.description=src.description WHERE dst.id=$new AND COALESCE(dst.description,'')='' AND COALESCE(src.description,'')<>''",
            );
            $db->query(
                "UPDATE {$p}podologo_import_links SET target_id=$new WHERE source_table='QUIROPODIAPATOLOGIA' AND target_id=$old",
            );
            $db->query("DELETE FROM {$p}podology_pathologies WHERE id=$old");
        }
        $rename = $db->prepare("UPDATE {$p}podology_pathologies SET name=? WHERE id=?");
        foreach ($plan['names'] as $id => $name) {
            $rename->execute([$name, $id]);
        }
        $rename->close();
        foreach ($merge_staff as $old => $new) {
            if ((int) $scalar("SELECT COUNT(*) FROM {$p}working_plan_exceptions WHERE id_users_provider=$old")) {
                throw new RuntimeException('Review imported work schedule exceptions before merging.');
            }
            if ((int) $scalar("SELECT COUNT(*) FROM {$p}appointments WHERE id_users_customer=$old")) {
                throw new RuntimeException('Provider also used as patient; merge needs review.');
            }
            $db->query("UPDATE {$p}appointments SET id_users_provider=$new WHERE id_users_provider=$old");
            $db->query("UPDATE {$p}users SET id_users_preferred_provider=$new WHERE id_users_preferred_provider=$old");
            $db->query("UPDATE {$p}consents SET id_users=$new WHERE id_users=$old");
            foreach (
                [
                    'services_providers' => ['id_users', 'id_services'],
                    'secretaries_providers' => ['id_users_provider', 'id_users_secretary'],
                    'blocked_periods_providers' => ['id_users_provider', 'id_blocked_periods'],
                ]
                as $table => [$column, $other]
            ) {
                $db->query(
                    "INSERT IGNORE INTO {$p}$table ($column,$other) SELECT $new,$other FROM {$p}$table WHERE $column=$old",
                );
                $db->query("DELETE FROM {$p}$table WHERE $column=$old");
            }
            // Retain existing logins/settings. Fill missing contact fields from the imported record.
            foreach (['last_name', 'email', 'phone_number', 'address', 'city', 'state', 'zip_code'] as $column) {
                $db->query(
                    "UPDATE {$p}users dst JOIN {$p}users src ON src.id=$old SET dst.$column=src.$column WHERE dst.id=$new AND COALESCE(dst.$column,'')='' AND COALESCE(src.$column,'')<>''",
                );
            }
            $db->query("DELETE FROM {$p}users WHERE id=$old");
        }
        $assign = $db->prepare(
            "UPDATE {$p}appointments SET id_users_provider=? WHERE id=? AND start_datetime>='2024-01-01'",
        );
        $tonyi_changed = 0;
        foreach ($t_visits as $id => $_) {
            $assign->execute([$tonyi, $id]);
            $tonyi_changed += $assign->affected_rows;
        }
        $assign->close();
        $identity = static fn($row) => $row;
        if (!hash_equals($marker_expected, $hash($marker_sql, $identity))) {
            throw new RuntimeException('Marker verification failed.');
        }
        if (!hash_equals($appointments_expected, $hash($appointments_sql, $identity))) {
            throw new RuntimeException('Appointment verification failed.');
        }
        $db->commit();
        $summary += [
            'relinked_markers' => $affected_markers,
            'tonyi_changed' => $tonyi_changed,
            'backup' => basename($backup),
            'all_marker_fields_verified' => true,
            'all_appointment_fields_verified' => true,
        ];
        audit_log('updated', 'database', null, $summary);
        echo json_encode($summary, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
} finally {
    $db->query("SELECT RELEASE_LOCK('podologo_import')");
}
