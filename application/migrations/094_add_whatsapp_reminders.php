<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * WhatsApp reminders, sent the day before each appointment at 10:00 (Europe/Madrid) through the WhatsApp Cloud API of
 * Meta (see the Whatsapp_reminders library).
 *
 * - appointments.send_whatsapp_reminder: whether the appointment gets the reminder, yes by default (also for the
 *   appointments that already exist).
 * - whatsapp_reminders: one row per reminder, with its status and the answers of Meta. idempotency_key is unique per
 *   appointment and day, so a reminder is never sent twice even when the scheduled task runs again.
 * - The settings of the integration, off until the clinic enters its Meta details.
 */
class Migration_Add_whatsapp_reminders extends EA_Migration
{
    /**
     * Settings of the integration and their default values.
     */
    private const SETTINGS = [
        'whatsapp_enabled' => '0',
        'whatsapp_phone_number_id' => '',
        'whatsapp_access_token' => '',
        'whatsapp_app_secret' => '',
        'whatsapp_template_name' => 'recordatori_cita',
        'whatsapp_template_language' => 'ca',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('send_whatsapp_reminder', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'send_whatsapp_reminder' => [
                    'type' => 'TINYINT',
                    'constraint' => '1',
                    'default' => 1,
                    'null' => false,
                    'after' => 'is_first_visit',
                ],
            ]);
        }

        if (!$this->db->table_exists('whatsapp_reminders')) {
            $reminders = $this->db->dbprefix('whatsapp_reminders');

            $this->db->query(
                "CREATE TABLE `$reminders` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `create_datetime` DATETIME NULL,
                    `update_datetime` DATETIME NULL,
                    `idempotency_key` VARCHAR(64) NOT NULL,
                    `id_appointments` INT NULL,
                    `id_users_customer` INT NULL,
                    `phone` VARCHAR(32) NULL,
                    `appointment_datetime` DATETIME NOT NULL,
                    `scheduled_datetime` DATETIME NOT NULL,
                    `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
                    `attempts` INT NOT NULL DEFAULT 0,
                    `last_attempt_datetime` DATETIME NULL,
                    `provider_message_id` VARCHAR(128) NULL,
                    `status_datetime` DATETIME NULL,
                    `error` TEXT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `whatsapp_reminders_idempotency_key` (`idempotency_key`),
                    KEY `whatsapp_reminders_provider_message_id` (`provider_message_id`),
                    KEY `whatsapp_reminders_status` (`status`, `scheduled_datetime`),
                    KEY `whatsapp_reminders_appointment` (`id_appointments`),
                    CONSTRAINT `whatsapp_reminders_appointment` FOREIGN KEY (`id_appointments`) REFERENCES `" .
                    $this->db->dbprefix('appointments') .
                    "` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
        }

        foreach (self::SETTINGS as $name => $value) {
            if (!$this->db->get_where('settings', ['name' => $name])->num_rows()) {
                $this->db->insert('settings', ['name' => $name, 'value' => $value]);
            }
        }

        // The token that Meta sends back when the webhook is registered, made up here so that it is never guessable.
        if (!$this->db->get_where('settings', ['name' => 'whatsapp_webhook_verify_token'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'whatsapp_webhook_verify_token',
                'value' => bin2hex(random_bytes(16)),
            ]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->dbforge->drop_table('whatsapp_reminders', true);

        if ($this->db->field_exists('send_whatsapp_reminder', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'send_whatsapp_reminder');
        }

        $this->db->where_in('name', [...array_keys(self::SETTINGS), 'whatsapp_webhook_verify_token']);
        $this->db->delete('settings');
    }
}
