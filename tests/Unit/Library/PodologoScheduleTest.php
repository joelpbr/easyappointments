<?php
namespace Tests\Unit\Library;
use PHPUnit\Framework\TestCase;
use Podologo_schedule;
use InvalidArgumentException;
class PodologoScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Podologo_schedule.php';
    }
    public function testRoundsBackAndUsesEachServiceDuration(): void
    {
        foreach (
            [15 => '2026-11-12 10:30:00', 30 => '2026-11-12 10:45:00', 60 => '2026-11-12 11:15:00']
            as $duration => $end
        ) {
            self::assertSame(
                ['start_datetime' => '2026-11-12 10:15:00', 'end_datetime' => $end],
                Podologo_schedule::times('2026-11-12 10:29:43.606000', $duration),
            );
        }
    }
    public function testExactQuarterAndMidnightStayOnTheOriginalDate(): void
    {
        self::assertSame('2026-01-01 10:15:00', Podologo_schedule::times('2026-01-01 10:15:00', 30)['start_datetime']);
        self::assertSame('2026-01-01 00:00:00', Podologo_schedule::times('2026-01-01 00:00:00', 30)['start_datetime']);
        self::assertSame(
            ['start_datetime' => '2026-12-31 23:45:00', 'end_datetime' => '2027-01-01 00:15:00'],
            Podologo_schedule::times('2026-12-31 23:59:59', 30),
        );
    }
    public function testWallClockValuesDoNotShiftWithServerTimezoneOrDst(): void
    {
        $before = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Madrid');
            self::assertSame(
                '2026-03-29 02:15:00',
                Podologo_schedule::times('2026-03-29 02:29:00', '15')['start_datetime'],
            );
        } finally {
            date_default_timezone_set($before);
        }
    }
    public function testInvalidServiceDurationCannotChangeAnAppointment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Podologo_schedule::times('2026-01-01 10:00:00', 0);
    }
    public function testInvalidDateCannotBeSilentlyRolledOver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Podologo_schedule::times('2026-02-30 10:00:00', 30);
    }
}
