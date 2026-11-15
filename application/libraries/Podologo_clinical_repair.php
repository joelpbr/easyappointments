<?php defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Podologo_clinical.php';
require_once APPPATH . 'libraries/Podologo_clinical_v2.php';

/** Repair previous imports from their retained source rows; never re-import patients or markers. */
class Podologo_clinical_repair
{
    private mysqli $connection;
    private string $prefix;

    public function __construct()
    {
        $CI = &get_instance();
        $CI->db->initialize();
        $this->connection = $CI->db->conn_id;
        $this->prefix = $CI->db->dbprefix;
    }

    private function table(string $name): string
    {
        return '`' . $this->prefix . $name . '`';
    }

    public function rows(?string $only = null, bool $context_only = false): Generator
    {
        $tables = $only
            ? [$only]
            : array_column(
                $this->connection
                    ->query('SELECT DISTINCT source_table FROM ' . $this->table('podologo_source_rows'))
                    ->fetch_all(MYSQLI_ASSOC),
                'source_table',
            );
        $stmt = $this->connection->prepare(
            'SELECT `row_number`, source_json FROM ' .
                $this->table('podologo_source_rows') .
                ' WHERE source_table=? AND `row_number`>? ORDER BY `row_number` LIMIT 100',
        );
        try {
            foreach ($tables as $table) {
                if (
                    $context_only &&
                    in_array($table, ['VISITA', 'PACIENTE', 'PERSONAL', 'LOGPERSONAL', 'CONFIGURACION'], true)
                ) {
                    continue;
                }
                $last = 0;
                do {
                    $stmt->execute([$table, $last]);
                    $batch = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    foreach ($batch as $item) {
                        $last = (int) $item['row_number'];
                        yield [$table, json_decode($item['source_json'], true, 512, JSON_THROW_ON_ERROR)];
                    }
                } while ($batch);
            }
        } finally {
            $stmt->close();
        }
    }

    /** Match the first import's insertion order, then independently verify every identity/date and every marker link. */
    private function links(): array
    {
        $links = ['PACIENTE' => [], 'VISITA' => []];
        $saved = $this->connection->query('SELECT * FROM ' . $this->table('podologo_import_links'));
        while ($row = $saved->fetch_assoc()) {
            $links[$row['source_table']][(int) $row['source_id']] = (int) $row['target_id'];
        }
        if ($links['PACIENTE'] && $links['VISITA']) {
            return $links;
        }
        if ($links['PACIENTE'] || $links['VISITA']) {
            throw new RuntimeException('Incomplete legacy ID map.');
        }
        $patients = $this->connection
            ->query(
                'SELECT u.id,u.first_name,u.last_name,r.birth_date FROM ' .
                    $this->table('users') .
                    ' u JOIN ' .
                    $this->table('patient_records') .
                    ' r ON r.id_users_customer=u.id ORDER BY u.id',
            )
            ->fetch_all(MYSQLI_ASSOC);
        $i = 0;
        foreach ($this->rows('PACIENTE') as [, $row]) {
            $target = $patients[$i++] ?? null;
            if (
                !$target ||
                trim($target['first_name'] ?? '') !== Podologo_clinical::text($row['NOMBRE']) ||
                trim($target['last_name'] ?? '') !== Podologo_clinical::text($row['APELLIDOS']) ||
                ($target['birth_date'] ?? '') !== substr($row['FECHANACIMIENTO'] ?? '', 0, 10)
            ) {
                throw new RuntimeException('Cannot unambiguously recover the patient ID map; nothing changed.');
            }
            $links['PACIENTE'][$row['ID']] = (int) $target['id'];
        }
        if ($i !== count($patients)) {
            throw new RuntimeException('Patient count differs; nothing changed.');
        }
        unset($patients);
        $visits = $this->connection
            ->query(
                'SELECT a.id,a.id_users_customer,a.start_datetime FROM ' .
                    $this->table('appointments') .
                    ' a JOIN ' .
                    $this->table('services') .
                    " s ON s.id=a.id_services WHERE s.name='Historial Podologo' ORDER BY a.id",
            )
            ->fetch_all(MYSQLI_ASSOC);
        $i = 0;
        foreach ($this->rows('VISITA') as [, $row]) {
            $target = $visits[$i++] ?? null;
            if (
                !$target ||
                (int) $target['id_users_customer'] !== ($links['PACIENTE'][$row['IDPACIENTE']] ?? null) ||
                $target['start_datetime'] !== substr($row['FECHAVISITA'] ?? '', 0, 19)
            ) {
                throw new RuntimeException('Cannot unambiguously recover the visit ID map; nothing changed.');
            }
            $links['VISITA'][$row['ID']] = (int) $target['id'];
        }
        if ($i !== count($visits)) {
            throw new RuntimeException('Visit count differs; nothing changed.');
        }
        unset($visits);
        $markers = $this->connection->query(
            'SELECT original_external_id,id_appointments FROM ' .
                $this->table('appointment_foot_markers') .
                " WHERE original_external_id LIKE 'podologo:%'",
        );
        $targets = [];
        while ($marker = $markers->fetch_assoc()) {
            $targets[(int) substr($marker['original_external_id'], 9)] = (int) $marker['id_appointments'];
        }
        $count = 0;
        foreach ($this->rows('QUIROPODIAPATOLOGIACOLOCADA') as [, $row]) {
            if (($targets[$row['ID']] ?? null) !== ($links['VISITA'][$row['IDVISITA']] ?? null)) {
                throw new RuntimeException('Original marker/visit link mismatch; nothing changed.');
            }
            $count++;
        }
        if ($count !== count($targets)) {
            throw new RuntimeException('Original marker count differs; nothing changed.');
        }
        return $links;
    }

