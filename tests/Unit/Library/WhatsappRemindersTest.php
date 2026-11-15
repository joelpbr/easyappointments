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

    public function testFromTenTheAppointmentsOfTheNextDayAreDue(): void
    {
        $this->assertSame('2026-11-17', Whatsapp_reminders::due_date(self::madrid('2026-11-16 10:00:00')));
        $this->assertSame('2026-11-17', Whatsapp_reminders::due_date(self::madrid('2026-11-16 19:59:00')));
    }

    public function testNothingIsDueBeforeTenNorAfterTheWindow(): void
    {
        $this->assertNull(Whatsapp_reminders::due_date(self::madrid('2026-11-16 09:59:00')));
        $this->assertNull(Whatsapp_reminders::due_date(self::madrid('2026-11-16 20:00:00')));
    }

    public function testFridayRemindsSaturdayAndSundayRemindsMonday(): void
    {
        $this->assertSame('2026-11-21', Whatsapp_reminders::due_date(self::madrid('2026-11-20 10:15:00')));
        $this->assertSame('2026-11-23', Whatsapp_reminders::due_date(self::madrid('2026-11-22 10:15:00')));
    }

    public function testTheTenOClockIsTheOneOfMadridWhateverTheZoneOfTheServer(): void
    {
        // 09:00 UTC is 10:00 in Madrid in winter and 11:00 in summer; 08:30 UTC in summer is 10:30 in Madrid.
        $this->assertSame(
            '2026-01-16',
            Whatsapp_reminders::due_date(new DateTimeImmutable('2026-01-15 09:00:00', new DateTimeZone('UTC'))),
        );
        $this->assertSame(
            '2026-07-16',
            Whatsapp_reminders::due_date(new DateTimeImmutable('2026-07-15 08:30:00', new DateTimeZone('UTC'))),
        );
        $this->assertNull(
            Whatsapp_reminders::due_date(new DateTimeImmutable('2026-01-15 08:30:00', new DateTimeZone('UTC'))),
        );
    }

    public function testTheChangesOfTimeAndOfYearAreFollowed(): void
    {
        // The night of the change to summer time (29/03/2026) and to winter time (25/10/2026).
        $this->assertSame('2026-03-29', Whatsapp_reminders::due_date(self::madrid('2026-03-28 10:00:00')));
        $this->assertSame('2026-10-26', Whatsapp_reminders::due_date(self::madrid('2026-10-25 10:00:00')));
        $this->assertSame('2027-01-01', Whatsapp_reminders::due_date(self::madrid('2026-12-31 10:00:00')));
    }

    public function testTheWindowOfADayClosesAtEightInTheEvening(): void
    {
        $this->assertSame('2026-11-15', Whatsapp_reminders::last_closed_day(self::madrid('2026-11-16 19:59:00')));
        $this->assertSame('2026-11-16', Whatsapp_reminders::last_closed_day(self::madrid('2026-11-16 20:00:00')));
    }

    public function testAReminderIsScheduledTheDayBeforeAtTen(): void
    {
        $this->assertSame('2026-11-16 10:00:00', Whatsapp_reminders::scheduled_datetime('2026-11-17 16:30:00'));
        $this->assertSame('2026-12-31 10:00:00', Whatsapp_reminders::scheduled_datetime('2027-01-01 09:00:00'));
    }

    public function testTheKeyIsOnePerAppointmentAndDay(): void
    {
        $this->assertSame(
            Whatsapp_reminders::idempotency_key(7, '2026-11-17 09:00:00'),
            Whatsapp_reminders::idempotency_key(7, '2026-11-17 16:30:00'),
        );
        $this->assertNotSame(
            Whatsapp_reminders::idempotency_key(7, '2026-11-17 09:00:00'),
            Whatsapp_reminders::idempotency_key(7, '2026-11-18 09:00:00'),
        );
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

    public function testTheTemplateGetsNameDateAndTimeOnly(): void
    {
        $parameters = Whatsapp_reminders::template_parameters(
            ['start_datetime' => '2026-11-17 09:05:00', 'notes' => 'Private note'],
            ['first_name' => 'Pau', 'last_name' => 'Prat'],
        );

        $this->assertSame(['Pau', 'dimarts 17 de novembre', '09:05'], $parameters);

        $payload = Whatsapp_reminders::message_payload('34600123456', 'recordatori_cita', 'ca', $parameters);

        $this->assertSame('template', $payload['type']);
        $this->assertSame('34600123456', $payload['to']);
        $this->assertSame('recordatori_cita', $payload['template']['name']);
        $this->assertSame('ca', $payload['template']['language']['code']);
        $this->assertSame(
            ['Pau', 'dimarts 17 de novembre', '09:05'],
            array_column($payload['template']['components'][0]['parameters'], 'text'),
        );
    }

    public function testTheDateOfTheTemplateIsInCatalan(): void
    {
        $date = fn(string $datetime): string => Whatsapp_reminders::template_parameters(
            ['start_datetime' => $datetime],
            ['first_name' => ''],
        )[1];

        $this->assertSame("dijous 15 d'abril", $date('2027-04-15 11:45:00'));
        $this->assertSame("diumenge 1 d'agost", $date('2027-08-01 10:00:00'));
        $this->assertSame("dissabte 9 d'octubre", $date('2027-10-09 10:00:00'));
        $this->assertSame('divendres 5 de març', $date('2027-03-05 10:00:00'));
        $this->assertSame('dissabte 16 de gener', $date('2027-01-16 09:00:00'));
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

    public function testAReminderIsSentAgainByHandOnlyTheDayBefore(): void
    {
        $now = new DateTimeImmutable('2026-11-15 18:30:00', new DateTimeZone('Europe/Madrid'));

        $this->assertNull(Whatsapp_reminders::retry_blocker('2026-11-16 09:00:00', $now));
        $this->assertSame('whatsapp_not_tomorrow', Whatsapp_reminders::retry_blocker('2026-11-15 19:00:00', $now));
        $this->assertSame('whatsapp_not_tomorrow', Whatsapp_reminders::retry_blocker('2026-11-17 09:00:00', $now));

        // The day of the clinic, whatever the time zone of the server: 23:30 in UTC is already the next day in Madrid.
        $utc = new DateTimeImmutable('2026-11-15 23:30:00', new DateTimeZone('UTC'));

        $this->assertNull(Whatsapp_reminders::retry_blocker('2026-11-17 09:00:00', $utc));
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
}
