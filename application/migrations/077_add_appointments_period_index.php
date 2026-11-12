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
 * Index for the availability queries, which always look for the appointments of a provider in a period.
 */
class Migration_Add_appointments_period_index extends EA_Migration
{
    private const INDEX = 'appointments_provider_period';

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->index_exists()) {
            $this->db->query(
                'CREATE INDEX `' .
                    self::INDEX .
                    '` ON `' .
                    $this->db->dbprefix('appointments') .
                    '` (`id_users_provider`, `start_datetime`, `end_datetime`)',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->index_exists()) {
            $this->db->query('DROP INDEX `' . self::INDEX . '` ON `' . $this->db->dbprefix('appointments') . '`');
        }
    }

    /**
     * Check whether the index already exists.
     */
    private function index_exists(): bool
    {
        return $this->db
            ->query('SHOW INDEX FROM `' . $this->db->dbprefix('appointments') . '` WHERE Key_name = ?', [self::INDEX])
            ->num_rows() > 0;
    }
}
