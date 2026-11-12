<?php

namespace Tests\Unit\Helper;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CalendarHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['ea_test_settings']['calendar_slot_minutes']);

        parent::tearDown();
    }

    public function testValidateAcceptsWholeMinutesWithinTheRange(): void
    {
        $this->assertSame(5, validate_calendar_slot_minutes('5'));
        $this->assertSame(15, validate_calendar_slot_minutes(15));
        $this->assertSame(25, validate_calendar_slot_minutes('25'));
        $this->assertSame(60, validate_calendar_slot_minutes('60'));
    }

    public static function invalidMinutes(): array
    {
        return [['4'], ['61'], ['0'], ['-15'], ['12.5'], ['abc'], [''], [null]];
    }

    #[DataProvider('invalidMinutes')]
    public function testValidateRejectsValuesOutsideTheRange(mixed $minutes): void
    {
        $this->expectException(InvalidArgumentException::class);

        validate_calendar_slot_minutes($minutes);
    }

    public function testTheIntervalDefaultsToFifteenMinutes(): void
    {
        $this->assertSame(15, calendar_slot_minutes());
    }

    public function testTheIntervalIsReadFromTheSetting(): void
    {
        setting(['calendar_slot_minutes' => '30']);

        $this->assertSame(30, calendar_slot_minutes());
    }

    public function testAnInvalidStoredIntervalFallsBackToFifteenMinutes(): void
    {
        setting(['calendar_slot_minutes' => '90']);

        $this->assertSame(15, calendar_slot_minutes());
    }
}
