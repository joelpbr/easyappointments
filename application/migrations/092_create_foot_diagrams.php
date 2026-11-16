<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * Foot diagrams of the visits: pathology icons placed on pictures of the feet.
 *
 * - podology_pathologies: the shared catalog of pathologies, each with its icon. Left empty: the clinic fills it.
 * - podology_diagram_types: the four global diagrams (ids 0 to 3), each with its background picture. They are shared by
 *   every visit, so a visit has its four diagrams without any row of its own.
 * - appointment_foot_markers: each pathology placed on a diagram of a visit, with its exact coordinates on the original
 *   picture (signed integers, kept as given: never scaled, normalized nor rounded). The same pathology can be placed
 *   several times on the same diagram, and the markers of different visits never mix.
 *
 * Patient -> appointments -> markers; deleting a visit deletes its markers, while a pathology or a diagram in use
 * cannot be deleted.
 */
class Migration_Create_foot_diagrams extends EA_Migration
{
    /**
     * The four diagrams and their pictures (assets/img/fotos_pies). Their anatomical views are not named here.
     */
    private const DIAGRAMS = [
        0 => 'fotos_pies/1.png',
        1 => 'fotos_pies/2.png',
        2 => 'fotos_pies/3.png',
        3 => 'fotos_pies/4.png',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('podology_pathologies')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => '11', 'auto_increment' => true],
                'create_datetime' => ['type' => 'DATETIME', 'null' => true],
                'update_datetime' => ['type' => 'DATETIME', 'null' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => '256'],
                'image_filename' => ['type' => 'VARCHAR', 'constraint' => '256'],
                'description' => ['type' => 'TEXT', 'null' => true],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->create_table('podology_pathologies', true, ['engine' => 'InnoDB']);

            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('podology_pathologies') .
                    '` ADD UNIQUE KEY `podology_pathologies_name` (`name`)',
            );
        }

        if (!$this->db->table_exists('podology_diagram_types')) {
            // The ids are the diagram numbers 0 to 3, not generated.
            $this->db->query(
                'CREATE TABLE `' .
                    $this->db->dbprefix('podology_diagram_types') .
                    '` (
                    `id` TINYINT UNSIGNED NOT NULL,
                    `name` VARCHAR(128) NULL,
                    `background_image` VARCHAR(256) NOT NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `podology_diagram_types_id_range` CHECK (`id` BETWEEN 0 AND 3)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            );

            foreach (self::DIAGRAMS as $id => $background_image) {
                $this->db->insert('podology_diagram_types', ['id' => $id, 'background_image' => $background_image]);
            }
        }

        if (!$this->db->table_exists('appointment_foot_markers')) {
            $markers = $this->db->dbprefix('appointment_foot_markers');

            $this->db->query(
                "CREATE TABLE `$markers` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `create_datetime` DATETIME NULL,
                    `update_datetime` DATETIME NULL,
                    `id_appointments` INT NOT NULL,
                    `id_podology_diagram_types` TINYINT UNSIGNED NOT NULL,
                    `id_podology_pathologies` INT NOT NULL,
                    `x1` INT NOT NULL,
                    `y1` INT NOT NULL,
                    `x2` INT NOT NULL,
                    `y2` INT NOT NULL,
                    `is_healed` TINYINT(1) NOT NULL DEFAULT 0,
                    `annotation_title` VARCHAR(256) NULL,
                    `annotation_text` TEXT NULL,
                    `original_external_id` VARCHAR(128) NULL,
                    PRIMARY KEY (`id`),
                    KEY `appointment_foot_markers_diagram` (`id_appointments`, `id_podology_diagram_types`),
                    KEY `appointment_foot_markers_type` (`id_podology_diagram_types`),
                    KEY `appointment_foot_markers_pathology` (`id_podology_pathologies`),
                    UNIQUE KEY `appointment_foot_markers_external_id` (`original_external_id`),
                    CONSTRAINT `appointment_foot_markers_appointment` FOREIGN KEY (`id_appointments`) REFERENCES `" .
                    $this->db->dbprefix('appointments') .
                    "` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `appointment_foot_markers_diagram_type` FOREIGN KEY (`id_podology_diagram_types`) REFERENCES `" .
                    $this->db->dbprefix('podology_diagram_types') .
                    "` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT `appointment_foot_markers_pathology` FOREIGN KEY (`id_podology_pathologies`) REFERENCES `" .
                    $this->db->dbprefix('podology_pathologies') .
                    "` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->dbforge->drop_table('appointment_foot_markers', true);
        $this->dbforge->drop_table('podology_diagram_types', true);
        $this->dbforge->drop_table('podology_pathologies', true);
    }
}
