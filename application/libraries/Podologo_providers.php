<?php defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Podologo_unify.php';

/** Clinic-confirmed provider shorthand in the original visit comments. */
class Podologo_providers
{
    public static function comments(array $row): string
    {
        require_once APPPATH . 'libraries/Podologo_clinical.php';
        $parts = [Podologo_clinical::text($row['DESCRIPCION'] ?? null)];
        foreach (
            ['QUIRO' => 'QUIROPODIA', 'CIRUGIA' => 'CIRUGIA', 'BIO' => 'BIOMECANICA', 'PIERIESGO' => 'PIERIESGO']
            as $suffix => $prefix
        ) {
            foreach (
                ['MOTIVOVISITA' . $suffix, 'EVOLUCION' . $suffix, $prefix . 'DIAGNOSTICO', $prefix . 'RECOMENDACION']
                as $key
            ) {
                $parts[] = Podologo_clinical::text($row[$key] ?? null);
            }
        }
        return implode("\n", $parts);
    }

    public static function resolve(?int $current, string $comments, string $date, array $ids): array
    {
        require_once APPPATH . 'libraries/Podologo_unify.php';
        if (Podologo_unify::tonyi_comment($comments, $date)) {
            return ['id' => $ids['tonyi'], 'reason' => 'T_from_2024'];
        }
        if (preg_match('/(?<![\p{L}\p{N}_])S(?![\p{L}\p{N}_])/u', $comments)) {
            return ['id' => $ids['sebas'], 'reason' => 'S_comment'];
        }
        if ($current) {
            return ['id' => $current, 'reason' => 'existing_assignment'];
        }
        return [
            'id' => $ids['sara'],
            'reason' => substr($date, 11, 8) === '00:00:00' ? 'undated_time_Sara' : 'unmarked_Sara',
        ];
    }
}
