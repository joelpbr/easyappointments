<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_webhook_active_state extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->field_exists('is_active', 'webhooks')) {
            $this->dbforge->add_column('webhooks', [
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->field_exists('is_active', 'webhooks')) {
            $this->dbforge->drop_column('webhooks', 'is_active');
        }
    }
}
