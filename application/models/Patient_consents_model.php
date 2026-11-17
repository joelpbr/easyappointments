<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Consents signed by the patients (see Patient_consents). The rows are only added, never changed: the latest one of a
 * patient is the current consent.
 */
class Patient_consents_model extends EA_Model
{
    private const SUMMARY = 'id, id_users_customer, id_patient_documents, signed_at, version, language, choices, whatsapp, signer_role, signer_name, signer_relationship, collected_by_name';

    /** The current consent of a patient, without the signature, or null when they never signed one. */
    public function current(int $customer_id): ?array
    {
        $row = $this->db
            ->select(self::SUMMARY)
            ->where('id_users_customer', $customer_id)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('patient_consents')
            ->row_array();

        if (!$row) {
            return null;
        }

        $row['choices'] = json_decode((string) $row['choices'], true) ?: [];

        return $row;
    }

    /** Paginated metadata only: never load signature images or PDFs into the listing. */
    public function page(string $query, int $page): array
    {
        $filter = function () use ($query): void {
            $this->db->from('patient_consents c')->join('users u', 'u.id = c.id_users_customer');
            if ($query !== '') {
                $this->db
                    ->group_start()
                    ->like("CONCAT(u.first_name, ' ', u.last_name)", $query)
                    ->or_like('c.signer_name', $query)
                    ->or_like('c.signer_document', $query)
                    ->group_end();
            }
        };
        $filter();
        $total = $this->db->count_all_results();
        $pages = max(1, (int) ceil($total / 30));
        $page = min(max(1, $page), $pages);
        $filter();
        $entries = $this->db
            ->select(
                'c.id, c.id_users_customer, c.id_patient_documents, c.signed_at, c.language, c.signer_name, c.collected_by_name, u.first_name, u.last_name',
            )
            ->order_by('c.signed_at', 'DESC')
            ->order_by('c.id', 'DESC')
            ->limit(30, ($page - 1) * 30)
            ->get()
            ->result_array();
        return compact('entries', 'total', 'page', 'pages');
    }

    public function store(array $consent): int
    {
        $consent['choices'] = json_encode($consent['choices'], JSON_THROW_ON_ERROR);

        if (!$this->db->insert('patient_consents', $consent)) {
            throw new RuntimeException(lang('consent_save_error'));
        }

        return (int) $this->db->insert_id();
    }
}
