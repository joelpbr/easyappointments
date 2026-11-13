<?php

namespace Tests\Unit\Library;

use Holiday_calendar;
use Tests\TestCase;

class HolidayCalendarTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'libraries/Holiday_calendar.php';
    }

    public function testEasterSunday(): void
    {
        $this->assertSame('2025-04-20', Holiday_calendar::easter(2025));
        $this->assertSame('2026-04-05', Holiday_calendar::easter(2026));
        $this->assertSame('2027-03-28', Holiday_calendar::easter(2027));
    }

    public function testNationalHolidaysIncludeGoodFriday(): void
    {
        $holidays = array_column(Holiday_calendar::national(2026), 'name', 'date');

        $this->assertCount(10, $holidays);
        $this->assertSame('Viernes Santo', $holidays['2026-04-03']);
        $this->assertSame('Fiesta Nacional de España', $holidays['2026-10-12']);
    }

    public function testCataloniaIncludesEasterMondayAndItsFixedDays(): void
    {
        $holidays = array_column(Holiday_calendar::regional(2026, 'CT'), 'name', 'date');

        $this->assertSame(['2026-04-06', '2026-06-24', '2026-09-11', '2026-12-26'], array_keys($holidays));
    }

    public function testMadridIncludesHolyThursday(): void
    {
        $this->assertContains('2026-04-02', array_column(Holiday_calendar::regional(2026, 'MD'), 'date'));
        $this->assertSame([], Holiday_calendar::regional(2026, null));
    }
}
