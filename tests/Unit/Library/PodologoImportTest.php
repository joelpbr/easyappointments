<?php
namespace Tests\Unit\Library;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Podologo_import;

class PodologoImportTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Podologo_import.php';
    }

    private function source(): array
    {
        return [
            'ID' => 42,
            'IDIMAGENCOLOCADA' => 0,
            'X_1' => -3,
            'Y_1' => -7,
            'X_2' => 13,
            'Y_2' => 9,
            'CURADO' => 1,
            'TITULOTEXTO' => 'note ',
            'TEXTOTEXTO' => "a\nb",
        ];
    }

    public function testCoordinatesAreNeverScaledRoundedClampedOrTrimmed(): void
    {
        $marker = Podologo_import::marker($this->source(), Podologo_import::diagrams(360, 300), 91, 12);
        self::assertSame([-3, -7, 13, 9], array_map(fn($key) => $marker[$key], ['x1', 'y1', 'x2', 'y2']));
        self::assertSame(91, $marker['id_appointments']);
        self::assertSame(12, $marker['id_podology_pathologies']);
        self::assertSame('note ', $marker['annotation_title']);
        self::assertSame("a\nb", $marker['annotation_text']);
        self::assertSame(1, $marker['is_healed']);
        self::assertSame(360, $marker['coordinate_width']);
        self::assertSame('fotos_pies/1.png', $marker['background_image']);
    }

    public function testRejectsFractionalCoordinatesInsteadOfSilentlyChangingThem(): void
    {
        $row = $this->source();
        $row['X_1'] = 1.5;
        $this->expectException(InvalidArgumentException::class);
        Podologo_import::marker($row, Podologo_import::diagrams(360, 300), 1, 1);
    }

    public function testRejectsUnknownViews(): void
    {
        $row = $this->source();
        $row['IDIMAGENCOLOCADA'] = 4;
        $this->expectException(InvalidArgumentException::class);
        Podologo_import::marker($row, Podologo_import::diagrams(360, 300), 1, 1);
    }

    public function testDetectsBinaryContentRegardlessOfExtension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'podologo-test-');
        try {
            $header = str_repeat("\0", 64);
            $header[0] = "\1";
            $header[18] = "\x0b";
            $header[19] = "\x80";
            file_put_contents($path, $header);
            self::assertTrue(Podologo_import::matches($path));
            file_put_contents($path, 'CREATE TABLE ea_migrations (version INT);');
            self::assertFalse(Podologo_import::matches($path));
            file_put_contents($path, 'SQLite format 3' . str_repeat("\0", 64));
            self::assertFalse(Podologo_import::matches($path));
        } finally {
            unlink($path);
        }
    }

    public function testKnownIconsAndMissingIconsRemainEditable(): void
    {
        self::assertSame('heloma.png', Podologo_import::icon('A.Heloma'));
        self::assertSame('missing.svg', Podologo_import::icon('Unmapped condition'));
    }

    public function testLegacyBinaryNotesAreDecodedWithoutChangingWhitespace(): void
    {
        self::assertSame("  café\r\n", Podologo_import::source_text(['base64' => base64_encode("  caf\xe9\r\n")]));
        self::assertSame(
            "  café niño 1º\r\n",
            Podologo_import::source_text(['base64' => base64_encode("  café niño 1º\r\n")]),
        );
        self::assertSame('NIÑA, 1º', Podologo_import::source_text('NIÃ‘A, 1Âº'));
        self::assertNull(Podologo_import::source_text(null));
    }
}
