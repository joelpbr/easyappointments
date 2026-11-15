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
 * WhatsApp reminders.
 *
 * The day before an appointment, at 10:00 in Europe/Madrid (whatever the time zone of the server, with summer and
 * winter time), the patient gets a reminder through the WhatsApp Cloud API of Meta, with an approved template of three
 * parameters: name, date and time. Nothing clinical is sent.
 *
 * run() is called by the scheduled task every few minutes (php index.php console whatsapp):
 *
 * - From 10:00 it takes the appointments of the next day that want a reminder (appointments.send_whatsapp_reminder),
 *   are not cancelled and whose patient has a phone. Until LAST_SEND_TIME it keeps trying, so a server that was off
 *   at 10:00 still sends them; later than that a reminder would arrive too late, and it is left for review instead.
 * - Every reminder is a row of whatsapp_reminders with a key unique per appointment and day, created before anything
 *   is sent, and taken by one run only (pending -> processing), so a run that repeats never sends twice. A reminder
 *   left in "processing" (the run stopped while sending) is never sent again: it shows up for review.
 * - A failed reminder is tried again on the next runs, up to MAX_ATTEMPTS times, while the window lasts.
 * - Meta tells what happens next (sent, delivered, read, failed) through the webhook (see handle_webhook()). A
 *   reminder is never taken as delivered only because the API accepted it.
 * - Every message, also the tests sent from the settings, is listed on the WhatsApp messages page, where the ones that
 *   could not be sent are warned about and can be sent again, marked as sent by hand, cancelled or removed.
 *
 * @package Libraries
 */
class Whatsapp_reminders
{
    public const TIMEZONE = 'Europe/Madrid';

    public const SEND_TIME = '10:00';

    public const LAST_SEND_TIME = '20:00';

    public const MAX_ATTEMPTS = 3;

    /**
     * Approximate price of a reminder (utility template in Spain), in euros: Meta sets it, so it only estimates the cost
     * of the month on the settings page.
     */
    public const MESSAGE_PRICE = 0.016;

    public const GRAPH_API = 'https://graph.facebook.com/v21.0/';

    /**
     * Days of the week in Catalan, from Sunday, for the date of the template.
     */
    public const WEEKDAYS = ['diumenge', 'dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres', 'dissabte'];

    /**
     * Months in Catalan, for the date of the template.
     */
    public const MONTHS = [
        'gener',
        'febrer',
        'març',
        'abril',
        'maig',
        'juny',
        'juliol',
        'agost',
        'setembre',
        'octubre',
        'novembre',
        'desembre',
    ];

    /**
     * Text of the default template, to create it in Meta (category Utility, language Catalan, with a call button):
     * {{1}} name, {{2}} date (e.g. "dilluns 16 de gener") and {{3}} time (e.g. "09:00").
     */
    public const TEMPLATE_BODY =
        "Hola {{1}},
" .
        "Tens visita demà, *{{2}}*, a les *{{3}} h*, a la Clínica Podològica.

" .
        "```· Recorda que no acceptem pagament amb targeta.
" .
        "· Aquest número de telèfon només atén missatges de WhatsApp.
" .
        "· Si necessites parlar amb nosaltres, prem el botó de trucada.```

" .
        'Gràcies 👣';

    public const STATUSES = [
        'pending',
        'processing',
        'accepted',
        'sent',
        'delivered',
        'read',
        'failed',
        'cancelled',
        'review',
        'manual',
    ];

    /**
     * Statuses of a message that could not be sent, warned about until someone deals with it.
     */
    public const PROBLEM_STATUSES = ['failed', 'review'];

    /**
     * Error codes of Meta explained in plain words on the messages page (whatsapp_error_<code>).
     */
    public const KNOWN_ERRORS = [
        '10',
        '190',
        '130429',
        '131026',
        '131042',
        '131049',
        '131056',
        '132000',
        '132001',
        '133010',
    ];

    /**
     * Order of the statuses that Meta reports, so that a late "sent" never replaces a "read".
     */
    private const PROGRESS = ['accepted' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Whatsapp_reminders constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->helper('phone');
    }

