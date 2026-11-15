<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Repair recognizable UTF-8 text that was previously read as Windows-1252 or Latin-1. */
class Podologo_text_encoding
{
    public static function decode(string $bytes): string
    {
        return self::repair(
            mb_check_encoding($bytes, 'UTF-8') ? $bytes : mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252'),
        );
    }

    public static function repair(string $text): string
    {
        static $replacements;
        if ($replacements === null) {
            $replacements = [];
            // Accented letters, ordinals, punctuation, currency, arrows and mathematical signs.
            foreach ([[0x0080, 0x02ff], [0x2000, 0x206f], [0x20a0, 0x20cf], [0x2100, 0x22ff]] as [$first, $last]) {
                for ($point = $first; $point <= $last; $point++) {
                    $character = mb_chr($point, 'UTF-8');
                    foreach (['Windows-1252', 'ISO-8859-1'] as $encoding) {
                        $broken = mb_convert_encoding($character, 'UTF-8', $encoding);
                        // Only reversible sequences: never guess characters replaced by a question mark.
                        if (mb_convert_encoding($broken, $encoding, 'UTF-8') === $character) {
                            $replacements[$broken] = $character;
                        }
                    }
                }
            }
        }
        // Some legacy values have been incorrectly converted more than once.
        do {
            $previous = $text;
            $text = strtr($text, $replacements);
        } while ($text !== $previous);
        return $text;
    }
}
