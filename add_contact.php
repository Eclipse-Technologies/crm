<?php
// Authentication check (without HTML output)
require_once __DIR__ . '/simple_auth/middleware.php';

// Load helper files
require_once 'contact_validator.php';
require_once 'db_mysql.php';
require_once 'forecast_calc.php';
require_once 'error_handler.php';
require_once 'csrf_helper.php';
require_once 'sanitize_helper.php';
require_once 'audit_handler.php';

// Initialize CSRF token
initializeCSRFToken();

// ✅ CSRF Protection: Verify token on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        logWarning('CSRF token validation failed', ['email' => $_POST['email'] ?? '']);
        header('Location: contact_form.php?error=csrf');
        exit;
    }
}


$logFile = 'error_log.txt';
$schema = require __DIR__ . '/contact_schema.php';

$timestamp = date('Y-m-d H:i:s');
$postedType = trim((string) ($_POST['tags'] ?? ''));
if ($postedType === '__custom__') {
    $postedType = trim((string) ($_POST['tags_custom'] ?? ''));
}
$newContact = [
    'first_name' => $_POST['first_name'] ?? '',
    'last_name' => $_POST['last_name'] ?? '',
    'company' => $_POST['company'] ?? '',
    'address_1' => $_POST['address_1'] ?? '',
    'address_2' => $_POST['address_2'] ?? '', 
    'city' => $_POST['city'] ?? '',
    'postal_code' => $_POST['postal_code'] ?? '',
    'province' => $_POST['province'] ?? '',
    'country' => $_POST['country'] ?? '',
    'phone' => $_POST['phone'] ?? '',
    'email' => $_POST['email'] ?? '',
    'tags' => $postedType,
    'created_at' => $timestamp,
    'last_modified' => $timestamp,
    'is_customer' => isset($_POST['is_customer']) ? (($_POST['is_customer'] == '1' || $_POST['is_customer'] === 1) ? 1 : 0) : 0,
    'delivery_date' => (isset($_POST['delivery_date']) && $_POST['delivery_date'] !== '' && $_POST['delivery_date'] !== '0000-00-00') ? $_POST['delivery_date'] : null,
    'tank_number' => (isset($_POST['tank_number']) && is_numeric($_POST['tank_number']) && $_POST['tank_number'] !== '') ? $_POST['tank_number'] : null,
    'source' => 'manual_entry'
];

// ✅ ENHANCED: Sanitize input before validation
$newContact = sanitizeContact($newContact);
// Robustly set delivery_date to NULL if empty or invalid
if (!isset($newContact['delivery_date']) || trim($newContact['delivery_date']) === '' || $newContact['delivery_date'] === '0000-00-00') {
    $newContact['delivery_date'] = null;
}
// Optionally, repeat for any other DATE fields

// ✅ ENHANCED: Validate contact
$errors = validateContact($newContact);

// ✅ ENHANCED: Check for duplicate email
if (empty($errors) && !empty($newContact['email'])) {
    if (!isEmailUnique($newContact['email'])) {
        $errors['email'] = "Email already exists in the system.";
    }
}

if (!empty($errors)) {
    // Display errors to user
    showValidationErrors($errors);
    
    // Log validation errors
    logWarning('Contact validation failed', [
        'email' => $newContact['email'],
        'errors' => $errors,
    ]);

    echo "<p><a href=\"javascript:history.back()\">← Go back</a></p>";
    exit;
}

try {
    $conn = get_mysql_connection();
    $fields = array_intersect(array_keys($newContact), array_diff($schema, ['id', 'contact_id']));
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $columns = implode(',', array_map(function($f) { return "`$f`"; }, $fields));
    $stmt = $conn->prepare("INSERT INTO contacts ($columns) VALUES ($placeholders)");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $values = array_map(function($f) use ($newContact) { return $newContact[$f]; }, $fields);
    // Build types string, use 's' for string, but bind NULL as null
    $types = '';
    foreach ($fields as $f) {
        if ($f === 'delivery_date') {
            $types .= 's'; // MySQLi requires a type, but will accept null for date
        } else {
            $types .= 's';
        }
    }
    // Convert empty string to null for delivery_date
    foreach ($fields as $i => $f) {
        if ($f === 'delivery_date' && ($values[$i] === '' || $values[$i] === null)) {
            $values[$i] = null;
        }
    }
    $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
    $conn->close();
    // Log success
    logInfo('Contact created successfully', [
        'email' => $newContact['email'],
        'name' => $newContact['first_name'] . ' ' . $newContact['last_name'],
    ]);
    // ✅ AUDIT: Log contact creation
    auditCreateContact($newContact, 'success');
    // Redirect to confirmation
    header('Location: contact_success.php');
    exit;
} catch (Exception $e) {
    $errorMsg = getErrorMessage($e);
    showError('Unable to Save Contact', $errorMsg, $e->getMessage());
    // Log error
    logError('Contact save failed', [
        'email' => $newContact['email'],
        'exception' => $e->getMessage(),
    ]);
    // ✅ AUDIT: Log failed creation attempt
    auditCreateContact($newContact, 'failed', $e->getMessage());
    echo "<p><a href=\"javascript:history.back()\">← Try again</a></p>";
    exit;
}
?>
