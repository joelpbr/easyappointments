<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
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
    private const CUSTOMER_FIELDS = ['first_name', 'last_name', 'phone_number', 'address', 'city', 'zip_code', 'notes'];

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
        $this->load->model('foot_diagrams_model');
        $this->load->model('patient_records_model');
        $this->load->model('providers_model');

        $this->load->library('clinical_record_templates');
        $this->load->library('permissions');
        $this->load->library('webhooks_client');
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
     * Save the record of a patient, or create a new patient from an empty record (without customer_id).
     */
    public function save_patient(): void
    {
        try {
            method('post');

            $is_new = !(int) request('customer_id');
            $customer_id = $is_new ? 0 : $this->authorize_customer((int) request('customer_id'));

            if ($is_new) {
                $this->authorize_new_customer();
            }

            $submitted_customer = is_array(request('customer')) ? request('customer') : [];
            $record = Clinical_record_templates::clean_patient_record(
                is_array(request('record')) ? request('record') : [],
            );

            // The NIF/NIE is the document of the fiscal data, so both places always show the same one.
            $fiscal = array_merge($is_new ? [] : $this->fiscal_parties_model->get_patient_data($customer_id), [
                'document_type' => (string) request('document_type'),
                'document_number' => (string) request('document_number'),
            ]);

            $this->fiscal_parties_model->validate($fiscal);

            // The other patient fields are kept, so that the required ones are still checked. A new patient gets the
            // default language, as on the patients page.
            if ($is_new) {
                $customer = [
                    'language' => setting('default_language'),
                ];
            } else {
                $customer = $this->customers_model->find($customer_id);
                $customer =
                    ['id' => $customer_id] + array_intersect_key($customer, array_flip($this->customer_save_fields()));
            }

            foreach (self::CUSTOMER_FIELDS as $field) {
                if (array_key_exists($field, $submitted_customer)) {
                    $customer[$field] = trim((string) $submitted_customer[$field]);
                }
            }

            $this->db->trans_start();

            $customer_id = $this->customers_model->save($customer);
            $this->fiscal_parties_model->save_patient_data($customer_id, $fiscal);
            $this->patient_records_model->save_for_customer($customer_id, $record);

            $this->db->trans_complete();

            if (!$this->db->trans_status()) {
                throw new RuntimeException('Could not save the patient record.');
            }

            $this->webhooks_client->trigger(WEBHOOK_CUSTOMER_SAVE, $this->customers_model->find($customer_id));

            json_response(['success' => true, 'id' => $customer_id]);
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

            $has_feet = in_array($template, Clinical_record_templates::TEMPLATES_WITH_FEET, true);

            json_response([
                'appointment' => [
                    'id' => (int) $appointment['id'],
                    'start_datetime' => $appointment['start_datetime'],
                    'service_name' => $this->get_service_name($appointment),
                    'price' => $appointment['price'] !== null ? (float) $appointment['price'] : null,
                ],
                'template' => $template,
                'fields' => Clinical_record_templates::TEMPLATES[$template],
                'has_feet' => $has_feet,
                'record' => $this->appointment_records_model->find_by_appointment((int) $appointment['id']),
                // The four foot diagrams, the catalog of pathologies and the ones placed on this visit.
                'feet' => $has_feet
                    ? [
                        'diagrams' => $this->foot_diagrams_model->get_diagram_types(),
                        'pathologies' => $this->foot_diagrams_model->get_pathologies(),
                        // Its own drawing, or a copy of the one of the previous visit.
                        ...$this->foot_diagrams_model->get_drawing($appointment),
                    ]
                    : null,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Save the record of a visit, the price that it gives to the visit (silicone and surgery) and the pathologies
     * placed on its foot diagrams (markers, sent as JSON: the whole list of the visit).
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

            $has_feet = in_array($template, Clinical_record_templates::TEMPLATES_WITH_FEET, true);
            $markers = $has_feet && request('markers') !== null ? json_decode((string) request('markers'), true) : null;

            if ($markers !== null && !is_array($markers)) {
                throw new InvalidArgumentException('The provided markers are not valid.');
            }

            $this->db->trans_start();

            $this->appointment_records_model->save_for_appointment((int) $appointment['id'], $record);

            if ($markers !== null) {
                $this->foot_diagrams_model->sync_markers((int) $appointment['id'], $markers);
            }

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

            json_response([
                'success' => true,
                'price' => $price ?? $appointment['price'],
                'markers' => $has_feet ? $this->foot_diagrams_model->get_markers((int) $appointment['id']) : null,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /** Document choices and known data are available only through the visit's clinical authorization. */
    public function document_options(): void
    {
        try {
            method('get');
            $appointment = $this->authorize_appointment((int) request('appointment_id'));
            $this->load->library('visit_documents');
            $context = $this->document_context($appointment);
            json_response(
                $this->visit_documents->client_catalog() + [
                    'defaults' => $context['defaults'],
                    'current_professional' => $context['current_professional'],
                    // An object for the page, also when empty: the IDs are its keys.
                    'professionals' => (object) array_map(fn(array $signer) => $signer['name'], $context['signers']),
                    'provider_name' => $context['provider_name'],
                    'allergies' => $context['allergies'],
                ],
                200,
                ['Cache-Control: no-store, private'],
            );
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Generate a finished PDF and keep it in the history of the patient (logged by the model); neither the patient
     * nor the visit is changed. The page opens it with saved_document(). Asking again for a document that would
     * print exactly the same answers with the saved one.
     */
    public function generate_document(): void
    {
        try {
            method('post');
            $appointment = $this->authorize_appointment((int) request('appointment_id'));
            $this->load->library('visit_documents');
            $this->load->model('patient_documents_model');
            $template = $this->visit_documents->template((string) request('document_type'));
            $context = $this->document_context($appointment);
            $signer = $this->visit_documents->signer(
                $template,
                $context['signers'],
                (int) session('user_id'),
                (int) request('professional'),
                $context['provider_name'],
            );
            $submitted = request('fields', []);
            if (!is_array($submitted)) {
                throw new InvalidArgumentException(lang('visit_document_invalid_field'));
            }
            $values = $this->visit_documents->values(
                $template,
                $context['defaults'],
                $submitted,
                request('reviewed') === '1',
                config('language'),
            );
            $clinic = $this->clinic_fields();
            $fingerprint = $this->visit_documents->fingerprint($template, $values, $signer, $clinic);
            $document_id =
                $this->patient_documents_model->identical((int) $appointment['id'], $fingerprint) ??
                $this->patient_documents_model->store(
                    $appointment,
                    $template,
                    $signer,
                    trim($this->accounts->get_user_display_name((int) session('user_id'))),
                    $this->visit_documents->generate_pdf($template, $values, $signer, $clinic),
                    $fingerprint,
                );
            json_response(['document_id' => $document_id], 200, ['Cache-Control: no-store, private']);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function document_history(): void
    {
        try {
            method('get');
            $customer_id = $this->authorize_customer((int) request('customer_id'));
            $this->load->model('patient_documents_model');
            json_response(
                $this->patient_documents_model->history($customer_id, max(0, (int) request('before_id'))),
                200,
                ['Cache-Control: no-store, private'],
            );
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function saved_document(): void
    {
        try {
            method('get');
            if (!Clinical_record_templates::can_access(session('role_slug'))) {
                abort(403, 'Forbidden');
            }
            $this->load->model('patient_documents_model');
            $document = $this->patient_documents_model->metadata((int) request('document_id'));
            $this->authorize_customer((int) $document['id_users_customer']);
            $pdf = $this->patient_documents_model->pdf((int) $document['id']);
            audit_log('download', 'patient_record', (int) $document['id_users_customer'], [
                'saved_document' => [null, (int) $document['id']],
            ]);
            $this->output->set_content_type('application/pdf');
            response($pdf, 200, [
                'Content-Disposition: ' . (request('download') === '1' ? 'attachment' : 'inline') .
                    '; filename="document-' . (int) $document['id'] . '.pdf"',
                'Cache-Control: no-store, private',
                'X-Content-Type-Options: nosniff',
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function delete_document(): void
    {
        try {
            method('post');
            $customer_id = $this->authorize_customer((int) request('customer_id'));
            $this->load->model('patient_documents_model');
            $this->patient_documents_model->delete_document(
                (int) request('document_id'),
                $customer_id,
                (int) session('user_id'),
            );
            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /** What the documents of a visit start from: who can sign them and what the records already say. */
    private function document_context(array $appointment): array
    {
        $customer_id = (int) $appointment['id_users_customer'];
        $patient = $this->patient_records_model->find_by_customer($customer_id);
        $signers = Visit_documents::professionals($this->providers_model->get_document_identities());
        $user_id = (int) session('user_id');

        return [
            'signers' => $signers,
            'defaults' => $this->visit_documents->defaults(
                $this->customers_model->find($customer_id),
                $this->fiscal_parties_model->get_patient_data($customer_id),
                $patient,
                $appointment,
                $this->appointment_records_model->find_by_appointment((int) $appointment['id']),
                $this->get_service_name($appointment),
            ),
            'current_professional' => isset($signers[$user_id]) ? $user_id : null,
            'provider_name' => trim($this->accounts->get_user_display_name((int) $appointment['id_users_provider'])),
            'allergies' => (string) ($patient['allergies'] ?? ''),
        ];
    }

    /** The clinic_* fields of the document templates, from the general settings. */
    private function clinic_fields(): array
    {
        $fields = [];
        foreach (['name', 'address', 'phone', 'email', 'link'] as $name) {
            $fields['clinic_' . $name] = trim((string) setting('company_' . $name));
        }

        return $fields;
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
     * Make sure the user can create a patient from an empty record, as on the patients page.
     */
    private function authorize_new_customer(): void
    {
        if (!Clinical_record_templates::can_access(session('role_slug')) || cannot('add', PRIV_CUSTOMERS)) {
            abort(403, 'Forbidden');
        }

        if (!has_shared_clinic_access() && session('role_slug') !== DB_SLUG_ADMIN && setting('limit_customer_access')) {
            abort(403, 'Forbidden');
        }
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
                'appointments.id, appointments.start_datetime, appointments.status, appointments.price, appointments.notes, ' .
                    'appointments.service_name, services.name AS current_service_name, ' .
                    'services.record_template, providers.first_name AS provider_first_name, ' .
                    'providers.last_name AS provider_last_name, providers.short_name AS provider_short_name',
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
                'provider_name' => provider_display_name([
                    'first_name' => $appointment['provider_first_name'],
                    'last_name' => $appointment['provider_last_name'],
                    'short_name' => $appointment['provider_short_name'],
                ]),
                'has_record' => in_array((int) $appointment['id'], $recorded_ids, true),
                // The comment of the appointment (its details in the calendar), shown from an icon by its date.
                'notes' => trim((string) $appointment['notes']),
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
        return ['first_name', 'last_name', 'email', 'phone_number', 'address', 'city', 'zip_code', 'notes'];
    }
}
