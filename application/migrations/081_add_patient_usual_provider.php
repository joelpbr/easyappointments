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
 * The usual provider of a patient (optional), suggested when an appointment is booked for them.
 *
 * Removing the provider leaves the patient without a usual provider.
 */
class Migration_Add_patient_usual_provider extends EA_Migration
{
    private const FOREIGN_KEY = 'users_preferred_provider';

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('id_users_preferred_provider', 'users')) {
            $this->dbforge->add_column('users', [
                'id_users_preferred_provider' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'null' => true,
                    'after' => 'id_roles',
                ],
            ]);

            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('users') .
                    '` ADD CONSTRAINT `' .
                    self::FOREIGN_KEY .
                    '` FOREIGN KEY (`id_users_preferred_provider`) REFERENCES `' .
                    $this->db->dbprefix('users') .
                    '` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('id_users_preferred_provider', 'users')) {
            $this->db->query(
                'ALTER TABLE `' . $this->db->dbprefix('users') . '` DROP FOREIGN KEY `' . self::FOREIGN_KEY . '`',
            );

            $this->dbforge->drop_column('users', 'id_users_preferred_provider');
        }
    }
}
