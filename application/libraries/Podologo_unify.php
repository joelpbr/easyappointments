<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Explicit equivalences for catalog data; never merges different pathological subtypes. */
class Podologo_unify
{
    public static function display_name(string $name): string
    {
        $name = preg_replace('/^Podologo\s+\d+:\s*/iu', '', trim($name));
        return trim(preg_replace('/^A\.\s*/u', '', $name));
    }

    public static function pathology_key(string $name): string
    {
        $name = mb_strtolower(self::display_name($name));
        $name = strtr($name, [
            'à' => 'a',
            'á' => 'a',
            'è' => 'e',
            'é' => 'e',
            'í' => 'i',
            'ï' => 'i',
            'ò' => 'o',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
            '·' => '',
        ]);
        $name = preg_replace('/\s+/u', ' ', trim($name));
        return [
            'hiperqueratosis' => 'hiperqueratosi',
            'onicomicosis' => 'onicomicosi',
            'onicocriptosis' => 'onicocriptosi',
            'papiloma' => 'papilloma',
            'papilomas' => 'papilloma',
        ][$name] ?? $name;
    }

    public static function tonyi_comment(?string $comment, string $date): bool
    {
        return substr($date, 0, 10) >= '2024-01-01' &&
            preg_match('/(?<![\p{L}\p{N}_])T(?![\p{L}\p{N}_])/u', $comment ?? '') === 1;
    }

    public static function pathology_plan(array $rows): array
    {
        // Prefer the existing clinic entries, retaining their names and manually assigned icons.
        usort(
            $rows,
            static fn($a, $b) => [str_starts_with($a['name'], 'Podologo '), (int) $a['id']] <=> [
                str_starts_with($b['name'], 'Podologo '),
                (int) $b['id'],
            ],
        );
        $canonical = [];
        $merges = [];
        $names = [];
        foreach ($rows as $row) {
            $key = self::pathology_key($row['name']);
            if (isset($canonical[$key])) {
                $merges[(int) $row['id']] = $canonical[$key];
            } else {
                $canonical[$key] = (int) $row['id'];
                $names[(int) $row['id']] = self::display_name($row['name']);
            }
        }
        return ['merges' => $merges, 'names' => $names];
    }
}
