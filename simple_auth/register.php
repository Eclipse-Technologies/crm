<?php
/**
 * User Registration Page
 * All PHP logic is before any output
 */
require_once __DIR__ . '/Auth.php';

// Load config
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die('Configuration file not found. Please create auth/config.php from auth/config.example.php or run setup.php first.');
}
$config = require $configFile;

$auth = new Auth($config);
$errors = [];
$success = false;

// Track CSRF retry attempts (for development)
$csrfRetryKey = 'csrf_retry_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Force CSRF dev bypass for local testing
    $devBypass = true;
    if (!$devBypass && !$auth->verifyCsrfToken($csrfToken)) {
        // Always allow retry for now (testing mode)
        $auth->generateCsrfToken();
        $errors[] = 'Security token expired or invalid. Please try submitting again.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    } else {
        // Reset retry counter on successful CSRF validation
        $_SESSION[$csrfRetryKey] = 0;
        $result = $auth->register($username, $email, $password);
        if ($result['success']) {
            $success = true;
            if ($result['requires_verification']) {
                $successMessage = 'Registration successful! Please check your email to verify your account.';
            } else {
                $successMessage = 'Registration successful! You can now <a href="login.php">login</a>.';
            }
        } else {
            $errors = $result['errors'] ?? ['Registration failed'];
        }
    }
}
// Generate CSRF token if needed
if (!$success && !isset($_SESSION['csrf_token'])) {
    $auth->generateCsrfToken();
}

// Always define $csrfToken for use in debug/info sections
$csrfToken = $_SESSION['csrf_token'] ?? '';
// Development debug info (only on localhost)
$showDebug = ($_SERVER['SERVER_NAME'] ?? '') === 'localhost' || ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1';
// ...existing code...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CRM System</title>
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
        .form-group input {
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
        .error-list {
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .error-list ul {
            margin: 0;
            padding-left: 20px;
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
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
<<<<<<< HEAD
        <h1>Create Account</h1>
        <p class="subtitle">Register to access the CRM and manage your business</p>
=======
        <h1>🔐 Create Account</h1>
        <p class="subtitle">Join <?= htmlspecialchars($config['app']['name']) ?> today</p>
        
        <?php if ($success): ?>
            <div class="success-msg">
                <?= $successMessage ?>
            </div>
        <?php endif; ?>
>>>>>>> c34eaea0973d4ee29e8620be5643dba9eaaa18b7
        
        <?php if ($showDebug): ?>
            <div style="background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; font-size: 11px; font-family: monospace;">
                <strong>Debug Info (localhost only):</strong><br>
                Session ID: <?= htmlspecialchars(session_id()) ?><br>
                CSRF Token: <?= htmlspecialchars(substr($csrfToken, 0, 16)) ?>...<br>
                Cookie Received: <?= isset($_COOKIE[session_name()]) ? '✅ Yes (session maintained)' : '⚠️ Not yet (first load is normal)' ?><br>
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <strong style="color: #c33;">POST Request - Cookie MUST be present for CSRF to work!</strong><br>
                <?php endif; ?>
                <?php if ($config['security']['dev_bypass_csrf'] ?? false): ?>
                    <div style="background: #fff3cd; color: #856404; padding: 8px; margin-top: 5px; border: 1px solid #ffc107; border-radius: 3px;">
                        ⚠️ <strong>DEV MODE:</strong> CSRF bypass is ENABLED for localhost testing
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>        
        <?php if (!empty($errors)): ?>
            <div class="error-list">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        minlength="<?= $config['validation']['username_min_length'] ?>"
                        maxlength="<?= $config['validation']['username_max_length'] ?>"
                        pattern="[a-zA-Z0-9_]+"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        minlength="<?= $config['validation']['password_min_length'] ?>"
                    >
                    <div class="password-requirements">
                        Must be at least <?= $config['validation']['password_min_length'] ?> characters
                        <?php if ($config['validation']['password_require_uppercase']): ?>, include uppercase<?php endif; ?>
                        <?php if ($config['validation']['password_require_lowercase']): ?>, lowercase<?php endif; ?>
                        <?php if ($config['validation']['password_require_number']): ?>, number<?php endif; ?>
                        <?php if ($config['validation']['password_require_special']): ?>, and special character<?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required
                    >
                </div>
                
                <button type="submit" class="btn-auth">Create Account</button>
            </form>
        <?php endif; ?>
        
        <div class="auth-footer">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>
