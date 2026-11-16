<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_provider_calendar_order extends EA_Migration
{
    public function up(): void
    {
        if ($this->db->field_exists('calendar_order', 'users')) {
            return;
        }
        $this->dbforge->add_column('users', [
            'calendar_order' => ['type' => 'INT', 'unsigned' => true, 'default' => 1000],
        ]);
        // Preserve the existing alphabetical calendar order until the administrator changes it.
        $providers = $this->db
            ->select('users.id')
            ->from('users')
            ->join('roles', 'roles.id = users.id_roles')
            ->where('roles.slug', 'provider')
            ->order_by('users.first_name, users.last_name, users.email, users.id')
            ->get()->result_array();
        foreach ($providers as $index => $provider) {
            $this->db->update('users', ['calendar_order' => $index + 1], ['id' => $provider['id']]);
        }
    }

    public function down(): void
    {
        if ($this->db->field_exists('calendar_order', 'users')) {
            $this->dbforge->drop_column('users', 'calendar_order');
        }
    }
}
