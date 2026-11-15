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
 * Pathologies model.
 *
 * The catalog of pathologies placed on the foot diagrams (podology_pathologies), edited on the pathologies page.
 *
 * The icons of the catalog that came with the application are in assets/img/podology_pathologies. The ones uploaded
 * from the page are kept in storage/uploads/podology_pathologies, which survives the updates of the application, and
 * their image_filename starts with "uploads/"; they are served by the pathologies controller.
 *
 * @package Models
 */
class Pathologies_model extends EA_Model
{
    /**
     * Start of the image_filename of an uploaded icon.
     */
    public const UPLOAD_PREFIX = 'uploads/';

    /**
     * Largest icon that can be uploaded, in bytes.
     */
    public const MAX_IMAGE_BYTES = 1048576;

    /**
     * Picture types accepted for an icon, with the extension they are saved with.
     */
    public const IMAGE_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * Get the whole catalog by usage descending, then name, with the number of saved markers for each pathology.
     *
     * @return array List of ['id', 'name', 'description', 'image_url', 'marker_count'].
     */
    public function get_all(): array
    {
        $counts = array_column(
            $this->db
                ->select('id_podology_pathologies, COUNT(*) AS total')
                ->group_by('id_podology_pathologies')
                ->get('appointment_foot_markers')
                ->result_array(),
            'total',
            'id_podology_pathologies',
        );

        $pathologies = $this->db->order_by('name')->get('podology_pathologies')->result_array();

        $catalog = array_map(
            fn(array $pathology): array => $this->resource($pathology) + [
                'marker_count' => (int) ($counts[$pathology['id']] ?? 0),
            ],
            $pathologies,
        );

        // Equal counts keep the alphabetical order from the database (PHP's sort is stable).
        usort($catalog, static fn(array $a, array $b): int => $b['marker_count'] <=> $a['marker_count']);

        return $catalog;
    }

    /**
     * Get a pathology of the catalog.
     *
     * @param int $pathology_id
     *
     * @return array Row of podology_pathologies.
     *
     * @throws InvalidArgumentException When it does not exist.
     */
    public function find_row(int $pathology_id): array
    {
        $pathology = $this->db->get_where('podology_pathologies', ['id' => $pathology_id])->row_array();

        if (!$pathology) {
            throw new InvalidArgumentException('The provided pathology does not exist.');
        }

        return $pathology;
    }

