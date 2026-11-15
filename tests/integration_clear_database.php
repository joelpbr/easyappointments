<?php
/** Opt-in integration test: php tests/integration_clear_database.php (local MySQL root/socket). */
define('BASEPATH', __DIR__ . '/../system/');
define('APPPATH', __DIR__ . '/../application/');
define('FCPATH', sys_get_temp_dir() . '/ea-clear-test-' . bin2hex(random_bytes(8)) . '/');
class CI_Controller {}
class EA_Controller extends CI_Controller
{
    public object $db;
}
function &get_instance(): EA_Controller
{
    return $GLOBALS['test_ci'];
}
require APPPATH . 'libraries/Database_backup.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', '');
$name = 'ea_clear_test_' . bin2hex(random_bytes(8));
$conn->query("CREATE DATABASE `$name`");
$conn->select_db($name);
mkdir(FCPATH . 'storage/backups', 0700, true);
$GLOBALS['test_ci'] = new EA_Controller();
$GLOBALS['test_ci']->db = new class ($conn, $name) {
    public string $dbprefix = 'ea_';
    public string $char_set = 'utf8mb4';
    public function __construct(public mysqli $conn_id, public string $database) {}
    public function initialize(): void {}
};
$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$count = static fn(string $table): int => (int) $conn->query("SELECT COUNT(*) FROM `ea_$table`")->fetch_row()[0];
try {
    $conn->query('CREATE TABLE ea_roles (id INT PRIMARY KEY, slug VARCHAR(32)) ENGINE=InnoDB');
    $conn->query("INSERT INTO ea_roles VALUES (1,'admin'),(2,'customer')");
    $conn->query('CREATE TABLE ea_users (id INT PRIMARY KEY, id_roles INT) ENGINE=InnoDB');
    $conn->query('INSERT INTO ea_users VALUES (1,1),(2,2)');
    $conn->query('CREATE TABLE ea_consents (id INT PRIMARY KEY, id_users INT) ENGINE=InnoDB');
    $conn->query('INSERT INTO ea_consents VALUES (1,1),(2,2)');
    $clinical = [
        'whatsapp_reminders',
        'appointment_foot_markers',
        'appointment_records',
        'appointments',
        'patient_records',
        'fiscal_parties',
        'podologo_source_rows',
    ];
    foreach ([...$clinical, 'settings', 'podology_pathologies', 'audit_log', 'migrations'] as $table) {
        $conn->query("CREATE TABLE `ea_$table` (id INT PRIMARY KEY) ENGINE=InnoDB");
        $conn->query("INSERT INTO `ea_$table` VALUES (1)");
    }
    $backup = new Database_backup();
    $conn->query(
        "CREATE TRIGGER reject_clear BEFORE DELETE ON ea_users FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test rollback'",
    );
    try {
        $backup->clear_clinical_data();
        throw new LogicException('Expected failure');
    } catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(), 'Test rollback'), 'Unexpected failure');
    }
    foreach ($clinical as $table) {
        $check($count($table) === 1, 'Rollback lost ' . $table);
    }
    $check($count('users') === 2, 'Rollback lost users');
    $conn->query('DROP TRIGGER reject_clear');
    $previous = $backup->clear_clinical_data();
    $check(is_file($previous), 'Missing safety backup');
    $sql = Database_backup::read($previous);
    $check(str_contains($sql, 'INSERT INTO `ea_users`'), 'Backup missing original users');
    foreach ($clinical as $table) {
        $check($count($table) === 0, 'Did not clear ' . $table);
    }
    foreach (['users', 'consents', 'settings', 'podology_pathologies', 'audit_log', 'migrations'] as $table) {
        $check($count($table) === 1, 'Did not preserve ' . $table);
    }
    echo "PASS: safety backup, clinical deletion, staff/catalog preservation and failure rollback.\n";
} finally {
    $conn->query("DROP DATABASE `$name`");
    foreach (glob(FCPATH . 'storage/backups/*') as $file) {
        unlink($file);
    }
    rmdir(FCPATH . 'storage/backups');
    rmdir(FCPATH . 'storage');
    rmdir(FCPATH);
}
