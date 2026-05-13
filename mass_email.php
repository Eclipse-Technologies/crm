<?php
$pageTitle = 'Mass Email';
require_once __DIR__ . '/layout_start.php';
require_once __DIR__ . '/db_mysql.php';

$phpMailerSrc = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
if (file_exists($phpMailerSrc . 'Exception.php')) {
  require_once $phpMailerSrc . 'Exception.php';
  require_once $phpMailerSrc . 'PHPMailer.php';
  require_once $phpMailerSrc . 'SMTP.php';
}

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function env_flag_enabled(string $value): bool {
  return !in_array(strtolower(trim($value)), ['0', 'false', 'no', 'off', ''], true);
}

function smtp_endpoint_reachable(string $host, int $port, int $timeoutSeconds = 5): bool {
  $errno = 0;
  $errstr = '';
  $socket = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
  if ($socket === false) {
    return false;
  }
  fclose($socket);
  return true;
}

function http_post_form(string $url, array $fields, int $timeoutSeconds = 15): array {
  $body = http_build_query($fields);
  $headers = [
    'Content-Type: application/x-www-form-urlencoded',
    'Content-Length: ' . strlen($body),
  ];

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $body,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => $timeoutSeconds,
      CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $responseBody = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $responseBody === false ? '' : $responseBody, 'error' => $error];
  }

  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => implode("\r\n", $headers),
      'content' => $body,
      'timeout' => $timeoutSeconds,
      'ignore_errors' => true,
    ],
  ]);
  $responseBody = @file_get_contents($url, false, $context);
  $status = 0;
  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
    $status = (int) $matches[1];
  }
  return ['status' => $status, 'body' => $responseBody === false ? '' : $responseBody, 'error' => $responseBody === false ? 'HTTP request failed.' : ''];
}

function http_post_json(string $url, array $payload, array $headers, int $timeoutSeconds = 15): array {
  $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
  $headers[] = 'Content-Type: application/json';
  $headers[] = 'Content-Length: ' . strlen((string) $body);

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $body,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => $timeoutSeconds,
      CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $responseBody = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $responseBody === false ? '' : $responseBody, 'error' => $error];
  }

  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => implode("\r\n", $headers),
      'content' => $body,
      'timeout' => $timeoutSeconds,
      'ignore_errors' => true,
    ],
  ]);
  $responseBody = @file_get_contents($url, false, $context);
  $status = 0;
  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
    $status = (int) $matches[1];
  }
  return ['status' => $status, 'body' => $responseBody === false ? '' : $responseBody, 'error' => $responseBody === false ? 'HTTP request failed.' : ''];
}

function graph_get_access_token(string $tenantId, string $clientId, string $clientSecret): array {
  $tokenUrl = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';
  $response = http_post_form($tokenUrl, [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'grant_type' => 'client_credentials',
    'scope' => 'https://graph.microsoft.com/.default',
  ]);

  if ($response['error'] !== '') {
    return ['token' => '', 'error' => $response['error']];
  }

  $decoded = json_decode($response['body'], true);
  if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
    $message = is_array($decoded) ? ($decoded['error_description'] ?? ($decoded['error']['message'] ?? 'Failed to get Graph access token.')) : 'Failed to get Graph access token.';
    return ['token' => '', 'error' => $message];
  }

  return ['token' => $decoded['access_token'], 'error' => ''];
}

function graph_send_mail(string $accessToken, string $sender, string $recipient, string $subject, string $body): string {
  $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($sender) . '/sendMail';
  $payload = [
    'message' => [
      'subject' => $subject,
      'body' => [
        'contentType' => 'Text',
        'content' => $body,
      ],
      'toRecipients' => [[
        'emailAddress' => ['address' => $recipient],
      ]],
    ],
    'saveToSentItems' => true,
  ];

  $response = http_post_json($url, $payload, ['Authorization: Bearer ' . $accessToken]);
  if ($response['error'] !== '') {
    return $response['error'];
  }

  if ($response['status'] < 200 || $response['status'] >= 300) {
    $decoded = json_decode($response['body'], true);
    if (is_array($decoded)) {
      return $decoded['error']['message'] ?? 'Graph sendMail request failed.';
    }
    return 'Graph sendMail request failed.';
  }

  return '';
}

$conn = get_mysql_connection();
$currentUser = auth_current_user();
$authorName = $currentUser['username'] ?? 'System';

$errors = [];
$successMessage = '';
$failedRecipients = [];
$smtpFailureDetail = '';

