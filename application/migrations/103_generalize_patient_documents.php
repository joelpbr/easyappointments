<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * The saved documents no longer assume the two languages of the first clinic, nor are they saved twice:
 *
 * - titles: the title in every language of the document pack, as JSON ({"catalan": ..., "spanish": ...}), instead of
 *   one column per language.
 * - fingerprint: what was printed (Visit_documents::fingerprint()), so that asking again for the same document of
 *   a visit opens the saved one instead of adding a copy.
 */
class Migration_Generalize_patient_documents extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $table = $this->db->dbprefix('patient_documents');

        if (!$this->db->field_exists('titles', 'patient_documents')) {
            $this->db->query("ALTER TABLE `$table` ADD COLUMN titles TEXT NULL AFTER document_type");
        }

        if ($this->db->field_exists('title_ca', 'patient_documents')) {
            $this->db->query("UPDATE `$table` SET titles = JSON_OBJECT('catalan', title_ca, 'spanish', title_es)");
            $this->db->query("ALTER TABLE `$table` DROP COLUMN title_ca, DROP COLUMN title_es");
        }

        if (!$this->db->field_exists('fingerprint', 'patient_documents')) {
            $this->db->query(
                "ALTER TABLE `$table` ADD COLUMN fingerprint CHAR(64) NULL AFTER created_by_name, " .
                    'ADD KEY patient_documents_identical (id_appointments, fingerprint)',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $table = $this->db->dbprefix('patient_documents');

        if ($this->db->field_exists('fingerprint', 'patient_documents')) {
            $this->db->query("ALTER TABLE `$table` DROP KEY patient_documents_identical, DROP COLUMN fingerprint");
        }

        if (!$this->db->field_exists('title_ca', 'patient_documents')) {
            $this->db->query(
                "ALTER TABLE `$table` ADD COLUMN title_ca VARCHAR(256) NOT NULL DEFAULT '' AFTER document_type, " .
                    "ADD COLUMN title_es VARCHAR(256) NOT NULL DEFAULT '' AFTER title_ca",
            );
            $this->db->query(
                "UPDATE `$table` SET title_ca = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(titles, '$.catalan')), ''), " .
                    "title_es = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(titles, '$.spanish')), '')",
            );
        }

        if ($this->db->field_exists('titles', 'patient_documents')) {
            $this->db->query("ALTER TABLE `$table` DROP COLUMN titles");
        }
    }
}
