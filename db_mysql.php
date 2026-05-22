<?php
// db_mysql.php - MySQL connection for CRM
require_once __DIR__ . '/env_loader.php';
load_env();

function crm_get_env(string $name): string {
    $value = getenv($name);
    return $value === false ? '' : trim((string)$value);
}

function crm_first_env(array $names): string {
    foreach ($names as $name) {
        $value = crm_get_env($name);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function crm_infer_cpanel_account(): string {
    $path = str_replace('\\', '/', __DIR__);
    if (preg_match('#/home/([^/]+)/#', $path, $m)) {
        return trim((string) ($m[1] ?? ''));
    }
    return '';
}

function crm_build_connection_candidates(string $host, string $dbname, string $user, string $password, bool $isLocal): array {
    $candidates = [[
        'host' => $host,
        'dbname' => $dbname,
        'user' => $user,
        'password' => $password,
        'label' => 'primary',
    ]];

    if ($isLocal) {
        return $candidates;
    }

    $account = crm_first_env(['CPANEL_ACCOUNT', 'CPANEL_DB_PREFIX']);
    if ($account === '') {
        $account = crm_infer_cpanel_account();
    }

    if ($account === '') {
        return $candidates;
    }

    $prefixedUser = (strpos($user, '_') === false) ? ($account . '_' . $user) : $user;
    $prefixedDb = (strpos($dbname, '_') === false) ? ($account . '_' . $dbname) : $dbname;

    if ($prefixedUser !== $user || $prefixedDb !== $dbname) {
        $candidates[] = [
            'host' => $host,
            'dbname' => $prefixedDb,
            'user' => $prefixedUser,
            'password' => $password,
            'label' => 'cpanel-prefixed',
        ];
    }

    return $candidates;
}

function get_mysql_connection() {
    $isLocal = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1']);

    if ($isLocal) {
        $host = crm_first_env(['DB_HOST', 'PROD_DB_HOST']);
        $dbname = crm_first_env(['DB_NAME', 'PROD_DB_NAME']);
        $user = crm_first_env(['DB_USER', 'PROD_DB_USER']);
        $password = crm_first_env(['DB_PASSWORD', 'PROD_DB_PASSWORD']);

        $hasCompleteEnv = ($host !== '' && $dbname !== '' && $user !== '');
    } else {
        // Production accepts either PROD_DB_* or DB_* names.
        $host = crm_first_env(['PROD_DB_HOST', 'DB_HOST']);
        $dbname = crm_first_env(['PROD_DB_NAME', 'DB_NAME']);
        $user = crm_first_env(['PROD_DB_USER', 'DB_USER']);
        $password = crm_first_env(['PROD_DB_PASSWORD', 'DB_PASSWORD']);

        $hasCompleteEnv = ($host !== '' && $dbname !== '' && $user !== '' && $password !== '');
    }

    if (!$hasCompleteEnv) {
        $config = require __DIR__ . '/config.local.php';
        $env = $isLocal ? 'local' : 'production';
        $db = $config[$env];
        $host = (string) ($db['host'] ?? '');
        $dbname = (string) ($db['dbname'] ?? '');
        $user = (string) ($db['user'] ?? '');
        $password = (string) ($db['password'] ?? '');

        // Final fallback to env vars when config.local.php keeps password blank.
        if ($password === '') {
            $password = crm_first_env(['PROD_DB_PASSWORD', 'DB_PASSWORD']);
        }
    }

    $attempts = crm_build_connection_candidates($host, $dbname, $user, $password, $isLocal);
    $errors = [];

    foreach ($attempts as $attempt) {
        $conn = @new mysqli($attempt['host'], $attempt['user'], $attempt['password']);
        if ($conn->connect_error) {
            $errors[] = $attempt['label'] . ': connect failed for user=' . $attempt['user'] . ' db=' . $attempt['dbname'] . ' error=' . $conn->connect_error;
            continue;
        }

        if (!$conn->select_db($attempt['dbname'])) {
            $errors[] = $attempt['label'] . ': select db failed for user=' . $attempt['user'] . ' db=' . $attempt['dbname'] . ' error=' . $conn->error;
            $conn->close();
            continue;
        }

        return $conn;
    }

    error_log('MySQL connection attempts failed. ' . implode(' | ', $errors));
    die('Database connection error. Please contact support.');
}
?>
