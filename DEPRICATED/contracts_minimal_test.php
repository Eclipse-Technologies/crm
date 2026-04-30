<?php
// contracts_minimal_test.php - Minimal test for contracts table access
$basePath = __DIR__ . DIRECTORY_SEPARATOR;
require_once $basePath . 'db_mysql.php';
$contractSchema = require $basePath . 'contract_schema.php';
$conn = get_mysql_connection();
$fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $contractSchema));
$sql = "SELECT $fields FROM contracts LIMIT 1";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo '<pre>';
    print_r($row);
    echo '</pre>';
    $result->free();
} else {
    echo 'SQL Error: ' . $conn->error;
}
$conn->close();
?>
