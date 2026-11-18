<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Consent of the patients, signed with a finger on the tablet at the reception or in the surgery.
 *
 * Whoever can edit the patient collects it. Each signature makes a PDF that is kept in the documents of the patient
 * (part of the clinical history, never removed) and a row of patient_consents with what proves it: the exact text and
 * version, the choices, who signed and in which capacity, the drawing, the moment, who collected it, the device and
 * the SHA-256 fingerprints of the content and of the PDF.
 */
class Patient_consents extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('customers_model');
        $this->load->model('fiscal_parties_model');
        $this->load->model('patient_consents_model');
        $this->load->model('patient_documents_model');
        $this->load->model('users_model');

        $this->load->library('accounts');
        $this->load->library('patient_consent');
        $this->load->library('permissions');
    }

    /** All signed consents, accessible only from administrator settings. */
    public function index(): void
    {
        method('get');
        session(['dest_url' => site_url('patient_consents')]);
        $user_id = session('user_id');
        if (session('role_slug') !== DB_SLUG_ADMIN || cannot('view', PRIV_SYSTEM_SETTINGS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }
            redirect('login');
            return;
        }

        $query = mb_substr(trim((string) request('q')), 0, 100);
        $result = $this->patient_consents_model->page($query, max(1, (int) request('page')));
        script_vars(['user_id' => $user_id, 'role_slug' => session('role_slug')]);
        html_vars([
            'page_title' => lang('consents_admin'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'query' => $query,
            ...$result,
        ]);
        $this->output->set_header('Cache-Control: no-store, private');
        $this->load->view('pages/patient_consents');
    }

    private function authorize(int $customer_id): int
    {
        $user_id = (int) session('user_id');

        if (
            !$user_id ||
            cannot('edit', PRIV_CUSTOMERS) ||
            !$customer_id ||
            !$this->permissions->has_customer_access($user_id, $customer_id)
        ) {
            abort(403, 'Forbidden');
        }

        return $customer_id;
    }

    private function clinic(): array
    {
        return [
            'name' => setting('company_name'),
            'address' => setting('company_address'),
            'email' => setting('company_email'),
            'phone' => setting('company_phone'),
            'dpo' => setting('company_dpo_contact'),
            'dpo_name' => setting('company_dpo_name', ''),
        ];
    }

    private function patient(int $customer_id): array
    {
        $customer = $this->customers_model->find($customer_id);
        $fiscal = $this->fiscal_parties_model->get_patient_data($customer_id);

        return [
            'name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
            'document' => (string) ($fiscal['document_number'] ?? ''),
        ];
    }

    /**
     * What the tablet shows: the text in each language, the patient and the current consent.
     */
    public function form(): void
    {
        try {
            method('get');

            $customer_id = $this->authorize((int) request('customer_id'));
            $clinic = $this->clinic();

            json_response(
                [
                    'patient' => $this->patient($customer_id),
                    'texts' => ['ca' => Patient_consent::text('ca', $clinic)],
                    'current' => $this->patient_consents_model->current($customer_id),
                    'dpo_missing' => !trim((string) $clinic['dpo']),
                ],
                200,
                ['Cache-Control: no-store, private'],
            );
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Keep a signature: the PDF in the documents of the patient and the consent with its evidence.
     */
    public function sign(): void
    {
        try {
            method('post');

            $customer_id = $this->authorize((int) request('customer_id'));
            $data = json_decode((string) request('consent'), true, 8, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new InvalidArgumentException(lang('consent_save_error'));
            }

            $clinic = $this->clinic();

            // The information to the patient must name the data protection officer (art. 13.1.b RGPD).
            if (!trim((string) $clinic['dpo'])) {
                throw new InvalidArgumentException(lang('consent_dpo_missing'));
            }

            if (($data['language'] ?? '') !== 'ca') {
                throw new InvalidArgumentException(lang('consent_language_reload'));
            }
            $text = Patient_consent::text('ca', $clinic);
            $choices = Patient_consent::choices((array) ($data['choices'] ?? []));
            $patient = $this->patient($customer_id);
            $signer_data = (array) ($data['signer'] ?? []);
            if (($signer_data['role'] ?? '') === 'patient') {
                $signer_data = ['role' => 'patient', 'name' => $patient['name'], 'document' => $patient['document']];
            }
            $signer = Patient_consent::signer($signer_data);
            $png = Patient_consent::signature_png((string) ($data['signature'] ?? ''));
            $signed_at = date('Y-m-d H:i:s');
            $fingerprint = Patient_consent::fingerprint($text, $choices, $signer, $patient, $signed_at, $png);
            $user_id = (int) session('user_id');
            $collected_by = $this->accounts->get_user_display_name($user_id);

            $pdf = $this->patient_consent->pdf(
                Patient_consent::document_html($text, $patient, $signer, $choices, [
                    'signed_at' =>
                        date('d/m/Y H:i:s', strtotime($signed_at)) . ' (' . date_default_timezone_get() . ')',
                    'collected_by' => $collected_by,
                    'fingerprint' => $fingerprint,
                    'signature_size' => array_slice(getimagesizefromstring($png) ?: [600, 200], 0, 2),
                ]),
                $png,
            );

            $titles = Patient_consent::config()['patient_consent_title'];

            $this->db->trans_begin();

            try {
                $document_id = $this->patient_documents_model->store_consent(
                    $customer_id,
                    ['catalan' => $titles['ca'], 'spanish' => $titles['es']],
                    $collected_by,
                    $pdf,
                    $fingerprint,
                );

                $consent_id = $this->patient_consents_model->store([
                    'id_users_customer' => $customer_id,
                    'id_patient_documents' => $document_id,
                    'signed_at' => $signed_at,
                    'version' => $text['version'],
                    'language' => $text['language'],
                    'choices' => $choices,
                    'signer_role' => $signer['role'],
                    'signer_name' => $signer['name'],
                    'signer_document' => $signer['document'],
                    'signer_relationship' => $signer['relationship'],
                    'fingerprint' => $fingerprint,
                    'pdf_sha256' => hash('sha256', $pdf),
                    'signature' => $png,
                    'collected_by' => $user_id,
                    'collected_by_name' => $collected_by,
                    'ip' => mb_substr((string) $this->input->ip_address(), 0, 45),
                    'user_agent' => mb_substr((string) $this->input->user_agent(), 0, 255),
                ]);

                audit_log('signed', 'patient_consent', $consent_id, [
                    'id_users_customer' => [null, $customer_id],
                    'version' => [null, $text['version']],
                    'choices' => [null, $choices],
                    'signer_role' => [null, $signer['role']],
                    'fingerprint' => [null, $fingerprint],
                ]);

                if (!$this->db->trans_status()) {
                    throw new RuntimeException(lang('consent_save_error'));
                }

                $this->db->trans_commit();
            } catch (Throwable $e) {
                $this->db->trans_rollback();
                throw $e;
            }

            json_response(['current' => $this->patient_consents_model->current($customer_id)]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * The signed PDF of a consent.
     */
    public function pdf(): void
    {
        try {
            method('get');

            $document = $this->patient_documents_model->metadata((int) request('document_id'));

            if ($document['document_type'] !== Patient_documents_model::SIGNED_CONSENT) {
                abort(404, 'Not found');
            }

            $this->authorize((int) $document['id_users_customer']);

            // Like the other saved documents: logged with the patient (see Clinical_records::saved_document()).
            audit_log('download', 'patient_record', (int) $document['id_users_customer'], [
                'saved_document' => [null, (int) $document['id']],
            ]);

            $this->output->set_content_type('application/pdf');

            response($this->patient_documents_model->pdf((int) $document['id']), 200, [
                'Content-Disposition: ' . (request('download') === '1' ? 'attachment' : 'inline') .
                    '; filename="consentiment-' . (int) $document['id'] . '.pdf"',
                'Cache-Control: no-store, private',
                'X-Content-Type-Options: nosniff',
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
