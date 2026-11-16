<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * Whether the foot diagrams of a visit were saved (appointment_records.foot_diagrams_saved).
 *
 * A visit whose diagrams were never saved starts with a copy of the drawing of the previous visit of the patient; one
 * that was saved keeps its own drawing, even when it is empty because every pathology was removed.
 */
class Migration_Add_foot_diagrams_saved extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('foot_diagrams_saved', 'appointment_records')) {
            $this->dbforge->add_column('appointment_records', [
                'foot_diagrams_saved' => [
                    'type' => 'TINYINT',
                    'constraint' => '1',
                    'default' => 0,
                    'after' => 'orthosis',
                ],
            ]);
        }

        // The visits that already have pathologies placed had their diagrams saved.
        $records = $this->db->dbprefix('appointment_records');
        $markers = $this->db->dbprefix('appointment_foot_markers');

        $this->db->query(
            "INSERT INTO `$records` (create_datetime, update_datetime, id_appointments, foot_diagrams_saved)
            SELECT NOW(), NOW(), markers.id_appointments, 1 FROM `$markers` AS markers
            GROUP BY markers.id_appointments
            ON DUPLICATE KEY UPDATE foot_diagrams_saved = 1",
        );
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('foot_diagrams_saved', 'appointment_records')) {
            $this->dbforge->drop_column('appointment_records', 'foot_diagrams_saved');
        }
    }
}
