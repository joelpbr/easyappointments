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

if (!function_exists('audit_changes')) {
    /**
     * Get the changes between two versions of a record.
     *
     * Only the listed fields are compared. Redacted fields only record that they changed, never their content, so
     * free text that may hold sensitive data (e.g. notes) does not end up in the log.
     *
     * @param array $before Previous record (empty for a new record).
     * @param array $after New record.
     * @param array $fields Fields to compare.
     * @param array $redacted Fields whose values must not be stored.
     *
     * @return array Map of field => [previous value, new value].
     */
    function audit_changes(array $before, array $after, array $fields, array $redacted = []): array
    {
        $changes = [];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $after)) {
                continue;
            }

            // Booleans are compared and stored as 0/1, as the database returns them ("0" and false are the same).
            $previous = is_bool($before[$field] ?? null) ? (int) $before[$field] : $before[$field] ?? null;
            $current = is_bool($after[$field]) ? (int) $after[$field] : $after[$field];

            if ((string) $previous === (string) $current) {
                continue;
            }

            $changes[$field] = in_array($field, $redacted, true) ? ['***', '***'] : [$previous, $current];
        }

        return $changes;
    }
}

if (!function_exists('audit_diff')) {
    /**
     * Get the changes between two versions of a whole record: every field of the new one (of the old one for a
     * deletion), except the technical ones (ids of the row, timestamps, passwords and tokens) and those given.
     * Lists and objects are compared as JSON.
     *
     * @param array $before Previous record (empty for a new record).
     * @param array $after New record (empty for a deleted record).
     * @param array $ignored More fields to leave out.
     * @param array $redacted Fields whose values must not be stored (see audit_changes()).
     *
     * @return array Map of field => [previous value, new value].
     */
    function audit_diff(array $before, array $after, array $ignored = [], array $redacted = []): array
    {
        $ignored = array_merge(
            [
                'id',
                'create_datetime',
                'update_datetime',
                'book_datetime',
                'hash',
                'password',
                'salt',
                'password_reset_token',
                'password_reset_expires',
            ],
            $ignored,
        );

        $deleted = !$after && $before;
        $fields = array_values(array_diff(array_keys($deleted ? $before : $after), $ignored));

        if ($deleted) {
            $after = array_fill_keys($fields, null);
        }

        $flatten = static fn(array $record): array => array_map(
            static fn($value) => is_array($value) || is_object($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : $value,
            $record,
        );

        return audit_changes($flatten($before), $flatten($after), $fields, $redacted);
    }
}

if (!function_exists('audit_record')) {
    /**
     * Record in the audit log the creation, change or deletion of a whole record (see audit_diff()): created when
     * there was nothing before, deleted when there is nothing after, updated otherwise and only when something
     * changed.
     *
     * @param string $entity E.g. "customer", "setting".
     * @param int|null $entity_id Record ID.
     * @param array $before Previous record (empty for a new record).
     * @param array $after New record (empty for a deleted record).
     * @param array $ignored More fields to leave out.
     * @param array $redacted Fields whose values must not be stored.
     * @param string|null $action Another action than created, updated or deleted (e.g. "cancelled").
     */
    function audit_record(
        string $entity,
        ?int $entity_id,
        array $before,
        array $after,
        array $ignored = [],
        array $redacted = [],
        ?string $action = null,
    ): void {
        $changes = audit_diff($before, $after, $ignored, $redacted);
        $action ??= !$before ? 'created' : (!$after ? 'deleted' : 'updated');

        if ($action === 'updated' && !$changes) {
            return;
        }

        audit_log($action, $entity, $entity_id, $changes);
    }
}

if (!function_exists('audit_user')) {
    /**
     * Record in the audit log the creation, change or deletion of a user of the staff, from two snapshots taken with
     * audit_user_snapshot() (empty before a creation and after a deletion).
     *
     * @param string $entity "secretary", "admin", "provider" or "user" (the own account).
     * @param int $user_id
     * @param array $before
     * @param array $after
     */
    function audit_user(string $entity, int $user_id, array $before, array $after): void
    {
        audit_record($entity, $user_id, $before, $after, AUDIT_USER_IGNORED, AUDIT_USER_REDACTED);
    }
}

if (!function_exists('audit_user_snapshot')) {
    /**
     * Get what the audit log compares of a user of the staff: the user row, their settings and the providers of a
     * secretary. Secrets are never part of it: the password only as a fingerprint of its hash, so that the log says
     * that it changed (see AUDIT_USER_REDACTED), and the tokens of the calendar syncs not at all.
     *
     * @param int $user_id
     *
     * @return array Empty when the user does not exist.
     */
    function audit_user_snapshot(int $user_id): array
    {
        $CI = &get_instance();

        $user = $CI->db->get_where('users', ['id' => $user_id])->row_array();

        if (!$user) {
            return [];
        }

        $settings = $CI->db->get_where('user_settings', ['id_users' => $user_id])->row_array() ?: [];

        $snapshot = $user + array_diff_key($settings, array_flip(AUDIT_USER_SECRETS));

        $snapshot['password_fingerprint'] = !empty($settings['password']) ? md5($settings['password']) : null;

        if ($CI->db->table_exists('secretaries_providers')) {
            $providers = array_map(
                'intval',
                array_column(
                    $CI->db
                        ->select('id_users_provider')
                        ->order_by('id_users_provider')
                        ->get_where('secretaries_providers', ['id_users_secretary' => $user_id])
                        ->result_array(),
                    'id_users_provider',
                ),
            );

            if ($providers) {
                $snapshot['providers'] = $providers;
            }
        }

        // The treatments of a provider.
        if ($CI->db->table_exists('services_providers')) {
            $services = array_map(
                'intval',
                array_column(
                    $CI->db
                        ->select('id_services')
                        ->order_by('id_services')
                        ->get_where('services_providers', ['id_users' => $user_id])
                        ->result_array(),
                    'id_services',
                ),
            );

            if ($services) {
                $snapshot['services'] = $services;
            }
        }

        unset($snapshot['id_users'], $snapshot['id_roles']);

        return $snapshot;
    }
}

if (!defined('AUDIT_USER_SECRETS')) {
    /**
     * Settings of a user that never go to the audit log, not even as "changed".
     */
    define('AUDIT_USER_SECRETS', [
        'password',
        'salt',
        'password_reset_token',
        'password_reset_expires',
        'google_token',
        'caldav_password',
    ]);

    /**
     * Fields of a user snapshot whose content is not stored: the log only says that they changed.
     */
    define('AUDIT_USER_REDACTED', ['password_fingerprint', 'notes']);

    /**
     * Fields of a user snapshot left out of the log: worked out from others.
     */
    define('AUDIT_USER_IGNORED', ['phone_normalized', 'no_show_count']);
}

if (!function_exists('audit_source')) {
    /**
     * Get where the current request comes from.
     *
     * @return string "console", "api", "backend" (logged in user) or "public" (booking page).
     */
    function audit_source(): string
    {
        if (is_cli()) {
            return 'console';
        }

        $CI = &get_instance();

        if (isset($CI->uri) && $CI->uri->segment(1) === 'api') {
            return 'api';
        }

        return session('user_id') ? 'backend' : 'public';
    }
}

if (!function_exists('audit_log')) {
    /**
     * Record an operation in the audit log.
     *
     * A failure to write the log is logged as an error but never breaks the operation itself.
     *
     * @param string $action E.g. "created", "updated", "deleted", "deactivated".
     * @param string $entity E.g. "appointment", "service", "provider".
     * @param int|null $entity_id Record ID.
     * @param array $changes Changes from audit_changes().
     */
    function audit_log(string $action, string $entity, ?int $entity_id, array $changes = []): void
    {
        try {
            $CI = &get_instance();

            // The table does not exist yet while the migrations are pending.
            if (!$CI->db->table_exists('audit_log')) {
                return;
            }

            $CI->db->insert('audit_log', [
                'create_datetime' => date('Y-m-d H:i:s'),
                'id_users' => session('user_id') ?: $CI->api->user_id ?? null,
                'source' => audit_source(),
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entity_id,
                'changes' => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Could not write the audit log: ' . $e->getMessage());
        }
    }
}
