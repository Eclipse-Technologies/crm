<?php
require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/daily_call_list_helper.php';
require_once __DIR__ . '/env_loader.php';

load_env();

$isCli = (PHP_SAPI === 'cli');
$recipient = '';

if ($isCli) {
    global $argv;
    $recipient = trim((string) ($argv[1] ?? ''));
} else {
    require_once __DIR__ . '/simple_auth/middleware.php';
    require_once __DIR__ . '/csrf_helper.php';

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF validation failed');
    }

    $recipient = trim((string) ($_POST['email_to'] ?? ''));
}

if ($recipient === '') {
    $recipient = trim((string) getenv('DAILY_CALL_EMAIL_TO'));
}

if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    $msg = 'Recipient email is missing/invalid. Set DAILY_CALL_EMAIL_TO in .env or pass an email argument.';
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
    } else {
        http_response_code(400);
        echo $msg;
    }
    exit(1);
}

$conn = get_mysql_connection();
ensure_daily_call_tracking_table($conn);
$candidates = fetch_daily_ontario_call_candidates($conn, 10);

if (empty($candidates)) {
    $conn->close();
    $msg = 'No eligible contacts for today.';
    if ($isCli) {
        fwrite(STDOUT, $msg . PHP_EOL);
    } else {
        echo $msg;
    }
    exit(0);
}

$sendResult = send_daily_call_email($recipient, $candidates);
if (empty($sendResult['ok'])) {
    $conn->close();
    $msg = (string) ($sendResult['error'] ?? 'Email send failed.');
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
    } else {
        http_response_code(500);
        echo $msg;
    }
    exit(1);
}

$ids = array_map(static function ($row) {
    return (string) ($row['contact_id'] ?? '');
}, $candidates);
mark_daily_call_contacts_sent($conn, $ids);
$conn->close();

$okMsg = 'Sent daily call list to ' . $recipient . ' with ' . count($candidates) . ' contacts.';
if ($isCli) {
    fwrite(STDOUT, $okMsg . PHP_EOL);
} else {
    echo $okMsg;
}

exit(0);
