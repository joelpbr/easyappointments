<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * Remove what the clinic does not use and was removed from the code:
 *
 * - The video call link of the appointments (Jitsi and Google Meet).
 * - LDAP (directory login and user import).
 * - The five custom fields of the patients.
 * - The web analytics (Google Analytics and Matomo).
 * - The mobile number of the users, always empty: the phone number is the one in use.
 *
 * Also: the patients are always in the clinic's timezone, so their own one is emptied, and the online booking is
 * turned off, as the clinic books its appointments itself (the address of the application leads to the login).
 */
class Migration_Remove_unused_features extends EA_Migration
{
    private const COLUMNS = [
        'appointments' => ['meeting_link'],
        'users' => [
            'ldap_dn',
            'custom_field_1',
            'custom_field_2',
            'custom_field_3',
            'custom_field_4',
            'custom_field_5',
            'mobile_number',
        ],
    ];

    private const SETTINGS = [
        'google_analytics_code',
        'matomo_analytics_url',
        'matomo_analytics_site_id',
        'google_meet_link_generation',
    ];

    private const SETTING_PREFIXES = [
        'ldap_',
        'jitsi_',
        'display_custom_field_',
        'require_custom_field_',
        'label_custom_field_',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->db->field_exists($column, $table)) {
                    $this->dbforge->drop_column($table, $column);
                }
            }
        }

        $this->db->where_in('name', self::SETTINGS)->delete('settings');

        foreach (self::SETTING_PREFIXES as $prefix) {
            $this->db->like('name', $prefix, 'after')->delete('settings');
        }

        $this->db->update('settings', ['value' => '1'], ['name' => 'disable_booking']);

        $customer_role = $this->db->get_where('roles', ['slug' => 'customer'])->row_array();

        if ($customer_role) {
            $this->db->update('users', ['timezone' => null], ['id_roles' => $customer_role['id']]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        // The columns come back empty; the removed settings and the timezones of the patients are not restored.
        if (!$this->db->field_exists('meeting_link', 'appointments')) {
            $this->dbforge->add_column('appointments', ['meeting_link' => ['type' => 'TEXT', 'null' => true]]);
        }

        foreach (['mobile_number' => 'VARCHAR(128)', 'ldap_dn' => 'TEXT'] as $column => $type) {
            if (!$this->db->field_exists($column, 'users')) {
                $this->db->query('ALTER TABLE `' . $this->db->dbprefix('users') . "` ADD `$column` $type NULL");
            }
        }

        for ($i = 1; $i <= 5; $i++) {
            if (!$this->db->field_exists('custom_field_' . $i, 'users')) {
                $this->dbforge->add_column('users', ['custom_field_' . $i => ['type' => 'TEXT', 'null' => true]]);
            }
        }

        $this->db->update('settings', ['value' => '0'], ['name' => 'disable_booking']);
    }
}
