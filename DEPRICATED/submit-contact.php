<?php

require_once 'contact_validator.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer.php';
require 'SMTP.php';
require 'Exception.php';

$schema = require __DIR__ . '/contact_schema.php';
$mail = new PHPMailer(true);

try {
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
