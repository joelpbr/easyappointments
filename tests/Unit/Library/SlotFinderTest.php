<?php

namespace Tests\Unit\Library;

use Slot_finder;
use Tests\TestCase;

class SlotFinderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'libraries/Slot_finder.php';
    }

    private function starts(array $slots): array
    {
        return array_map(fn(array $slot): string => substr($slot['start'], 11, 5), $slots);
    }

    public function testSlotsFollowTheGridAndFitTheWholeDuration(): void
    {
        $slots = Slot_finder::slots_in_periods('2026-11-16', [['start' => '09:00', 'end' => '10:00']], 30, 15);

        $this->assertSame(['09:00', '09:15', '09:30'], $this->starts($slots));
        $this->assertSame('2026-11-16 10:00:00', end($slots)['end']);
    }

    public function testALongerAppointmentSkipsGapsThatAreTooShort(): void
    {
        // A 45 minutes appointment does not fit in the 30 minutes gap (case 6 of the specification).
        $periods = [['start' => '09:00', 'end' => '09:30'], ['start' => '10:00', 'end' => '11:00']];

        $this->assertSame(
            ['10:00', '10:15'],
            $this->starts(Slot_finder::slots_in_periods('2026-11-16', $periods, 45, 15)),
        );
    }

    public function testAPeriodThatBeginsOffTheGridOffersItsExactStartAndThenTheGrid(): void
    {
        // e.g. after an appointment that ended at 09:25.
        $slots = Slot_finder::slots_in_periods('2026-11-16', [['start' => '09:25', 'end' => '10:30']], 25, 15);

        $this->assertSame(['09:25', '09:30', '09:45', '10:00'], $this->starts($slots));
    }

    public function testAGapThatOnlyFitsOffTheGridIsFound(): void
    {
        // A 10 minutes gap between two appointments (10:40 to 10:50) fits a 10 minutes visit.
        $slots = Slot_finder::slots_in_periods('2026-11-16', [['start' => '10:40', 'end' => '10:50']], 10, 15);

        $this->assertSame(['10:40'], $this->starts($slots));
        $this->assertSame('2026-11-16 10:50:00', $slots[0]['end']);
    }

    public function testAnOffTheGridGapThatIsTooShortIsSkipped(): void
    {
        $this->assertSame(
            [],
            Slot_finder::slots_in_periods('2026-11-16', [['start' => '10:40', 'end' => '10:48']], 10, 15),
        );
    }

    public function testTheSlotsOfSeveralPeriodsAreSortedByTime(): void
    {
        $periods = [['start' => '15:00', 'end' => '15:30'], ['start' => '09:00', 'end' => '09:30']];

        $this->assertSame(
            ['09:00', '15:00'],
            $this->starts(Slot_finder::slots_in_periods('2026-11-16', $periods, 30, 15)),
        );
    }

    private function slot(string $start, int $minutes = 30): array
    {
        return ['start' => $start, 'end' => date('Y-m-d H:i:s', strtotime($start) + $minutes * 60)];
    }

    public function testTheTimeWindowKeepsOnlyMorningOrAfternoonSlots(): void
    {
        $afternoon = ['time_from' => '14:00', 'time_to' => '21:00'];

        $this->assertFalse(Slot_finder::matches($this->slot('2026-11-19 11:30:00'), $afternoon));
        $this->assertTrue(Slot_finder::matches($this->slot('2026-11-19 16:00:00'), $afternoon));
        // The whole appointment must end inside the window.
        $this->assertFalse(Slot_finder::matches($this->slot('2026-11-19 20:45:00'), $afternoon));
    }

    public function testThePreferredTimeAcceptsTheMargin(): void
    {
        $around = ['preferred_time' => '17:30', 'margin' => 30];

        $this->assertTrue(Slot_finder::matches($this->slot('2026-11-16 17:00:00'), $around));
        $this->assertTrue(Slot_finder::matches($this->slot('2026-11-16 18:00:00'), $around));
        $this->assertFalse(Slot_finder::matches($this->slot('2026-11-16 18:15:00'), $around));
        $this->assertFalse(Slot_finder::matches($this->slot('2026-11-16 18:15:00'), ['preferred_time' => '18:00']));
        $this->assertTrue(Slot_finder::matches($this->slot('2026-11-16 18:00:00'), ['preferred_time' => '18:00']));
    }

    public function testWeekdaysLimitTheDays(): void
    {
        $thursdays = ['weekdays' => [4]];

        $this->assertTrue(Slot_finder::matches($this->slot('2026-11-19 10:00:00'), $thursdays));
        $this->assertFalse(Slot_finder::matches($this->slot('2026-11-16 10:00:00'), $thursdays));
    }

    public function testTheDistanceIsMeasuredFromThePreferredTime(): void
    {
        $this->assertSame(15, Slot_finder::distance($this->slot('2026-11-16 17:15:00'), '17:30'));
        $this->assertSame(30, Slot_finder::distance($this->slot('2026-11-16 18:00:00'), '17:30'));
        $this->assertNull(Slot_finder::distance($this->slot('2026-11-16 18:00:00'), null));
    }

    public function testSlotsAreSortedByDateThenClosenessThenPreferredProvider(): void
    {
        $slots = [
            ['start' => '2026-11-17 17:30:00', 'distance' => 0, 'provider_id' => 4, 'is_preferred_provider' => false],
            ['start' => '2026-11-16 18:00:00', 'distance' => 30, 'provider_id' => 4, 'is_preferred_provider' => false],
            ['start' => '2026-11-16 17:15:00', 'distance' => 15, 'provider_id' => 5, 'is_preferred_provider' => false],
            ['start' => '2026-11-16 17:15:00', 'distance' => 15, 'provider_id' => 6, 'is_preferred_provider' => true],
        ];

        $sorted = Slot_finder::sort($slots);

        $this->assertSame([6, 5, 4, 4], array_column($sorted, 'provider_id'));
        $this->assertSame('2026-11-17 17:30:00', end($sorted)['start']);
    }

    public function testWithoutAPreferredTimeTheSlotsAreChronological(): void
    {
        $slots = [
            [
                'start' => '2026-11-16 15:00:00',
                'distance' => null,
                'provider_id' => 4,
                'is_preferred_provider' => false,
            ],
            [
                'start' => '2026-11-16 09:00:00',
                'distance' => null,
                'provider_id' => 5,
                'is_preferred_provider' => false,
            ],
        ];

        $this->assertSame(
            ['2026-11-16 09:00:00', '2026-11-16 15:00:00'],
            array_column(Slot_finder::sort($slots), 'start'),
        );
    }
}
