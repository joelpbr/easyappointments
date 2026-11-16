<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.2.0
 * ---------------------------------------------------------------------------- */

class Migration_Add_calendar_view_setting extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('calendar_view', 'user_settings')) {
            $fields = [
                'calendar_view' => [
                    'type' => 'VARCHAR',
                    'constraint' => '32',
                    'default' => 'default',
                ],
            ];

            $this->dbforge->add_column('user_settings', $fields);

            $this->db->update('user_settings', ['calendar_view' => 'default']);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('calendar_view', 'user_settings')) {
            $this->dbforge->drop_column('user_settings', 'calendar_view');
        }
    }
}
