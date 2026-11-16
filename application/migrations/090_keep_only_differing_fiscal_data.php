<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * ---------------------------------------------------------------------------- */

/**
 * The patient's own fiscal row keeps only what differs from the patient (see Fiscal_parties_model): the name,
 * address, zip code, city and province that repeat the patient's ones are emptied, and so is a country that means
 * Spain. Nothing is lost: an empty field means the patient's value.
 */
class Migration_Keep_only_differing_fiscal_data extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $fiscal = '`' . $this->db->dbprefix('fiscal_parties') . '`';
        $users = '`' . $this->db->dbprefix('users') . '`';
        $join = "UPDATE $fiscal AS fiscal JOIN $users AS users ON users.id = fiscal.id_users_customer";
        $patient = "fiscal.relationship = 'patient'";

        $this->db->query(
            "$join SET fiscal.name = NULL WHERE $patient AND fiscal.name = TRIM(CONCAT_WS(' ', users.first_name, users.last_name))",
        );

        foreach (
            ['address' => 'address', 'zip_code' => 'zip_code', 'city' => 'city', 'province' => 'state']
            as $field => $user_field
        ) {
            $this->db->query("$join SET fiscal.$field = NULL WHERE $patient AND fiscal.$field = users.$user_field");
        }

        $this->db->query(
            "UPDATE $fiscal AS fiscal SET fiscal.country = NULL WHERE $patient AND LOWER(TRIM(fiscal.country)) IN ('es', 'españa', 'espanya', 'spain')",
        );
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        // Empty fields mean the patient's values, so there is nothing to restore.
    }
}
