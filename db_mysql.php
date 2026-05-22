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
    $primary = [
        'host' => $host,
        'dbname' => $dbname,
        'user' => $user,
        'password' => $password,
        'label' => 'primary',
    ];

    $candidates = [$primary];

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

    $accountPrefix = $account . '_';
    $prefixedUser = (strpos($user, $accountPrefix) === 0) ? $user : ($accountPrefix . $user);
    $prefixedDb = (strpos($dbname, $accountPrefix) === 0) ? $dbname : ($accountPrefix . $dbname);
    $strippedUser = (strpos($user, $accountPrefix) === 0) ? substr($user, strlen($accountPrefix)) : $user;
    $strippedDb = (strpos($dbname, $accountPrefix) === 0) ? substr($dbname, strlen($accountPrefix)) : $dbname;

    // Build a de-duplicated list: prefixed first, then provided, then stripped fallback.
    $seen = [];
    $built = [];

    $variants = [
        ['label' => 'cpanel-prefixed', 'host' => $host, 'dbname' => $prefixedDb, 'user' => $prefixedUser, 'password' => $password],
        ['label' => 'primary', 'host' => $host, 'dbname' => $dbname, 'user' => $user, 'password' => $password],
        ['label' => 'cpanel-unprefixed', 'host' => $host, 'dbname' => $strippedDb, 'user' => $strippedUser, 'password' => $password],
    ];

    foreach ($variants as $variant) {
        $key = $variant['user'] . '|' . $variant['dbname'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $built[] = $variant;
    }

    $candidates = $built;

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
        try {
            $conn = @new mysqli($attempt['host'], $attempt['user'], $attempt['password']);
        } catch (Throwable $e) {
            $errors[] = $attempt['label'] . ': connect exception for user=' . $attempt['user'] . ' db=' . $attempt['dbname'] . ' error=' . $e->getMessage();
            continue;
        }

        if ($conn->connect_error) {
            $errors[] = $attempt['label'] . ': connect failed for user=' . $attempt['user'] . ' db=' . $attempt['dbname'] . ' error=' . $conn->connect_error;
            continue;
        }

        try {
            $selected = $conn->select_db($attempt['dbname']);
        } catch (Throwable $e) {
            $errors[] = $attempt['label'] . ': select db exception for user=' . $attempt['user'] . ' db=' . $attempt['dbname'] . ' error=' . $e->getMessage();
            $conn->close();
            continue;
        }

        if (!$selected) {
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
