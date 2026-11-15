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
 * WhatsApp webhook controller.
 *
 * The address registered in the app of Meta (Callback URL), which tells what happens to the reminders: sent,
 * delivered, read or failed (see Whatsapp_reminders::handle_webhook()). It is public, as Meta calls it, and without
 * CSRF token (config.php): GET only answers the check of Meta when it has the verify token of the settings, and POST
 * only takes the requests signed with the secret of the app.
 *
 * @package Controllers
 */
class Whatsapp_webhook extends EA_Controller
{
    /**
     * Whatsapp_webhook constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('whatsapp_reminders');
    }

    /**
     * Answer the check of Meta (GET) or take the statuses it sends (POST).
     */
    public function index(): void
    {
        if ($this->input->method() === 'get') {
            $this->verify();

            return;
        }

        $body = (string) file_get_contents('php://input');

        if (
            !Whatsapp_reminders::valid_signature(
                $body,
                $this->input->get_request_header('X-Hub-Signature-256'),
                (string) setting('whatsapp_app_secret'),
            )
        ) {
            $this->output->set_status_header(403)->set_output('Forbidden');

            return;
        }

        $payload = json_decode($body, true);

        if (is_array($payload)) {
            $this->whatsapp_reminders->handle_webhook($payload);
        }

        // Meta only needs to know that it arrived.
        $this->output->set_status_header(200)->set_output('OK');
    }

    /**
     * Answer the check that Meta makes when the webhook is registered: the challenge back when the token matches.
     */
    private function verify(): void
    {
        $token = (string) setting('whatsapp_webhook_verify_token');

        if (
            $this->input->get('hub_mode') === 'subscribe' &&
            $token !== '' &&
            hash_equals($token, (string) $this->input->get('hub_verify_token'))
        ) {
            $this->output->set_content_type('text/plain')->set_output((string) $this->input->get('hub_challenge'));

            return;
        }

        $this->output->set_status_header(403)->set_output('Forbidden');
    }
}
