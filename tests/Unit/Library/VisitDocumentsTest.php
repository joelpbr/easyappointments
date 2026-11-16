<?php

namespace Tests\Unit\Library;

use DOMDocument;
use InvalidArgumentException;
use PharData;
use Tests\TestCase;
use Visit_documents;

class VisitDocumentsTest extends TestCase
{
    /** Fictional signers, as Visit_documents::professionals() returns them. */
    private const PROFESSIONALS = [
        4 => ['name' => 'PODÒLOGA DE PROVA', 'college' => '838-08-0001', 'tax_id' => '00000000T'],
        5 => ['name' => 'PODÒLEG DE PROVA', 'college' => '838-08-0002', 'tax_id' => '99999999R'],
    ];

    /** Fictional clinic, as the controller reads it from the general settings. */
    private const CLINIC = [
        'clinic_name' => 'Clínica de prova',
        'clinic_address' => 'Carrer de Prova 1, 08000 Prova',
        'clinic_phone' => '600000000',
        'clinic_email' => 'clinica@clinica.test',
        'clinic_link' => 'https://clinica.test',
    ];

    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'libraries/Visit_documents.php';
    }

    public function testAllOriginalFilesAreClassifiedExactlyOnce(): void
    {
        $catalog = (new Visit_documents())->catalog();
        $sources = array_merge(...array_column($catalog, 'sources'));
        $this->assertCount(35, $catalog);
        $this->assertCount(39, $sources);
        $this->assertCount(39, array_unique($sources));
        $this->assertCount(35, array_unique(array_column($catalog, 'id')));
    }

    public function testEveryTypeProducesValidWordWithEscapedDataAndNoUnresolvedFields(): void
    {
        $documents = new Visit_documents();
        foreach ($documents->catalog() as $template) {
            $submitted = $this->fields($template);
            $submitted['patient_name'] = "Pacient Àngels & <Test>\nSegona línia";
            $values = $documents->values($template, [], $submitted, true);
            foreach (array_keys(self::PROFESSIONALS) as $who) {
                $signer = $documents->signer($template, self::PROFESSIONALS, 6, $who, 'Professional de la visita');
                $content = $documents->generate($template, $values, $signer, self::CLINIC);
                $path = tempnam(sys_get_temp_dir(), 'ea-doc-test-') . '.zip';
                try {
                    file_put_contents($path, $content);
                    $zip = new PharData($path);
                    $this->assertTrue(isset($zip['[Content_Types].xml']), $template['id']);
                    $xml = $zip['word/document.xml']->getContent();
                    $document = new DOMDocument();
                    $this->assertTrue($document->loadXML($xml, LIBXML_NONET), $template['id']);
                    $this->assertStringNotContainsString('${', $xml, $template['id']);
                    $this->assertStringNotContainsString('Elija un elemento', $xml, $template['id']);
                    $this->assertStringNotContainsString('Haga clic aquí', $xml, $template['id']);
                    $this->assertStringContainsString('Pacient Àngels &amp; &lt;Test&gt;', $xml, $template['id']);
                    $this->assertStringNotContainsString('CRISTOBAL', $xml);
                    $this->assertStringNotContainsString('SEHNG LI', $xml);
                    $this->assertFalse(isset($zip['docProps/core.xml']));
                    $this->assertStringContainsString(
                        'wordprocessingml.document.main+xml',
                        $zip['[Content_Types].xml']->getContent(),
                    );
                    unset($zip);
                } finally {
                    @unlink($path);
                    @unlink(substr($path, 0, -4));
                }
            }
        }
    }

    public function testOnlyProvidersWithAMembershipNumberCanSign(): void
    {
        $professionals = Visit_documents::professionals([
            [
                'id' => '4',
                'first_name' => 'Sara',
                'last_name' => '',
                'legal_name' => 'PODÒLOGA DE PROVA',
                'college_number' => ' 838-08-0001 ',
                'tax_id' => '00000000T',
            ],
            [
                'id' => '5',
                'first_name' => 'Sebas',
                'last_name' => 'Prova',
                'legal_name' => null,
                'college_number' => '838-08-0002',
                'tax_id' => null,
            ],
            [
                'id' => '6',
                'first_name' => 'Toñi',
                'last_name' => '',
                'legal_name' => 'TOÑI',
                'college_number' => '',
                'tax_id' => '',
            ],
        ]);
        $this->assertSame([4, 5], array_keys($professionals));
        $this->assertSame(
            ['name' => 'PODÒLOGA DE PROVA', 'college' => '838-08-0001', 'tax_id' => '00000000T'],
            $professionals[4],
        );
        $this->assertSame(['name' => 'SEBAS PROVA', 'college' => '838-08-0002', 'tax_id' => ''], $professionals[5]);
    }

    public function testAProfessionalCannotBeChangedToAnotherSignerThroughTheRequest(): void
    {
        $documents = new Visit_documents();
        $signer = $documents->signer($documents->template('invoice'), self::PROFESSIONALS, 4, 5, 'Toñi');
        $this->assertSame(4, $signer['key']);
        $this->assertSame(self::PROFESSIONALS[4]['name'], $signer['name']);
    }

    public function testOtherUsersMustChooseAProfessional(): void
    {
        $documents = new Visit_documents();
        foreach ([0, 6, 99] as $selected) {
            try {
                $documents->signer($documents->template('prescription'), self::PROFESSIONALS, 6, $selected, 'Toñi');
                $this->fail('Unlicensed signer accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('visit_document_choose_professional', $e->getMessage());
            }
        }
        $this->assertSame(
            5,
            $documents->signer($documents->template('prescription'), self::PROFESSIONALS, 6, 5, 'Toñi')['key'],
        );
        $this->assertSame(
            'Toñi',
            $documents->signer($documents->template('attendance'), self::PROFESSIONALS, 6, 0, 'Toñi')['name'],
        );
    }

    public function testTheInvoiceCarriesTheDataOfWhoeverSigns(): void
    {
        $documents = new Visit_documents();
        $template = $documents->template('invoice');
        $values = $documents->values($template, [], $this->fields($template), true);
        foreach (self::PROFESSIONALS as $id => $professional) {
            $signer = $documents->signer($template, self::PROFESSIONALS, $id, 0, '');
            $xml = $this->documentXml($documents->generate($template, $values, $signer, self::CLINIC));
            $this->assertStringContainsString($professional['name'], $xml);
            $this->assertStringContainsString('COL. ' . $professional['college'], $xml);
            $this->assertStringContainsString('NIF. ' . $professional['tax_id'], $xml);
        }
    }

    public function testAnInvoiceIsNotIssuedWithoutTheTaxIdOfTheSigner(): void
    {
        $documents = new Visit_documents();
        $template = $documents->template('invoice');
        $values = $documents->values($template, [], $this->fields($template), true);
        $signer = ['key' => 7, 'name' => 'NOVA PODÒLOGA', 'college' => '838-08-9999', 'tax_id' => ''];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('visit_document_professional_incomplete');
        $documents->generate($template, $values, $signer, self::CLINIC);
    }

    public function testThePrescriptionPrintsTheAddressOfTheClinicFromTheSettings(): void
    {
        $documents = new Visit_documents();
        $template = $documents->template('prescription');
        $values = $documents->values($template, [], $this->fields($template), true);
        $signer = $documents->signer($template, self::PROFESSIONALS, 4, 0, '');
        $xml = $this->documentXml($documents->generate($template, $values, $signer, self::CLINIC));
        $this->assertStringContainsString(self::CLINIC['clinic_address'], $xml);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('visit_document_clinic_incomplete');
        $documents->generate($template, $values, $signer, ['clinic_address' => ''] + self::CLINIC);
    }

    public function testASubmittedFieldCannotReplaceTheSignerOrTheClinic(): void
    {
        $documents = new Visit_documents();
        $template = $documents->template('prescription');
        $values = $documents->values($template, [], $this->fields($template), true);
        $values += ['professional_name' => 'INTRUS', 'clinic_address' => 'INTRUS'];
        $signer = $documents->signer($template, self::PROFESSIONALS, 4, 0, '');
        $xml = $this->documentXml($documents->generate($template, $values, $signer, self::CLINIC));
        $this->assertStringNotContainsString('INTRUS', $xml);
    }

    public function testTheCatalogSpellsOutTheRulesThePageAndTheServerShare(): void
    {
        $documents = new Visit_documents();
        $groups = array_column($documents->groups(), null, 'id');
        foreach ($documents->catalog() as $template) {
            $this->assertArrayHasKey($template['group'], $groups, $template['id']);
            $this->assertSame(
                !empty($groups[$template['group']]['requires_review']),
                $template['requires_review'],
                $template['id'],
            );
            foreach ($template['fields'] as $field) {
                $this->assertIsInt($field['max_length'], $template['id'] . '/' . $field['key']);
                if ($field['type'] === 'integer') {
                    $this->assertIsInt($field['min']);
                    $this->assertIsInt($field['max']);
                }
            }
        }
        $this->assertTrue($documents->template('prescription')['requires_review']);
        $this->assertFalse($documents->template('invoice')['requires_review']);

        $client = $documents->client_catalog();
        $this->assertSame(array_keys($groups), array_column($client['groups'], 'id'));
        $this->assertCount(35, $client['templates']);
        foreach ($client['templates'] as $template) {
            $this->assertArrayNotHasKey('sources', $template);
            $this->assertArrayNotHasKey('template', $template);
        }
    }

    public function testAClinicCanBringItsOwnDocumentPack(): void
    {
        $directory = sys_get_temp_dir() . '/ea-pack-test-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        try {
            copy(APPPATH . 'resources/visit_documents/attendance.docx', $directory . '/letter.docx');
            $field = fn(string $key, string $type) => [
                'key' => $key,
                'label' => ['english' => ucfirst($key)],
                'type' => $type,
                'required' => true,
            ];
            file_put_contents(
                $directory . '/catalog.json',
                json_encode([
                    'groups' => [['id' => 'letters', 'label' => ['english' => 'Letters'], 'requires_review' => true]],
                    'templates' => [
                        [
                            'id' => 'letter',
                            'label' => ['english' => 'Attendance letter'],
                            'group' => 'letters',
                            'template' => 'letter.docx',
                            'fields' => [
                                $field('patient_name', 'text'),
                                $field('date', 'date'),
                                $field('document_number', 'text'),
                                $field('visit_date', 'date'),
                                $field('visit_time', 'time'),
                            ],
                        ],
                    ],
                ]),
            );
            $documents = new Visit_documents($directory);
            $template = $documents->template('letter');
            $this->assertSame('Attendance letter', Visit_documents::label($template['label'], 'catalan'));
            $this->assertFalse($template['restricted']);
            $this->assertTrue($template['requires_review']);
            $values = $documents->values($template, [], $this->fields($template), true);
            $signer = $documents->signer($template, [], 6, 0, 'Dr. Test');
            $xml = $this->documentXml($documents->generate($template, $values, $signer));
            $this->assertStringContainsString('Dr. Test', $xml);
            $this->assertStringNotContainsString('${', $xml);
        } finally {
            @unlink($directory . '/letter.docx');
            @unlink($directory . '/catalog.json');
            @rmdir($directory);
        }
    }

    public function testTheFingerprintChangesWithAnythingTheDocumentPrints(): void
    {
        $documents = new Visit_documents();
        $template = $documents->template('invoice');
        $values = $documents->values($template, [], $this->fields($template), true);
        $signer = $documents->signer($template, self::PROFESSIONALS, 4, 0, '');
        $fingerprint = $documents->fingerprint($template, $values, $signer, self::CLINIC);

        $this->assertSame($fingerprint, $documents->fingerprint($template, $values, $signer, self::CLINIC));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fingerprint);
        $other_signer = $documents->signer($template, self::PROFESSIONALS, 5, 0, '');
        foreach (
            [
                [$template, ['amount' => '31,00'] + $values, $signer, self::CLINIC],
                [$template, $values, $other_signer, self::CLINIC],
                [$template, $values, $signer, ['clinic_address' => 'Un altre carrer'] + self::CLINIC],
                [$documents->template('attendance'), $values, $signer, self::CLINIC],
            ]
            as $different
        ) {
            $this->assertNotSame($fingerprint, $documents->fingerprint(...$different));
        }
    }

    private function documentXml(string $word): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ea-doc-test-');
        try {
            file_put_contents($path . '.zip', $word);
            return (new PharData($path . '.zip'))['word/document.xml']->getContent();
        } finally {
            @unlink($path);
            @unlink($path . '.zip');
        }
    }

    public function testPrescriptionRequiresExplicitReviewAndDoesNotInferADose(): void
    {
        $documents = new Visit_documents();
        $template = $documents->template('rx_diazepam');
        $this->assertArrayNotHasKey('dosage', $template['defaults']);
        $this->expectException(InvalidArgumentException::class);
        $documents->values($template, [], $this->fields($template), false);
    }

    public function testMissingFieldsAreNotSilentlyLeftBlank(): void
    {
        $documents = new Visit_documents();
        $this->expectException(InvalidArgumentException::class);
        $documents->values($documents->template('invoice'), [], [], true);
    }

    public function testInvalidDatesNegativePricesAndNonScalarFieldsAreRejected(): void
    {
        $documents = new Visit_documents();
        $template = $documents->template('invoice');
        foreach (
            [['birth_date' => '2025-02-30'], ['amount' => '-10'], ['amount' => '12oops'], ['patient_name' => ['bad']]]
            as $invalid
        ) {
            try {
                $documents->values($template, [], array_replace($this->fields($template), $invalid), true);
                $this->fail('Invalid data accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testTheRulesOfTheCatalogAreEnforced(): void
    {
        $documents = new Visit_documents();
        $template = $documents->template('prescription');
        foreach ([['birth_date' => date('Y-m-d', strtotime('+1 day'))], ['packages' => '0']] as $invalid) {
            try {
                $documents->values($template, [], array_replace($this->fields($template), $invalid), true);
                $this->fail('Invalid data accepted: ' . key($invalid));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('visit_document_bad_field', $e->getMessage());
            }
        }
        $valid = array_replace($this->fields($template), ['birth_date' => date('Y-m-d'), 'packages' => '1']);
        $this->assertSame('1', $documents->values($template, [], $valid, true)['packages']);
    }

    public function testDefaultsUseOnlyExistingRecordsAndPreserveZeroPrices(): void
    {
        $defaults = (new Visit_documents())->defaults(
            ['first_name' => 'Àngels', 'last_name' => 'Prova'],
            ['document_number' => 'TEST'],
            ['birth_date' => '1990-01-01'],
            ['start_datetime' => '2026-11-16 12:30:00', 'price' => 0],
            ['diagnosis' => 'Existing diagnosis'],
            'Quiropòdia',
        );
        $this->assertSame('Àngels Prova', $defaults['patient_name']);
        $this->assertSame('0.00', $defaults['amount']);
        $this->assertSame('12:30', $defaults['visit_time']);
        $this->assertArrayNotHasKey('invoice_number', $defaults);
        $this->assertArrayNotHasKey('dosage', $defaults);
    }

    public function testReportOptionsAndVisitDateBindingsAreComplete(): void
    {
        $documents = new Visit_documents();
        foreach (['diabetic_foot' => 19, 'biomechanical' => 21] as $id => $count) {
            $template = $documents->template($id);
            $options = array_filter($template['fields'], static fn($field) => !empty($field['options']));
            $this->assertCount($count, $options);
            $fields = array_column($template['fields'], null, 'key');
            $this->assertSame('visit_date', $fields['date']['source']);
            $data = $this->fields($template);
            unset($data['date']);
            $values = $documents->values(
                $template,
                ['date' => '2026-11-16', 'visit_date' => '2024-06-20'],
                $data,
                true,
            );
            $this->assertSame('20/06/2024', $values['date']);
            $this->assertSame([], $template['defaults']);
        }
        $invoice = array_column($documents->template('invoice')['fields'], null, 'key');
        $this->assertContains('29.00', $invoice['amount']['options']);
        $this->assertContains('22.00', $invoice['amount']['options']);
        foreach ($invoice['amount']['options'] as $option) {
            $this->assertMatchesRegularExpression('/^\d+(?:\.\d+)?$/', $option);
        }
    }

    public function testAllReportChoicesAndCustomValuesAreAcceptedWithoutLosingTheSelectedText(): void
    {
        $documents = new Visit_documents();
        foreach (['diabetic_foot', 'biomechanical'] as $id) {
            $template = $documents->template($id);
            foreach ($template['fields'] as $field) {
                foreach (array_merge($field['options'] ?? [], ['Valor personalitzat']) as $option) {
                    if (empty($field['options'])) {
                        continue;
                    }
                    $data = $this->fields($template);
                    $data[$field['key']] = $option;
                    $values = $documents->values($template, [], $data, true);
                    $this->assertSame($option, $values[$field['key']], $id . '/' . $field['key']);
                }
            }
        }
    }

    private function fields(array $template): array
    {
        $data = [];
        foreach ($template['fields'] as $field) {
            $data[$field['key']] = match ($field['type']) {
                'date' => '2020-01-02',
                'time' => '12:30',
                'money' => '30,50',
                'integer' => '2',
                default => 'Prova',
            };
        }
        return $data;
    }
}
