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

/*
 * Appointment statuses.
 *
 * Appointments store a stable status code. The clinic can rename the statuses and add its own ones from the business
 * settings, which are kept as JSON in the "appointment_statuses" setting. The system statuses always exist and keep
 * their meaning; a status that "frees the slot" (cancelled) no longer counts as taken time for the availability.
 */

if (!function_exists('appointment_system_statuses')) {
    /**
     * Get the system statuses with their default names and whether they free the slot.
     *
     * @return array Map of code => ['name' => string, 'frees_slot' => bool].
     */
    function appointment_system_statuses(): array
    {
        return [
            APPOINTMENT_STATUS_SCHEDULED => ['name' => 'Programada', 'frees_slot' => false],
            APPOINTMENT_STATUS_CONFIRMED => ['name' => 'Confirmada', 'frees_slot' => false],
            APPOINTMENT_STATUS_COMPLETED => ['name' => 'Realizada', 'frees_slot' => false],
            APPOINTMENT_STATUS_CANCELLED => ['name' => 'Cancelada', 'frees_slot' => true],
            APPOINTMENT_STATUS_NO_SHOW => ['name' => 'No presentado', 'frees_slot' => false],
        ];
    }
}

if (!function_exists('normalize_appointment_statuses')) {
    /**
     * Turn a raw status list into a valid one.
     *
     * The system statuses are always present, first and with their fixed meaning; only their name can change. Custom
     * statuses need a "custom_" code and a name, duplicates are dropped.
     *
     * @param array $statuses Raw list of ['code' => string, 'name' => string, 'frees_slot' => bool].
     *
     * @return array List of ['code' => string, 'name' => string, 'frees_slot' => bool, 'is_system' => bool].
     */
    function normalize_appointment_statuses(array $statuses): array
    {
        $names = [];
        $custom = [];

        foreach ($statuses as $status) {
            if (!is_array($status) || !is_string($status['code'] ?? null)) {
                continue;
            }

            $code = $status['code'];
            $name = mb_substr(trim(strip_tags((string) ($status['name'] ?? ''))), 0, 64);

            if (array_key_exists($code, appointment_system_statuses())) {
                $names[$code] = $name;
            } elseif (preg_match('/^custom_[a-z0-9]{1,32}$/', $code) && $name !== '' && !isset($custom[$code])) {
                $custom[$code] = [
                    'code' => $code,
                    'name' => $name,
                    'frees_slot' => filter_var($status['frees_slot'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_system' => false,
                ];
            }
        }

        $normalized = [];

        foreach (appointment_system_statuses() as $code => $system_status) {
            $normalized[] = [
                'code' => $code,
                'name' => ($names[$code] ?? '') !== '' ? $names[$code] : $system_status['name'],
                'frees_slot' => $system_status['frees_slot'],
                'is_system' => true,
            ];
        }

        return array_merge($normalized, array_values($custom));
    }
}

if (!function_exists('appointment_statuses')) {
    /**
     * Get the appointment statuses of the installation, the system ones first.
     *
     * @return array List of ['code' => string, 'name' => string, 'frees_slot' => bool, 'is_system' => bool].
     */
    function appointment_statuses(): array
    {
        $statuses = json_decode((string) setting('appointment_statuses', '[]'), true);

        return normalize_appointment_statuses(is_array($statuses) ? $statuses : []);
    }
}

if (!function_exists('appointment_status_exists')) {
    /**
     * Check whether a status code exists.
     *
     * @param string $code Status code.
     *
     * @return bool
     */
    function appointment_status_exists(string $code): bool
    {
        return in_array($code, array_column(appointment_statuses(), 'code'), true);
    }
}

if (!function_exists('appointment_status_name')) {
    /**
     * Get the display name of a status code.
     *
     * @param string|null $code Status code.
     *
     * @return string The status name, or the code itself when it is unknown.
     */
    function appointment_status_name(?string $code): string
    {
        foreach (appointment_statuses() as $status) {
            if ($status['code'] === $code) {
                return $status['name'];
            }
        }

        return (string) $code;
    }
}

if (!function_exists('appointment_status_free_codes')) {
    /**
     * Get the codes of the statuses whose appointments no longer take their slot.
     *
     * @return array List of status codes.
     */
    function appointment_status_free_codes(): array
    {
        return array_column(
            array_filter(appointment_statuses(), fn(array $status): bool => $status['frees_slot']),
            'code',
        );
    }
}

if (!function_exists('normalize_first_visit')) {
    /**
     * Normalize the first visit flag of an appointment.
     *
     * @param mixed $value 1/0, true/false, "1"/"0", or null/"" when it is not known.
     *
     * @return int|null 1 for a first visit, 0 for a returning patient, null when it is not known.
     *
     * @throws InvalidArgumentException For any other value.
     */
    function normalize_first_visit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($flag === null) {
            throw new InvalidArgumentException('The provided first visit value is invalid: ' . print_r($value, true));
        }

        return (int) $flag;
    }
}
