<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * WhatsApp reminders.
 *
 * Every appointment gets one reminder through the WhatsApp Cloud API of Meta, with an approved template of three
 * parameters: name, day and time. Nothing clinical is sent. The times are the ones of Europe/Madrid, whatever the time
 * zone of the server, with summer and winter time.
 *
 * When it is sent follows rules that the clinic edits in the settings (see rules()), by default:
 *
 *     moment = the latest of (the day before at 10:00, the creation of the appointment + 30 minutes)
 *
 * and nothing is sent when less than 2 hours are left before the appointment. Changing an appointment does not move
 * the 30 minutes: they count from its creation. Weekends, holidays and the hours the clinic is closed are no
 * exception.
 *
 * run() is called by the scheduled task every few minutes (php index.php console whatsapp):
 *
 * - It works the moment out for every appointment that wants a reminder (appointments.send_whatsapp_reminder), is not
 *   cancelled and has a patient, from its current day and time, so a moved or cancelled appointment needs nothing
 *   else. A server that was off at that moment sends it when it is back, while the minimum notice allows it.
 * - Only then a row of whatsapp_reminders is created, with a key unique per appointment, and taken by one run only
 *   (pending -> processing): an appointment never gets a second reminder, not even when it is moved to another day,
 *   and a run that repeats never sends twice. A reminder left in "processing" (the run stopped while sending) is never
 *   sent again: it shows up for review.
 * - A failed reminder is tried again on the next runs, up to MAX_ATTEMPTS times, until the minimum notice.
 * - When the day or the time of an appointment changes after its reminder went out, the reminder is marked, so that
 *   the clinic tells the patient by other means (see flag_change()).
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

    /**
     * Rules of the sending, editable in the settings, and their default values (see rules()).
     */
    public const RULE_DEFAULTS = [
        // Usual moment: so many days before the appointment, at this time.
        'send_time' => '10:00',
        'days_before' => 1,
        // Minutes that must pass from the creation of the appointment.
        'booking_delay' => 30,
        // Minutes before the appointment from which nothing is sent any more.
        'min_notice' => 120,
    ];

    /**
     * Limits of the rules, so that a typing mistake in the settings cannot stop or flood the reminders.
     */
    public const RULE_LIMITS = [
        'days_before' => [0, 7],
        'booking_delay' => [0, 1440],
        'min_notice' => [0, 2880],
    ];

    /**
     * Statuses of a reminder that reached Meta (or the patient, by other means): the patient knows the day and time
     * it said.
     */
    public const SENT_STATUSES = ['accepted', 'sent', 'delivered', 'read', 'manual'];

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
     * {{1}} name, {{2}} day (e.g. "demà, dilluns 16 de gener", "avui, dijous 3 d'abril"; see day_text()) and {{3}} time
     * (e.g. "09:00"). %s is the name of the clinic (see template_body()). The template never says "avui" or "demà"
     * itself: {{2}} does, so the same template fits a reminder sent on any day.
     */
    public const TEMPLATE_BODY =
        "Hola {{1}},
" .
        "Tens visita *{{2}}* a les *{{3}}h* a la %s.

" .
        "```· Recorda que no acceptem pagament amb targeta.
" .
        "· Aquest número de telèfon només atén missatges de WhatsApp.
" .
        "· Si necessites parlar amb nosaltres, prem el botó de trucada.```

