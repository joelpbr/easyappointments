<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * The rules of the WhatsApp reminders, editable from their settings (see Whatsapp_reminders::rules()):
 *
 * - whatsapp_send_time, whatsapp_days_before: the usual moment, the day before at 10:00.
 * - whatsapp_booking_delay: minutes that must pass from the creation of the appointment (30).
 * - whatsapp_min_notice: minutes before the appointment from which nothing is sent any more (120).
 * - whatsapp_template_says_tomorrow: the approved template says "demà" in its text. It is on where the integration
 *   was already set up with that template, so that nothing changes until the new one (without "demà") is approved
 *   in Meta; then it is turned off from the settings.
 *
 * whatsapp_reminders gets the change of an appointment made after its reminder went out, so that the clinic tells
 * the patient: the new start (change_to_datetime), when it was seen and when someone said the patient was told.
 */
class Migration_Add_whatsapp_send_rules extends EA_Migration
{
    /**
     * Rules and their default values.
     */
    private const SETTINGS = [
        'whatsapp_send_time' => '10:00',
        'whatsapp_days_before' => '1',
        'whatsapp_booking_delay' => '30',
        'whatsapp_min_notice' => '120',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        foreach (self::SETTINGS as $name => $value) {
            if (!$this->db->get_where('settings', ['name' => $name])->num_rows()) {
                $this->db->insert('settings', ['name' => $name, 'value' => $value]);
            }
        }

        if (!$this->db->get_where('settings', ['name' => 'whatsapp_template_says_tomorrow'])->num_rows()) {
            $configured =
                $this->db->get_where('settings', ['name' => 'whatsapp_enabled', 'value' => '1'])->num_rows() ||
                $this->db
                    ->where('name', 'whatsapp_access_token')
                    ->where('value !=', '')
                    ->count_all_results('settings');

            $this->db->insert('settings', [
                'name' => 'whatsapp_template_says_tomorrow',
                'value' => $configured ? '1' : '0',
            ]);
        }

        $columns = [
            'change_to_datetime' => ['type' => 'DATETIME', 'null' => true, 'after' => 'error'],
            'change_datetime' => ['type' => 'DATETIME', 'null' => true, 'after' => 'change_to_datetime'],
            'change_handled_datetime' => ['type' => 'DATETIME', 'null' => true, 'after' => 'change_datetime'],
        ];

        foreach ($columns as $name => $column) {
            if (!$this->db->field_exists($name, 'whatsapp_reminders')) {
                $this->dbforge->add_column('whatsapp_reminders', [$name => $column]);
            }
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        foreach (['change_handled_datetime', 'change_datetime', 'change_to_datetime'] as $name) {
            if ($this->db->field_exists($name, 'whatsapp_reminders')) {
                $this->dbforge->drop_column('whatsapp_reminders', $name);
            }
        }

        $this->db->where_in('name', [...array_keys(self::SETTINGS), 'whatsapp_template_says_tomorrow']);
        $this->db->delete('settings');
    }
}
