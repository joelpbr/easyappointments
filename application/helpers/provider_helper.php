<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Compact name for selectors and the agenda; document signatures keep their legal/full names. */
function provider_display_name(array $provider): string
{
    $short_name = trim((string) ($provider['short_name'] ?? ''));

    return $short_name !== ''
        ? $short_name
        : trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''));
}
