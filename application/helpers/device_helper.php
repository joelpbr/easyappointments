<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

if (!function_exists('is_phone_user_agent')) {
    /**
     * Whether a browser is a phone, so that the calendar can open its simple version (see the Simple controller).
     *
     * Tablets are not phones: they have room for the full calendar. Android tablets leave "Mobile" out of their user
     * agent, and iPads either say "iPad" or present themselves as a Mac.
     *
     * @param string|null $user_agent User agent of the browser.
     *
     * @return bool
     */
    function is_phone_user_agent(?string $user_agent): bool
    {
        $user_agent = (string) $user_agent;

        if ($user_agent === '' || preg_match('/iPad|Tablet/i', $user_agent)) {
            return false;
        }

        return (bool) preg_match(
            '/iPhone|iPod|Android.*Mobile|Windows Phone|Mobile.*Firefox|BlackBerry|Opera Mini/i',
            $user_agent,
        );
    }
}
