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
 * Audit trail.
 *
 * Reads the audit log (see audit_helper) for the "Activity log" page, in plain words: who changed what and when, the
 * record each change is about ("Appointment of Maria Acasio, 16/11/2026 10:15") and every field that changed with its
 * label and its values ("Provider: Sara → Sebas"). Only changes are listed: the views of the clinical records and the
 * downloads of backups stay in the log but not on the page.
 *
 * @package Libraries
 */
class Audit_trail
{
    /**
     * Actions of the log that are not changes, left out of the page.
     */
    public const HIDDEN_ACTIONS = ['viewed', 'download'];

    public const PER_PAGE = 50;

    /**
     * Label of a field, by field name, when it is not the translation of the field name itself.
     */
    private const FIELD_LABELS = [
        'start_datetime' => 'start',
        'end_datetime' => 'end',
        'id_users_provider' => 'provider',
        'id_users_customer' => 'customer',
        'id_users_preferred_provider' => 'usual_provider',
        'id_services' => 'service',
        'id_service_categories' => 'category',
        'id_podology_pathologies' => 'pathology',
        'is_first_visit' => 'first_visit',
        'password_fingerprint' => 'password',
        'providers' => 'providers',
        'services' => 'services',
        'birth_date' => 'clinical_birth_date',
        'referral_source' => 'clinical_referral_source',
        'profession' => 'clinical_profession',
        'physical_activity' => 'clinical_physical_activity',
        'medical_history' => 'clinical_medical_history',
        'allergies' => 'clinical_allergies',
        'reason' => 'clinical_reason',
        'diagnosis' => 'clinical_diagnosis',
        'treatment' => 'clinical_treatment',
        'observations' => 'clinical_observations',
        'orthosis' => 'clinical_orthosis',
        'document_type' => 'fiscal_document_type',
        'document_number' => 'fiscal_document_number',
        'province' => 'fiscal_province',
        'country' => 'fiscal_country',
        'is_private' => 'audit_field_is_private',
        'is_active' => 'audit_field_is_active',
        'calendar_view' => 'audit_field_calendar_view',
        'notifications' => 'audit_field_notifications',
        'start_time' => 'audit_field_start_time',
        'end_time' => 'audit_field_end_time',
        'recurrence' => 'audit_field_recurrence',
        'recurrence_until' => 'audit_field_recurrence_until',
        'type' => 'audit_field_type',
        'is_healed' => 'audit_field_is_healed',
        'appointment_datetime' => 'appointment',
        'change_handled_datetime' => 'whatsapp_change_handled',
        'level' => 'audit_field_level',
        'phone_normalized' => 'phone_number',
        'file' => 'audit_field_file',
        'previous' => 'audit_field_previous',
    ];

    /**
     * Fields that hold a user ID, a service ID, a category ID or lists of them, shown by name.
     */
    private const USER_FIELDS = ['id_users_provider', 'id_users_customer', 'id_users_preferred_provider', 'id_users'];

    private const SERVICE_FIELDS = ['id_services'];

    private const CATEGORY_FIELDS = ['id_service_categories'];

    /**
     * Values longer than this (working plans, breaks, long texts) are only said to have changed.
     */
    private const MAX_VALUE_LENGTH = 120;

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Names already looked up, by kind and ID.
     */
    private array $names = ['user' => [], 'service' => [], 'category' => [], 'pathology' => []];

    private ?array $statuses = null;

    /**
     * People found by each search, as the page runs the query twice (count and rows).
     */
    private array $people = [];

    /**
     * Audit_trail constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Get a page of the log, the latest first.
     *
     * @param array $filters from and to (Y-m-d), user (ID), entity, action and q (the search box), all optional.
     * @param int $page From 1.
     *
     * @return array ['entries' => [...], 'total' => n]: every entry with datetime, user, source, action, entity,
     * entity_id, subject and changes (see describe()).
     */
    public function page(array $filters, int $page = 1): array
    {
        $count = $this->query($filters)->count_all_results();

        $rows = $this->query($filters)
            ->select('audit_log.*')
            ->select("CONCAT_WS(' ', staff.first_name, staff.last_name) AS user_name", false)
            ->join('users AS staff', 'staff.id = audit_log.id_users', 'left')
            ->order_by('audit_log.id', 'DESC')
            ->limit(self::PER_PAGE, (max(1, $page) - 1) * self::PER_PAGE)
            ->get()
            ->result_array();

        return ['entries' => array_map(fn(array $row) => $this->describe($row), $rows), 'total' => $count];
    }

