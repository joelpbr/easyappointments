<?php
namespace Tests\Unit\Library;
use PHPUnit\Framework\TestCase;
use Podologo_providers;
class PodologoProvidersTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Podologo_providers.php';
    }
    public function testExplicitCommentCodesAndCutoff(): void
    {
        $ids = ['sara' => 4, 'sebas' => 5, 'tonyi' => 6];
        self::assertSame(5, Podologo_providers::resolve(null, 'QUIRO S', '2026-01-01 10:00:00', $ids)['id']);
        self::assertSame(6, Podologo_providers::resolve(4, 'QUIRO T', '2024-01-01 10:00:00', $ids)['id']);
        self::assertSame(4, Podologo_providers::resolve(null, 'QUIRO T', '2023-12-31 10:00:00', $ids)['id']);
        self::assertSame(4, Podologo_providers::resolve(null, 'QUIRO', '2026-01-01 10:00:00', $ids)['id']);
        self::assertSame(4, Podologo_providers::resolve(null, '', '2012-01-01 00:00:00', $ids)['id']);
        self::assertSame(5, Podologo_providers::resolve(null, 'CURA PV S', '2026-01-01 10:00:00', $ids)['id']);
        self::assertSame(5, Podologo_providers::resolve(5, 'CT PI. FOTO', '2026-01-01 10:00:00', $ids)['id']);
        self::assertSame(4, Podologo_providers::resolve(null, 'CURA s', '2026-01-01 10:00:00', $ids)['id']);
    }
    public function testReadsOriginalClinicalCommentFieldsWithoutInterpretingIdsAsCodes(): void
    {
        $comments = Podologo_providers::comments(['QUIROPODIARECOMENDACION' => 'QUIRO S', 'QIDESPECIALISTA' => 2]);
        self::assertStringContainsString('QUIRO S', $comments);
        self::assertStringNotContainsString('2', $comments);
    }
}
