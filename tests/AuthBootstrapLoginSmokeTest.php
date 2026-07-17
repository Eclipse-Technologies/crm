<?php

require_once __DIR__ . '/../simple_auth/Auth.php';
require_once __DIR__ . '/../db_mysql.php';

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
}

$bootstrapUser = 'bootstrap_test_' . bin2hex(random_bytes(3));
$bootstrapPassword = 'BootstrapPass123!';
putenv('CRM_BOOTSTRAP_AUTH_USERNAME=' . $bootstrapUser);
putenv('CRM_BOOTSTRAP_AUTH_PASSWORD=' . $bootstrapPassword);

$conn = get_mysql_connection();
if (!$conn) {
    echo "FAILED: database connection unavailable\n";
    exit(1);
}

$stmt = $conn->prepare('DELETE FROM users WHERE username = ?');
$stmt->bind_param('s', $bootstrapUser);
$stmt->execute();
$stmt->close();
$conn->close();

$config = [
    'security' => [
        'session_lifetime' => 86400,
        'session_cookie_secure' => false,
        'session_cookie_httponly' => true,
        'session_cookie_samesite' => 'Lax',
        'csrf_token_length' => 32,
        'session_name' => 'CRM_TEST_SESSION',
        'password_algo' => PASSWORD_BCRYPT,
        'password_options' => ['cost' => 12],
    ],
    'app' => [
        'name' => 'Eclipse CRM',
        'require_email_verification' => false,
        'admin_email' => 'admin@example.com',
    ],
    'validation' => [
        'username_min_length' => 3,
        'username_max_length' => 50,
        'password_min_length' => 8,
        'password_require_uppercase' => true,
        'password_require_lowercase' => true,
        'password_require_number' => true,
        'password_require_special' => true,
    ],
    'logging' => [
        'enabled' => false,
        'log_failed_logins' => false,
        'log_successful_logins' => false,
        'log_registrations' => false,
        'log_password_changes' => false,
    ],
];

$auth = new Auth($config);
$result = $auth->login($bootstrapUser, $bootstrapPassword, false);
assertTrue($result['success'] ?? false, 'Expected bootstrap login to succeed', $failures);

$conn = get_mysql_connection();
$stmt = $conn->prepare('SELECT id, username, email, role, is_active, is_verified FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $bootstrapUser);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

assertTrue(!empty($row), 'Expected bootstrap login to create a user record', $failures);
assertTrue(($row['is_active'] ?? 0) == 1, 'Expected bootstrap user to be active', $failures);
assertTrue(($row['is_verified'] ?? 0) == 1, 'Expected bootstrap user to be verified', $failures);

if (!empty($failures)) {
    echo 'FAILED (' . count($failures) . '):' . PHP_EOL;
    foreach ($failures as $failure) {
        echo '- ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo 'PASS: bootstrap auth login works.' . PHP_EOL;
exit(0);
