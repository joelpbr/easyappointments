<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Import the original Firebird database through the existing backup upload flow. */
class Podologo_import
{
    public static function matches(string $path): bool
    {
        $header = @file_get_contents($path, false, null, 0, 64);
        return $header !== false &&
            strlen($header) === 64 &&
            ord($header[0]) === 1 &&
            unpack('v', substr($header, 18, 2))[1] === 0x800b;
    }

    /** The old canvas dimensions are supplied separately: Firebird does not store them. */
    public static function diagrams(int $width, int $height): array
    {
        if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000) {
            throw new InvalidArgumentException('Invalid original drawing canvas dimensions.');
        }
        $diagrams = [];
        foreach (range(0, 3) as $id) {
            $diagrams[$id] = ['width' => $width, 'height' => $height, 'file' => 'fotos_pies/' . ($id + 1) . '.png'];
        }
        return $diagrams;
    }

    public static function icon(string $name): string
    {
        $name = mb_strtolower($name);
        foreach (
            [
                'hiperqueratos' => 'hiperqueratosi.png',
                'onicomicos' => 'onicomicosi.png',
                'onicocript' => 'onicocriptosi.png',
                'heloma' => 'heloma.png',
                'papil' => 'papil-loma.png',
            ]
            as $match => $file
        ) {
            if (str_contains($name, $match)) {
                return $file;
            }
        }
        return 'missing.svg';
    }

    public static function marker(array $row, array $diagrams, int $appointment, int $pathology): array
    {
        $diagram = $row['IDIMAGENCOLOCADA'];
        if (!is_int($diagram) || !isset($diagrams[$diagram])) {
            throw new InvalidArgumentException('Unknown original foot diagram.');
        }
        $marker = [
            'id_appointments' => $appointment,
            'id_podology_pathologies' => $pathology,
            'id_podology_diagram_types' => $diagram,
            'original_external_id' => 'podologo:' . $row['ID'],
            'is_healed' => $row['CURADO'] === 1 ? 1 : 0,
            'annotation_title' => self::source_text($row['TITULOTEXTO']),
            'annotation_text' => self::source_text($row['TEXTOTEXTO']),
            'coordinate_width' => $diagrams[$diagram]['width'],
            'coordinate_height' => $diagrams[$diagram]['height'],
            'background_image' => $diagrams[$diagram]['file'],
        ];
        foreach (['x1' => 'X_1', 'y1' => 'Y_1', 'x2' => 'X_2', 'y2' => 'Y_2'] as $target => $source) {
            if (!is_int($row[$source]) || $row[$source] < -2147483648 || $row[$source] > 2147483647) {
                throw new InvalidArgumentException('Invalid original marker coordinate.');
            }
            $marker[$target] = $row[$source];
        }
        return $marker;
    }

    public static function source_text(mixed $value): ?string
    {
        require_once APPPATH . 'libraries/Podologo_text_encoding.php';
        if ($value === null) {
            return null;
        }
        if (is_array($value) && isset($value['base64'])) {
            $bytes = base64_decode($value['base64'], true);
            if ($bytes === false) {
                throw new InvalidArgumentException('Invalid original binary text.');
            }
            return Podologo_text_encoding::decode($bytes);
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid original text value.');
        }
        return Podologo_text_encoding::repair($value);
    }

    /** Stream, validate, then commit atomically; retain all original fields in the source archive table. */
    public function restore(string $source, int $width, int $height): string
    {
        $CI = &get_instance();
        $assets = ['diagrams' => self::diagrams($width, $height)];
        if (!$CI->db->table_exists('podologo_source_rows')) {
            throw new RuntimeException('Run the application migrations before importing Podologo.');
        }
        if ($CI->db->count_all('podologo_source_rows')) {
            throw new InvalidArgumentException(lang('backup_podologo_already_imported'));
        }
        $work = sys_get_temp_dir() . '/podologo-' . bin2hex(random_bytes(16));
        if (!mkdir($work, 0700) || !copy($source, $work . '/source.db')) {
            throw new RuntimeException('Could not prepare the database conversion.');
        }
        try {
            $python = getenv('PODOLOGO_PYTHON') ?: '/opt/podologo/venv/bin/python';
            $process = proc_open(
                [$python, APPPATH . 'scripts/read_podologo.py', $work . '/source.db', $work . '/rows.jsonl'],
                [
                    0 => ['pipe', 'r'],
                    1 => ['file', $work . '/reader.log', 'a'],
                    2 => ['file', $work . '/reader.log', 'a'],
                ],
                $pipes,
            );
            if (!is_resource($process)) {
                throw new RuntimeException('The Podologo database reader is not installed.');
            }
            fclose($pipes[0]);
            if (proc_close($process) !== 0) {
                throw new InvalidArgumentException(lang('backup_podologo_reader_failed'));
            }
            return $this->import_rows($work . '/rows.jsonl', $assets);
        } finally {
            foreach (glob($work . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($work);
        }
    }

    private static function rows(string $path): Generator
    {
        $file = fopen($path, 'rb');
        try {
            while (($line = fgets($file)) !== false) {
                yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            fclose($file);
        }
    }

    public function import_rows(string $path, array $assets): string
    {
        require_once APPPATH . 'libraries/Podologo_clinical.php';
        require_once APPPATH . 'libraries/Podologo_unify.php';
        require_once APPPATH . 'libraries/Podologo_documents.php';
        require_once APPPATH . 'libraries/Podologo_schedule.php';
        require_once APPPATH . 'libraries/Podologo_providers.php';
        $CI = &get_instance();
        $db = $CI->db;
        $db->initialize();
        $connection = $db->conn_id;
        $prefix = $db->dbprefix;
        $patients = $visits = $pathologies = $staff = $counts = [];
        $complete = null;
        // Only ID maps and the small pathology catalog are kept in memory.
        foreach (self::rows($path) as [$table, $row]) {
            if ($table === '__complete__') {
                $complete = $row;
                continue;
            }
            $counts[$table] = ($counts[$table] ?? 0) + 1;
            if ($table === 'PACIENTE') {
                $patients[$row['ID']] = null;
            }
            if ($table === 'VISITA') {
                $visits[$row['ID']] = null;
            }
            if ($table === 'QUIROPODIAPATOLOGIA') {
                $pathologies[$row['ID']] = $row;
            }
        }
        if ($complete === null || array_filter($complete) !== $counts || !$patients || !$visits || !$pathologies) {
            throw new InvalidArgumentException('Incomplete Podologo database export.');
        }
        foreach (self::rows($path) as [$table, $row]) {
            if ($table === 'VISITA' && !array_key_exists($row['IDPACIENTE'], $patients)) {
                throw new InvalidArgumentException('A visit has no original patient.');
            }
            if ($table === 'QUIROPODIAPATOLOGIACOLOCADA') {
                if (
                    !array_key_exists($row['IDVISITA'], $visits) ||
                    !isset($pathologies[$row['IDQUIROPODIAPATOLOGIA']])
                ) {
                    throw new InvalidArgumentException('An original drawing has a broken visit or pathology link.');
                }
                self::marker($row, $assets['diagrams'], 1, 1);
            }
        }
        $CI->load->library('database_backup');
        $context = Podologo_clinical::context(static fn() => self::rows($path));
        $previous = $CI->database_backup->create('before-podologo-import');
        $statements = [];
        $insert = function (string $table, array $row) use ($connection, $prefix, &$statements): int {
            $key = $table . ':' . implode(',', array_keys($row));
            if (!isset($statements[$key])) {
                $columns = '`' . implode('`,`', array_keys($row)) . '`';
                $sql =
                    'INSERT INTO `' .
                    $prefix .
                    $table .
                    '` (' .
                    $columns .
                    ') VALUES (' .
                    implode(',', array_fill(0, count($row), '?')) .
                    ')';
                $statements[$key] = $connection->prepare($sql);
            }
            if (!$statements[$key]->execute(array_values($row))) {
                throw new RuntimeException('Could not store the imported ' . $table . ' row.');
            }
            return (int) $connection->insert_id;
        };
        $text = static fn($row, $key) => rtrim(self::source_text($row[$key] ?? null) ?? '');
        $roles = [];
        foreach ($db->get('roles')->result_array() as $role) {
            $roles[$role['slug']] = (int) $role['id'];
        }
        $connection->begin_transaction();
        try {
            // Serialize imports; a second upload must never duplicate all patients and drawings.
            $lock = $connection->query("SELECT GET_LOCK('podologo_import', 0)")->fetch_row()[0];
            if (!$lock || $connection->query('SELECT 1 FROM `' . $prefix . 'podologo_source_rows` LIMIT 1')->num_rows) {
                throw new InvalidArgumentException(lang('backup_podologo_already_imported'));
            }
            $services = [];
            foreach ($db->get('services')->result_array() as $existing_service) {
                $services[$existing_service['name']] = $existing_service;
            }
            $clinic_provider_ids = [];
            foreach (
                $db->get_where('users', ['id_roles' => $roles['provider'], 'is_active' => 1])->result_array()
                as $clinic_provider
            ) {
                $alias =
                    ['sara' => 'sara', 'sebas' => 'sebas', 'sebastian' => 'sebas', 'toni' => 'tonyi'][
                        Podologo_unify::pathology_key($clinic_provider['first_name'])
                    ] ?? null;
                if ($alias) {
                    $clinic_provider_ids[$alias] = (int) $clinic_provider['id'];
                }
            }
            $service = (int) ($services['Quiropodia']['id'] ?? 0);
            if (!$service) {
                throw new RuntimeException('Existing treatment catalog required.');
            }
            foreach ($pathologies as $id => $row) {
                $name = Podologo_unify::display_name($text($row, 'PATOLOGIA'));
                $key = Podologo_unify::pathology_key($name);
                $existing = null;
                foreach ($db->get('podology_pathologies')->result_array() as $candidate) {
                    if (Podologo_unify::pathology_key($candidate['name']) === $key) {
                        $existing = $candidate;
                        break;
                    }
                }
                $pathologies[$id] = $existing
                    ? (int) $existing['id']
                    : $insert('podology_pathologies', [
                        'name' => $name,
                        'image_filename' => self::icon($text($row, 'PATOLOGIA')),
                    ]);
            }
            $numbers = [];
            foreach (self::rows($path) as [$table, $row]) {
                if ($table === '__complete__') {
                    continue;
                }
                $numbers[$table] = ($numbers[$table] ?? 0) + 1;
                $insert('podologo_source_rows', [
                    'source_table' => $table,
                    'row_number' => $numbers[$table],
                    'source_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
                if ($table === 'PERSONAL') {
                    $key = Podologo_unify::pathology_key($text($row, 'NOMBRE'));
                    $aliases = ['sara' => ['sara'], 'sebastian' => ['sebas', 'sebastian']][$key] ?? [];
                    $existing_provider = null;
                    foreach (
                        $db->get_where('users', ['id_roles' => $roles['provider'], 'is_active' => 1])->result_array()
                        as $candidate
                    ) {
                        if (in_array(Podologo_unify::pathology_key($candidate['first_name']), $aliases, true)) {
                            $existing_provider = $candidate;
                            break;
                        }
                    }
                    if ($existing_provider) {
                        $staff[$row['ID']] = (int) $existing_provider['id'];
                        continue;
                    }
                    $staff[$row['ID']] = $insert('users', [
                        'first_name' => $text($row, 'NOMBRE'),
                        'last_name' => $text($row, 'APELLIDOS'),
                        'id_roles' => $roles['provider'],
                        'email' => $text($row, 'EMAIL'),
                        'phone_number' => $text($row, 'TELEFONO'),
                        'is_active' => 0,
                    ]);
                    $insert('services_providers', ['id_users' => $staff[$row['ID']], 'id_services' => $service]);
                    $insert('user_settings', [
                        'id_users' => $staff[$row['ID']],
                        'username' => 'podologo-' . $row['ID'] . '-' . bin2hex(random_bytes(4)),
                        'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    ]);
                }
                if ($table === 'PACIENTE') {
                    $phone = $text($row, 'TELEFONOMOVIL') ?: $text($row, 'TELEFONOCASA');
                    $patients[$row['ID']] = $insert('users', [
                        'first_name' => $text($row, 'NOMBRE'),
                        'last_name' => $text($row, 'APELLIDOS'),
                        'id_roles' => $roles['customer'],
                        'language' => setting('default_language') ?: 'catalan',
                        'email' => $text($row, 'EMAIL1'),
                        'phone_number' => $phone,
                        'phone_normalized' => normalize_phone($phone),
                        'address' => $text($row, 'DOMICILIO'),
                        'city' => $text($row, 'POBLACION'),
                        'state' => $text($row, 'PROVINCIA'),
                        'zip_code' => $text($row, 'CODIGOPOSTAL'),
                        'notes' => $text($row, 'COMENTARIOS'),
                    ]);
                    $insert(
                        'patient_records',
                        [
                            'id_users_customer' => $patients[$row['ID']],
                            'birth_date' => $row['FECHANACIMIENTO'] ? substr($row['FECHANACIMIENTO'], 0, 10) : null,
                        ] + Podologo_clinical::patient($row, $context),
                    );
                    $insert('podologo_import_links', [
                        'source_table' => 'PACIENTE',
                        'source_id' => $row['ID'],
                        'target_id' => $patients[$row['ID']],
                    ]);
                    $document = Podologo_documents::convert($text($row, 'NIF'));
                    if ($document) {
                        $insert(
                            'fiscal_parties',
                            ['id_users_customer' => $patients[$row['ID']], 'relationship' => 'patient'] + $document,
                        );
                    }
                }
                if ($table === 'VISITA') {
                    $start = $row['FECHAVISITA'];
                    if (!$start) {
                        throw new InvalidArgumentException('An original visit has no date.');
                    }
                    $provider =
                        $staff[$row['QIDESPECIALISTA']] ??
                        ($staff[$row['CIDESPECIALISTA']] ??
                            ($staff[$row['BIDESPECIALISTA']] ?? ($staff[$row['PIDESPECIALISTA']] ?? null)));
                    if (count($clinic_provider_ids) === 3) {
                        $provider = Podologo_providers::resolve(
                            $provider,
                            Podologo_providers::comments($row),
                            $start,
                            $clinic_provider_ids,
                        )['id'];
                    }
                    if (!$provider) {
                        throw new RuntimeException(
                            'Original visit provider could not be resolved from the clinic rules.',
                        );
                    }
                    $classification = Podologo_clinical::classification($row, $context['treatments'][$row['ID']] ?? '');
                    $selected = $services[$classification['name']] ?? null;
                    if (!$selected) {
                        throw new RuntimeException('Existing treatment missing.');
                    }
                    $visits[$row['ID']] = $insert('appointments', [
                        ...Podologo_schedule::times($start, $selected['duration']),
                        'id_users_customer' => $patients[$row['IDPACIENTE']],
                        'id_users_provider' => $provider,
                        'id_services' => (int) $selected['id'],
                        'status' => 'completed',
                        'notes' => $text($row, 'DESCRIPCION'),
                        'service_name' => $classification['name'],
                    ]);
                    $insert('podologo_import_links', [
                        'source_table' => 'VISITA',
                        'source_id' => $row['ID'],
                        'target_id' => $visits[$row['ID']],
                    ]);
                    $insert(
                        'appointment_records',
                        [
                            'id_appointments' => $visits[$row['ID']],
                            'foot_diagrams_saved' => 1,
                            'foot_diagram_layout' => json_encode($assets['diagrams'], JSON_THROW_ON_ERROR),
                        ] +
                            Podologo_clinical::record(
                                $row,
                                $context['visits'][$row['ID']] ?? '',
                                $selected['record_template'],
                            ),
                    );
                }
            }
            // VISITA sorts after the marker table; resolve markers only after every visit exists.
            $expected = hash_init('sha256');
            foreach (self::rows($path) as [$table, $row]) {
                if ($table !== 'QUIROPODIAPATOLOGIACOLOCADA') {
                    continue;
                }
                $marker = self::marker(
                    $row,
                    $assets['diagrams'],
                    $visits[$row['IDVISITA']],
                    $pathologies[$row['IDQUIROPODIAPATOLOGIA']],
                );
                $insert('appointment_foot_markers', $marker);
                hash_update($expected, self::fingerprint($marker));
            }
            $actual = hash_init('sha256');
            $result = $connection->query(
                'SELECT * FROM `' .
                    $prefix .
                    "appointment_foot_markers` WHERE original_external_id LIKE 'podologo:%' ORDER BY id",
                MYSQLI_USE_RESULT,
            );
            while ($marker = $result->fetch_assoc()) {
                hash_update($actual, self::fingerprint($marker));
            }
            $result->free();
            if (!hash_equals(hash_final($expected), hash_final($actual))) {
                throw new RuntimeException('The imported drawing verification failed.');
            }
            $connection->commit();
        } catch (Throwable $error) {
            $connection->rollback();
            throw $error;
        } finally {
            $connection->query("SELECT RELEASE_LOCK('podologo_import')");
            foreach ($statements as $statement) {
                $statement->close();
            }
        }
        return $previous;
    }

    private static function fingerprint(array $marker): string
    {
        $values = [];
        foreach (
            [
                'original_external_id',
                'id_appointments',
                'id_podology_pathologies',
                'id_podology_diagram_types',
                'x1',
                'y1',
                'x2',
                'y2',
                'is_healed',
                'coordinate_width',
                'coordinate_height',
                'background_image',
                'annotation_title',
                'annotation_text',
            ]
            as $field
        ) {
            $values[] = $marker[$field] === null ? null : (string) $marker[$field];
        }
        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}
