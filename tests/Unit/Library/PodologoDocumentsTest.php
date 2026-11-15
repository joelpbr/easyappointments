<?php
namespace Tests\Unit\Library;
use PHPUnit\Framework\TestCase;
use Podologo_documents;
class PodologoDocumentsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Podologo_documents.php';
    }
    public function testValidDocumentsUseExistingTypesAndPreserveLeadingZeros(): void
    {
        foreach (
            [
                '12.345.678-z' => ['dni', '12345678Z'],
                'X-1234567 L' => ['nie', 'X1234567L'],
                'B12345674' => ['nif', 'B12345674'],
                '00000000T' => ['dni', '00000000T'],
            ]
            as $source => [$type, $number]
        ) {
            self::assertSame(
                ['document_type' => $type, 'document_number' => $number],
                Podologo_documents::convert($source),
            );
        }
    }
    public function testMissingOrInvalidDataAreNeverInventedOrDropped(): void
    {
        self::assertNull(Podologo_documents::convert('  '));
        self::assertNull(Podologo_documents::convert(null));
        foreach (['12345678A', '12345678', 'AB 0123456', '0001234'] as $source) {
            self::assertSame(
                ['document_type' => 'other', 'document_number' => $source],
                Podologo_documents::convert($source),
            );
        }
        self::assertTrue(Podologo_documents::matches('12.345.678-z', '12345678Z'));
        self::assertFalse(Podologo_documents::matches('12345678A', '12345678Z'));
    }
}
