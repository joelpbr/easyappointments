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
 * Appointment statuses with a meaning.
 *
 * Replaces the free text "appointment_status_options" setting by the "appointment_statuses" setting, where every
 * status has a stable code, a name and whether it frees the slot, and converts the status of the existing
 * appointments to those codes. Labels that do not match a system status are kept as custom statuses.
 */
class Migration_Add_appointment_status_codes extends EA_Migration
{
    /**
     * System statuses as they were defined when this migration was written.
     */
    private const SYSTEM_STATUSES = [
        'scheduled' => ['name' => 'Programada', 'frees_slot' => false],
        'confirmed' => ['name' => 'Confirmada', 'frees_slot' => false],
        'completed' => ['name' => 'Realizada', 'frees_slot' => false],
        'cancelled' => ['name' => 'Cancelada', 'frees_slot' => true],
        'no_show' => ['name' => 'No presentado', 'frees_slot' => false],
    ];

    /**
     * Previous labels (lowercase) and the code they become.
     */
    private const LEGACY_LABELS = [
        'booked' => 'scheduled',
        'rescheduled' => 'scheduled',
        'draft' => 'scheduled',
        'programada' => 'scheduled',
        'confirmed' => 'confirmed',
        'confirmada' => 'confirmed',
        'completed' => 'completed',
        'realizada' => 'completed',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
        'cancelada' => 'cancelled',
        'no show' => 'no_show',
        'no presentado' => 'no_show',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $custom = [];

        $code_of = function (?string $label) use (&$custom): string {
            $label = trim((string) $label);

            if ($label === '') {
                return 'scheduled';
            }

            $key = mb_strtolower($label);

            if (isset(self::LEGACY_LABELS[$key])) {
                return self::LEGACY_LABELS[$key];
            }

            $code = 'custom_' . substr(sha1($key), 0, 8);

            $custom[$code] ??= ['code' => $code, 'name' => mb_substr($label, 0, 64), 'frees_slot' => false];

            return $code;
        };

        $setting = $this->db->get_where('settings', ['name' => 'appointment_status_options'])->row_array();

        foreach (json_decode($setting['value'] ?? '[]', true) ?: [] as $label) {
            if (is_string($label)) {
                $code_of($label);
            }
        }

        $labels = $this->db
            ->distinct()
            ->select('status')
            ->get_where('appointments', ['is_unavailability' => false])
            ->result_array();

        foreach (array_column($labels, 'status') as $label) {
            $this->db
                ->where('is_unavailability', false)
                ->where($label === null ? 'status IS NULL' : ['status' => $label])
                ->update('appointments', ['status' => $code_of($label)]);
        }

        $statuses = [];

        foreach (self::SYSTEM_STATUSES as $code => $status) {
            $statuses[] = ['code' => $code, 'name' => $status['name'], 'frees_slot' => $status['frees_slot']];
        }

        $value = json_encode(array_merge($statuses, array_values($custom)), JSON_UNESCAPED_UNICODE);

        if ($this->db->get_where('settings', ['name' => 'appointment_statuses'])->num_rows()) {
            $this->db->update('settings', ['value' => $value], ['name' => 'appointment_statuses']);
        } else {
            $this->db->insert('settings', ['name' => 'appointment_statuses', 'value' => $value]);
        }

        $this->db->delete('settings', ['name' => 'appointment_status_options']);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $setting = $this->db->get_where('settings', ['name' => 'appointment_statuses'])->row_array();

        $statuses = json_decode($setting['value'] ?? '[]', true) ?: [];

        foreach ($statuses as $status) {
            $this->db
                ->where('is_unavailability', false)
                ->where('status', $status['code'])
                ->update('appointments', ['status' => $status['name']]);
        }

        if (!$this->db->get_where('settings', ['name' => 'appointment_status_options'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'appointment_status_options',
                'value' => json_encode(array_column($statuses, 'name'), JSON_UNESCAPED_UNICODE),
            ]);
        }

        $this->db->delete('settings', ['name' => 'appointment_statuses']);
    }
}