$subject = trim($_POST['subject'] ?? 'SDI Service Cost Review (GTA Regeneration, No Fuel Surcharges)');
$messageTemplate = trim($_POST['message'] ?? "Hi {{first_name}},\n\nI'm reaching out regarding Service Deionization (SDI) program costs in Ontario.\n\nMany facilities are currently reviewing SDI service pricing due to:\n1. Tariff-related cost pressure on parts and materials\n2. Added transport and logistics costs\n3. Fuel surcharges applied by some providers\n\nFor reference, our SDI model is structured as follows:\n1. Regeneration is performed in the GTA\n2. No fuel surcharges are applied\n3. Pricing is provided in a transparent format for easier cost tracking\n\nIf useful, we can provide a side-by-side SDI cost comparison based on your current service structure and exchange frequency.\n\nBest regards,\nRobert Lee\nEclipse Water Technologies\nrlee@eclipsewatertechnologies.com\n647-355-0944");
$discussionTemplate = trim($_POST['discussion_message'] ?? 'Sent SDI cost review email. Subject: {{subject}}');
$fromName = trim($_POST['from_name'] ?? ($authorName ?: 'CRM Team'));
$fromEmail = trim($_POST['from_email'] ?? '');
$selectedIds = $_POST['contact_ids'] ?? [];
$submitAction = $_POST['action'] ?? 'send_mass';
$isSmtpTest = ($submitAction === 'test_smtp');

$mailTransport = strtolower(trim((string) (getenv('MAIL_TRANSPORT') ?: 'smtp')));

$smtpHost = trim((string) getenv('SMTP_HOST'));
$smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
$smtpAuthEnabled = env_flag_enabled((string) (getenv('SMTP_AUTH') ?: 'true'));
$smtpUsername = trim((string) getenv('SMTP_USERNAME'));
$smtpPassword = trim((string) getenv('SMTP_PASSWORD'));
$smtpFromEmail = trim((string) getenv('SMTP_FROM_EMAIL'));
$smtpEncryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')));

$graphTenantId = trim((string) getenv('GRAPH_TENANT_ID'));
$graphClientId = trim((string) getenv('GRAPH_CLIENT_ID'));
$graphClientSecret = trim((string) getenv('GRAPH_CLIENT_SECRET'));
$graphSender = trim((string) getenv('GRAPH_SENDER'));

$isGraphTransport = ($mailTransport === 'graph');

if ($fromEmail === '' && $isGraphTransport && $graphSender !== '' && filter_var($graphSender, FILTER_VALIDATE_EMAIL)) {
  $fromEmail = $graphSender;
}

if ($fromEmail === '' && $smtpFromEmail !== '' && filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL)) {
  $fromEmail = $smtpFromEmail;
}

