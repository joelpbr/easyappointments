<?php
namespace Tests\Unit\Library;
use PHPUnit\Framework\TestCase;
use Podologo_unify;
class PodologoUnifyTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Podologo_unify.php';
    }
    public function testMergesEquivalentNamesButKeepsSpecificConditions(): void
    {
        $plan = Podologo_unify::pathology_plan([
            ['id' => 21, 'name' => 'Podologo 1: A.Hiperqueratosis'],
            ['id' => 14, 'name' => 'Hiperqueratosi'],
            ['id' => 22, 'name' => 'Podologo 2: Hiperqueratosis difusa'],
            ['id' => 91, 'name' => 'Podologo 71: Liquen simple crónico'],
            ['id' => 101, 'name' => 'Podologo 81: Liquen simple crónico'],
            ['id' => 16, 'name' => 'Papil·loma'],
            ['id' => 112, 'name' => 'Podologo 92: A.Papilomas'],
            ['id' => 53, 'name' => 'Podologo 33: A.Papiloma secundario'],
        ]);
        self::assertSame(14, $plan['merges'][21]);
        self::assertSame(91, $plan['merges'][101]);
        self::assertSame(16, $plan['merges'][112]);
        self::assertArrayNotHasKey(22, $plan['merges']);
        self::assertArrayNotHasKey(53, $plan['merges']);
    }
    public function testTonyiRuleIsCaseSensitiveAndStartsAt2024(): void
    {
        self::assertTrue(Podologo_unify::tonyi_comment(" T\r\n", '2024-01-01 00:00:00'));
        foreach (['QUIRO T', 'T.', 'T test', 'test T'] as $comment) {
            self::assertTrue(Podologo_unify::tonyi_comment($comment, '2026-01-01'));
        }
        foreach (['t', 'CT', 'FOTO', 'TT', 'test', ''] as $comment) {
            self::assertFalse(Podologo_unify::tonyi_comment($comment, '2026-01-01'));
        }
        self::assertFalse(Podologo_unify::tonyi_comment('T', '2023-12-31 23:59:59'));
    }
}
