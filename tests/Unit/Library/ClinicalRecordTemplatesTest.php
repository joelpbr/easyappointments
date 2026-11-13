<?php

namespace Tests\Unit\Library;

use Clinical_record_templates;
use InvalidArgumentException;
use Tests\TestCase;

class ClinicalRecordTemplatesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'libraries/Clinical_record_templates.php';
    }

    public function testUnknownTemplatesFallBackToNotes(): void
    {
        $this->assertSame('surgery', Clinical_record_templates::template('surgery'));
        $this->assertSame('notes', Clinical_record_templates::template('unknown'));
        $this->assertSame('notes', Clinical_record_templates::template(null));
    }

    public function testOnlyAdminsAndProvidersCanAccess(): void
    {
        $this->assertTrue(Clinical_record_templates::can_access(DB_SLUG_ADMIN));
        $this->assertTrue(Clinical_record_templates::can_access(DB_SLUG_PROVIDER));
        $this->assertFalse(Clinical_record_templates::can_access(DB_SLUG_SECRETARY));
        $this->assertFalse(Clinical_record_templates::can_access(DB_SLUG_CUSTOMER));
        $this->assertFalse(Clinical_record_templates::can_access(null));
    }

    public function testAppointmentRecordKeepsOnlyTheFieldsOfItsTemplate(): void
    {
        $record = Clinical_record_templates::clean_appointment_record('exploration', [
            'reason' => '  Pain  ',
            'diagnosis' => 'Fasciitis',
            'treatment' => '',
            'observations' => 'Not part of an exploration',
            'orthosis' => 'omega',
        ]);

        $this->assertSame(
            [
                'reason' => 'Pain',
                'diagnosis' => 'Fasciitis',
                'treatment' => null,
                'observations' => null,
                'orthosis' => null,
            ],
            $record,
        );
    }

    public function testUnknownOrthosisIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Clinical_record_templates::clean_appointment_record('silicone', ['orthosis' => 'other']);
    }

    public function testSiliconeOrthosisSetsThePrice(): void
    {
        $record = Clinical_record_templates::clean_appointment_record('silicone', ['orthosis' => 'interdigital_4']);

        $this->assertSame(25.0, Clinical_record_templates::appointment_price('silicone', $record));

        $record = Clinical_record_templates::clean_appointment_record('silicone', ['orthosis' => '']);

        $this->assertNull(Clinical_record_templates::appointment_price('silicone', $record));
    }

    public function testSurgeryPriceSetsThePrice(): void
    {
        $record = Clinical_record_templates::clean_appointment_record('surgery', []);

        $this->assertSame(150.5, Clinical_record_templates::appointment_price('surgery', $record, '150,50'));
        $this->assertNull(Clinical_record_templates::appointment_price('surgery', $record, ''));
        $this->assertNull(Clinical_record_templates::appointment_price('feet', $record, '150'));
    }

    public function testInvalidSurgeryPriceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Clinical_record_templates::appointment_price(
            'surgery',
            Clinical_record_templates::clean_appointment_record('surgery', []),
            '-5',
        );
    }

    public function testPatientRecordChecksTheBirthDate(): void
    {
        $record = Clinical_record_templates::clean_patient_record(['birth_date' => '1980-02-29', 'allergies' => ' ']);

        $this->assertSame('1980-02-29', $record['birth_date']);
        $this->assertNull($record['allergies']);

        foreach (['1981-02-29', '29/02/1980', date('Y-m-d', strtotime('+1 day'))] as $birth_date) {
            try {
                Clinical_record_templates::clean_patient_record(['birth_date' => $birth_date]);
                $this->fail('The birth date ' . $birth_date . ' was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
