<?php

namespace Tests\Unit\Model;

use Blocked_periods_model;
use Tests\TestCase;

class BlockedPeriodOccurrencesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // EA_Model extends CodeIgniter's CI_Model, which must be defined before EA_Model is parsed.
        require_once BASEPATH . 'core/Model.php';
        require_once APPPATH . 'core/EA_Model.php';
        require_once APPPATH . 'models/Blocked_periods_model.php';
    }

    private function starts(array $period, string $from, string $to): array
    {
        return array_column(Blocked_periods_model::expand_occurrences($period, $from, $to), 'start_datetime');
    }

    public function testAPeriodWithoutRepetitionIsItsOnlyOccurrence(): void
    {
        $period = [
            'start_datetime' => '2026-12-21 00:00:00',
            'end_datetime' => '2026-12-27 23:59:00',
            'recurrence' => 'none',
        ];

        $this->assertSame(
            ['2026-12-21 00:00:00'],
            $this->starts($period, '2026-12-24 00:00:00', '2026-12-24 23:59:59'),
        );
        $this->assertSame([], $this->starts($period, '2026-12-28 00:00:00', '2026-12-31 23:59:59'));
    }

    public function testAWeeklyPeriodRepeatsUntilItsEndDate(): void
    {
        // A meeting every Monday from 9:00 to 10:00 during November.
        $period = [
            'start_datetime' => '2026-11-02 09:00:00',
            'end_datetime' => '2026-11-02 10:00:00',
            'recurrence' => 'weekly',
            'recurrence_until' => '2026-11-30',
        ];

        $occurrences = Blocked_periods_model::expand_occurrences($period, '2026-11-01 00:00:00', '2026-12-31 23:59:59');

        $this->assertSame(
            [
                '2026-11-02 09:00:00',
                '2026-11-09 09:00:00',
                '2026-11-16 09:00:00',
                '2026-11-23 09:00:00',
                '2026-11-30 09:00:00',
            ],
            array_column($occurrences, 'start_datetime'),
        );
        $this->assertSame('2026-11-30 10:00:00', end($occurrences)['end_datetime']);
    }

    public function testOnlyTheOccurrencesInTheRangeAreReturned(): void
    {
        $period = [
            'start_datetime' => '2020-01-06 15:00:00',
            'end_datetime' => '2020-01-06 16:00:00',
            'recurrence' => 'weekly',
            'recurrence_until' => null,
        ];

        $this->assertSame(
            ['2026-11-16 15:00:00'],
            $this->starts($period, '2026-11-16 00:00:00', '2026-11-16 23:59:59'),
        );
    }

    public function testAYearlyPeriodKeepsItsDate(): void
    {
        // A local holiday that closes the clinic every year.
        $period = [
            'start_datetime' => '2025-12-26 00:00:00',
            'end_datetime' => '2025-12-26 23:59:00',
            'recurrence' => 'yearly',
            'recurrence_until' => null,
        ];

        $this->assertSame(
            ['2026-12-26 00:00:00', '2027-12-26 00:00:00'],
            $this->starts($period, '2026-01-01 00:00:00', '2027-12-31 23:59:59'),
        );
    }
}
