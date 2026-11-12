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
 * Treatment catalog of the clinic.
 *
 * - services.is_active: inactive treatments are kept for the appointment history but can no longer be booked.
 * - appointments.service_name: the treatment name at the time of the visit, so renaming a treatment later does not
 *   rewrite the history.
 * - The initial treatments, with a default duration of 30 minutes that the clinic adjusts from the services page.
 */
class Migration_Add_treatment_catalog extends EA_Migration
{
    /**
     * Initial treatments and their colors.
     */
    private const TREATMENTS = [
        'Quiropodia' => '#4a90d9',
        'Exploración' => '#4caf7a',
        'Cirugía' => '#d9534f',
        'Cura' => '#e88fb4',
        'Cura PV' => '#f0a04b',
        'Entrega SP' => '#3fbfbf',
        'Moldes' => '#f2d25c',
        'Silicona' => '#b28ddb',
        'Ortonixia' => '#7e57c2',
        'Otro' => '#9e9e9e',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('is_active', 'services')) {
            $this->dbforge->add_column('services', [
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => '4',
                    'default' => '1',
                    'after' => 'is_private',
                ],
            ]);
        }

        if (!$this->db->field_exists('service_name', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'service_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => '256',
                    'null' => true,
                    'after' => 'id_services',
                ],
            ]);

            $this->db->query(
                'UPDATE `' .
                    $this->db->dbprefix('appointments') .
                    '` AS appointments JOIN `' .
                    $this->db->dbprefix('services') .
                    '` AS services ON services.id = appointments.id_services SET appointments.service_name = services.name',
            );
        }

        foreach (self::TREATMENTS as $name => $color) {
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
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        foreach (array_keys(self::TREATMENTS) as $name) {
            $service = $this->db->get_where('services', ['name' => $name])->row_array();

            if ($service && !$this->db->get_where('appointments', ['id_services' => $service['id']])->num_rows()) {
                $this->db->delete('services', ['id' => $service['id']]);
            }
        }

        if ($this->db->field_exists('service_name', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'service_name');
        }

        if ($this->db->field_exists('is_active', 'services')) {
            $this->dbforge->drop_column('services', 'is_active');
        }
    }
}
