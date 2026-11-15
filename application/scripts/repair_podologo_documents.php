<?php
/** Populate the existing patient fiscal document fields from the preserved legacy source. */
$apply = in_array('--apply', $_SERVER['argv'], true);
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir(dirname(__DIR__, 2));
require 'index.php';
require_once APPPATH . 'libraries/Podologo_documents.php';
require_once APPPATH . 'libraries/Podologo_clinical_repair.php';
$ci = &get_instance();
$db = $ci->db->conn_id;
$p = $ci->db->dbprefix;
$links = [];
$r = $db->query("SELECT source_id,target_id FROM {$p}podologo_import_links WHERE source_table='PACIENTE'");
while ($row = $r->fetch_assoc()) {
    $links[(int) $row['source_id']] = (int) $row['target_id'];
}
$fiscal = [];
$r = $db->query("SELECT * FROM {$p}fiscal_parties WHERE relationship='patient'");
while ($row = $r->fetch_assoc()) {
    $fiscal[(int) $row['id_users_customer']][] = $row;
}
$changes = [];
$sources = [];
$types = [];
$conflicts = [];
$matching = 0;
$empty = 0;
foreach ((new Podologo_clinical_repair())->rows('PACIENTE') as [, $row]) {
    $document = Podologo_documents::convert(Podologo_clinical::text($row['NIF'] ?? null));
    if (!$document) {
        $empty++;
        continue;
    }
    $target = $links[$row['ID']] ?? null;
    if (!$target) {
        throw new RuntimeException('Original patient is missing its destination ID.');
    }
    $types[$document['document_type']] = ($types[$document['document_type']] ?? 0) + 1;
    $sources[$target] = $document;
    $current = $fiscal[$target] ?? [];
    if (count($current) > 1) {
        throw new RuntimeException('Multiple patient fiscal records require review.');
    }
    $current = $current[0] ?? null;
    if (trim($current['document_number'] ?? '') !== '') {
        if (Podologo_documents::matches($current['document_number'], $document['document_number'])) {
            $matching++;
        } else {
            $conflicts[] = $target;
        }
        continue;
    }
    $changes[$target] = ['document' => $document, 'current' => $current];
}
$summary = [
    'source_documents' => count($sources),
    'empty_source' => $empty,
    'document_types' => $types,
    'to_restore' => count($changes),
    'already_present' => $matching,
    'preserved_conflicts' => count($conflicts),
];
if (!$apply || !$changes) {
    echo json_encode($summary) . PHP_EOL;
    exit();
}
if ((int) $db->query("SELECT GET_LOCK('podologo_import',0)")->fetch_row()[0] !== 1) {
    throw new RuntimeException('An import is running.');
}
try {
    $ci->load->library('database_backup');
    $backup = $ci->database_backup->create('before-document-repair');
    $db->begin_transaction();
    try {
        $read = $db->prepare(
            "SELECT * FROM {$p}fiscal_parties WHERE id_users_customer=? AND relationship='patient' FOR UPDATE",
        );
        $insert = $db->prepare(
            "INSERT INTO {$p}fiscal_parties (id_users_customer,relationship,document_type,document_number,create_datetime,update_datetime) VALUES (?,'patient',?,?,NOW(),NOW())",
        );
        $update = $db->prepare(
            "UPDATE {$p}fiscal_parties SET document_type=?,document_number=?,update_datetime=NOW() WHERE id=?",
        );
        foreach ($changes as $patient => $item) {
            $read->execute([$patient]);
            $current = $read->get_result()->fetch_assoc();
            if ($current && trim($current['document_number'] ?? '') !== '') {
                throw new RuntimeException('A document was edited during the repair; nothing changed.');
            }
            $doc = $item['document'];
            if ($current) {
                $update->execute([$doc['document_type'], $doc['document_number'], $current['id']]);
            } else {
                $insert->execute([$patient, $doc['document_type'], $doc['document_number']]);
            }
            $read->execute([$patient]);
            $stored = $read->get_result()->fetch_assoc();
            if (
                $stored['document_type'] !== $doc['document_type'] ||
                $stored['document_number'] !== $doc['document_number']
            ) {
                throw new RuntimeException('Document verification failed.');
            }
            if ($current) {
                foreach ($current as $column => $value) {
                    if (in_array($column, ['document_type', 'document_number', 'update_datetime'], true)) {
                        continue;
                    }
                    if (
                        ($stored[$column] === null ? null : (string) $stored[$column]) !==
                        ($value === null ? null : (string) $value)
                    ) {
                        throw new RuntimeException('An unrelated fiscal field changed.');
                    }
                }
            }
        }
        $db->commit();
        $read->close();
        $insert->close();
        $update->close();
        $summary += ['restored' => count($changes), 'backup' => basename($backup), 'all_documents_verified' => true];
        audit_log('updated', 'database', null, $summary);
        echo json_encode($summary) . PHP_EOL;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
} finally {
    $db->query("SELECT RELEASE_LOCK('podologo_import')");
}
