<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Remove old stored language choices and make Catalan the default for every existing user. */
class Migration_Keep_catalan_and_spanish extends EA_Migration
{
    public function up(): void
    {
        $this->db->trans_start();
        $this->db->update('settings', ['value' => 'catalan'], ['name' => 'default_language']);
        $this->db
            ->group_start()
            ->where('language !=', 'catalan')
            ->or_where('language', null)
            ->group_end()
            ->update('users', ['language' => 'catalan']);
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            throw new RuntimeException('Could not set Catalan language preferences.');
        }
        $table = $this->db->dbprefix('users');
        $this->db->query("ALTER TABLE `{$table}` ALTER COLUMN `language` SET DEFAULT 'catalan'");
    }

    public function down(): void
    {
        // The removed translation resources are not restored by rolling back a database version.
        // Keep Catalan and never invent the users' previous preferences.
    }
}
