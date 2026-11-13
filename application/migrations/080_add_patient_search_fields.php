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
 * Fields and indexes for the patient search.
 *
 * - users.phone_normalized: the phone number in one format (see normalize_phone()), to find a patient whatever the
 *   way the number was typed and to detect possible duplicates.
 * - Indexes on the first name, the last name and the normalized phone, which the search matches by their beginning.
 */
class Migration_Add_patient_search_fields extends EA_Migration
{
    private const INDEXES = [
        'users_first_name' => 'first_name',
        'users_last_name' => 'last_name',
        'users_phone_normalized' => 'phone_normalized',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('phone_normalized', 'users')) {
            $this->dbforge->add_column('users', [
                'phone_normalized' => [
                    'type' => 'VARCHAR',
                    'constraint' => '32',
                    'null' => true,
                    'after' => 'phone_number',
                ],
            ]);
        }

        $users = $this->db
            ->select('id, phone_number')
            ->from('users')
            ->where('phone_number IS NOT NULL')
            ->where('phone_number !=', '')
            ->get()
            ->result_array();

        foreach ($users as $user) {
            $this->db->update(
                'users',
                ['phone_normalized' => normalize_phone($user['phone_number'])],
                ['id' => $user['id']],
            );
        }

        foreach (self::INDEXES as $index => $column) {
            if (!$this->index_exists($index)) {
                $this->db->query(
                    'CREATE INDEX `' . $index . '` ON `' . $this->db->dbprefix('users') . '` (`' . $column . '`)',
                );
            }
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $index) {
            if ($this->index_exists($index)) {
                $this->db->query('DROP INDEX `' . $index . '` ON `' . $this->db->dbprefix('users') . '`');
            }
        }

        if ($this->db->field_exists('phone_normalized', 'users')) {
            $this->dbforge->drop_column('users', 'phone_normalized');
        }
    }

    /**
     * Check whether an index of the users table exists.
     */
    private function index_exists(string $index): bool
    {
        return $this->db
            ->query('SHOW INDEX FROM `' . $this->db->dbprefix('users') . '` WHERE Key_name = ?', [$index])
            ->num_rows() > 0;
    }
}
