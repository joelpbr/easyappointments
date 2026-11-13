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
 * Clinical record of the patients (see the Clinical_record_templates library).
 *
 * - patient_records: the record of each patient (birth date, referral, profession, physical activity, medical history
 *   and allergies). Name, phone, address and NIF/NIE stay in users and fiscal_parties.
 * - appointment_records: what the provider writes down in each visit. Which fields a visit has depends on the record
 *   template of its treatment.
 * - services.record_template: the record template of each treatment, set here for the clinic treatments and editable
 *   on the services page.
 */
class Migration_Add_clinical_records extends EA_Migration
{
    /**
     * Record template of the clinic treatments; the others use "notes".
     */
    private const TEMPLATES = [
        'Quiropodia' => 'feet',
        'Cura' => 'feet',
        'Cura PV' => 'feet',
        'Ortonixia' => 'feet',
        'Wallet' => 'feet',
        'Cirugía' => 'surgery',
        'Exploración' => 'exploration',
        'Silicona' => 'silicone',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('record_template', 'services')) {
            $this->dbforge->add_column('services', [
                'record_template' => [
                    'type' => 'VARCHAR',
                    'constraint' => '32',
                    'default' => 'notes',
                    'after' => 'color',
                ],
            ]);

            foreach (self::TEMPLATES as $name => $template) {
                $this->db->update('services', ['record_template' => $template], ['name' => $name]);
            }
        }

        if (!$this->db->table_exists('patient_records')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => '11', 'auto_increment' => true],
                'create_datetime' => ['type' => 'DATETIME', 'null' => true],
                'update_datetime' => ['type' => 'DATETIME', 'null' => true],
                'id_users_customer' => ['type' => 'INT', 'constraint' => '11'],
                'birth_date' => ['type' => 'DATE', 'null' => true],
                'referral_source' => ['type' => 'VARCHAR', 'constraint' => '256', 'null' => true],
                'profession' => ['type' => 'VARCHAR', 'constraint' => '256', 'null' => true],
                'physical_activity' => ['type' => 'VARCHAR', 'constraint' => '256', 'null' => true],
                'medical_history' => ['type' => 'TEXT', 'null' => true],
                'allergies' => ['type' => 'TEXT', 'null' => true],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->create_table('patient_records', true, ['engine' => 'InnoDB']);

            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('patient_records') .
                    '` ADD UNIQUE KEY `patient_records_customer` (`id_users_customer`), ADD CONSTRAINT `patient_records_customer_fk` FOREIGN KEY (`id_users_customer`) REFERENCES `' .
                    $this->db->dbprefix('users') .
                    '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            );
        }

        if (!$this->db->table_exists('appointment_records')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => '11', 'auto_increment' => true],
                'create_datetime' => ['type' => 'DATETIME', 'null' => true],
                'update_datetime' => ['type' => 'DATETIME', 'null' => true],
                'id_appointments' => ['type' => 'INT', 'constraint' => '11'],
                'reason' => ['type' => 'TEXT', 'null' => true],
                'diagnosis' => ['type' => 'TEXT', 'null' => true],
                'treatment' => ['type' => 'TEXT', 'null' => true],
                'observations' => ['type' => 'TEXT', 'null' => true],
                'orthosis' => ['type' => 'VARCHAR', 'constraint' => '32', 'null' => true],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->create_table('appointment_records', true, ['engine' => 'InnoDB']);

            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('appointment_records') .
                    '` ADD UNIQUE KEY `appointment_records_appointment` (`id_appointments`), ADD CONSTRAINT `appointment_records_appointment_fk` FOREIGN KEY (`id_appointments`) REFERENCES `' .
                    $this->db->dbprefix('appointments') .
                    '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->dbforge->drop_table('appointment_records', true);
        $this->dbforge->drop_table('patient_records', true);

        if ($this->db->field_exists('record_template', 'services')) {
            $this->dbforge->drop_column('services', 'record_template');
        }
    }
}
