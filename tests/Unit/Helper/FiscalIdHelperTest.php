<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

class FiscalIdHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'helpers/fiscal_id_helper.php';
    }

    public function testDniChecksItsLetter(): void
    {
        $this->assertSame(FISCAL_ID_VALID, validate_fiscal_id('dni', '12345678Z'));
        $this->assertSame(FISCAL_ID_VALID, validate_fiscal_id('dni', '12.345.678-z'));
        $this->assertSame(FISCAL_ID_INVALID_CHECK, validate_fiscal_id('dni', '12345678A'));
        $this->assertSame(FISCAL_ID_INVALID_FORMAT, validate_fiscal_id('dni', '1234567Z'));
        $this->assertSame(FISCAL_ID_INVALID_FORMAT, validate_fiscal_id('dni', 'X1234567L'));
    }

    public function testNieTurnsItsFirstLetterIntoADigit(): void
    {
        $this->assertSame(FISCAL_ID_VALID, validate_fiscal_id('nie', 'X1234567L'));
        $this->assertSame(FISCAL_ID_VALID, validate_fiscal_id('nie', 'Y1234567X'));
        $this->assertSame(FISCAL_ID_VALID, validate_fiscal_id('nie', 'Z1234567R'));
        $this->assertSame(FISCAL_ID_INVALID_CHECK, validate_fiscal_id('nie', 'X1234567A'));
        $this->assertSame(FISCAL_ID_INVALID_FORMAT, validate_fiscal_id('nie', '12345678Z'));
    }

    public function testEntityNifChecksItsDigitOrLetter(): void
    {
        $this->assertSame(FISCAL_ID_VALID, validate_fiscal_id('nif', 'B12345674'));
        $this->assertSame(FISCAL_ID_VALID, validate_fiscal_id('nif', 'A58818501'));
        $this->assertSame(FISCAL_ID_VALID, validate_fiscal_id('nif', 'Q2826000H'));
        $this->assertSame(FISCAL_ID_INVALID_CHECK, validate_fiscal_id('nif', 'B12345675'));
        // Entities of type Q always use a letter.
        $this->assertSame(FISCAL_ID_INVALID_CHECK, validate_fiscal_id('nif', 'Q28260008'));
        $this->assertSame(FISCAL_ID_INVALID_FORMAT, validate_fiscal_id('nif', '12345678Z'));
    }

    public function testForeignAndOtherDocumentsAreNotValidated(): void
    {
        $this->assertSame(FISCAL_ID_NOT_VALIDATED, validate_fiscal_id('passport', 'AB123456'));
        $this->assertSame(FISCAL_ID_NOT_VALIDATED, validate_fiscal_id('foreign', 'FR 12 345 678 901'));
        $this->assertSame(FISCAL_ID_NOT_VALIDATED, validate_fiscal_id('other', 'whatever'));
    }

    public function testNormalizationRemovesSeparators(): void
    {
        $this->assertSame('X1234567L', normalize_fiscal_id(' x-1234567 l '));
    }
}
