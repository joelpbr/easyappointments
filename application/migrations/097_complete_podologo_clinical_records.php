<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Complete_podologo_clinical_records extends EA_Migration
{
    public function up(): void
    {
        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `' .
                $this->db->dbprefix('podologo_import_links') .
                '` (
            source_table VARCHAR(32) NOT NULL, source_id INT NOT NULL, target_id INT NOT NULL,
            PRIMARY KEY(source_table, source_id), UNIQUE KEY target(source_table, target_id)
        ) ENGINE=InnoDB',
        );
        foreach (
            [
                'appointment_records' => ['reason', 'diagnosis', 'treatment', 'observations'],
                'patient_records' => ['medical_history', 'allergies'],
            ]
            as $table => $fields
        ) {
            foreach ($fields as $field) {
                $this->db->query(
                    'ALTER TABLE `' . $this->db->dbprefix($table) . '` MODIFY `' . $field . '` LONGTEXT NULL',
                );
            }
        }
    }

    public function down(): void
    {
        $this->dbforge->drop_table('podologo_import_links', true);
        // Do not shrink clinical text columns: that could truncate recovered histories.
    }
}
