<?php
require_once __DIR__ . '/request_guard.php';
require_once __DIR__ . '/db_mysql.php';

require_post_with_csrf_json();

header('Content-Type: application/json');

$contactId = trim((string) ($_POST['contact_id'] ?? ''));
$field = trim((string) ($_POST['field'] ?? ''));
$value = trim((string) ($_POST['value'] ?? ''));

if ($contactId === '' || $field === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing contact_id or field']);
    exit;
}

$schema = require __DIR__ . '/contact_schema.php';
$allowedFields = array_values(array_diff($schema, ['contact_id', 'created_at', 'last_modified']));

if (!in_array($field, $allowedFields, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Field is not editable']);
    exit;
}

if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

$conn = get_mysql_connection();
$sql = "UPDATE contacts SET `$field` = ?, last_modified = NOW() WHERE contact_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed']);
    $conn->close();
    exit;
}

$stmt->bind_param('ss', $value, $contactId);
$ok = $stmt->execute();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Update failed']);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'value' => $value]);
