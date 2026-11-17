<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Working time rules in the settings (Estatuto de los Trabajadores values until the clinic sets those of its
 * collective agreement) and the contract of each employee: category, duration, schedule and complementary hours.
 */
class Migration_Add_attendance_settings extends EA_Migration
{
    private const COLUMNS = [
        'contract_duration' => 'VARCHAR(16) NOT NULL DEFAULT "unknown"',
        'category' => 'VARCHAR(255) NOT NULL DEFAULT ""',
        'schedule' => 'TEXT NULL',
        'complementary_percent' => 'INT NOT NULL DEFAULT 0',
    ];

    public function up(): void
    {
        require_once APPPATH . 'libraries/Attendance_rules.php';
        $table = $this->db->dbprefix('attendance_profiles');
        foreach (self::COLUMNS as $name => $definition) {
            if (!$this->db->field_exists($name, 'attendance_profiles')) {
                $this->db->query("ALTER TABLE `$table` ADD `$name` $definition");
            }
        }
        // Undefined pauses now follow the rules of the settings instead of leaving the summaries provisional.
        $this->db->update('attendance_profiles', ['pause_policy' => 'agreement'], ['pause_policy' => 'unknown']);
        foreach (Attendance_rules::defaults() as $key => $value) {
            if (!$this->db->get_where('settings', ['name' => 'attendance_' . $key])->num_rows()) {
                $this->db->insert('settings', ['name' => 'attendance_' . $key, 'value' => (string) $value]);
            }
        }
    }

    public function down(): void
    {
        require_once APPPATH . 'libraries/Attendance_rules.php';
        foreach (array_keys(Attendance_rules::defaults()) as $key) {
            $this->db->delete('settings', ['name' => 'attendance_' . $key]);
        }
        $this->db->update('attendance_profiles', ['pause_policy' => 'unknown'], ['pause_policy' => 'agreement']);
        $table = $this->db->dbprefix('attendance_profiles');
        foreach (array_keys(self::COLUMNS) as $name) {
            if ($this->db->field_exists($name, 'attendance_profiles')) {
                $this->db->query("ALTER TABLE `$table` DROP `$name`");
            }
        }
    }
}
