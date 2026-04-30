<?php
// discussion_mysql.php - MySQL handler for discussions
require_once 'db_mysql.php';

function fetch_discussions_mysql($contact_id, $schema) {
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
    $stmt = $conn->prepare("SELECT $fields FROM discussions WHERE contact_id = ? ORDER BY timestamp DESC");
    $stmt->bind_param('s', $contact_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $rows;
}
