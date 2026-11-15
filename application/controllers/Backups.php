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
 * Backups controller.
 *
 * Settings › Backups: download a copy of the whole database, the daily ones included, and restore one (uploaded or from
 * the list). Only for administrators, because a copy holds every patient and clinical record.
 *
 * @package Controllers
 */
class Backups extends EA_Controller
{
    /**
     * Backups constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('database_backup');
        $this->load->library('accounts');
    }

    /**
     * Render the page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('backups')]);

        $user_id = session('user_id');

        if (!$this->allowed()) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
        ]);

        html_vars([
            'page_title' => lang('backups'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'backups' => $this->database_backup->all(),
            'max_upload_bytes' => self::max_upload_bytes(),
        ]);

        $this->load->view('pages/backups');
    }

    /**
     * Take a copy of the database now. The page downloads it right after.
     */
    public function create(): void
    {
        try {
            method('post');

            $this->check_allowed();

            set_time_limit(0);

            $name = basename($this->database_backup->create());

            audit_log('backup', 'database', null, ['file' => $name]);

            json_response([
                'success' => true,
                'name' => $name,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Download a copy of the backups folder.
     *
     * @param string $name
     */
    public function download(string $name = ''): void
    {
        method('get');

        if (!$this->allowed()) {
            abort(403, 'Forbidden');
        }

        $path = $this->database_backup->path($name);

        if (!$path) {
            abort(404, 'Not Found');
        }

        audit_log('download', 'database', null, ['file' => $name]);

        // Straight from the file, so a big copy does not have to fit in memory.
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        readfile($path);

        exit();
    }

    /**
     * Replace the whole database with an uploaded file (backup_file) or a copy of the list (name).
     */
    public function restore(): void
    {
        try {
            method('post');

            $this->check_allowed();

            set_time_limit(0);

            $name = (string) request('name', '');

            if ($name !== '') {
                $path = $this->database_backup->path($name);

                if (!$path) {
                    throw new InvalidArgumentException(lang('backup_not_found'));
                }
            } else {
                $path = $this->uploaded_file();
                $name = (string) $_FILES['backup_file']['name'];
            }

            $user_id = (int) session('user_id');

            try {
                $previous = $this->database_backup->restore($path);
            } catch (InvalidArgumentException $e) {
                log_message('error', 'Backup not restored: ' . $e->getMessage());

                throw new InvalidArgumentException(lang('backup_invalid_file'), 0, $e);
            } catch (RuntimeException $e) {
                log_message('error', 'Backup not restored: ' . $e->getMessage());

                // The exact error of MySQL is in the log; the page says in what state the database is.
                $message = match ($e->getCode()) {
                    Database_backup::RESTORE_FAILED => lang('backup_restore_failed'),
                    Database_backup::ROLLBACK_FAILED => lang('backup_rollback_failed'),
                    Database_backup::MIGRATION_FAILED => lang('backup_migration_failed'),
                    default => lang('backup_nothing_changed'),
                };

                throw new RuntimeException($message, 0, $e);
            }

            audit_log('restore', 'database', null, ['file' => $name, 'previous' => basename($previous)]);

            // The administrator may not exist in the restored database, or have another role there.
            $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
            $role = $user ? $this->db->get_where('roles', ['id' => $user['id_roles']])->row_array() : null;
            $logged_out = ($role['slug'] ?? null) !== DB_SLUG_ADMIN;

            if ($logged_out) {
                $this->session->sess_destroy();
            }

            json_response([
                'success' => true,
                'previous' => basename($previous),
                'logged_out' => $logged_out,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Path of the file uploaded with the request, after checking it arrived whole.
     */
    private function uploaded_file(): string
    {
        $file = $_FILES['backup_file'] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException(lang('backup_choose_file'));
        }

        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new InvalidArgumentException(lang('backup_file_too_big'));
        }

        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('The file could not be uploaded (error ' . $file['error'] . ').');
        }

        return $file['tmp_name'];
    }

    /**
     * Whether the user of the session is an administrator.
     */
    private function allowed(): bool
    {
        return session('role_slug') === DB_SLUG_ADMIN && can('edit', PRIV_SYSTEM_SETTINGS);
    }

    private function check_allowed(): void
    {
        if (!$this->allowed()) {
            throw new RuntimeException('You do not have the required permissions for this task.');
        }
    }

    /**
     * Biggest file PHP accepts in an upload, in bytes (the smaller of upload_max_filesize and post_max_size).
     */
    private static function max_upload_bytes(): int
    {
        $limits = array_filter(
            array_map(fn($setting) => self::bytes((string) ini_get($setting)), [
                'upload_max_filesize',
                'post_max_size',
            ]),
        );

        return $limits ? min($limits) : 0;
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
