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

if (!function_exists('validate_calendar_slot_minutes')) {
    /**
     * Validate the grid interval of the calendar.
     *
     * @param mixed $minutes Interval in minutes.
     *
     * @return int The interval as an integer.
     *
     * @throws InvalidArgumentException When it is not a whole number of minutes between 5 and 60.
     */
    function validate_calendar_slot_minutes(mixed $minutes): int
    {
        $value = filter_var($minutes, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => CALENDAR_SLOT_MINUTES_MIN, 'max_range' => CALENDAR_SLOT_MINUTES_MAX],
        ]);

        if ($value === false) {
            throw new InvalidArgumentException(lang('calendar_slot_minutes_invalid'));
        }

        return $value;
    }
}

if (!function_exists('calendar_slot_minutes')) {
    /**
     * Get the grid interval of the calendar, falling back to 15 minutes when the setting is missing or invalid.
     *
     * @return int Interval in minutes.
     */
    function calendar_slot_minutes(): int
    {
        try {
            return validate_calendar_slot_minutes(setting('calendar_slot_minutes', CALENDAR_SLOT_MINUTES_DEFAULT));
        } catch (InvalidArgumentException) {
            return CALENDAR_SLOT_MINUTES_DEFAULT;
        }
    }
}
