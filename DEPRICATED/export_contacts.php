
<?php
// Modern export_contacts.php: User-friendly HTML output
require_once 'csv_handler.php';
require_once 'db_mysql.php';
$schema = require __DIR__ . '/contact_schema.php';

function fetch_all_contacts($schema, $filters = []) {
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
    $sql = "SELECT $fields FROM contacts";
    $where = [];
    $params = [];
    foreach ($filters as $key => $value) {
        $where[] = "$key = ?";
        $params[] = $value;
    }
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $contacts;
}

$filters = [];
foreach ($schema as $field) {
    if (!empty($_GET[$field])) {
        $filters[$field] = $_GET[$field];
    }
}

$contacts = fetch_all_contacts($schema, $filters);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Contacts to CSV</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="style.css">
</head>
<body style="background:#f7f7f7;font-family:Arial,sans-serif;">
<div style="max-width:600px;margin:40px auto;padding:32px 24px;background:white;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
<h2 style="margin-top:0;">Export Contacts to CSV</h2>
<?php
try {
    if (count($contacts) > 0) {
        writeCSV('contacts_export.csv', $contacts, $schema);
        $export_count = count($contacts);
        echo '<div class="alert-success">Export complete. ' . $export_count . ' contacts exported to <b>contacts_export.csv</b>.</div>';
    } else {
        echo '<div class="alert-warning">No matching contacts found to export.</div>';
    }
} catch (Exception $e) {
    echo '<div class="alert-danger">Export failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
<a href="contacts_list.php" class="btn" style="margin-top:24px;display:inline-block;">&larr; Back to Contacts</a>
</div>
</body>
</html>
?>

