<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Default_table_calendar_view extends EA_Migration
{
    public function up(): void
    {
        $this->dbforge->modify_column('user_settings', [
            'calendar_view' => ['type' => 'VARCHAR', 'constraint' => '32', 'default' => 'table'],
        ]);
        $this->db->update('user_settings', ['calendar_view' => 'table']);
    }

    public function down(): void
    {
        // Restore the schema default without overwriting users' saved preferences.
        $this->dbforge->modify_column('user_settings', [
            'calendar_view' => ['type' => 'VARCHAR', 'constraint' => '32', 'default' => 'default'],
        ]);
    }
}
