<?php

require_once 'contact_validator.php';
require_once 'csrf_helper.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Location: contact_form.php?status=error');
        exit;
    }

    // Honeypot field: legitimate users leave this empty.
    $hp = trim((string) ($_POST['website_url'] ?? ''));
    if ($hp !== '') {
        header('Location: contact_form.php?status=success');
        exit;
    }

    // Basic per-IP cooldown to reduce spam bursts.
    $clientIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $rateKey = 'crm_contact_' . md5($clientIp);
    $rateFile = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $rateKey . '.txt';
    $cooldownSeconds = 20;
    $now = time();
    if (file_exists($rateFile)) {
        $last = (int) trim((string) @file_get_contents($rateFile));
        if ($last > 0 && ($now - $last) < $cooldownSeconds) {
            header('Location: contact_form.php?status=error');
            exit;
        }
    }

    @file_put_contents($rateFile, (string) $now, LOCK_EX);

    // Collect and sanitize form data
    $data = [];
    foreach ($schema as $field) {
        if ($field === 'id') continue;
        $value = htmlspecialchars(trim($_POST[$field] ?? ''));
        $data[$field] = $value;
    }

    // Validate required fields
    if (empty($data['first_name']) || empty($data['email']) || empty($data['message'])) {
        throw new Exception("Please fill in all required fields.");
    }

    // Save to CSV
    $csvRow = array_values($data);
    $csvRow[] = date('Y-m-d H:i:s');
    $file = fopen('contacts.csv', 'a');
    if ($file) {
        fputcsv($file, $csvRow);
        fclose($file);
    } else {
        throw new Exception("Unable to write to contacts.csv");
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
    header('Location: contact_form.php?status=success');
    exit;

} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    header('Location: contact_form.php?status=error');
    exit;
}
