<?php
namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;

class LanguageHelperTest extends TestCase
{
    public function testCatalanAndSpanishRemainAvailable(): void
    {
        self::assertSame('catalan', normalize_language('catalan'));
        self::assertSame('spanish', normalize_language('spanish'));
    }

    public function testRemovedMalformedAndEmptyPreferencesUseCatalan(): void
    {
        foreach (['english', 'french', '', null, 'ca', 'es', '../english', ['spanish'], false] as $value) {
            self::assertSame('catalan', normalize_language($value));
        }
    }

    public function testOnlyTheTwoSupportedTranslationDirectoriesAreShipped(): void
    {
        $languages = array_map('basename', glob(APPPATH . 'language/*', GLOB_ONLYDIR));
        sort($languages);
        self::assertSame(['catalan', 'spanish'], $languages);
    }

    public function testBothLanguagesContainTheSameApplicationLabels(): void
    {
        $read = static function ($file) {
            $lang = [];
            require $file;
            return $lang;
        };
        $catalan = array_keys($read(APPPATH . 'language/catalan/translations_lang.php'));
        $spanish = array_keys($read(APPPATH . 'language/spanish/translations_lang.php'));
        sort($catalan);
        sort($spanish);
        self::assertSame($catalan, $spanish);
    }
}
