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

if (!defined('PATIENT_SEARCH_MIN_LETTERS')) {
    define('PATIENT_SEARCH_MIN_LETTERS', 3);
}

if (!defined('PATIENT_SEARCH_MIN_PHONE_DIGITS')) {
    define('PATIENT_SEARCH_MIN_PHONE_DIGITS', 6);
}

if (!function_exists('is_specific_patient_search')) {
    /**
     * Check whether a patient search is worth running.
     *
     * A name search needs at least three letters (e.g. "lucia" or "ma ga") and a phone search six digits, so that one
     * or two characters never list a large part of the patients. The results are limited anyway.
     *
     * @param string $keyword Typed text.
     *
     * @return bool
     */
    function is_specific_patient_search(string $keyword): bool
    {
        $letters = preg_match_all('/\p{L}/u', $keyword);

        if (!$letters) {
            return strlen(preg_replace('/\D/', '', $keyword)) >= PATIENT_SEARCH_MIN_PHONE_DIGITS;
        }

        return $letters >= PATIENT_SEARCH_MIN_LETTERS;
    }
}
