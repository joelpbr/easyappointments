<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Original rows and drawing coordinate systems for lossless legacy imports. */
class Migration_Add_podologo_import extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->field_exists('foot_diagram_layout', 'appointment_records')) {
            $this->dbforge->add_column('appointment_records', [
                'foot_diagram_layout' => ['type' => 'TEXT', 'null' => true],
            ]);
        }
        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `' .
                $this->db->dbprefix('podologo_source_rows') .
                '` (
            source_table VARCHAR(64) NOT NULL,
            `row_number` INT NOT NULL,
            source_json LONGTEXT NOT NULL,
            PRIMARY KEY (source_table, `row_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
        foreach (['coordinate_width', 'coordinate_height'] as $field) {
            if (!$this->db->field_exists($field, 'appointment_foot_markers')) {
                $this->dbforge->add_column('appointment_foot_markers', [
                    $field => ['type' => 'INT', 'null' => true],
                ]);
            }
        }
        if (!$this->db->field_exists('background_image', 'appointment_foot_markers')) {
            $this->dbforge->add_column('appointment_foot_markers', [
                'background_image' => ['type' => 'VARCHAR', 'constraint' => '256', 'null' => true],
            ]);
        }
    }

    public function down(): void
    {
        $this->dbforge->drop_column('appointment_records', 'foot_diagram_layout');
        $this->dbforge->drop_table('podologo_source_rows', true);
        foreach (['coordinate_width', 'coordinate_height'] as $field) {
            $this->dbforge->drop_column('appointment_foot_markers', $field);
        }
        $this->dbforge->drop_column('appointment_foot_markers', 'background_image');
    }
}
