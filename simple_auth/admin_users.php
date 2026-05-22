<?php
/**
 * Admin User Management
 * Admin-only page to create users, activate/deactivate users, and reset passwords.
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../db_mysql.php';
require_once __DIR__ . '/../audit_handler.php';

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

function adminAudit(string $action, string $summary, string $entityType = 'user', string $entityId = 'n/a', array $changes = []): void {
    @logAuditAction($action, $entityType, $entityId, $changes, $summary, 'success', null);
}

function ensureRequestTable(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS auth_registration_requests (
        id INT NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        company VARCHAR(190) DEFAULT NULL,
        request_note TEXT DEFAULT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        reviewed_by INT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_status_created (status, created_at),
        KEY idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!$auth->verifyCsrfToken($postedToken)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $targetUserId = (int) ($_POST['user_id'] ?? 0);

        try {
            $conn = get_mysql_connection();
            ensureRequestTable($conn);

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
                        $newUserId = (int) $insert->insert_id;
                        $insert->close();
                        $message = 'User created successfully.';
                        adminAudit(
                            'admin_create_user',
                            'Admin created user ' . $username,
                            'user',
                            (string) $newUserId,
                            ['username' => ['old' => null, 'new' => $username], 'email' => ['old' => null, 'new' => $email], 'role' => ['old' => null, 'new' => $role]]
                        );
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
                    adminAudit('admin_toggle_user_active', 'Admin toggled user active state', 'user', (string) $targetUserId);
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
                    adminAudit('admin_reset_password', 'Admin reset a user password', 'user', (string) $targetUserId);
                }
            } elseif ($action === 'review_request') {
                $requestId = (int) ($_POST['request_id'] ?? 0);
                $newStatus = (string) ($_POST['request_status'] ?? 'pending');
                if ($requestId <= 0 || !in_array($newStatus, ['approved', 'rejected'], true)) {
                    $errors[] = 'Invalid registration request action.';
                } else {
                    $reviewedBy = (int) ($_SESSION['user_id'] ?? 0);
                    $review = $conn->prepare('UPDATE auth_registration_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
                    $review->bind_param('sii', $newStatus, $reviewedBy, $requestId);
                    $review->execute();
                    $review->close();
                    $message = 'Registration request updated.';
                    adminAudit('admin_review_registration_request', 'Admin reviewed registration request', 'registration_request', (string) $requestId, ['status' => ['old' => 'pending', 'new' => $newStatus]]);
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
$requests = [];
try {
    $conn = get_mysql_connection();
    ensureRequestTable($conn);
    $result = $conn->query('SELECT id, username, email, role, is_active, is_verified, last_login, created_at FROM users ORDER BY id DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->free();
    }

    $requestResult = $conn->query('SELECT id, full_name, email, company, request_note, status, created_at, reviewed_at FROM auth_registration_requests ORDER BY created_at DESC LIMIT 200');
    if ($requestResult) {
        while ($row = $requestResult->fetch_assoc()) {
            $requests[] = $row;
        }
        $requestResult->free();
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
        body { background: #f6f8fb; font-family: Arial, sans-serif; margin: 0; color: #0f172a; }
        .page-shell { display: flex; min-height: 100vh; }
        .side-nav {
            width: 250px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            padding: 20px 14px;
            box-shadow: 2px 0 12px rgba(15, 23, 42, 0.18);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .side-brand { font-size: 18px; font-weight: 700; margin: 4px 8px 16px; letter-spacing: 0.2px; }
        .side-group { margin-bottom: 14px; }
        .side-title {
            margin: 0 8px 8px;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 700;
        }
        .side-link {
            display: block;
            padding: 10px 12px;
            margin: 2px 0;
            border-radius: 8px;
            color: #e2e8f0;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s ease;
        }
        .side-link:hover { background: rgba(148, 163, 184, 0.18); }
        .side-link.active {
            background: rgba(59, 130, 246, 0.28);
            color: #dbeafe;
            font-weight: 700;
        }
        .content-shell { flex: 1; min-width: 0; }
        .wrap { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
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
        @media (max-width: 980px) {
            .page-shell { flex-direction: column; }
            .side-nav {
                width: 100%;
                height: auto;
                position: static;
                padding: 12px;
                box-shadow: none;
            }
            .side-group { display: flex; flex-wrap: wrap; gap: 6px; }
            .side-title { width: 100%; margin-bottom: 6px; }
            .side-link { margin: 0; }
        }
        @media (max-width: 800px) {
            .grid { grid-template-columns: 1fr; }
            th, td { font-size: 13px; }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <aside class="side-nav" aria-label="Admin navigation">
        <div class="side-brand">Auth Admin</div>

        <div class="side-group">
            <div class="side-title">Management</div>
            <a class="side-link active" href="admin_users.php">User Management</a>
            <a class="side-link" href="request_access.php">Access Requests</a>
            <a class="side-link" href="register.php">Manual Registration</a>
        </div>

        <div class="side-group">
            <div class="side-title">System</div>
            <a class="side-link" href="../admin_dashboard.php">CRM Admin Dashboard</a>
            <a class="side-link" href="../dashboard.php">CRM Dashboard</a>
            <a class="side-link" href="logout.php">Sign Out</a>
        </div>
    </aside>

    <main class="content-shell">
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
        <h2>Registration Requests</h2>
        <p class="help">External users can request access via <code>simple_auth/request_access.php</code>. Review requests here.</p>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Note</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?= (int) $req['id'] ?></td>
                    <td><?= htmlspecialchars((string) $req['full_name']) ?></td>
                    <td><?= htmlspecialchars((string) $req['email']) ?></td>
                    <td><?= htmlspecialchars((string) ($req['company'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($req['request_note'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) $req['status']) ?></td>
                    <td><?= htmlspecialchars((string) $req['created_at']) ?></td>
                    <td>
                        <?php if ($req['status'] === 'pending'): ?>
                        <div class="row-actions">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="review_request">
                                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                <input type="hidden" name="request_status" value="approved">
                                <button type="submit">Approve</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="review_request">
                                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                <input type="hidden" name="request_status" value="rejected">
                                <button type="submit" class="btn-muted">Reject</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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
</main>
</div>
</body>
</html>
