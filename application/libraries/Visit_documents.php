<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Documents of a visit, generated from the document pack of the clinic.
 *
 * A pack is a directory with a catalog.json (groups, document types and their fields) and the Word templates the
 * catalog names. The application ships one in application/resources/visit_documents; a clinic with its own
 * documents places its pack in storage/visit_documents and nothing else changes. Everything specific to a clinic
 * lives in the pack, in the settings (clinic_* fields) or in the record of each provider (professional_* fields).
 */
class Visit_documents
{
    private const TEXT_MAX_LENGTH = 500;

    private const TEXTAREA_MAX_LENGTH = 8000;

    private const INTEGER_MAX = 999;

    /** Catalog keys that only matter on the server. */
    private const SERVER_ONLY = ['template', 'sources'];

    private string $directory;

    private ?array $pack = null;

    /**
     * @param array|string|null $directory Directory of the pack; the one of the installation when it is not given
     * (CodeIgniter passes an array, or nothing, when it loads the library).
     */
    public function __construct(array|string|null $directory = null)
    {
        $this->directory = rtrim(is_string($directory) ? $directory : self::installed_directory(), '/\\') . '/';
    }

    /** The pack of the clinic when there is one, the bundled one otherwise. */
    public static function installed_directory(): string
    {
        $custom = (defined('FCPATH') ? FCPATH : dirname(APPPATH) . '/') . 'storage/visit_documents';

        return is_file($custom . '/catalog.json') ? $custom : APPPATH . 'resources/visit_documents';
    }

    /** The text in the given language, or in the first one of the pack when it lacks that language. */
    public static function label(array $labels, ?string $language = null): string
    {
        return (string) ($labels[$language ?? ''] ?? (reset($labels) ?: ''));
    }

    /**
     * Who can sign the restricted documents: the providers with a membership number on their record.
     *
     * @param array $providers Rows of users (id, first_name, last_name, legal_name, college_number, tax_id).
     *
     * @return array ID => name (as the documents print it), college and tax_id.
     */
    public static function professionals(array $providers): array
    {
        $professionals = [];
        foreach ($providers as $provider) {
            $college = trim((string) ($provider['college_number'] ?? ''));
            if ($college === '') {
                continue;
            }
            $name = trim((string) ($provider['legal_name'] ?? ''));
            $professionals[(int) $provider['id']] = [
                'name' =>
                    $name !== ''
                        ? $name
                        : mb_strtoupper(trim($provider['first_name'] . ' ' . ($provider['last_name'] ?? ''))),
                'college' => $college,
                'tax_id' => trim((string) ($provider['tax_id'] ?? '')),
            ];
        }
        return $professionals;
    }

    /** Document types, with the rules of their group and the limits of every field spelled out. */
    public function catalog(): array
    {
        return $this->pack()['templates'];
    }

    /** Sections of the menu, in the order the pack lists them. */
    public function groups(): array
    {
        return $this->pack()['groups'];
    }

    /** What the page needs to draw the menu and the forms: the catalog without its server-side details. */
    public function client_catalog(): array
    {
        return [
            'groups' => $this->groups(),
            'templates' => array_map(
                fn(array $template) => array_diff_key($template, array_flip(self::SERVER_ONLY)),
                $this->catalog(),
            ),
        ];
    }

    public function template(string $id): array
    {
        foreach ($this->catalog() as $template) {
            if ($template['id'] === $id) {
                return $template;
            }
        }
        throw new InvalidArgumentException(lang('visit_document_invalid_type'));
    }

    /**
     * An eligible signed-in professional signs their own restricted documents. Everyone else chooses one.
     *
     * @param array $template
     * @param array $professionals From professionals().
     * @param int $current The signed-in user.
     * @param int $selected The professional chosen on the form.
     * @param string $visit_provider Name on the documents anyone can issue.
     */
    public function signer(
        array $template,
        array $professionals,
        int $current,
        int $selected,
        string $visit_provider,
    ): array {
        if (!$template['restricted']) {
            return ['key' => null, 'name' => $visit_provider, 'college' => '', 'tax_id' => ''];
        }
        $key = isset($professionals[$current]) ? $current : $selected;
        if (!isset($professionals[$key])) {
            throw new InvalidArgumentException(lang('visit_document_choose_professional'));
        }
        return ['key' => $key] + $professionals[$key];
    }

