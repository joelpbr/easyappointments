<?php defined('BASEPATH') or exit('No direct script access allowed');

// Shared by the settings navigation and overview.
return [
    'clinic' => [
        'label' => 'settings_group_clinic',
        'description' => 'settings_group_clinic_hint',
        'icon' => 'fa-clinic-medical',
        'items' => [
            ['general_settings', 'general_settings'],
            ['business_settings', 'business_logic'],
            ['booking_settings', 'booking_settings'],
            ['attendance_settings', 'att_settings', true],
        ],
    ],
    'connections' => [
        'label' => 'settings_group_connections',
        'description' => 'settings_group_connections_hint',
        'icon' => 'fa-plug',
        'items' => [
            ['whatsapp_settings', 'whatsapp_reminders'],
            ['google_calendar_settings', 'google_calendar'],
            ['caldav_settings', 'caldav'],
            ['webhooks', 'webhooks'],
            ['api_settings', 'api'],
        ],
    ],
    'privacy' => [
        'label' => 'settings_group_privacy',
        'description' => 'settings_group_privacy_hint',
        'icon' => 'fa-shield-alt',
        'items' => [
            ['legal_settings', 'legal_contents'],
            ['patient_consents', 'consents_admin', true],
            ['altcha_settings', 'altcha'],
        ],
    ],
    'maintenance' => [
        'label' => 'settings_group_maintenance',
        'description' => 'settings_group_maintenance_hint',
        'icon' => 'fa-tools',
        'items' => [['backups', 'backups', true], ['activity_log', 'activity_log', true]],
    ],
];
