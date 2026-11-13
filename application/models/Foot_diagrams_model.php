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
     * Save the pathologies placed on the diagrams of a visit: the given list is the whole list of the visit, so the
     * markers it keeps (with their id) stay as they are, the new ones (without id) are added and the others are removed.
     *
     * Every new marker is checked before anything is saved.
     *
     * @param int $appointment_id Appointment ID.
     * @param array $markers Markers with id (kept) or with diagram_id, pathology_id, x1, y1, x2 and y2 (new).
     *
     * @throws InvalidArgumentException When a new marker is not valid.
     */
    public function sync_markers(int $appointment_id, array $markers): void
    {
        $current = array_map(
            'intval',
            array_column(
                $this->db
                    ->select('id')
                    ->get_where('appointment_foot_markers', ['id_appointments' => $appointment_id])
                    ->result_array(),
                'id',
            ),
        );

        $kept = [];
        $new = [];

        foreach ($markers as $marker) {
            $id = (int) ($marker['id'] ?? 0);

            if ($id) {
                // Only the markers of this visit can be kept.
                if (in_array($id, $current, true)) {
                    $kept[] = $id;
                }

                continue;
            }

            $new[] = $this->clean_marker(is_array($marker) ? $marker : []);
        }

        foreach (array_diff($current, $kept) as $marker_id) {
            $this->db->delete('appointment_foot_markers', ['id' => $marker_id]);
            audit_log('deleted', 'appointment_foot_marker', $marker_id);
        }

        foreach ($new as $marker) {
            $this->db->insert(
                'appointment_foot_markers',
                [
                    'create_datetime' => date('Y-m-d H:i:s'),
                    'update_datetime' => date('Y-m-d H:i:s'),
                    'id_appointments' => $appointment_id,
                ] + $marker,
            );

            audit_log('created', 'appointment_foot_marker', (int) $this->db->insert_id());
        }
    }

    /**
     * Check a new marker.
     *
     * @param array $marker With diagram_id, pathology_id and the coordinates x1, y1, x2, y2 on the original picture.
     *
     * @return array The columns of the marker.
     *
     * @throws InvalidArgumentException When the diagram, the pathology or a coordinate is not valid.
     */
    private function clean_marker(array $marker): array
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
        $columns = ['id_podology_diagram_types' => $diagram_id, 'id_podology_pathologies' => $pathology_id];

        foreach (['x1', 'y1', 'x2', 'y2'] as $field) {
            $value = filter_var($marker[$field] ?? null, FILTER_VALIDATE_INT);

            if ($value === false) {
                throw new InvalidArgumentException('The provided coordinate is not a whole number: ' . $field);
            }

            $columns[$field] = $value;
        }

        return $columns;
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
