<?php

namespace Tests\Unit\Library;

use InvalidArgumentException;
use Patient_consent;
use Tests\TestCase;

class PatientConsentTest extends TestCase
{
    private const CLINIC = [
        'name' => 'Clínica de prova',
        'address' => 'Carrer Major 1, Barcelona',
        'email' => 'clinica@prova.test',
        'phone' => '930000000',
        'dpo' => 'dpd@prova.test',
    ];

    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Patient_consent.php';
    }

    /** A PNG data URL like the one of the pad: transparent, with a stroke when asked. */
    private static function drawing(bool $stroke = true): string
    {
        $image = imagecreatetruecolor(600, 200);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        if ($stroke) {
            imagesetthickness($image, 4);
            imageline($image, 20, 150, 580, 40, imagecolorallocate($image, 0, 0, 0));
        }
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    public function testTheTextGetsTheDataOfTheClinicInEachLanguage(): void
    {
        $text = Patient_consent::text('ca', self::CLINIC);
        $all = json_encode($text, JSON_UNESCAPED_UNICODE);

        $this->assertSame('ca', $text['language']);
        $this->assertStringContainsString('Clínica de prova', $all);
        $this->assertStringContainsString('dpd@prova.test', $all);
        $this->assertStringContainsString('www.aepd.es', $all);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z]+\}/', $all);
        $this->assertArrayHasKey('images', $text['options']);
        foreach (Patient_consent::LANGUAGES as $language) {
            $this->assertStringNotContainsStringIgnoringCase(
                'whatsapp',
                json_encode(Patient_consent::text($language, self::CLINIC), JSON_UNESCAPED_UNICODE),
            );
        }

        $this->assertSame('es', Patient_consent::text('es', self::CLINIC)['language']);
        // Any other language gets the first one.
        $this->assertSame('ca', Patient_consent::text('en', self::CLINIC)['language']);
    }

    public function testOptionalConsentsAreNeverPresumed(): void
    {
        $this->assertSame(['images' => 0], Patient_consent::choices([]));
        $this->assertSame(
            ['images' => 0],
            Patient_consent::choices(['whatsapp' => true, 'images' => 'no', 'other' => true]),
        );
    }

    public function testResponsibleNamesAppearAlongsideTheirContactInBothLanguages(): void
    {
        $names = 'Sebastián Pineda Aráez i Sara Burgueño Melgarejo';
        foreach (Patient_consent::LANGUAGES as $language) {
            $text = Patient_consent::text($language, self::CLINIC + ['dpo_name' => $names]);
            $this->assertStringContainsString(
                $names . ' · ' . self::CLINIC['dpo'],
                json_encode($text, JSON_UNESCAPED_UNICODE),
            );
        }
    }

    public function testAPatientSignsWithTheirNameAndARepresentativeSaysWhoTheyAre(): void
    {
        $this->assertSame(
            ['role' => 'patient', 'name' => 'Anna Puig', 'document' => '12345678Z', 'relationship' => ''],
            Patient_consent::signer(['role' => 'patient', 'name' => ' Anna Puig ', 'document' => '12345678z']),
        );
        $this->assertSame(
            'mare',
            Patient_consent::signer([
                'role' => 'representative',
                'name' => 'Marta Puig',
                'document' => '87654321X',
                'relationship' => 'mare',
            ])['relationship'],
        );

        $this->expectException(InvalidArgumentException::class);
        Patient_consent::signer(['role' => 'representative', 'name' => 'Marta Puig', 'document' => '']);
    }

    public function testAnUnknownSignerIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Patient_consent::signer(['role' => 'neighbour', 'name' => 'Anna Puig']);
    }

    public function testTheSignatureMustBeAPngWithAStroke(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is needed to read the signature.');
        }

        $this->assertStringStartsWith("\x89PNG", Patient_consent::signature_png(self::drawing()));

        foreach ([self::drawing(false), 'data:image/png;base64,AAAA', 'not an image', ''] as $wrong) {
            try {
                Patient_consent::signature_png($wrong);
                $this->fail('An empty or wrong signature was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheFingerprintChangesWithAnythingThatIsSigned(): void
    {
        $text = Patient_consent::text('ca', self::CLINIC);
        $choices = Patient_consent::choices(['images' => true]);
        $signer = ['role' => 'patient', 'name' => 'Anna Puig', 'document' => '', 'relationship' => ''];
        $patient = ['name' => 'Anna Puig', 'document' => ''];
        $at = '2026-11-17 10:00:00';
        $fingerprint = Patient_consent::fingerprint($text, $choices, $signer, $patient, $at, 'png');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fingerprint);
        $this->assertSame($fingerprint, Patient_consent::fingerprint($text, $choices, $signer, $patient, $at, 'png'));
        $this->assertNotSame(
            $fingerprint,
            Patient_consent::fingerprint($text, ['images' => 0] + $choices, $signer, $patient, $at, 'png'),
        );
        $this->assertNotSame(
            $fingerprint,
            Patient_consent::fingerprint(
                Patient_consent::text('es', self::CLINIC),
                $choices,
                $signer,
                $patient,
                $at,
                'png',
            ),
        );
        $this->assertNotSame(
            $fingerprint,
            Patient_consent::fingerprint($text, $choices, $signer, $patient, $at, 'other'),
        );
    }

    public function testTheDocumentShowsTheChoicesAndEscapesWhatWasTyped(): void
    {
        $html = Patient_consent::document_html(
            Patient_consent::text('ca', self::CLINIC),
            ['name' => 'Anna <b>Puig</b>', 'document' => '12345678Z'],
            ['role' => 'representative', 'name' => 'Marta', 'document' => '87654321X', 'relationship' => 'mare'],
            ['whatsapp' => 1, 'images' => 0],
            ['signed_at' => '17/11/2026 10:00:00', 'collected_by' => 'Eva', 'fingerprint' => str_repeat('a', 64)],
        );

        $this->assertStringContainsString('Anna &lt;b&gt;Puig&lt;/b&gt;', $html);
        $this->assertStringNotContainsStringIgnoringCase('whatsapp', $html);
        $this->assertStringContainsString('<b>No</b>', $html);
        // Printed at the proportions of the drawing (600 x 200 when not given).
        $this->assertStringContainsString('width="300" height="100"', $html);
        $this->assertStringContainsString('en qualitat de mare', $html);
        $this->assertStringContainsString('signature.png', $html);
        $this->assertStringContainsString(str_repeat('a', 64), $html);
    }
}
