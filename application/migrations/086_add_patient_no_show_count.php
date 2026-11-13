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
 * The number of appointments each patient missed (no-shows), kept with the patient so that the search and the
 * appointment form can show it at once. It is counted from the existing no-show appointments.
 */
class Migration_Add_patient_no_show_count extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if ($this->db->field_exists('no_show_count', 'users')) {
            return;
        }

        $this->dbforge->add_column('users', [
            'no_show_count' => ['type' => 'INT', 'constraint' => '11', 'default' => 0, 'after' => 'notes'],
        ]);

        $this->db->query(
            'UPDATE `' .
                $this->db->dbprefix('users') .
                '` AS users SET users.no_show_count = (SELECT COUNT(*) FROM `' .
                $this->db->dbprefix('appointments') .
                "` AS appointments WHERE appointments.id_users_customer = users.id AND appointments.status = 'no_show')",
        );
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('no_show_count', 'users')) {
            $this->dbforge->drop_column('users', 'no_show_count');
        }
    }
}
