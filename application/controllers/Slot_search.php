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
 * Slot search controller ("Buscar hueco" of the calendar page).
 */
class Slot_search extends EA_Controller
{
    /**
     * Slot_search constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('providers_model');
        $this->load->model('services_model');

        $this->load->library('slot_finder');
    }

    /**
     * Search the free slots for a service.
     *
     * Only the active providers that offer the service and that the current user can manage are searched (all for
     * administrators and reception with every provider assigned, the own agenda for a provider).
     */
    public function search(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('service_id', 'numeric');
            check('duration', 'numeric|null');
            check('provider_ids', 'array|null');
            check('preferred_provider_id', 'numeric|null');
            check('date_from', 'string|null');
            check('date_to', 'string|null');
            check('time_from', 'string|null');
            check('time_to', 'string|null');
            check('preferred_time', 'string|null');
            check('margin', 'numeric|null');
            check('weekdays', 'array|null');

            $service = $this->services_model->find((int) request('service_id'));

            $duration = (int) (request('duration') ?: $service['duration']);

            if ($duration < EVENT_MINIMUM_DURATION || $duration > 24 * 60) {
                throw new InvalidArgumentException(lang('appointment_duration_invalid'));
            }

            $date_from = $this->date(request('date_from')) ?? date('Y-m-d');
            $date_to = $this->date(request('date_to')) ?? date('Y-m-d', strtotime($date_from . ' +13 days'));

            $range_days = (strtotime($date_to) - strtotime($date_from)) / 86400;

            if ($range_days < 0 || $range_days >= Slot_finder::MAX_RANGE_DAYS) {
                throw new InvalidArgumentException(lang('slot_search_invalid_range'));
            }

            $provider_ids = array_map('intval', request('provider_ids') ?: []);

            $providers = array_values(
                array_filter(
                    $this->providers_model->get_available_providers(),
                    fn(array $provider): bool => in_array(
                        (int) $service['id'],
                        array_map('intval', $provider['services']),
                        true,
                    ) &&
                        (!$provider_ids || in_array((int) $provider['id'], $provider_ids, true)) &&
                        can_manage_provider((int) $provider['id']),
                ),
            );

            $constraints = [
                'time_from' => $this->time(request('time_from')),
                'time_to' => $this->time(request('time_to')),
                'preferred_time' => $this->time(request('preferred_time')),
                'margin' => max(0, min(240, (int) request('margin'))),
                'weekdays' => array_values(
                    array_filter(
                        array_map('intval', request('weekdays') ?: []),
                        fn(int $weekday): bool => $weekday >= 1 && $weekday <= 7,
                    ),
                ),
            ];

            $preferred_provider_id = request('preferred_provider_id');

            $slots = $this->slot_finder->search(
                $providers,
                $duration,
                $date_from,
                $date_to,
                $constraints,
                $preferred_provider_id ? (int) $preferred_provider_id : null,
                30,
            );

            $names = array_column(
                array_map(
                    fn(array $provider): array => [
                        'id' => $provider['id'],
                        'name' => trim($provider['first_name'] . ' ' . $provider['last_name']),
                    ],
                    $providers,
                ),
                'name',
                'id',
            );

            json_response([
                'service' => ['id' => (int) $service['id'], 'name' => $service['name']],
                'duration' => $duration,
                'slots' => array_map(
                    fn(array $slot): array => [
                        'provider_id' => $slot['provider_id'],
                        'provider_name' => $names[$slot['provider_id']] ?? '',
                        'start' => $slot['start'],
                        'end' => $slot['end'],
                        'distance' => $slot['distance'],
                        'is_preferred_provider' => $slot['is_preferred_provider'],
                    ],
                    $slots,
                ),
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Read an optional date (Y-m-d).
     *
     * @throws InvalidArgumentException
     */
    protected function date(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $date = DateTime::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid date: ' . $value);
        }

        return $value;
    }

    /**
     * Read an optional time (H:i).
     *
     * @throws InvalidArgumentException
     */
    protected function time(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw new InvalidArgumentException('Invalid time: ' . $value);
        }

        return $value;
    }
}
