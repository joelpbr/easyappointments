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