    /**
     * Get the kinds of records and the actions that appear in the log, for the filters.
     *
     * @return array ['entities' => [...], 'actions' => [...]]
     */
    public function filter_options(): array
    {
        $column = fn(string $field): array => array_column(
            $this->CI->db
                ->distinct()
                ->select($field)
                ->where_not_in('action', self::HIDDEN_ACTIONS)
                ->order_by($field)
                ->get('audit_log')
                ->result_array(),
            $field,
        );

        return ['entities' => $column('entity'), 'actions' => $column('action')];
    }

    /**
     * Get the users of the staff, for the filter.
     *
     * @return array ID => name.
     */
    public function staff(): array
    {
        $rows = $this->CI->db
            ->select('staff.id')
            ->select("CONCAT_WS(' ', staff.first_name, staff.last_name) AS name", false)
            ->from('users AS staff')
            ->join('roles', 'roles.id = staff.id_roles', 'inner')
            ->where_in('roles.slug', [DB_SLUG_ADMIN, DB_SLUG_PROVIDER, DB_SLUG_SECRETARY])
            ->order_by('staff.first_name')
            ->get()
            ->result_array();

        return array_column($rows, 'name', 'id');
    }

    /**
     * Build the query of the log with the filters.
     *
     * @param array $filters
     *
     * @return CI_DB_query_builder
     */
    private function query(array $filters): CI_DB_query_builder
    {
        // Worked out first: it runs its own queries, and the query builder is shared.
        $search = trim((string) ($filters['q'] ?? ''));
        $search_conditions = mb_strlen($search) >= 2 ? $this->search_conditions($search) : [];

        $query = $this->CI->db->from('audit_log')->where_not_in('audit_log.action', self::HIDDEN_ACTIONS);

        if (!empty($filters['from'])) {
            $query->where('audit_log.create_datetime >=', $filters['from'] . ' 00:00:00');
        }

        if (!empty($filters['to'])) {
            $query->where('audit_log.create_datetime <=', $filters['to'] . ' 23:59:59');
        }

        if (!empty($filters['user'])) {
            $query->where('audit_log.id_users', (int) $filters['user']);
        }

        if (!empty($filters['entity'])) {
            $query->where('audit_log.entity', $filters['entity']);
        }

        if (!empty($filters['action'])) {
            $query->where('audit_log.action', $filters['action']);
        }

        if ($search_conditions) {
            $query->where('(' . implode(' OR ', $search_conditions) . ')', null, false);
        }

        return $query;
    }

