<?php
/** Exercise the live model inside a rolled-back transaction, using only fictional patients and PDF bytes. */
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir(dirname(__DIR__));
require 'index.php';
$ci = &get_instance();
$ci->load->model('patient_documents_model');
$model = $ci->patient_documents_model;
$db = $ci->db;
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$db->trans_begin();
try {
    $db->insert('users', ['first_name' => 'DOCUMENT TEST A']);
    $patient_a = (int) $db->insert_id();
    $db->insert('users', ['first_name' => 'DOCUMENT TEST B']);
    $patient_b = (int) $db->insert_id();
    $db->insert('appointments', ['id_users_customer' => $patient_a, 'start_datetime' => '2020-01-02 12:30:00']);
    $appointment_id = (int) $db->insert_id();
    $appointment = [
        'id' => $appointment_id,
        'id_users_customer' => $patient_a,
        'start_datetime' => '2020-01-02 12:30:00',
    ];
    $template = [
        'id' => 'attendance',
        'label' => ['catalan' => 'Justificant de visita', 'spanish' => 'Justificante de visita'],
    ];
    $pdf = "%PDF-1.7\n\x00\xFF\x80 Exact binary snapshot\n%%EOF";
    $ids = [];
    for ($i = 0; $i < 27; $i++) {
        $ids[] = $model->store(
            $appointment,
            $template,
            ['name' => 'TEST PROFESSIONAL'],
            'TEST CREATOR',
            $pdf,
            hash('sha256', 'document ' . $i),
        );
    }
    $check(
        $model->identical($appointment_id, hash('sha256', 'document 3')) === $ids[3] &&
            $model->identical($appointment_id, hash('sha256', 'another document')) === null &&
            $model->identical($appointment_id + 1, hash('sha256', 'document 3')) === null,
        'The same document of the same visit is found again',
    );
    $check($model->metadata($ids[0])['titles'] === $template['label'], 'Titles kept in every language');
    $created = $db
        ->get_where('audit_log', ['entity' => 'patient_document', 'entity_id' => $ids[0], 'action' => 'created'])
        ->row_array();
    $created_changes = json_decode($created['changes'] ?? '', true);
    $check(
        ($created_changes['id_users_customer'] ?? null) === [null, $patient_a] &&
            in_array($created_changes['document_title'][1] ?? null, $template['label'], true) &&
            !str_contains($created['changes'], '%PDF-'),
        'Creation logged without the PDF content',
    );
    $first = $model->history($patient_a);
    $check(count($first['documents']) === 25 && $first['has_more'], 'First history page');
    $check((int) $first['documents'][0]['id'] === $ids[26], 'History newest first');
    $check(!array_key_exists('pdf', $first['documents'][0]), 'History excludes PDF content');
    $last_id = (int) $first['documents'][24]['id'];
    $second = $model->history($patient_a, $last_id);
    $check(count($second['documents']) === 2 && !$second['has_more'], 'Second history page');
    $check($model->history($patient_b)['documents'] === [], 'Patient isolation');
    $check($model->pdf($ids[0]) === $pdf, 'Exact binary PDF round trip');
    $check($model->metadata($ids[0])['created_by_name'] === 'TEST CREATOR', 'Creator snapshot');
    try {
        $model->store($appointment, $template, ['name' => 'TEST'], 'TEST', 'not a PDF');
        throw new LogicException('Invalid PDF was accepted');
    } catch (RuntimeException $error) {
        $check(!($error instanceof LogicException), $error->getMessage());
    }
    $db->delete('appointments', ['id' => $appointment_id]);
    $saved = $model->metadata($ids[0]);
    $check(
        $saved['id_appointments'] === null && $saved['visit_datetime'] === '2020-01-02 12:30:00',
        'Deleted visit retains document and visit date',
    );
    $check($model->pdf($ids[0]) === $pdf, 'Reprints do not regenerate or change the document');
    try {
        $model->delete_document($ids[1], $patient_b, $patient_b);
        throw new LogicException('Wrong patient deletion accepted');
    } catch (InvalidArgumentException $error) {
        $check($model->pdf($ids[1]) === $pdf, 'Wrong patient must retain PDF');
    }
    $model->delete_document($ids[1], $patient_a, $patient_b);
    $check($model->pdf($ids[1]) === '', 'Deleted PDF removed');
    $entry = $db
        ->get_where('audit_log', ['entity' => 'patient_document', 'entity_id' => $ids[1], 'action' => 'deleted'])
        ->row_array();
    $check((int) $entry['id_users'] === $patient_b, 'Deletion attributed to actor');
    $changes = json_decode($entry['changes'], true);
    $check($changes['id_users_customer'][0] === $patient_a, 'Log retains patient');
    $check(in_array($changes['document_title'][0], $template['label'], true), 'Log retains deleted document title');
    $check(!isset($changes['pdf']) && !str_contains($entry['changes'], '%PDF-'), 'PDF content excluded from log');
    $ci->load->library('audit_trail');
    $described = $ci->audit_trail->describe($entry + ['user_name' => 'TEST CREATOR']);
    $check(
        str_contains($described['subject'], 'DOCUMENT TEST A') && str_contains($described['subject'], 'Justifica'),
        'Activity log displays deleted document and patient',
    );
    try {
        $model->delete_document($ids[1], $patient_a, $patient_b);
        throw new LogicException('Duplicate deletion accepted');
    } catch (InvalidArgumentException $error) {
        $check(
            $db
                ->where(['entity' => 'patient_document', 'entity_id' => $ids[1], 'action' => 'deleted'])
                ->count_all_results('audit_log') === 1,
            'No duplicate log entry',
        );
    }
    $db->delete('users', ['id' => $patient_a]);
    $check($model->history($patient_a)['documents'] === [], 'Patient removal cascades to PDFs');
    echo "PASS: binary persistence, patient isolation, pagination, snapshots, invalid PDFs, visit deletion, patient removal, document deletion and activity log.\n";
} finally {
    $db->trans_rollback();
}
