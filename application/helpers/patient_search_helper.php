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

if (!defined('PATIENT_SEARCH_MIN_WORD_LETTERS')) {
    define('PATIENT_SEARCH_MIN_WORD_LETTERS', 2);
}

if (!defined('PATIENT_SEARCH_MIN_PHONE_DIGITS')) {
    define('PATIENT_SEARCH_MIN_PHONE_DIGITS', 6);
}

if (!function_exists('is_specific_patient_search')) {
    /**
     * Check whether a patient search says clearly who is being looked for.
     *
     * With thousands of patients a few letters match too many of them, so a search needs the first name and the last
     * name (at least two words of two letters, e.g. "ma ga") or six digits of the phone number.
     *
     * @param string $keyword Typed text.
     *
     * @return bool
     */
    function is_specific_patient_search(string $keyword): bool
    {
        $keyword = trim($keyword);

        if (!preg_match('/\p{L}/u', $keyword)) {
            return strlen(preg_replace('/\D/', '', $keyword)) >= PATIENT_SEARCH_MIN_PHONE_DIGITS;
        }

        $words = preg_split('/\s+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) < 2) {
            return false;
        }

        foreach ($words as $word) {
            if (mb_strlen($word) < PATIENT_SEARCH_MIN_WORD_LETTERS) {
                return false;
            }
        }

        return true;
    }
}
