<?php
// discussion_logger.php
require_once 'csv_handler.php';

// Define the logging function
require_once 'db_mysql.php';
function logDiscussionEntry(array $data): bool {
    $conn = get_mysql_connection();
    $schema = require __DIR__ . '/discussion_schema.php';
    $fields = [];
    $placeholders = [];
    $values = [];
    foreach ($schema as $col) {
        if ($col === 'timestamp') {
            $fields[] = '`timestamp`';
            $placeholders[] = '?';
            $values[] = date('Y-m-d H:i:s');
        } else if ($col === 'entry_text') {
            $fields[] = '`entry_text`';
            $placeholders[] = '?';
            $values[] = isset($data[$col]) ? str_replace(["\r", "\n"], ' ', $data[$col]) : '';
        } else if ($col === 'author') {
            $fields[] = '`author`';
            $placeholders[] = '?';
            $values[] = $data[$col] ?? 'System';
        } else if ($col === 'linked_opportunity_id') {
            $fields[] = '`linked_opportunity_id`';
            $placeholders[] = '?';
            $values[] = $data[$col] ?? null;
        } else if ($col === 'visibility') {
            $fields[] = '`visibility`';
            $placeholders[] = '?';
            $values[] = $data[$col] ?? 'public';
        } else {
            $fields[] = "`$col`";
            $placeholders[] = '?';
            $values[] = $data[$col] ?? '';
        }
    }
    $sql = "INSERT INTO discussion_log (" . implode(",", $fields) . ") VALUES (" . implode(",", $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        file_put_contents('error_log.txt', "[" . date('Y-m-d H:i:s') . "] Failed to prepare statement: $sql\n", FILE_APPEND);
        return false;
    }
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    $result = $stmt->execute();
    if (!$result) {
        file_put_contents('error_log.txt', "[" . date('Y-m-d H:i:s') . "] Failed to insert discussion: " . $stmt->error . "\n", FILE_APPEND);
    }
    $stmt->close();
    $conn->close();
    return $result;
}

// Only run request handling when this file is called directly, not when included.
if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    require_once 'csrf_helper.php';
    require_once __DIR__ . '/simple_auth/middleware.php';

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }

    $data = $_POST;
    $contactId = $data['contact_id'] ?? null;

    if (!empty($data) && !empty($contactId) && logDiscussionEntry($data)) {
        header('Location: contact_view.php?id=' . urlencode((string) $contactId));
        exit;
    }

    header('Location: contacts_list.php');
    exit;
}
?>

