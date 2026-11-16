<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Finished PDFs of the patients, with what is needed to list them without their content.
 *
 * The title is kept in every language of the document pack (titles, JSON), so that the history reads in the language
 * of whoever opens it and survives a later change of the pack.
 */
class Patient_documents_model extends EA_Model
{
    private const PAGE = 25;

    private const METADATA = 'id, id_users_customer, id_appointments, created_at, visit_datetime, document_type, titles, professional_name, created_by_name';

    /**
     * @param string $fingerprint Visit_documents::fingerprint() of what was printed, to find it again (see identical()).
     */
    public function store(
        array $appointment,
        array $template,
        array $signer,
        string $creator,
        string $pdf,
        ?string $fingerprint = null,
    ): int {
        if (!str_starts_with($pdf, '%PDF-') || strlen($pdf) > 16777215) {
            throw new RuntimeException(lang('visit_document_save_error'));
        }
        $saved = $this->db->insert('patient_documents', [
            'id_users_customer' => (int) $appointment['id_users_customer'],
            'id_appointments' => (int) $appointment['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'visit_datetime' => $appointment['start_datetime'],
            'document_type' => $template['id'],
            'titles' => json_encode($template['label'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'professional_name' => $signer['name'],
            'created_by_name' => $creator,
            'fingerprint' => $fingerprint,
            'pdf' => $pdf,
        ]);
        if (!$saved) {
            throw new RuntimeException(lang('visit_document_save_error'));
        }
        $id = (int) $this->db->insert_id();
        audit_log(
            'created',
            'patient_document',
            $id,
            array_map(fn($value) => [null, $value], $this->audit_fields($this->metadata($id))),
        );
        return $id;
    }

    /** The saved document of the visit that prints exactly the same, if there is one: generating it again adds nothing. */
    public function identical(int $appointment_id, string $fingerprint): ?int
    {
        $id = $this->db
            ->select('id')
            ->order_by('id', 'DESC')
            ->get_where('patient_documents', ['id_appointments' => $appointment_id, 'fingerprint' => $fingerprint], 1)
            ->row('id');

        return $id ? (int) $id : null;
    }

    public function history(int $customer_id, int $before_id = 0): array
    {
        $this->db->select(self::METADATA)->where('id_users_customer', $customer_id);
        if ($before_id > 0) {
            $this->db->where('id <', $before_id);
        }
        $rows = $this->db
            ->order_by('id', 'DESC')
            ->get('patient_documents', self::PAGE + 1)
            ->result_array();

        return [
            'documents' => array_map([$this, 'decode'], array_slice($rows, 0, self::PAGE)),
            'has_more' => count($rows) > self::PAGE,
        ];
    }

    public function metadata(int $id): array
    {
        $row = $this->db
            ->select(self::METADATA)
            ->get_where('patient_documents', ['id' => $id])
            ->row_array();
        if (!$row) {
            throw new InvalidArgumentException(lang('visit_document_not_found'));
        }
        return $this->decode($row);
    }

    public function pdf(int $id): string
    {
        return (string) $this->db
            ->select('pdf')
            ->get_where('patient_documents', ['id' => $id])
            ->row('pdf');
    }

    public function delete_document(int $id, int $customer_id, int $actor_id): void
    {
        $this->db->trans_begin();
        try {
            $table = $this->db->dbprefix('patient_documents');
            $document = $this->db
                ->query(
                    'SELECT ' . self::METADATA . " FROM `$table` WHERE id = ? AND id_users_customer = ? FOR UPDATE",
                    [$id, $customer_id],
                )
                ->row_array();
            if (!$document) {
                throw new InvalidArgumentException(lang('visit_document_not_found'));
            }
            $changes = array_map(fn($value) => [$value, null], $this->audit_fields($this->decode($document)));
            $deleted = $this->db->delete('patient_documents', ['id' => $id, 'id_users_customer' => $customer_id]);
            if (
                // Written here and not with audit_log(): a removal that cannot be logged is rolled back.
                !$deleted ||
                $this->db->affected_rows() !== 1 ||
                !$this->db->insert('audit_log', [
                    'create_datetime' => date('Y-m-d H:i:s'),
                    'id_users' => $actor_id,
                    'source' => audit_source(),
                    'action' => 'deleted',
                    'entity' => 'patient_document',
                    'entity_id' => $id,
                    'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]) ||
                !$this->db->trans_status()
            ) {
                throw new RuntimeException(lang('visit_document_delete_error'));
            }
            $this->db->trans_commit();
        } catch (Throwable $error) {
            $this->db->trans_rollback();
            throw $error;
        }
    }

    private function decode(array $row): array
    {
        $row['titles'] = json_decode((string) $row['titles'], true) ?: [];

        return $row;
    }

    /** What the activity log keeps of a document, so that it can still name it once removed. Never the PDF. */
    private function audit_fields(array $document): array
    {
        $patient = $this->db
            ->select('first_name, last_name')
            ->get_where('users', ['id' => $document['id_users_customer']])
            ->row_array();
        $titles = $document['titles'];

        return [
            'id_users_customer' => (int) $document['id_users_customer'],
            'patient_name' => trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')),
            // In the language of the installation, as the log is read by its administrators.
            'document_title' => (string) ($titles[setting('default_language')] ?? (reset($titles) ?: '')),
            'professional_name' => $document['professional_name'],
            'generated_at' => $document['created_at'],
            'visit_datetime' => $document['visit_datetime'],
        ];
    }
}
