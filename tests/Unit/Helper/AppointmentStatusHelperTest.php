<?php

namespace Tests\Unit\Helper;

use InvalidArgumentException;
use Tests\TestCase;

class AppointmentStatusHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['ea_test_settings']['appointment_statuses']);

        parent::tearDown();
    }

    public function testNormalizeAddsTheSystemStatusesFirstWithTheirDefaults(): void
    {
        $statuses = normalize_appointment_statuses([]);

        $this->assertSame(
            ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'],
            array_column($statuses, 'code'),
        );
        $this->assertSame('Programada', $statuses[0]['name']);
        $this->assertTrue($statuses[0]['is_system']);
    }

    public function testNormalizeOnlyLetsTheSystemStatusesBeRenamed(): void
    {
        $statuses = normalize_appointment_statuses([
            ['code' => 'cancelled', 'name' => 'Anulada', 'frees_slot' => false],
            ['code' => 'scheduled', 'name' => 'Pendiente', 'frees_slot' => true],
        ]);

        $by_code = array_column($statuses, null, 'code');

        $this->assertSame('Anulada', $by_code['cancelled']['name']);
        $this->assertTrue($by_code['cancelled']['frees_slot']);
        $this->assertSame('Pendiente', $by_code['scheduled']['name']);
        $this->assertFalse($by_code['scheduled']['frees_slot']);
    }

    public function testNormalizeFallsBackToTheDefaultNameWhenASystemStatusIsLeftEmpty(): void
    {
        $statuses = normalize_appointment_statuses([['code' => 'confirmed', 'name' => '   ']]);

        $this->assertSame('Confirmada', array_column($statuses, 'name', 'code')['confirmed']);
    }

    public function testNormalizeKeepsValidCustomStatusesAfterTheSystemOnes(): void
    {
        $statuses = normalize_appointment_statuses([
            ['code' => 'custom_abc123', 'name' => 'En sala de espera', 'frees_slot' => false],
            ['code' => 'custom_def456', 'name' => 'Anulada por la clínica', 'frees_slot' => true],
        ]);

        $this->assertSame(['custom_abc123', 'custom_def456'], array_column(array_slice($statuses, 5), 'code'));
        $this->assertTrue($statuses[6]['frees_slot']);
        $this->assertFalse($statuses[6]['is_system']);
    }

    public function testNormalizeDropsInvalidCustomStatuses(): void
    {
        $statuses = normalize_appointment_statuses([
            ['code' => 'unknown', 'name' => 'Code without the custom prefix'],
            ['code' => 'custom_UPPER', 'name' => 'Invalid characters'],
            ['code' => 'custom_empty', 'name' => ''],
            ['code' => 'custom_dup', 'name' => 'First'],
            ['code' => 'custom_dup', 'name' => 'Duplicate'],
            'not an array',
            ['name' => 'No code'],
        ]);

        $custom = array_slice($statuses, 5);

        $this->assertCount(1, $custom);
        $this->assertSame('First', $custom[0]['name']);
    }

    public function testNormalizeStripsMarkupAndLimitsTheNameLength(): void
    {
        $statuses = normalize_appointment_statuses([
            ['code' => 'custom_x', 'name' => '<b>Urgente</b>' . str_repeat('a', 100)],
        ]);

        $name = $statuses[5]['name'];

        $this->assertStringStartsWith('Urgente', $name);
        $this->assertSame(64, mb_strlen($name));
    }

    public function testTheStatusHelpersReadTheSetting(): void
    {
        setting([
            'appointment_statuses' => json_encode([
                ['code' => 'cancelled', 'name' => 'Anulada'],
                ['code' => 'custom_late', 'name' => 'Retrasada', 'frees_slot' => false],
                ['code' => 'custom_moved', 'name' => 'Movida a otra clínica', 'frees_slot' => true],
            ]),
        ]);

        $this->assertTrue(appointment_status_exists('custom_late'));
        $this->assertFalse(appointment_status_exists('Booked'));
        $this->assertSame('Anulada', appointment_status_name('cancelled'));
        $this->assertSame('Booked', appointment_status_name('Booked'));
        $this->assertSame('', appointment_status_name(null));
        $this->assertSame(['cancelled', 'custom_moved'], appointment_status_free_codes());
    }

    public function testTheStatusHelpersWorkWithAnInvalidSetting(): void
    {
        setting(['appointment_statuses' => 'not json']);

        $this->assertCount(5, appointment_statuses());
        $this->assertSame(['cancelled'], appointment_status_free_codes());
    }

    public function testNormalizeFirstVisitKeepsUnknownAsNull(): void
    {
        $this->assertNull(normalize_first_visit(null));
        $this->assertNull(normalize_first_visit(''));
    }

    public function testNormalizeFirstVisitAcceptsTheFormAndApiValues(): void
    {
        $this->assertSame(1, normalize_first_visit('1'));
        $this->assertSame(1, normalize_first_visit(true));
        $this->assertSame(0, normalize_first_visit('0'));
        $this->assertSame(0, normalize_first_visit(false));
        $this->assertSame(0, normalize_first_visit(0));
    }

    public function testNormalizeFirstVisitRejectsOtherValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        normalize_first_visit('maybe');
    }
}
