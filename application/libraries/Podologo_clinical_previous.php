<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Lossless, visible clinical text from the legacy records, including fields unknown to the new form. */
class Podologo_clinical_previous
{
    private const GROUPS = [
        'QUIRO' => 'Quiropòdia',
        'CIRUGIA' => 'Cirurgia',
        'BIO' => 'Exploració biomecànica',
        'PIERIESGO' => 'Peu de risc',
    ];

    public static function text(mixed $value): string
    {
        require_once APPPATH . 'libraries/Podologo_import.php';
        if ($value === null) {
            return '';
        }
        if (is_string($value) || is_array($value)) {
            return trim(Podologo_import::source_text($value) ?? '');
        }
        return (string) $value;
    }

    public static function title(array $row): string
    {
        $types = [];
        foreach (
            [
                'QUIROPODIA' => 'Quiropòdia',
                'CIRUGIA' => 'Cirurgia',
                'BIOMECANICA' => 'Exploració',
                'PIERIESGO' => 'Peu de risc',
            ]
            as $key => $label
        ) {
            if (!empty($row[$key])) {
                $types[] = $label;
            }
        }
        return ($types ? implode(' / ', $types) : 'Visita') . ' · Podologo';
    }

    /** All nonempty source values are retained, with original field codes when there is no safe translation. */
    public static function details(array $row, array $exclude = []): string
    {
        $parts = [];
        foreach ($row as $field => $value) {
            if (in_array($field, $exclude, true)) {
                continue;
            }
            $text = self::text($value);
            if ($text !== '') {
                $parts[] = $field . ': ' . $text;
            }
        }
        return implode("\n\n", $parts);
    }

    public static function record(array $row, string $related = ''): array
    {
        $result = ['reason' => [], 'diagnosis' => [], 'treatment' => []];
        foreach (self::GROUPS as $suffix => $label) {
            $prefix = $suffix === 'QUIRO' ? 'QUIROPODIA' : ($suffix === 'BIO' ? 'BIOMECANICA' : $suffix);
            foreach (
                [
                    'reason' => 'MOTIVOVISITA' . $suffix,
                    'diagnosis' => $prefix . 'DIAGNOSTICO',
                    'treatment' => $prefix . 'RECOMENDACION',
                ]
                as $target => $field
            ) {
                $value = self::text($row[$field] ?? null);
                if ($value !== '') {
                    $result[$target][] = $label . ': ' . $value;
                }
            }
        }
        $result = array_map(static fn($parts) => implode("\n\n", $parts), $result);
        // Observations also carry the complete original clinical content: no hidden template field can discard it.
        $result['observations'] = implode(
            "\n\n",
            array_filter(
                [
                    self::details($row, [
                        'ID',
                        'IDPACIENTE',
                        'FECHAVISITA',
                        'LIMITETIEMPOMODIFICACION',
                        'NUMEROFACTURA',
                        'ANOFACTURA',
                        'IDSERIEFACTURA',
                        'IDPAGAFACTURA',
                        'QIDESPECIALISTA',
                        'CIDESPECIALISTA',
                        'BIDESPECIALISTA',
                        'PIDESPECIALISTA',
                    ]),
                    $related,
                ],
                static fn($value) => $value !== '',
            ),
        );
        return $result;
    }

