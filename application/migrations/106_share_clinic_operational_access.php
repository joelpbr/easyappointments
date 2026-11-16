<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Share_clinic_operational_access extends EA_Migration
{
    public function up(): void
    {
        $this->db->where_in('slug', ['provider', 'secretary'])->update('roles', [
            'appointments' => 15,
            'customers' => 15,
            'services' => 15,
            'blocked_periods' => 15,
            'user_settings' => 15,
            'users' => 0,
            'system_settings' => 0,
            'webhooks' => 0,
        ]);
        if (!$this->db->get_where('settings', ['name' => 'shared_clinic_access'])->num_rows()) {
            $this->db->insert('settings', ['name' => 'shared_clinic_access', 'value' => '1']);
        } else {
            $this->db->update('settings', ['value' => '1'], ['name' => 'shared_clinic_access']);
        }
    }

    public function down(): void
    {
        $this->db
            ->where_in('slug', ['provider', 'secretary'])
            ->update('roles', ['services' => 0, 'blocked_periods' => 0]);
        $this->db->delete('settings', ['name' => 'shared_clinic_access']);
    }
}
