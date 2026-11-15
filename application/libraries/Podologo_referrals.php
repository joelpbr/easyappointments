<?php defined('BASEPATH') or exit('No direct script access allowed');

/** EMAIL2 was used as free-text provenance in the clinic's legacy application. */
class Podologo_referrals
{
    public static function merge(?string $current, ?string $source): string
    {
        $existing = trim($current ?? '');
        $source = trim($source ?? '');
        if ($source === '') {
            return $existing;
        }
        $parts = $existing === '' ? [] : preg_split('/\R/u', $existing);
        $normalize = static fn($value) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
        $keys = array_map($normalize, $parts);
        foreach (preg_split('/\R/u', $source) as $part) {
            $part = trim($part);
            if ($part !== '' && !in_array($normalize($part), $keys, true)) {
                $parts[] = $part;
                $keys[] = $normalize($part);
            }
        }
        $result = implode("\n", $parts);
        if (mb_strlen($result) > 256) {
            throw new InvalidArgumentException(
                'Combined provenance exceeds the existing field length; no text was truncated.',
            );
        }
        return $result;
    }
}
