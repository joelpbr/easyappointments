<?php

namespace Tests\Unit\Model;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class ProviderCalendarOrderTest extends TestCase
{
    public static function invalidOrders(): array
    {
        return [[0], [-1], [10000], [1.5], ['2.5'], ['first'], [''], [null]];
    }

    #[DataProvider('invalidOrders')]
    public function testInvalidCalendarOrderIsRejectedBeforeSaving(mixed $order): void
    {
        require_once BASEPATH . 'core/Model.php';
        require_once APPPATH . 'core/EA_Model.php';
        require_once APPPATH . 'models/Providers_model.php';
        $model = (new ReflectionClass(\Providers_model::class))->newInstanceWithoutConstructor();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('provider_calendar_order_invalid');
        $model->validate(['calendar_order' => $order]);
    }
}
