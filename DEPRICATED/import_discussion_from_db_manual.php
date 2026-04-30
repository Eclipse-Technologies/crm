<?php
// import_discussion_from_db_manual.php
// For each row in discussion_log where manual_contact_id is not null/blank, insert a new row with contact_id = manual_contact_id
require_once 'db_mysql.php';

$conn = get_mysql_connection();

// Fetch all discussions with a non-empty manual_contact_id
$sql = "SELECT * FROM discussion_log WHERE manual_contact_id IS NOT NULL AND TRIM(manual_contact_id) != ''";
$result = $conn->query($sql);
if (!$result) {
    die('Failed to fetch discussion_log: ' . $conn->error);
}

$count = 0;
while ($row = $result->fetch_assoc()) {
    $fields = [
        'contact_id', 'author', 'timestamp', 'entry_text', 'linked_opportunity_id', 'visibility', 'company', 'manual_contact_id'
    ];
    $values = [
        $row['manual_contact_id'], // use manual_contact_id as contact_id
        $row['author'],
        $row['timestamp'],
        $row['entry_text'],
        $row['linked_opportunity_id'],
        $row['visibility'],
        $row['company'],
        $row['manual_contact_id']
    ];
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $fields_sql = implode(',', array_map(function($f) { return "`$f`"; }, $fields));
    $stmt = $conn->prepare("INSERT INTO discussion_log ($fields_sql) VALUES ($placeholders)");
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
    $count++;
}
$result->free();
$conn->close();
echo "Imported $count discussion log entries using manual_contact_id as contact_id.";
