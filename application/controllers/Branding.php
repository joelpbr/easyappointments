<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Public clinic logo, also used by browser icons and link previews. */
class Branding extends EA_Controller
{
    public function logo(): void
    {
        method('get');
        $logo = (string) setting('company_logo', '');
        if ($logo === '' || !branding_valid_logo($logo)) {
            redirect(base_url('assets/img/logo-192.png'));
            return;
        }
        [$metadata, $encoded] = explode(',', $logo, 2);
        $mime = substr($metadata, 5, strpos($metadata, ';') - 5);
        $this->output
            ->set_content_type($mime)
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_header('Cache-Control: public, max-age=86400')
            ->set_output(base64_decode($encoded, true));
    }
}
