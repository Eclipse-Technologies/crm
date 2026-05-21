<?php
/**
 * Admin User Management
 * Admin-only page to create users, activate/deactivate users, and reset passwords.
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../db_mysql.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Forbidden: admin access required.';
    exit;
}

$auth = auth();
if (!$auth) {
    http_response_code(500);
    echo 'Authentication subsystem unavailable.';
    exit;
}

$config = require __DIR__ . '/config.php';
$csrfToken = $auth->generateCsrfToken();
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!$auth->verifyCsrfToken($postedToken)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $targetUserId = (int) ($_POST['user_id'] ?? 0);

        try {
            $conn = get_mysql_connection();

            if ($action === 'create_user') {
                $username = trim((string) ($_POST['username'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $role = (string) ($_POST['role'] ?? 'user');
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if ($username === '' || $email === '' || $password === '') {
                    $errors[] = 'Username, email, and password are required.';
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Email format is invalid.';
                }
                if (!in_array($role, ['admin', 'user'], true)) {
                    $errors[] = 'Role is invalid.';
                }

                if (empty($errors)) {
                    $check = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
                    $check->bind_param('ss', $username, $email);
                    $check->execute();
                    $exists = $check->get_result()->fetch_assoc();
                    $check->close();

                    if ($exists) {
                        $errors[] = 'A user with this username or email already exists.';
                    } else {
                        $hash = password_hash(
                            $password,
                            $config['security']['password_algo'],
                            $config['security']['password_options']
                        );
                        $isVerified = 1;
                        $verificationToken = null;
                        $resetToken = '';
                        $resetTokenExpires = null;
                        $failedAttempts = 0;
                        $lockedUntil = null;
                        $lastLogin = null;

                        $insert = $conn->prepare(
                            'INSERT INTO users (username, email, password_hash, role, is_verified, is_active, verification_token, reset_token, reset_token_expires, failed_login_attempts, locked_until, last_login) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $insert->bind_param(
                            'ssssiiississ',
                            $username,
                            $email,
                            $hash,
                            $role,
                            $isVerified,
                            $isActive,
                            $verificationToken,
                            $resetToken,
                            $resetTokenExpires,
                            $failedAttempts,
                            $lockedUntil,
                            $lastLogin
                        );
                        $insert->execute();
                        $insert->close();
                        $message = 'User created successfully.';
                    }
                }
            } elseif ($action === 'toggle_active' && $targetUserId > 0) {
                if ($targetUserId === (int) ($_SESSION['user_id'] ?? 0)) {
                    $errors[] = 'You cannot deactivate your own account.';
                } else {
                    $toggle = $conn->prepare('UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
                    $toggle->bind_param('i', $targetUserId);
                    $toggle->execute();
                    $toggle->close();
                    $message = 'User status updated.';
                }
            } elseif ($action === 'reset_password' && $targetUserId > 0) {
                $newPassword = (string) ($_POST['new_password'] ?? '');
                if (strlen($newPassword) < (int) ($config['validation']['password_min_length'] ?? 8)) {
                    $errors[] = 'New password is too short.';
                } else {
                    $newHash = password_hash(
                        $newPassword,
                        $config['security']['password_algo'],
                        $config['security']['password_options']
                    );
                    $reset = $conn->prepare('UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
                    $reset->bind_param('si', $newHash, $targetUserId);
                    $reset->execute();
                    $reset->close();
                    $message = 'Password reset successfully.';
                }
            }

            $conn->close();
        } catch (Throwable $e) {
            $errors[] = 'Action failed. Check server logs for details.';
            error_log('admin_users.php error: ' . $e->getMessage());
        }
    }
}

$users = [];
try {
    $conn = get_mysql_connection();
    $result = $conn->query('SELECT id, username, email, role, is_active, is_verified, last_login, created_at FROM users ORDER BY id DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->free();
    }
    $conn->close();
} catch (Throwable $e) {
    $errors[] = 'Unable to load users.';
    error_log('admin_users.php list error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Administration</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background: #f6f8fb; font-family: Arial, sans-serif; margin: 0; }
        .wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #dde3ea; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        h1 { margin: 0 0 12px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #eef2f6; padding: 10px; text-align: left; font-size: 14px; }
        th { background: #f8fafc; }
        .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        input, select, button { padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; }
        button { cursor: pointer; background: #14532d; color: #fff; border: none; }
        .btn-muted { background: #475569; }
        .notice { background: #ecfeff; border: 1px solid #a5f3fc; color: #155e75; border-radius: 6px; padding: 10px; }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; border-radius: 6px; padding: 10px; margin-bottom: 8px; }
        .help { color: #475569; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 8px; }
        @media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>User Administration</h1>
        <p class="help">Public self-registration is disabled by default. Create and manage user access here.</p>
        <?php if ($message !== ''): ?><div class="notice"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php foreach ($errors as $error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
    </div>

    <div class="card">
        <h2>Create User</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_user">
            <div class="grid">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Temporary password" required>
                <select name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <label style="display:block; margin-top:8px;"><input type="checkbox" name="is_active" checked> Active account</label>
            <button type="submit" style="margin-top:10px;">Create User</button>
        </form>
    </div>

    <div class="card">
        <h2>Existing Users</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Active</th>
                    <th>Verified</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int) $user['id'] ?></td>
                    <td><?= htmlspecialchars((string) $user['username']) ?></td>
                    <td><?= htmlspecialchars((string) $user['email']) ?></td>
                    <td><?= htmlspecialchars((string) $user['role']) ?></td>
                    <td><?= ((int) $user['is_active'] === 1) ? 'Yes' : 'No' ?></td>
                    <td><?= ((int) $user['is_verified'] === 1) ? 'Yes' : 'No' ?></td>
                    <td><?= htmlspecialchars((string) ($user['last_login'] ?? '')) ?></td>
                    <td>
                        <div class="row-actions">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <button type="submit" class="btn-muted"><?= ((int) $user['is_active'] === 1) ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <input type="password" name="new_password" placeholder="New password" required>
                                <button type="submit">Reset Password</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
