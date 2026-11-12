<?php

namespace Tests\Unit\Model;

use InvalidArgumentException;
use ReflectionClass;
use Services_model;
use Tests\TestCase;

class ServicesModelTest extends TestCase
{
    private function services_model(): Services_model
    {
        // EA_Model extends CodeIgniter's CI_Model, which must be defined before EA_Model is parsed.
        require_once BASEPATH . 'core/Model.php';
        require_once APPPATH . 'core/EA_Model.php';
        require_once APPPATH . 'models/Services_model.php';

        // Matches application/config/constants.php -- defined here rather than requiring that
        // file wholesale, which would clash with the DB_SLUG_* stand-ins tests/bootstrap.php
        // already defines for the permission helper tests.
        defined('EVENT_MINIMUM_DURATION') or define('EVENT_MINIMUM_DURATION', 5);

        // The constructor only loads CodeIgniter model/DB dependencies that validate() does not
        // need, as long as the "id"/"id_service_categories" keys (the only $this->db touches) are
        // omitted from the payload.
        return (new ReflectionClass(Services_model::class))->newInstanceWithoutConstructor();
    }

    public function testValidateThrowsWhenNameIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->services_model()->validate(['attendants_number' => 1]);
    }

    public function testValidateThrowsWhenDurationIsBelowTheMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->services_model()->validate(['name' => 'Haircut', 'duration' => 2, 'attendants_number' => 1]);
    }

    public function testValidateThrowsWhenPriceIsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->services_model()->validate(['name' => 'Haircut', 'price' => -5, 'attendants_number' => 1]);
    }

    public function testValidateThrowsWhenSlotIntervalIsBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->services_model()->validate(['name' => 'Haircut', 'slot_interval' => 0, 'attendants_number' => 1]);
    }

    public function testValidateThrowsWhenAttendantsNumberIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->services_model()->validate(['name' => 'Haircut']);
    }

    public function testValidateThrowsWhenAttendantsNumberIsBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->services_model()->validate(['name' => 'Haircut', 'attendants_number' => 0]);
    }

    public function testValidateThrowsWhenColorIsNotAHexValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->services_model()->validate([
            'name' => 'Haircut',
            'color' => 'red" onmouseover="x',
            'attendants_number' => 1,
        ]);
    }

    public function testValidatePassesForAFullyValidPayload(): void
    {
        $this->services_model()->validate(['name' => 'Haircut', 'attendants_number' => 1]);

        $this->assertTrue(true); // No exception thrown.
    }

    private function service_record(array $overrides = []): array
    {
        return array_merge(
            [
                'id' => 3,
                'name' => 'Quiropodia',
                'duration' => 30,
                'price' => 25.0,
                'currency' => 'EUR',
                'description' => null,
                'location' => null,
                'color' => '#4a90d9',
                'slot_interval' => 15,
                'attendants_number' => 1,
                'is_private' => false,
                'is_active' => true,
                'id_service_categories' => null,
            ],
            $overrides,
        );
    }

    public function testApiEncodeIncludesTheColorAndTheActiveState(): void
    {
        $service = $this->service_record(['is_active' => false]);

        $this->services_model()->api_encode($service);

        $this->assertSame('#4a90d9', $service['color']);
        $this->assertFalse($service['isActive']);
    }

    public function testApiEncodeTreatsRecordsWithoutTheActiveFieldAsActive(): void
    {
        $service = $this->service_record();
        unset($service['is_active']);

        $this->services_model()->api_encode($service);

        $this->assertTrue($service['isActive']);
    }

    public function testApiDecodeMapsTheActiveState(): void
    {
        $resource = ['name' => 'Quiropodia', 'isActive' => false];

        $this->services_model()->api_decode($resource);

        $this->assertSame(['name' => 'Quiropodia', 'is_active' => false], $resource);
    }

    public function testApiDecodeLeavesTheActiveStateUntouchedWhenItIsNotSent(): void
    {
        $resource = ['name' => 'Quiropodia'];

        $this->services_model()->api_decode($resource, ['id' => 3, 'is_active' => false]);

        $this->assertFalse($resource['is_active']);
    }
}
