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
 * Pathologies controller.
 *
 * The page of the catalog of pathologies placed on the foot diagrams of the visits (menu of the treatments): add,
 * change and remove them, with their icon. It takes the permissions of the treatments.
 *
 * @package Controllers
 */
class Pathologies extends EA_Controller
{
    /**
     * Pathologies constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('pathologies_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
    }

    /**
     * Render the pathologies page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('pathologies')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_SERVICES)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
            'pathology_max_image_bytes' => Pathologies_model::MAX_IMAGE_BYTES,
        ]);

        html_vars([
            'page_title' => lang('pathologies'),
            'active_menu' => PRIV_SERVICES,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
        ]);

        $this->load->view('pages/pathologies');
    }

    /**
     * Get the whole catalog.
     */
    public function search(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_SERVICES)) {
                abort(403, 'Forbidden');
            }

            json_response($this->pathologies_model->get_all());
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Add a pathology or change one (with its id).
     */
    public function save(): void
    {
        try {
            method('post');

            check('pathology', 'array');

            $pathology = request('pathology');
            $is_new = empty($pathology['id']);

            if (cannot($is_new ? 'add' : 'edit', PRIV_SERVICES)) {
                abort(403, 'Forbidden');
            }

            $pathology_id = $this->pathologies_model->save($pathology);

            json_response([
                'success' => true,
                'id' => $pathology_id,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Remove a pathology that is not placed on any visit.
     */
    public function destroy(): void
    {
        try {
            method('post');

            if (cannot('delete', PRIV_SERVICES)) {
                abort(403, 'Forbidden');
            }

            check('pathology_id', 'numeric');

            $this->pathologies_model->delete((int) request('pathology_id'));

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Send the icon uploaded for a pathology, to the users that are logged in.
     *
     * @param string $pathology_id
     */
    public function image(string $pathology_id = ''): void
    {
        if (!session('user_id')) {
            abort(403, 'Forbidden');
        }

        try {
            $path = $this->pathologies_model->uploaded_image_path(
                $this->pathologies_model->find_row((int) $pathology_id),
            );
        } catch (InvalidArgumentException) {
            $path = null;
        }

        if (!$path) {
            abort(404, 'Not Found');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = array_search($extension, Pathologies_model::IMAGE_TYPES, true) ?: 'application/octet-stream';

        // The address changes with every new icon (see Pathologies_model::image_url()), so it can be kept long.
        $this->output
            ->set_header('Content-Type: ' . $mime)
            ->set_header('Cache-Control: private, max-age=31536000, immutable')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output(file_get_contents($path));
    }
}
