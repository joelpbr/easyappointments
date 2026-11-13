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

const FISCAL_ID_TYPES = ['dni', 'nie', 'nif', 'passport', 'foreign', 'other'];

const FISCAL_ID_VALID = 'valid'; // Format and check character are right.
const FISCAL_ID_INVALID_FORMAT = 'invalid_format'; // It does not have the shape of the document type.
const FISCAL_ID_INVALID_CHECK = 'invalid_check'; // The shape is right but the check character is not.
const FISCAL_ID_NOT_VALIDATED = 'not_validated'; // Foreign and other documents, which cannot be checked.

if (!function_exists('normalize_fiscal_id')) {
    /**
     * Normalize an identification number: upper case, without spaces, dots or dashes.
     *
     * @param string|null $number
     *
     * @return string
     */
    function normalize_fiscal_id(?string $number): string
    {
        return strtoupper(preg_replace('/[\s.\-\/]/', '', (string) $number));
    }
}

if (!function_exists('validate_fiscal_id')) {
    /**
     * Check a Spanish identification number (DNI, NIE or the NIF of an entity) by its format and check character.
     *
     * Passports, foreign documents and other documents cannot be checked, so they are only reported as such.
     *
     * @param string $type One of FISCAL_ID_TYPES.
     * @param string $number Identification number (normalized or not).
     *
     * @return string One of the FISCAL_ID_* results.
     */
    function validate_fiscal_id(string $type, string $number): string
    {
        $number = normalize_fiscal_id($number);

        switch ($type) {
            case 'dni':
                if (!preg_match('/^(\d{8})([A-Z])$/', $number, $matches)) {
                    return FISCAL_ID_INVALID_FORMAT;
                }

                return fiscal_id_letter((int) $matches[1]) === $matches[2] ? FISCAL_ID_VALID : FISCAL_ID_INVALID_CHECK;

            case 'nie':
                if (!preg_match('/^([XYZ])(\d{7})([A-Z])$/', $number, $matches)) {
                    return FISCAL_ID_INVALID_FORMAT;
                }

                $digits = (int) (strpos('XYZ', $matches[1]) . $matches[2]);

                return fiscal_id_letter($digits) === $matches[3] ? FISCAL_ID_VALID : FISCAL_ID_INVALID_CHECK;

            case 'nif':
                return validate_entity_nif($number);

            default:
                return FISCAL_ID_NOT_VALIDATED;
        }
    }
}

if (!function_exists('fiscal_id_letter')) {
    /**
     * Get the check letter of a DNI (or of a NIE, with its first letter turned into a digit).
     *
     * @param int $digits
     *
     * @return string
     */
    function fiscal_id_letter(int $digits): string
    {
        return 'TRWAGMYFPDXBNJZSQVHLCKE'[$digits % 23];
    }
}

if (!function_exists('validate_entity_nif')) {
    /**
     * Check the NIF of an entity (formerly CIF): a letter, seven digits and a check digit or letter.
     *
     * @param string $number Normalized number.
     *
     * @return string One of the FISCAL_ID_* results.
     */
    function validate_entity_nif(string $number): string
    {
        if (!preg_match('/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-J])$/', $number, $matches)) {
            return FISCAL_ID_INVALID_FORMAT;
        }

        [, $letter, $digits, $check] = $matches;

        $sum = 0;

        foreach (str_split($digits) as $index => $digit) {
            if ($index % 2 === 0) {
                // Positions 1, 3, 5 and 7: doubled, adding the digits of the result.
                $double = (int) $digit * 2;
                $sum += intdiv($double, 10) + ($double % 10);
            } else {
                $sum += (int) $digit;
            }
        }

        $control = (10 - ($sum % 10)) % 10;
        $control_letter = 'JABCDEFGHI'[$control];

        // Some entity types always use a letter, others always a digit, and the rest either of them.
        if (str_contains('NPQRSW', $letter)) {
            $valid = $check === $control_letter;
        } elseif (str_contains('ABEH', $letter)) {
            $valid = $check === (string) $control;
        } else {
            $valid = $check === (string) $control || $check === $control_letter;
        }

        return $valid ? FISCAL_ID_VALID : FISCAL_ID_INVALID_CHECK;
    }
}
