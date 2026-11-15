<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Legacy visit timestamps are local calendar values, not timezone conversions. */
class Podologo_schedule
{
    public static function times(string $source, mixed $duration): array
    {
        $minutes = filter_var($duration, FILTER_VALIDATE_INT);
        if ($minutes === false || $minutes < 1) {
            throw new InvalidArgumentException('The existing service duration is invalid.');
        }
        $wall_time = substr($source, 0, 19);
        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wall_time, new DateTimeZone('UTC'));
        if (!$start || $start->format('Y-m-d H:i:s') !== $wall_time) {
            throw new InvalidArgumentException('The original visit timestamp is invalid.');
        }
        $start = $start->setTime((int) $start->format('H'), intdiv((int) $start->format('i'), 15) * 15, 0);
        return [
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $start->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s'),
        ];
    }
}
