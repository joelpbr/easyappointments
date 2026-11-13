<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Add the "Revisión SP" treatment (review after the delivery of the insoles), 30 minutes and 40 €, offered by every
 * provider that offers treatments. It is listed after "Entrega SP" (see Services_model::DROPDOWN_ORDER).
 */
class Migration_Add_sp_review_treatment extends EA_Migration
{
    private const NAME = 'Revisión SP';

    public function up(): void
    {
        if ($this->db->get_where('services', ['name' => self::NAME])->num_rows()) {
            return;
        }

        // A treatment must be linked to a provider to appear in booking dropdowns.
        $providers = $this->db->distinct()->select('id_users')->from('services_providers')->get()->result_array();

        $this->db->trans_start();

        $this->db->insert('services', [
            'create_datetime' => date('Y-m-d H:i:s'),
            'update_datetime' => date('Y-m-d H:i:s'),
            'name' => self::NAME,
            'duration' => 30,
            'price' => 40,
            'currency' => 'EUR',
            'slot_interval' => 15,
            'color' => '#f7d6ae',
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

        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            throw new RuntimeException('Could not add the Revisión SP treatment.');
        }
    }

    public function down(): void
    {
        // Keep the service so that existing appointments and provider links remain valid.
    }
}
