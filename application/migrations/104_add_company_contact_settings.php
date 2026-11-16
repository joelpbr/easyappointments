<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * The address and the phone of the clinic, in the general settings: the documents of the visits print them
 * (the clinic_address and clinic_phone fields of their templates) instead of an address written in the code.
 */
class Migration_Add_company_contact_settings extends EA_Migration
{
    private const SETTINGS = ['company_address', 'company_phone'];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        foreach (self::SETTINGS as $name) {
            if (!$this->db->get_where('settings', ['name' => $name])->num_rows()) {
                $this->db->insert('settings', ['name' => $name, 'value' => '']);
            }
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->db->where_in('name', self::SETTINGS)->delete('settings');
    }
}
