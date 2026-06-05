<?php

require_once 'contact_validator.php';
require_once 'csrf_helper.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Prefer Composer autoload when available; otherwise fallback to bundled vendor paths.
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

if (!class_exists(PHPMailer::class)) {
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
}

$schema = require __DIR__ . '/contact_schema.php';
$mail = new PHPMailer(true);

function contactRedirectWithReason(string $status, string $reason = ''): void {
    $url = 'contact_form.php?status=' . rawurlencode($status);
    if ($reason !== '') {
        $url .= '&reason=' . rawurlencode($reason);
    }
    header('Location: ' . $url);
    exit;
}

function contactLogAndFail(string $reason, string $detail = ''): void {
    $message = 'Contact form [' . $reason . ']';
    if ($detail !== '') {
        $message .= ': ' . $detail;
    }
    error_log($message);
    contactRedirectWithReason('error', $reason);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        contactLogAndFail('csrf', 'CSRF token validation failed');
    }

    // Honeypot field: legitimate users leave this empty.
    $hp = trim((string) ($_POST['website_url'] ?? ''));
    if ($hp !== '') {
        contactRedirectWithReason('success', 'honeypot');
    }

    // Basic per-IP cooldown to reduce spam bursts.
    $clientIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $rateKey = 'crm_contact_' . md5($clientIp);
    $rateFile = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $rateKey . '.txt';
    $sessionRateKey = 'crm_contact_last_submit_ts';
    $cookieRateKey = 'crm_contact_last_submit_ts';
    $cooldownSeconds = 20;
    $now = time();

    $cookieLast = (int) ($_COOKIE[$cookieRateKey] ?? 0);
    if ($cookieLast > 0 && ($now - $cookieLast) < $cooldownSeconds) {
        contactLogAndFail('cooldown', 'Submission blocked by cookie cooldown window');
    }

    $sessionLast = (int) ($_SESSION[$sessionRateKey] ?? 0);
    if ($sessionLast > 0 && ($now - $sessionLast) < $cooldownSeconds) {
        contactLogAndFail('cooldown', 'Submission blocked by session cooldown window');
    }

    if (file_exists($rateFile)) {
        $last = (int) trim((string) @file_get_contents($rateFile));
        if ($last > 0 && ($now - $last) < $cooldownSeconds) {
            contactLogAndFail('cooldown', 'Submission blocked by cooldown window');
        }
    }

    $_SESSION[$sessionRateKey] = $now;
    setcookie($cookieRateKey, (string) $now, [
        'expires' => $now + $cooldownSeconds,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @file_put_contents($rateFile, (string) $now, LOCK_EX);

    // Collect and sanitize form data
    $data = [];
    foreach ($schema as $field) {
        if ($field === 'id') continue;
        $value = htmlspecialchars(trim($_POST[$field] ?? ''));
        $data[$field] = $value;
    }

    // Public form includes a free-text message field not present in contact schema.
    $data['message'] = htmlspecialchars(trim((string) ($_POST['message'] ?? '')));

    // Validate required fields
    if (empty($data['first_name']) || empty($data['email']) || empty($data['message'])) {
        contactLogAndFail('validation', 'Missing one or more required fields');
    }

    // Save to CSV
    $csvRow = array_values($data);
    $csvRow[] = date('Y-m-d H:i:s');
    $file = fopen('contacts.csv', 'a');
    if ($file) {
        fputcsv($file, $csvRow);
        fclose($file);
    } else {
        contactLogAndFail('csv_write', 'Unable to write to contacts.csv');
    }

    // GoDaddy relay SMTP settings
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = 'relay-hosting.secureserver.net';
    $mail->SMTPAuth = false;
    $mail->SMTPSecure = '';
    $mail->Port = 25;

    // Email headers
    $mail->setFrom('rlee@eclipsewatertechnologies.com', 'Eclipse Contact Form');
    $mail->addAddress('rlee@eclipsewatertechnologies.com');
    $mail->addReplyTo($data['email'], $data['first_name']);

    // Email content
    $mail->Subject = 'New Contact Form Submission';
    $mail->Body = "Name: {$data['first_name']}\nEmail: {$data['email']}\nCompany: {$data['company']}\nMessage:\n{$data['message']}";

    $mail->send();

    // Redirect with success status
    contactRedirectWithReason('success');

} catch (PHPMailerException $e) {
    contactLogAndFail('smtp_send', $e->getMessage());
} catch (Throwable $e) {
    contactLogAndFail('submit_failed', $e->getMessage());
}
