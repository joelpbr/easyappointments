<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.3.0
 * ---------------------------------------------------------------------------- */

/**
 * Assets URL helper function.
 *
 * This function will create an asset file URL that includes a cache busting parameter in order
 * to invalidate the browser cache in case of an update.
 *
 * @param string $uri Relative URI (just like the one used in the base_url helper).
 * @param string|null $protocol Valid URI protocol.
 *
 * @return string Returns the final asset URL.
 */
function asset_url(string $uri = '', ?string $protocol = null): string
{
    $debug = config('debug');

    $cache_busting_token = '?' . config('cache_busting_token');

    if (str_contains(basename($uri), '.js') && !str_contains(basename($uri), '.min.js') && !$debug) {
        $uri = str_replace('.js', '.min.js', $uri);
    }

    if (str_contains(basename($uri), '.css') && !str_contains(basename($uri), '.min.css') && !$debug) {
        $uri = str_replace('.css', '.min.css', $uri);
    }

    // The fixed token only changes with a new release, so browsers kept serving old scripts and styles after an
    // update. The modification time of the file changes whenever the file does.
    $path = FCPATH . ltrim($uri, '/');

    if (is_file($path)) {
        $cache_busting_token .= '-' . filemtime($path);
    }

    return base_url($uri . $cache_busting_token, $protocol);
}
