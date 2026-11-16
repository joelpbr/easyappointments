<?php
/** Give Onicolisis its own icon. Preview by default; pass --apply to save. */
require dirname(__DIR__, 2) . '/config.php';

$db = new mysqli(Config::DB_HOST, Config::DB_USERNAME, Config::DB_PASSWORD, Config::DB_NAME);
$db->set_charset('utf8mb4');
$db->begin_transaction();
try {
    $rows = $db->query("SELECT id, name, image_filename FROM ea_podology_pathologies WHERE name = 'Onicolisis' FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected exactly one Onicolisis pathology.');
    }
    $row = $rows[0];
    if ($row['image_filename'] === '52_onicolisis.svg') {
        $db->rollback();
        exit("Onicolisis already has its own icon.\n");
    }
    if ($row['image_filename'] !== '45_rectangulo_dos_esquinas.svg') {
        throw new RuntimeException('The icon has changed; refusing to replace a custom icon.');
    }
    if (!is_file(dirname(__DIR__, 2) . '/assets/img/podology_pathologies/52_onicolisis.svg')) {
        throw new RuntimeException('The new icon is missing.');
    }
    echo "Onicolisis: {$row['image_filename']} -> 52_onicolisis.svg\n";
    if (!in_array('--apply', $argv, true)) {
        $db->rollback();
        exit("Preview only. Pass --apply to save.\n");
    }
    $backup = dirname(__DIR__, 2) . '/storage/onicolysis-icon-before-' . date('Ymd-His') . '.json';
    if (file_put_contents($backup, json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException('Could not save the previous icon assignment.');
    }
    $stmt = $db->prepare('UPDATE ea_podology_pathologies SET image_filename = ? WHERE id = ?');
    $filename = '52_onicolisis.svg';
    $stmt->bind_param('si', $filename, $row['id']);
    $stmt->execute();
    $db->commit();
    echo "Saved. Previous assignment: $backup\n";
} catch (Throwable $exception) {
    $db->rollback();
    throw $exception;
}
