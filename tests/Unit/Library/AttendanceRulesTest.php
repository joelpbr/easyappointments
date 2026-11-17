<?php

namespace Tests\Unit\Library;

use Attendance_rules;
use InvalidArgumentException;
use Tests\TestCase;

class AttendanceRulesTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Attendance_rules.php';
    }

    private function entry(array $segments, bool $voided = false): array
    {
        return ['segments' => $segments, 'end_at' => $segments[count($segments) - 1]['end'], 'voided' => $voided];
    }

    private function segment(string $start, ?string $end, string $policy = 'work'): array
    {
        return ['start' => $start, 'end' => $end, 'policy' => $policy, 'kind' => $policy === 'work' ? 'work' : 'break'];
    }

    public function testSplitsMidnightAndClipsToMonth(): void
    {
        $entry = $this->entry([$this->segment('2026-10-31 22:00:00', '2026-11-01 02:00:00')]);
        $summary = Attendance_rules::summarize([$entry], '2026-11', strtotime('2026-11-02 UTC'));
        $this->assertSame(10800, $summary['counted_seconds']);
        $this->assertSame('2026-11-01', $summary['days'][0]['date']);
    }

    public function testDaylightSavingDurationUsesInstantsNotWallClock(): void
    {
        $start = Attendance_rules::local_to_utc('2026-10-25T02:30:00+02:00');
        $end = Attendance_rules::local_to_utc('2026-10-25T02:30:00+01:00');
        $summary = Attendance_rules::summarize(
            [$this->entry([$this->segment($start, $end)])],
            '2026-10',
            strtotime('2026-11-02 UTC'),
        );
        $this->assertSame(3600, $summary['counted_seconds']);
    }

    public function testRejectsNonexistentSpringClockTime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Attendance_rules::local_to_utc('2026-03-29T02:30:00+01:00');
    }

    public function testOpenSessionsAndUnknownPausesRemainProvisional(): void
    {
        $segments = [
            $this->segment('2026-11-10 08:00:00', '2026-11-10 09:00:00'),
            $this->segment('2026-11-10 09:00:00', '2026-11-10 09:15:00', 'paid'),
            $this->segment('2026-11-10 09:15:00', '2026-11-10 09:30:00', 'unpaid'),
            $this->segment('2026-11-10 09:30:00', null, 'unknown'),
        ];
        $summary = Attendance_rules::summarize(
            [$this->entry($segments)],
            '2026-11',
            strtotime('2026-11-10 10:00:00 UTC'),
        );
        $this->assertSame(4500, $summary['counted_seconds']);
        $this->assertSame(1800, $summary['totals']['unknown']);
        $this->assertTrue($summary['provisional']);
        $this->assertSame(1, $summary['open']);
    }

    public function testVoidedRowsAreExcluded(): void
    {
        $summary = Attendance_rules::summarize(
            [$this->entry([$this->segment('2026-11-10 08:00:00', null)], true)],
            '2026-11',
            time(),
        );
        $this->assertSame(0, $summary['counted_seconds']);
        $this->assertSame(0, $summary['open']);
    }

    public function testRejectsGaps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Attendance_rules::validate(
            [
                $this->segment('2026-01-01 08:00:00', '2026-01-01 09:00:00'),
                $this->segment('2026-01-01 09:01:00', '2026-01-01 10:00:00'),
            ],
            time(),
        );
    }

    public function testRejectsFutureOrNegativeIntervals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Attendance_rules::validate([$this->segment('2026-01-01 09:00:00', '2026-01-01 08:00:00')], time());
    }

    public function testRejectsFutureEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Attendance_rules::validate([$this->segment('2026-01-01 09:00:00', null)], strtotime('2026-01-01 08:00:00 UTC'));
    }

    public function testRejectsBreakAsFirstSegment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Attendance_rules::validate([$this->segment('2026-01-01 09:00:00', null, 'paid')], time());
    }

    public function testMultipleOpenSegmentsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Attendance_rules::validate(
            [$this->segment('2026-01-01 08:00:00', null), $this->segment('2026-01-01 09:00:00', null)],
            time(),
        );
    }

    public function testValidShiftAllowsSameSecondActions(): void
    {
        Attendance_rules::validate(
            [
                $this->segment('2026-01-01 08:00:00', '2026-01-01 08:00:00'),
                $this->segment('2026-01-01 08:00:00', null, 'paid'),
            ],
            time(),
        );
        $this->addToAssertionCount(1);
    }

    public function testReviewFlagsDoNotTreatSplitShiftsAsShortOvernightRest(): void
    {
        $entries = [
            ['id' => 1, 'start_at' => '2026-01-01 08:00:00'] +
            $this->entry([$this->segment('2026-01-01 08:00:00', '2026-01-01 12:00:00')]),
            ['id' => 2, 'start_at' => '2026-01-01 14:00:00'] +
            $this->entry([$this->segment('2026-01-01 14:00:00', '2026-01-01 21:00:00')]),
            ['id' => 3, 'start_at' => '2026-01-02 06:00:00'] +
            $this->entry([$this->segment('2026-01-02 06:00:00', '2026-01-02 09:00:00')]),
        ];
        $now = strtotime('2026-01-03 UTC');
        $summary = Attendance_rules::summarize($entries, '2026-01', $now);
        $flags = Attendance_rules::review_flags($entries, $summary, $now);
        $rest = array_values(array_filter($flags, fn($flag) => $flag['type'] === 'short_rest'));
        $this->assertCount(1, $rest);
        $this->assertSame(3, $rest[0]['entry_id']);
        $this->assertContains('long_day', array_column($flags, 'type'));
        $this->assertContains('missing_break', array_column($flags, 'type'));
    }

    private function auto(array $rules, string $start, string $pause, string $resume, string $end): array
    {
        return [
            'id' => 1,
            'start_at' => $start,
            'end_at' => $end,
            'voided' => false,
            'policy_snapshot' => ['rules' => $rules],
            'segments' => [
                $this->segment($start, $pause),
                ['kind' => 'break', 'policy' => 'auto', 'start' => $pause, 'end' => $resume],
                $this->segment($resume, $end),
            ],
        ];
    }

    public function testAgreementPauseIsPaidUpToItsMinutesWhenTheWorkdayIsLongEnough(): void
    {
        $rules = Attendance_rules::agreements()['catalonia_health']['values'];
        $entry = $this->auto(
            $rules,
            '2026-11-10 08:00:00',
            '2026-11-10 11:00:00',
            '2026-11-10 11:30:00',
            '2026-11-10 14:00:00',
        );
        $summary = Attendance_rules::summarize([$entry], '2026-11', strtotime('2026-11-11 UTC'));
        $this->assertSame(1200, $summary['totals']['paid']);
        $this->assertSame(600, $summary['totals']['unpaid']);
        $this->assertSame(5.5 * 3600 + 1200, (float) $summary['counted_seconds']);
        $this->assertFalse($summary['provisional']);
    }

    public function testAgreementPauseIsUnpaidInAShortWorkdayOrUnderTheStatute(): void
    {
        $short = $this->auto(
            Attendance_rules::agreements()['catalonia_health']['values'],
            '2026-11-10 08:00:00',
            '2026-11-10 09:00:00',
            '2026-11-10 09:15:00',
            '2026-11-10 11:00:00',
        );
        $statute = $this->auto(
            Attendance_rules::defaults(),
            '2026-11-11 08:00:00',
            '2026-11-11 11:00:00',
            '2026-11-11 11:20:00',
            '2026-11-11 15:00:00',
        );
        $summary = Attendance_rules::summarize([$short, $statute], '2026-11', strtotime('2026-11-12 UTC'));
        $this->assertSame(0, $summary['totals']['paid']);
        $this->assertSame(2100, $summary['totals']['unpaid']);
    }

    public function testMissingBreakFollowsTheConfiguredThreshold(): void
    {
        $entry =
            ['id' => 1, 'start_at' => '2026-11-10 08:00:00'] +
            $this->entry([$this->segment('2026-11-10 08:00:00', '2026-11-10 13:30:00')]);
        $now = strtotime('2026-11-11 UTC');
        $summary = Attendance_rules::summarize([$entry], '2026-11', $now);
        $statute = Attendance_rules::review_flags([$entry], $summary, $now);
        $agreement = Attendance_rules::review_flags(
            [$entry],
            $summary,
            $now,
            Attendance_rules::agreements()['catalonia_health']['values'],
        );
        $this->assertNotContains('missing_break', array_column($statute, 'type'));
        $this->assertContains('missing_break', array_column($agreement, 'type'));
    }

    public function testWeeklyRestAcceptsWeekendsAndFlagsTwoWeeksWithoutRest(): void
    {
        $entries = [];
        $id = 0;
        foreach (range(1, 30) as $day) {
            $date = sprintf('2026-11-%02d', $day);
            $weekday = (int) date('N', strtotime($date));
            if ($weekday <= 4 || $day >= 14) {
                $entries[] =
                    ['id' => ++$id, 'start_at' => "$date 08:00:00"] +
                    $this->entry([$this->segment("$date 08:00:00", "$date 16:00:00")]);
            }
        }
        $days = Attendance_rules::weekly_rest_days(
            $entries,
            '2026-11',
            strtotime('2026-12-01 UTC'),
            Attendance_rules::defaults(),
        );
        $this->assertCount(1, $days);
        $this->assertGreaterThanOrEqual('2026-11-20', $days[0]);
    }

    public function testScheduleRejectsOverlapsAndMoreThanThreeIntervals(): void
    {
        $split = Attendance_rules::schedule(['1' => [['09:00', '12:30'], ['15:00', '19:00']]], true);
        $this->assertSame(450, Attendance_rules::scheduled_minutes($split, 1));
        $this->assertSame(450, Attendance_rules::scheduled_minutes($split));
        $this->expectException(InvalidArgumentException::class);
        Attendance_rules::schedule(['2' => [['09:00', '12:30'], ['12:00', '19:00']]], true);
    }

    public function testScheduleRejectsMoreIntervalsThanConfigured(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Attendance_rules::schedule(
            [
                '3' => [
                    ['08:00', '09:00'],
                    ['10:00', '11:00'],
                    ['12:00', '13:00'],
                    ['14:00', '15:00'],
                    ['16:00', '17:00'],
                ],
            ],
            true,
        );
    }

    public function testTimeBeyondTheScheduleIsComplementaryForPartTime(): void
    {
        $schedule = Attendance_rules::schedule(['2' => [['09:00', '12:30'], ['15:00', '19:00']]]);
        // Tuesday 10 November 2026: scheduled 7.5 h, worked 8 h (Madrid is UTC+1).
        $entry = $this->entry([$this->segment('2026-11-10 08:00:00', '2026-11-10 16:00:00')]);
        $summary = Attendance_rules::plan(Attendance_rules::summarize([$entry], '2026-11', time()), $schedule);
        $this->assertSame(27000, $summary['days'][0]['planned']);
        $this->assertSame(1800, $summary['excess_seconds']);
        $this->assertSame(
            ['ordinary' => 27000, 'complementary' => 1800, 'overtime' => 0],
            Attendance_rules::suggest($summary, 'part'),
        );
        $this->assertSame(
            ['ordinary' => 27000, 'complementary' => 0, 'overtime' => 1800],
            Attendance_rules::suggest($summary, 'full'),
        );
    }

    public function testSettingsCannotGoBelowTheLegalMinimums(): void
    {
        $this->assertSame(720, Attendance_rules::config(['min_rest_minutes' => '600'])['min_rest_minutes']);
        $this->expectException(InvalidArgumentException::class);
        Attendance_rules::config(['break_after_minutes' => '420'], true);
    }

    public function testSettingsStoredAsTextAreRead(): void
    {
        $config = Attendance_rules::config(['break_minutes' => '20', 'annual_max_minutes' => '103560']);
        $this->assertSame(20, $config['break_minutes']);
        $this->assertSame(103560, $config['annual_max_minutes']);
    }

    public function testAgreementsKeepTheStatuteValuesTheyLeaveOut(): void
    {
        $values = Attendance_rules::agreements()['catalonia_health']['values'];
        $this->assertSame(Attendance_rules::defaults()['max_daily_minutes'], $values['max_daily_minutes']);
        $this->assertSame(20, $values['break_minutes']);
        $this->assertNotEmpty($values['agreement']);
    }

    public function testPartTimeSplitScheduleRespectsTheInterruptions(): void
    {
        $split = Attendance_rules::schedule(['1' => [['09:00', '12:30'], ['15:00', '19:00']]]);
        $twice = Attendance_rules::schedule(['1' => [['08:00', '10:00'], ['11:00', '13:00'], ['16:00', '18:00']]]);
        $this->assertTrue(Attendance_rules::interruptions_allowed($split, Attendance_rules::defaults()));
        $this->assertFalse(Attendance_rules::interruptions_allowed($twice, Attendance_rules::defaults()));
        $this->assertTrue(
            Attendance_rules::interruptions_allowed(
                $twice,
                Attendance_rules::agreements()['catalonia_health']['values'],
            ),
        );
    }

    public function testJournalSealsChainEveryRow(): void
    {
        $row = [
            'employee_id' => 5,
            'entry_id' => 9,
            'actor_id' => 5,
            'action' => 'in',
            'reason' => '',
            'before_json' => 'null',
            'after_json' => '{"id":9}',
            'created_at' => '2026-11-17 08:00:00',
        ];
        $first = Attendance_rules::seal(null, $row);
        $this->assertSame($first, Attendance_rules::seal(null, $row));
        $this->assertNotSame($first, Attendance_rules::seal(null, ['after_json' => '{"id":10}'] + $row));
        $this->assertNotSame(Attendance_rules::seal($first, $row), Attendance_rules::seal(str_repeat('0', 64), $row));
    }

    public function testDaysFollowTheTimezoneOfTheClinic(): void
    {
        Attendance_rules::set_zone('Atlantic/Canary');
        try {
            [$from] = Attendance_rules::month_bounds('2026-11');
            $this->assertSame(strtotime('2026-11-01 00:00:00 UTC'), $from);
        } finally {
            Attendance_rules::set_zone(null);
        }
        $this->assertSame('Europe/Madrid', Attendance_rules::zone());
    }

    public function testSplitShiftsClockOutInsteadOfPausing(): void
    {
        $rules = Attendance_rules::agreements()['catalonia_health']['values'];
        $split = Attendance_rules::schedule(['1' => [['09:00', '12:30'], ['15:00', '19:00']]]);
        $continuous = Attendance_rules::schedule(['1' => [['08:00', '15:00']]]);
        $this->assertFalse(Attendance_rules::pauses_allowed($split, $rules));
        $this->assertTrue(Attendance_rules::pauses_allowed($continuous, $rules));
        $this->assertTrue(Attendance_rules::pauses_allowed(Attendance_rules::schedule([]), $rules));
    }

    public function testComplementaryHoursNeedAPactAndRespectIt(): void
    {
        $profile = ['contract_type' => 'part', 'complementary_percent' => 0];
        $flags = Attendance_rules::limit_flags(
            ['counted' => 0, 'excess' => 3600, 'planned' => 36000],
            ['excess_seconds' => 3600],
            $profile,
            [],
        );
        $this->assertSame(['extra_without_pact'], array_column($flags, 'type'));
        $profile['complementary_percent'] = 5;
        $flags = Attendance_rules::limit_flags(
            ['counted' => 0, 'excess' => 3600, 'planned' => 36000],
            ['excess_seconds' => 3600],
            $profile,
            [],
        );
        $this->assertSame(['pact_exceeded'], array_column($flags, 'type'));
    }
}
