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

        return array_map(fn(array $marker): array => $this->marker_resource($marker), $markers);
    }

    /**
     * Place a pathology on a diagram of a visit.
     *
     * @param int $appointment_id Appointment ID.
     * @param array $marker With diagram_id, pathology_id and the coordinates x1, y1, x2, y2 on the original picture.
     *
     * @return array The saved marker.
     *
     * @throws InvalidArgumentException When the diagram, the pathology or a coordinate is not valid.
     */
    public function add_marker(int $appointment_id, array $marker): array
    {
        $diagram_id = filter_var($marker['diagram_id'] ?? null, FILTER_VALIDATE_INT);
        $pathology_id = filter_var($marker['pathology_id'] ?? null, FILTER_VALIDATE_INT);

        if (
            $diagram_id === false ||
            !$this->db->get_where('podology_diagram_types', ['id' => $diagram_id])->num_rows()
        ) {
            throw new InvalidArgumentException('The provided foot diagram does not exist.');
        }

        if (
            $pathology_id === false ||
            !$this->db->get_where('podology_pathologies', ['id' => $pathology_id])->num_rows()
        ) {
            throw new InvalidArgumentException('The provided pathology does not exist.');
        }

        // The coordinates are kept exactly as given: whole numbers, possibly negative, never scaled nor rounded.
        $coordinates = [];

        foreach (['x1', 'y1', 'x2', 'y2'] as $field) {
            $value = filter_var($marker[$field] ?? null, FILTER_VALIDATE_INT);

            if ($value === false) {
                throw new InvalidArgumentException('The provided coordinate is not a whole number: ' . $field);
            }

            $coordinates[$field] = $value;
        }

        $this->db->insert(
            'appointment_foot_markers',
            [
                'create_datetime' => date('Y-m-d H:i:s'),
                'update_datetime' => date('Y-m-d H:i:s'),
                'id_appointments' => $appointment_id,
                'id_podology_diagram_types' => $diagram_id,
                'id_podology_pathologies' => $pathology_id,
            ] + $coordinates,
        );

        $marker_id = (int) $this->db->insert_id();

        audit_log('created', 'appointment_foot_marker', $marker_id);

        return $this->marker_resource(
            $this->db->get_where('appointment_foot_markers', ['id' => $marker_id])->row_array(),
        );
    }

    /**
     * Remove a pathology placed on a diagram of a visit.
     *
     * @param int $appointment_id Appointment ID, so that a marker of another visit is never removed.
     * @param int $marker_id Marker ID.
     *
     * @throws InvalidArgumentException When the marker does not belong to the visit.
     */
    public function delete_marker(int $appointment_id, int $marker_id): void
    {
        $where = ['id' => $marker_id, 'id_appointments' => $appointment_id];

        if (!$this->db->get_where('appointment_foot_markers', $where)->num_rows()) {
            throw new InvalidArgumentException('The provided marker does not belong to the visit.');
        }

        $this->db->delete('appointment_foot_markers', $where);

        audit_log('deleted', 'appointment_foot_marker', $marker_id);
    }

    /**
     * Get the resource of a marker for the record of the visit.
     *
     * @param array $marker Row of appointment_foot_markers.
     *
     * @return array
     */
    private function marker_resource(array $marker): array
    {
        return [
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
        ];
    }
}