    /**
     * Add a pathology to the catalog or change one.
     *
     * @param array $pathology With name and description, the id of the one changed, and the icon as a data URL
     * ("data:image/png;base64,..."): required for a new pathology, optional when changing one.
     *
     * @return int ID of the pathology.
     *
     * @throws InvalidArgumentException When the name is missing or repeated, or the icon is not valid.
     */
    public function save(array $pathology): int
    {
        $pathology_id = (int) ($pathology['id'] ?? 0);
        $current = $pathology_id ? $this->find_row($pathology_id) : null;

        $name = mb_substr(trim((string) ($pathology['name'] ?? '')), 0, 256);
        $description = trim((string) ($pathology['description'] ?? '')) ?: null;
        $image = trim((string) ($pathology['image'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException(lang('fields_are_required'));
        }

        $same_name = $this->db
            ->where('LOWER(name)', mb_strtolower($name))
            ->where('id !=', $pathology_id)
            ->get('podology_pathologies')
            ->num_rows();

        if ($same_name) {
            throw new InvalidArgumentException(lang('pathology_name_taken'));
        }

        if ($image === '' && !$current) {
            throw new InvalidArgumentException(lang('pathology_image_required'));
        }

        // The new icon is checked and saved before the row, so that a wrong picture changes nothing.
        $image_filename = $image !== '' ? $this->store_image($image) : $current['image_filename'];

        $row = [
            'update_datetime' => date('Y-m-d H:i:s'),
            'name' => $name,
            'description' => $description,
            'image_filename' => $image_filename,
        ];

        if ($current) {
            $this->db->update('podology_pathologies', $row, ['id' => $pathology_id]);
            audit_log('updated', 'podology_pathology', $pathology_id);

            if ($image_filename !== $current['image_filename']) {
                $this->delete_image($current['image_filename']);
            }

            return $pathology_id;
        }

        $this->db->insert('podology_pathologies', ['create_datetime' => date('Y-m-d H:i:s')] + $row);
        $pathology_id = (int) $this->db->insert_id();
        audit_log('created', 'podology_pathology', $pathology_id);

        return $pathology_id;
    }

    /**
     * Remove a pathology from the catalog. One that is placed on the diagram of some visit cannot be removed, as the
     * visit would lose it.
     *
     * @param int $pathology_id
     *
     * @throws InvalidArgumentException When it does not exist or it is in use.
     */
    public function delete(int $pathology_id): void
    {
        $pathology = $this->find_row($pathology_id);

        $in_use = $this->db
            ->where('id_podology_pathologies', $pathology_id)
            ->count_all_results('appointment_foot_markers');

        if ($in_use) {
            throw new InvalidArgumentException(str_replace('%s', (string) $in_use, lang('pathology_in_use')));
        }

        $this->db->delete('podology_pathologies', ['id' => $pathology_id]);
        audit_log('deleted', 'podology_pathology', $pathology_id);

        $this->delete_image($pathology['image_filename']);
    }

    /**
     * Get the address of the icon of a pathology.
     *
     * @param array $pathology Row of podology_pathologies.
     *
     * @return string
     */
    public static function image_url(array $pathology): string
    {
        if (str_starts_with((string) $pathology['image_filename'], self::UPLOAD_PREFIX)) {
            // The name of the file changes with every new icon, so the address does too.
            return site_url(
                'pathologies/image/' .
                    (int) $pathology['id'] .
                    '?v=' .
                    rawurlencode(substr($pathology['image_filename'], strlen(self::UPLOAD_PREFIX))),
            );
        }

        return base_url('assets/img/podology_pathologies/' . $pathology['image_filename']);
    }

    /**
     * Get the file of an uploaded icon.
     *
     * @param array $pathology Row of podology_pathologies.
     *
     * @return string|null Path of the file, or null when the icon is not an uploaded one or the file is missing.
     */
    public function uploaded_image_path(array $pathology): ?string
    {
        $filename = (string) $pathology['image_filename'];

        if (!str_starts_with($filename, self::UPLOAD_PREFIX)) {
            return null;
        }

        $path = $this->upload_folder() . basename(substr($filename, strlen(self::UPLOAD_PREFIX)));

        return is_file($path) ? $path : null;
    }

    /**
     * Check a data URL with a picture, as sent by the pathologies page.
     *
     * @param string $data_url "data:image/png;base64,...".
     *
     * @return array ['bytes' => contents of the picture, 'extension' => extension to save it with].
     *
     * @throws InvalidArgumentException When it is not a picture of an accepted type and size.
     */
    public static function decode_image(string $data_url): array
    {
        if (!preg_match('#^data:(image/[a-z+]+);base64,(.+)$#s', $data_url, $matches)) {
            throw new InvalidArgumentException(lang('pathology_image_invalid'));
        }

        $bytes = base64_decode($matches[2], true);

        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw new InvalidArgumentException(lang('pathology_image_invalid'));
        }

        // The type is the one of the contents, never the one the browser announced.
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? null;

        if (!$mime || !isset(self::IMAGE_TYPES[$mime])) {
            throw new InvalidArgumentException(lang('pathology_image_invalid'));
        }

        return ['bytes' => $bytes, 'extension' => self::IMAGE_TYPES[$mime]];
    }

    /**
     * Save an uploaded icon.
     *
     * @param string $data_url
     *
     * @return string Its image_filename.
     */
    private function store_image(string $data_url): string
    {
        $image = self::decode_image($data_url);
        $folder = $this->upload_folder();

        if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
            throw new RuntimeException('Could not create the folder of the pathology icons.');
        }

        $name = bin2hex(random_bytes(12)) . '.' . $image['extension'];

        if (file_put_contents($folder . $name, $image['bytes']) === false) {
            throw new RuntimeException('Could not save the pathology icon.');
        }

        return self::UPLOAD_PREFIX . $name;
    }

    /**
     * Remove an uploaded icon that is no longer used. The icons that came with the application are kept.
     *
     * @param string $image_filename
     */
    private function delete_image(string $image_filename): void
    {
        $path = $this->uploaded_image_path(['image_filename' => $image_filename]);

        if ($path) {
            @unlink($path);
        }
    }

    /**
     * Folder of the uploaded icons.
     *
     * @return string
     */
    private function upload_folder(): string
    {
        return storage_path('uploads/podology_pathologies/');
    }

    /**
     * Get the resource of a pathology for the pages.
     *
     * @param array $pathology Row of podology_pathologies.
     *
     * @return array
     */
    private function resource(array $pathology): array
    {
        return [
            'id' => (int) $pathology['id'],
            'name' => $pathology['name'],
            'description' => $pathology['description'],
            'image_url' => self::image_url($pathology),
        ];
    }
}
