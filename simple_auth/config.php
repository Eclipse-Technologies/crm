<?php
/**
 * Authentication System Configuration — Eclipse CRM
 * Prefer environment overrides for deployment-specific values.
 */

require_once __DIR__ . '/../env_loader.php';
load_env();

$appBaseUrl = trim((string) getenv('APP_BASE_URL'));
if ($appBaseUrl === '') {
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $scheme = $isHttps ? 'https' : 'http';
        $appBaseUrl = $scheme . '://' . $host;
    } else {
        $appBaseUrl = 'http://localhost/CRM';
    }
}

$sessionCookieSecureRaw = strtolower(trim((string) getenv('AUTH_SESSION_COOKIE_SECURE')));
$sessionCookieSecure = in_array($sessionCookieSecureRaw, ['1', 'true', 'yes', 'on'], true);
$allowSelfRegistrationRaw = strtolower(trim((string) getenv('AUTH_ALLOW_SELF_REGISTRATION')));
$allowSelfRegistration = in_array($allowSelfRegistrationRaw, ['1', 'true', 'yes', 'on'], true);

$passwordAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$passwordOptions = ($passwordAlgo === PASSWORD_ARGON2ID)
    ? [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3,
    ]
    : [
        'cost' => 12,
    ];

return [
    'storage' => [
        'data_dir' => __DIR__ . '/data',
    ],

    'security' => [
        'password_algo' => $passwordAlgo,
        'password_options' => $passwordOptions,

        'session_name' => 'CRM_SESSION',
        'session_lifetime' => 86400,
        'session_cookie_secure' => $sessionCookieSecure,
        'session_cookie_httponly' => true,
        'session_cookie_samesite' => 'Lax',

        'csrf_token_name' => 'csrf_token',
        'csrf_token_length' => 32,

        'max_login_attempts' => 5,
        'lockout_duration' => 900,
        'rate_limit_window' => 900,
    ],

    'app' => [
        'name' => 'Eclipse CRM',
        'base_url' => $appBaseUrl,
        'allow_self_registration' => $allowSelfRegistration,
        'require_email_verification' => false,
        'enable_2fa' => false,
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
        'enabled' => true,
        'log_failed_logins' => true,
        'log_successful_logins' => true,
        'log_registrations' => true,
        'log_password_changes' => true,
    ],
];
