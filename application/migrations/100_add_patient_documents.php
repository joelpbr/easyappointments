<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_patient_documents extends EA_Migration
{
    public function up(): void
    {
        $documents = $this->db->dbprefix('patient_documents');
        $users = $this->db->dbprefix('users');
        $appointments = $this->db->dbprefix('appointments');
        $this->db->query("CREATE TABLE IF NOT EXISTS `$documents` (
            id INT NOT NULL AUTO_INCREMENT,
            id_users_customer INT NOT NULL,
            id_appointments INT NULL,
            created_at DATETIME NOT NULL,
            visit_datetime DATETIME NOT NULL,
            document_type VARCHAR(64) NOT NULL,
            title_ca VARCHAR(256) NOT NULL,
            title_es VARCHAR(256) NOT NULL,
            professional_name VARCHAR(256) NOT NULL,
            created_by_name VARCHAR(256) NOT NULL,
            pdf MEDIUMBLOB NOT NULL,
            PRIMARY KEY (id),
            KEY patient_documents_history (id_users_customer, id),
            FOREIGN KEY (id_users_customer) REFERENCES `$users` (id) ON DELETE CASCADE,
            FOREIGN KEY (id_appointments) REFERENCES `$appointments` (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->dbforge->drop_table('patient_documents', true);
    }
}
