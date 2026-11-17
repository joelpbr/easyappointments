<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Pure calculations. All stored instants are UTC; calendar days use Europe/Madrid. */
class Attendance_rules
{
    public const ZONE = 'Europe/Madrid';

    public static function instant(string $value): int
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException(lang('att_invalid_time'));
        }
        return $date->getTimestamp();
    }

    public static function local_to_utc(string $value): string
    {
        // An explicit numeric offset avoids ambiguous repeated hours at the DST change.
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        if (
            !$date ||
            $date->format('Y-m-d\TH:i:sP') !== $value ||
            $date->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d\TH:i:sP') !== $value
        ) {
            throw new InvalidArgumentException(lang('att_invalid_time'));
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function validate(array $segments, int $now): void
    {
        if (!$segments || count($segments) > 200 || ($segments[0]['kind'] ?? '') !== 'work') {
            throw new InvalidArgumentException(lang('att_invalid_segments'));
        }
        $previous = null;
        foreach ($segments as $index => $segment) {
            if (
                !in_array($segment['kind'] ?? '', ['work', 'break'], true) ||
                !in_array($segment['policy'] ?? '', ['work', 'paid', 'unpaid', 'unknown'], true)
            ) {
                throw new InvalidArgumentException(lang('att_invalid_segments'));
            }
            if (($segment['kind'] === 'work') !== ($segment['policy'] === 'work')) {
                throw new InvalidArgumentException(lang('att_invalid_segments'));
            }
            $start = self::instant($segment['start']);
            $end = $segment['end'] === null ? null : self::instant($segment['end']);
            if (
                $start > $now ||
                ($end !== null && ($end < $start || $end > $now)) ||
                ($index > 0 && ($previous === null || $start !== $previous)) ||
                ($end === null && $index !== count($segments) - 1)
            ) {
                throw new InvalidArgumentException(lang('att_invalid_segments'));
            }
            $previous = $end;
        }
    }

    public static function month_bounds(string $month): array
    {
        if (
            !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ||
            substr($month, 0, 4) < '2000' ||
            substr($month, 0, 4) > '2100'
        ) {
            throw new InvalidArgumentException(lang('att_invalid_month'));
        }
        $start = new DateTimeImmutable($month . '-01 00:00:00', new DateTimeZone(self::ZONE));
        return [$start->getTimestamp(), $start->modify('+1 month')->getTimestamp()];
    }

    public static function review_flags(array $entries, array $summary, int $now): array
    {
        $flags = [];
        $previous = null;
        $local_day = static fn($value) => (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(self::ZONE))
            ->format('Y-m-d');
        foreach ($entries as $entry) {
            if (!empty($entry['voided'])) {
                continue;
            }
            if (!$entry['end_at'] && $now - self::instant($entry['start_at']) > 16 * 3600) {
                $flags[] = [
                    'type' => 'long_open',
                    'date' => $local_day($entry['start_at']),
                    'entry_id' => $entry['id'],
                ];
            }
            if (
                $previous &&
                $previous['end_at'] &&
                $local_day($previous['start_at']) !== $local_day($entry['start_at']) &&
                self::instant($entry['start_at']) - self::instant($previous['end_at']) < 12 * 3600
            ) {
                $flags[] = [
                    'type' => 'short_rest',
                    'date' => $local_day($entry['start_at']),
                    'entry_id' => $entry['id'],
                ];
            }
            foreach ($entry['segments'] as $segment) {
                if (
                    $segment['kind'] === 'work' &&
                    ($segment['end'] ? self::instant($segment['end']) : $now) - self::instant($segment['start']) >
                        6 * 3600
                ) {
                    $flags[] = [
                        'type' => 'long_continuous',
                        'date' => $local_day($segment['start']),
                        'entry_id' => $entry['id'],
                    ];
                    break;
                }
            }
            $previous = $entry;
        }
        foreach ($summary['days'] as $day) {
            if ($day['work'] + $day['paid'] > 9 * 3600) {
                $flags[] = ['type' => 'long_day', 'date' => $day['date'], 'entry_id' => null];
            }
        }
        return $flags;
    }

    public static function summarize(array $entries, string $month, int $now): array
    {
        [$from, $until] = self::month_bounds($month);
        $days = [];
        $totals = ['work' => 0, 'paid' => 0, 'unpaid' => 0, 'unknown' => 0];
        $open = 0;
        foreach ($entries as $entry) {
            if (!empty($entry['voided'])) {
                continue;
            }
            if ($entry['end_at'] === null) {
                $open++;
            }
            foreach ($entry['segments'] as $segment) {
                $cursor = max($from, self::instant($segment['start']));
                $end = min($until, $segment['end'] === null ? $now : self::instant($segment['end']));
                while ($cursor < $end) {
                    $local = (new DateTimeImmutable('@' . $cursor))->setTimezone(new DateTimeZone(self::ZONE));
                    $day = $local->format('Y-m-d');
                    $stop = min($end, $local->modify('tomorrow')->setTime(0, 0)->getTimestamp());
                    $days[$day] ??= ['date' => $day, 'work' => 0, 'paid' => 0, 'unpaid' => 0, 'unknown' => 0];
                    $seconds = $stop - $cursor;
                    $days[$day][$segment['policy']] += $seconds;
                    $totals[$segment['policy']] += $seconds;
                    $cursor = $stop;
                }
            }
        }
        ksort($days);
        return [
            'days' => array_values($days),
            'totals' => $totals,
            'open' => $open,
            'counted_seconds' => $totals['work'] + $totals['paid'],
            'provisional' => $open > 0 || $totals['unknown'] > 0,
        ];
    }
}
