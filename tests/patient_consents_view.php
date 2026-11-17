<?php
// Regression: settings views receive controller data through vars(), not local PHP variables.
function extend($layout) {}
function section($name) {}
function end_section($name) {}
function component($name) {}
function lang($key) { return $key; }
function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function site_url($path) { return '/index.php/' . $path; }
function vars($key) { return $GLOBALS['consent_view_data'][$key]; }
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function render_consents(): string
{
    ob_start();
    require __DIR__ . '/../application/views/pages/patient_consents.php';
    return ob_get_clean();
}
$GLOBALS['consent_view_data'] = [
    'entries' => [[
        'id_users_customer' => 123, 'first_name' => 'Test <patient>', 'last_name' => 'Example',
        'signed_at' => '2026-11-17 16:51:08', 'signer_name' => 'Test signer',
        'collected_by_name' => 'Test collector', 'language' => 'ca', 'id_patient_documents' => 456,
    ]],
    'total' => 31, 'page' => 1, 'pages' => 2, 'query' => 'Test & patient',
];
$html = render_consents();
foreach (['Test &lt;patient&gt;', 'document_id=456', '17/11/2026 16:51', 'page=2', 'Test &amp; patient'] as $expected) {
    if (!str_contains($html, $expected)) {
        throw new RuntimeException('Missing rendered consent data: ' . $expected);
    }
}
$GLOBALS['consent_view_data']['entries'] = [];
$GLOBALS['consent_view_data']['total'] = 0;
$GLOBALS['consent_view_data']['pages'] = 1;
if (!str_contains(render_consents(), 'no_records_found')) {
    throw new RuntimeException('Empty list message is missing.');
}
echo "PASS: consent list reads controller variables, renders PDF links and pagination, and handles empty results.\n";
