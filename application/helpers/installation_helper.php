<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.1.0
 * ---------------------------------------------------------------------------- */

/**
 * Check if ProgramaConsulta is installed.
 *
 * This function will check some factors to determine if ProgramaConsulta is installed or not. It is possible that the
 * installation is properly configure without being recognized by this method.
 *
 * Notice: You can add more checks into this file in order to further check the installation state of the application.
 *
 * @return bool Returns whether the application is installed or not.
 */
function is_app_installed(): bool
{
    $CI = &get_instance();

    return $CI->db->table_exists('users');
}
