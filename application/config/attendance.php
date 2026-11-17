<?php defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Working time register (registro de jornada)
|--------------------------------------------------------------------------
|
| Everything that comes from the law or from a collective agreement lives here, so that a change of the law or a
| clinic with another agreement needs no change in the code. Times are in minutes unless stated otherwise.
|
| attendance_statute     Values of the Estatuto de los Trabajadores, applied while the clinic has no agreement.
| attendance_limits      Range accepted for each value. The legal minimums are the floor no agreement can go below,
|                        and the legal maximums the ceiling no agreement can go above.
| attendance_agreements  Collective agreements the settings page applies in one step. Add the agreement of a clinic
|                        here with its official name and the values it sets; any value left out keeps the statute one.
| attendance_roles       Roles whose users can be employees who clock in (the administrator manages, never clocks).
|
*/

$config['attendance_statute'] = [
    'agreement' => '',
    'full_time_minutes' => 2400, // art. 34.1 ET: 40 h a week on average
    'annual_max_minutes' => 0, // set by the collective agreement, 0 when none applies
    'max_daily_minutes' => 540, // art. 34.3 ET: 9 h of ordinary work a day
    'min_rest_minutes' => 720, // art. 34.3 ET: 12 h between workdays
    'weekly_rest_minutes' => 2160, // art. 37.1 ET: a day and a half without interruption
    'weekly_rest_days' => 14, // art. 37.1 ET: accumulable over periods of up to 14 days
    'break_after_minutes' => 360, // art. 34.4 ET: continuous workday longer than 6 h
    'break_minutes' => 15, // art. 34.4 ET: at least 15 min
    'break_paid' => 0, // art. 34.4 ET: work time only when the agreement or the contract says so
    'part_time_max_interruptions' => 1, // art. 12.4.b ET: one interruption a day in a split part-time workday
    'overtime_max_minutes' => 4800, // art. 35.2 ET: 80 h a year
    'complementary_max_percent' => 30, // art. 12.5.c ET: 30 %, up to 60 % by collective agreement
    'open_alert_minutes' => 960,
    'retention_years' => 4, // art. 34.9 ET
];

$config['attendance_limits'] = [
    'full_time_minutes' => [60, 2400],
    'annual_max_minutes' => [0, 131040],
    'max_daily_minutes' => [60, 720],
    'min_rest_minutes' => [720, 1440],
    'weekly_rest_minutes' => [2160, 10080],
    'weekly_rest_days' => [7, 14],
    'break_after_minutes' => [60, 360],
    'break_minutes' => [15, 240],
    'break_paid' => [0, 1],
    'part_time_max_interruptions' => [0, 3],
    'overtime_max_minutes' => [0, 4800],
    'complementary_max_percent' => [0, 60],
    'open_alert_minutes' => [60, 2880],
    'retention_years' => [4, 30],
];

$config['attendance_agreements'] = [
    'catalonia_health' => [
        'label' => 'Establiments sanitaris de Catalunya (79000815011994)',
        'values' => [
            'agreement' =>
                'XII Conveni col·lectiu de treball d’establiments sanitaris d’hospitalització, assistència, consulta i laboratoris d’anàlisis clíniques de Catalunya (79000815011994)',
            'annual_max_minutes' => 103560, // art. 31.2: 1.726 h a year for day shifts since 2025
            'min_rest_minutes' => 720, // art. 36.1
            'weekly_rest_minutes' => 2160, // art. 36.2: 36 h
            'weekly_rest_days' => 14, // art. 36.2
            'break_after_minutes' => 300, // art. 36.3: continuous schedule of more than 5 h
            'break_minutes' => 20, // art. 36.3
            'break_paid' => 1, // art. 36.3: paid break
            'part_time_max_interruptions' => 2, // art. 48.4
            'overtime_max_minutes' => 4800, // art. 20.2
            'complementary_max_percent' => 50, // art. 48.4
        ],
    ],
];

// Art. 12.5.a ET: complementary hours can only be agreed with a part-time workday of at least 10 h a week.
$config['attendance_complementary_min_weekly_minutes'] = 600;

// Most intervals a day the schedule editor accepts, whatever the contract.
$config['attendance_max_daily_intervals'] = 4;

$config['attendance_roles'] = ['provider', 'secretary'];

// Used only when the general settings have no default timezone.
$config['attendance_timezone'] = 'Europe/Madrid';

$config['attendance_legal_reference'] = 'Estatuto de los Trabajadores, art. 34.9';
$config['attendance_legal_url'] = 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-11430#a34';
