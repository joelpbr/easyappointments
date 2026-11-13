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
 * Add the "Revisión" treatment (a short free check-up), 10 minutes and free, offered by every provider that offers
 * treatments, with observations as its clinical record. It is listed right after "Quiropodia" (see
 * Services_model::DROPDOWN_ORDER).
 */
class Migration_Add_review_treatment extends EA_Migration
{
    private const NAME = 'Revisión';

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
            'duration' => 10,
            'price' => 0,
            'currency' => 'EUR',
            'slot_interval' => 15,
            'color' => '#b9e4f0',
            'record_template' => 'notes',
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
            throw new RuntimeException('Could not add the Revisión treatment.');
        }
    }

    public function down(): void
    {
        // Keep the service so that existing appointments and provider links remain valid.
    }
}
