<?php
/**
 * Authentication Middleware
 * 
 * Include this file at the top of any protected page
 * Usage: require_once __DIR__ . '/middleware.php';
 */

require_once __DIR__ . '/Auth.php';

// Load configuration
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die('Authentication configuration not found. Please set up auth/config.php');
}

$config = require $configFile;

// Initialize auth
$auth = new Auth($config);

// Check if user is authenticated
if (!$auth->isAuthenticated()) {
    $reason = 'login_required';
    if (isset($_SESSION['user_id'], $_SESSION['session_token'])) {
        $token = trim((string) $_SESSION['session_token']);
        $uid = (int) $_SESSION['user_id'];
        $sessionRow = (new SessionDataStore())->fetchOne($token, $uid);
        if (!$sessionRow || (isset($sessionRow['expires_at']) && strtotime($sessionRow['expires_at']) <= time())) {
            $reason = 'session_expired';
        } else {
            $reason = 'reauth_required';
        }
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'], $_SESSION['role'], $_SESSION['session_token'], $_SESSION['session_lifetime']);
    }

    $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $basePath = $scriptDir === '' ? '' : '/' . $scriptDir;
    $loginPath = ($basePath === '' ? '' : $basePath) . '/simple_auth/login.php';
    $currentPath = $_SERVER['REQUEST_URI'] ?? (($basePath === '' ? '' : $basePath) . '/');
    $separator = strpos($loginPath, '?') === false ? '?' : '&';
    $location = $loginPath . $separator . http_build_query([
        'reason' => $reason,
        'redirect' => $currentPath,
    ]);

    if (!headers_sent()) {
        header('Location: ' . $location);
        exit;
    }

    echo '<div style="background:#fee;border:1px solid #fcc;border-radius:4px;padding:15px;margin:20px;">';
    echo '<strong>Authentication required.</strong> Please <a href="' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '">sign in again</a>.';
    echo '</div>';
    exit;
}

// Make auth and current user available globally
$GLOBALS['auth'] = $auth;
$GLOBALS['current_user'] = $auth->getCurrentUser();

/**
 * Helper function to get current authenticated user
 */
function auth_current_user() {
    return $GLOBALS['current_user'] ?? null;
}

/**
 * Helper function to check if user is authenticated
 */
function auth_check() {
    return isset($GLOBALS['current_user']) && $GLOBALS['current_user'] !== null;
}

/**
 * Helper function to get auth instance
 */
function auth() {
    return $GLOBALS['auth'] ?? null;
}
