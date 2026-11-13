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
 * Appointment records model.
 *
 * What the provider writes down in each visit (see the Clinical_record_templates library). It holds health data, so the audit
 * log only records which fields changed, never their content.
 *
 * @package Models
 */
class Appointment_records_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'id_appointments' => 'integer',
    ];

    /**
     * Get the record of an appointment.
     *
     * @param int $appointment_id Appointment ID.
     *
     * @return array Every Clinical_record_templates::APPOINTMENT_FIELDS key, null when there is no data.
     */
    public function find_by_appointment(int $appointment_id): array
    {
        $record = $this->db->get_where('appointment_records', ['id_appointments' => $appointment_id])->row_array();

        return array_merge(
            array_fill_keys(Clinical_record_templates::APPOINTMENT_FIELDS, null),
            array_intersect_key($record ?: [], array_flip(Clinical_record_templates::APPOINTMENT_FIELDS)),
        );
    }

    /**
     * Get which of some appointments have something written down.
     *
     * @param array $appointment_ids Appointment IDs.
     *
     * @return array Appointment IDs with a record.
     */
    public function get_recorded_ids(array $appointment_ids): array
    {
        if (!$appointment_ids) {
            return [];
        }

        $rows = $this->db
            ->select('id_appointments')
            ->from('appointment_records')
            ->where_in('id_appointments', array_map('intval', $appointment_ids))
            ->group_start()
            ->where('reason IS NOT NULL')
            ->or_where('diagnosis IS NOT NULL')
            ->or_where('treatment IS NOT NULL')
            ->or_where('observations IS NOT NULL')
            ->or_where('orthosis IS NOT NULL')
            ->group_end()
            ->get()
            ->result_array();

        return array_map('intval', array_column($rows, 'id_appointments'));
    }

    /**
     * Save the record of an appointment.
     *
     * @param int $appointment_id Appointment ID.
     * @param array $record Record from Clinical_record_templates::clean_appointment_record().
     */
    public function save_for_appointment(int $appointment_id, array $record): void
    {
        $record = array_intersect_key($record, array_flip(Clinical_record_templates::APPOINTMENT_FIELDS));
        $current = $this->db->get_where('appointment_records', ['id_appointments' => $appointment_id])->row_array();
        $fields = Clinical_record_templates::APPOINTMENT_FIELDS;
        $changes = audit_changes($current ?: [], $record, $fields, $fields);

        if (!$changes) {
            return;
        }

        $record['update_datetime'] = date('Y-m-d H:i:s');

        if ($current) {
            $this->db->update('appointment_records', $record, ['id' => $current['id']]);
            audit_log('updated', 'appointment_record', $appointment_id, $changes);

            return;
        }

        $this->db->insert(
            'appointment_records',
            $record + ['id_appointments' => $appointment_id, 'create_datetime' => date('Y-m-d H:i:s')],
        );
        audit_log('created', 'appointment_record', $appointment_id, $changes);
    }
}
