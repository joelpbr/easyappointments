<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_provider_short_name extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->field_exists('short_name', 'users')) {
            $this->dbforge->add_column('users', [
                'short_name' => ['type' => 'VARCHAR', 'constraint' => '64', 'null' => true, 'after' => 'last_name'],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->field_exists('short_name', 'users')) {
            $this->dbforge->drop_column('users', 'short_name');
        }
    }
}
