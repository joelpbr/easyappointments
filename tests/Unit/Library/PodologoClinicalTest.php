<?php
namespace Tests\Unit\Library;
use PHPUnit\Framework\TestCase;
use Podologo_clinical;
use Clinical_record_templates;
class PodologoClinicalTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'libraries/Podologo_clinical.php';
        require_once APPPATH . 'libraries/Clinical_record_templates.php';
    }
    public function testOnlyOriginalTemplatesExist(): void
    {
        self::assertSame(
            ['feet', 'surgery', 'exploration', 'silicone', 'notes'],
            array_keys(Clinical_record_templates::TEMPLATES),
        );
    }
    public function testEqualFeetFormFlagIsExcludedWithoutRemovingClinicalContent(): void
    {
        $record = Podologo_clinical::record(
            ['QUIROPODIARECOMENDACION' => 'Clinical note', 'PIESIGUALESBIO' => 1],
            '',
            'feet',
        );
        self::assertSame('Clinical note', $record['observations']);
        self::assertSame(
            'Clinical note',
            Podologo_clinical::remove_equal_feet_flag("Piesigualesbio: 1\n\nClinical note"),
        );
        self::assertSame(
            "Before\n\nAfter",
            Podologo_clinical::remove_equal_feet_flag("Before\n\nPiesigualesbio: 1\n\nAfter"),
        );
        self::assertNull(Podologo_clinical::remove_equal_feet_flag('Piesigualesbio: 1'));
        self::assertSame(
            'Discuss Piesigualesbio: 1 later',
            Podologo_clinical::remove_equal_feet_flag('Discuss Piesigualesbio: 1 later'),
        );
    }
    public function testUserExampleIsOnlyTheOriginalClinicalSentence(): void
    {
        $text = 'OC CT PI. PAUTO MUPIROCINA';
        $row = [
            'ID' => 98,
            'IDPACIENTE' => 4,
            'QUIROPODIA' => 1,
            'QIDESPECIALISTA' => 2,
            'CIDESPECIALISTA' => 3,
            'BIDESPECIALISTA' => 4,
            'PIDESPECIALISTA' => 5,
            'QUIROPODIARECOMENDACION' => $text,
        ];
        $record = Podologo_clinical::record($row, '', 'feet');
        self::assertSame($text, $record['observations']);
        self::assertSame('', $record['reason']);
        self::assertSame('', $record['diagnosis']);
        self::assertSame('', $record['treatment']);
    }
    public function testCleaningPreviousConversionPreservesOnlyOriginalExampleAndLaterNotes(): void
    {
        require_once APPPATH . 'libraries/Podologo_clinical_v2.php';
        $row = ['QUIROPODIA' => 1, 'QIDESPECIALISTA' => 2, 'QUIROPODIARECOMENDACION' => 'OC CT PI. PAUTO MUPIROCINA'];
        $previous = \Podologo_clinical_v2::record($row, '', 'feet');
        $plain = Podologo_clinical::record($row, '', 'feet');
        self::assertStringContainsString('Qidespecialista: 2', $previous['observations']);
        self::assertSame(
            'OC CT PI. PAUTO MUPIROCINA',
            Podologo_clinical::merge($previous['observations'], $plain['observations'], $previous['observations']),
        );
        $withLaterNote = Podologo_clinical::merge(
            $previous['observations'] . "\n\nNota posterior",
            $plain['observations'],
            $previous['observations'],
        );
        self::assertStringContainsString('Nota posterior', $withLaterNote);
        self::assertStringNotContainsString('Qidespecialista', $withLaterNote);
        self::assertStringNotContainsString('Tractament:', $withLaterNote);
    }
    public function testMultipleOriginalNarrativesAreJoinedWithoutAddedLabelsOrDuplicates(): void
    {
        $row = [
            'MOTIVOVISITABIO' => 'Pain',
            'BIOMECANICADIAGNOSTICO' => 'Fasciitis',
            'BIOMECANICARECOMENDACION' => 'Exercises',
            'EVOLUCIONBIO' => 'Improving',
            'OBSERVDESCI' => 'Left foot findings',
            'QIDESPECIALISTA' => 2,
        ];
        $record = Podologo_clinical::record($row, 'Improving', 'feet');
        self::assertSame("Improving\n\nLeft foot findings\n\nPain\n\nFasciitis\n\nExercises", $record['observations']);
        foreach (['notes', 'exploration', 'surgery', 'silicone'] as $template) {
            $saved = Clinical_record_templates::clean_appointment_record(
                $template,
                Podologo_clinical::record($row, '', $template),
            );
            $all = implode("\n", array_filter($saved));
            foreach (['Pain', 'Fasciitis', 'Exercises', 'Improving', 'Left foot findings'] as $original) {
                self::assertStringContainsString($original, $all);
            }
            foreach (['Qidespecialista', 'Tractament:', 'Exploració biomecànica:', 'Observacions:'] as $added) {
                self::assertStringNotContainsString($added, $all);
            }
        }
    }
    public function testPrescribedCareOverridesGeneralChiropody(): void
    {
        $row = ['QUIROPODIA' => 1, 'DESCRIPCION' => 'Control'];
        foreach (
            ['Cura PV 1ª' => 'Cura PV', 'Cura PV' => 'Cura PV', 'Cura' => 'Cura', 'aQuiropodia' => 'Quiropodia']
            as $text => $expected
        ) {
            self::assertSame($expected, Podologo_clinical::classification($row, $text)['name']);
        }
        self::assertSame(
            'Cura PV',
            Podologo_clinical::classification(['QUIROPODIA' => 1, 'QUIROPODIARECOMENDACION' => 'Cura PV'])['name'],
        );
    }
    public function testFewerFieldsNeverDiscardSourceText(): void
    {
        $row = [
            'QUIROPODIA' => 1,
            'MOTIVOVISITAQUIRO' => 'Pain',
            'QUIROPODIADIAGNOSTICO' => 'Heloma',
            'QUIROPODIARECOMENDACION' => 'Cura PV',
            'EVOLUCIONQUIRO' => 'Improved',
            'OBSERVDESCI' => 'Examination findings',
        ];
        foreach (['feet', 'surgery', 'exploration', 'silicone', 'notes'] as $template) {
            $mapped = Podologo_clinical::record($row, 'Linked treatment', $template);
            $saved = Clinical_record_templates::clean_appointment_record($template, $mapped);
            $text = implode("\n", array_filter($saved));
            foreach (
                ['Pain', 'Heloma', 'Cura PV', 'Improved', 'Examination findings', 'Linked treatment']
                as $expected
            ) {
                self::assertStringContainsString($expected, $text);
            }
            self::assertStringNotContainsString('EVOLUCIONQUIRO', $text);
        }
    }
    public function testRelatedCatalogNamesAreResolvedWithoutIds(): void
    {
        $rows = [
            ['QUIROPODIATRATAMIENTO', ['ID' => 3, 'TRATAMIENTO' => 'Cura PV 1ª']],
            ['QUIROPODIATRATAMIENTOVISITA', ['ID' => 9, 'IDVISITA' => 42, 'IDTRATAMIENTO' => 3]],
        ];
        $context = Podologo_clinical::context(static fn() => $rows);
        self::assertSame(
            'Cura PV',
            Podologo_clinical::classification(['QUIROPODIA' => 1], $context['treatments'][42])['name'],
        );
        self::assertStringNotContainsString('IDTRATAMIENTO', $context['visits'][42]);
        self::assertStringContainsString('Cura PV 1ª', $context['visits'][42]);
    }
    public function testLaterEditsAreConsolidatedIntoExistingVisibleFields(): void
    {
        $fitted = Podologo_clinical::fit(
            ['diagnosis' => 'Later diagnosis', 'observations' => 'Original comments'],
            'feet',
        );
        $saved = Clinical_record_templates::clean_appointment_record('feet', $fitted);
        self::assertStringContainsString('Later diagnosis', $saved['observations']);
        self::assertStringContainsString('Original comments', $saved['observations']);
        self::assertNull($saved['diagnosis']);
    }
    public function testPreviousGeneratedDumpIsReplacedButLaterNotesSurvive(): void
    {
        self::assertSame(
            'New comments',
            Podologo_clinical::merge('Old generated dump', 'New comments', 'Old generated dump'),
        );
        $text = Podologo_clinical::merge("Old generated dump\n\nLater note", 'New comments', 'Old generated dump');
        self::assertStringContainsString('Later note', $text);
        self::assertStringNotContainsString('Old generated dump', $text);
        self::assertSame($text, Podologo_clinical::merge($text, 'New comments', 'Old generated dump'));
    }
}
