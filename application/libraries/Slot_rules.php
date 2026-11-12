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

/**
 * Slot rules.
 *
 * Checks whether a provider can take an appointment in a time period, with the same rules the availability engine
 * uses to offer free slots: working plan and its exceptions, breaks, blocked periods, unavailabilities and the
 * appointments that take their slot. Every violation is reported, so the user gets a clear reason.
 *
 * Saving an appointment must happen inside lock_provider(), so two sessions cannot take the same slot at once.
 */
class Slot_rules
{
    // Violations that reception may confirm on purpose (e.g. an extra patient after hours).
    public const OVERLAP_APPOINTMENT = 'overlap_appointment';
    public const OVERLAP_UNAVAILABILITY = 'overlap_unavailability';
    public const BLOCKED_PERIOD = 'blocked_period';
    public const OUTSIDE_WORKING_PLAN = 'outside_working_plan';

    // Violations that can never be confirmed.
    public const INVALID_PERIOD = 'invalid_period';
    public const PROVIDER_INACTIVE = 'provider_inactive';
    public const SERVICE_NOT_OFFERED = 'service_not_offered';

    private const FORCEABLE = [
        self::OVERLAP_APPOINTMENT,
        self::OVERLAP_UNAVAILABILITY,
        self::BLOCKED_PERIOD,
        self::OUTSIDE_WORKING_PLAN,
    ];

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Slot_rules constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('appointments_model');
        $this->CI->load->model('blocked_periods_model');
        $this->CI->load->model('providers_model');

