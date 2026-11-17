<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Consent of a patient signed with a finger on the tablet.
 *
 * The text comes from config/patient_consent.php. A signature drawn on the screen is a simple electronic signature
 * (art. 25 eIDAS, Ley 6/2020): to be able to prove it, what was signed is kept as a PDF with the drawing, and its
 * SHA-256 fingerprint covers the exact text, the choices, who signed and the patient (see fingerprint()).
 */
class Patient_consent
{
    public const LANGUAGES = ['ca', 'es'];

    /** Who signs: the patient, or their legal representative (under 16, or with support measures). */
    public const ROLES = ['patient', 'representative'];

    private static ?array $config = null;

    /** Contents of application/config/patient_consent.php, read once (also outside the framework, e.g. in tests). */
    public static function config(): array
    {
        if (self::$config === null) {
            $config = [];
            include APPPATH . 'config/patient_consent.php';
            self::$config = $config;
        }
        return self::$config;
    }

    /**
     * The text to sign in a language, with the data of the clinic.
     *
     * @param array $clinic name, address, email, phone, dpo (contact) and optional dpo_name.
     *
     * @return array version, language, title, sections [[title, paragraphs]], options [key => text], declaration.
     */
    public static function text(string $language, array $clinic): array
    {
        $config = self::config();
        $language = in_array($language, self::LANGUAGES, true) ? $language : self::LANGUAGES[0];
        $fill = static fn(string $text): string => strtr($text, [
            '{clinic}' => trim((string) ($clinic['name'] ?? '')) ?: '—',
            '{address}' => trim((string) ($clinic['address'] ?? '')) ?: '—',
            '{email}' => trim((string) ($clinic['email'] ?? '')) ?: '—',
            '{phone}' => trim((string) ($clinic['phone'] ?? '')) ?: '—',
            '{dpo}' => implode(' · ', array_filter([
                trim((string) ($clinic['dpo_name'] ?? '')),
                trim((string) ($clinic['dpo'] ?? '')),
            ], static fn(string $value): bool => $value !== '')) ?: '—',
        ]);

        return [
            'version' => (string) $config['patient_consent_version'],
            'language' => $language,
            'title' => $fill($config['patient_consent_title'][$language]),
            'sections' => array_map(
                fn(array $section) => [
                    'title' => $fill($section['title']),
                    'paragraphs' => array_map($fill, $section['paragraphs']),
                ],
                $config['patient_consent_sections'][$language],
            ),
            'options' => array_map(fn(array $texts) => $fill($texts[$language]), $config['patient_consent_options']),
            'declaration' => $fill($config['patient_consent_declaration'][$language]),
        ];
    }