if ($fromEmail === '' && !$isGraphTransport && $smtpUsername !== '' && filter_var($smtpUsername, FILTER_VALIDATE_EMAIL)) {
  $fromEmail = $smtpUsername;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Avoid hard-failing at the default 30s limit during SMTP/network operations.
  @set_time_limit(120);
  @ini_set('max_execution_time', '120');
  @ini_set('default_socket_timeout', '10');

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    }

    if ($subject === '') {
        $errors[] = 'Subject is required.';
    }

    if ($messageTemplate === '') {
        $errors[] = 'Message is required.';
    }

    if (!$isSmtpTest) {
      if (!is_array($selectedIds) || count($selectedIds) === 0) {
        $errors[] = 'Select at least one contact.';
      }

      $selectedIds = array_values(array_filter(array_map('intval', (array) $selectedIds), function ($id) {
        return $id > 0;
      }));

      if (count($selectedIds) === 0) {
        $errors[] = 'No valid contact IDs were selected.';
      }
    } else {
      $selectedIds = [];
    }

    if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'From Email is not valid.';
    }

    if ($isGraphTransport) {
      if ($graphTenantId === '' || $graphClientId === '' || $graphClientSecret === '' || $graphSender === '') {
        $errors[] = 'Graph transport is enabled. Set GRAPH_TENANT_ID, GRAPH_CLIENT_ID, GRAPH_CLIENT_SECRET, and GRAPH_SENDER in .env.';
      }
      if ($fromEmail === '') {
        $errors[] = 'From Email is required when using Graph.';
      }
    } else {
      if ($smtpHost === '') {
        $errors[] = 'SMTP is not configured. Set SMTP_HOST in .env.';
      }

      if ($smtpAuthEnabled && ($smtpUsername === '' || $smtpPassword === '')) {
        $errors[] = 'SMTP auth is enabled. Set SMTP_USERNAME and SMTP_PASSWORD in .env.';
      }

      if ($smtpHost !== '' && $smtpPort > 0 && !smtp_endpoint_reachable($smtpHost, $smtpPort, 5)) {
        $errors[] = 'SMTP server is not reachable right now (' . $smtpHost . ':' . $smtpPort . ').';
      }

      if (!class_exists(PHPMailer::class)) {
        $errors[] = 'PHPMailer is missing. Ensure vendor/phpmailer/phpmailer is present.';
      }

      if (!in_array($smtpEncryption, ['tls', 'ssl', 'starttls', 'none', ''], true)) {
        $errors[] = 'SMTP_ENCRYPTION must be tls, ssl, starttls, or none.';
      }

      if ($fromEmail === '') {
        $errors[] = 'From Email is required when using SMTP. Set it in the form or set SMTP_FROM_EMAIL in .env.';
      }
    }

    if (empty($errors)) {
      if ($isSmtpTest) {
        $testRecipient = 'robertja98@gmail.com';
        if ($isGraphTransport) {
          $tokenResult = graph_get_access_token($graphTenantId, $graphClientId, $graphClientSecret);
          if ($tokenResult['error'] !== '') {
            $errors[] = 'Graph test failed: ' . $tokenResult['error'];
          } else {
            $graphError = graph_send_mail($tokenResult['token'], $graphSender, $testRecipient, '[Graph Test] ' . $subject, $messageTemplate);
            if ($graphError !== '') {
              $errors[] = 'Graph test failed: ' . $graphError;
            } else {
              $successMessage = 'Graph test email sent successfully to ' . $testRecipient . '.';
            }
          }
        } else {
          $mailer = new PHPMailer(true);
          try {
            $mailer->isSMTP();
            $mailer->Host = $smtpHost;
            $mailer->SMTPAuth = $smtpAuthEnabled;
            if ($smtpAuthEnabled) {
              $mailer->Username = $smtpUsername;
              $mailer->Password = $smtpPassword;
            }
            $mailer->Port = $smtpPort;
            $mailer->Timeout = 10;
            $mailer->Timelimit = 20;

            if ($smtpEncryption === 'ssl') {
              $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtpEncryption === 'tls' || $smtpEncryption === 'starttls') {
              $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
              $mailer->SMTPSecure = '';
              $mailer->SMTPAutoTLS = false;
            }

            $mailer->CharSet = 'UTF-8';
            $mailer->isHTML(false);
            $mailer->setFrom($fromEmail, $fromName !== '' ? $fromName : 'CRM Team');
            $mailer->Subject = '[SMTP Test] ' . $subject;
            $mailer->Body = $messageTemplate;
            $mailer->addAddress($testRecipient);
            $mailer->send();
            $successMessage = 'SMTP test email sent successfully to ' . $testRecipient . '.';
          } catch (Exception $e) {
            $smtpFailureDetail = trim((string) $mailer->ErrorInfo);
            $errors[] = 'SMTP test failed: ' . ($smtpFailureDetail !== '' ? $smtpFailureDetail : $e->getMessage());
          } finally {
            $mailer->smtpClose();
          }
        }
      } else {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $types = str_repeat('i', count($selectedIds));
        $sql = "SELECT contact_id, first_name, last_name, company, email FROM contacts WHERE contact_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errors[] = 'Failed to prepare contact query.';
        } else {
            $stmt->bind_param($types, ...$selectedIds);
            $stmt->execute();
            $result = $stmt->get_result();

            $contacts = [];
            while ($row = $result->fetch_assoc()) {
                $contacts[] = $row;
            }
            $stmt->close();

            if (empty($contacts)) {
                $errors[] = 'No matching contacts found.';
            } else {
                $discussionSql = "INSERT INTO discussion_log (contact_id, author, timestamp, entry_text, linked_opportunity_id, visibility) VALUES (?, ?, NOW(), ?, '', 'public')";
                $discussionStmt = $conn->prepare($discussionSql);

                if (!$discussionStmt) {
                    $errors[] = 'Failed to prepare discussion log statement.';
                } else {
                    $sentCount = 0;

                    if ($isGraphTransport) {
                      $tokenResult = graph_get_access_token($graphTenantId, $graphClientId, $graphClientSecret);
                      if ($tokenResult['error'] !== '') {
                        $discussionStmt->close();
                        $errors[] = 'Graph setup failed: ' . $tokenResult['error'];
                      } else {
                        foreach ($contacts as $contact) {
                          $to = trim((string) ($contact['email'] ?? ''));
                          if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                            $failedRecipients[] = ($contact['company'] ?: 'Unknown company') . ' - missing/invalid email';
                            continue;
                          }

                          $tokenValues = [
                            '{{first_name}}' => (string) ($contact['first_name'] ?? ''),
                            '{{last_name}}' => (string) ($contact['last_name'] ?? ''),
                            '{{company}}' => (string) ($contact['company'] ?? ''),
                            '{{email}}' => $to,
                            '{{subject}}' => $subject,
                          ];

                          $body = strtr($messageTemplate, $tokenValues);
                          $discussionMessage = strtr($discussionTemplate, $tokenValues);
                          $graphError = graph_send_mail($tokenResult['token'], $graphSender, $to, $subject, $body);
                          if ($graphError !== '') {
                            $failedRecipients[] = ($contact['company'] ?: 'Unknown company') . ' - ' . $to . ' Reason: ' . $graphError;
                            continue;
                          }

                          $contactId = (string) $contact['contact_id'];
                          $discussionStmt->bind_param('sss', $contactId, $authorName, $discussionMessage);
                          $discussionStmt->execute();
                          $sentCount++;
                        }

                        $discussionStmt->close();
                        $successMessage = 'Mass email complete. Sent: ' . $sentCount . '. Failed: ' . count($failedRecipients) . '.';
                      }
                    } else {
                      $mailer = new PHPMailer(true);

                      try {
                        $mailer->isSMTP();
                        $mailer->Host = $smtpHost;
                        $mailer->SMTPAuth = $smtpAuthEnabled;
                        if ($smtpAuthEnabled) {
                          $mailer->Username = $smtpUsername;
                          $mailer->Password = $smtpPassword;
                        }
                        $mailer->Port = $smtpPort;
                        // Fail fast on network/connect issues instead of hitting PHP max_execution_time.
                        $mailer->Timeout = 10;
                        $mailer->Timelimit = 20;
                        $mailer->SMTPKeepAlive = true;

                        if ($smtpEncryption === 'ssl') {
                          $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        } elseif ($smtpEncryption === 'tls' || $smtpEncryption === 'starttls') {
                          $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        } else {
                          $mailer->SMTPSecure = '';
                          $mailer->SMTPAutoTLS = false;
                        }

                        $mailer->CharSet = 'UTF-8';
                        $mailer->isHTML(false);
                        $mailer->setFrom($fromEmail, $fromName !== '' ? $fromName : 'CRM Team');
                      } catch (Exception $e) {
                        $smtpFailureDetail = trim((string) $mailer->ErrorInfo);
                        $errors[] = 'SMTP setup failed: ' . ($smtpFailureDetail !== '' ? $smtpFailureDetail : $e->getMessage());
                      }

                      if (!empty($errors)) {
                        $discussionStmt->close();
                      } else {
                        try {
                          foreach ($contacts as $contact) {
                            $to = trim((string) ($contact['email'] ?? ''));
                            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                              $failedRecipients[] = ($contact['company'] ?: 'Unknown company') . ' - missing/invalid email';
                              continue;
                            }

                            $tokenValues = [
                              '{{first_name}}' => (string) ($contact['first_name'] ?? ''),
                              '{{last_name}}' => (string) ($contact['last_name'] ?? ''),
                              '{{company}}' => (string) ($contact['company'] ?? ''),
                              '{{email}}' => $to,
                              '{{subject}}' => $subject,
                            ];

                            $body = strtr($messageTemplate, $tokenValues);
                            $discussionMessage = strtr($discussionTemplate, $tokenValues);

                            try {
                              $mailer->clearAddresses();
                              $mailer->Subject = $subject;
                              $mailer->Body = $body;
                              $mailer->addAddress($to);
                              $sent = $mailer->send();
                            } catch (Exception $e) {
                              $sent = false;
                              $smtpFailureDetail = trim((string) $mailer->ErrorInfo);
                              if ($smtpFailureDetail === '') {
                                $smtpFailureDetail = $e->getMessage();
                              }
                            }

                            if (!$sent) {
                              $reason = $smtpFailureDetail !== '' ? (' Reason: ' . $smtpFailureDetail) : '';
                              $failedRecipients[] = ($contact['company'] ?: 'Unknown company') . ' - ' . $to . $reason;
                              $detailLower = strtolower((string) $smtpFailureDetail);
                              if (strpos($detailLower, 'authenticate') !== false || strpos($detailLower, '535') !== false) {
                                $errors[] = 'SMTP authentication failed. Send operation stopped to avoid repeated retries. Please verify SMTP_USERNAME/SMTP_PASSWORD in .env.';
                                break;
                              }
                              continue;
                            }

                            $contactId = (string) $contact['contact_id'];
                            $discussionStmt->bind_param('sss', $contactId, $authorName, $discussionMessage);
                            $discussionStmt->execute();
                            $sentCount++;
                          }
                        } finally {
                          // Ensure connection is closed cleanly for the request lifecycle.
                          $mailer->smtpClose();
                        }

                        $discussionStmt->close();
                        $successMessage = 'Mass email complete. Sent: ' . $sentCount . '. Failed: ' . count($failedRecipients) . '.';
                      }
                    }
                }
            }
        }
            }
    }
}