        $this->CI->load->library('availability');
    }

    /**
     * Get the rules an appointment would break.
     *
     * @param int $provider_id Provider ID.
     * @param int|null $service_id Service ID (null skips the "service offered" rule, e.g. for unavailabilities).
     * @param string $start_datetime Start (Y-m-d H:i:s).
     * @param string $end_datetime End (Y-m-d H:i:s).
     * @param int|null $exclude_appointment_id Appointment being edited.
     *
     * @return array List of violation codes, empty when the slot is valid.
     */
    public function check(
        int $provider_id,
        ?int $service_id,
        string $start_datetime,
        string $end_datetime,
        ?int $exclude_appointment_id = null,
    ): array {
        $start = new DateTime($start_datetime);
        $end = new DateTime($end_datetime);

        if ($end <= $start) {
            return [self::INVALID_PERIOD];
        }

        $provider = $this->CI->providers_model->find($provider_id);

        $violations = [];

        if (!$provider['is_active']) {
            $violations[] = self::PROVIDER_INACTIVE;
        }

        if ($service_id !== null && !in_array($service_id, array_map('intval', $provider['services']), true)) {
            $violations[] = self::SERVICE_NOT_OFFERED;
        }

        if ($this->overlaps_appointments($provider_id, $start, $end, $exclude_appointment_id)) {
            $violations[] = self::OVERLAP_APPOINTMENT;
        }

        if ($this->overlaps_unavailabilities($provider_id, $start, $end)) {
            $violations[] = self::OVERLAP_UNAVAILABILITY;
        }

        if ($this->overlaps_blocked_periods($start, $end)) {
            $violations[] = self::BLOCKED_PERIOD;
        }

        // What is left is the working plan: outside the opening hours, in a break or on a day off.
        if (
            !array_intersect($violations, [
                self::OVERLAP_APPOINTMENT,
                self::OVERLAP_UNAVAILABILITY,
                self::BLOCKED_PERIOD,
            ]) &&
            !$this->fits_free_period($provider, $start, $end, $exclude_appointment_id)
        ) {
            $violations[] = self::OUTSIDE_WORKING_PLAN;
        }

        return $violations;
    }

    /**
     * Check whether an edit moves the appointment to another slot and therefore has to be checked again.
     *
     * Editing only the notes of an appointment that was confirmed outside the rules must not be refused.
     *
     * @param array $before Stored appointment (empty for a new one).
     * @param array $after Appointment to save.
     *
     * @return bool
     */
    public static function slot_changed(array $before, array $after): bool
    {
        if (!$before) {
            return true;
        }

        foreach (['id_users_provider', 'id_services', 'start_datetime', 'end_datetime'] as $field) {
            if (array_key_exists($field, $after) && (string) $after[$field] !== (string) ($before[$field] ?? '')) {
                return true;
            }
        }

        // An appointment that took no slot (e.g. cancelled) takes one again.
        $free_codes = appointment_status_free_codes();

        return in_array($before['status'] ?? '', $free_codes, true) &&
            !in_array($after['status'] ?? $before['status'], $free_codes, true);
    }

    /**
     * Check whether every violation can be confirmed by the user.
     *
     * @param array $violations Violation codes.
     *
     * @return bool
     */
    public static function is_forceable(array $violations): bool
    {
        return !array_diff($violations, self::FORCEABLE);
    }

    /**
     * Get a readable message for the violations.
     *
     * @param array $violations Violation codes.
     *
     * @return string
     */
    public static function message(array $violations): string
    {
        return implode(' ', array_map(fn(string $violation): string => lang('slot_rule_' . $violation), $violations));
    }

    /**
     * Run a callback while holding the lock of a provider, inside a transaction.
     *
     * The provider row is locked (SELECT ... FOR UPDATE) before anything is read, so a second session that wants to
     * book the same provider waits until the first one commits and then sees its appointment. The callback must check
     * the rules and save; the transaction is committed when it returns and rolled back when it throws.
     *
     * @param int $provider_id Provider ID.
     * @param callable $callback Work to do while holding the lock.
     *
     * @return mixed The callback result.
     *
     * @throws Throwable
     */
    public function lock_provider(int $provider_id, callable $callback): mixed
    {
        $db = $this->CI->db;

        $db->trans_begin();

        try {
            $db->query('SELECT id FROM ' . $db->dbprefix('users') . ' WHERE id = ? FOR UPDATE', [$provider_id]);

            $result = $callback();

            if ($db->trans_status() === false) {
                throw new RuntimeException('The appointment could not be saved.');
            }

            $db->trans_commit();

            return $result;
        } catch (Throwable $e) {
            $db->trans_rollback();

            throw $e;
        }
    }

    /**
     * Check for appointments that take their slot in the period.
     */
    protected function overlaps_appointments(
        int $provider_id,
        DateTime $start,
        DateTime $end,
        ?int $exclude_appointment_id,
    ): bool {
        return $this->CI->appointments_model->has_provider_conflict(
            $provider_id,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $exclude_appointment_id,
            true,
        );
    }

    /**
     * Check for unavailabilities of the provider in the period.
     */
    protected function overlaps_unavailabilities(int $provider_id, DateTime $start, DateTime $end): bool
    {
        return $this->CI->db
            ->from('appointments')
            ->where('id_users_provider', $provider_id)
            ->where('is_unavailability', true)
            ->where('start_datetime <', $end->format('Y-m-d H:i:s'))
            ->where('end_datetime >', $start->format('Y-m-d H:i:s'))
            ->count_all_results() > 0;
    }

    /**
     * Check for blocked periods (whole clinic) in the period.
     */
    protected function overlaps_blocked_periods(DateTime $start, DateTime $end): bool
    {
        $blocked_periods = $this->CI->blocked_periods_model->get_for_period(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        );

        foreach ($blocked_periods as $blocked_period) {
            if (
                new DateTime($blocked_period['start_datetime']) < $end &&
                new DateTime($blocked_period['end_datetime']) > $start
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check that the period fits in one of the free periods of the working plan of its day.
     */
    protected function fits_free_period(
        array $provider,
        DateTime $start,
        DateTime $end,
        ?int $exclude_appointment_id,
    ): bool {
        // Appointments that cross midnight never fit a working day.
        if ($start->format('Y-m-d') !== $end->format('Y-m-d') && $end->format('H:i:s') !== '00:00:00') {
            return false;
        }

        $date = $start->format('Y-m-d');

        $periods = $this->CI->availability->get_available_periods($date, $provider, $exclude_appointment_id);

        foreach ($periods as $period) {
            $period_start = new DateTime($date . ' ' . $period['start']);
            $period_end = new DateTime($date . ' ' . $period['end']);

            if ($period_start <= $start && $end <= $period_end) {
                return true;
            }
        }

        return false;
    }
}
