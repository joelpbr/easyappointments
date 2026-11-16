<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/*
 * This file can only be used in a testing environment and only from the terminal.
 */

if (ENVIRONMENT !== 'testing' || !is_cli()) {
    show_404();
}

/**
 * Test controller.
 *
 * This controller does not have or need any logic, it is just used so that CI can be loaded properly during the test
 * execution.
 */
class Test extends EA_Controller
{
    /**
     * Placeholder callback.
     *
     * @return void
     */
    public function index(): void
    {
        //
    }
}