    /**
     * Conditions of the search box (any of them): the text in the values of the changes, a date typed as d/m/Y,
     * and the people and treatments whose name matches, with their records: a patient finds the changes of the
     * patient, of their appointments, clinical records, fiscal data and WhatsApp messages, also the removed ones; a
     * person of the staff finds also what they did.
     *
     * @param string $search
     *
     * @return array SQL conditions.
     */
    private function search_conditions(string $search): array
    {
        $db = $this->CI->db;
        $t = fn(string $table): string => $db->protect_identifiers($table, true);
        // escape_like_str() already escapes the text for SQL (quotes too), it is not escaped again.
        $like = fn(string $text): string => "'%" . $db->escape_like_str($text) . "%' ESCAPE '!'";

        $log = $t('audit_log');
        $conditions = ["$log.changes LIKE " . $like($search)];

        // A date typed as the clinic writes it (16/11/2026) is looked for as it is stored.
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $search, $date)) {
            $iso = sprintf('%04d-%02d-%02d', $date[3], $date[2], $date[1]);
            $conditions[] = "$log.changes LIKE " . $like($iso);
            $conditions[] = "$log.create_datetime LIKE " . $db->escape($iso . '%');
        }

        $people = $this->matching_people($search);

        if ($people) {
            $ids = implode(',', $people);
            $in = fn(string $entity, string $select): string => "($log.entity = " .
                $db->escape($entity) .
                " AND $log.entity_id IN ($select))";

            $conditions[] = "$log.id_users IN ($ids)";
            // The entries of a patient record carry the ID of the patient too.
            $conditions[] =
                "($log.entity IN ('customer', 'provider', 'secretary', 'admin', 'user', 'patient_record') " .
                "AND $log.entity_id IN ($ids))";

            $appointments =
                'SELECT id FROM ' .
                $t('appointments') .
                " WHERE id_users_customer IN ($ids) OR id_users_provider IN ($ids)";

            $conditions[] = $in('appointment', $appointments);
            $conditions[] = $in('unavailability', $appointments);
            $conditions[] = $in(
                'whatsapp_message',
                'SELECT id FROM ' . $t('whatsapp_reminders') . " WHERE id_users_customer IN ($ids)",
            );
            $conditions[] = $in(
                'fiscal_party',
                'SELECT id FROM ' . $t('fiscal_parties') . " WHERE id_users_customer IN ($ids)",
            );

            foreach (
                ['appointment_record' => 'appointment_records', 'appointment_foot_marker' => 'appointment_foot_markers']
                as $entity => $table
            ) {
                $conditions[] = $in(
                    $entity,
                    'SELECT r.id FROM ' .
                        $t($table) .
                        ' r JOIN ' .
                        $t('appointments') .
                        ' a ON a.id = r.id_appointments ' .
                        "WHERE a.id_users_customer IN ($ids) OR a.id_users_provider IN ($ids)",
                );
            }

            // Removed records only keep the ID of the person in their changes: every entry of an appointment that had
            // the person at some point (its other entries only hold what changed).
            $person_in_changes =
                'changes REGEXP ' .
                $db->escape('"id_users_(customer|provider)":\[([^\]]*,)?"?(' . implode('|', $people) . ')"?[,\]]');

            $conditions[] = "$log.$person_in_changes";
            $conditions[] =
                "($log.entity IN ('appointment', 'unavailability') AND $log.entity_id IN (SELECT entity_id FROM " .
                "(SELECT entity_id FROM $log WHERE entity IN ('appointment', 'unavailability') AND $person_in_changes) " .
                'AS person_appointments))';
        }

        // The settings and the kinds of records by the name the page shows ("antelació", "festiu").
        $folded = self::fold($search);

        foreach (array_column($db->select('name')->get('settings')->result_array(), 'name') as $setting) {
            if (str_contains(self::fold(self::label($setting, $setting)), $folded)) {
                $conditions[] = "($log.entity = 'setting' AND $log.changes LIKE " . $like('"' . $setting . '"') . ')';
            }
        }

        $entities = array_filter(
            $this->filter_options()['entities'],
            fn(string $entity) => str_contains(self::fold(self::label('audit_entity_' . $entity, $entity)), $folded),
        );

        if ($entities) {
            $conditions[] = "$log.entity IN (" . implode(',', array_map([$db, 'escape'], $entities)) . ')';
        }

        $services = array_map(
            'intval',
            array_column($db->select('id')->like('name', $search)->limit(50)->get('services')->result_array(), 'id'),
        );

        if ($services) {
            $ids = implode(',', $services);
            $conditions[] = "($log.entity = 'service' AND $log.entity_id IN ($ids))";
            $conditions[] =
                "$log.changes REGEXP " .
                $db->escape('"id_services":\[([^\]]*,)?"?(' . implode('|', $services) . ')"?[,\]]');
        }

        return $conditions;
    }

    /**
     * Get the last known value of every field of a record from all its entries of the log, as changes ([before,
     * after]), for a record that no longer exists.
     *
     * @param string $entity
     * @param int $id
     *
     * @return array
     */
    private function history(string $entity, int $id): array
    {
        $known = [];

        $entries = $this->CI->db
            ->select('changes')
            ->where('entity', $entity)
            ->where('entity_id', $id)
            ->order_by('id')
            ->get('audit_log')
            ->result_array();

        foreach ($entries as $entry) {
            foreach (json_decode((string) $entry['changes'], true) ?: [] as $field => $values) {
                if (is_array($values) && count($values) === 2) {
                    $value = $values[1] ?? $values[0];

                    if ($value !== null && $value !== '***') {
                        $known[$field] = [null, $value];
                    }
                }
            }
        }

        return $known;
    }

    /**
     * Get a text in lower case and without accents, to compare it as the database does.
     *
     * @param string $text
     *
     * @return string
     */
    public static function fold(string $text): string
    {
        return strtr(mb_strtolower($text), [
            'à' => 'a',
            'á' => 'a',
            'è' => 'e',
            'é' => 'e',
            'í' => 'i',
            'ï' => 'i',
            'ò' => 'o',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ç' => 'c',
            'ñ' => 'n',
            'l·l' => 'll',
        ]);
    }

    /**
     * Get the people (patients and staff) that a search matches: every word in their first or last names (accents do
     * not matter), or the text in their email, or its digits in their phone number. At most 300, the search is meant
     * to find someone.
     *
     * @param string $search
     *
     * @return int[]
     */
    private function matching_people(string $search): array
    {
        if (array_key_exists($search, $this->people)) {
            return $this->people[$search];
        }

        $words = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY);
        $digits = preg_replace('/\D/', '', $search);

        $query = $this->CI->db->select('id')->from('users')->group_start()->group_start();

        foreach ($words as $word) {
            $query->group_start()->like('first_name', $word)->or_like('last_name', $word)->group_end();
        }

        $query->group_end()->or_like('email', $search);

        if (strlen($digits) >= 6) {
            $query->or_like('phone_normalized', $digits);
        }

        $query->group_end();

        $people = array_column($query->limit(300)->get()->result_array(), 'id');

        // Removed people are found by the name the log kept of them.
        $removed = $this->CI->db
            ->distinct()
            ->select('entity_id')
            ->from('audit_log')
            ->where_in('entity', ['customer', 'provider', 'secretary', 'admin'])
            ->where('action', 'deleted');

        foreach ($words as $word) {
            $removed->like('changes', $word);
        }

        $people = array_merge($people, array_column($removed->limit(300)->get()->result_array(), 'entity_id'));

        return $this->people[$search] = array_values(array_unique(array_map('intval', array_filter($people))));
    }

    /**
     * Get an entry of the log in plain words.
     *
     * @param array $row Row of audit_log with user_name.
     *
     * @return array datetime, user, source, action, entity, entity_id, subject and changes (a list of label, before,
     * after, every value already in words, null when there was none).
     */
    public function describe(array $row): array
    {
        $changes = json_decode((string) $row['changes'], true);
        $changes = is_array($changes) ? $changes : [];

        $lines = [];

        foreach ($changes as $field => $values) {
            [$before, $after] =
                is_array($values) && array_is_list($values) && count($values) === 2 ? $values : [null, $values];

            $lines[] = [
                'label' => $row['entity'] === 'setting' ? self::label($field, $field) : $this->field_label($field),
                'before' => $this->value($field, $before, $row['entity']),
                'after' => $this->value($field, $after, $row['entity']),
            ];
        }

        return [
            'datetime' => $row['create_datetime'],
            'user' => $row['user_name'] ?: null,
            'source' => $row['source'],
            'action' => $row['action'],
            'entity' => $row['entity'],
            'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            'subject' => $this->subject(
                $row['entity'],
                $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
                $changes,
            ),
            'changes' => $lines,
        ];
    }

    /**
     * Get the translation of a key, or a fallback when there is none.
     *
     * @param string $key
     * @param string $fallback
     *
     * @return string
     */
    public static function label(string $key, string $fallback): string
    {
        $text = lang($key);

        return $text !== $key && $text !== '' ? $text : ucfirst(str_replace('_', ' ', $fallback));
    }

    /**
     * Get the label of a field.
     *
     * @param string $field
     *
     * @return string
     */
    private function field_label(string $field): string
    {
        return self::label(self::FIELD_LABELS[$field] ?? $field, $field);
    }

    /**
     * Get a value of a field in words.
     *
     * @param string $field
     * @param mixed $value
     * @param string $entity
     *
     * @return string|null Null when there was no value.
     */
    private function value(string $field, mixed $value, string $entity): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === '***') {
            return lang('audit_value_hidden');
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $value = (string) $value;

        if (in_array($field, self::USER_FIELDS, true)) {
            return $this->name('user', (int) $value);
        }

        if (in_array($field, self::SERVICE_FIELDS, true)) {
            return $this->name('service', (int) $value);
        }

        if (in_array($field, self::CATEGORY_FIELDS, true)) {
            return $this->name('category', (int) $value);
        }

        if ($field === 'id_podology_pathologies') {
            return $this->name('pathology', (int) $value);
        }

        // Lists of providers or treatments.
        if (in_array($field, ['providers', 'services'], true)) {
            $ids = json_decode($value, true);

            if (is_array($ids)) {
                $kind = $field === 'providers' ? 'user' : 'service';

                return implode(', ', array_map(fn($id) => $this->name($kind, (int) $id), $ids)) ?: null;
            }
        }

        if ($field === 'status') {
            return $entity === 'whatsapp_message'
                ? self::label('whatsapp_state_' . $value, $value)
                : $this->status_name($value);
        }

        if (
            preg_match('/^(is_|send_|notifications$|google_sync$|caldav_sync$)/', $field) &&
            in_array($value, ['0', '1'], true)
        ) {
            return lang($value === '1' ? 'yes' : 'no');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
            return date('d/m/Y H:i', strtotime($value));
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return date('d/m/Y', strtotime($value));
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return substr($value, 0, 5);
        }

        if (mb_strlen($value) > self::MAX_VALUE_LENGTH || in_array($value[0], ['{', '['], true)) {
            return lang('audit_value_changed');
        }

        return $value;
    }

    /**
     * Get the name of an appointment status.
     *
     * @param string $code
     *
     * @return string
     */
    private function status_name(string $code): string
    {
        $this->statuses ??= array_column(
            json_decode((string) setting('appointment_statuses'), true) ?: [],
            'name',
            'code',
        );

        return $this->statuses[$code] ?? $code;
    }

    /**
     * Get the name of a user, a service, a category or a pathology (removed ones show their ID).
     *
     * @param string $kind user, service, category or pathology.
     * @param int $id
     *
     * @return string
     */
    private function name(string $kind, int $id): string
    {
        if (!array_key_exists($id, $this->names[$kind])) {
            $row = match ($kind) {
                'user' => $this->CI->db
                    ->select("CONCAT_WS(' ', first_name, last_name) AS name", false)
                    ->get_where('users', ['id' => $id])
                    ->row_array(),
                'service' => $this->CI->db
                    ->select('name')
                    ->get_where('services', ['id' => $id])
                    ->row_array(),
                'pathology' => $this->CI->db
                    ->select('name')
                    ->get_where('podology_pathologies', ['id' => $id])
                    ->row_array(),
                default => $this->CI->db
                    ->select('name')
                    ->get_where('service_categories', ['id' => $id])
                    ->row_array(),
            };

            $name = trim((string) ($row['name'] ?? ''));

            // A removed person: the name the log kept of them.
            if ($name === '' && $kind === 'user') {
                $removed = json_decode(
                    (string) $this->CI->db
                        ->select('changes')
                        ->where_in('entity', ['customer', 'provider', 'secretary', 'admin'])
                        ->where('entity_id', $id)
                        ->where('action', 'deleted')
                        ->order_by('id', 'DESC')
                        ->get('audit_log', 1)
                        ->row('changes'),
                    true,
                );

                $name = trim(($removed['first_name'][0] ?? '') . ' ' . ($removed['last_name'][0] ?? ''));
            }

            $this->names[$kind][$id] = $name ?: '#' . $id;
        }

        return $this->names[$kind][$id];
    }

    /**
     * Get what a record of the log is, in words: the patient and the time of an appointment, the name of a person,
     * a treatment or a setting, the dates of a blocked period...
     *
     * @param string $entity
     * @param int|null $id
     * @param array $changes The changes of the entry, for the records that no longer exist.
     *
     * @return string|null
     */
    private function subject(string $entity, ?int $id, array $changes): ?string
    {
        // The value of a field, as it is now or as the entry recorded it.
        $recorded = static function (string $field) use (&$changes): ?string {
            $values = $changes[$field] ?? null;

            if (!is_array($values) || count($values) !== 2) {
                return null;
            }

            $value = $values[1] ?? $values[0];

            return $value === null || $value === '***' ? null : (string) $value;
        };

        $when = static fn(?string $datetime): string => $datetime ? date('d/m/Y H:i', strtotime($datetime)) : '';

        switch ($entity) {
            case 'appointment':
            case 'unavailability':
                $appointment = $id ? $this->CI->db->get_where('appointments', ['id' => $id])->row_array() : null;

                // Removed: what the other entries of the log kept of it fills what this one does not say.
                if (!$appointment && $id) {
                    $changes += $this->history($entity, $id);
                }

                $start = $appointment['start_datetime'] ?? $recorded('start_datetime');
                $customer = $appointment['id_users_customer'] ?? $recorded('id_users_customer');
                $provider = $appointment['id_users_provider'] ?? $recorded('id_users_provider');

                $who = $entity === 'appointment' && $customer ? $this->name('user', (int) $customer) : null;
                $who ??= $provider ? $this->name('user', (int) $provider) : null;

                return trim(($who ? $who . ', ' : '') . $when($start), ', ') ?: null;

            case 'customer':
            case 'provider':
            case 'secretary':
            case 'admin':
            case 'user':
                // Also a removed person, by the name the log kept (see name()).
                if ($id) {
                    return $this->name('user', $id);
                }

                return trim(($recorded('first_name') ?? '') . ' ' . ($recorded('last_name') ?? '')) ?: null;

            case 'service':
            case 'service_category':
            case 'podology_pathology':
            case 'holiday':
            case 'blocked_period':
            case 'webhook':
            case 'role':
            case 'fiscal_party':
                $table = [
                    'service' => 'services',
                    'service_category' => 'service_categories',
                    'podology_pathology' => 'podology_pathologies',
                    'holiday' => 'holidays',
                    'blocked_period' => 'blocked_periods',
                    'webhook' => 'webhooks',
                    'role' => 'roles',
                    'fiscal_party' => 'fiscal_parties',
                ][$entity];

                $name = $id ? $this->CI->db->get_where($table, ['id' => $id])->row('name') : null;

                return $name ?: $recorded('name');

            case 'working_plan_exception':
                $exception = $id
                    ? $this->CI->db->get_where('working_plan_exceptions', ['id' => $id])->row_array()
                    : null;

                $provider = $exception['id_users_provider'] ?? $recorded('id_users_provider');
                $date = $exception['start_date'] ?? $recorded('start_date');

                return trim(
                    ($provider ? $this->name('user', (int) $provider) . ', ' : '') .
                        ($date ? date('d/m/Y', strtotime($date)) : ''),
                    ', ',
                ) ?:
                    null;

            case 'setting':
                $name = array_key_first($changes);

                return $name ? self::label($name, $name) : null;

            case 'whatsapp_message':
                $reminder = $id ? $this->CI->db->get_where('whatsapp_reminders', ['id' => $id])->row_array() : null;

                if (!$reminder) {
                    return null;
                }

                if ((int) $reminder['is_test']) {
                    return lang('whatsapp_test');
                }

                return trim(
                    ($reminder['id_users_customer']
                        ? $this->name('user', (int) $reminder['id_users_customer']) . ', '
                        : '') . $when($reminder['appointment_datetime']),
                    ', ',
                );

            case 'patient_record':
                // Logged with the ID of the patient, not with the one of the record (see Patient_records_model).
                return $id ? $this->name('user', $id) : null;

            case 'appointment_record':
            case 'appointment_foot_marker':
                $table = $entity === 'appointment_record' ? 'appointment_records' : 'appointment_foot_markers';
                $appointment_id = $id ? $this->CI->db->get_where($table, ['id' => $id])->row('id_appointments') : null;

                return $appointment_id ? $this->subject('appointment', (int) $appointment_id, []) : null;

            case 'database':
                return $recorded('file') ??
                    (isset($changes['file']) && is_string($changes['file']) ? $changes['file'] : null);
        }

        return null;
    }
}
