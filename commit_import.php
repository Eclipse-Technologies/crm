<?php
require_once 'db_mysql.php'; // Assumes you have a db connection helper
require_once __DIR__ . '/request_guard.php';

require_post_with_csrf();

if (!isset($_SESSION['import_preview']) || !is_array($_SESSION['import_preview'])) {
    die('No import data found.');
}

$rows = $_SESSION['import_preview'];
$conn = get_mysql_connection();
$success = 0;
$fail = 0;
$errors = [];

// Detect if this is a discussion log import (by checking for 'company' and 'entry_text' or 'discussion_text')
$is_discussion = false;
if (!empty($rows) && (isset($rows[0]['company']) && (isset($rows[0]['entry_text']) || isset($rows[0]['discussion_text'])))) {
    $is_discussion = true;
}

if ($is_discussion) {
    foreach ($rows as $row) {
        $contact_id = null; // Leave blank as per user request
        $author = $row['author'] ?? '';
        $timestamp = $row['timestamp'] ?? date('Y-m-d H:i:s');
        $entry_text = $row['discussion_text'] ?? $row['entry_text'] ?? '';
        $linked_opportunity_id = $row['linked_opportunity_id'] ?? null;
        $visibility = $row['visibility'] ?? 'private';
        $company = $row['company'] ?? '';

        $stmt = $conn->prepare("INSERT INTO discussion_log (contact_id, author, timestamp, entry_text, linked_opportunity_id, visibility, company) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssss', $contact_id, $author, $timestamp, $entry_text, $linked_opportunity_id, $visibility, $company);
        if ($stmt->execute()) {
            $success++;
        } else {
            $fail++;
            $errors[] = $conn->error;
        }
        $stmt->close();
    }
    $conn->close();
    unset($_SESSION['import_preview']);
    if ($fail === 0) {
        echo '<div style="color:green;">Successfully imported ' . $success . ' discussion log entries.</div>';
    } else {
        echo '<div style="color:red;">Imported ' . $success . ' entries, failed ' . $fail . '. Errors: ' . implode('<br>', $errors) . '</div>';
    }
    echo '<a href="import_contacts.php">Back to Import</a>';
    exit;
}
    echo "<p><a href='import_contacts.php'>← Back to Import</a></p>";

    echo "</div>";
