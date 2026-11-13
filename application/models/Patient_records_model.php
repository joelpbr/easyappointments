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
 * Patient records model.
 *
 * The clinical record of each patient (see the Clinical_record_templates library). It holds health data, so the audit log
 * only records which fields changed, never their content.
 *
 * @package Models
 */
class Patient_records_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'id_users_customer' => 'integer',
    ];

    /**
     * Get the record of a patient.
     *
     * @param int $customer_id Patient ID.
     *
     * @return array Every Clinical_record_templates::PATIENT_FIELDS key, null when there is no data.
     */
    public function find_by_customer(int $customer_id): array
    {
        $record = $this->db->get_where('patient_records', ['id_users_customer' => $customer_id])->row_array();

        return array_merge(
            array_fill_keys(Clinical_record_templates::PATIENT_FIELDS, null),
            array_intersect_key($record ?: [], array_flip(Clinical_record_templates::PATIENT_FIELDS)),
        );
    }

    /**
     * Save the record of a patient.
     *
     * @param int $customer_id Patient ID.
     * @param array $record Record from Clinical_record_templates::clean_patient_record().
     */
    public function save_for_customer(int $customer_id, array $record): void
    {
        $record = array_intersect_key($record, array_flip(Clinical_record_templates::PATIENT_FIELDS));
        $current = $this->db->get_where('patient_records', ['id_users_customer' => $customer_id])->row_array();
        $fields = Clinical_record_templates::PATIENT_FIELDS;
        $changes = audit_changes($current ?: [], $record, $fields, $fields);

        if (!$changes) {
            return;
        }

        $record['update_datetime'] = date('Y-m-d H:i:s');

        if ($current) {
            $this->db->update('patient_records', $record, ['id' => $current['id']]);
            audit_log('updated', 'patient_record', $customer_id, $changes);

            return;
        }

        $this->db->insert(
            'patient_records',
            $record + ['id_users_customer' => $customer_id, 'create_datetime' => date('Y-m-d H:i:s')],
        );
        audit_log('created', 'patient_record', $customer_id, $changes);
    }
}