    /**
     * Check the choices: one 0 or 1 for each optional consent, unticked when missing (art. 7 RGPD: never presumed).
     *
     * @return array [key => 0|1]
     */
    public static function choices(array $values): array
    {
        $choices = [];
        foreach (array_keys(self::config()['patient_consent_options']) as $key) {
            $choices[$key] = filter_var($values[$key] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        return $choices;
    }

    /**
     * Check who signs: the patient, or a representative with name, identity document and relationship.
     *
     * @return array role, name, document, relationship.
     */
    public static function signer(array $values): array
    {
        $role = in_array($values['role'] ?? '', self::ROLES, true) ? $values['role'] : null;
        $signer = [
            'role' => $role,
            'name' => trim((string) ($values['name'] ?? '')),
            'document' => strtoupper(trim((string) ($values['document'] ?? ''))),
            'relationship' => $role === 'representative' ? trim((string) ($values['relationship'] ?? '')) : '',
        ];
        if (
            !$role ||
            mb_strlen($signer['name']) < 3 ||
            mb_strlen($signer['name']) > 200 ||
            mb_strlen($signer['document']) > 20 ||
            mb_strlen($signer['relationship']) > 60 ||
            ($role === 'representative' && ($signer['document'] === '' || mb_strlen($signer['relationship']) < 3))
        ) {
            throw new InvalidArgumentException(lang('consent_signer_invalid'));
        }
        return $signer;
    }

    /**
     * Get the picture of the signature from the canvas (a PNG data URL), refusing an empty one.
     *
     * @return string PNG.
     */
    public static function signature_png(string $data_url): string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#', $data_url, $match)) {
            throw new InvalidArgumentException(lang('consent_signature_empty'));
        }
        $png = base64_decode($match[1], true);
        if ($png === false || strlen($png) > 1048576 || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            throw new InvalidArgumentException(lang('consent_signature_empty'));
        }
        $image = @imagecreatefromstring($png);
        if (!$image) {
            throw new InvalidArgumentException(lang('consent_signature_empty'));
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $ink = 0;
        if ($width >= 100 && $height >= 50 && $width <= 4000 && $height <= 4000) {
            for ($x = 0; $x < $width; $x += 2) {
                for ($y = 0; $y < $height; $y += 2) {
                    $color = imagecolorat($image, $x, $y);
                    $alpha = ($color >> 24) & 0x7f;
                    $light = (($color >> 16) & 0xff) + (($color >> 8) & 0xff) + ($color & 0xff);
                    // Opaque and dark: a stroke, not the transparent or white background.
                    if ($alpha < 100 && $light < 600) {
                        $ink++;
                    }
                }
            }
        }
        imagedestroy($image);
        if ($ink < 40) {
            throw new InvalidArgumentException(lang('consent_signature_empty'));
        }
        return $png;
    }

    /**
     * SHA-256 of what is signed: the exact text with its version, the choices, who signs, the patient, the moment and
     * the signature drawn. Any change in any of them gives another fingerprint.
     */
    public static function fingerprint(
        array $text,
        array $choices,
        array $signer,
        array $patient,
        string $signed_at,
        string $png,
    ): string {
        return hash(
            'sha256',
            json_encode(
                [$text, $choices, $signer, $patient, $signed_at, hash('sha256', $png)],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
        );
    }

    /**
     * The signed document, as HTML for the PDF. The signature is the file signature.png next to it.
     *
     * @param array $patient name, document.
     * @param array $meta signed_at (as shown), collected_by, fingerprint and signature_size ([width, height] of the
     * drawing, to print it at its proportions).
     */
    public static function document_html(array $text, array $patient, array $signer, array $choices, array $meta): string
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Printed 100 px high, at most 380 px wide: LibreOffice only follows the size given as attributes.
        [$drawn_width, $drawn_height] = ($meta['signature_size'] ?? null) ?: [600, 200];
        $height = 100;
        $width = (int) round(min(380, ($height * $drawn_width) / max(1, $drawn_height)));
        $height = (int) round(($width * $drawn_height) / max(1, $drawn_width));
        $labels = [
            'ca' => [
                'patient' => 'Pacient',
                'document' => 'Document d’identitat',
                'signer' => 'Signa',
                'self' => 'el mateix pacient',
                'as' => 'en qualitat de',
                'date' => 'Data i hora',
                'collected' => 'Signatura recollida per',
                'yes' => 'Sí',
                'no' => 'No',
                'footer' => 'Document signat amb signatura electrònica manuscrita sobre pantalla. Versió del text',
                'hash' => 'Empremta SHA-256',
            ],
            'es' => [
                'patient' => 'Paciente',
                'document' => 'Documento de identidad',
                'signer' => 'Firma',
                'self' => 'el propio paciente',
                'as' => 'en calidad de',
                'date' => 'Fecha y hora',
                'collected' => 'Firma recogida por',
                'yes' => 'Sí',
                'no' => 'No',
                'footer' => 'Documento firmado con firma electrónica manuscrita sobre pantalla. Versión del texto',
                'hash' => 'Huella SHA-256',
            ],
        ][$text['language']];

        $html =
            '<!doctype html><html lang="' .
            $e($text['language']) .
            '"><head><meta charset="utf-8"><title>' .
            $e($text['title']) .
            '</title>' .
            '<style>body,h1,h2,p,td{font-family:"Liberation Sans",Arial,sans-serif}' .
            'body{font-size:10pt;line-height:1.35}' .
            'h1{font-size:14pt;margin:0 0 8pt}h2{font-size:11pt;margin:12pt 0 4pt}p{margin:0 0 5pt}' .
            'table{border-collapse:collapse;width:100%}td{padding:3pt 4pt;vertical-align:top}' .
            '.box{border:1px solid #999;padding:6pt}.small{font-size:8pt;color:#444}</style></head><body>';
        $html .= '<h1>' . $e($text['title']) . '</h1>';
        $html .=
            '<p><b>' .
            $labels['patient'] .
            ':</b> ' .
            $e($patient['name']) .
            ($patient['document'] ? ' · ' . $labels['document'] . ': ' . $e($patient['document']) : '') .
            '</p>';
        foreach ($text['sections'] as $section) {
            $html .= '<h2>' . $e($section['title']) . '</h2>';
            foreach ($section['paragraphs'] as $paragraph) {
                $html .= '<p>' . $e($paragraph) . '</p>';
            }
        }
        $html .= '<table>';
        foreach ($text['options'] as $key => $option) {
            $html .=
                '<tr><td width="40"><b>' .
                ($choices[$key] ? $labels['yes'] : $labels['no']) .
                '</b></td><td>' .
                $e($option) .
                '</td></tr>';
        }
        $html .= '</table>';
        $html .= '<p style="margin-top:10pt">' . $e($text['declaration']) . '</p>';
        $html .=
            '<div class="box"><p><b>' .
            $labels['signer'] .
            ':</b> ' .
            $e($signer['name']) .
            ($signer['document'] ? ' (' . $e($signer['document']) . ')' : '') .
            ', ' .
            ($signer['role'] === 'representative'
                ? $labels['as'] . ' ' . $e($signer['relationship'])
                : $labels['self']) .
            '</p><p><b>' .
            $labels['date'] .
            ':</b> ' .
            $e($meta['signed_at']) .
            '</p><p><img src="signature.png" alt="" width="' .
            $width .
            '" height="' .
            $height .
            '"></p><p><b>' .
            $labels['collected'] .
            ':</b> ' .
            $e($meta['collected_by']) .
            '</p></div>';
        $html .=
            '<p class="small" style="margin-top:8pt">' .
            $labels['footer'] .
            ': ' .
            $e($text['version']) .
            '. ' .
            $labels['hash'] .
            ': ' .
            $e($meta['fingerprint']) .
            '</p></body></html>';

        return $html;
    }

    /** Render the document as a PDF with LibreOffice, like the other documents of the patient. */
    public function pdf(string $html, string $png): string
    {
        $directory = sys_get_temp_dir() . '/ea-consent-pdf-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException(lang('consent_save_error'));
        }
        try {
            if (
                file_put_contents($directory . '/document.html', $html) === false ||
                file_put_contents($directory . '/signature.png', $png) === false
            ) {
                throw new RuntimeException(lang('consent_save_error'));
            }
            set_time_limit(60);
            $process = proc_open(
                ['python3', APPPATH . 'scripts/render_visit_pdf.py', $directory, 'document.html'],
                [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes,
            );
            if (!is_resource($process) || proc_close($process) !== 0) {
                throw new RuntimeException(lang('consent_save_error'));
            }
            $pdf = file_get_contents($directory . '/document.pdf');
            if ($pdf === false || !str_starts_with($pdf, '%PDF-')) {
                throw new RuntimeException(lang('consent_save_error'));
            }
            return $pdf;
        } finally {
            foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            ) as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
            @rmdir($directory);
        }
    }
}
