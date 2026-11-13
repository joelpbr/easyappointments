<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

if (!function_exists('normalize_phone')) {
    /**
     * Normalize a phone number to compare and search it, whatever the way it was typed.
     *
     * Spaces, dots, dashes and parentheses are removed, the international prefix 00 becomes +, and a Spanish number
     * of 9 digits (starting with 6, 7, 8 or 9) gets the +34 prefix, so "600 12 34 56", "0034600123456" and
     * "+34 600-123-456" all give "+34600123456". Other numbers keep their digits as they are.
     *
     * @param string|null $phone Phone number as typed.
     *
     * @return string|null The normalized number, or null when it has no digits.
     */
    function normalize_phone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        $international = str_starts_with($phone, '+') || str_starts_with($phone, '00');

        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($phone, '00')) {
            $digits = substr($digits, 2);
        }

        if (!$international && preg_match('/^[6789]\d{8}$/', $digits)) {
            return '+34' . $digits;
        }

        return $international ? '+' . $digits : $digits;
    }
}
