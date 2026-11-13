<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Blocked-Periods model.
 *
 * Handles all the database operations of the blocked-period resource.
 *
 * @package Models
 */
class Blocked_periods_model extends EA_Model
{
    /**
     * Kinds of blocked periods (specification 10.1).
     */
    public const TYPES = [
        'holiday_national',
        'holiday_regional',
        'holiday_local',
        'clinic_closure',
        'bridge',
        'vacation',
        'day_off',
        'personal_absence',
        'meeting',
        'training',
        'break',
        'extraordinary',
        'other',
    ];

    public const RECURRENCES = ['none', 'weekly', 'yearly'];

    /**
     * Repetitions are expanded at most this many times, which covers more than ten years of weekly periods.
     */
    private const MAX_OCCURRENCES = 600;

    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
    ];

    /**
     * @var array
     */
    protected array $api_resource = [
        'id' => 'id',
        'name' => 'name',
        'start' => 'start_datetime',
        'end' => 'end_datetime',
        'notes' => 'notes',
        'type' => 'type',
        'recurrence' => 'recurrence',
        'recurrenceUntil' => 'recurrence_until',
    ];

    /**
     * Save (insert or update) a blocked-period.
     *
     * @param array $blocked_period Associative array with the blocked-period data.
     *
     * @return int Returns the blocked-period ID.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function save(array $blocked_period): int
    {
        $this->validate($blocked_period);

        // The providers are kept in their own table; without providers the period closes the whole clinic.
        $provider_ids = array_key_exists('provider_ids', $blocked_period)
            ? self::provider_ids($blocked_period['provider_ids'])
            : null;

        unset($blocked_period['provider_ids']);

        if (array_key_exists('recurrence_until', $blocked_period)) {
            $blocked_period['recurrence_until'] = $blocked_period['recurrence_until'] ?: null;
        }

        $blocked_period_id = empty($blocked_period['id'])
            ? $this->insert($blocked_period)
            : $this->update($blocked_period);

        if ($provider_ids !== null) {
            $this->db->delete('blocked_periods_providers', ['id_blocked_periods' => $blocked_period_id]);

            foreach ($provider_ids as $provider_id) {
                $this->db->insert('blocked_periods_providers', [
                    'id_blocked_periods' => $blocked_period_id,
                    'id_users_provider' => $provider_id,
                ]);
            }
        }

        return $blocked_period_id;
    }

    /**
     * Validate the blocked-period data.
     *
     * @param array $blocked_period Associative array with the blocked-period data.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function validate(array $blocked_period): void
    {
        // If a blocked-period ID is provided then check whether the record really exists in the database.
        if (!empty($blocked_period['id'])) {
            $count = $this->db->get_where('blocked_periods', ['id' => $blocked_period['id']])->num_rows();

            if (!$count) {
                throw new InvalidArgumentException(
                    'The provided blocked-period ID does not exist in the database: ' . $blocked_period['id'],
                );
            }
        }

        // Make sure all required fields are provided.
        if (
            empty($blocked_period['name']) ||
            empty($blocked_period['start_datetime']) ||
            empty($blocked_period['end_datetime'])
        ) {
            throw new InvalidArgumentException(
                'Not all required fields are provided: ' . print_r($blocked_period, true),
            );
        }

        if (
            !validate_datetime($blocked_period['start_datetime']) ||
            !validate_datetime($blocked_period['end_datetime'])
        ) {
            throw new InvalidArgumentException('The blocked-period start or end date time is invalid.');
        }

        // Make sure that the start date time is before the end.
        $start_date_time_object = new DateTime($blocked_period['start_datetime']);
        $end_date_time_object = new DateTime($blocked_period['end_datetime']);

        if ($start_date_time_object >= $end_date_time_object) {
            throw new InvalidArgumentException('The start must be before the end date time value.');
        }

        if (isset($blocked_period['type']) && !in_array($blocked_period['type'], self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown blocked-period type: ' . $blocked_period['type']);
        }

        $recurrence = $blocked_period['recurrence'] ?? 'none';

        if (!in_array($recurrence, self::RECURRENCES, true)) {
            throw new InvalidArgumentException('Unknown blocked-period recurrence: ' . $recurrence);
        }

        $until = $blocked_period['recurrence_until'] ?? null;

        if ($until) {
            if (!validate_datetime($until . ' 00:00:00')) {
                throw new InvalidArgumentException('The repetition end date is invalid.');
            }

            if ($until < $start_date_time_object->format('Y-m-d')) {
                throw new InvalidArgumentException(lang('blocked_period_until_before_start'));
            }
        }

        // A weekly repetition only makes sense for periods shorter than a week.
        if ($recurrence === 'weekly' && $start_date_time_object->diff($end_date_time_object)->days >= 7) {
            throw new InvalidArgumentException(lang('blocked_period_weekly_too_long'));
        }

        $provider_ids = self::provider_ids($blocked_period['provider_ids'] ?? []);

        if ($provider_ids) {
            $count = $this->db
                ->from('users')
                ->join('roles', 'roles.id = users.id_roles', 'inner')
                ->where('roles.slug', DB_SLUG_PROVIDER)
                ->where_in('users.id', $provider_ids)
                ->count_all_results();

            if ($count !== count($provider_ids)) {
                throw new InvalidArgumentException('Some of the providers of the blocked period do not exist.');
            }
        }
    }

    /**
     * Insert a new blocked-period into the database.
     *
     * @param array $blocked_period Associative array with the blocked-period data.
     *
     * @return int Returns the blocked-period ID.
     *
     * @throws RuntimeException
     */
    protected function insert(array $blocked_period): int
    {
        $blocked_period['create_datetime'] = date('Y-m-d H:i:s');
        $blocked_period['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->insert('blocked_periods', $blocked_period)) {
            throw new RuntimeException('Could not insert blocked-period.');
        }

        return $this->db->insert_id();
    }

    /**
     * Update an existing blocked-period.
     *
     * @param array $blocked_period Associative array with the blocked-period data.
     *
     * @return int Returns the blocked-period ID.
     *
     * @throws RuntimeException
     */
    protected function update(array $blocked_period): int
    {
        $blocked_period['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->update('blocked_periods', $blocked_period, ['id' => $blocked_period['id']])) {
            throw new RuntimeException('Could not update blocked periods.');
        }

        return $blocked_period['id'];
    }

    /**
     * Remove an existing blocked-period from the database.
     *
     * @param int $blocked_period_id Blocked period ID.
     *
     * @throws RuntimeException
     */
    public function delete(int $blocked_period_id): void
    {
        $this->db->delete('blocked_periods', ['id' => $blocked_period_id]);
    }

    /**
     * Get a specific blocked-period from the database.
     *
     * @param int $blocked_period_id The ID of the record to be returned.
     *
     * @return array Returns an array with the blocked-period data.
     *
     * @throws InvalidArgumentException
     */
    public function find(int $blocked_period_id): array
    {
        $blocked_period = $this->db->get_where('blocked_periods', ['id' => $blocked_period_id])->row_array();

        if (!$blocked_period) {
            throw new InvalidArgumentException(
                'The provided blocked-period ID was not found in the database: ' . $blocked_period_id,
            );
        }

        $this->cast($blocked_period);

        $this->attach_provider_ids($blocked_period);

        return $blocked_period;
    }

    /**
     * Get a specific field value from the database.
     *
     * @param int $blocked_period_id Blocked period ID.
     * @param string $field Name of the value to be returned.
     *
     * @return mixed Returns the selected blocked-period value from the database.
     *
     * @throws InvalidArgumentException
     */
    public function value(int $blocked_period_id, string $field): mixed
    {
        if (empty($field)) {
            throw new InvalidArgumentException('The field argument is cannot be empty.');
        }

        if (empty($blocked_period_id)) {
            throw new InvalidArgumentException('The blocked-period ID argument cannot be empty.');
        }

        // Check whether the service exists.
        $query = $this->db->get_where('blocked_periods', ['id' => $blocked_period_id]);

        if (!$query->num_rows()) {
            throw new InvalidArgumentException(
                'The provided blocked-period ID was not found in the database: ' . $blocked_period_id,
            );
        }

        // Check if the required field is part of the blocked-period data.
        $blocked_period = $query->row_array();

        $this->cast($blocked_period);

        if (!array_key_exists($field, $blocked_period)) {
            throw new InvalidArgumentException(
                'The requested field was not found in the blocked-period data: ' . $field,
            );
        }

        return $blocked_period[$field];
    }

    /**
     * Search blocked periods by the provided keyword.
     *
     * @param string $keyword Search keyword.
     * @param int|null $limit Record limit.
     * @param int|null $offset Record offset.
     * @param string|null $order_by Order by.
     *
     * @return array Returns an array of blocked periods.
     */
    public function search(string $keyword, ?int $limit = null, ?int $offset = null, ?string $order_by = null): array
    {
        $blocked_periods = $this->db
            ->select()
            ->from('blocked_periods')
            ->group_start()
            ->like('name', $keyword)
            ->or_like('notes', $keyword)
            ->group_end()
            ->limit($limit)
            ->offset($offset)
            ->order_by($this->quote_order_by($order_by))
            ->get()
            ->result_array();

        foreach ($blocked_periods as &$blocked_period) {
            $this->cast($blocked_period);
            $this->attach_provider_ids($blocked_period);
        }

        return $blocked_periods;
    }

    /**
     * Get blocked periods as options for dropdowns.
     *
     * @param array|string|null $where Where conditions.
     *
     * @return array Returns an array of options with 'value' and 'label' keys.
     */
    public function to_options(array|string|null $where = null): array
    {
        if ($where !== null) {
            $this->db->where($where);
        }

        $blocked_periods = $this->db
            ->select('id, name')
            ->from('blocked_periods')
            ->order_by('name')
            ->get()
            ->result_array();

        $options = [];

        foreach ($blocked_periods as $blocked_period) {
            $options[] = [
                'value' => (int) $blocked_period['id'],
                'label' => $blocked_period['name'],
            ];
        }

        return $options;
    }

    /**
     * Get all services that match the provided criteria.
     *
     * @param array|string|null $where Where conditions
     * @param int|null $limit Record limit.
     * @param int|null $offset Record offset.
     * @param string|null $order_by Order by.
     *
     * @return array Returns an array of blocked periods.
     */
    public function get(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $order_by = null,
    ): array {
        if ($where !== null) {
            $this->db->where($where);
        }

        if ($order_by !== null) {
            $this->db->order_by($this->quote_order_by($order_by));
        }

        $blocked_periods = $this->db->get('blocked_periods', $limit, $offset)->result_array();

        foreach ($blocked_periods as &$blocked_period) {
            $this->cast($blocked_period);
            $this->attach_provider_ids($blocked_period);
        }

        return $blocked_periods;
    }

    /**
     * Load related resources to a blocked-period.
     *
     * @param array $blocked_period Associative array with the blocked-period data.
     * @param array $resources Resource names to be attached.
     *
     * @throws InvalidArgumentException
     */
    public function load(array &$blocked_period, array $resources)
    {
        // Blocked periods do not currently have any related resources.
    }

    /**
     * Convert the database blocked-period record to the equivalent API resource.
     *
     * @param array $blocked_period Blocked period data.
     */
    public function api_encode(array &$blocked_period): void
    {
        $encoded_resource = [
            'id' => array_key_exists('id', $blocked_period) ? (int) $blocked_period['id'] : null,
            'name' => $blocked_period['name'],
            'start' => array_key_exists('start_datetime', $blocked_period) ? $blocked_period['start_datetime'] : null,
            'end' => array_key_exists('end_datetime', $blocked_period) ? $blocked_period['end_datetime'] : null,
            'notes' => array_key_exists('notes', $blocked_period) ? $blocked_period['notes'] : null,
            'type' => $blocked_period['type'] ?? 'other',
            'providerIds' => array_map('intval', $blocked_period['provider_ids'] ?? []),
            'recurrence' => $blocked_period['recurrence'] ?? 'none',
            'recurrenceUntil' => $blocked_period['recurrence_until'] ?? null,
        ];

        $blocked_period = $encoded_resource;
    }

    /**
     * Convert the API resource to the equivalent database blocked-period record.
     *
     * @param array $blocked_period API resource.
     * @param array|null $base Base blocked-period data to be overwritten with the provided values (useful for updates).
     */
    public function api_decode(array &$blocked_period, ?array $base = null): void
    {
        $decoded_resource = $base ?: [];

        if (array_key_exists('id', $blocked_period)) {
            $decoded_resource['id'] = $blocked_period['id'];
        }

        if (array_key_exists('name', $blocked_period)) {
            $decoded_resource['name'] = $blocked_period['name'];
        }

        if (array_key_exists('start', $blocked_period)) {
            $decoded_resource['start_datetime'] = $blocked_period['start'];
        }

        if (array_key_exists('end', $blocked_period)) {
            $decoded_resource['end_datetime'] = $blocked_period['end'];
        }

        if (array_key_exists('notes', $blocked_period)) {
            $decoded_resource['notes'] = $blocked_period['notes'];
        }

        if (array_key_exists('type', $blocked_period)) {
            $decoded_resource['type'] = $blocked_period['type'];
        }

        if (array_key_exists('providerIds', $blocked_period)) {
            $decoded_resource['provider_ids'] = (array) $blocked_period['providerIds'];
        }

        if (array_key_exists('recurrence', $blocked_period)) {
            $decoded_resource['recurrence'] = $blocked_period['recurrence'];
        }

        if (array_key_exists('recurrenceUntil', $blocked_period)) {
            $decoded_resource['recurrence_until'] = $blocked_period['recurrenceUntil'];
        }

        $blocked_period = $decoded_resource;
    }

    /**
     * Get the blocked periods that fall within some dates, with their repetitions expanded.
     *
     * Every occurrence is returned with its own start_datetime and end_datetime, and with provider_ids (empty when the
     * period closes the whole clinic).
     *
     * @param string $start_date First date (Y-m-d).
     * @param string $end_date Last date (Y-m-d).
     * @param int|null $provider_id Only the periods that apply to this provider (null: all of them).
     *
     * @return array
     */
    public function get_for_period(string $start_date, string $end_date, ?int $provider_id = null): array
    {
        $from = $start_date . ' 00:00:00';
        $to = $end_date . ' 23:59:59';

        $blocked_periods = $this->query()
            ->where('start_datetime <=', $to)
            ->group_start()
            ->group_start()
            ->where('recurrence', 'none')
            ->where('end_datetime >=', $from)
            ->group_end()
            ->or_group_start()
            ->where('recurrence !=', 'none')
            ->group_start()
            ->where('recurrence_until IS NULL')
            ->or_where('recurrence_until >=', $start_date)
            ->group_end()
            ->group_end()
            ->group_end()
            ->get()
            ->result_array();

        $occurrences = [];

        foreach ($blocked_periods as $blocked_period) {
            $this->cast($blocked_period);
            $this->attach_provider_ids($blocked_period);

            if (
                $provider_id &&
                $blocked_period['provider_ids'] &&
                !in_array($provider_id, $blocked_period['provider_ids'])
            ) {
                continue;
            }

            array_push($occurrences, ...self::expand_occurrences($blocked_period, $from, $to));
        }

        return $occurrences;
    }

    /**
     * Get the occurrences of a blocked period that overlap a time range.
     *
     * A period without repetition is its only occurrence. A weekly or yearly period repeats with the same duration
     * until its recurrence_until date (included), or forever.
     *
     * @param array $blocked_period Blocked period with start_datetime, end_datetime, recurrence and recurrence_until.
     * @param string $from Start of the range (Y-m-d H:i:s).
     * @param string $to End of the range (Y-m-d H:i:s).
     *
     * @return array Copies of the period with the start_datetime and end_datetime of each occurrence.
     */
    public static function expand_occurrences(array $blocked_period, string $from, string $to): array
    {
        $unit = ['weekly' => 'week', 'yearly' => 'year'][$blocked_period['recurrence'] ?? 'none'] ?? null;
        $start = new DateTime($blocked_period['start_datetime']);
        $end = new DateTime($blocked_period['end_datetime']);
        $duration = $start->diff($end);
        $until = !empty($blocked_period['recurrence_until']) ? $blocked_period['recurrence_until'] . ' 23:59:59' : null;
        $occurrences = [];

        for ($index = 0; $index < ($unit ? self::MAX_OCCURRENCES : 1); $index++) {
            // Each occurrence is counted from the first one, so a yearly period on the 31st never drifts.
            $occurrence_start = $unit ? (clone $start)->modify('+' . $index . ' ' . $unit) : clone $start;
            $occurrence_end = (clone $occurrence_start)->add($duration);

            if (
                $occurrence_start->format('Y-m-d H:i:s') > $to ||
                ($until && $occurrence_start->format('Y-m-d H:i:s') > $until)
            ) {
                break;
            }

            if ($occurrence_end->format('Y-m-d H:i:s') >= $from) {
                $occurrences[] = array_merge($blocked_period, [
                    'start_datetime' => $occurrence_start->format('Y-m-d H:i:s'),
                    'end_datetime' => $occurrence_end->format('Y-m-d H:i:s'),
                ]);
            }
        }

        return $occurrences;
    }

    /**
     * Find the appointments that a blocked period would fall on, so that they can be rescheduled first.
     *
     * Cancelled appointments do not count. A repetition without end date is checked for one year.
     *
     * @param array $blocked_period Blocked period to save (with provider_ids, recurrence and recurrence_until).
     *
     * @return array Appointments with id, hash, start_datetime, end_datetime, provider_name and customer_name.
     */
    public function get_affected_appointments(array $blocked_period): array
    {
        $start = $blocked_period['start_datetime'];
        $last = !empty($blocked_period['recurrence_until'])
            ? $blocked_period['recurrence_until'] . ' 23:59:59'
            : (($blocked_period['recurrence'] ?? 'none') === 'none'
                ? $blocked_period['end_datetime']
                : date('Y-m-d H:i:s', strtotime($start . ' +1 year')));

        $occurrences = self::expand_occurrences($blocked_period, $start, $last);

        if (!$occurrences) {
            return [];
        }

        $provider_ids = self::provider_ids($blocked_period['provider_ids'] ?? []);

        // Read first: it runs its own query, which would reset the one being built.
        $free_codes = appointment_status_free_codes();

        $query = $this->db
            ->select(
                'appointments.id, appointments.hash, appointments.start_datetime, appointments.end_datetime, ' .
                    "CONCAT_WS(' ', providers.first_name, providers.last_name) AS provider_name, " .
                    "CONCAT_WS(' ', customers.first_name, customers.last_name) AS customer_name",
                false,
            )
            // Aliased, so that the hand written select above needs no table prefix.
            ->from('appointments AS appointments')
            ->join('users AS providers', 'providers.id = appointments.id_users_provider', 'inner')
            ->join('users AS customers', 'customers.id = appointments.id_users_customer', 'left')
            ->where('appointments.is_unavailability', false)
            ->where('appointments.start_datetime <', end($occurrences)['end_datetime'])
            ->where('appointments.end_datetime >', $occurrences[0]['start_datetime'])
            ->order_by('appointments.start_datetime');

        if ($free_codes) {
            $query->where_not_in('appointments.status', $free_codes);
        }

        if ($provider_ids) {
            $query->where_in('appointments.id_users_provider', $provider_ids);
        }

        $appointments = $query->get()->result_array();

        return array_values(
            array_filter($appointments, function (array $appointment) use ($occurrences): bool {
                foreach ($occurrences as $occurrence) {
                    if (
                        $appointment['start_datetime'] < $occurrence['end_datetime'] &&
                        $appointment['end_datetime'] > $occurrence['start_datetime']
                    ) {
                        return true;
                    }
                }

                return false;
            }),
        );
    }

    /**
     * Clean a list of provider IDs, as sent by a form (it may be empty or hold empty values).
     *
     * @param mixed $provider_ids
     *
     * @return array Unique positive IDs.
     */
    private static function provider_ids(mixed $provider_ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $provider_ids))));
    }

    /**
     * Add the IDs of the providers a blocked period applies to (empty for the whole clinic).
     *
     * @param array $blocked_period
     */
    private function attach_provider_ids(array &$blocked_period): void
    {
        $blocked_period['provider_ids'] = empty($blocked_period['id'])
            ? []
            : array_map(
                'intval',
                array_column(
                    $this->db
                        ->select('id_users_provider')
                        ->get_where('blocked_periods_providers', ['id_blocked_periods' => $blocked_period['id']])
                        ->result_array(),
                    'id_users_provider',
                ),
            );
    }

    /**
     * Get the query builder interface, configured for use with the blocked periods table.
     *
     * @return CI_DB_query_builder
     */
    public function query(): CI_DB_query_builder
    {
        return $this->db->from('blocked_periods');
    }

    /**
     * Check if a date is entirely covered by a single blocked period (or occurrence of one).
     *
     * Periods that only cover a part of the date are removed from the available hours separately.
     *
     * @param string $date
     * @param int|null $provider_id Only consider the periods that apply to this provider (null: all of them).
     *
     * @return bool
     */
    public function is_entire_date_blocked(string $date, ?int $provider_id = null): bool
    {
        // A period that ends at 23:59 counts as covering the rest of the day, as the date pickers have minute precision.
        foreach ($this->get_for_period($date, $date, $provider_id) as $occurrence) {
            if (
                $occurrence['start_datetime'] <= $date . ' 00:00:00' &&
                $occurrence['end_datetime'] >= $date . ' 23:59:00'
            ) {
                return true;
            }
        }

        return false;
    }
}