    /** Only these values are inferred; findings, doses, invoice numbers and payment status are never invented. */
    public function defaults(
        array $customer,
        array $fiscal,
        array $patient,
        array $appointment,
        array $record,
        string $service,
    ): array {
        return [
            'patient_name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
            'document_number' => (string) ($fiscal['document_number'] ?? ''),
            'birth_date' => (string) ($patient['birth_date'] ?? ''),
            'date' => date('Y-m-d'),
            'dispensing_date' => date('Y-m-d'),
            'visit_date' => substr($appointment['start_datetime'], 0, 10),
            'visit_time' => substr($appointment['start_datetime'], 11, 5),
            'description' => $service,
            'amount' => $appointment['price'] === null ? '' : number_format((float) $appointment['price'], 2, '.', ''),
            'reason' => (string) ($record['reason'] ?? ''),
            'diagnosis' => (string) ($record['diagnosis'] ?? ''),
            'treatment' => (string) ($record['treatment'] ?? ''),
        ];
    }

    /** Validate every declared field, including prefilled values, before writing any document. */
    public function values(
        array $template,
        array $defaults,
        array $submitted,
        bool $reviewed,
        ?string $language = null,
    ): array {
        if ($template['requires_review'] && !$reviewed) {
            throw new InvalidArgumentException(lang('visit_document_review_required'));
        }
        $source = array_replace($template['defaults'], $defaults, $submitted);
        $values = [];
        foreach ($template['fields'] as $field) {
            $key = $field['key'];
            $value = $submitted[$key] ?? ($source[$field['source'] ?? $key] ?? '');
            if (!is_scalar($value)) {
                throw new InvalidArgumentException(lang('visit_document_invalid_field'));
            }
            $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
            $label = self::label($field['label'], $language);
            if ($field['required'] && $value === '') {
                throw new InvalidArgumentException(sprintf(lang('visit_document_missing_field'), $label));
            }
            $printed = $this->printed_value($field, $value);
            if ($printed === null) {
                throw new InvalidArgumentException(sprintf(lang('visit_document_bad_field'), $label));
            }
            $values[$key] = $printed;
        }
        return $values;
    }