" .
        'Gràcies 👣';

    /**
     * Text of the default template with the name of the clinic.
     *
     * @param string $clinic Name of the clinic, as the patients know it.
     *
     * @return string
     */
    public static function template_body(string $clinic): string
    {
        return sprintf(self::TEMPLATE_BODY, $clinic);
    }

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
     * Get the rules of the sending, from the settings.
     *
     * @return array See RULE_DEFAULTS.
     */
    public function rules(): array
    {
        return self::normalize_rules([
            'send_time' => setting('whatsapp_send_time'),
            'days_before' => setting('whatsapp_days_before'),
            'booking_delay' => setting('whatsapp_booking_delay'),
            'min_notice' => setting('whatsapp_min_notice'),
        ]);
    }

    /**
     * Get valid rules from the values given: a missing or wrong value takes its default, and a number out of its
     * limits the nearest limit.
     *
     * @param array $values Values by rule name, as typed or saved.
     *
     * @return array See RULE_DEFAULTS.
     */
    public static function normalize_rules(array $values): array
    {
        $rules = self::RULE_DEFAULTS;

        $time = trim((string) ($values['send_time'] ?? ''));

        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $matches)) {
            $rules['send_time'] = str_pad($matches[1], 2, '0', STR_PAD_LEFT) . ':' . $matches[2];
        }

        foreach (self::RULE_LIMITS as $name => [$min, $max]) {
            $value = trim((string) ($values[$name] ?? ''));

            if (preg_match('/^\d+$/', $value)) {
                $rules[$name] = max($min, min($max, (int) $value));
            }
        }

        return $rules;
    }

    /**
     * Get a moment in the time of the clinic.
     *
     * @param DateTimeInterface|string $moment A date, or a "Y-m-d H:i:s" in the time of the clinic.
     *
     * @return DateTimeImmutable
     */
    private static function local(DateTimeInterface|string $moment): DateTimeImmutable
    {
        $zone = new DateTimeZone(self::TIMEZONE);

        return is_string($moment)
            ? new DateTimeImmutable($moment, $zone)
            : DateTimeImmutable::createFromInterface($moment)->setTimezone($zone);
    }

    /**
     * Get the moment the reminder of an appointment is sent: the usual one (so many days before, at the send time),
     * or later when the appointment was created less than booking_delay minutes before it.
     *
     * @param string $appointment_start Start of the appointment (Y-m-d H:i:s, time of the clinic).
     * @param DateTimeInterface|null $created When the appointment was created, or null when it is not known.
     * @param array $rules See rules().
     *
     * @return DateTimeImmutable In the time of the clinic.
     */
    public static function send_moment(
        string $appointment_start,
        ?DateTimeInterface $created,
        array $rules,
    ): DateTimeImmutable {
        [$hours, $minutes] = array_map('intval', explode(':', $rules['send_time']));

        $moment = self::local($appointment_start)
            ->setTime(0, 0)
            ->modify('-' . (int) $rules['days_before'] . ' days')
            ->setTime($hours, $minutes);

        if ($created) {
            $earliest = self::local($created)->modify('+' . (int) $rules['booking_delay'] . ' minutes');

            if ($earliest > $moment) {
                $moment = $earliest;
            }
        }

        return $moment;
    }

    /**
     * Get the moment from which the reminder of an appointment is no longer sent: min_notice minutes before it.
     *
     * @param string $appointment_start Start of the appointment (Y-m-d H:i:s, time of the clinic).
     * @param array $rules See rules().
     *
     * @return DateTimeImmutable In the time of the clinic.
     */
    public static function send_deadline(string $appointment_start, array $rules): DateTimeImmutable
    {
        return self::local($appointment_start)->modify('-' . (int) $rules['min_notice'] . ' minutes');
    }

    /**
     * Whether the reminder of an appointment is to be sent at a moment: its send moment has come and the minimum
     * notice has not.
     *
     * @param string $appointment_start Start of the appointment (Y-m-d H:i:s, time of the clinic).
     * @param DateTimeInterface|null $created When the appointment was created.
     * @param DateTimeInterface $now
     * @param array $rules See rules().
     *
     * @return bool
     */
    public static function is_due(
        string $appointment_start,
        ?DateTimeInterface $created,
        DateTimeInterface $now,
        array $rules,
    ): bool {
        $now = self::local($now);

        return self::send_moment($appointment_start, $created, $rules) <= $now &&
            $now < self::send_deadline($appointment_start, $rules);
    }

    /**
     * Get when an appointment was created, from its row: create_datetime, written in the time zone of the application.
     *
     * @param array $appointment With create_datetime (or book_datetime).
     *
     * @return DateTimeImmutable|null
     */
    public static function created_moment(array $appointment): ?DateTimeImmutable
    {
        $created = $appointment['create_datetime'] ?? null ?: $appointment['book_datetime'] ?? null;

        if (!$created) {
            return null;
        }

        try {
            return new DateTimeImmutable($created, new DateTimeZone(date_default_timezone_get()));
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get the key that makes the reminder of an appointment unique: one per appointment, whatever its day.
     *
     * @param int $appointment_id
     *
     * @return string
     */
    public static function idempotency_key(int $appointment_id): string
    {
        return 'reminder:' . $appointment_id;
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
     * Get the parameters of the template, in its order: name, day (see day_text()) and time (e.g. "09:00").
     *
     * @param array $appointment With start_datetime.
     * @param array $customer With first_name.
     * @param DateTimeInterface|null $now When it is sent, for "avui" and "demà"; now by default.
     *
     * @return array
     */
    public static function template_parameters(
        array $appointment,
        array $customer,
        ?DateTimeInterface $now = null,
    ): array {
        $start = strtotime($appointment['start_datetime']);

        return [
            trim((string) ($customer['first_name'] ?? '')) ?: '-',
            self::day_text($appointment['start_datetime'], $now ?? new DateTimeImmutable()),
            date('H:i', $start),
        ];
    }

    /**
     * Get the date of an appointment in Catalan, e.g. "dilluns 16 de gener" or "dijous 3 d'abril".
     *
     * @param string $appointment_start Y-m-d H:i:s
     *
     * @return string
     */
    public static function date_text(string $appointment_start): string
    {
        $start = strtotime($appointment_start);
        $month = self::MONTHS[(int) date('n', $start) - 1];

        return self::WEEKDAYS[(int) date('w', $start)] .
            ' ' .
            date('j', $start) .
            ' ' .
            (in_array($month[0], ['a', 'o'], true) ? "d'" : 'de ') .
            $month;
    }

    /**
     * Get the day of an appointment as the template says it, from the moment it is sent: "avui, dimarts 17 de
     * novembre", "demà, dimecres 18 de novembre" or, any other day, "el dijous 19 de novembre".
     *
     * @param string $appointment_start Y-m-d H:i:s, time of the clinic.
     * @param DateTimeInterface $now
     *
     * @return string
     */
    public static function day_text(string $appointment_start, DateTimeInterface $now): string
    {
        $today = self::local($now)->setTime(0, 0);
        $day = self::local($appointment_start)->setTime(0, 0);
        $date = self::date_text($appointment_start);

        if ($day == $today) {
            return 'avui, ' . $date;
        }

        if ($day == $today->modify('+1 day')) {
            return 'demà, ' . $date;
        }

        return 'el ' . $date;
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
     * Send the reminders that are due now.
     *
     * @param DateTimeInterface|null $now For tests and manual runs; now by default.
     *
     * @return array Counts: ['created', 'sent', 'failed', 'cancelled', 'review', 'waiting', 'changed'].
     */
    public function run(?DateTimeInterface $now = null): array
    {
        $now = self::local($now ?? new DateTimeImmutable());
        $report = [
            'created' => 0,
            'sent' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'review' => 0,
            'waiting' => 0,
            'changed' => 0,
        ];

        if (!$this->is_configured()) {
            return $report;
        }

        $rules = $this->rules();

        // The appointments moved after their reminder went out are marked for the clinic.
        $report['changed'] = $this->flag_changes($now);

        $report['created'] = $this->create_due_reminders($now, $rules);

        $reminders = $this->CI->db
            ->where('scheduled_datetime <=', $now->format('Y-m-d H:i:s'))
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
            $result = $this->process($reminder, $now, $rules);

            if (isset($report[$result])) {
                $report[$result]++;
            }
        }

        // The ones that could not go out before the minimum notice are left for review, never sent late.
        $report['review'] += $this->expire_unsent($now, $rules);

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

        $reminder_id = (int) $this->CI->db->insert_id();

        $result = $this->send_test_message($reminder_id);

        audit_log('test_sent', 'whatsapp_message', $reminder_id, [
            'phone' => [null, $recipient ?: trim($phone)],
            'status' => [null, $result['ok'] ? 'accepted' : 'failed'],
        ]);

        return $result;
    }

    /**
     * Record in the audit log what someone did with a message of the list.
     *
     * @param string $action E.g. "resent", "marked_sent", "cancelled", "deleted", "patient_told".
     * @param array $reminder The message as it was.
     */
    private function audit_action(string $action, array $reminder): void
    {
        $after = $this->CI->db->get_where('whatsapp_reminders', ['id' => $reminder['id']])->row_array() ?: [];

        $changes = audit_changes($reminder, $after, ['status', 'change_handled_datetime']);

        // Which appointment, so that the log says it even when nothing else changed.
        $changes['appointment_datetime'] = [$reminder['appointment_datetime'], $reminder['appointment_datetime']];

        audit_log($action, 'whatsapp_message', (int) $reminder['id'], $changes);
    }

    /**
     * Count the messages that could not be sent and nobody dealt with yet, for the button of the notifications.
     *
     * @return int
     */
    public function problem_count(): int
    {
        $this->CI->db->where('is_deleted', 0);
        $this->where_problem('whatsapp_reminders');

        return $this->CI->db->count_all_results('whatsapp_reminders');
    }

    /**
     * Count the messages whose appointment changed after they went out while the patient was not told yet.
     *
     * @return int
     */
    public function change_pending_count(): int
    {
        return $this->CI->db
            ->where('is_deleted', 0)
            ->where('change_to_datetime IS NOT NULL')
            ->where('change_handled_datetime IS NULL')
            ->count_all_results('whatsapp_reminders');
    }

    /**
     * Add to the query the messages that need someone: those that could not be sent, and those whose appointment
     * changed after they went out while the patient was not told yet.
     *
     * @param string $table Name (or alias) of whatsapp_reminders in the query.
     */
    private function where_problem(string $table): void
    {
        $this->CI->db
            ->group_start()
            ->where_in($table . '.status', self::PROBLEM_STATUSES)
            ->or_group_start()
            ->where($table . '.change_to_datetime IS NOT NULL')
            ->where($table . '.change_handled_datetime IS NULL')
            ->group_end()
            ->group_end();
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
            $this->where_problem('whatsapp_reminders');
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

        $now = self::local(new DateTimeImmutable());
        $rules = $this->rules();

        // Sent by hand: whoever sends it decides, whatever the time left before the appointment.
        $result = $this->process($reminder, $now, $rules, true);

        $this->audit_action('resent', $reminder);

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
        $reminder = $this->find($reminder_id);

        $this->update($reminder_id, ['status' => 'manual', 'status_datetime' => date('Y-m-d H:i:s')]);

        $this->audit_action('marked_sent', $reminder);
    }

    /**
     * Say that the patient was told about the change of an appointment made after its reminder went out, so that it is
     * no longer warned about.
     *
     * @param int $reminder_id
     */
    public function mark_change_handled(int $reminder_id): void
    {
        $reminder = $this->find($reminder_id);

        $this->update($reminder_id, ['change_handled_datetime' => date('Y-m-d H:i:s')]);

        $this->audit_action('patient_told', $reminder);
    }

    /**
     * Mark the reminder of an appointment when the appointment no longer is on the day and time the reminder said, so
     * that the clinic tells the patient; moved back to that day and time, it is no longer marked. A reminder that
     * did not reach the patient is left as it is: the next one, if any, has the new day.
     *
     * @param int $appointment_id
     * @param DateTimeInterface|null $now
     *
     * @return array|null When the appointment has just been found moved: ['sent_datetime', 'appointment_datetime'] of
     * the reminder (Y-m-d H:i:s), to tell the user right away. Null otherwise.
     */
    public function flag_change(int $appointment_id, ?DateTimeInterface $now = null): ?array
    {
        $reminder = $this->CI->db
            ->select('whatsapp_reminders.*, appointments.start_datetime, appointments.status AS appointment_status')
            ->from('whatsapp_reminders')
            ->join('appointments', 'appointments.id = whatsapp_reminders.id_appointments', 'inner')
            ->where('whatsapp_reminders.id_appointments', $appointment_id)
            ->where('whatsapp_reminders.is_test', 0)
            ->where('whatsapp_reminders.is_deleted', 0)
            ->where_in('whatsapp_reminders.status', self::SENT_STATUSES)
            ->order_by('whatsapp_reminders.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        return $reminder ? $this->flag_reminder($reminder, self::local($now ?? new DateTimeImmutable())) : null;
    }

    /**
     * Mark every reminder whose appointment was moved after it went out (see flag_change()), whatever moved it.
     *
     * @param DateTimeInterface $now
     *
     * @return int Reminders marked.
     */
    private function flag_changes(DateTimeInterface $now): int
    {
        $reminders = $this->CI->db
            ->select('whatsapp_reminders.*, appointments.start_datetime, appointments.status AS appointment_status')
            ->from('whatsapp_reminders')
            ->join('appointments', 'appointments.id = whatsapp_reminders.id_appointments', 'inner')
            ->where('whatsapp_reminders.is_test', 0)
            ->where('whatsapp_reminders.is_deleted', 0)
            ->where_in('whatsapp_reminders.status', self::SENT_STATUSES)
            ->where('appointments.start_datetime >=', self::local($now)->format('Y-m-d 00:00:00'))
            ->group_start()
            ->where(
                $this->CI->db->dbprefix('appointments') .
                    '.start_datetime != ' .
                    $this->CI->db->dbprefix('whatsapp_reminders') .
                    '.appointment_datetime',
                null,
                false,
            )
            ->or_where('whatsapp_reminders.change_to_datetime IS NOT NULL')
            ->group_end()
            ->get()
            ->result_array();

        $flagged = 0;

        foreach ($reminders as $reminder) {
            $flagged += $this->flag_reminder($reminder, self::local($now)) ? 1 : 0;
        }

        return $flagged;
    }

    /**
     * Mark a reminder whose appointment is no longer on the day and time it said (see flag_change()).
     *
     * @param array $reminder Row of whatsapp_reminders with the start_datetime and appointment_status of its
     * appointment.
     * @param DateTimeImmutable $now
     *
     * @return array|null See flag_change().
     */
    private function flag_reminder(array $reminder, DateTimeImmutable $now): ?array
    {
        $start = $reminder['start_datetime'];

        // A cancelled appointment is not marked: the patient is the one who usually asks for it.
        $moved =
            $start !== $reminder['appointment_datetime'] &&
            !in_array($reminder['appointment_status'], appointment_status_free_codes(), true);

        if (!$moved) {
            if ($reminder['change_to_datetime'] !== null) {
                $this->update($reminder['id'], [
                    'change_to_datetime' => null,
                    'change_datetime' => null,
                    'change_handled_datetime' => null,
                ]);
            }

            return null;
        }

        // Already marked with this day and time.
        if ($reminder['change_to_datetime'] === $start) {
            return null;
        }

        $this->update($reminder['id'], [
            'change_to_datetime' => $start,
            'change_datetime' => date('Y-m-d H:i:s'),
            'change_handled_datetime' => null,
        ]);

        return [
            'sent_datetime' => $reminder['last_attempt_datetime'] ?: $reminder['status_datetime'],
            'appointment_datetime' => $reminder['appointment_datetime'],
        ];
    }

    /**
     * Get the reminder of an appointment, as the appointment window shows it.
     *
     * @param int $appointment_id
     *
     * @return array ['state' => off|disabled|refused|sent|pending|failed|review|cancelled|scheduled|too_late|past]
     * (refused: the patient signed that they do not want WhatsApp messages):
     * - sent (and the other statuses of a reminder that exists): status, sent_datetime, appointment_datetime,
     *   change_to_datetime.
     * - scheduled: datetime, the moment it will be sent.
     */
    public function appointment_reminder(int $appointment_id): array
    {
        if (!$this->is_configured()) {
            return ['state' => 'off'];
        }

        $reminder = $this->CI->db
            ->where('id_appointments', $appointment_id)
            ->where('is_test', 0)
            ->where('is_deleted', 0)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('whatsapp_reminders')
            ->row_array();

        if ($reminder) {
            $sent = in_array($reminder['status'], self::SENT_STATUSES, true);

            return [
                'state' => $sent ? 'sent' : $reminder['status'],
                'status' => $reminder['status'],
                'sent_datetime' => $reminder['last_attempt_datetime'] ?: $reminder['status_datetime'],
                'appointment_datetime' => $reminder['appointment_datetime'],
                'change_to_datetime' => $reminder['change_to_datetime'],
                'change_handled' => (bool) $reminder['change_handled_datetime'],
            ];
        }

        $appointment = $this->CI->db->get_where('appointments', ['id' => $appointment_id])->row_array();

        if (
            !$appointment ||
            !(int) $appointment['send_whatsapp_reminder'] ||
            in_array($appointment['status'], appointment_status_free_codes(), true)
        ) {
            return ['state' => 'disabled'];
        }

        if ($this->refused((int) $appointment['id_users_customer'])) {
            return ['state' => 'refused'];
        }

        $rules = $this->rules();
        $now = self::local(new DateTimeImmutable());
        $start = $appointment['start_datetime'];

        if (self::local($start) <= $now) {
            return ['state' => 'past'];
        }

        [$state, $moment] = self::planned_send($start, self::created_moment($appointment), $now, $rules);

        return $moment ? ['state' => $state, 'datetime' => $moment->format('Y-m-d H:i:s')] : ['state' => $state];
    }

    /**
     * Get when the reminder of an appointment that has none yet will go out, from now.
     *
     * @param string $appointment_start Start of the appointment (Y-m-d H:i:s, time of the clinic).
     * @param DateTimeInterface|null $created When the appointment was created.
     * @param DateTimeInterface $now
     * @param array $rules See rules().
     *
     * @return array [state, moment]: ['scheduled', DateTimeImmutable] (a moment already gone is the next run, that is,
     * now), or ['too_late', null] when the minimum notice comes first.
     */
    public static function planned_send(
        string $appointment_start,
        ?DateTimeInterface $created,
        DateTimeInterface $now,
        array $rules,
    ): array {
        $now = self::local($now);
        $moment = max(self::send_moment($appointment_start, $created, $rules), $now);

        if ($moment >= self::send_deadline($appointment_start, $rules)) {
            return ['too_late', null];
        }

        return ['scheduled', $moment];
    }

    /**
     * Get every reminder still to go out, from the future appointments that want one: when it will be sent, or why it
     * will not. They are worked out as run() does, so the list says what will happen.
     *
     * @param DateTimeInterface|null $now
     * @param int $limit Most appointments listed.
     *
     * @return array Rows ['appointment_id', 'appointment_datetime', 'customer_name', 'send_datetime' (Y-m-d H:i:s or
     * null), 'state' (scheduled, pending, failed, too_late or invalid_phone)], the first to go out first.
     */
    public function upcoming(?DateTimeInterface $now = null, int $limit = 500): array
    {
        if (!$this->is_configured()) {
            return [];
        }

        $now = self::local($now ?? new DateTimeImmutable());
        $rules = $this->rules();
        $reminders = $this->CI->db->dbprefix('whatsapp_reminders');
        $appointments = $this->CI->db->dbprefix('appointments');
        // Before building the query: the free codes come from the settings, with a query of their own.
        $free_codes = appointment_status_free_codes();

        $query = $this->CI->db
            ->select(
                'appointments.id, appointments.start_datetime, appointments.create_datetime, ' .
                    'appointments.book_datetime, customers.phone_number, ' .
                    "CONCAT_WS(' ', customers.first_name, customers.last_name) AS customer_name",
                false,
            )
            ->from('appointments')
            ->join('users AS customers', 'customers.id = appointments.id_users_customer', 'inner')
            ->where('appointments.is_unavailability', 0)
            ->where('appointments.send_whatsapp_reminder', 1)
            ->where('appointments.start_datetime >', $now->format('Y-m-d H:i:s'))
            // Not those whose reminder already went out or was left for review (the same reminders that stop
            // create_due_reminders() from creating another one); a pending or failed one is still to go out.
            ->where(
                "NOT EXISTS (SELECT 1 FROM `$reminders` r WHERE r.id_appointments = `$appointments`.id " .
                    "AND r.is_test = 0 AND (r.status <> 'cancelled' OR r.provider_message_id IS NOT NULL) " .
                    "AND r.status NOT IN ('pending', 'failed'))",
                null,
                false,
            )
            ->where($this->not_refused("`$appointments`.id_users_customer"), null, false)
            ->order_by('appointments.start_datetime')
            ->limit($limit);

        if ($free_codes) {
            $query->where_not_in('appointments.status', $free_codes);
        }

        $rows = $query->get()->result_array();

        $waiting = [];

        if ($rows) {
            foreach (
                $this->CI->db
                    ->where_in('id_appointments', array_column($rows, 'id'))
                    ->where_in('status', ['pending', 'failed'])
                    ->where('is_test', 0)
                    ->where('is_deleted', 0)
                    ->get('whatsapp_reminders')
                    ->result_array()
                as $reminder
            ) {
                $waiting[(int) $reminder['id_appointments']] = $reminder;
            }
        }

        $list = [];

        foreach ($rows as $row) {
            $start = $row['start_datetime'];
            $reminder = $waiting[(int) $row['id']] ?? null;

            if ($reminder) {
                // Created already: it goes out on the next run (a failed one, while it has attempts left).
                $retries = $reminder['status'] === 'pending' || (int) $reminder['attempts'] < self::MAX_ATTEMPTS;
                $state = $reminder['status'];
                $moment = $retries ? max(self::local($reminder['scheduled_datetime']), $now) : null;
            } else {
                [$state, $moment] = self::planned_send($start, self::created_moment($row), $now, $rules);

                if ($state === 'scheduled' && !self::recipient($row['phone_number'])) {
                    $state = 'invalid_phone';
                }
            }

            $list[] = [
                'appointment_id' => (int) $row['id'],
                'appointment_datetime' => $start,
                'customer_name' => $row['customer_name'],
                'send_datetime' => $moment?->format('Y-m-d H:i:s'),
                'state' => $state,
            ];
        }

        // The first to go out first; those that will not go out at the end.
        usort(
            $list,
            fn(array $a, array $b) => [
                $a['send_datetime'] === null,
                $a['send_datetime'],
                $a['appointment_datetime'],
            ] <=> [$b['send_datetime'] === null, $b['send_datetime'], $b['appointment_datetime']],
        );

        return $list;
    }

    /**
     * Cancel a message: it is not sent, nor warned about.
     *
     * @param int $reminder_id
     */
    public function cancel(int $reminder_id): void
    {
        $reminder = $this->find($reminder_id);

        $this->update($reminder_id, ['status' => 'cancelled', 'status_datetime' => date('Y-m-d H:i:s')]);

        $this->audit_action('cancelled', $reminder);
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

        $this->audit_action('deleted', $reminder);
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
     * Create, once, the reminders that are due now: of the appointments that want one, whose send moment has come
     * and whose minimum notice has not (see is_due()), and that never had one. Those whose patient cannot receive
     * WhatsApp messages are created for review.
     *
     * @param DateTimeImmutable $now In the time of the clinic.
     * @param array $rules See rules().
     *
     * @return int Reminders created.
     */
    private function create_due_reminders(DateTimeImmutable $now, array $rules): int
    {
        $free_codes = appointment_status_free_codes();
        $reminders = $this->CI->db->dbprefix('whatsapp_reminders');
        $appointments = $this->CI->db->dbprefix('appointments');

        // Only the days whose usual moment may have come; send_moment() decides on each one.
        $query = $this->CI->db
            ->select(
                'appointments.id, appointments.start_datetime, appointments.create_datetime, ' .
                    'appointments.book_datetime, appointments.id_users_customer, customers.phone_number',
            )
            ->from('appointments')
            ->join('users AS customers', 'customers.id = appointments.id_users_customer', 'inner')
            ->where('appointments.is_unavailability', 0)
            ->where('appointments.send_whatsapp_reminder', 1)
            ->where(
                'appointments.start_datetime >',
                $now->modify('+' . $rules['min_notice'] . ' minutes')->format('Y-m-d H:i:s'),
            )
            ->where(
                'appointments.start_datetime <=',
                $now->modify('+' . $rules['days_before'] . ' days')->format('Y-m-d 23:59:59'),
            )
            // Never a second one: also the reminders of the former key (one per appointment and day) count, except
            // those cancelled before reaching Meta.
            ->where(
                "NOT EXISTS (SELECT 1 FROM `$reminders` r WHERE r.id_appointments = `$appointments`.id " .
                    "AND r.is_test = 0 AND (r.status <> 'cancelled' OR r.provider_message_id IS NOT NULL))",
                null,
                false,
            )
            ->where($this->not_refused("`$appointments`.id_users_customer"), null, false);

        if ($free_codes) {
            $query->where_not_in('appointments.status', $free_codes);
        }

        $created = 0;

        foreach ($query->get()->result_array() as $appointment) {
            $start = $appointment['start_datetime'];
            $created_moment = self::created_moment($appointment);

            if (!self::is_due($start, $created_moment, $now, $rules)) {
                continue;
            }

            $recipient = self::recipient($appointment['phone_number']);

            // A unique key: a reminder that already exists (from this run or an earlier one) is left as it is.
            $this->CI->db->query(
                'INSERT IGNORE INTO `' .
                    $reminders .
                    '` (create_datetime, update_datetime, idempotency_key, id_appointments, id_users_customer, phone, ' .
                    'appointment_datetime, scheduled_datetime, status, error) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s'),
                    self::idempotency_key((int) $appointment['id']),
                    (int) $appointment['id'],
                    (int) $appointment['id_users_customer'],
                    $recipient,
                    $start,
                    self::send_moment($start, $created_moment, $rules)->format('Y-m-d H:i:s'),
                    $recipient ? 'pending' : 'review',
                    $recipient ? null : lang('whatsapp_invalid_phone'),
                ],
            );

            $created += $this->CI->db->affected_rows();
        }

        return $created;
    }

    /**
     * SQL condition: the patient did not refuse the WhatsApp messages in the last consent that included that choice.
     * New consent versions do not ask about WhatsApp and must not overwrite an earlier choice.
     *
     * @param string $customer_column Column with the ID of the patient, quoted.
     */
    private function not_refused(string $customer_column): string
    {
        $consents = $this->CI->db->dbprefix('patient_consents');

        return "NOT EXISTS (SELECT 1 FROM `$consents` pc WHERE pc.id_users_customer = $customer_column " .
            "AND pc.whatsapp = 0 AND pc.id = (SELECT MAX(pc2.id) FROM `$consents` pc2 " .
            "WHERE pc2.id_users_customer = pc.id_users_customer AND JSON_CONTAINS_PATH(pc2.choices, 'one', '$.whatsapp') = 1))";
    }

    /** Whether the last explicit WhatsApp choice refuses messages (see not_refused()). */
    private function refused(int $customer_id): bool
    {
        $whatsapp = $this->CI->db
            ->select('whatsapp')
            ->where('id_users_customer', $customer_id)
            ->where("JSON_CONTAINS_PATH(choices, 'one', '$.whatsapp') = 1", null, false)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('patient_consents')
            ->row('whatsapp');

        return $whatsapp !== null && !(int) $whatsapp;
    }

    /**
     * Send one reminder, after checking that its appointment still wants it.
     *
     * @param array $reminder Row of whatsapp_reminders.
     * @param DateTimeImmutable $now In the time of the clinic.
     * @param array $rules See rules().
     * @param bool $by_hand Sent by hand from the list: the moment and the minimum notice are not checked.
     *
     * @return string What happened: sent, failed, cancelled, review, waiting or skipped.
     */
    private function process(array $reminder, DateTimeImmutable $now, array $rules, bool $by_hand = false): string
    {
        $appointment = $reminder['id_appointments']
            ? $this->CI->db->get_where('appointments', ['id' => $reminder['id_appointments']])->row_array()
            : null;

        // Removed, cancelled, or no longer wanted since the reminder was created (also by the patient, signing that
        // they do not want WhatsApp messages).
        if (
            !$appointment ||
            !(int) $appointment['send_whatsapp_reminder'] ||
            in_array($appointment['status'], appointment_status_free_codes(), true) ||
            $this->refused((int) $appointment['id_users_customer'])
        ) {
            $this->update($reminder['id'], ['status' => 'cancelled']);

            return 'cancelled';
        }

        $start = $appointment['start_datetime'];

        if (!$by_hand) {
            // Moved since the reminder was created (it has not gone out yet): it waits for the moment of the new day.
            if ($start !== $reminder['appointment_datetime']) {
                $moment = self::send_moment($start, self::created_moment($appointment), $rules);

                $this->update($reminder['id'], [
                    'appointment_datetime' => $start,
                    'scheduled_datetime' => $moment->format('Y-m-d H:i:s'),
                ]);

                if ($moment > $now) {
                    return 'waiting';
                }
            }

            if ($now >= self::send_deadline($start, $rules)) {
                $this->update($reminder['id'], ['status' => 'review', 'error' => lang('whatsapp_too_late')]);

                return 'review';
            }
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

        $result = $this->send($recipient, self::template_parameters($appointment, $customer, $now));

        if ($result['ok']) {
            // The patient has the current day and time: a change marked before is no longer pending.
            $this->update($reminder['id'], [
                'status' => 'accepted',
                'phone' => $recipient,
                'appointment_datetime' => $appointment['start_datetime'],
                'provider_message_id' => $result['message_id'],
                'status_datetime' => date('Y-m-d H:i:s'),
                'error' => null,
                'change_to_datetime' => null,
                'change_datetime' => null,
                'change_handled_datetime' => null,
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
     * Leave for review the reminders that could not go out before the minimum notice of their appointment (as it is
     * now), and those that stayed in "processing" (a run stopped while sending, so it is not known whether the patient
     * got it).
     *
     * @param DateTimeImmutable $now In the time of the clinic.
     * @param array $rules See rules().
     *
     * @return int Reminders left for review.
     */
    private function expire_unsent(DateTimeImmutable $now, array $rules): int
    {
        $limit = $now->modify('+' . $rules['min_notice'] . ' minutes')->format('Y-m-d H:i:s');

        $late = array_column(
            $this->CI->db
                ->select('whatsapp_reminders.id')
                ->from('whatsapp_reminders')
                ->join('appointments', 'appointments.id = whatsapp_reminders.id_appointments', 'left')
                ->where('whatsapp_reminders.is_test', 0)
                ->where_in('whatsapp_reminders.status', ['pending', 'failed'])
                ->where(
                    'COALESCE(' .
                        $this->CI->db->dbprefix('appointments') .
                        '.start_datetime, ' .
                        $this->CI->db->dbprefix('whatsapp_reminders') .
                        '.appointment_datetime) <=',
                    $this->CI->db->escape($limit),
                    false,
                )
                ->get()
                ->result_array(),
            'id',
        );

        $expired = 0;

        if ($late) {
            $this->CI->db
                ->set('status', 'review')
                ->set('error', lang('whatsapp_too_late'))
                ->set('update_datetime', date('Y-m-d H:i:s'))
                ->where_in('id', $late)
                ->where_in('status', ['pending', 'failed'])
                ->update('whatsapp_reminders');

            $expired = $this->CI->db->affected_rows();
        }

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
                    self::local(new DateTimeImmutable()),
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
