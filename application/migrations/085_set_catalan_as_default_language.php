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
 * The clinic works in Catalan and Spanish, with Catalan by default.
 *
 * New patients get Catalan, and the users with a language that is no longer offered (e.g. English, the default of
 * Easy!Appointments) move to Catalan, so that their emails keep a language the clinic uses.
 */
class Migration_Set_catalan_as_default_language extends EA_Migration
{
    private const LANGUAGES = ['catalan', 'spanish'];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $this->db->update('settings', ['value' => 'catalan'], ['name' => 'default_language']);

        $this->db->where_not_in('language', self::LANGUAGES)->update('users', ['language' => 'catalan']);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->db->update('settings', ['value' => 'english'], ['name' => 'default_language']);
    }
}
