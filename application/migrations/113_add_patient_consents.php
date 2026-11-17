<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Consents signed by the patients on the tablet (see Patient_consents). Each signature is one row, never changed: a new
 * signature replaces the previous one as the current consent, and both stay. The signed PDF is kept with the other
 * documents of the patient (patient_documents), so it is part of the clinical history.
 *
 * company_dpo_contact: contact of the data protection officer, which the information to the patients must give
 * (art. 13.1.b RGPD); health centres keeping clinical histories must appoint one (art. 34.1.l LOPDGDD).
 */
class Migration_Add_patient_consents extends EA_Migration
{
    public function up(): void
    {
        $consents = $this->db->dbprefix('patient_consents');
        $users = $this->db->dbprefix('users');
        $documents = $this->db->dbprefix('patient_documents');
        $this->db->query("CREATE TABLE IF NOT EXISTS `$consents` (
            id INT NOT NULL AUTO_INCREMENT,
            id_users_customer INT NOT NULL,
            id_patient_documents INT NULL,
            signed_at DATETIME NOT NULL,
            version VARCHAR(32) NOT NULL,
            language CHAR(2) NOT NULL,
            choices TEXT NOT NULL,
            whatsapp TINYINT(1) NOT NULL DEFAULT 0,
            signer_role VARCHAR(16) NOT NULL,
            signer_name VARCHAR(200) NOT NULL,
            signer_document VARCHAR(20) NOT NULL DEFAULT '',
            signer_relationship VARCHAR(60) NOT NULL DEFAULT '',
            fingerprint CHAR(64) NOT NULL,
            pdf_sha256 CHAR(64) NOT NULL,
            signature MEDIUMBLOB NOT NULL,
            collected_by INT NULL,
            collected_by_name VARCHAR(256) NOT NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY patient_consents_current (id_users_customer, id),
            FOREIGN KEY (id_users_customer) REFERENCES `$users` (id) ON DELETE CASCADE,
            FOREIGN KEY (id_patient_documents) REFERENCES `$documents` (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!$this->db->get_where('settings', ['name' => 'company_dpo_contact'])->num_rows()) {
            $this->db->insert('settings', ['name' => 'company_dpo_contact', 'value' => '']);
        }
    }

    public function down(): void
    {
        $this->dbforge->drop_table('patient_consents', true);
        $this->db->delete('settings', ['name' => 'company_dpo_contact']);
    }
}
