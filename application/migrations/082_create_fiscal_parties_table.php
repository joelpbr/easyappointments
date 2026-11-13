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
 * Fiscal parties: the identification and fiscal address used to invoice a patient.
 *
 * They are kept apart from the patient because the person who receives the treatment is not always the one an
 * invoice is addressed to: "relationship" is "patient" for the patient's own data and "other" for another person or
 * an entity that pays for them (e.g. a relative or an insurer).
 */
class Migration_Create_fiscal_parties_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if ($this->db->table_exists('fiscal_parties')) {
            return;
        }

        $this->dbforge->add_field([
            'id' => ['type' => 'INT', 'constraint' => '11', 'auto_increment' => true],
            'create_datetime' => ['type' => 'DATETIME', 'null' => true],
            'update_datetime' => ['type' => 'DATETIME', 'null' => true],
            'id_users_customer' => ['type' => 'INT', 'constraint' => '11'],
            'relationship' => ['type' => 'VARCHAR', 'constraint' => '32', 'default' => 'patient'],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => '16', 'null' => true],
            'document_number' => ['type' => 'VARCHAR', 'constraint' => '64', 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => '256', 'null' => true],
            'address' => ['type' => 'VARCHAR', 'constraint' => '256', 'null' => true],
            'zip_code' => ['type' => 'VARCHAR', 'constraint' => '16', 'null' => true],
            'city' => ['type' => 'VARCHAR', 'constraint' => '128', 'null' => true],
            'province' => ['type' => 'VARCHAR', 'constraint' => '128', 'null' => true],
            'country' => ['type' => 'VARCHAR', 'constraint' => '128', 'null' => true],
        ]);

        $this->dbforge->add_key('id', true);
        $this->dbforge->add_key('id_users_customer');
        $this->dbforge->add_key('document_number');
        $this->dbforge->create_table('fiscal_parties', true, ['engine' => 'InnoDB']);

        $this->db->query(
            'ALTER TABLE `' .
                $this->db->dbprefix('fiscal_parties') .
                '` ADD CONSTRAINT `fiscal_parties_customer` FOREIGN KEY (`id_users_customer`) REFERENCES `' .
                $this->db->dbprefix('users') .
                '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
        );
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->dbforge->drop_table('fiscal_parties', true);
    }
}
