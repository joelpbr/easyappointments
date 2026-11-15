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
 * WhatsApp messages controller.
 *
 * The page that the button of the notifications (header) opens: every WhatsApp message, reminders and tests, with the
 * ones that could not be sent marked. Each one can be sent again, marked as sent by hand, cancelled or removed.
 *
 * @package Controllers
 */
class Whatsapp_messages extends EA_Controller
{
    /**
     * Whatsapp_messages constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('whatsapp_reminders');
        $this->load->library('accounts');
    }

    /**
     * Render the list.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('whatsapp_messages')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_APPOINTMENTS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $only_problems = (bool) request('problems');

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
        ]);

        html_vars([
            'page_title' => lang('whatsapp_messages'),
            'active_menu' => PRIV_APPOINTMENTS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'messages' => $this->whatsapp_reminders->messages($only_problems),
            'only_problems' => $only_problems,
            'problem_count' => $this->whatsapp_reminders->problem_count(),
            'can_edit' => can('edit', PRIV_APPOINTMENTS),
        ]);

        $this->load->view('pages/whatsapp_messages');
    }

    /**
     * Send a message again. A failure is not an error of the request: the page offers what to do with it.
     */
    public function retry(): void
    {
        $this->act(function (int $id): array {
            $result = $this->whatsapp_reminders->retry($id);

            // The explanation, and what Meta answered exactly when it is a known error.
            return [
                'ok' => $result['ok'],
                'error' => Whatsapp_reminders::describe_error($result['error']),
                'detail' => Whatsapp_reminders::error_key($result['error']) ? $result['error'] : null,
            ];
        });
    }

    /**
     * Mark a message as sent by other means.
     */
    public function mark_sent(): void
    {
        $this->act(fn(int $id) => $this->whatsapp_reminders->mark_sent($id));
    }

    /**
     * Cancel a message.
     */
    public function cancel(): void
    {
        $this->act(fn(int $id) => $this->whatsapp_reminders->cancel($id));
    }

    /**
     * Remove a message from the list.
     */
    public function destroy(): void
    {
        $this->act(fn(int $id) => $this->whatsapp_reminders->delete($id));
    }

    /**
     * Do something with the message of the request, for those who can change appointments.
     *
     * @param callable $action Called with the id of the message; what it returns goes with the response.
     */
    private function act(callable $action): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('id', 'numeric');

            $result = $action((int) request('id'));

            json_response(
                ['success' => true, 'problem_count' => $this->whatsapp_reminders->problem_count()] +
                    (is_array($result) ? $result : []),
            );
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
