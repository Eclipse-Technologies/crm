<?php
require_once 'db_mysql.php'; // Assumes you have a db connection helper
require_once __DIR__ . '/request_guard.php';

require_post_with_csrf();

function commit_import_has_column(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $has = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    $cache[$key] = $has;
    return $has;
}

if (!isset($_SESSION['import_preview']) || !is_array($_SESSION['import_preview'])) {
    die('No import data found.');
}

$rows = $_SESSION['import_preview'];
if (empty($rows)) {
    die('No valid rows available to import.');
}

$conn = get_mysql_connection();
$success = 0;
$fail = 0;
$skipped = 0;
$errors = [];
$db_hard_failure = false;
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
    $conn->begin_transaction();

    $stmt = $conn->prepare("INSERT INTO contacts (first_name, last_name, company, email, phone, address, city, province, postal_code, country, notes, created_at, last_modified, is_customer, tank_number, delivery_date, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $checkStmt = $conn->prepare("SELECT contact_id FROM contacts WHERE LOWER(email) = LOWER(?) LIMIT 1");

    if (!$stmt || !$checkStmt) {
        $conn->rollback();
        $conn->close();
        die('Failed to initialize import statements.');
    }

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

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail++;
            $errors[] = 'Invalid or missing email in preview row.';
            continue;
        }

        $checkStmt->bind_param('s', $email);
        if (!$checkStmt->execute()) {
            $db_hard_failure = true;
            $errors[] = 'Email duplicate check failed: ' . $checkStmt->error;
            break;
        }
        $existingResult = $checkStmt->get_result();
        $exists = $existingResult && $existingResult->num_rows > 0;
        if ($existingResult) {
            $existingResult->free();
        }
        if ($exists) {
            $skipped++;
            continue;
        }

        $stmt->bind_param('sssssssssssssisss', $first_name, $last_name, $company, $email, $phone, $address, $city, $province, $postal_code, $country, $notes, $created_at, $last_modified, $is_customer, $tank_number, $delivery_date, $tags);
        if ($stmt->execute()) {
            $success++;
        } else {
            $fail++;
            $errors[] = $stmt->error;
            $db_hard_failure = true;
            break;
        }
    }

    $stmt->close();
    $checkStmt->close();

    if ($db_hard_failure) {
        $conn->rollback();
    } else {
        $conn->commit();
        unset($_SESSION['import_preview'], $_SESSION['import_type']);
    }
    $conn->close();

    if ($db_hard_failure) {
        echo '<div style="color:red;">Import rolled back due to a database error. No contacts were committed.</div>';
    } elseif ($fail === 0) {
        echo '<div style="color:green;">Successfully imported ' . $success . ' contact(s). Skipped existing emails: ' . $skipped . '.</div>';
    } else {
        echo '<div style="color:#b45309;">Imported ' . $success . ' contact(s), skipped existing emails ' . $skipped . ', failed ' . $fail . '. Errors: ' . implode('<br>', array_slice($errors, 0, 10)) . '</div>';
    }
    echo '<a href="import_contacts.php">Back to Import</a>';
    exit;
}

if ($is_discussion) {
    $hasCompanyColumn = commit_import_has_column($conn, 'discussion_log', 'company');
    $conn->begin_transaction();

    $sql = $hasCompanyColumn
        ? "INSERT INTO discussion_log (contact_id, author, timestamp, entry_text, linked_opportunity_id, visibility, company) VALUES (?, ?, ?, ?, ?, ?, ?)"
        : "INSERT INTO discussion_log (contact_id, author, timestamp, entry_text, linked_opportunity_id, visibility) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $conn->rollback();
        $conn->close();
        die('Failed to initialize discussion import statement.');
    }

    foreach ($rows as $row) {
        $contact_id = trim((string)($row['contact_id'] ?? ''));
        $author = trim((string)($row['author'] ?? ''));
        $timestamp = trim((string)($row['timestamp'] ?? ''));
        $entry_text = trim((string)($row['discussion_text'] ?? $row['entry_text'] ?? ''));
        $linked_opportunity_id = trim((string)($row['linked_opportunity_id'] ?? ''));
        $visibility = trim((string)($row['visibility'] ?? 'private'));
        $company = trim((string)($row['company'] ?? ''));

        if ($contact_id === '' || !ctype_digit($contact_id)) {
            $fail++;
            $errors[] = 'Discussion row missing numeric contact_id.';
            continue;
        }
        if ($entry_text === '') {
            $fail++;
            $errors[] = 'Discussion row missing discussion_text.';
            continue;
        }
        if ($author === '') {
            $author = 'Imported';
        }
        if ($timestamp === '') {
            $timestamp = date('Y-m-d H:i:s');
        }

        if ($hasCompanyColumn) {
            $stmt->bind_param('sssssss', $contact_id, $author, $timestamp, $entry_text, $linked_opportunity_id, $visibility, $company);
        } else {
            $stmt->bind_param('ssssss', $contact_id, $author, $timestamp, $entry_text, $linked_opportunity_id, $visibility);
        }

        if ($stmt->execute()) {
            $success++;
        } else {
            $fail++;
            $errors[] = $stmt->error;
            $db_hard_failure = true;
            break;
        }
    }

    $stmt->close();

    if ($db_hard_failure) {
        $conn->rollback();
    } else {
        $conn->commit();
        unset($_SESSION['import_preview'], $_SESSION['import_type']);
    }
    $conn->close();

    if ($db_hard_failure) {
        echo '<div style="color:red;">Discussion import rolled back due to a database error. No discussion rows were committed.</div>';
    } elseif ($fail === 0) {
        echo '<div style="color:green;">Successfully imported ' . $success . ' discussion log entries.</div>';
    } else {
        echo '<div style="color:#b45309;">Imported ' . $success . ' entries, failed ' . $fail . '. Errors: ' . implode('<br>', array_slice($errors, 0, 10)) . '</div>';
    }
    echo '<a href="import_contacts.php">Back to Import</a>';
    exit;
}

echo '<div style="color:red;">Import type not recognized. Please upload and preview again.</div>';
echo "<p><a href='import_contacts.php'>← Back to Import</a></p>";
