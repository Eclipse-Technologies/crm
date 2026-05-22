<?php
require_once __DIR__ . '/simple_auth/middleware.php';
require_once 'db_mysql.php';

$conn = get_mysql_connection();

echo "=== All Discussions in Database (Last 20) ===\n";
$result = $conn->query("SELECT id, contact_id, author, entry_text, timestamp FROM discussion_log ORDER BY id DESC LIMIT 20");
$count = 0;
while ($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
    $count++;
}
echo "\nTotal shown: $count\n";

echo "\n=== Discussions for contact_id = 318 ===\n";
$result = $conn->query("SELECT id, contact_id, author, entry_text, timestamp FROM discussion_log WHERE contact_id = '318'");
$count = 0;
while ($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
    $count++;
}
echo "\nTotal for contact 318: $count\n";

echo "\n=== Debug Log Contents ===\n";
if (file_exists(__DIR__ . '/debug_log.txt')) {
    echo file_get_contents(__DIR__ . '/debug_log.txt');
} else {
    echo "No debug_log.txt found\n";
}

$conn->close();
?>