    public function repair(): array
    {
        $CI = &get_instance();
        if ((int) $this->connection->query("SELECT GET_LOCK('podologo_import',0)")->fetch_row()[0] !== 1) {
            throw new RuntimeException('An import is running.');
        }
        try {
            $links = $this->links();
            $context = Podologo_clinical::context(fn() => $this->rows(null, true));
            $previous_context = Podologo_clinical_v2::context(fn() => $this->rows(null, true));
            $services = [];
            foreach (
                $this->connection
                    ->query('SELECT id,name,record_template FROM ' . $this->table('services'))
                    ->fetch_all(MYSQLI_ASSOC)
                as $service
            ) {
                $services[$service['name']] = $service;
            }
            $CI->load->library('database_backup');
            $previous = $CI->database_backup->create('before-clinical-repair');
            $this->connection->begin_transaction();
            try {
                $save_link = $this->connection->prepare(
                    'INSERT IGNORE INTO ' . $this->table('podologo_import_links') . ' VALUES (?,?,?)',
                );
                foreach ($links as $table => $ids) {
                    foreach ($ids as $source => $target) {
                        $save_link->execute([$table, $source, $target]);
                    }
                }
                $save_link->close();
                $fields = ['reason', 'diagnosis', 'treatment', 'observations'];
                $read = $this->connection->prepare(
                    'SELECT * FROM ' . $this->table('appointment_records') . ' WHERE id_appointments=? FOR UPDATE',
                );
                $write = $this->connection->prepare(
                    'UPDATE ' .
                        $this->table('appointment_records') .
                        ' SET reason=?,diagnosis=?,treatment=?,observations=? WHERE id_appointments=?',
                );
                $title = $this->connection->prepare(
                    'UPDATE ' . $this->table('appointments') . ' SET service_name=?,id_services=? WHERE id=?',
                );
                $visits = $text_fields = 0;
                foreach ($this->rows('VISITA') as [, $row]) {
                    $id = $links['VISITA'][$row['ID']] ?? null;
                    if (!$id) {
                        throw new RuntimeException('Missing visit ID map.');
                    }
                    $read->execute([$id]);
                    $current = $read->get_result()->fetch_assoc();
                    if (!$current) {
                        throw new RuntimeException('Missing destination clinical record.');
                    }
                    $classification = Podologo_clinical::classification($row, $context['treatments'][$row['ID']] ?? '');
                    $service = $services[$classification['name']] ?? null;
                    if (!$service) {
                        throw new RuntimeException('Required existing treatment missing: ' . $classification['name']);
                    }
                    $original = Podologo_clinical::record(
                        $row,
                        $context['visits'][$row['ID']] ?? '',
                        $service['record_template'],
                    );
                    $previous_record = Podologo_clinical_v2::record(
                        $row,
                        $previous_context['visits'][$row['ID']] ?? '',
                        $service['record_template'],
                    );
                    $values = [];
                    $old_fields = [
                        'reason' => [
                            'MOTIVOVISITAQUIRO',
                            'MOTIVOVISITACIRUGIA',
                            'MOTIVOVISITABIO',
                            'MOTIVOVISITAPIERIESGO',
                        ],
                        'diagnosis' => [
                            'QUIROPODIADIAGNOSTICO',
                            'CIRUGIADIAGNOSTICO',
                            'BIOMECANICADIAGNOSTICO',
                            'PIERIESGODIAGNOSTICO',
                        ],
                        'treatment' => [
                            'QUIROPODIARECOMENDACION',
                            'CIRUGIARECOMENDACION',
                            'BIOMECANICARECOMENDACION',
                            'PIERIESGORECOMENDACION',
                        ],
                        'observations' => ['EVOLUCIONQUIRO', 'EVOLUCIONCIRUGIA', 'EVOLUCIONBIO', 'EVOLUCIONPIERIESGO'],
                    ];
                    foreach ($fields as $field) {
                        $old = [];
                        foreach ($old_fields[$field] as $key) {
                            $value = rtrim(Podologo_import::source_text($row[$key] ?? null) ?? '');
                            if ($value !== '') {
                                $old[] = $key . ': ' . $value;
                            }
                        }
                        $values[] = Podologo_clinical::merge(
                            $current[$field],
                            $original[$field],
                            $previous_record[$field],
                        );
                    }
                    $fitted = Podologo_clinical::fit(array_combine($fields, $values), $service['record_template']);
                    $values = array_map(static fn($key) => $fitted[$key] === '' ? null : $fitted[$key], $fields);
                    $write->execute([...$values, $id]);
                    $title->execute([$classification['name'], (int) $service['id'], $id]);
                    // Read back every record and verify all source clinical text is visible, not merely archived.
                    $read->execute([$id]);
                    $stored = $read->get_result()->fetch_assoc();
                    foreach ($fields as $field) {
                        if (($stored[$field] ?? null) !== $values[array_search($field, $fields, true)]) {
                            throw new RuntimeException('Clinical record verification failed.');
                        }
                    }
                    foreach ($row as $key => $value) {
                        if (!(is_string($value) || is_array($value)) || $key === 'FECHAVISITA') {
                            continue;
                        }
                        $text = Podologo_clinical::text($value);
                        if ($text === '') {
                            continue;
                        }
                        if (
                            !str_contains(
                                implode("\n", array_map(static fn($key) => $stored[$key] ?? '', $fields)),
                                $text,
                            )
                        ) {
                            throw new RuntimeException('Unmapped clinical text: ' . $key);
                        }
                        $text_fields++;
                    }
                    $visits++;
                }
                $read->close();
                $write->close();
                $title->close();
                $read = $this->connection->prepare(
                    'SELECT * FROM ' . $this->table('patient_records') . ' WHERE id_users_customer=? FOR UPDATE',
                );
                $write = $this->connection->prepare(
                    'UPDATE ' .
                        $this->table('patient_records') .
                        ' SET referral_source=?,profession=?,medical_history=?,allergies=? WHERE id_users_customer=?',
                );
                $patients = 0;
                foreach ($this->rows('PACIENTE') as [, $row]) {
                    $id = $links['PACIENTE'][$row['ID']] ?? null;
                    if (!$id) {
                        throw new RuntimeException('Missing patient ID map.');
                    }
                    $read->execute([$id]);
                    $current = $read->get_result()->fetch_assoc();
                    if (!$current) {
                        throw new RuntimeException('Missing patient record.');
                    }
                    $original = Podologo_clinical::patient($row, $context);
                    $previous_patient = Podologo_clinical_v2::patient($row, $previous_context);
                    $old_history =
                        rtrim(Podologo_import::source_text($row['ENFERMEDADES']) ?? '') .
                        "\n" .
                        rtrim(Podologo_import::source_text($row['HISTORIALANTIGUO']) ?? '');
                    $values = [];
                    foreach (['referral_source', 'profession', 'medical_history', 'allergies'] as $field) {
                        $values[] = Podologo_clinical::merge(
                            $current[$field],
                            $original[$field],
                            $previous_patient[$field] ?? null,
                        );
                    }
                    $write->execute([...$values, $id]);
                    $patients++;
                }
                $read->close();
                $write->close();
                $this->connection->query(
                    'UPDATE ' .
                        $this->table('services') .
                        " SET record_template='notes',is_active=0 WHERE name='Historial Podologo'",
                );
                $this->connection->commit();
                return [
                    'patients' => $patients,
                    'visits' => $visits,
                    'verified_text_fields' => $text_fields,
                    'backup' => basename($previous),
                ];
            } catch (Throwable $error) {
                $this->connection->rollback();
                throw $error;
            }
        } finally {
            $this->connection->query("SELECT RELEASE_LOCK('podologo_import')");
        }
    }
}
