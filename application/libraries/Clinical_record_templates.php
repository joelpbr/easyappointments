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
 * Clinical record templates library.
 *
 * Each treatment has a record template (services.record_template) that sets what the provider writes down in a visit
 * of that treatment:
 *
 * - feet: the feet drawing and observations (chiropody, wound care, onychoplasty...).
 * - surgery: the feet drawing, observations, reason for the visit, diagnosis, treatment and the price of the surgery,
 *   which becomes the price of the visit.
 * - exploration: reason for the visit, diagnosis and treatment.
 * - silicone: reason for the visit, diagnosis and the orthosis, whose price becomes the price of the visit.
 * - notes: observations only.
 *
 * Only admins and providers can see or edit the records, as they hold health data (see can_access()).
 *
 * @package Libraries
 */
class Clinical_record_templates
{
    public const TEMPLATE_FEET = 'feet';
    public const TEMPLATE_SURGERY = 'surgery';
    public const TEMPLATE_EXPLORATION = 'exploration';
    public const TEMPLATE_SILICONE = 'silicone';
    public const TEMPLATE_NOTES = 'notes';

    /**
     * Fields of each record template, in the order of the form.
     */
    public const TEMPLATES = [
        self::TEMPLATE_FEET => ['observations'],
        self::TEMPLATE_SURGERY => ['reason', 'diagnosis', 'treatment', 'observations'],
        self::TEMPLATE_EXPLORATION => ['reason', 'diagnosis', 'treatment'],
        self::TEMPLATE_SILICONE => ['reason', 'diagnosis', 'orthosis'],
        self::TEMPLATE_NOTES => ['observations'],
    ];

    /**
     * Templates that show the feet drawing.
     */
    public const TEMPLATES_WITH_FEET = [self::TEMPLATE_FEET, self::TEMPLATE_SURGERY];

    /**
     * Orthoses of the silicone template and their price.
     */
    public const ORTHOSES = [
        'crest' => 30.0,
        'interdigital_1' => 27.0,
        'interdigital_4' => 25.0,
        'omega' => 30.0,
    ];

    /**
     * Every field an appointment record can have.
     */
    public const APPOINTMENT_FIELDS = ['reason', 'diagnosis', 'treatment', 'observations', 'orthosis'];

    /**
     * Fields of the patient record.
     */
    public const PATIENT_FIELDS = [
        'birth_date',
        'referral_source',
        'profession',
        'physical_activity',
        'medical_history',
        'allergies',
    ];

    /**
     * Get a valid record template, "notes" for an unknown one.
     *
     * @param string|null $template
     *
     * @return string
     */
    public static function template(?string $template): string
    {
        return array_key_exists((string) $template, self::TEMPLATES) ? $template : self::TEMPLATE_NOTES;
    }

    /**
     * Whether a role can see and edit the clinical records.
     *
     * @param string|null $role_slug
     *
     * @return bool
     */
    public static function can_access(?string $role_slug): bool
    {
        return in_array($role_slug, [DB_SLUG_ADMIN, DB_SLUG_PROVIDER], true);
    }

    /**
     * Keep the fields of a template, trimmed, and empty the others, so that a record never keeps the fields of the
     * template its treatment had before.
     *
     * @param string $template Record template.
     * @param array $data Submitted record.
     *
     * @return array Every APPOINTMENT_FIELDS key, null when empty or not part of the template.
     *
     * @throws InvalidArgumentException When the orthosis is unknown.
     */
    public static function clean_appointment_record(string $template, array $data): array
    {
        $fields = self::TEMPLATES[self::template($template)];
        $record = [];

        foreach (self::APPOINTMENT_FIELDS as $field) {
            $value = in_array($field, $fields, true) ? trim((string) ($data[$field] ?? '')) : '';

            $record[$field] = $value === '' ? null : $value;
        }

        if ($record['orthosis'] !== null && !array_key_exists($record['orthosis'], self::ORTHOSES)) {
            throw new InvalidArgumentException('The provided orthosis is invalid: ' . $record['orthosis']);
        }

        return $record;
    }

    /**
     * Get the price that a record gives to its visit.
     *
     * @param string $template Record template.
     * @param array $record Record from clean_appointment_record().
     * @param mixed $surgery_price Price typed in a surgery record.
     *
     * @return float|null The new price, or null when the record does not set it.
     *
     * @throws InvalidArgumentException When the surgery price is not a valid amount.
     */
    public static function appointment_price(string $template, array $record, mixed $surgery_price = null): ?float
    {
        $template = self::template($template);

        if ($template === self::TEMPLATE_SILICONE) {
            return $record['orthosis'] !== null ? self::ORTHOSES[$record['orthosis']] : null;
        }

        if ($template === self::TEMPLATE_SURGERY) {
            $surgery_price = str_replace(',', '.', trim((string) $surgery_price));

            if ($surgery_price === '') {
                return null;
            }

            if (!is_numeric($surgery_price) || (float) $surgery_price < 0) {
                throw new InvalidArgumentException(lang('clinical_invalid_price'));
            }

            return round((float) $surgery_price, 2);
        }

        return null;
    }

    /**
     * Keep the patient record fields, trimmed, with null for the empty ones.
     *
     * @param array $data Submitted record.
     *
     * @return array Every PATIENT_FIELDS key.
     *
     * @throws InvalidArgumentException When the birth date is not a valid past date.
     */
    public static function clean_patient_record(array $data): array
    {
        $record = [];

        foreach (self::PATIENT_FIELDS as $field) {
            $value = trim((string) ($data[$field] ?? ''));

            $record[$field] = $value === '' ? null : $value;
        }

        if ($record['birth_date'] !== null) {
            $date = DateTime::createFromFormat('!Y-m-d', $record['birth_date']);

            if (!$date || $date->format('Y-m-d') !== $record['birth_date'] || $date > new DateTime('today')) {
                throw new InvalidArgumentException(lang('clinical_invalid_birth_date'));
            }
        }

        return $record;
    }
}
