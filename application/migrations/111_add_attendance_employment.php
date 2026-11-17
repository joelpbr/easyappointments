<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Employment relationship of each staff member: employees ("employee") clock in, self-employed professionals and
 * partners ("self_employed") do not. Secretaries were the only ones clocking in, so they start as employees; the
 * other staff starts undefined until the clinic sets it. The journal of the records gets a chain of seals that shows
 * any change made directly in the database, and the rules get the interruptions a part-time workday may have.
 */
class Migration_Add_attendance_employment extends EA_Migration
{
    public function up(): void
    {
        require_once APPPATH . 'libraries/Attendance_rules.php';
        if (!$this->db->field_exists('employment', 'users')) {
            $this->dbforge->add_column('users', [
                'employment' => ['type' => 'VARCHAR', 'constraint' => '16', 'null' => true],
            ]);
        }
        $secretary = $this->db->get_where('roles', ['slug' => DB_SLUG_SECRETARY])->row('id');
        if ($secretary) {
            $this->db
                ->where('id_roles', $secretary)
                ->where('employment', null)
                ->update('users', ['employment' => 'employee']);
        }

        $journal = $this->db->dbprefix('attendance_journal');
        if (!$this->db->field_exists('hash', 'attendance_journal')) {
            $this->db->query("ALTER TABLE `$journal` ADD `hash` CHAR(64) NULL");
        }
        $previous = [];
        foreach ($this->db->order_by('id')->get('attendance_journal')->result_array() as $row) {
            $hash = Attendance_rules::seal($previous[$row['employee_id']] ?? null, $row);
            $this->db->update('attendance_journal', ['hash' => $hash], ['id' => $row['id']]);
            $previous[$row['employee_id']] = $hash;
        }

        $name = 'attendance_part_time_max_interruptions';
        if (!$this->db->get_where('settings', ['name' => $name])->num_rows()) {
            $value = Attendance_rules::defaults()['part_time_max_interruptions'];
            foreach (Attendance_rules::agreements() as $agreement) {
                if ($agreement['values']['agreement'] === setting('attendance_agreement')) {
                    $value = $agreement['values']['part_time_max_interruptions'];
                }
            }
            $this->db->insert('settings', ['name' => $name, 'value' => (string) $value]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'attendance_part_time_max_interruptions']);
        if ($this->db->field_exists('hash', 'attendance_journal')) {
            $this->dbforge->drop_column('attendance_journal', 'hash');
        }
        if ($this->db->field_exists('employment', 'users')) {
            $this->dbforge->drop_column('users', 'employment');
        }
    }
}
