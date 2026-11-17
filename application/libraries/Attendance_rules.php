<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pure calculations. All stored instants are UTC; calendar days use the timezone of the clinic. The legal values come
 * from application/config/attendance.php, so a change of the law or another collective agreement needs no code.
 */
class Attendance_rules
{
    private static string $zone = '';

    private static ?array $law = null;

    /** Contents of application/config/attendance.php, read once (also outside the framework, e.g. in tests). */
    public static function law(): array
    {
        if (self::$law === null) {
            $config = [];
            include APPPATH . 'config/attendance.php';
            self::$law = $config;
        }
        return self::$law;
    }

    public static function zone(): string
    {
        return self::$zone ?: self::law()['attendance_timezone'];
    }

    public static function set_zone(?string $zone): void
    {
        self::$zone = $zone && in_array($zone, timezone_identifiers_list(), true) ? $zone : '';
    }

    /** Values of the Estatuto de los Trabajadores, used until the clinic sets those of its collective agreement. */
    public static function defaults(): array
    {
        return self::law()['attendance_statute'];
    }

    /** Accepted range of each value: an agreement can improve the legal minimums, never go below them. */
    public static function limits(): array
    {
        return self::law()['attendance_limits'];
    }

    /** Collective agreements with their full values: those the agreement leaves out keep the statute ones. */
    public static function agreements(): array
    {
        $agreements = [];
        foreach (self::law()['attendance_agreements'] as $key => $agreement) {
            $agreements[$key] = [
                'label' => $agreement['label'],
                'values' => self::config($agreement['values'] + self::defaults()),
            ];
        }
        return $agreements;
    }

