<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Public clinic logo, also used by browser icons and link previews. */
class Branding extends EA_Controller
{
    /** Public home-screen metadata, using the configured origin for each installation. */
    public function manifest(): void
    {
        method('get');
        $manifest = [
            'id' => base_url(),
            'name' => branding_name(),
            'short_name' => branding_name(),
            'start_url' => base_url(),
            'scope' => base_url(),
            'display' => 'standalone',
            'theme_color' => branding_color(),
            'background_color' => '#ffffff',
            'icons' => [
                ['src' => branding_logo(), 'purpose' => 'any'],
            ],
        ];
        $this->output
            ->set_content_type('application/manifest+json')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_header('Cache-Control: no-cache')
            ->set_output(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

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
