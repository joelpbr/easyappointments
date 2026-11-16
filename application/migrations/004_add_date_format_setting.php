<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.1.0
 * ---------------------------------------------------------------------------- */

class Migration_Add_date_format_setting extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * @throws Exception
     */
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'date_format'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'date_format',
                'value' => 'DMY',
            ]);
        }
    }

    /**
     * Downgrade method.
     *
     * @throws Exception
     */
    public function down(): void
    {
        if ($this->db->get_where('settings', ['name' => 'date_format'])->num_rows()) {
            $this->db->delete('settings', ['name' => 'date_format']);
        }
    }
}