    public static function config(array $values, bool $strict = false): array
    {
        $config = [];
        $limits = self::limits();
        foreach (self::defaults() as $key => $default) {
            $value = $values[$key] ?? $default;
            if ($key === 'agreement') {
                $config[$key] = mb_substr(trim((string) $value), 0, 512);
                continue;
            }
            [$min, $max] = $limits[$key];
            $valid =
                (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value))) &&
                (int) $value >= $min &&
                (int) $value <= $max;
            if (!$valid && $strict) {
                throw new InvalidArgumentException(lang('att_invalid_settings'));
            }
            $config[$key] = $valid ? (int) $value : $default;
        }
        return $config;
    }

    /** Weekly schedule keyed by ISO weekday (1 = Monday), with the most intervals a day of the configuration. */
    public static function schedule(mixed $value, bool $strict = false): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        $schedule = [];
        foreach (range(1, 7) as $day) {
            $intervals = is_array($value) ? $value[$day] ?? ($value[(string) $day] ?? []) : [];
            $clean = [];
            $previous = '';
            $valid = is_array($intervals) && count($intervals) <= self::law()['attendance_max_daily_intervals'];
            foreach ($valid ? $intervals : [] as $interval) {
                [$start, $end] = is_array($interval) ? array_values($interval) + [null, null] : [null, null];
                $time = '/^([01]\d|2[0-3]):[0-5]\d$/';
                if (
                    !is_string($start) ||
                    !is_string($end) ||
                    !preg_match($time, $start) ||
                    !preg_match($time, $end) ||
                    $start >= $end ||
                    $start < $previous
                ) {
                    $valid = false;
                    break;
                }
                $clean[] = [$start, $end];
                $previous = $end;
            }
            if (!$valid && $strict) {
                throw new InvalidArgumentException(lang('att_invalid_schedule'));
            }
            $schedule[$day] = $valid ? $clean : [];
        }
        return $schedule;
    }

    public static function scheduled_minutes(array $schedule, ?int $weekday = null): int
    {
        $minutes = 0;
        foreach ($weekday ? [$weekday] : range(1, 7) as $day) {
            foreach ($schedule[$day] ?? [] as [$start, $end]) {
                $minutes += self::minutes($end) - self::minutes($start);
            }
        }
        return $minutes;
    }

    /**
     * A pause belongs to a continuous stretch long enough to give right to it (art. 34.4 ET); with a split schedule
     * the employee clocks out between the stretches instead. Without a schedule, pauses stay available.
     */
    public static function pauses_allowed(array $schedule, array $config): bool
    {
        $config = self::config($config);
        $blocks = array_merge(...array_values($schedule));
        if (!$blocks) {
            return true;
        }
        foreach ($blocks as [$start, $end]) {
            if (self::minutes($end) - self::minutes($start) > $config['break_after_minutes']) {
                return true;
            }
        }
        return false;
    }

    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        return $hours * 60 + $minutes;
    }

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
            $date->setTimezone(new DateTimeZone(self::zone()))->format('Y-m-d\TH:i:sP') !== $value
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
                !in_array($segment['policy'] ?? '', ['work', 'paid', 'unpaid', 'unknown', 'auto'], true)
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
        $start = new DateTimeImmutable($month . '-01 00:00:00', new DateTimeZone(self::zone()));
        return [$start->getTimestamp(), $start->modify('+1 month')->getTimestamp()];
    }

    private static function end(array $entry, int $now): int
    {
        $last = $entry['segments'][count($entry['segments']) - 1];
        return $last['end'] === null ? $now : self::instant($last['end']);
    }

    /**
     * Turns the pauses that follow the agreement ("auto") into paid or unpaid time: when the workday lasts more than
     * the configured hours, the first minutes of pause count as work if the agreement says so; the rest never does.
     */
    public static function resolve(array $entry, int $now): array
    {
        $segments = $entry['segments'];
        if (!in_array('auto', array_column($segments, 'policy'), true)) {
            return $segments;
        }
        $rules = self::config($entry['policy_snapshot']['rules'] ?? []);
        $span = self::end($entry, $now) - self::instant($segments[0]['start']);
        $allowance =
            $span > $rules['break_after_minutes'] * 60 && $rules['break_paid'] ? $rules['break_minutes'] * 60 : 0;
        $resolved = [];
        foreach ($segments as $segment) {
            if ($segment['policy'] !== 'auto') {
                $resolved[] = $segment;
                continue;
            }
            $start = self::instant($segment['start']);
            $length = max(0, ($segment['end'] === null ? $now : self::instant($segment['end'])) - $start);
            $paid = min($allowance, $length);
            $allowance -= $paid;
            if ($paid > 0) {
                $resolved[] = array_replace($segment, [
                    'policy' => 'paid',
                    'end' => $paid === $length ? $segment['end'] : gmdate('Y-m-d H:i:s', $start + $paid),
                ]);
            }
            if ($paid < $length || $length === 0) {
                $resolved[] = array_replace($segment, [
                    'policy' => 'unpaid',
                    'start' => gmdate('Y-m-d H:i:s', $start + $paid),
                ]);
            }
        }
        return $resolved;
    }

    public static function review_flags(
        array $entries,
        array $summary,
        int $now,
        array $config = [],
        array $prior = [],
        string $month = '',
    ): array {
        $config = self::config($config);
        $flags = [];
        $previous = null;
        $local_day = static fn($value) => (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(self::zone()))
            ->format('Y-m-d');
        foreach ($entries as $entry) {
            if (!empty($entry['voided'])) {
                continue;
            }
            $start = self::instant($entry['segments'][0]['start']);
            if (!$entry['end_at'] && $now - $start > $config['open_alert_minutes'] * 60) {
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
                $start - self::instant($previous['end_at']) < $config['min_rest_minutes'] * 60
            ) {
                $flags[] = [
                    'type' => 'short_rest',
                    'date' => $local_day($entry['start_at']),
                    'entry_id' => $entry['id'],
                ];
            }
            $pauses = 0;
            foreach ($entry['segments'] as $segment) {
                if ($segment['kind'] === 'break') {
                    $pauses +=
                        ($segment['end'] ? self::instant($segment['end']) : $now) - self::instant($segment['start']);
                }
            }
            if (
                self::end($entry, $now) - $start > $config['break_after_minutes'] * 60 &&
                $pauses < $config['break_minutes'] * 60
            ) {
                $flags[] = [
                    'type' => 'missing_break',
                    'date' => $local_day($entry['start_at']),
                    'entry_id' => $entry['id'],
                ];
            }
            $previous = $entry;
        }
        foreach ($summary['days'] as $day) {
            if ($day['work'] + $day['paid'] > $config['max_daily_minutes'] * 60) {
                $flags[] = ['type' => 'long_day', 'date' => $day['date'], 'entry_id' => null];
            }
        }
        if ($month) {
            foreach (self::weekly_rest_days(array_merge($prior, $entries), $month, $now, $config) as $date) {
                $flags[] = ['type' => 'weekly_rest', 'date' => $date, 'entry_id' => null];
            }
        }
        return $flags;
    }

    /**
     * Days of the month that close a window of "weekly_rest_days" days whose uninterrupted rests of at least
     * "weekly_rest_minutes" do not add up to that rest for every week of the window (art. 37.1 ET).
     */
    public static function weekly_rest_days(array $entries, string $month, int $now, array $config): array
    {
        $config = self::config($config);
        $busy = [];
        foreach ($entries as $entry) {
            if (empty($entry['voided'])) {
                $busy[] = [self::instant($entry['segments'][0]['start']), self::end($entry, $now)];
            }
        }
        if (!$busy) {
            return [];
        }
        usort($busy, fn($a, $b) => $a[0] <=> $b[0]);
        $block = $config['weekly_rest_minutes'] * 60;
        $required = intdiv($block * $config['weekly_rest_days'], 7);
        [$from, $until] = self::month_bounds($month);
        $days = [];
        $flagged = false;
        for (
            $day = new DateTimeImmutable('@' . $from);
            ($end = $day
                ->setTimezone(new DateTimeZone(self::zone()))
                ->modify('+1 day')
                ->getTimestamp()) <= min($until, $now);
            $day = new DateTimeImmutable('@' . $end)
        ) {
            $start = (new DateTimeImmutable('@' . $end))
                ->setTimezone(new DateTimeZone(self::zone()))
                ->modify('-' . $config['weekly_rest_days'] . ' days')
                ->getTimestamp();
            $rest = 0;
            $cursor = $start;
            $worked = false;
            foreach ($busy as [$begin, $finish]) {
                if ($finish <= $start || $begin >= $end) {
                    continue;
                }
                $worked = true;
                $gap = max($start, $begin) - $cursor;
                $rest += $gap >= $block ? $gap : 0;
                $cursor = max($cursor, min($end, $finish));
            }
            $rest += $end - $cursor >= $block ? $end - $cursor : 0;
            $short = $worked && $rest < $required;
            if ($short && !$flagged) {
                $days[] = (new DateTimeImmutable('@' . ($end - 1)))
                    ->setTimezone(new DateTimeZone(self::zone()))
                    ->format('Y-m-d');
            }
            $flagged = $short;
        }
        return $days;
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
            foreach (self::resolve($entry, $now) as $segment) {
                $cursor = max($from, self::instant($segment['start']));
                $end = min($until, $segment['end'] === null ? $now : self::instant($segment['end']));
                while ($cursor < $end) {
                    $local = (new DateTimeImmutable('@' . $cursor))->setTimezone(new DateTimeZone(self::zone()));
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

    /**
     * Adds the scheduled time to each worked day and splits the counted time into the part within the schedule
     * (ordinary) and the part beyond it. Without a schedule all the counted time is ordinary.
     */
    public static function plan(array $summary, array $schedule): array
    {
        $has_schedule = self::scheduled_minutes($schedule) > 0;
        $ordinary = 0;
        foreach ($summary['days'] as &$day) {
            $counted = $day['work'] + $day['paid'];
            $weekday = (int) (new DateTimeImmutable($day['date']))->format('N');
            $day['planned'] = self::scheduled_minutes($schedule, $weekday) * 60;
            $ordinary += $has_schedule ? min($counted, $day['planned']) : $counted;
        }
        unset($day);
        $summary['ordinary_seconds'] = $ordinary;
        $summary['excess_seconds'] = $summary['counted_seconds'] - $ordinary;
        return $summary;
    }

    /** Scheduled seconds of a month, for the share of complementary hours agreed over the ordinary ones. */
    public static function planned_seconds(string $month, array $schedule): int
    {
        [$from, $until] = self::month_bounds($month);
        $seconds = 0;
        for (
            $day = (new DateTimeImmutable('@' . $from))->setTimezone(new DateTimeZone(self::zone()));
            $day->getTimestamp() < $until;
            $day = $day->modify('+1 day')
        ) {
            $seconds += self::scheduled_minutes($schedule, (int) $day->format('N')) * 60;
        }
        return $seconds;
    }

    /** Proposed split for the monthly summary: time beyond the schedule is complementary or overtime by contract. */
    public static function suggest(array $summary, string $contract_type): array
    {
        $excess = $contract_type === 'unknown' ? 0 : $summary['excess_seconds'];
        return [
            'ordinary' => $summary['counted_seconds'] - $excess,
            'complementary' => $contract_type === 'part' ? $excess : 0,
            'overtime' => $contract_type === 'full' ? $excess : 0,
        ];
    }

    /** Yearly limits, from the totals of January to the month being reviewed. */
    public static function limit_flags(array $year, array $month, array $profile, array $config): array
    {
        $config = self::config($config);
        $flags = [];
        if ($config['annual_max_minutes'] && $year['counted'] > $config['annual_max_minutes'] * 60) {
            $flags[] = 'annual_limit';
        }
        if (
            $profile['contract_type'] === 'part' &&
            $month['excess_seconds'] > 0 &&
            !$profile['complementary_percent']
        ) {
            $flags[] = 'extra_without_pact';
        }
        if (
            $profile['contract_type'] === 'part' &&
            $profile['complementary_percent'] &&
            $year['excess'] > ($year['planned'] * $profile['complementary_percent']) / 100
        ) {
            $flags[] = 'pact_exceeded';
        }
        if ($profile['contract_type'] === 'full' && $year['excess'] > $config['overtime_max_minutes'] * 60) {
            $flags[] = 'overtime_limit';
        }
        return array_map(fn($type) => ['type' => $type, 'date' => null, 'entry_id' => null], $flags);
    }

    /** A split part-time schedule has at most the interruptions a day of the rules (art. 12.4.b ET). */
    public static function interruptions_allowed(array $schedule, array $config): bool
    {
        $config = self::config($config);
        foreach ($schedule as $intervals) {
            if (count($intervals) - 1 > $config['part_time_max_interruptions']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Link of the tamper-evident chain of the journal of an employee: each row seals the previous one, so changing or
     * deleting a row directly in the database breaks every seal after it.
     */
    public static function seal(?string $previous, array $row): string
    {
        $fields = [
            (int) $row['employee_id'],
            $row['entry_id'] === null ? null : (int) $row['entry_id'],
            (int) $row['actor_id'],
            (string) $row['action'],
            (string) $row['reason'],
            $row['before_json'] === null ? null : (string) $row['before_json'],
            (string) $row['after_json'],
            (string) $row['created_at'],
        ];
        return hash('sha256', ($previous ?? '') . "\n" . json_encode($fields, JSON_UNESCAPED_UNICODE));
    }
}
