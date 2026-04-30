<?php
// import_discussion_log.php
// Import discussion_log.csv into MySQL and update customer records

require_once 'db_mysql.php';
$discussionSchema = require __DIR__ . '/discussion_schema.php';
$customerSchema = require __DIR__ . '/customer_schema.php';


$csvFile = __DIR__ . '/discussion_log.csv';
if (!file_exists($csvFile)) {
    die('discussion_log.csv not found');
}

// Preview mode: show mapping and table before import
if (!isset($_POST['confirm_import'])) {
    echo "<h2>Discussion Log Import Preview</h2>";
    echo "<p><strong>Target Table:</strong> <code>discussion_log</code></p>";
    echo "<p>The following columns will be imported:</p>";
    echo "<ul>";
    foreach ($discussionSchema as $col) {
        echo "<li><code>$col</code></li>";
    }
    echo "</ul>";
    // Show a preview of the first 5 rows
    $handle = fopen($csvFile, 'r');
    $header = fgetcsv($handle);
    echo "<table border='1' cellpadding='4' style='border-collapse:collapse;'><tr>";
    foreach ($header as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</tr>";
    $rowCount = 0;
    while (($row = fgetcsv($handle)) !== false && $rowCount < 5) {
        echo "<tr>";
        foreach ($row as $cell) {
            echo "<td>" . htmlspecialchars($cell) . "</td>";
        }
        echo "</tr>";
        $rowCount++;
    }
    fclose($handle);
    echo "</table>";
    echo '<form method="POST"><input type="hidden" name="confirm_import" value="1"><button type="submit">Confirm Import</button></form>';
    exit;
}

$conn = get_mysql_connection();
$handle = fopen($csvFile, 'r');
$header = fgetcsv($handle);
$count = 0;
while (($row = fgetcsv($handle)) !== false) {
    $data = array_combine($header, $row);
    // Insert into discussion_log table
    $fields = [];
    $placeholders = [];
    $values = [];
    foreach ($discussionSchema as $col) {
        $fields[] = "`$col`";
        $placeholders[] = '?';
        $values[] = $data[$col] ?? null;
    }
    $sql = "INSERT INTO discussion_log (" . implode(",", $fields) . ") VALUES (" . implode(",", $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
    $count++;
    // Optionally update customer record (example: update last_modified)
    if (!empty($data['contact_id'])) {
        $update = $conn->prepare("UPDATE customers SET last_modified = ? WHERE customer_id = ?");
        $update->bind_param('ss', $data['timestamp'], $data['contact_id']);
        $update->execute();
        $update->close();
    }
}
fclose($handle);
$conn->close();
echo "Imported $count discussion log entries and updated customers.";
