<?php

namespace Tests\Unit\Library;

use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;
use Whatsapp_reminders;

class WhatsappRemindersTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'helpers/phone_helper.php';
        require_once APPPATH . 'libraries/Whatsapp_reminders.php';
    }

    private static function madrid(string $datetime): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime, new DateTimeZone('Europe/Madrid'));
    }

    /**
     * The rules of the protocol of the clinic: the day before at 10:00, 30 minutes after the creation at the soonest,
     * nothing with less than 2 hours left.
     */
    private static function rules(array $changes = []): array
    {
        return $changes + Whatsapp_reminders::RULE_DEFAULTS;
    }

    /**
     * Get when the reminder goes out, or null when it is never sent.
     */
    private static function sent_at(string $created, string $start, array $rules = []): ?string
    {
        $rules = self::rules($rules);
        $moment = Whatsapp_reminders::send_moment($start, self::madrid($created), $rules);

        return $moment < Whatsapp_reminders::send_deadline($start, $rules) ? $moment->format('Y-m-d H:i') : null;
    }

    public function testTheExamplesOfTheProtocolOfTheClinic(): void
    {
        // Monday 16/11/2026 to Monday 23/11/2026.
        $this->assertSame('2026-11-16 10:00', self::sent_at('2026-11-16 09:00', '2026-11-17 09:00:00'));
        $this->assertSame('2026-11-16 10:15', self::sent_at('2026-11-16 09:45', '2026-11-17 09:00:00'));
        $this->assertSame('2026-11-16 11:30', self::sent_at('2026-11-16 11:00', '2026-11-17 09:00:00'));
        $this->assertSame('2026-11-16 19:00', self::sent_at('2026-11-16 18:30', '2026-11-17 09:00:00'));
        $this->assertSame('2026-11-16 19:30', self::sent_at('2026-11-16 19:00', '2026-11-17 09:00:00'));
        $this->assertSame('2026-11-19 10:00', self::sent_at('2026-11-16 09:00', '2026-11-20 16:00:00'));
        $this->assertSame('2026-11-22 10:00', self::sent_at('2026-11-20 17:00', '2026-11-23 09:00:00'));
        $this->assertSame('2026-11-22 18:30', self::sent_at('2026-11-22 18:00', '2026-11-23 09:00:00'));
        $this->assertSame('2026-11-17 09:30', self::sent_at('2026-11-17 09:00', '2026-11-17 18:00:00'));
        $this->assertNull(self::sent_at('2026-11-17 17:45', '2026-11-17 18:00:00'));
    }

    public function testNothingIsSentWithLessThanTheMinimumNoticeLeft(): void
    {
        // Booked at 16:30 for 18:00: it would go out at 17:00, with one hour left.
        $this->assertNull(self::sent_at('2026-11-17 16:30', '2026-11-17 18:00:00'));
        $this->assertSame('2026-11-17 15:30', self::sent_at('2026-11-17 15:00', '2026-11-17 18:00:00'));

        // With the minimum notice turned off, the formula alone decides.
        $this->assertSame(
            '2026-11-17 17:00',
            self::sent_at('2026-11-17 16:30', '2026-11-17 18:00:00', ['min_notice' => 0]),
        );
    }

    public function testTheRulesComeFromTheSettings(): void
    {
        $rules = ['send_time' => '18:30', 'days_before' => 2, 'booking_delay' => 60];

        $this->assertSame('2026-11-14 18:30', self::sent_at('2026-11-10 09:00', '2026-11-16 09:00:00', $rules));
        $this->assertSame('2026-11-15 10:00', self::sent_at('2026-11-15 09:00', '2026-11-16 12:00:00', $rules));
        $this->assertSame(
            '2026-11-16 08:00',
            self::sent_at('2026-11-15 09:00', '2026-11-16 12:00:00', ['days_before' => 0, 'send_time' => '08:00']),
        );
    }

    public function testAnAppointmentWhoseCreationIsNotKnownGetsTheUsualMoment(): void
    {
        $this->assertSame(
            '2026-11-16 10:00',
            Whatsapp_reminders::send_moment('2026-11-17 09:00:00', null, self::rules())->format('Y-m-d H:i'),
        );
    }

    public function testAReminderIsDueFromItsMomentUntilTheMinimumNotice(): void
    {
        $created = self::madrid('2026-11-10 12:00');
        $rules = self::rules();

        $this->assertFalse(Whatsapp_reminders::is_due('2026-11-17 16:30:00', $created, self::madrid('2026-11-16 09:59'), $rules));
        $this->assertTrue(Whatsapp_reminders::is_due('2026-11-17 16:30:00', $created, self::madrid('2026-11-16 10:00'), $rules));

        // A server that was off at 10:00 still sends it when it is back, while there is time.
        $this->assertTrue(Whatsapp_reminders::is_due('2026-11-17 16:30:00', $created, self::madrid('2026-11-17 14:29'), $rules));
        $this->assertFalse(Whatsapp_reminders::is_due('2026-11-17 16:30:00', $created, self::madrid('2026-11-17 14:30'), $rules));
    }

    public function testTheTimesAreTheOnesOfMadridWhateverTheZoneOfTheServer(): void
    {
        $created = self::madrid('2026-01-01 12:00');
        $rules = self::rules();

        // 09:00 UTC is 10:00 in Madrid in winter; 08:30 UTC in summer is 10:30 in Madrid.
        $this->assertTrue(
            Whatsapp_reminders::is_due('2026-01-16 12:00:00', $created, new DateTimeImmutable('2026-01-15 09:00', new DateTimeZone('UTC')), $rules),
        );
        $this->assertFalse(
            Whatsapp_reminders::is_due('2026-01-16 12:00:00', $created, new DateTimeImmutable('2026-01-15 08:30', new DateTimeZone('UTC')), $rules),
        );
        $this->assertTrue(
            Whatsapp_reminders::is_due('2026-07-16 12:00:00', $created, new DateTimeImmutable('2026-07-15 08:30', new DateTimeZone('UTC')), $rules),
        );
    }

    public function testTheChangesOfTimeAndOfYearAreFollowed(): void
    {
        // The night of the change to summer time (29/03/2026) and to winter time (25/10/2026).
        $this->assertSame('2026-03-28 10:00', self::sent_at('2026-03-01 10:00', '2026-03-29 09:00:00'));
        $this->assertSame('2026-10-25 10:00', self::sent_at('2026-10-01 10:00', '2026-10-26 09:00:00'));
        $this->assertSame('2026-12-31 10:00', self::sent_at('2026-12-01 10:00', '2027-01-01 12:00:00'));
    }

    public function testTheCreationIsReadInTheZoneOfTheApplication(): void
    {
        $zone = date_default_timezone_get();

        date_default_timezone_set('UTC');

        try {
            $created = Whatsapp_reminders::created_moment(['create_datetime' => '2026-11-16 08:00:00']);
        } finally {
            date_default_timezone_set($zone);
        }

        $this->assertSame(
            '2026-11-16 09:00',
            $created->setTimezone(new DateTimeZone('Europe/Madrid'))->format('Y-m-d H:i'),
        );
        $this->assertNull(Whatsapp_reminders::created_moment(['create_datetime' => null]));
    }

    public function testWrongRulesTakeTheirDefaultOrTheirLimit(): void
    {
        $rules = Whatsapp_reminders::normalize_rules([
            'send_time' => '9:5',
            'days_before' => '30',
            'booking_delay' => '-5',
            'min_notice' => 'abc',
            // The switch of the former template is gone: it is ignored.
            'template_says_tomorrow' => '1',
        ]);

        $this->assertSame('10:00', $rules['send_time']);
        $this->assertSame(7, $rules['days_before']);
        $this->assertSame(30, $rules['booking_delay']);
        $this->assertSame(120, $rules['min_notice']);
        $this->assertArrayNotHasKey('template_says_tomorrow', $rules);

        $rules = Whatsapp_reminders::normalize_rules(['send_time' => '9:05', 'days_before' => '0', 'min_notice' => '0']);

        $this->assertSame('09:05', $rules['send_time']);
        $this->assertSame(0, $rules['days_before']);
        $this->assertSame(0, $rules['min_notice']);
    }

    public function testTheKeyIsOnePerAppointmentWhateverItsDay(): void
    {
        $this->assertSame('reminder:7', Whatsapp_reminders::idempotency_key(7));
        $this->assertNotSame(Whatsapp_reminders::idempotency_key(7), Whatsapp_reminders::idempotency_key(8));
    }

    public function testTheNumberIsSentWithItsCountryCodeAndWithoutPlus(): void
    {
        $this->assertSame('34600123456', Whatsapp_reminders::recipient('600 12 34 56'));
        $this->assertSame('34600123456', Whatsapp_reminders::recipient('+34 600-123-456'));
        $this->assertSame('447700900123', Whatsapp_reminders::recipient('0044 7700 900123'));
    }

    public function testANumberWithoutCountryCodeOrEmptyCannotReceiveMessages(): void
    {
        $this->assertNull(Whatsapp_reminders::recipient(null));
        $this->assertNull(Whatsapp_reminders::recipient(''));
        $this->assertNull(Whatsapp_reminders::recipient('12345'));
    }

    public function testTheTemplateGetsNameDayAndTimeOnly(): void
    {
        $parameters = Whatsapp_reminders::template_parameters(
            ['start_datetime' => '2026-11-17 09:05:00', 'notes' => 'Private note'],
            ['first_name' => 'Pau', 'last_name' => 'Prat'],
            self::madrid('2026-11-16 10:00'),
        );

        $this->assertSame(['Pau', 'demà, dimarts 17 de novembre', '09:05'], $parameters);

        $payload = Whatsapp_reminders::message_payload('34600123456', 'recordatori_cita', 'ca', $parameters);

        $this->assertSame('template', $payload['type']);
        $this->assertSame('34600123456', $payload['to']);
        $this->assertSame('recordatori_cita', $payload['template']['name']);
        $this->assertSame('ca', $payload['template']['language']['code']);
        $this->assertSame(
            ['Pau', 'demà, dimarts 17 de novembre', '09:05'],
            array_column($payload['template']['components'][0]['parameters'], 'text'),
        );
    }

    public function testTheDaySaysTodayTomorrowOrTheDate(): void
    {
        $now = self::madrid('2026-11-17 09:30');

        $this->assertSame('avui, dimarts 17 de novembre', Whatsapp_reminders::day_text('2026-11-17 18:00:00', $now));
        $this->assertSame('demà, dimecres 18 de novembre', Whatsapp_reminders::day_text('2026-11-18 09:00:00', $now));
        $this->assertSame('el dijous 19 de novembre', Whatsapp_reminders::day_text('2026-11-19 09:00:00', $now));

        // Late in the evening in UTC it is already the next day in Madrid.
        $this->assertSame(
            'avui, dimecres 18 de novembre',
            Whatsapp_reminders::day_text('2026-11-18 09:00:00', new DateTimeImmutable('2026-11-17 23:30', new DateTimeZone('UTC'))),
        );
    }

    public function testTheDateOfTheTemplateIsInCatalan(): void
    {
        $this->assertSame("dijous 15 d'abril", Whatsapp_reminders::date_text('2027-04-15 11:45:00'));
        $this->assertSame("diumenge 1 d'agost", Whatsapp_reminders::date_text('2027-08-01 10:00:00'));
        $this->assertSame("dissabte 9 d'octubre", Whatsapp_reminders::date_text('2027-10-09 10:00:00'));
        $this->assertSame('divendres 5 de març', Whatsapp_reminders::date_text('2027-03-05 10:00:00'));
        $this->assertSame('dissabte 16 de gener', Whatsapp_reminders::date_text('2027-01-16 09:00:00'));
        $this->assertSame('-', Whatsapp_reminders::template_parameters(['start_datetime' => '2027-03-05'], [])[0]);
    }

    public function testTheKnownErrorsOfMetaAreExplained(): void
    {
        $this->assertSame(
            'whatsapp_error_131026',
            Whatsapp_reminders::error_key('HTTP 400 131026 Receiver is incapable of receiving this message'),
        );
        $this->assertSame(
            'whatsapp_error_10',
            Whatsapp_reminders::error_key('HTTP 400 10 Application does not have permission for this action'),
        );
        $this->assertSame('whatsapp_error_131049', Whatsapp_reminders::error_key('131049 Not delivered'));
        $this->assertSame('whatsapp_error_connection', Whatsapp_reminders::error_key('Connection: timed out'));
        $this->assertNull(Whatsapp_reminders::error_key('HTTP 500 999999 Unknown'));
        $this->assertNull(Whatsapp_reminders::error_key('El número no és vàlid'));
        $this->assertNull(Whatsapp_reminders::error_key(null));
    }

    public function testTheStatusOnlyMovesForward(): void
    {
        $this->assertTrue(Whatsapp_reminders::status_moves_forward('accepted', 'sent'));
        $this->assertTrue(Whatsapp_reminders::status_moves_forward('sent', 'read'));
        $this->assertFalse(Whatsapp_reminders::status_moves_forward('read', 'delivered'));
        $this->assertFalse(Whatsapp_reminders::status_moves_forward('delivered', 'sent'));
        $this->assertTrue(Whatsapp_reminders::status_moves_forward('accepted', 'failed'));
        $this->assertFalse(Whatsapp_reminders::status_moves_forward('delivered', 'failed'));
    }

    public function testOnlyTheStatusesOfMetaAreTaken(): void
    {
        $this->assertSame('delivered', Whatsapp_reminders::webhook_status('delivered'));
        $this->assertNull(Whatsapp_reminders::webhook_status('deleted'));
    }

    public function testTheWebhookOnlyTakesRequestsSignedWithTheSecret(): void
    {
        $body = '{"entry":[]}';
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'secret');

        $this->assertTrue(Whatsapp_reminders::valid_signature($body, $signature, 'secret'));
        $this->assertFalse(Whatsapp_reminders::valid_signature($body . ' ', $signature, 'secret'));
        $this->assertFalse(Whatsapp_reminders::valid_signature($body, $signature, 'other'));
        $this->assertFalse(Whatsapp_reminders::valid_signature($body, null, 'secret'));
        $this->assertFalse(Whatsapp_reminders::valid_signature($body, $signature, ''));
    }

    public function testWithTheNewTemplateAVisitBookedAfterTheUsualMomentGoesOutHalfAnHourLater(): void
    {
        // Booked on Tuesday at 11:00 for that same day at 18:00: it cannot wait for Monday at 10:00.
        $start = '2026-11-17 18:00:00';
        $this->assertSame('2026-11-17 11:30', self::sent_at('2026-11-17 11:00', $start));
        $moment = self::madrid('2026-11-17 11:30');
        $this->assertSame(
            ['scheduled', '2026-11-17 11:30'],
            [
                Whatsapp_reminders::planned_send($start, self::madrid('2026-11-17 11:00'), $moment, self::rules())[0],
                $moment->format('Y-m-d H:i'),
            ],
        );
        $this->assertSame(
            'avui, dimarts 17 de novembre',
            Whatsapp_reminders::template_parameters(['start_datetime' => $start], ['first_name' => 'Anna'], $moment)[1],
        );
    }

    public function testTheListSaysWhenEachReminderWillGoOutOrWhyNot(): void
    {
        $now = self::madrid('2026-11-17 12:00');
        $created = self::madrid('2026-11-10 09:00');

        // Usual moment still to come: the day before at 10:00.
        [$state, $moment] = Whatsapp_reminders::planned_send('2026-11-20 09:00:00', $created, $now, self::rules());
        $this->assertSame(['scheduled', '2026-11-19 10:00'], [$state, $moment->format('Y-m-d H:i')]);

        // Its moment has gone and it was not sent yet: it goes out on the next run.
        [$state, $moment] = Whatsapp_reminders::planned_send('2026-11-18 09:00:00', $created, $now, self::rules());
        $this->assertSame(['scheduled', '2026-11-17 12:00'], [$state, $moment->format('Y-m-d H:i')]);

        // Less than the minimum notice left.
        $this->assertSame(
            ['too_late', null],
            Whatsapp_reminders::planned_send('2026-11-17 13:00:00', $created, $now, self::rules()),
        );

        // A visit of the same day goes out like any other: {{2}} says "avui".
        [$state, $moment] = Whatsapp_reminders::planned_send('2026-11-17 18:00:00', $created, $now, self::rules());
        $this->assertSame(['scheduled', '2026-11-17 12:00'], [$state, $moment->format('Y-m-d H:i')]);
    }

    public function testTheTemplateTextNamesTheClinic(): void
    {
        $body = Whatsapp_reminders::template_body('Clínica de prova');
        $this->assertStringContainsString('Tens visita *{{2}}* a les *{{3}}h* a la Clínica de prova.', $body);
        $this->assertStringNotContainsString('%s', $body);
    }
}
