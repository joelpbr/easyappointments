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
 * Foot diagrams model.
 *
 * Reads the four global foot diagrams, the catalog of pathologies and the pathologies placed on the diagrams of a
 * visit (see the create_foot_diagrams migration). The coordinates are returned exactly as saved.
 *
 * @package Models
 */
class Foot_diagrams_model extends EA_Model
{
    /**
     * Get the four diagrams, with the address of their picture.
     *
     * @return array List of ['id', 'name', 'image_url'], by id.
     */
    public function get_diagram_types(): array
    {
        $diagrams = $this->db->order_by('id')->get('podology_diagram_types')->result_array();

        return array_map(
            fn(array $diagram): array => [
                'id' => (int) $diagram['id'],
                'name' => $diagram['name'],
                'image_url' => base_url('assets/img/' . $diagram['background_image']),
            ],
            $diagrams,
        );
    }

    /**
     * Get the catalog of pathologies, by name.
     *
     * @return array List of ['id', 'name', 'image_url', 'description'].
     */
    public function get_pathologies(): array
    {
        $pathologies = $this->db->order_by('name')->get('podology_pathologies')->result_array();

        return array_map(
            fn(array $pathology): array => [
                'id' => (int) $pathology['id'],
                'name' => $pathology['name'],
                'image_url' => base_url('assets/img/podology_pathologies/' . $pathology['image_filename']),
                'description' => $pathology['description'],
            ],
            $pathologies,
        );
    }

    /**
     * Get the pathologies placed on the diagrams of a visit.
     *
     * @param int $appointment_id Appointment ID.
     *
     * @return array Markers with their exact coordinates, by diagram.
     */
    public function get_markers(int $appointment_id): array
    {
        $markers = $this->db
            ->order_by('id_podology_diagram_types, id')
            ->get_where('appointment_foot_markers', ['id_appointments' => $appointment_id])
            ->result_array();

        return array_map(
            fn(array $marker): array => [
                'id' => (int) $marker['id'],
                'diagram_id' => (int) $marker['id_podology_diagram_types'],
                'pathology_id' => (int) $marker['id_podology_pathologies'],
                'x1' => (int) $marker['x1'],
                'y1' => (int) $marker['y1'],
                'x2' => (int) $marker['x2'],
                'y2' => (int) $marker['y2'],
                'is_healed' => (bool) $marker['is_healed'],
                'annotation_title' => $marker['annotation_title'],
                'annotation_text' => $marker['annotation_text'],
            ],
            $markers,
        );
    }
}
