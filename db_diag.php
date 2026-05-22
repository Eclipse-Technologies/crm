<?php
// Temporary DB diagnostics page (remove after production fix).
require_once __DIR__ . '/env_loader.php';
load_env();

header('Content-Type: text/plain; charset=UTF-8');

function diag_env(string $name): string {
    $v = getenv($name);
    return $v === false ? '' : trim((string) $v);
}

function infer_account(): string {
    $path = str_replace('\\', '/', __DIR__);
    if (preg_match('#/home/([^/]+)/#', $path, $m)) {
        return trim((string) ($m[1] ?? ''));
    }
    return '';
}

$isLocal = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1'], true);

if ($isLocal) {
    $host = diag_env('DB_HOST') ?: diag_env('PROD_DB_HOST');
    $db = diag_env('DB_NAME') ?: diag_env('PROD_DB_NAME');
    $user = diag_env('DB_USER') ?: diag_env('PROD_DB_USER');
    $password = diag_env('DB_PASSWORD') ?: diag_env('PROD_DB_PASSWORD');
} else {
    $host = diag_env('PROD_DB_HOST') ?: diag_env('DB_HOST');
    $db = diag_env('PROD_DB_NAME') ?: diag_env('DB_NAME');
    $user = diag_env('PROD_DB_USER') ?: diag_env('DB_USER');
    $password = diag_env('PROD_DB_PASSWORD') ?: diag_env('DB_PASSWORD');
}

$account = diag_env('CPANEL_ACCOUNT') ?: diag_env('CPANEL_DB_PREFIX') ?: infer_account();
$prefix = $account !== '' ? ($account . '_') : '';
$prefUser = ($prefix !== '' && strpos($user, $prefix) !== 0) ? ($prefix . $user) : $user;
$prefDb = ($prefix !== '' && strpos($db, $prefix) !== 0) ? ($prefix . $db) : $db;

$candidates = [];
if (!$isLocal && ($prefUser !== $user || $prefDb !== $db)) {
    $candidates[] = ['label' => 'cpanel-prefixed', 'host' => $host, 'user' => $prefUser, 'db' => $prefDb];
}
$candidates[] = ['label' => 'primary', 'host' => $host, 'user' => $user, 'db' => $db];

echo "DB Diagnostics\n";
echo "============\n";
echo "server_name: " . ($_SERVER['SERVER_NAME'] ?? 'n/a') . "\n";
echo "is_local: " . ($isLocal ? 'yes' : 'no') . "\n";
echo "account_prefix: " . ($account !== '' ? $account : '(empty)') . "\n";
echo "password_present: " . ($password !== '' ? 'yes' : 'no') . "\n\n";

foreach ($candidates as $idx => $c) {
    $n = $idx + 1;
    echo "Attempt $n ({$c['label']})\n";
    echo "host={$c['host']} user={$c['user']} db={$c['db']}\n";

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($c['host'], $c['user'], $password);
    if ($conn->connect_error) {
        echo "connect: FAIL - " . $conn->connect_error . "\n\n";
        continue;
    }

    if (!$conn->select_db($c['db'])) {
        echo "connect: OK\n";
        echo "select_db: FAIL - " . $conn->error . "\n\n";
        $conn->close();
        continue;
    }

    echo "connect: OK\n";
    echo "select_db: OK\n\n";
    $conn->close();
}

echo "Done. Remove db_diag.php after fixing credentials.\n";
