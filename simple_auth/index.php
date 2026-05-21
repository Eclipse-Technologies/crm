<?php
require_once __DIR__ . '/middleware.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM - Login</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .auth-container {
            max-width: 550px;
            margin: 80px auto;
            padding: 50px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        .auth-container h1 {
            margin-bottom: 12px;
            color: #1e1b1b;
            font-size: 2.2rem;
            letter-spacing: -0.02em;
        }
        .auth-container .subtitle {
            color: #6b6b6b;
            margin-bottom: 40px;
            font-size: 1.05rem;
            line-height: 1.6;
        }
        .auth-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .auth-btn {
            flex: 1;
            min-width: 200px;
            padding: 16px 28px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 2px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .auth-btn-primary {
            background: #1f7a4f;
            color: white;
        }
        .auth-btn-primary:hover {
            background: #175c3a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(31, 122, 79, 0.3);
        }
        .auth-btn-secondary {
            background: transparent;
            color: #1f7a4f;
            border-color: #1f7a4f;
        }
        .auth-btn-secondary:hover {
            background: #1f7a4f;
            color: white;
            transform: translateY(-2px);
        }
        .logged-in-content {
            text-align: center;
        }
        .welcome-icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
        }
        .logged-in-content a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #1f7a4f;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .logged-in-content a:hover {
            background: #175c3a;
            transform: translateY(-2px);
        }
        .divider {
            margin: 30px 0;
            text-align: center;
            color: #ccc;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="body-hub" style="background: radial-gradient(circle at 20% 20%, rgba(244, 182, 109, 0.25), transparent 45%), radial-gradient(circle at 80% 0%, rgba(31, 122, 79, 0.2), transparent 40%), linear-gradient(140deg, #f7f4ee 0%, #eef4ff 45%, #f5f9f1 100%); min-height: 100vh;">
    
    <div class="auth-container">
        <?php if (auth_check()): ?>
            <!-- Already logged in -->
            <div class="logged-in-content">
                <div class="welcome-icon">💪</div>
                <h1>Welcome back!</h1>
                <p class="subtitle">
                    You're logged in as <strong><?= htmlspecialchars(auth_current_user()['username']) ?></strong>
                </p>
                <a href="../index.php">Go to CRM →</a>
            </div>
        <?php else: ?>
            <!-- Not logged in -->
            <h1>CRM System</h1>
            <p class="subtitle">
                Manage customer relationships, track sales, and grow your business.
            </p>
            
            <div class="auth-buttons">
                <a href="login.php" class="auth-btn auth-btn-secondary">Sign In</a>
            </div>
            
            <div class="divider">
                or return to <a href="../" style="color: #1f7a4f; text-decoration: none; font-weight: 600;">home</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
