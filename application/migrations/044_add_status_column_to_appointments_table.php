<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

class Migration_Add_status_column_to_appointments_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('status', 'appointments')) {
            $fields = [
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => '512',
                    'default' => '',
                    'after' => 'color',
                ],
            ];

            $this->dbforge->add_column('appointments', $fields);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('status', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'status');
        }
    }
}
