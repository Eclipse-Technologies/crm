<?php
/**
 * Public request access form.
 */

require_once __DIR__ . '/../db_mysql.php';

$authConfig = require __DIR__ . '/config.php';
$adminEmail = trim((string) ($authConfig['app']['admin_email'] ?? ''));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['request_access_csrf'])) {
    $_SESSION['request_access_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$message = '';

try {
    $conn = get_mysql_connection();
    $conn->query("CREATE TABLE IF NOT EXISTS auth_registration_requests (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals((string) $_SESSION['request_access_csrf'], $csrf)) {
            $errors[] = 'Security token mismatch. Please refresh and try again.';
        }

        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $company = trim((string) ($_POST['company'] ?? ''));
        $note = trim((string) ($_POST['request_note'] ?? ''));

        if ($fullName === '' || $email === '') {
            $errors[] = 'Name and email are required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }

        if (empty($errors)) {
            $check = $conn->prepare("SELECT id, status FROM auth_registration_requests WHERE email = ? AND status = 'pending' LIMIT 1");
            $check->bind_param('s', $email);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existing) {
                $message = 'A request is already pending for this email.';
            } else {
                $insert = $conn->prepare('INSERT INTO auth_registration_requests (full_name, email, company, request_note, status) VALUES (?, ?, ?, ?, "pending")');
                $insert->bind_param('ssss', $fullName, $email, $company, $note);
                $insert->execute();
                $requestId = (int) $insert->insert_id;
                $insert->close();
                $message = 'Request submitted. An administrator will contact you.';

                // Best-effort notification for faster admin review.
                if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    $subject = 'New CRM Access Request #' . $requestId;
                    $body = "A new CRM access request was submitted.\n\n"
                        . "Request ID: " . $requestId . "\n"
                        . "Name: " . $fullName . "\n"
                        . "Email: " . $email . "\n"
                        . "Company: " . ($company !== '' ? $company : 'n/a') . "\n"
                        . "Note: " . ($note !== '' ? $note : 'n/a') . "\n\n"
                        . "Review in admin: simple_auth/admin_users.php\n";
                    @mail($adminEmail, $subject, $body);
                }
            }
        }
    }

    $conn->close();
} catch (Throwable $e) {
    $errors[] = 'Unable to process request right now.';
    error_log('request_access.php error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Access</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background: #f8fafc; font-family: Arial, sans-serif; margin: 0; color: #0f172a; }
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
        .panel { max-width: 540px; margin: 40px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; }
        input, textarea, button { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; margin-top: 8px; }
        button { background: #0f766e; color: #fff; border: none; cursor: pointer; }
        .msg { background: #ecfeff; border: 1px solid #a5f3fc; color: #155e75; border-radius: 6px; padding: 10px; margin-bottom: 8px; }
        .err { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; border-radius: 6px; padding: 10px; margin-bottom: 8px; }
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
            .panel { margin: 18px auto; }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <aside class="side-nav" aria-label="Authentication navigation">
        <div class="side-brand">Auth Access</div>

        <div class="side-group">
            <div class="side-title">Access</div>
            <a class="side-link" href="login.php">Login</a>
            <a class="side-link active" href="request_access.php">Request Access</a>
            <a class="side-link" href="register.php">Register</a>
        </div>

        <div class="side-group">
            <div class="side-title">CRM</div>
            <a class="side-link" href="../dashboard.php">CRM Dashboard</a>
            <a class="side-link" href="../admin_dashboard.php">Admin Dashboard</a>
        </div>
    </aside>

    <main class="content-shell">
<div class="panel">
    <h1>Request CRM Access</h1>
    <p>Public registration is disabled. Submit this form and an administrator will review your request.</p>

    <?php if ($message !== ''): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['request_access_csrf']) ?>">
        <label>Full Name</label>
        <input type="text" name="full_name" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Company (optional)</label>
        <input type="text" name="company">

        <label>Reason for access (optional)</label>
        <textarea name="request_note" rows="4"></textarea>

        <button type="submit">Submit Request</button>
    </form>

    <p style="margin-top:12px;"><a href="login.php">Back to login</a></p>
</div>
</main>
</div>
</body>
</html>
