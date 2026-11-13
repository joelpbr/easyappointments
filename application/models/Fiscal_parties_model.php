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
 * Fiscal parties model.
 *
 * The identification and fiscal address used to invoice a patient. Every field is optional: a patient never needs
 * them to book an appointment, and they can be added or corrected later. See the fiscal_parties migration for the
 * difference between the patient and other invoice recipients.
 *
 * @package Models
 */
class Fiscal_parties_model extends EA_Model
{
    public const RELATIONSHIP_PATIENT = 'patient';

    public const FIELDS = [
        'document_type',
        'document_number',
        'name',
        'address',
        'zip_code',
        'city',
        'province',
        'country',
    ];

    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'id_users_customer' => 'integer',
    ];

    /**
     * Get the patient's own fiscal data.
     *
     * @param int $customer_id Patient ID.
     *
     * @return array The FIELDS, empty when there is no data.
     */
    public function get_patient_data(int $customer_id): array
    {
        $party = $this->db
            ->get_where('fiscal_parties', [
                'id_users_customer' => $customer_id,
                'relationship' => self::RELATIONSHIP_PATIENT,
            ])
            ->row_array();

        return array_merge(
            array_fill_keys(self::FIELDS, ''),
            array_intersect_key($party ?: [], array_flip(self::FIELDS)),
        );
    }

    /**
     * Check the fiscal data, without requiring any of it.
     *
     * A document number needs its type, and Spanish documents (DNI, NIE, NIF) must have a valid format and check
     * character. Passports and foreign documents are accepted as typed. A Spanish zip code must have five digits.
     *
     * @param array $data The FIELDS.
     *
     * @throws InvalidArgumentException With a message for the user.
     */
    public function validate(array $data): void
    {
        $number = trim((string) ($data['document_number'] ?? ''));
        $type = (string) ($data['document_type'] ?? '');

        if ($number !== '') {
            if (!in_array($type, FISCAL_ID_TYPES, true)) {
                throw new InvalidArgumentException(lang('fiscal_document_type_missing'));
            }

            $result = validate_fiscal_id($type, $number);

            if ($result === FISCAL_ID_INVALID_FORMAT) {
                throw new InvalidArgumentException(
                    sprintf(lang('fiscal_document_invalid_format'), $number, lang('fiscal_document_' . $type)),
                );
            }

            if ($result === FISCAL_ID_INVALID_CHECK) {
                throw new InvalidArgumentException(sprintf(lang('fiscal_document_invalid_check'), $number));
            }
        }

        $zip_code = trim((string) ($data['zip_code'] ?? ''));

        if ($zip_code !== '' && $this->is_spain($data['country'] ?? '') && !preg_match('/^\d{5}$/', $zip_code)) {
            throw new InvalidArgumentException(sprintf(lang('fiscal_zip_code_invalid'), $zip_code));
        }
    }

    /**
     * Save the patient's own fiscal data, or remove it when every field is empty.
     *
     * @param int $customer_id Patient ID.
     * @param array $data The FIELDS.
     *
     * @throws InvalidArgumentException
     */
    public function save_patient_data(int $customer_id, array $data): void
    {
        $data = array_map(fn($value) => trim((string) $value), array_intersect_key($data, array_flip(self::FIELDS)));
        $data += array_fill_keys(self::FIELDS, '');

        $this->validate($data);

        if (in_array($data['document_type'], ['dni', 'nie', 'nif'], true)) {
            $data['document_number'] = normalize_fiscal_id($data['document_number']);
        }

        if ($data['document_number'] === '') {
            $data['document_type'] = '';
        }

        $where = ['id_users_customer' => $customer_id, 'relationship' => self::RELATIONSHIP_PATIENT];
        $current = $this->db->get_where('fiscal_parties', $where)->row_array();
        $is_empty = !array_filter($data, fn($value) => $value !== '');

        // Only which fields changed is logged, never their content.
        $changes = audit_changes($current ?: [], $data, self::FIELDS, self::FIELDS);

        if (!$current && $is_empty) {
            return;
        }

        if ($current && $is_empty) {
            $this->db->delete('fiscal_parties', ['id' => $current['id']]);
            audit_log('deleted', 'fiscal_party', (int) $current['id']);

            return;
        }

        $record = array_map(fn($value) => $value === '' ? null : $value, $data);
        $record['update_datetime'] = date('Y-m-d H:i:s');

        if ($current) {
            if ($changes) {
                $this->db->update('fiscal_parties', $record, ['id' => $current['id']]);
                audit_log('updated', 'fiscal_party', (int) $current['id'], $changes);
            }

            return;
        }

        $this->db->insert('fiscal_parties', $record + $where + ['create_datetime' => date('Y-m-d H:i:s')]);
        audit_log('created', 'fiscal_party', (int) $this->db->insert_id(), $changes);
    }

    /**
     * Check whether a country name means Spain (an empty country is taken as Spain).
     *
     * @param string $country
     *
     * @return bool
     */
    private function is_spain(string $country): bool
    {
        return in_array(mb_strtolower(trim($country)), ['', 'es', 'españa', 'espanya', 'spain'], true);
    }
}
