<?php
/** Opt-in read-only smoke test inside the app: php /tmp/integration_simple_agenda.php. */
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir('/var/www/html');
require 'index.php';
require_once APPPATH . 'controllers/Simple.php';
$ci = &get_instance();
foreach (['providers_model', 'secretaries_model', 'roles_model'] as $model) {
    $ci->load->model($model);
}
$ci->lang->load('translations', 'catalan');
$ci->load->library('clinical_record_templates');
$simple = (new ReflectionClass(Simple::class))->newInstanceWithoutConstructor();
foreach (get_object_vars($ci) as $key => $value) {
    $simple->$key = $value;
}
$_SERVER['REQUEST_METHOD'] = 'GET';
unset($_COOKIE[FULL_CALENDAR_COOKIE]);
$summary = [];
foreach ([DB_SLUG_ADMIN, DB_SLUG_PROVIDER, DB_SLUG_SECRETARY] as $role) {
    $user = $ci->db
        ->select('users.id')
        ->from('users')
        ->join('roles', 'roles.id=users.id_roles')
        ->where('roles.slug', $role)
        ->where('users.is_active', 1)
        ->order_by('users.id')
        ->limit(1)
        ->get()
        ->row_array();
    if (!$user) {
        continue;
    }
    session(['user_id' => (int) $user['id'], 'role_slug' => $role]);
    $ci->output->set_output('');
    ob_start();
    $simple->index();
    $html = ob_get_clean() . $ci->output->get_output();
    foreach (['class="ea-simple"', 'simple-date', 'simple-sheet', 'Versió simple'] as $expected) {
        if (!str_contains($html, $expected)) {
            throw new RuntimeException('Simple agenda did not render: ' . $expected);
        }
    }
    if (
        !preg_match('~assets/css/simple(?:\.min)?\.css~', $html) ||
        !preg_match('~assets/js/pages/simple(?:\.min)?\.js~', $html)
    ) {
        throw new RuntimeException('Renamed agenda assets were not rendered.');
    }
    if (session('dest_url') !== site_url('simple')) {
        throw new RuntimeException('Post-login destination still uses the old URL.');
    }
    $clinical = Clinical_record_templates::can_access($role);
    if (
        script_vars('clinical_records') !== $clinical ||
        str_contains($html, 'id="clinical-record-modal"') !== $clinical
    ) {
        throw new RuntimeException('Clinical modal access does not match the role.');
    }
    if (
        $clinical &&
        (!str_contains($html, 'clinical_record_modal') || script_vars('date_format') !== setting('date_format'))
    ) {
        throw new RuntimeException('Clinical record dependencies are missing.');
    }
    $ids = array_column(script_vars('providers'), 'id');
    if ($role === DB_SLUG_PROVIDER && array_diff($ids, [(int) $user['id']])) {
        throw new RuntimeException('Provider filter exposes another professional.');
    }
    if ($role === DB_SLUG_SECRETARY) {
        $allowed = $ci->secretaries_model->find((int) $user['id'])['providers'];
        if (array_diff($ids, $allowed)) {
            throw new RuntimeException('Secretary filter exposes an unrelated professional.');
        }
    }
    $summary[$role] = ['rendered' => true, 'visible_providers' => count($ids)];
}
if (!isset($summary[DB_SLUG_ADMIN])) {
    throw new RuntimeException('No administrator was available for the agenda check.');
}
$ci->output->set_output('');
echo json_encode(['simple_agenda' => $summary]) . PHP_EOL;
