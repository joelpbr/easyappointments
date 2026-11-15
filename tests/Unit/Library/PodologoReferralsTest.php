<?php
namespace Tests\Unit\Library;
use PHPUnit\Framework\TestCase;
use Podologo_referrals;
use Podologo_clinical;
class PodologoReferralsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Podologo_referrals.php';
        require_once APPPATH . 'libraries/Podologo_clinical.php';
    }
    public function testEmail2MapsToExistingProvenanceField(): void
    {
        $patient = Podologo_clinical::patient(
            ['ID' => 1, 'EMAIL2' => ' Recomanació amiga '],
            ['catalogs' => [], 'patients' => []],
        );
        self::assertSame('Recomanació amiga', $patient['referral_source']);
        self::assertArrayNotHasKey('email', $patient);
    }
    public function testExistingProvenanceIsPreservedAndNotDuplicated(): void
    {
        self::assertSame("Internet\nAmics", Podologo_referrals::merge('Internet', 'Amics'));
        self::assertSame('Internet', Podologo_referrals::merge('Internet', ' internet '));
        self::assertSame("Internet\nAmics", Podologo_referrals::merge("Internet\nAmics", 'Amics'));
        self::assertSame('Existing note', Podologo_referrals::merge('Existing note', null));
    }
}