$contactRows = [];
$filter_province = trim($_GET['filter_province'] ?? '');
$filter_status   = trim($_GET['filter_status']   ?? '');

// Build filter clause
$filter_where = "email IS NOT NULL AND TRIM(email) <> ''";
$filter_params = [];
$filter_types  = '';
if ($filter_province !== '') {
    $filter_where .= ' AND province = ?';
    $filter_params[] = $filter_province;
    $filter_types  .= 's';
}
if ($filter_status !== '') {
    $filter_where .= ' AND status = ?';
    $filter_params[] = $filter_status;
    $filter_types  .= 's';
}

$listSql = "SELECT contact_id, first_name, last_name, company, email, province, status FROM contacts WHERE $filter_where ORDER BY company ASC, last_name ASC, first_name ASC";
if ($filter_params) {
    $listStmt = $conn->prepare($listSql);
    $listStmt->bind_param($filter_types, ...$filter_params);
    $listStmt->execute();
    $listResult = $listStmt->get_result();
} else {
    $listResult = $conn->query($listSql);
}
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $contactRows[] = $row;
    }
    $listResult->free();
}

// Province and status options for filter dropdowns
$provinces = [];
$r_prov = $conn->query("SELECT DISTINCT province FROM contacts WHERE province IS NOT NULL AND province <> '' ORDER BY province");
if ($r_prov) { while ($p = $r_prov->fetch_row()) { $provinces[] = $p[0]; } $r_prov->free(); }
$statuses = [];
$r_stat = $conn->query("SELECT DISTINCT status FROM contacts WHERE status IS NOT NULL AND status <> '' ORDER BY status");
if ($r_stat) { while ($s = $r_stat->fetch_row()) { $statuses[] = $s[0]; } $r_stat->free(); }

