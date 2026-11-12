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
 * Slot finder ("Buscar hueco").
 *
 * Finds the free slots of one or more providers for a duration in a date range, using the free periods of the
 * availability engine (working plan, exceptions, breaks, blocked periods, unavailabilities and appointments), so it
 * never offers a slot that saving would refuse. The slots can be limited to a time window, to a margin around a
 * preferred time and to some weekdays, and are sorted by date or by closeness to the preferred time, with the
 * preferred provider first on ties.
 */
class Slot_finder
{
    public const MAX_RANGE_DAYS = 92;
    public const MAX_RESULTS = 100;

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Slot_finder constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('providers_model');

        $this->CI->load->library('availability');
    }

    /**
     * Search the free slots.
     *
     * @param array $providers Providers to search (records with settings, e.g. from providers_model->find()).
     * @param int $duration Appointment duration in minutes.
     * @param string $date_from First date (Y-m-d).
     * @param string $date_to Last date (Y-m-d).
     * @param array $constraints See matches() for the keys.
     * @param int|null $preferred_provider_id Provider sorted first on ties (e.g. the usual one of the patient).
     * @param int $limit Maximum number of slots.
     *
     * @return array List of slots: provider_id, start (Y-m-d H:i:s), end (Y-m-d H:i:s), distance (minutes from the
     * preferred time or null), is_preferred_provider.
     */
    public function search(
        array $providers,
        int $duration,
        string $date_from,
        string $date_to,
        array $constraints = [],
        ?int $preferred_provider_id = null,
        int $limit = 20,
    ): array {
        $interval = calendar_slot_minutes();
        $now = new DateTime();
        $slots = [];

        $date = new DateTime($date_from);
        $last = new DateTime($date_to);

        // Without a preferred time the slots are sorted chronologically, so the search can stop once the day that
        // completes the result list has been processed.
        $chronological = empty($constraints['preferred_time']);

        // The appointments, unavailabilities, blocked periods and exceptions are loaded once per provider for the
        // whole range, instead of once per provider and day.
        $preloaded = [];

        foreach ($providers as $provider) {
            $preloaded[$provider['id']] = $this->CI->availability->preload_period_data($provider, $date_from, $date_to);
        }

        while ($date <= $last) {
            foreach ($providers as $provider) {
                $periods = $this->CI->availability->get_available_periods(
                    $date->format('Y-m-d'),
                    $provider,
                    null,
                    $preloaded[$provider['id']],
                );

                foreach (self::slots_in_periods($date->format('Y-m-d'), $periods, $duration, $interval) as $slot) {
                    if (new DateTime($slot['start']) < $now || !self::matches($slot, $constraints)) {
                        continue;
                    }

                    $slots[] = $slot + [
                        'provider_id' => (int) $provider['id'],
                        'distance' => self::distance($slot, $constraints['preferred_time'] ?? null),
                        'is_preferred_provider' => (int) $provider['id'] === $preferred_provider_id,
                    ];
                }
            }

            if ($chronological && count($slots) >= $limit) {
                break;
            }

            $date->modify('+1 day');
        }

        return array_slice(self::sort($slots), 0, $limit);
    }

    /**
     * Get the start times that fit a duration in the free periods of a day.
     *
     * Starts are aligned to the calendar grid (e.g. 09:00, 09:15...), and the whole duration must fit before the
     * period ends, so a 45 minutes appointment is never offered in a 30 minutes gap.
     *
     * @param string $date Date (Y-m-d).
     * @param array $periods Free periods: [['start' => 'H:i', 'end' => 'H:i'], ...].
     * @param int $duration Minutes.
     * @param int $interval Grid interval in minutes.
     *
     * @return array List of ['start' => 'Y-m-d H:i:s', 'end' => 'Y-m-d H:i:s'].
     */
    public static function slots_in_periods(string $date, array $periods, int $duration, int $interval): array
    {
        $slots = [];

        foreach ($periods as $period) {
            $period_start = new DateTime($date . ' ' . $period['start']);
            $period_end = new DateTime($date . ' ' . $period['end']);

            // First grid time at or after the start of the period.
            $minutes = (int) $period_start->format('H') * 60 + (int) $period_start->format('i');
            $remainder = $minutes % $interval;

            $start = clone $period_start;

            if ($remainder) {
                $start->modify('+' . ($interval - $remainder) . ' minutes');
            }

            while (true) {
                $end = (clone $start)->modify('+' . $duration . ' minutes');

                if ($end > $period_end) {
                    break;
                }

                $slots[] = ['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s')];

                $start->modify('+' . $interval . ' minutes');
            }
        }

        usort($slots, fn(array $a, array $b): int => strcmp($a['start'], $b['start']));

        return $slots;
    }

    /**
     * Check a slot against the search constraints.
     *
     * @param array $slot ['start' => 'Y-m-d H:i:s', 'end' => 'Y-m-d H:i:s'].
     * @param array $constraints Optional keys:
     *   - time_from / time_to ('H:i'): the slot must start and end inside this window (e.g. mornings).
     *   - preferred_time ('H:i') and margin (minutes): the slot must start within the margin of the preferred time.
     *   - weekdays (ISO numbers, 1 = Monday): allowed days.
     *
     * @return bool
     */
    public static function matches(array $slot, array $constraints): bool
    {
        $start = new DateTime($slot['start']);
        $end = new DateTime($slot['end']);

        if (!empty($constraints['weekdays']) && !in_array((int) $start->format('N'), $constraints['weekdays'], true)) {
            return false;
        }

        if (!empty($constraints['time_from']) && $start->format('H:i') < $constraints['time_from']) {
            return false;
        }

        if (!empty($constraints['time_to']) && $end->format('H:i') > $constraints['time_to']) {
            return false;
        }

        if (!empty($constraints['preferred_time'])) {
            return self::distance($slot, $constraints['preferred_time']) <= (int) ($constraints['margin'] ?? 0);
        }

        return true;
    }

    /**
     * Get the minutes between the start of a slot and the preferred time of its day.
     *
     * @param array $slot Slot.
     * @param string|null $preferred_time 'H:i'.
     *
     * @return int|null
     */
    public static function distance(array $slot, ?string $preferred_time): ?int
    {
        if (empty($preferred_time)) {
            return null;
        }

        $start = new DateTime($slot['start']);
        $preferred = new DateTime($start->format('Y-m-d') . ' ' . $preferred_time);

        return (int) abs(($start->getTimestamp() - $preferred->getTimestamp()) / 60);
    }

    /**
     * Sort the slots: by date, then by closeness to the preferred time (when there is one) or by time, then the
     * preferred provider first.
     *
     * @param array $slots Slots from search().
     *
     * @return array
     */
    public static function sort(array $slots): array
    {
        usort($slots, function (array $a, array $b): int {
            return [
                substr($a['start'], 0, 10),
                $a['distance'] ?? 0,
                $a['start'],
                $a['is_preferred_provider'] ? 0 : 1,
                $a['provider_id'],
            ] <=> [
                substr($b['start'], 0, 10),
                $b['distance'] ?? 0,
                $b['start'],
                $b['is_preferred_provider'] ? 0 : 1,
                $b['provider_id'],
            ];
        });

        return $slots;
    }
}
