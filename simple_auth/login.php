<?php
// Session initialization is now handled by Auth
/**
 * User Login Page
 */
if (!is_dir(__DIR__ . '/../logs')) {
    @mkdir(__DIR__ . '/../logs', 0755, true);
}
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../logs/errors.log');

if (file_exists(__DIR__ . '/../error_handler.php')) {
    require_once __DIR__ . '/../error_handler.php';
}

require_once __DIR__ . '/Auth.php';

if (isset($_GET['crm_path_probe']) && (string) $_GET['crm_path_probe'] === '1') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'probe=login-path' . PHP_EOL;
    echo '__FILE__=' . __FILE__ . PHP_EOL;
    echo '__DIR__=' . __DIR__ . PHP_EOL;
    echo 'cwd=' . getcwd() . PHP_EOL;
    exit;
}

// Load config
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die('Configuration file not found. Please create auth/config.php from auth/config.example.php or run setup.php first.');
}
$config = require $configFile;

try {
    $auth = new Auth($config);
} catch (Throwable $e) {
    $bootstrapMessage = 'simple_auth/login.php bootstrap failure: ' . $e->getMessage();
    @error_log($bootstrapMessage);
    @file_put_contents(__DIR__ . '/login_debug.log', '[' . date('Y-m-d H:i:s') . '] ' . $bootstrapMessage . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    if (isset($_GET['crm_db_debug']) || isset($_GET['db_debug'])) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $bootstrapMessage . PHP_EOL;
        exit;
    }
    echo 'Login is temporarily unavailable. Please contact support.';
    exit;
}
$error = null; // Always initialize $error
$reason = $_GET['reason'] ?? '';
$notice = null;
$scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$scriptSegments = $scriptDir === '' ? [] : explode('/', $scriptDir);
array_pop($scriptSegments);
$basePath = empty($scriptSegments) ? '' : '/' . implode('/', $scriptSegments);
$loginPath = ($basePath === '' ? '' : $basePath) . '/simple_auth/login.php';
$defaultTarget = ($basePath === '' ? '' : $basePath) . '/contacts_list.php';

if ($reason === 'session_expired') {
    $notice = 'Your session expired. Please sign in again.';
} else if ($reason === 'reauth_required') {
    $notice = 'Please sign in again to continue.';
} else if ($reason === 'login_required') {
    $notice = 'Please sign in to continue.';
}

// Check if already logged in
if ($auth->isAuthenticated()) {
    $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
    $target = ($redirect && strpos($redirect, '/') === 0) ? $redirect : $defaultTarget;
    if (!headers_sent()) {
        header('Location: ' . $target);
        exit;
    } else {
        echo '<script>window.location.href = ' . json_encode($target) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target) . '"></noscript>';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!$auth->verifyCsrfToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $result = $auth->login($usernameOrEmail, $password, $rememberMe);
        if ($result['success']) {
            // Redirect to the URL in the 'redirect' parameter if present, else to /index.php
            $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
            if ($redirect && strpos($redirect, '/') === 0) {
                header('Location: ' . $redirect);
            } else {
                header('Location: ' . $defaultTarget);
            }
            exit;
        } else {
            $error = $result['error'] ?? 'Login failed. Please try again.';
        }
    }
}

$csrfToken = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['app']['name']) ?> - Login</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .auth-container {
            max-width: 450px;
            margin: 60px auto;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .auth-container h1 {
            margin-bottom: 10px;
            color: #333;
        }
        .auth-container .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        .form-group-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-group-checkbox input {
            margin-right: 8px;
        }
        .form-group-checkbox label {
            margin: 0;
            font-weight: normal;
            color: #333;
        }
        .btn-auth {
            width: 100%;
            padding: 14px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-auth:hover {
            background: #45a049;
        }
        .btn-auth:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .error-msg {
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c33;
        }
        .success-msg {
            background: #efe;
            border: 1px solid #cfc;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            color: #363;
        }
        .info-msg {
            background: #eef6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            color: #1d4ed8;
        }
        .auth-footer {
            margin-top: 20px;
            text-align: center;
            color: #666;
        }
        .auth-footer a {
            color: #4CAF50;
            text-decoration: none;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>🔐 Welcome Back</h1>
        <p class="subtitle">Login to <?= htmlspecialchars($config['app']['name']) ?></p>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($notice)): ?>
            <div class="info-msg"><?= htmlspecialchars($notice) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="success-msg">
                Registration successful! Please login with your credentials.
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($loginPath . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <?php $redirectVal = $_GET['redirect'] ?? $_POST['redirect'] ?? ''; ?>
            <?php if ($redirectVal): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectVal) ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="username_or_email">Username or Email</label>
                <input 
                    type="text" 
                    id="username_or_email" 
                    name="username_or_email" 
                    required
                    autocomplete="username"
                    value="<?= htmlspecialchars($_POST['username_or_email'] ?? '') ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <div class="form-group-checkbox">
                <input 
                    type="checkbox" 
                    id="remember_me" 
                    name="remember_me"
                >
                <label for="remember_me">Remember me for 30 days</label>
            </div>
            
            <button type="submit" class="btn-auth">Login</button>
        </form>
        
        <div class="auth-footer">
            Need access? <a href="request_access.php">Request access</a> or contact your administrator.
        </div>
    </div>
</body>
</html>