$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 m-0">Mass Email</h1>
  <span class="badge bg-secondary">Contacts with email: <?= count($contactRows) ?></span>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($successMessage !== ''): ?>
  <div class="alert alert-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (!empty($failedRecipients)): ?>
  <div class="alert alert-warning">
    <strong>Failed Recipients</strong>
    <ul class="mb-0 mt-2">
      <?php foreach ($failedRecipients as $failed): ?>
        <li><?= htmlspecialchars($failed, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="mass_email.php">
  <?php renderCSRFInput(); ?>

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-8">
          <label for="subject" class="form-label">Subject</label>
          <input type="text" id="subject" name="subject" class="form-control" required value="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label for="from_name" class="form-label">From Name</label>
          <input type="text" id="from_name" name="from_name" class="form-control" value="<?= htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-6">
          <label for="from_email" class="form-label">From Email (optional)</label>
          <input type="email" id="from_email" name="from_email" class="form-control" value="<?= htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <label for="message" class="form-label">Email Message</label>
      <textarea id="message" name="message" class="form-control" rows="10" required><?= htmlspecialchars($messageTemplate, ENT_QUOTES, 'UTF-8') ?></textarea>
      <div class="form-text mt-2">Tokens supported: {{first_name}}, {{last_name}}, {{company}}, {{email}}, {{subject}}</div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <label for="discussion_message" class="form-label">Discussion Log Message</label>
      <textarea id="discussion_message" name="discussion_message" class="form-control" rows="3" required><?= htmlspecialchars($discussionTemplate, ENT_QUOTES, 'UTF-8') ?></textarea>
      <div class="form-text mt-2">This message is added to each contact in discussion log after a successful send.</div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Select Recipients</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleAllContacts">Select All</button>
    </div>
    <div class="card-body">
      <!-- Segment filter bar -->
      <form method="GET" class="row g-2 mb-3" id="segmentFilterForm">
        <?php foreach (['subject','message','discussion_message','from_name','from_email'] as $carry): ?>
          <input type="hidden" name="<?= $carry ?>" value="<?= htmlspecialchars($_GET[$carry] ?? '', ENT_QUOTES) ?>">
        <?php endforeach; ?>
        <div class="col-auto">
          <select name="filter_province" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Provinces</option>
            <?php foreach ($provinces as $pv): ?>
              <option value="<?= htmlspecialchars($pv) ?>" <?= $filter_province === $pv ? 'selected' : '' ?>><?= htmlspecialchars($pv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <select name="filter_status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $st): ?>
              <option value="<?= htmlspecialchars($st) ?>" <?= $filter_status === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <input type="text" id="recipientSearch" class="form-control form-control-sm" placeholder="Search name/company…" style="width:180px;">
        </div>
        <div class="col-auto align-self-center text-muted" style="font-size:12px;">
          Showing <span id="visibleCount"><?= count($contactRows) ?></span> of <?= count($contactRows) ?>
        </div>
        <?php if ($filter_province !== '' || $filter_status !== ''): ?>
        <div class="col-auto">
          <a href="mass_email.php" class="btn btn-sm btn-outline-danger">✕ Clear filters</a>
        </div>
        <?php endif; ?>
      </form>

      <div style="max-height: 360px; overflow: auto;" id="recipientList">
        <?php if (empty($contactRows)): ?>
          <p class="text-muted mb-0">No contacts match the current filters.</p>
        <?php else: ?>
          <?php foreach ($contactRows as $contact): ?>
            <?php
              $cid = (int) ($contact['contact_id'] ?? 0);
              $label = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
              if ($label === '') {
                  $label = $contact['company'] ?: 'Unnamed Contact';
              }
              $isChecked = in_array($cid, $selectedIds, true);
            ?>
            <div class="form-check mb-2 recipient-row" data-search="<?= htmlspecialchars(strtolower($label . ' ' . ($contact['company'] ?? '')), ENT_QUOTES) ?>">
              <input class="form-check-input recipient-checkbox" type="checkbox" name="contact_ids[]" value="<?= $cid ?>" id="contact_<?= $cid ?>" <?= $isChecked ? 'checked' : '' ?>>
              <label class="form-check-label" for="contact_<?= $cid ?>">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                <span class="text-muted">(<?= htmlspecialchars($contact['company'] ?? '', ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($contact['email'] ?? '', ENT_QUOTES, 'UTF-8') ?><?= $contact['province'] ? ' · ' . htmlspecialchars($contact['province']) : '' ?>)</span>
              </label>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" name="action" value="send_mass" class="btn btn-primary">Send Mass Email</button>
    <button type="submit" name="action" value="test_smtp" class="btn btn-outline-primary"><?= $isGraphTransport ? 'Test Graph (to robertja98@gmail.com)' : 'Test SMTP (to robertja98@gmail.com)' ?></button>
  </div>
</form>

<script>
(function () {
  const toggleBtn = document.getElementById('toggleAllContacts');
  const checkboxes = document.querySelectorAll('.recipient-checkbox');

  if (toggleBtn && checkboxes.length > 0) {
    toggleBtn.addEventListener('click', function () {
      const visibleRows = Array.from(document.querySelectorAll('.recipient-row')).filter(r => r.style.display !== 'none');
      const allChecked = visibleRows.every(r => r.querySelector('input').checked);
      visibleRows.forEach(r => r.querySelector('input').checked = !allChecked);
      toggleBtn.textContent = allChecked ? 'Select All' : 'Unselect All';
    });
  }

  const searchInput = document.getElementById('recipientSearch');
  const visibleCountEl = document.getElementById('visibleCount');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      let count = 0;
      document.querySelectorAll('.recipient-row').forEach(function (row) {
        const matches = q === '' || row.dataset.search.includes(q);
        row.style.display = matches ? '' : 'none';
        if (matches) count++;
      });
      if (visibleCountEl) visibleCountEl.textContent = count;
    });
  }
})();
</script>

<?php require_once __DIR__ . '/layout_end.php'; ?>
