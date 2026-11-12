<?php
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
 * Docker configuration file.
 *
 * Every value is read from the container environment, which docker-compose.yml fills from the .env file.
 */

define('DOCKER_BASE_URL', (string) getenv('BASE_URL'));
define('DOCKER_LANGUAGE', getenv('LANGUAGE') ?: 'english');
define('DOCKER_DEBUG_MODE', filter_var(getenv('DEBUG_MODE'), FILTER_VALIDATE_BOOLEAN));
define('DOCKER_DB_HOST', (string) getenv('DB_HOST'));
define('DOCKER_DB_NAME', (string) getenv('DB_NAME'));
define('DOCKER_DB_USERNAME', (string) getenv('DB_USERNAME'));
define('DOCKER_DB_PASSWORD', (string) getenv('DB_PASSWORD'));

class Config
{
    // ------------------------------------------------------------------------
    // GENERAL SETTINGS
    // ------------------------------------------------------------------------

    const BASE_URL = DOCKER_BASE_URL;
    const LANGUAGE = DOCKER_LANGUAGE;
    const DEBUG_MODE = DOCKER_DEBUG_MODE;

    // ------------------------------------------------------------------------
    // DATABASE SETTINGS
    // ------------------------------------------------------------------------

    const DB_HOST = DOCKER_DB_HOST;
    const DB_NAME = DOCKER_DB_NAME;
    const DB_USERNAME = DOCKER_DB_USERNAME;
    const DB_PASSWORD = DOCKER_DB_PASSWORD;
}
