<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * What a provider signs the invoices and the prescriptions with, on their own record instead of in the code (see
 * Visit_documents::signer()):
 *
 * - legal_name: the name as the documents print it, when it differs from the name shown in the application.
 * - college_number: the membership number. The providers that have one can sign.
 * - tax_id: the NIF of the invoices.
 *
 * The data is typed in on the record of each provider ("Document signing"); none of it lives in the code.
 */
class Migration_Add_provider_document_identity extends EA_Migration
{
    private const COLUMNS = [
        'legal_name' => ['type' => 'VARCHAR', 'constraint' => '256', 'null' => true, 'after' => 'last_name'],
        'college_number' => ['type' => 'VARCHAR', 'constraint' => '32', 'null' => true, 'after' => 'legal_name'],
        'tax_id' => ['type' => 'VARCHAR', 'constraint' => '32', 'null' => true, 'after' => 'college_number'],
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $name => $column) {
            if (!$this->db->field_exists($name, 'users')) {
                $this->dbforge->add_column('users', [$name => $column]);
            }
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        foreach (array_reverse(array_keys(self::COLUMNS)) as $name) {
            if ($this->db->field_exists($name, 'users')) {
                $this->dbforge->drop_column('users', $name);
            }
        }
    }
}
