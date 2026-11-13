<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

class PhoneHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'helpers/phone_helper.php';
    }

    public function testSpanishNumbersGetTheCountryPrefixWhateverTheFormat(): void
    {
        $this->assertSame('+34600123456', normalize_phone('600 12 34 56'));
        $this->assertSame('+34600123456', normalize_phone('600-123-456'));
        $this->assertSame('+34936123456', normalize_phone('(93) 612.34.56'));
        $this->assertSame('+34600123456', normalize_phone('+34 600 123 456'));
        $this->assertSame('+34600123456', normalize_phone('0034 600123456'));
    }

    public function testForeignNumbersKeepTheirPrefix(): void
    {
        $this->assertSame('+33612345678', normalize_phone('+33 6 12 34 56 78'));
        $this->assertSame('+33612345678', normalize_phone('0033612345678'));
    }

    public function testNumbersOfUnknownFormatKeepTheirDigits(): void
    {
        $this->assertSame('112', normalize_phone('112'));
        $this->assertSame('12345678', normalize_phone('1234 5678'));
    }

    public function testEmptyValuesGiveNull(): void
    {
        $this->assertNull(normalize_phone(null));
        $this->assertNull(normalize_phone(''));
        $this->assertNull(normalize_phone(' - '));
    }
}
