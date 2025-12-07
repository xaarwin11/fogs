<?php
// One-time migration: move order IDs from kds_hidden.json into orders.hidden_in_kds = 1
require_once __DIR__ . '/../../db.php';

echo "Starting KDS hidden migration...\n";
$file = __DIR__ . '/../kds_hidden.json';
if (!is_readable($file)) {
    echo "No kds_hidden.json found or not readable. Exiting.\n";
    exit(0);
}

$raw = @file_get_contents($file);
$arr = json_decode($raw, true);
if (!is_array($arr) || count($arr) === 0) {
    echo "No IDs to migrate. Exiting.\n";
    exit(0);
}

$ids = array_map('intval', $arr);
$mysqli = get_db_conn();

$colRes = $mysqli->query("SHOW COLUMNS FROM `orders` LIKE 'hidden_in_kds'");
if (!($colRes && $colRes->num_rows > 0)) {
    echo "Column `hidden_in_kds` not found in `orders` table. Please run the ALTER TABLE statement first:\n";
    echo "ALTER TABLE `orders` ADD COLUMN `hidden_in_kds` TINYINT(1) NOT NULL DEFAULT 0;\n";
    exit(1);
}

$stmt = $mysqli->prepare('UPDATE `orders` SET `hidden_in_kds` = 1 WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo "Prepare failed: " . $mysqli->error . "\n";
    exit(1);
}

$count = 0;
foreach ($ids as $id) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $count++;
}
$stmt->close();
$mysqli->close();

echo "Migration complete. Updated hidden_in_kds for {$count} orders.\n";
echo "You may now remove or archive `staff/kds_hidden.json` if everything looks good.\n";

?>