    /**
     * What identifies a document: its type, its template and everything printed on it. Two requests with the same
     * fingerprint would print the same document.
     */
    public function fingerprint(array $template, array $values, array $signer, array $clinic = []): string
    {
        return hash(
            'sha256',
            json_encode(
                [$template['id'], hash_file('sha256', $this->template_path($template)), $values, $signer, $clinic],
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    /**
     * Fill the Word template. Preserves its geometry, letterheads and backgrounds; escapes all user text as XML.
     *
     * @param array $template
     * @param array $values From values().
     * @param array $signer From signer(): the professional_* fields of the template.
     * @param array $clinic The clinic_* fields of the template (clinic_name, clinic_address...), from the settings.
     */
    public function generate(array $template, array $values, array $signer, array $clinic = []): string
    {
        $original = new PharData($this->template_path($template));
        $values =
            [
                'professional_name' => $signer['name'],
                'professional_college' => $signer['college'],
                'professional_tax_id' => $signer['tax_id'],
            ] +
            $clinic +
            $values;
        $document = new DOMDocument();
        if (!$document->loadXML($original['word/document.xml']->getContent(), LIBXML_NONET)) {
            throw new RuntimeException('Invalid document template.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach (iterator_to_array($xpath->query('//w:t')) as $node) {
            // A line with an optional token includes its label only when the field is filled in.
            $content = preg_replace_callback('/^.*\$\{\?([a-z_]+)\}.*$/m', static function ($match) use ($values) {
                if (!array_key_exists($match[1], $values)) {
                    throw new RuntimeException('Unresolved document field: ' . $match[1]);
                }
                return $values[$match[1]] === '' ? '' : str_replace('${?', '${', $match[0]);
            }, $node->textContent);
            $text = preg_replace_callback(
                '/\$\{([a-z_]+)\}/',
                static function ($match) use ($values) {
                    if (!array_key_exists($match[1], $values)) {
                        throw new RuntimeException('Unresolved document field: ' . $match[1]);
                    }
                    // A document never goes out with a blank where the record of the signer or the settings of
                    // the clinic lack something.
                    if ($values[$match[1]] === '' && str_starts_with($match[1], 'professional_')) {
                        throw new InvalidArgumentException(lang('visit_document_professional_incomplete'));
                    }
                    if ($values[$match[1]] === '' && str_starts_with($match[1], 'clinic_')) {
                        throw new InvalidArgumentException(lang('visit_document_clinic_incomplete'));
                    }
                    return $values[$match[1]];
                },
                rtrim($content, "\n"),
            );
            $lines = explode("\n", $text);
            $node->textContent = '';
            $node->appendChild($document->createTextNode(array_shift($lines)));
            $node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $previous = $node;
            foreach ($lines as $line) {
                $br = $document->createElementNS($node->namespaceURI, 'w:br');
                $next = $document->createElementNS($node->namespaceURI, 'w:t');
                $next->appendChild($document->createTextNode($line));
                $next->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                $node->parentNode->insertBefore($br, $previous->nextSibling);
                $node->parentNode->insertBefore($next, $br->nextSibling);
                $previous = $next;
            }
        }
        // PharData supports ZIP without the optional ZipArchive extension. Temporary files stay private.
        $temporary = tempnam(sys_get_temp_dir(), 'ea-word-');
        if ($temporary === false) {
            throw new RuntimeException('Could not allocate document storage.');
        }
        $zip_path = $temporary . '.zip';
        $previous_mask = umask(0077);
        try {
            $output = new PharData($zip_path, 0, null, Phar::ZIP);
            foreach (new RecursiveIteratorIterator($original) as $entry) {
                $name = substr($entry->getPathname(), strlen('phar://' . $original->getPath()) + 1);
                $output->addFromString(
                    $name,
                    $name === 'word/document.xml' ? $document->saveXML() : $entry->getContent(),
                );
            }
            unset($output);
            $content = file_get_contents($zip_path);
            if ($content === false) {
                throw new RuntimeException('Could not generate document.');
            }
            return $content;
        } finally {
            umask($previous_mask);
            @unlink($temporary);
            @unlink($zip_path);
        }
    }

    /** Render on the server so that the download is a finished PDF, with the original Word layout. */
    public function generate_pdf(array $template, array $values, array $signer, array $clinic = []): string
    {
        $directory = sys_get_temp_dir() . '/ea-visit-pdf-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException(lang('visit_document_generate_error'));
        }
        try {
            $word = $this->generate($template, $values, $signer, $clinic);
            if (file_put_contents($directory . '/document.docx', $word) === false) {
                throw new RuntimeException(lang('visit_document_generate_error'));
            }
            set_time_limit(60);
            $process = proc_open(
                ['python3', APPPATH . 'scripts/render_visit_pdf.py', $directory],
                [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes,
            );
            if (!is_resource($process) || proc_close($process) !== 0) {
                throw new RuntimeException(lang('visit_document_generate_error'));
            }
            $pdf = file_get_contents($directory . '/document.pdf');
            if ($pdf === false || !str_starts_with($pdf, '%PDF-')) {
                throw new RuntimeException(lang('visit_document_generate_error'));
            }
            return $pdf;
        } finally {
            $this->remove_job_directory($directory);
        }
    }

    /** The catalog as the rest of the code uses it: group rules and field limits filled in once, here. */
    private function pack(): array
    {
        if ($this->pack !== null) {
            return $this->pack;
        }
        $pack = json_decode(file_get_contents($this->directory . 'catalog.json'), true, 512, JSON_THROW_ON_ERROR);
        $groups = array_column($pack['groups'], null, 'id');
        foreach ($pack['templates'] as &$template) {
            if (!isset($groups[$template['group']])) {
                throw new RuntimeException('Unknown document group: ' . $template['group']);
            }
            $template['restricted'] = !empty($template['restricted']);
            $template['requires_review'] ??= !empty($groups[$template['group']]['requires_review']);
            $template['defaults'] ??= [];
            foreach ($template['fields'] as &$field) {
                $field['required'] = !empty($field['required']);
                $field['max_length'] ??=
                    $field['type'] === 'textarea' ? self::TEXTAREA_MAX_LENGTH : self::TEXT_MAX_LENGTH;
                if ($field['type'] === 'integer') {
                    $field['min'] ??= 0;
                    $field['max'] ??= self::INTEGER_MAX;
                }
            }
            unset($field);
        }
        unset($template);

        return $this->pack = $pack;
    }

    private function template_path(array $template): string
    {
        return $this->directory . basename($template['template']);
    }

    /** The value as the document prints it, or null when the field does not accept it. */
    private function printed_value(array $field, string $value): ?string
    {
        if (mb_strlen($value) > $field['max_length'] || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            return null;
        }
        if ($value === '') {
            return '';
        }
        return match ($field['type']) {
            'date' => $this->printed_date($value, !empty($field['not_future'])),
            'time' => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $value) ? $value : null,
            'integer' => ctype_digit($value) && (int) $value >= $field['min'] && (int) $value <= $field['max']
                ? $value
                : null,
            'money' => $this->printed_money($value),
            default => $value,
        };
    }

    private function printed_date(string $value, bool $not_future): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || ($not_future && $value > date('Y-m-d'))) {
            return null;
        }
        return $date->format('d/m/Y');
    }

    private function printed_money(string $value): ?string
    {
        $value = str_replace(',', '.', $value);
        if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $value)) {
            return null;
        }
        return number_format((float) $value, 2, ',', '.');
    }

    private function remove_job_directory(string $directory): void
    {
        foreach (new FilesystemIterator($directory) as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                $this->remove_job_directory($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($directory);
    }
}
