<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * Onicolisis gets its own icon instead of the one it shared. Only while it still has the shared one, so an icon the
 * clinic chose afterwards is never replaced.
 */
class Migration_Assign_onicolysis_icon extends EA_Migration
{
    private const SHARED = '45_rectangulo_dos_esquinas.svg';

    private const OWN = '52_onicolisis.svg';

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('podology_pathologies')) {
            return;
        }

        $this->db->update(
            'podology_pathologies',
            ['image_filename' => self::OWN],
            ['name' => 'Onicolisis', 'image_filename' => self::SHARED],
        );
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if (!$this->db->table_exists('podology_pathologies')) {
            return;
        }

        $this->db->update(
            'podology_pathologies',
            ['image_filename' => self::SHARED],
            ['name' => 'Onicolisis', 'image_filename' => self::OWN],
        );
    }
}
