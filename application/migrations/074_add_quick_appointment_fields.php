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
 * Quick appointment form.
 *
 * - appointments.is_first_visit: 1 = first visit, 0 = returning patient, NULL = not known (e.g. imported history).
 * - The email of the patient is no longer required: reception books by phone and many patients have no email.
 */
class Migration_Add_quick_appointment_fields extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('is_first_visit', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'is_first_visit' => [
                    'type' => 'TINYINT',
                    'constraint' => '1',
                    'null' => true,
                    'after' => 'status',
                ],
            ]);
        }

        $this->db->update('settings', ['value' => '0'], ['name' => 'require_email']);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('is_first_visit', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'is_first_visit');
        }

        $this->db->update('settings', ['value' => '1'], ['name' => 'require_email']);
    }
}
