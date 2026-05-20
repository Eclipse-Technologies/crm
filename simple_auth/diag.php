<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e !== null) {
        echo "\n\n--- SHUTDOWN ERROR ---\n";
        echo $e['message'] . "\n";
        echo 'File: ' . $e['file'] . ':' . $e['line'] . "\n";
    }
});

echo "CRM Auth Diagnostics\n";
echo "====================\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'SAPI: ' . PHP_SAPI . "\n";
echo 'mysqli loaded: ' . (extension_loaded('mysqli') ? 'yes' : 'no') . "\n";
echo 'argon2id available: ' . (defined('PASSWORD_ARGON2ID') ? 'yes' : 'no') . "\n\n";

$root = dirname(__DIR__);
$configPath = __DIR__ . '/config.php';
$envPath = $root . '/.env';
$dbConfigPath = $root . '/config.local.php';

echo 'config.php exists: ' . (is_file($configPath) ? 'yes' : 'no') . "\n";
echo 'config.php readable: ' . (is_readable($configPath) ? 'yes' : 'no') . "\n";
echo '.env exists: ' . (is_file($envPath) ? 'yes' : 'no') . "\n";
echo 'config.local.php exists: ' . (is_file($dbConfigPath) ? 'yes' : 'no') . "\n\n";

echo "Loading Auth.php...\n";
require_once __DIR__ . '/Auth.php';
echo "Auth.php loaded.\n";

echo "Loading config...\n";
$config = require $configPath;
echo "Config loaded.\n";

echo "Constructing Auth...\n";
$auth = new Auth($config);
echo "Auth constructed OK.\n";

echo "Checking auth state...\n";
$isAuth = $auth->isAuthenticated();
echo 'isAuthenticated(): ' . ($isAuth ? 'true' : 'false') . "\n";

echo "\nDiagnostics complete.\n";
