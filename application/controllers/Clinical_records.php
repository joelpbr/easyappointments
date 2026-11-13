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
 * Clinical records controller.
 *
 * Reads and saves the record of a patient and of each of their visits (see the Clinical_record_templates library). Only admins
 * and providers can use it, as it holds health data, and every time a record is opened is written to the audit log.
 *
 * @package Controllers
 */
class Clinical_records extends EA_Controller
{
    /**
     * Patient fields that the record shows and edits; the rest of the patient is edited on the patients page.
     */
    private const CUSTOMER_FIELDS = ['first_name', 'last_name', 'phone_number', 'address'];

    /**
     * Clinical records constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('appointment_records_model');
        $this->load->model('customers_model');
        $this->load->model('fiscal_parties_model');
        $this->load->model('patient_records_model');

        $this->load->library('clinical_record_templates');
        $this->load->library('permissions');
    }

    /**
     * Get the record of a patient, with their visits.
     */
    public function patient(): void
    {
        try {
            method('get');

            $customer_id = $this->authorize_customer((int) request('customer_id'));

            $customer = $this->customers_model->find($customer_id);
            $fiscal = $this->fiscal_parties_model->get_patient_data($customer_id);

            audit_log('viewed', 'patient_record', $customer_id);

            json_response([
                'customer' =>
                    ['id' => $customer_id] + array_intersect_key($customer, array_flip(self::CUSTOMER_FIELDS)),
                'document_type' => $fiscal['document_type'],
                'document_number' => $fiscal['document_number'],
                'record' => $this->patient_records_model->find_by_customer($customer_id),
                'appointments' => $this->get_patient_appointments($customer_id),
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Save the record of a patient.
     */
    public function save_patient(): void
    {
        try {
            method('post');

            $customer_id = $this->authorize_customer((int) request('customer_id'));

            $submitted_customer = is_array(request('customer')) ? request('customer') : [];
            $record = Clinical_record_templates::clean_patient_record(
                is_array(request('record')) ? request('record') : [],
            );

            // The NIF/NIE is the document of the fiscal data, so both places always show the same one.
            $fiscal = array_merge($this->fiscal_parties_model->get_patient_data($customer_id), [
                'document_type' => (string) request('document_type'),
                'document_number' => (string) request('document_number'),
            ]);

            $this->fiscal_parties_model->validate($fiscal);

            // The other patient fields are kept, so that the required ones are still checked.
            $customer = $this->customers_model->find($customer_id);
            $customer =
                ['id' => $customer_id] + array_intersect_key($customer, array_flip($this->customer_save_fields()));

            foreach (self::CUSTOMER_FIELDS as $field) {
                if (array_key_exists($field, $submitted_customer)) {
                    $customer[$field] = trim((string) $submitted_customer[$field]);
                }
            }

            $this->db->trans_start();

            $this->customers_model->save($customer);
            $this->fiscal_parties_model->save_patient_data($customer_id, $fiscal);
            $this->patient_records_model->save_for_customer($customer_id, $record);

            $this->db->trans_complete();

            if (!$this->db->trans_status()) {
                throw new RuntimeException('Could not save the patient record.');
            }

            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Get the record of a visit.
     */
    public function appointment(): void
    {
        try {
            method('get');

            $appointment = $this->authorize_appointment((int) request('appointment_id'));
            $template = $this->get_template($appointment);

            audit_log('viewed', 'appointment_record', (int) $appointment['id']);

            json_response([
                'appointment' => [
                    'id' => (int) $appointment['id'],
                    'start_datetime' => $appointment['start_datetime'],
                    'service_name' => $this->get_service_name($appointment),
                    'price' => $appointment['price'] !== null ? (float) $appointment['price'] : null,
                ],
                'template' => $template,
                'fields' => Clinical_record_templates::TEMPLATES[$template],
                'has_feet' => in_array($template, Clinical_record_templates::TEMPLATES_WITH_FEET, true),
                'record' => $this->appointment_records_model->find_by_appointment((int) $appointment['id']),
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Save the record of a visit, and the price that it gives to the visit (silicone and surgery).
     */
    public function save_appointment(): void
    {
        try {
            method('post');

            $appointment = $this->authorize_appointment((int) request('appointment_id'));
            $template = $this->get_template($appointment);

            $record = Clinical_record_templates::clean_appointment_record(
                $template,
                is_array(request('record')) ? request('record') : [],
            );
            $price = Clinical_record_templates::appointment_price($template, $record, request('surgery_price'));

            $this->db->trans_start();

            $this->appointment_records_model->save_for_appointment((int) $appointment['id'], $record);

            if ($price !== null && (float) $appointment['price'] !== $price) {
                $this->db->update(
                    'appointments',
                    ['price' => $price, 'update_datetime' => date('Y-m-d H:i:s')],
                    ['id' => $appointment['id']],
                );

                audit_log('updated', 'appointment', (int) $appointment['id'], [
                    'price' => [$appointment['price'], $price],
                ]);
            }

            $this->db->trans_complete();

            if (!$this->db->trans_status()) {
                throw new RuntimeException('Could not save the appointment record.');
            }

            json_response(['success' => true, 'price' => $price ?? $appointment['price']]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Make sure the user can see the clinical record of a patient.
     *
     * @param int $customer_id Patient ID.
     *
     * @return int The patient ID.
     */
    private function authorize_customer(int $customer_id): int
    {
        if (!Clinical_record_templates::can_access(session('role_slug'))) {
            abort(403, 'Forbidden');
        }

        if ($customer_id <= 0) {
            throw new InvalidArgumentException('Invalid customer ID provided.');
        }

        if (!$this->permissions->has_customer_access((int) session('user_id'), $customer_id)) {
            abort(403, 'Forbidden');
        }

        return $customer_id;
    }

    /**
     * Make sure the user can see the clinical record of a visit.
     *
     * @param int $appointment_id Appointment ID.
     *
     * @return array The appointment.
     */
    private function authorize_appointment(int $appointment_id): array
    {
        if ($appointment_id <= 0) {
            throw new InvalidArgumentException('Invalid appointment ID provided.');
        }

        $appointment = $this->db
            ->get_where('appointments', ['id' => $appointment_id, 'is_unavailability' => false])
            ->row_array();

        if (!$appointment || empty($appointment['id_users_customer'])) {
            throw new InvalidArgumentException('The provided appointment ID does not exist: ' . $appointment_id);
        }

        $this->authorize_customer((int) $appointment['id_users_customer']);

        return $appointment;
    }

    /**
     * Get the record template of a visit, from its treatment.
     *
     * @param array $appointment
     *
     * @return string
     */
    private function get_template(array $appointment): string
    {
        $service = $this->db
            ->select('record_template')
            ->get_where('services', ['id' => (int) $appointment['id_services']])
            ->row_array();

        return Clinical_record_templates::template($service['record_template'] ?? null);
    }

    /**
     * Get the treatment name of a visit, as it was at the time of the visit.
     *
     * @param array $appointment
     *
     * @return string
     */
    private function get_service_name(array $appointment): string
    {
        if (!empty($appointment['service_name'])) {
            return $appointment['service_name'];
        }

        $service = $this->db
            ->select('name')
            ->get_where('services', ['id' => (int) $appointment['id_services']])
            ->row_array();

        return $service['name'] ?? '';
    }

    /**
     * Get the visits of a patient, the latest first.
     *
     * @param int $customer_id Patient ID.
     *
     * @return array
     */
    private function get_patient_appointments(int $customer_id): array
    {
        $appointments = $this->db
            ->select(
                'appointments.id, appointments.start_datetime, appointments.status, appointments.price, ' .
                    'appointments.service_name, services.name AS current_service_name, ' .
                    'services.record_template, providers.first_name AS provider_first_name, ' .
                    'providers.last_name AS provider_last_name',
            )
            ->from('appointments')
            ->join('services', 'services.id = appointments.id_services', 'left')
            ->join('users AS providers', 'providers.id = appointments.id_users_provider', 'left')
            ->where('appointments.id_users_customer', $customer_id)
            ->where('appointments.is_unavailability', false)
            ->order_by('appointments.start_datetime', 'DESC')
            ->get()
            ->result_array();

        $recorded_ids = $this->appointment_records_model->get_recorded_ids(array_column($appointments, 'id'));

        return array_map(
            fn(array $appointment): array => [
                'id' => (int) $appointment['id'],
                'start_datetime' => $appointment['start_datetime'],
                'status' => $appointment['status'],
                'price' => $appointment['price'] !== null ? (float) $appointment['price'] : null,
                // The name at the time of the visit, or the current one for visits saved before it was kept.
                'service_name' => (string) ($appointment['service_name'] ?: $appointment['current_service_name']),
                'template' => Clinical_record_templates::template($appointment['record_template']),
                'provider_name' => trim($appointment['provider_first_name'] . ' ' . $appointment['provider_last_name']),
                'has_record' => in_array((int) $appointment['id'], $recorded_ids, true),
            ],
            $appointments,
        );
    }

    /**
     * Get the patient fields that are sent back when the record saves the patient.
     *
     * @return array
     */
    private function customer_save_fields(): array
    {
        return ['first_name', 'last_name', 'email', 'phone_number', 'address', 'city', 'zip_code'];
    }
}