    /**
     * Whether the integration is turned on and has what it needs to send.
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        return filter_var(setting('whatsapp_enabled'), FILTER_VALIDATE_BOOLEAN) &&
            setting('whatsapp_phone_number_id') &&
            setting('whatsapp_access_token') &&
            setting('whatsapp_template_name');
    }

    /**
     * Get the day whose appointments are reminded at a moment: the next day, from SEND_TIME to LAST_SEND_TIME (local
     * time of the clinic). Outside that window, nothing is due.
     *
     * @param DateTimeInterface $now
     *
     * @return string|null Date (Y-m-d) of the appointments, or null.
     */
    public static function due_date(DateTimeInterface $now): ?string
    {
        $local = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone(self::TIMEZONE));
        $time = $local->format('H:i');

        if ($time < self::SEND_TIME || $time >= self::LAST_SEND_TIME) {
            return null;
        }

        return $local->modify('+1 day')->format('Y-m-d');
    }

    /**
     * Get the last day whose sending window is over at a moment: today from LAST_SEND_TIME on, yesterday before. The
     * reminders scheduled on that day or earlier that were not sent are no longer sent.
     *
     * @param DateTimeInterface $now
     *
     * @return string Y-m-d
     */
    public static function last_closed_day(DateTimeInterface $now): string
    {
        $local = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone(self::TIMEZONE));

        return ($local->format('H:i') >= self::LAST_SEND_TIME ? $local : $local->modify('-1 day'))->format('Y-m-d');
    }

    /**
     * Get the moment a reminder of an appointment is due: the day before, at SEND_TIME.
     *
     * @param string $appointment_start Start of the appointment (Y-m-d H:i:s, local time of the clinic).
     *
     * @return string Y-m-d H:i:s
     */
    public static function scheduled_datetime(string $appointment_start): string
    {
        return date('Y-m-d', strtotime(substr($appointment_start, 0, 10) . ' -1 day')) . ' ' . self::SEND_TIME . ':00';
    }

    /**
     * Get the key that makes the reminder of an appointment unique: one per appointment and day.
     *
     * @param int $appointment_id
     * @param string $appointment_start
     *
     * @return string
     */
    public static function idempotency_key(int $appointment_id, string $appointment_start): string
    {
        return 'reminder:' . $appointment_id . ':' . substr($appointment_start, 0, 10);
    }

    /**
     * Get the number to send to, as the API expects it: the international number without "+".
     *
     * @param string|null $phone Phone of the patient, as typed.
     *
     * @return string|null Digits with the country code, or null when the number cannot receive messages.
     */
    public static function recipient(?string $phone): ?string
    {
        $normalized = normalize_phone($phone);

        if (!$normalized || !str_starts_with($normalized, '+')) {
            return null;
        }

        $digits = substr($normalized, 1);

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    /**
     * Get the parameters of the template, in its order: name, date (e.g. "dilluns 16 de gener", "dijous 3 d'abril") and time (e.g. "09:00").
     *
     * @param array $appointment With start_datetime.
     * @param array $customer With first_name.
     *
     * @return array
     */
    public static function template_parameters(array $appointment, array $customer): array
    {
        $start = strtotime($appointment['start_datetime']);
        $month = self::MONTHS[(int) date('n', $start) - 1];

        return [
            trim((string) ($customer['first_name'] ?? '')) ?: '-',
            self::WEEKDAYS[(int) date('w', $start)] .
            ' ' .
            date('j', $start) .
            ' ' .
            (in_array($month[0], ['a', 'o'], true) ? "d'" : 'de ') .
            $month,
            date('H:i', $start),
        ];
    }

    /**
     * Get the request that sends the template of a reminder.
     *
     * @param string $recipient Number, digits only.
     * @param string $template Name of the approved template.
     * @param string $language Language of the template (e.g. "es", "ca").
     * @param array $parameters Values of the body parameters, in order.
     *
     * @return array Body of the request.
     */
    public static function message_payload(
        string $recipient,
        string $template,
        string $language,
        array $parameters,
    ): array {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => array_map(
                            fn(string $value): array => ['type' => 'text', 'text' => $value],
                            array_values($parameters),
                        ),
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the status of a reminder from a status that Meta reports.
     *
     * @param string $status sent, delivered, read or failed.
     *
     * @return string|null The status of the reminder, or null when it is not known.
     */
    public static function webhook_status(string $status): ?string
    {
        return in_array($status, ['sent', 'delivered', 'read', 'failed'], true) ? $status : null;
    }

    /**
     * Whether a reported status may replace the current one: the progress only goes forward, and a failure only
     * replaces a reminder that was not delivered.
     *
     * @param string $current
     * @param string $reported
     *
     * @return bool
     */
    public static function status_moves_forward(string $current, string $reported): bool
    {
        if ($reported === 'failed') {
            return !in_array($current, ['delivered', 'read'], true);
        }

        return (self::PROGRESS[$reported] ?? 0) > (self::PROGRESS[$current] ?? 0);
    }

    /**
     * Get the explanation of an error kept from Meta ("HTTP 400 131026 ...", "131026 ..." from the webhook, or
     * "Connection: ..."), when it is a known one.
     *
     * @param string|null $error
     *
     * @return string|null Language key of the explanation, or null.
     */
    public static function error_key(?string $error): ?string
    {
        $error = trim((string) $error);

        if (str_starts_with($error, 'Connection:')) {
            return 'whatsapp_error_connection';
        }

        if (!preg_match('/^(?:HTTP \d{3} )?(\d+)\b/', $error, $match)) {
            return null;
        }

        return in_array($match[1], self::KNOWN_ERRORS, true) ? 'whatsapp_error_' . $match[1] : null;
    }

    /**
     * Get an error of a message to show it: its explanation when it is a known one, as it came otherwise.
     *
     * @param string|null $error
     *
     * @return string
     */
    public static function describe_error(?string $error): string
    {
        $key = self::error_key($error);

        return $key ? lang($key) : trim((string) $error);
    }

    /**
     * Check a webhook request of Meta by its signature (X-Hub-Signature-256), made with the secret of the app.
     *
     * @param string $body Raw body of the request.
     * @param string|null $signature Value of the header ("sha256=...").
     * @param string $app_secret
     *
     * @return bool
     */
    public static function valid_signature(string $body, ?string $signature, string $app_secret): bool
    {
        if ($app_secret === '' || !$signature || !str_starts_with($signature, 'sha256=')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $app_secret), substr($signature, 7));
    }

    /**
     * Get why the reminder of an appointment cannot be sent again by hand now: its text says "tomorrow", so it only
     * goes out the day before the appointment.
     *
     * @param string $appointment_start Start of the appointment (Y-m-d H:i:s, local time of the clinic).
     * @param DateTimeInterface $now
     *
     * @return string|null Language key of the reason, or null when it can be sent.
     */
    public static function retry_blocker(string $appointment_start, DateTimeInterface $now): ?string
    {
        $local = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone(self::TIMEZONE));

        return substr($appointment_start, 0, 10) === $local->modify('+1 day')->format('Y-m-d')
            ? null
            : 'whatsapp_not_tomorrow';
    }

    /**
     * Send the reminders that are due now.
     *
     * @param DateTimeInterface|null $now For tests and manual runs; now by default.
     *
     * @return array Counts: ['due_date', 'created', 'sent', 'failed', 'cancelled', 'review'].
     */
    public function run(?DateTimeInterface $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
        $report = ['due_date' => null, 'created' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'review' => 0];

        $due_date = self::due_date($now);

        if (!$this->is_configured()) {
            return $report;
        }

        // Reminders of past windows that never went out are left for review, so that they are never sent late.
        $report['review'] += $this->expire_unsent($now);

        if (!$due_date) {
            return $report;
        }

        $report['due_date'] = $due_date;
        $report['created'] = $this->create_due_reminders($due_date);

        $reminders = $this->CI->db
            ->where('appointment_datetime >=', $due_date . ' 00:00:00')
            ->where('appointment_datetime <=', $due_date . ' 23:59:59')
            ->where('is_test', 0)
            ->where('is_deleted', 0)
            ->group_start()
            ->where('status', 'pending')
            ->or_group_start()
            ->where('status', 'failed')
            ->where('attempts <', self::MAX_ATTEMPTS)
            ->group_end()
            ->group_end()
            ->get('whatsapp_reminders')
            ->result_array();

        foreach ($reminders as $reminder) {
            $result = $this->process($reminder);

            if (isset($report[$result])) {
                $report[$result]++;
            }
        }

        return $report;
    }

    /**
     * Get the counts of the reminders by status, and the latest ones, for the panel of the settings page.
     *
     * @param int $limit
     *
     * @return array ['counts' => [status => n], 'tomorrow' => n, 'month_sent' => n, 'latest' => [...]].
     */
    public function overview(int $limit = 50): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);

        foreach (
            $this->CI->db
                ->select('status, COUNT(*) AS total')
                ->where('is_deleted', 0)
                ->group_by('status')
                ->get('whatsapp_reminders')
                ->result_array()
            as $row
        ) {
            $counts[$row['status']] = (int) $row['total'];
        }

        $tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d');

        $latest = $this->CI->db
            ->select(
                'whatsapp_reminders.*, ' . "CONCAT_WS(' ', customers.first_name, customers.last_name) AS customer_name",
                false,
            )
            ->from('whatsapp_reminders')
            ->join('users AS customers', 'customers.id = whatsapp_reminders.id_users_customer', 'left')
            ->where('whatsapp_reminders.is_deleted', 0)
            ->order_by('whatsapp_reminders.scheduled_datetime DESC, whatsapp_reminders.id DESC')
            ->limit($limit)
            ->get()
            ->result_array();

        return [
            'counts' => $counts,
            'tomorrow' => $this->tomorrow_count($tomorrow),
            'month_sent' => $this->CI->db
                ->where_in('status', ['accepted', 'sent', 'delivered', 'read'])
                ->where('last_attempt_datetime >=', date('Y-m-01 00:00:00'))
                ->count_all_results('whatsapp_reminders'),
            'latest' => $latest,
        ];
    }

    /**
     * Send a template message to a number, to try the settings. It is listed with the other messages.
     *
     * @param string $phone
     *
     * @return array ['ok' => bool, 'message_id' => ?string, 'error' => ?string]
     */
    public function send_test(string $phone): array
    {
        $recipient = self::recipient($phone);
        $now = date('Y-m-d H:i:s');

        $this->CI->db->insert('whatsapp_reminders', [
            'create_datetime' => $now,
            'update_datetime' => $now,
            'idempotency_key' => 'test:' . bin2hex(random_bytes(12)),
            'phone' => $recipient ?: mb_substr(trim($phone), 0, 32),
            'appointment_datetime' => date('Y-m-d 10:00:00', strtotime('+1 day')),
            'scheduled_datetime' => $now,
            'status' => 'processing',
            'is_test' => 1,
        ]);

        return $this->send_test_message((int) $this->CI->db->insert_id());
    }

    /**
     * Count the messages that could not be sent and nobody dealt with yet, for the button of the notifications.
     *
     * @return int
     */
    public function problem_count(): int
    {
        return $this->CI->db
            ->where_in('status', self::PROBLEM_STATUSES)
            ->where('is_deleted', 0)
            ->count_all_results('whatsapp_reminders');
    }

    /**
     * Get the messages of the list, the latest first.
     *
     * @param bool $only_problems Only those that could not be sent.
     * @param int $limit
     *
     * @return array Rows of whatsapp_reminders with customer_name.
     */
    public function messages(bool $only_problems = false, int $limit = 300): array
    {
        $query = $this->CI->db
            ->select(
                'whatsapp_reminders.*, ' . "CONCAT_WS(' ', customers.first_name, customers.last_name) AS customer_name",
                false,
            )
            ->from('whatsapp_reminders')
            ->join('users AS customers', 'customers.id = whatsapp_reminders.id_users_customer', 'left')
            ->where('whatsapp_reminders.is_deleted', 0);

        if ($only_problems) {
            $query->where_in('whatsapp_reminders.status', self::PROBLEM_STATUSES);
        }

        return $query
            ->order_by('whatsapp_reminders.scheduled_datetime DESC, whatsapp_reminders.id DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    /**
     * Send a message of the list again, by hand.
     *
     * @param int $reminder_id
     *
     * @return array ['ok' => bool, 'error' => ?string]
     */
    public function retry(int $reminder_id): array
    {
        $reminder = $this->find($reminder_id);

        if ($reminder['status'] === 'processing') {
            return ['ok' => false, 'error' => lang('whatsapp_busy')];
        }

        if ((int) $reminder['is_test']) {
            $result = $this->send_test_message($reminder_id);

            return ['ok' => $result['ok'], 'error' => $result['error']];
        }

        $appointment = $reminder['id_appointments']
            ? $this->CI->db->get_where('appointments', ['id' => $reminder['id_appointments']])->row_array()
            : null;

        // An appointment that is still on the day of the reminder, but not tomorrow: the text would say "tomorrow".
        if (
            $appointment &&
            substr($appointment['start_datetime'], 0, 10) === substr($reminder['appointment_datetime'], 0, 10)
        ) {
            $blocker = self::retry_blocker(
                $appointment['start_datetime'],
                new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)),
            );

            if ($blocker) {
                return ['ok' => false, 'error' => lang($blocker)];
            }
        }

        $result = $this->process($reminder);

        if ($result === 'sent') {
            return ['ok' => true, 'error' => null];
        }

        if ($result === 'cancelled') {
            return ['ok' => false, 'error' => lang('whatsapp_appointment_gone')];
        }

        return ['ok' => false, 'error' => $this->find($reminder_id)['error'] ?: lang('whatsapp_busy')];
    }

    /**
     * Mark a message as sent by other means (e.g. the clinic phoned the patient), so that it is no longer warned about.
     *
     * @param int $reminder_id
     */
    public function mark_sent(int $reminder_id): void
    {
        $this->find($reminder_id);

        $this->update($reminder_id, ['status' => 'manual', 'status_datetime' => date('Y-m-d H:i:s')]);
    }

    /**
     * Cancel a message: it is not sent, nor warned about.
     *
     * @param int $reminder_id
     */
    public function cancel(int $reminder_id): void
    {
        $this->find($reminder_id);

        $this->update($reminder_id, ['status' => 'cancelled', 'status_datetime' => date('Y-m-d H:i:s')]);
    }

    /**
     * Remove a message from the list. One still to be sent is cancelled too, so that it never goes out.
     *
     * @param int $reminder_id
     */
    public function delete(int $reminder_id): void
    {
        $reminder = $this->find($reminder_id);

        $this->update($reminder_id, [
            'is_deleted' => 1,
            'status' => in_array($reminder['status'], ['pending', 'failed'], true) ? 'cancelled' : $reminder['status'],
        ]);
    }

    /**
     * Apply the statuses that Meta reports through the webhook.
     *
     * @param array $payload Decoded body of the webhook.
     *
     * @return int Number of reminders updated.
     */
    public function handle_webhook(array $payload): int
    {
        $updated = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $updated += $this->apply_status($status) ? 1 : 0;
                }
            }
        }

        return $updated;
    }

    /**
     * Create the reminders of the appointments of a day that want one, once. Those whose patient cannot receive
     * WhatsApp messages are created for review.
     *
     * @param string $date Y-m-d
     *
     * @return int Reminders created.
     */
    private function create_due_reminders(string $date): int
    {
        $free_codes = appointment_status_free_codes();

        $query = $this->CI->db
            ->select(
                'appointments.id, appointments.start_datetime, appointments.id_users_customer, customers.phone_number',
            )
            ->from('appointments')
            ->join('users AS customers', 'customers.id = appointments.id_users_customer', 'inner')
            ->where('appointments.is_unavailability', 0)
            ->where('appointments.send_whatsapp_reminder', 1)
            ->where('appointments.start_datetime >=', $date . ' 00:00:00')
            ->where('appointments.start_datetime <=', $date . ' 23:59:59');

        if ($free_codes) {
            $query->where_not_in('appointments.status', $free_codes);
        }

        $created = 0;

        foreach ($query->get()->result_array() as $appointment) {
            $recipient = self::recipient($appointment['phone_number']);

            // A unique key: a reminder that already exists (from this run or an earlier one) is left as it is.
            $this->CI->db->query(
                'INSERT IGNORE INTO `' .
                    $this->CI->db->dbprefix('whatsapp_reminders') .
                    '` (create_datetime, update_datetime, idempotency_key, id_appointments, id_users_customer, phone, ' .
                    'appointment_datetime, scheduled_datetime, status, error) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s'),
                    self::idempotency_key((int) $appointment['id'], $appointment['start_datetime']),
                    (int) $appointment['id'],
                    (int) $appointment['id_users_customer'],
                    $recipient,
                    $appointment['start_datetime'],
                    self::scheduled_datetime($appointment['start_datetime']),
                    $recipient ? 'pending' : 'review',
                    $recipient ? null : lang('whatsapp_invalid_phone'),
                ],
            );

            $created += $this->CI->db->affected_rows();
        }

        return $created;
    }

    /**
     * Send one reminder, after checking that its appointment still wants it.
     *
     * @param array $reminder Row of whatsapp_reminders.
     *
     * @return string What happened: sent, failed, cancelled, review or skipped.
     */
    private function process(array $reminder): string
    {
        $appointment = $reminder['id_appointments']
            ? $this->CI->db->get_where('appointments', ['id' => $reminder['id_appointments']])->row_array()
            : null;

        // Cancelled, moved to another day, or no longer wanted since the reminder was created.
        if (
            !$appointment ||
            !(int) $appointment['send_whatsapp_reminder'] ||
            in_array($appointment['status'], appointment_status_free_codes(), true) ||
            substr($appointment['start_datetime'], 0, 10) !== substr($reminder['appointment_datetime'], 0, 10)
        ) {
            $this->update($reminder['id'], ['status' => 'cancelled']);

            return 'cancelled';
        }

        // Taken by this run only: a run that gets here at the same time finds it already in "processing".
        $this->CI->db
            ->set('status', 'processing')
            ->set('attempts', 'attempts + 1', false)
            ->set('last_attempt_datetime', date('Y-m-d H:i:s'))
            ->set('update_datetime', date('Y-m-d H:i:s'))
            ->where('id', $reminder['id'])
            ->where('status', $reminder['status'])
            ->where('attempts', $reminder['attempts'])
            ->update('whatsapp_reminders');

        if (!$this->CI->db->affected_rows()) {
            return 'skipped';
        }

        $customer = $this->CI->db->get_where('users', ['id' => $appointment['id_users_customer']])->row_array() ?: [];
        $recipient = self::recipient($customer['phone_number'] ?? null);

        if (!$recipient) {
            $this->update($reminder['id'], ['status' => 'review', 'error' => lang('whatsapp_invalid_phone')]);

            return 'review';
        }

        $result = $this->send($recipient, self::template_parameters($appointment, $customer));

        if ($result['ok']) {
            $this->update($reminder['id'], [
                'status' => 'accepted',
                'phone' => $recipient,
                'appointment_datetime' => $appointment['start_datetime'],
                'provider_message_id' => $result['message_id'],
                'status_datetime' => date('Y-m-d H:i:s'),
                'error' => null,
            ]);

            return 'sent';
        }

        $this->update($reminder['id'], ['status' => 'failed', 'error' => $result['error']]);

        return 'failed';
    }

    /**
     * Send the template of a reminder through the API.
     *
     * @param string $recipient Number, digits only.
     * @param array $parameters
     *
     * @return array ['ok' => bool, 'message_id' => ?string, 'error' => ?string]
     */
    private function send(string $recipient, array $parameters): array
    {
        $payload = self::message_payload(
            $recipient,
            (string) setting('whatsapp_template_name'),
            (string) (setting('whatsapp_template_language') ?: 'ca'),
            $parameters,
        );

        // The Graph API of Meta; the setting whatsapp_api_url (not on the page) points it elsewhere to try it locally.
        $api = rtrim((string) (setting('whatsapp_api_url') ?: self::GRAPH_API), '/') . '/';

        $curl = curl_init($api . rawurlencode((string) setting('whatsapp_phone_number_id')) . '/messages');

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . setting('whatsapp_access_token'),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            return ['ok' => false, 'message_id' => null, 'error' => 'Connection: ' . $curl_error];
        }

        $response = json_decode((string) $body, true) ?: [];
        $message_id = $response['messages'][0]['id'] ?? null;

        if ($status >= 200 && $status < 300 && $message_id) {
            return ['ok' => true, 'message_id' => $message_id, 'error' => null];
        }

        $error = $response['error'] ?? [];

        return [
            'ok' => false,
            'message_id' => null,
            'error' => trim(
                'HTTP ' .
                    $status .
                    ' ' .
                    ($error['code'] ?? '') .
                    ' ' .
                    ($error['error_data']['details'] ?? ($error['message'] ?? substr((string) $body, 0, 300))),
            ),
        ];
    }

    /**
     * Apply one status reported by Meta to its reminder.
     *
     * @param array $status Item of value.statuses: id, status, errors...
     *
     * @return bool Whether a reminder changed.
     */
    private function apply_status(array $status): bool
    {
        $reported = self::webhook_status((string) ($status['status'] ?? ''));

        if (!$reported || empty($status['id'])) {
            return false;
        }

        $reminder = $this->CI->db
            ->get_where('whatsapp_reminders', ['provider_message_id' => $status['id']])
            ->row_array();

        if (!$reminder || !self::status_moves_forward($reminder['status'], $reported)) {
            return false;
        }

        $error = null;

        if ($reported === 'failed') {
            $first = $status['errors'][0] ?? [];
            $error = trim(($first['code'] ?? '') . ' ' . ($first['error_data']['details'] ?? ($first['title'] ?? '')));
        }

        $this->update($reminder['id'], [
            'status' => $reported,
            'status_datetime' => date('Y-m-d H:i:s'),
            'error' => $error ?: $reminder['error'],
        ]);

        return true;
    }

    /**
     * Leave for review the reminders whose window has passed without being sent, and those that stayed in
     * "processing" (a run stopped while sending, so it is not known whether the patient got it).
     *
     * @param DateTimeInterface $now
     *
     * @return int Reminders left for review.
     */
    private function expire_unsent(DateTimeInterface $now): int
    {
        $this->CI->db
            ->set('status', 'review')
            ->set('update_datetime', date('Y-m-d H:i:s'))
            ->where('scheduled_datetime <=', self::last_closed_day($now) . ' 23:59:59')
            ->where('is_test', 0)
            ->where_in('status', ['pending', 'failed', 'processing'])
            ->update('whatsapp_reminders');

        $expired = $this->CI->db->affected_rows();

        // A reminder taken more than 15 minutes ago and never finished is not sent again either.
        $this->CI->db
            ->set('status', 'review')
            ->set('error', lang('whatsapp_interrupted'))
            ->set('update_datetime', date('Y-m-d H:i:s'))
            ->where('status', 'processing')
            ->where('last_attempt_datetime <', date('Y-m-d H:i:s', strtotime('-15 minutes')))
            ->update('whatsapp_reminders');

        return $expired + $this->CI->db->affected_rows();
    }

    /**
     * Get a message of the list.
     *
     * @param int $reminder_id
     *
     * @return array
     *
     * @throws InvalidArgumentException When it does not exist or was removed.
     */
    private function find(int $reminder_id): array
    {
        $reminder = $this->CI->db
            ->get_where('whatsapp_reminders', ['id' => $reminder_id, 'is_deleted' => 0])
            ->row_array();

        if (!$reminder) {
            throw new InvalidArgumentException(lang('whatsapp_message_not_found'));
        }

        return $reminder;
    }

    /**
     * Send a test message of the list to its number, and keep what Meta answers.
     *
     * @param int $reminder_id
     *
     * @return array ['ok' => bool, 'message_id' => ?string, 'error' => ?string]
     */
    private function send_test_message(int $reminder_id): array
    {
        $reminder = $this->find($reminder_id);
        $phone = (string) $reminder['phone'];
        $recipient = ctype_digit($phone) ? $phone : self::recipient($phone);

        $this->CI->db
            ->set('status', 'processing')
            ->set('attempts', 'attempts + 1', false)
            ->set('last_attempt_datetime', date('Y-m-d H:i:s'))
            ->where('id', $reminder_id)
            ->update('whatsapp_reminders');

        $result = $recipient
            ? $this->send(
                $recipient,
                self::template_parameters(
                    ['start_datetime' => $reminder['appointment_datetime']],
                    ['first_name' => 'Prova'],
                ),
            )
            : ['ok' => false, 'message_id' => null, 'error' => lang('whatsapp_invalid_phone')];

        $this->update(
            $reminder_id,
            $result['ok']
                ? [
                    'status' => 'accepted',
                    'provider_message_id' => $result['message_id'],
                    'status_datetime' => date('Y-m-d H:i:s'),
                    'error' => null,
                ]
                : ['status' => 'failed', 'error' => $result['error']],
        );

        return $result;
    }

    /**
     * Count the appointments of a day that will get a reminder.
     *
     * @param string $date Y-m-d
     *
     * @return int
     */
    private function tomorrow_count(string $date): int
    {
        $free_codes = appointment_status_free_codes();

        $query = $this->CI->db
            ->from('appointments')
            ->where('is_unavailability', 0)
            ->where('send_whatsapp_reminder', 1)
            ->where('id_users_customer IS NOT NULL')
            ->where('start_datetime >=', $date . ' 00:00:00')
            ->where('start_datetime <=', $date . ' 23:59:59');

        if ($free_codes) {
            $query->where_not_in('status', $free_codes);
        }

        return $query->count_all_results();
    }

    /**
     * Change a reminder.
     *
     * @param int|string $reminder_id
     * @param array $fields
     */
    private function update(int|string $reminder_id, array $fields): void
    {
        $this->CI->db->update('whatsapp_reminders', $fields + ['update_datetime' => date('Y-m-d H:i:s')], [
            'id' => $reminder_id,
        ]);
    }
}
