<?php defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'helpers/fiscal_id_helper.php';

/** Convert the original patient NIF into the document fields already used by the patient record. */
class Podologo_documents
{
    public static function convert(?string $source): ?array
    {
        $source = trim($source ?? '');
        if ($source === '') {
            return null;
        }
        if (mb_strlen($source) > 64) {
            throw new InvalidArgumentException('Original document exceeds the existing field length.');
        }
        foreach (['dni', 'nie', 'nif'] as $type) {
            if (validate_fiscal_id($type, $source) === FISCAL_ID_VALID) {
                return ['document_type' => $type, 'document_number' => normalize_fiscal_id($source)];
            }
        }
        // Do not invent missing letters/digits or classify an unknown passport as a Spanish DNI.
        // The existing "other" type retains the original value and allows normal patient editing.
        return ['document_type' => 'other', 'document_number' => $source];
    }

    public static function matches(string $current, string $source): bool
    {
        return normalize_fiscal_id($current) === normalize_fiscal_id($source);
    }
}
