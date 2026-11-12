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
 * Reception agenda.
 *
 * - "calendar_slot_minutes": the grid interval of the calendar, 15 minutes by default.
 * - Administrators and secretaries (reception) start on the table view, where the providers are shown side by side.
 * - Spanish regional format: weeks start on Monday and times use 24 hours, unless they were already changed.
 * - Readable tones for two treatment colors of the initial palette, and appointments with the default color take the
 *   color of their treatment.
 */
class Migration_Add_reception_agenda_settings extends EA_Migration
{
    /**
     * Treatment name => [color set by migration 070, readable color].
     */
    private const PALETTE_FIXES = [
        'Quiropodia' => ['#4a90d9', '#5a9be0'],
        'Cirugía' => ['#d9534f', '#e57373'],
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'calendar_slot_minutes'])->num_rows()) {
            $this->db->insert('settings', ['name' => 'calendar_slot_minutes', 'value' => '15']);
        }

        $user_ids = $this->db
            ->select('users.id')
            ->from('users')
            ->join('roles', 'roles.id = users.id_roles', 'inner')
            ->where_in('roles.slug', ['admin', 'secretary'])
            ->get()
            ->result_array();

        if ($user_ids) {
            $this->db
                ->where_in('id_users', array_column($user_ids, 'id'))
                ->where('calendar_view', 'default')
                ->update('user_settings', ['calendar_view' => 'table']);
        }

        $this->db->update('settings', ['value' => 'monday'], ['name' => 'first_weekday', 'value' => 'sunday']);
        $this->db->update('settings', ['value' => 'military'], ['name' => 'time_format', 'value' => 'regular']);

        // Softer tones of the initial palette, so the appointment text keeps a contrast ratio above 4.5:1. Only the
        // colors that were not changed by the clinic are updated.
        foreach (self::PALETTE_FIXES as $name => [$previous_color, $color]) {
            $this->db->update('services', ['color' => $color], ['name' => $name, 'color' => $previous_color]);
        }

        // Appointments that kept the default color take the color of their service, as the blocks of the agenda are
        // colored by treatment.
        $this->db->query(
            'UPDATE `' .
                $this->db->dbprefix('appointments') .
                '` AS appointments JOIN `' .
                $this->db->dbprefix('services') .
                '` AS services ON services.id = appointments.id_services ' .
                'SET appointments.color = services.color ' .
                'WHERE appointments.is_unavailability = 0 AND LOWER(appointments.color) = ?',
            ['#7cbae8'],
        );
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        // The previous calendar view of each user is not known, so it is left as it is.
        $this->db->delete('settings', ['name' => 'calendar_slot_minutes']);
    }
}
