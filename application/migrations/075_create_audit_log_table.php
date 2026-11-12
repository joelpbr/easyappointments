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
 * Audit log: who did what and when on the relevant records (appointments, services, providers...).
 *
 * The user is not a foreign key on purpose, so the log survives the deletion of the user it mentions.
 */
class Migration_Create_audit_log_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if ($this->db->table_exists('audit_log')) {
            return;
        }

        $this->dbforge->add_field([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => '20',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'create_datetime' => [
                'type' => 'DATETIME',
            ],
            'id_users' => [
                'type' => 'INT',
                'constraint' => '11',
                'null' => true,
            ],
            'source' => [
                'type' => 'VARCHAR',
                'constraint' => '16',
            ],
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => '32',
            ],
            'entity' => [
                'type' => 'VARCHAR',
                'constraint' => '32',
            ],
            'entity_id' => [
                'type' => 'INT',
                'constraint' => '11',
                'null' => true,
            ],
            'changes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);

        $this->dbforge->add_key('id', true);
        $this->dbforge->add_key(['entity', 'entity_id']);
        $this->dbforge->add_key('create_datetime');

        $this->dbforge->create_table('audit_log', true, ['engine' => 'InnoDB']);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->dbforge->drop_table('audit_log', true);
    }
}
