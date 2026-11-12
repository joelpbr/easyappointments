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
 * Use the clinic theme (assets/css/themes/podologia-sabadell.scss), unless another theme was already chosen.
 */
class Migration_Set_podologia_sabadell_theme extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $this->db->update('settings', ['value' => 'podologia-sabadell'], ['name' => 'theme', 'value' => 'default']);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->db->update('settings', ['value' => 'default'], ['name' => 'theme', 'value' => 'podologia-sabadell']);
    }
}
