<?php
/** Run inside the application image with LibreOffice and Poppler installed. Uses only fictional data. */
define('BASEPATH', true);
define('APPPATH', (getenv('EA_PROJECT_ROOT') ?: dirname(__DIR__)) . '/application/');
function lang(string $key): string
{
    return $key;
}
require APPPATH . 'libraries/Visit_documents.php';

$documents = new Visit_documents();
$output = sys_get_temp_dir() . '/ea-visit-pdf-check-' . bin2hex(random_bytes(6));
mkdir($output, 0700);
$done = [];
foreach ($documents->catalog() as $template) {
    foreach ([4] as $professional) {
        $filename = $template['template'];
        if (isset($done[$filename])) {
            continue;
        }
        $done[$filename] = true;
        $data = [];
        foreach ($template['fields'] as $field) {
            $data[$field['key']] =
                $field['options'][0] ??
                match ($field['type']) {
                    'date' => '2020-01-02',
                    'time' => '12:30',
                    'money' => '30,50',
                    'integer' => '2',
                    default => $template['defaults'][$field['key']] ?? 'Prova clínica',
                };
        }
        $data['patient_name'] = 'PACIENT PROVA PDF';
        $data['document_number'] = 'TEST';
        if ($template['template'] === 'prescription.docx') {
            $data['medication'] = 'Paracetamol 650 mg EFG';
            $data['duration'] = 'Pauta de prova';
            $data['dosage'] = 'Dosi de prova';
            $data['interval'] = '8 h';
            $data['units'] = '1 comp.';
        }
        $signer = $documents->signer(
            $template,
            [4 => ['name' => 'PODÒLOGA DE PROVA', 'college' => '838-08-0001', 'tax_id' => '00000000T']],
            0,
            $professional,
            'PROFESSIONAL PROVA',
        );
        $before = glob(sys_get_temp_dir() . '/ea-visit-pdf-*');
        $pdf = $documents->generate_pdf($template, $documents->values($template, [], $data, true), $signer, [
            'clinic_name' => 'CLÍNICA DE PROVA',
            'clinic_address' => 'Carrer de Prova 1, 08000 Prova',
            'clinic_phone' => '600000000',
            'clinic_email' => 'clinica@clinica.test',
            'clinic_link' => 'https://clinica.test',
        ]);
        if (!str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('Invalid PDF: ' . $filename);
        }
        if ($before !== glob(sys_get_temp_dir() . '/ea-visit-pdf-*')) {
            throw new RuntimeException('Conversion left private temporary files.');
        }
        $path = $output . '/' . basename($filename, '.docx') . '.pdf';
        file_put_contents($path, $pdf);
        $process = proc_open(['pdftotext', $path, '-'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $text = stream_get_contents($pipes[1]);
        $text = preg_replace('/\s+/u', ' ', $text);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || !str_contains($text, 'PACIENT PROVA PDF') || str_contains($text, '${')) {
            throw new RuntimeException('PDF text missing or incomplete: ' . $filename);
        }
        echo 'PASS ' . basename($path) . "\n";
    }
}
echo count($done) . " PDF layouts generated and checked, with temporary-job cleanup. Fictional outputs: $output\n";
