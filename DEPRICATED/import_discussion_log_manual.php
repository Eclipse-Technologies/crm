<?php
// import_discussion_log_manual.php
// Import discussion_log_with_company_id.csv into MySQL, using matched_contact_id as contact_id if present

require_once 'db_mysql.php';
$discussionSchema = [
    'contact_id', 'author', 'timestamp', 'entry_text', 'linked_opportunity_id', 'visibility'
];

$csvFile = __DIR__ . '/discussion_log_with_company_id.csv';
if (!file_exists($csvFile)) {
    die('discussion_log_with_company_id.csv not found');
}

// Preview mode: show mapping and table before import
if (!isset($_POST['confirm_import'])) {
    echo "<h2>Discussion Log Import Preview (manual_contact_id logic)</h2>";
    echo "<p><strong>Target Table:</strong> <code>discussion_log</code></p>";
    echo "<p>The following columns will be imported (using matched_contact_id as contact_id):</p>";
    echo "<ul>";
    foreach ($discussionSchema as $col) {
        echo "<li><code>$col</code></li>";
    }
    echo "</ul>";
    // Show a preview of the first 5 rows with matched_contact_id
    $handle = fopen($csvFile, 'r');
    $header = fgetcsv($handle);
    echo "<table border='1' cellpadding='4' style='border-collapse:collapse;'><tr>";
    foreach ($header as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</tr>";
    $rowCount = 0;
    while (($row = fgetcsv($handle)) !== false && $rowCount < 10) {
        if (empty($row[6])) continue; // matched_contact_id blank, skip
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
    $manualId = trim($data['matched_contact_id'] ?? '');
    if ($manualId === '') continue; // skip if blank
    // Build values for insert
    $values = [
        $manualId, // use matched_contact_id as contact_id
        $data['author'] ?? '',
        $data['timestamp'] ?? '',
        $data['entry_text'] ?? '',
        $data['linked_opportunity_id'] ?? '',
        $data['visibility'] ?? 'public'
    ];
    $fields = implode(',', array_map(function($f) { return "`$f`"; }, $discussionSchema));
    $placeholders = implode(',', array_fill(0, count($discussionSchema), '?'));
    $sql = "INSERT INTO discussion_log ($fields) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
    $count++;
}
fclose($handle);
$conn->close();
echo "Imported $count discussion log entries using matched_contact_id.";
