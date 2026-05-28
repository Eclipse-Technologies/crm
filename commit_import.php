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
$import_type = trim((string) ($_POST['import_type'] ?? ($_SESSION['import_type'] ?? '')));

// Detect if this is a discussion log import (by checking for 'company' and 'entry_text' or 'discussion_text')
$is_discussion = false;
if (!empty($rows) && (isset($rows[0]['company']) && (isset($rows[0]['entry_text']) || isset($rows[0]['discussion_text'])))) {
    $is_discussion = true;
}

if ($import_type === '') {
    $import_type = $is_discussion ? 'discussion_log' : 'contacts';
}

if ($import_type === 'contacts') {
    foreach ($rows as $row) {
        $first_name = trim((string) ($row['first_name'] ?? ''));
        $last_name = trim((string) ($row['last_name'] ?? ''));
        $company = trim((string) ($row['company'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));
        $address = trim((string) ($row['address'] ?? ''));
        $city = trim((string) ($row['city'] ?? ''));
        $province = trim((string) ($row['province'] ?? ''));
        $postal_code = trim((string) ($row['postal_code'] ?? ''));
        $country = trim((string) ($row['country'] ?? ''));
        $notes = trim((string) ($row['notes'] ?? ''));
        $created_at = !empty($row['created_at']) ? $row['created_at'] : date('Y-m-d H:i:s');
        $last_modified = !empty($row['last_modified']) ? $row['last_modified'] : date('Y-m-d H:i:s');
        $is_customer = !empty($row['is_customer']) && (string) $row['is_customer'] !== '0' ? 1 : 0;
        $tank_number = trim((string) ($row['tank_number'] ?? ''));
        $delivery_date = trim((string) ($row['delivery_date'] ?? ''));
        $tags = trim((string) ($row['tags'] ?? ''));

        if ($delivery_date === '' || $delivery_date === '0000-00-00') {
            $delivery_date = null;
        }

        $stmt = $conn->prepare("INSERT INTO contacts (first_name, last_name, company, email, phone, address, city, province, postal_code, country, notes, created_at, last_modified, is_customer, tank_number, delivery_date, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $fail++;
            $errors[] = $conn->error;
            continue;
        }

        $stmt->bind_param('sssssssssssssisss', $first_name, $last_name, $company, $email, $phone, $address, $city, $province, $postal_code, $country, $notes, $created_at, $last_modified, $is_customer, $tank_number, $delivery_date, $tags);
        if ($stmt->execute()) {
            $success++;
        } else {
            $fail++;
            $errors[] = $stmt->error;
        }
        $stmt->close();
    }

    $conn->close();
    unset($_SESSION['import_preview'], $_SESSION['import_type']);

    if ($fail === 0) {
        echo '<div style="color:green;">Successfully imported ' . $success . ' contact(s). Contact IDs were auto-generated.</div>';
    } else {
        echo '<div style="color:red;">Imported ' . $success . ' contact(s), failed ' . $fail . '. Errors: ' . implode('<br>', $errors) . '</div>';
    }
    echo '<a href="import_contacts.php">Back to Import</a>';
    exit;
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
    unset($_SESSION['import_preview'], $_SESSION['import_type']);
    if ($fail === 0) {
        echo '<div style="color:green;">Successfully imported ' . $success . ' discussion log entries.</div>';
    } else {
        echo '<div style="color:red;">Imported ' . $success . ' entries, failed ' . $fail . '. Errors: ' . implode('<br>', $errors) . '</div>';
    }
    echo '<a href="import_contacts.php">Back to Import</a>';
    exit;
}

echo '<div style="color:red;">Import type not recognized. Please upload and preview again.</div>';
echo "<p><a href='import_contacts.php'>← Back to Import</a></p>";
