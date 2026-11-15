<?php

namespace Tests\Unit\Model;

use InvalidArgumentException;
use Pathologies_model;
use Tests\TestCase;

class PathologiesModelTest extends TestCase
{
    // A 1x1 transparent PNG.
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        // EA_Model extends CodeIgniter's CI_Model, which must be defined before EA_Model is parsed.
        require_once BASEPATH . 'core/Model.php';
        require_once APPPATH . 'core/EA_Model.php';
        require_once APPPATH . 'models/Pathologies_model.php';
    }

    public function testDecodeImageAcceptsAPng(): void
    {
        $image = Pathologies_model::decode_image('data:image/png;base64,' . self::PNG);

        $this->assertSame('png', $image['extension']);
        $this->assertSame(base64_decode(self::PNG), $image['bytes']);
    }

    public function testDecodeImageTakesTheTypeOfTheContentsNotTheAnnouncedOne(): void
    {
        $image = Pathologies_model::decode_image('data:image/jpeg;base64,' . self::PNG);

        $this->assertSame('png', $image['extension']);
    }

    public function testDecodeImageRefusesSomethingThatIsNotAPicture(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Pathologies_model::decode_image('data:image/png;base64,' . base64_encode('<svg onload="alert(1)"></svg>'));
    }

    public function testDecodeImageRefusesAnythingButADataUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Pathologies_model::decode_image('https://example.com/icon.png');
    }

    public function testDecodeImageRefusesAPictureThatIsTooLarge(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $bytes = base64_decode(self::PNG) . str_repeat("\0", Pathologies_model::MAX_IMAGE_BYTES);

        Pathologies_model::decode_image('data:image/png;base64,' . base64_encode($bytes));
    }
}
