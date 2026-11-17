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
        $this->assertContains('long_continuous', array_column($flags, 'type'));
    }
}
