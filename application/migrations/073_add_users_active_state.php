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
 * Active state of the users.
 *
 * An inactive provider keeps their appointments, but no longer appears in the agenda or the booking page and cannot
 * be booked.
 */
class Migration_Add_users_active_state extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('is_active', 'users')) {
            $this->dbforge->add_column('users', [
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => '4',
                    'default' => '1',
                    'after' => 'is_private',
                ],
            ]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('is_active', 'users')) {
            $this->dbforge->drop_column('users', 'is_active');
        }
    }
}
