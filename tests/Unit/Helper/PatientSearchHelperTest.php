<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

class PatientSearchHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'helpers/patient_search_helper.php';
    }

    public function testTheFirstNameAndTheLastNameAreNeeded(): void
    {
        $this->assertTrue(is_specific_patient_search('María García'));
        $this->assertTrue(is_specific_patient_search('ma ga'));
        $this->assertTrue(is_specific_patient_search('  Mª   José  '));
    }

    public function testASingleWordOrOneLetterWordsAreTooBroad(): void
    {
        $this->assertFalse(is_specific_patient_search('María'));
        $this->assertFalse(is_specific_patient_search('m g'));
        $this->assertFalse(is_specific_patient_search('María G'));
        $this->assertFalse(is_specific_patient_search(''));
    }

    public function testSixDigitsOfThePhoneAreNeeded(): void
    {
        $this->assertTrue(is_specific_patient_search('655 918'));
        $this->assertTrue(is_specific_patient_search('+34 655918122'));
        $this->assertFalse(is_specific_patient_search('65591'));
        $this->assertFalse(is_specific_patient_search('6-5'));
    }
}
