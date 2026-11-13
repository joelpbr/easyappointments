<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Holiday calendar library.
 *
 * Lists the official holidays of a year: the national ones and those of a region, calculated (including the Easter
 * based ones), plus the local holidays added by hand. A holiday never blocks anything by itself: the administrator
 * decides which ones close the clinic (specification 10.4). The official calendar can move a holiday of a given year
 * (e.g. when it falls on a Sunday); those changes are added by hand as local holidays.
 *
 * @package Libraries
 */
class Holiday_calendar
{
    public const REGIONS = ['CT', 'MD'];

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Holiday_calendar constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Get the holidays of a year, sorted by date.
     *
     * @param int $year
     * @param string|null $region One of REGIONS, or null for the national holidays only.
     *
     * @return array List of ['date' => 'Y-m-d', 'name' => string, 'level' => national|regional|local, 'id' => ?int].
     */
    public function get_for_year(int $year, ?string $region): array
    {
        $holidays = array_merge(self::national($year), self::regional($year, $region));

        $local = $this->CI->db
            ->where('date >=', $year . '-01-01')
            ->where('date <=', $year . '-12-31')
            ->get('holidays')
            ->result_array();

        foreach ($local as $holiday) {
            $holidays[] = [
                'id' => (int) $holiday['id'],
                'date' => $holiday['date'],
                'name' => $holiday['name'],
                'level' => $holiday['level'],
            ];
        }

        usort($holidays, fn(array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $holidays;
    }

    /**
     * Get the national holidays of Spain.
     *
     * @param int $year
     *
     * @return array
     */
    public static function national(int $year): array
    {
        $easter = self::easter($year);

        return self::list($year, 'national', [
            '01-01' => 'Año Nuevo',
            '01-06' => 'Epifanía del Señor',
            self::shift($easter, -2) => 'Viernes Santo',
            '05-01' => 'Fiesta del Trabajo',
            '08-15' => 'Asunción de la Virgen',
            '10-12' => 'Fiesta Nacional de España',
            '11-01' => 'Todos los Santos',
            '12-06' => 'Día de la Constitución',
            '12-08' => 'Inmaculada Concepción',
            '12-25' => 'Navidad',
        ]);
    }

    /**
     * Get the usual holidays of a region (Catalonia or Madrid).
     *
     * @param int $year
     * @param string|null $region
     *
     * @return array
     */
    public static function regional(int $year, ?string $region): array
    {
        $easter = self::easter($year);

        $days = match ($region) {
            'CT' => [
                self::shift($easter, 1) => 'Lunes de Pascua',
                '06-24' => 'Sant Joan',
                '09-11' => 'Diada Nacional de Catalunya',
                '12-26' => 'Sant Esteve',
            ],
            'MD' => [
                self::shift($easter, -3) => 'Jueves Santo',
                '05-02' => 'Fiesta de la Comunidad de Madrid',
            ],
            default => [],
        };

        return self::list($year, 'regional', $days);
    }

    /**
     * Get the date of Easter Sunday (Gregorian calendar, anonymous algorithm).
     *
     * @param int $year
     *
     * @return string Y-m-d
     */
    public static function easter(int $year): string
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Move a date some days, keeping only its month and day.
     */
    private static function shift(string $date, int $days): string
    {
        return date('m-d', strtotime($date . ' ' . ($days >= 0 ? '+' : '') . $days . ' days'));
    }

    /**
     * Turn a map of month-day => name into holidays of a year.
     */
    private static function list(int $year, string $level, array $days): array
    {
        $holidays = [];

        foreach ($days as $month_day => $name) {
            $holidays[] = ['id' => null, 'date' => $year . '-' . $month_day, 'name' => $name, 'level' => $level];
        }

        return $holidays;
    }
}
