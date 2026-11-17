<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * User controller.
 *
 * Handles the user related operations.
 *
 * @package Controllers
 */
class User extends EA_Controller
{
    /**
     * User constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('accounts');
        $this->load->library('email_messages');
    }

    /**
     * Redirect to the login page.
     */
    /** Read-only wake-up probe: never redirects or renews a session as a page navigation. */
    public function session_status(): void
    {
        method('get');
        $user_id = (int) session('user_id');
        json_response([
            'authenticated' => $user_id > 0,
            'user_id' => $user_id,
            'csrf_token' => $this->security->get_csrf_hash(),
        ], $user_id ? 200 : 401, ['Cache-Control: no-store, private']);
    }

    /** Redirect to the login page. */
    public function index(): void
    {
        redirect('login');
    }

    /**
     * Display the login page.
     *
     * @deprecated Since 1.5 Use the Login controller instead.
     */
    public function login(): void
    {
        redirect('login');
    }

    /**
     * Display the logout page.
     *
     * @deprecated Since 1.5 Use the Logout controller instead.
     */
    public function logout(): void
    {
        redirect('logout');
    }

    /**
     * Display the password recovery page.
     *
     * @deprecated Since 1.5 Use the Logout controller instead.
     */
    public function forgot_password(): void
    {
        redirect('recovery');
    }
}
