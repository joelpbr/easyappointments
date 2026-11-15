<?php defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH . 'libraries/Podologo_clinical_previous.php';

/** Conversion only: consolidate the original history into the application's existing fields and treatments. */
class Podologo_clinical_v2
{
    private const LABELS = [
        'DESCRIPCION' => 'Comentaris',
        'EVOLUCIONQUIRO' => 'Evolució',
        'EVOLUCIONBIO' => 'Evolució',
        'EVOLUCIONCIRUGIA' => 'Evolució',
        'EVOLUCIONPIERIESGO' => 'Evolució',
        'OBSERVDESCI' => 'Exploració del peu esquerre',
        'OBSERVDESCD' => 'Exploració del peu dret',
        'ARTCADMANIOBRASOBSERVA' => 'Maluc: maniobres',
        'ARTRODROTULAS' => 'Ròtules',
        'ARTRODMANIOBRASOBSERVACIONES' => 'Genoll: maniobres',
        'COALICIONES' => 'Coalicions',
        'ARTTOBMANIOBRASOBSERVA' => 'Turmell: maniobres',
        'OBSERVACIONESEXPLRADIOS' => 'Exploració radiològica',
        'GENEXPLMUSCOBSERVACIONES' => 'Exploració muscular',
        'MANIOBRASOBSERVACIONESMUSC' => 'Maniobres musculars',
        'PFE1ESCOLTESTADAMS' => 'Escoliosi: test d’Adams',
        'BIPOBSERVACIONES1' => 'Bipedestació: observacions',
        'PFE2PRUEBFLEXTRONC' => 'Flexió del tronc',
        'BIPOBSERVACIONES2' => 'Bipedestació: observacions complementàries',
        'PF3OBSERVACIONESTESTTIBPOS' => 'Test del tibial posterior',
        'PF4OBSERVACIONESOTRMAN' => 'Altres maniobres',
        'PSRODILLAOBSERVACIONES' => 'Posició del genoll',
        'PTOBSERVACIONES' => 'Posició transversal',
        'ESTATICASOBRECARGAS' => 'Estàtica: sobrecàrregues',
        'ESTATICACOMENTARIOS' => 'Estàtica: comentaris',
        'DINAMICADIAGNOSTICO' => 'Exploració dinàmica',
        'OBSERVACIONNEUROLOGICA' => 'Exploració neurològica',
        'DOPPLEROBSERVACIONES' => 'Doppler',
        'TEXTOTRATAMIENTODIABETES' => 'Tractament de la diabetis',
        'TEXTOHB1AC' => 'HbA1c',
        'TEXTOATENCIONPODOLOGICA' => 'Atenció podològica',
        'HISTORIALANTIGUO' => 'Antecedents',
        'ENFERMEDADES' => 'Malalties',
        'COMENTARIOS' => 'Comentaris',
        'ALERGIAS' => 'Al·lèrgies',
        'PESO' => 'Pes',
        'ALTURA' => 'Alçada',
        'NUMCALZADO' => 'Número de calçat',
        'TEXTOTEXTO' => 'Anotació',
        'TITULOTEXTO' => 'Anotació',
        'TRATAMIENTO' => 'Tractament',
        'DIAGNOSTICO' => 'Diagnòstic',
        'FACTORRIESGO' => 'Factor de risc',
        'COMPLICACIONASOCIADA' => 'Complicació associada',
        'ACTIVIDADFISICA' => 'Activitat física',
        'MORFOLOGIA' => 'Morfologia',
        'NOMBREIMAGEN' => 'Imatge',
    ];
    public static function text(mixed $value): string
    {
        return Podologo_clinical_previous::text($value);
    }
    public static function label(string $key): string
    {
        if (isset(self::LABELS[$key])) {
            return self::LABELS[$key];
        }
        $words = [
            'OBSERVACIONES' => ' observacions ',
            'OBSERVACION' => ' observació ',
            'IZQUIERDO' => ' esquerre ',
            'DERECHO' => ' dret ',
            'IZQDO' => ' esquerre ',
            'DCHO' => ' dret ',
            'DERECHA' => ' dreta ',
            'IZQUIERDA' => ' esquerra ',
            'NORMAL' => ' normal ',
            'DISMINUIDA' => ' disminuïda ',
            'AUSENTE' => ' absent ',
            'AUMENTADA' => ' augmentada ',
            'DIAGNOSTICO' => ' diagnòstic ',
            'TRATAMIENTO' => ' tractament ',
            'TEMPERATURA' => ' temperatura ',
            'DOLOR' => ' dolor ',
            'TENSION' => ' tensió ',
            'RODILLA' => ' genoll ',
            'CADERA' => ' maluc ',
            'TOBILLO' => ' turmell ',
            'FLEXION' => ' flexió ',
            'EXTENSION' => ' extensió ',
            'ROTACION' => ' rotació ',
            'INTERNA' => ' interna ',
            'EXTERNA' => ' externa ',
            'MEDICION' => ' mesura ',
            'VIBRACION' => ' vibració ',
            'REFLEJO' => ' reflex ',
            'PULSO' => ' pols ',
            'EDEMA' => ' edema ',
            'TEXTO' => ' ',
            'GLUCOSA' => ' glucosa ',
            'SANGRE' => ' sang ',
            'DIAMETRO' => ' diàmetre ',
            'PROFUNDIDAD' => ' profunditat ',
            'LESION' => ' lesió ',
            'ANGULO' => ' angle ',
            'PIEL' => ' pell ',
            'TOTAL' => ' total ',
            'COMENTARIOS' => ' comentaris ',
            'TEST' => ' test ',
        ];
        $key = strtr($key, $words);
        return ucfirst(trim(preg_replace('/\s+/', ' ', mb_strtolower(str_replace('_', ' ', $key)))));
    }
    private static function join(array $parts): string
    {
        return implode("\n\n", array_values(array_unique(array_filter($parts, static fn($v) => $v !== ''))));
    }
    /** Keep source content; omit administrative IDs and empty/default form flags from clinical prose. */
    public static function details(array $row, array $exclude = []): string
    {
        $parts = [];
        foreach ($row as $key => $value) {
            if (in_array($key, $exclude, true) || $key === 'ID' || str_starts_with($key, 'ID') || $value === null) {
                continue;
            }
            $text = self::text($value);
            if ($text === '' || ((is_int($value) || is_float($value)) && $value == 0)) {
                continue;
            }
            $parts[] = self::label($key) . ': ' . $text;
        }
        return self::join($parts);
    }
    public static function context(callable $rows): array
    {
        $old = Podologo_clinical_previous::context($rows);
        $visits = $patients = $treatments = [];
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
            $visit = $row['IDVISITA'] ?? null;
            $patient = $row['IDPACIENTE'] ?? null;
            if (!$visit && !$patient) {
                continue;
            }
            if ($table === 'QUIROPODIAPATOLOGIACOLOCADA') {
                $note = self::join([self::text($row['TITULOTEXTO'] ?? null), self::text($row['TEXTOTEXTO'] ?? null)]);
                if ($note !== '') {
                    $visits[$visit][] = 'Anotació del dibuix: ' . $note;
                }
                continue;
            }
            $parts = [];
            foreach ($row as $key => $value) {
                if (!str_starts_with($key, 'ID') || in_array($key, ['ID', 'IDVISITA', 'IDPACIENTE'], true) || !$value) {
                    continue;
                }
                $catalog =
                    [
                        'IDCOMPLICACIONASOCIADA' => 'COMPLICACIONESASOCIADAS',
                        'IDFACTORRIESGO' => 'FACTORESRIESGO',
                        'IDACTIVIDADFISICA' => 'ACTIVIDADFISICA',
                        'IDMORFOLOGIA' => 'MORFOLOGIA',
                        'IDRESULTADO' => 'CIRUGIARESULTADO',
                        'IDCIRUGIA' => 'CIRUGIA',
                        'IDANGULO' => 'ANGULO',
                        'IDTRATAMIENTONOULCERA' => 'TRATAMIENTONOULCERA',
                        'IDTRATAMIENTOULCERA' => 'TRATAMIENTOULCERA',
                    ][$key] ?? null;
                foreach (['BIOMECANICA', 'QUIROPODIA', 'CIRUGIA', 'PIERIESGO'] as $prefix) {
                    if (
                        str_starts_with($table, $prefix) &&
                        in_array($key, ['IDDIAGNOSTICO', 'IDTRATAMIENTO', 'IDPATOMECANICA', 'IDSINTOMATOLOGIA'], true)
                    ) {
                        $catalog = $prefix . substr($key, 2);
                    }
                }
                if ($catalog && isset($old['catalogs'][$catalog][$value])) {
                    $entry = $old['catalogs'][$catalog][$value];
                    $content = self::join(
                        array_map(fn($v) => self::text($v), array_values(array_diff_key($entry, ['ID' => true]))),
                    );
                    if ($content !== '') {
                        $parts[] = self::label(substr($key, 2)) . ': ' . $content;
                        if ($visit && $key === 'IDTRATAMIENTO') {
                            $treatments[$visit][] = $content;
                        }
                    }
                }
            }
            $parts[] = self::details($row, ['IDVISITA', 'IDPACIENTE', 'FECHA', 'LIMITETIEMPOMODIFICACION']);
            $section = self::join($parts);
            if ($section === '') {
                continue;
            }
            if ($visit) {
                $visits[$visit][] = $section;
            } else {
                $patients[$patient][] = $section;
            }
        }
        return [
            'visits' => array_map(fn($p) => self::join($p), $visits),
            'patients' => array_map(fn($p) => self::join($p), $patients),
            'treatments' => array_map(fn($p) => implode(' / ', array_unique($p)), $treatments),
            'catalogs' => $old['catalogs'],
            'previous' => $old,
        ];
    }
    /** Linked prescribed treatment is stronger evidence than an incidental word in an examination note. */
    public static function classification(array $row, string $prescribed = ''): array
    {
        $text =
            $prescribed !== ''
                ? $prescribed
                : self::join(
                    array_map(fn($key) => self::text($row[$key] ?? null), ['QUIROPODIARECOMENDACION', 'DESCRIPCION']),
                );
        $text = mb_strtolower($text);
        if (preg_match('/\bcura\s*p\.?\s*v\.?\b/u', $text)) {
            return ['name' => 'Cura PV', 'template' => 'feet'];
        }
        if (preg_match('/\bcura\b/u', $text)) {
            return ['name' => 'Cura', 'template' => 'feet'];
        }
        foreach (
            ['wallet' => 'Wallet', 'ortonixia' => 'Ortonixia', 'infiltraci' => 'Infiltración']
            as $match => $name
        ) {
            if (str_contains($text, $match)) {
                return ['name' => $name, 'template' => $name === 'Infiltración' ? 'notes' : 'feet'];
            }
        }
        if (preg_match('/\bortesis\b|\bsilicona\b/u', $text)) {
            return ['name' => 'Silicona', 'template' => 'silicone'];
        }
        if (!empty($row['CIRUGIA'])) {
            return ['name' => 'Cirugía', 'template' => 'surgery'];
        }
        if (!empty($row['QUIROPODIA'])) {
            return ['name' => 'Quiropodia', 'template' => 'feet'];
        }
        if (!empty($row['BIOMECANICA'])) {
            return ['name' => 'Exploración', 'template' => 'exploration'];
        }
        if (!empty($row['PIERIESGO'])) {
            return ['name' => 'Quiropodia', 'template' => 'feet'];
        }
        return ['name' => 'Otro', 'template' => 'notes'];
    }
    public static function title(array $row): string
    {
        return self::classification($row)['name'];
    }
    public static function record(array $row, string $related = '', ?string $template = null): array
    {
        $raw = Podologo_clinical_previous::record($row);
        $result = [
            'reason' => $raw['reason'],
            'diagnosis' => $raw['diagnosis'],
            'treatment' => $raw['treatment'],
            'observations' => '',
            'orthosis' => null,
        ];
        $mapped = [
            'ID',
            'IDPACIENTE',
            'FECHAVISITA',
            'LIMITETIEMPOMODIFICACION',
            'NUMEROFACTURA',
            'ANOFACTURA',
            'IDSERIEFACTURA',
            'IDPAGAFACTURA',
            'QUIROPODIA',
            'CIRUGIA',
            'BIOMECANICA',
            'PIERIESGO',
        ];
        foreach (
            ['QUIRO' => 'QUIROPODIA', 'CIRUGIA' => 'CIRUGIA', 'BIO' => 'BIOMECANICA', 'PIERIESGO' => 'PIERIESGO']
            as $suffix => $prefix
        ) {
            $mapped[] = 'MOTIVOVISITA' . $suffix;
            $mapped[] = $prefix . 'DIAGNOSTICO';
            $mapped[] = $prefix . 'RECOMENDACION';
        }
        $extra = self::details($row, $mapped);
        $result['observations'] = self::join([$extra, $related]);
        $template ??= self::classification($row)['template'];
        return self::fit($result, $template);
    }
    public static function fit(array $result, string $template): array
    {
        foreach (['reason', 'diagnosis', 'treatment', 'observations'] as $field) {
            $result[$field] = (string) ($result[$field] ?? '');
        }
        $allowed = [
            'feet' => ['observations'],
            'surgery' => ['reason', 'diagnosis', 'treatment', 'observations'],
            'exploration' => ['reason', 'diagnosis', 'treatment'],
            'silicone' => ['reason', 'diagnosis', 'orthosis'],
            'notes' => ['observations'],
        ][$template];
        $overflow = [];
        foreach (
            [
                'reason' => 'Motiu',
                'diagnosis' => 'Diagnòstic',
                'treatment' => 'Tractament',
                'observations' => 'Observacions',
            ]
            as $field => $label
        ) {
            if (!in_array($field, $allowed, true) && $result[$field] !== '') {
                $overflow[] = $label . ":\n" . $result[$field];
                $result[$field] = '';
            }
        }
        $destination = in_array('observations', $allowed, true)
            ? 'observations'
            : (in_array('treatment', $allowed, true)
                ? 'treatment'
                : 'diagnosis');
        $result[$destination] = self::join([$result[$destination], ...$overflow]);
        return $result;
    }
    public static function patient(array $row, array $context): array
    {
        $lookup = static function ($table, $id) use ($context) {
            return isset($context['catalogs'][$table][$id])
                ? self::join(
                    array_map(
                        fn($v) => self::text($v),
                        array_values(array_diff_key($context['catalogs'][$table][$id], ['ID' => true])),
                    ),
                )
                : '';
        };
        return [
            'referral_source' => $lookup('COMOCONOCIO', $row['IDPROCEDENCIA'] ?? 0),
            'profession' => $lookup('PROFESION', $row['IDPROFESION'] ?? 0),
            'medical_history' => self::join([
                self::details(
                    array_intersect_key(
                        $row,
                        array_flip(['ENFERMEDADES', 'HISTORIALANTIGUO', 'COMENTARIOS', 'ALTURA', 'PESO', 'NUMCALZADO']),
                    ),
                ),
                $context['patients'][$row['ID']] ?? '',
            ]),
            'allergies' => self::text($row['ALERGIAS'] ?? null),
        ];
    }
    public static function merge(?string $current, string $original, ?string $old_import = null): ?string
    {
        if ($old_import !== null && $old_import !== '') {
            $current = str_replace($old_import, '', $current ?? '');
        }
        $current = trim(str_replace('── Historial original Podologo ──', '', $current ?? ''));
        if ($current === '' || $current === $original) {
            return $original ?: null;
        }
        if ($original === '' || str_contains($current, $original)) {
            return $current;
        }
        return $current . "\n\n" . $original;
    }
}
