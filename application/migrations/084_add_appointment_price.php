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
 * The price of each appointment.
 *
 * It starts as the price of its service and can be changed for that appointment, so later changes of the service
 * price do not rewrite the past. Existing appointments take the current price of their service.
 */
class Migration_Add_appointment_price extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if ($this->db->field_exists('price', 'appointments')) {
            return;
        }

        $this->dbforge->add_column('appointments', [
            'price' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'after' => 'service_name'],
        ]);

        $this->db->query(
            'UPDATE `' .
                $this->db->dbprefix('appointments') .
                '` AS appointments INNER JOIN `' .
                $this->db->dbprefix('services') .
                '` AS services ON services.id = appointments.id_services
                SET appointments.price = services.price
                WHERE appointments.is_unavailability = 0',
        );
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('price', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'price');
        }
    }
}
