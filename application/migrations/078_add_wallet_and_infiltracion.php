<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Add the two missing clinic treatments without changing existing services.
 */
class Migration_Add_wallet_and_infiltracion extends EA_Migration
{
    public function up(): void
    {
        // A treatment must be linked to a provider to appear in booking dropdowns.
        $providers = $this->db
            ->distinct()
            ->select('id_users')
            ->from('services_providers')
            ->get()
            ->result_array();

        $this->db->trans_start();

        foreach (['Wallet' => '#90a4ae', 'Infiltración' => '#ce93d8'] as $name => $color) {
            if ($this->db->get_where('services', ['name' => $name])->num_rows()) {
                continue;
            }

            $this->db->insert('services', [
                'create_datetime' => date('Y-m-d H:i:s'),
                'update_datetime' => date('Y-m-d H:i:s'),
                'name' => $name,
                'duration' => 30,
                'currency' => 'EUR',
                'slot_interval' => 15,
                'color' => $color,
                'attendants_number' => 1,
                'is_private' => 0,
                'is_active' => 1,
            ]);

            $service_id = $this->db->insert_id();

            foreach ($providers as $provider) {
                $this->db->insert('services_providers', [
                    'id_services' => $service_id,
                    'id_users' => $provider['id_users'],
                ]);
            }
        }

        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            throw new RuntimeException('Could not add Wallet and Infiltración.');
        }
    }

    public function down(): void
    {
        // Keep services so that existing appointments and provider links remain valid.
    }
}
