<?php
/** Opt-in read-only smoke test inside the running app: php /tmp/integration_podologo_calendar.php. */
$_SERVER['argv'] = ['index.php', 'console', 'migrate'];
chdir('/var/www/html');
require 'index.php';
session(['user_id' => 1, 'role_slug' => DB_SLUG_ADMIN]);
require_once APPPATH . 'controllers/Calendar.php';
$ci = &get_instance();
foreach (['appointments_model', 'unavailabilities_model', 'blocked_periods_model', 'secretaries_model'] as $model) {
    $ci->load->model($model);
}
// CodeIgniter supports one constructed controller per request. Reuse the already bootstrapped services.
$calendar = (new ReflectionClass(Calendar::class))->newInstanceWithoutConstructor();
foreach (get_object_vars($ci) as $key => $value) {
    $calendar->$key = $value;
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$summary = [];
foreach (['2012-11-13', '2024-06-03', '2026-11-12'] as $date) {
    $_POST = ['start_date' => $date, 'end_date' => $date, 'record_id' => 'all', 'filter_type' => 'all'];
    foreach (['get_calendar_appointments_for_table_view', 'get_calendar_appointments'] as $method) {
        $calendar->output->set_output('');
        $calendar->$method();
        $data = json_decode($calendar->output->get_output(), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['appointments']) || (isset($data['success']) && $data['success'] === false)) {
            throw new RuntimeException('Calendar feed failed: ' . $method . ' ' . $date);
        }
        if (!$data['appointments']) {
            throw new RuntimeException('Expected historical appointments are missing.');
        }
        foreach ($data['appointments'] as $appointment) {
            if (empty($appointment['provider']['id'])) {
                throw new RuntimeException('Missing calendar provider.');
            }
            if (!isset($appointment['service'], $appointment['customer'])) {
                throw new RuntimeException('Missing calendar related data.');
            }
        }
        $summary[$date][$method] = count($data['appointments']);
    }
}
$calendar->output->set_output('');
echo json_encode(['calendar_feeds_passed' => $summary]) . PHP_EOL;
