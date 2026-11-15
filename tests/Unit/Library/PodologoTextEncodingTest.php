<?php
namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Podologo_text_encoding;

class PodologoTextEncodingTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Podologo_text_encoding.php';
    }

    public function testRepairsOrdinalsAccentsAndPunctuationWithoutChangingCaseOrWhitespace(): void
    {
        self::assertSame(
            "  Ñ ñ 1º 2ª à è é í ò ó ú ü ç l·l € ‘text’ –\r\n",
            Podologo_text_encoding::repair("  Ã‘ Ã± 1Âº 2Âª Ã  Ã¨ Ã© Ã­ Ã² Ã³ Ãº Ã¼ Ã§ lÂ·l â‚¬ â€˜textâ€™ â€“\r\n"),
        );
    }

    public function testAlreadyCorrectMultilingualTextIsUntouched(): void
    {
        $text = "  NIÑA, niño; 1º 2ª 37°C, 20 €; història, al·lèrgies, São João, Œuvre; Ελληνικά 中文 🙂\r\n";
        self::assertSame($text, Podologo_text_encoding::repair($text));
    }

    public function testBothWrongEncodingsAndRepeatedConversionsAreRepairedIdempotently(): void
    {
        $text = 'Ñ ñ 1º català, français; € “text”';
        foreach (['Windows-1252', 'ISO-8859-1'] as $encoding) {
            $broken = $text;
            for ($pass = 0; $pass < 3; $pass++) {
                $broken = mb_convert_encoding($broken, 'UTF-8', $encoding);
                self::assertSame($text, Podologo_text_encoding::repair($broken));
            }
        }
        self::assertSame('sí', Podologo_text_encoding::repair(Podologo_text_encoding::repair('sÃ­')));
    }

    public function testOriginalBinaryTextSupportsBothWindows1252AndUtf8(): void
    {
        self::assertSame("  niño 1º\r\n", Podologo_text_encoding::decode("  ni\xf1o 1\xba\r\n"));
        self::assertSame("  niño 1º\r\n", Podologo_text_encoding::decode("  niño 1º\r\n"));
        self::assertSame('niño 1º', Podologo_text_encoding::decode('niÃ±o 1Âº'));
    }

    public function testDoesNotGuessIrrecoverableOrLiteralCharacters(): void
    {
        self::assertSame('Ã Â � ? 1234', Podologo_text_encoding::repair('Ã Â � ? 1234'));
    }
}
