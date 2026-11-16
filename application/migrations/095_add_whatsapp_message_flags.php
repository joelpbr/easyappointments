<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * The WhatsApp messages page (Whatsapp_messages) also lists the test messages, and lets the messages be removed.
 *
 * - whatsapp_reminders.is_test: a test message sent from the settings, with no appointment.
 * - whatsapp_reminders.is_deleted: removed from the list. The row stays, so that its key keeps the scheduled task from
 *   creating and sending the reminder of that appointment again.
 */
class Migration_Add_whatsapp_message_flags extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        foreach (['is_test' => 'status', 'is_deleted' => 'is_test'] as $field => $after) {
            if (!$this->db->field_exists($field, 'whatsapp_reminders')) {
                $this->dbforge->add_column('whatsapp_reminders', [
                    $field => [
                        'type' => 'TINYINT',
                        'constraint' => '1',
                        'default' => 0,
                        'null' => false,
                        'after' => $after,
                    ],
                ]);
            }
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        foreach (['is_deleted', 'is_test'] as $field) {
            if ($this->db->field_exists($field, 'whatsapp_reminders')) {
                $this->dbforge->drop_column('whatsapp_reminders', $field);
            }
        }
    }
}