    /** Catalogs are small; related rows are streamed and resolved without loading the visit table into memory. */
    public static function context(callable $rows): array
    {
        $catalogs = [];
        $large = ['VISITA', 'PACIENTE', 'PERSONAL', 'LOGPERSONAL', 'CONFIGURACION', 'QUIROPODIAPATOLOGIACOLOCADA'];
        foreach ($rows() as [$table, $row]) {
            if (in_array($table, $large, true) || isset($row['IDVISITA']) || isset($row['IDPACIENTE'])) {
                continue;
            }
            if (isset($row['ID'])) {
                $catalogs[$table][$row['ID']] = $row;
            }
        }
        $visits = $patients = $ulcers = [];
        foreach ($rows() as [$table, $row]) {
            if ($table === 'ULCERA') {
                $ulcers[$row['ID']] = $row['IDVISITA'];
            }
        }
        foreach ($rows() as [$table, $row]) {
            if (
                in_array(
                    $table,
                    ['VISITA', 'PACIENTE', 'PERSONAL', 'LOGPERSONAL', 'CONFIGURACION', '__complete__'],
                    true,
                )
            ) {
                continue;
            }
            if ($table === 'QUIROPODIAPATOLOGIACOLOCADA') {
                $note = self::details(array_intersect_key($row, array_flip(['TITULOTEXTO', 'TEXTOTEXTO'])));
                if ($note !== '') {
                    $visits[$row['IDVISITA']][] = 'Anotació del dibuix ' . $row['ID'] . "\n" . $note;
                }
                continue;
            }
            $visit = $row['IDVISITA'] ?? ($table === 'PUNTOSULCERA' ? $ulcers[$row['IDULCERA']] ?? null : null);
            $patient = $row['IDPACIENTE'] ?? null;
            if (!$visit && !$patient) {
                continue;
            }
            $detail = self::details($row, ['IDVISITA', 'IDPACIENTE']);
            foreach ($row as $field => $value) {
                if (!str_starts_with($field, 'ID') || !$value || !is_int($value)) {
                    continue;
                }
                $catalog = self::catalog($table, $field);
                if ($catalog && isset($catalogs[$catalog][$value])) {
                    $detail .= "\n" . $catalog . ': ' . self::details($catalogs[$catalog][$value], ['ID']);
                }
            }
            $section = $table . "\n" . $detail;
            if ($visit) {
                $visits[$visit][] = $section;
            } elseif ($patient) {
                $patients[$patient][] = $section;
            }
        }
        return [
            'visits' => array_map(static fn($parts) => implode("\n\n", $parts), $visits),
            'patients' => array_map(static fn($parts) => implode("\n\n", $parts), $patients),
            'catalogs' => $catalogs,
        ];
    }

    private static function catalog(string $table, string $field): ?string
    {
        $explicit = [
            'IDCOMPLICACIONASOCIADA' => 'COMPLICACIONESASOCIADAS',
            'IDFACTORRIESGO' => 'FACTORESRIESGO',
            'IDACTIVIDADFISICA' => 'ACTIVIDADFISICA',
            'IDMORFOLOGIA' => 'MORFOLOGIA',
            'IDRESULTADO' => 'CIRUGIARESULTADO',
            'IDANGULO' => 'ANGULO',
            'IDCIRUGIA' => 'CIRUGIA',
        ];
        if (isset($explicit[$field])) {
            return $explicit[$field];
        }
        foreach (['BIOMECANICA', 'QUIROPODIA', 'CIRUGIA', 'PIERIESGO'] as $prefix) {
            if (
                str_starts_with($table, $prefix) &&
                in_array($field, ['IDDIAGNOSTICO', 'IDTRATAMIENTO', 'IDPATOMECANICA', 'IDSINTOMATOLOGIA'], true)
            ) {
                return $prefix . substr($field, 2);
            }
        }
        if ($field === 'IDTRATAMIENTONOULCERA') {
            return 'TRATAMIENTONOULCERA';
        }
        if ($field === 'IDTRATAMIENTOULCERA') {
            return 'TRATAMIENTOULCERA';
        }
        return null;
    }

    public static function patient(array $row, array $context): array
    {
        $catalog = static function (string $table, mixed $id) use ($context): string {
            return isset($context['catalogs'][$table][$id])
                ? self::details($context['catalogs'][$table][$id], ['ID'])
                : '';
        };
        return [
            'referral_source' => $catalog('COMOCONOCIO', $row['IDPROCEDENCIA'] ?? 0),
            'profession' => $catalog('PROFESION', $row['IDPROFESION'] ?? 0),
            'medical_history' => implode(
                "\n\n",
                array_filter(
                    [
                        self::details($row, [
                            'ID',
                            'FOTO',
                            'NOMBRE',
                            'APELLIDOS',
                            'NIF',
                            'DOMICILIO',
                            'POBLACION',
                            'CODIGOPOSTAL',
                            'PROVINCIA',
                            'PAIS',
                            'TELEFONOCASA',
                            'TELEFONOTRABAJO',
                            'TELEFONOMOVIL',
                            'EMAIL1',
                            'EMAIL2',
                            'BANCO',
                            'SUCURSAL',
                            'DC',
                            'CUENTA',
                        ]),
                        $context['patients'][$row['ID']] ?? '',
                    ],
                    static fn($value) => $value !== '',
                ),
            ),
            'allergies' => self::text($row['ALERGIAS'] ?? null),
        ];
    }

    /** Preserve edits made since import; repeated repairs do not append the same text again. */
    public static function merge(?string $current, string $original, ?string $old_import = null): ?string
    {
        if (trim($current ?? '') === '' || ($old_import !== null && trim($current ?? '') === trim($old_import))) {
            return $original ?: null;
        }
        if ($original === '' || str_contains($current, $original)) {
            return $current;
        }
        return $current . "\n\n── Historial original Podologo ──\n" . $original;
    }
}
