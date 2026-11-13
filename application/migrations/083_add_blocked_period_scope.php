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
 * Types, scope and repetition of the blocked periods, and the local holidays.
 *
 * - blocked_periods.type: holiday, closure, vacation, absence... (see Blocked_periods_model::TYPES).
 * - blocked_periods.recurrence and recurrence_until: the period repeats every week or every year until a date.
 * - blocked_periods_providers: the providers a period applies to; a period without providers closes the whole clinic.
 * - holidays: the local holidays and any other holiday added by hand. National and regional ones are calculated
 *   (see the Holidays library). A holiday never blocks anything by itself: the administrator decides.
 */
class Migration_Add_blocked_period_scope extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $columns = [
            'type' => ['type' => 'VARCHAR', 'constraint' => '32', 'default' => 'other', 'after' => 'name'],
            'recurrence' => ['type' => 'VARCHAR', 'constraint' => '16', 'default' => 'none', 'after' => 'end_datetime'],
            'recurrence_until' => ['type' => 'DATE', 'null' => true, 'after' => 'recurrence'],
        ];

        foreach ($columns as $name => $definition) {
            if (!$this->db->field_exists($name, 'blocked_periods')) {
                $this->dbforge->add_column('blocked_periods', [$name => $definition]);
            }
        }

        if (!$this->db->table_exists('blocked_periods_providers')) {
            $this->dbforge->add_field([
                'id_blocked_periods' => ['type' => 'INT', 'constraint' => '11'],
                'id_users_provider' => ['type' => 'INT', 'constraint' => '11'],
            ]);

            $this->dbforge->add_key(['id_blocked_periods', 'id_users_provider'], true);
            $this->dbforge->add_key('id_users_provider');
            $this->dbforge->create_table('blocked_periods_providers', true, ['engine' => 'InnoDB']);

            $table = $this->db->dbprefix('blocked_periods_providers');

            $this->db->query(
                'ALTER TABLE `' .
                    $table .
                    '`
                    ADD CONSTRAINT `blocked_periods_providers_period` FOREIGN KEY (`id_blocked_periods`)
                        REFERENCES `' .
                    $this->db->dbprefix('blocked_periods') .
                    '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    ADD CONSTRAINT `blocked_periods_providers_provider` FOREIGN KEY (`id_users_provider`)
                        REFERENCES `' .
                    $this->db->dbprefix('users') .
                    '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            );
        }

        if (!$this->db->table_exists('holidays')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => '11', 'auto_increment' => true],
                'date' => ['type' => 'DATE'],
                'name' => ['type' => 'VARCHAR', 'constraint' => '256'],
                'level' => ['type' => 'VARCHAR', 'constraint' => '16', 'default' => 'local'],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('date');
            $this->dbforge->create_table('holidays', true, ['engine' => 'InnoDB']);
        }

        if (!$this->db->get_where('settings', ['name' => 'holiday_region'])->num_rows()) {
            // The clinic is in Sabadell (Catalonia); it can be changed in the holidays page.
            $this->db->insert('settings', ['name' => 'holiday_region', 'value' => 'CT']);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->dbforge->drop_table('holidays', true);
        $this->dbforge->drop_table('blocked_periods_providers', true);

        foreach (['type', 'recurrence', 'recurrence_until'] as $name) {
            if ($this->db->field_exists($name, 'blocked_periods')) {
                $this->dbforge->drop_column('blocked_periods', $name);
            }
        }

        $this->db->delete('settings', ['name' => 'holiday_region']);
    }
}
