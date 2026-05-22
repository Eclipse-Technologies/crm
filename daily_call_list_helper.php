<?php
require_once __DIR__ . '/env_loader.php';

function daily_env_flag_enabled(string $value): bool {
    $normalized = strtolower(trim($value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function daily_smtp_endpoint_reachable(string $host, int $port, int $timeout = 5): bool {
    if ($host === '' || $port <= 0) {
        return false;
    }

    $errno = 0;
    $errstr = '';
    $conn = @fsockopen($host, $port, $errno, $errstr, max(1, $timeout));
    if (is_resource($conn)) {
        fclose($conn);
        return true;
    }

    return false;
}

function ensure_daily_call_tracking_table(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS daily_call_tracking (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contact_id VARCHAR(64) NOT NULL,
        first_sent_at DATETIME NULL,
        last_sent_at DATETIME NULL,
        sent_count INT NOT NULL DEFAULT 0,
        called_at DATETIME NULL,
        called_by VARCHAR(64) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_daily_call_contact (contact_id),
        KEY idx_daily_call_called_at (called_at),
        KEY idx_daily_call_last_sent_at (last_sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

function fetch_daily_ontario_call_candidates(mysqli $conn, int $limit = 10): array {
    $limit = max(1, min(100, $limit));
    $sql = "SELECT
                c.contact_id,
                c.first_name,
                c.last_name,
                c.company,
                c.phone,
                c.email,
                c.city,
                c.province,
                t.last_sent_at,
                t.called_at
            FROM contacts c
            LEFT JOIN daily_call_tracking t ON t.contact_id = c.contact_id
            WHERE TRIM(COALESCE(c.phone, '')) <> ''
              AND LOWER(TRIM(COALESCE(c.province, ''))) IN ('on', 'ontario')
              AND t.called_at IS NULL
              AND (t.last_sent_at IS NULL OR DATE(t.last_sent_at) < CURDATE())
            ORDER BY
              CASE WHEN t.contact_id IS NULL THEN 0 ELSE 1 END ASC,
              COALESCE(t.last_sent_at, '1970-01-01 00:00:00') ASC,
              c.contact_id ASC
            LIMIT ?";

    $rows = [];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $rows;
    }

    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function mark_daily_call_contacts_sent(mysqli $conn, array $contactIds): void {
    $contactIds = array_values(array_filter(array_map('strval', $contactIds), static function ($id) {
        return trim($id) !== '';
    }));

    if (empty($contactIds)) {
        return;
    }

    $sql = "INSERT INTO daily_call_tracking (contact_id, first_sent_at, last_sent_at, sent_count)
            VALUES (?, NOW(), NOW(), 1)
            ON DUPLICATE KEY UPDATE
                last_sent_at = NOW(),
                sent_count = sent_count + 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }

    foreach ($contactIds as $id) {
        $stmt->bind_param('s', $id);
        $stmt->execute();
    }

    $stmt->close();
}

function mark_daily_call_contact_called(mysqli $conn, string $contactId, string $calledBy = ''): bool {
    $contactId = trim($contactId);
    if ($contactId === '') {
        return false;
    }

    $sql = "INSERT INTO daily_call_tracking (contact_id, called_at, called_by)
            VALUES (?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                called_at = NOW(),
                called_by = VALUES(called_by)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $contactId, $calledBy);
    $ok = $stmt->execute();
    $stmt->close();

    return (bool) $ok;
}

function fetch_called_contact_id_map(mysqli $conn, array $contactIds): array {
    $contactIds = array_values(array_filter(array_map('strval', $contactIds), static function ($id) {
        return trim($id) !== '';
    }));

    if (empty($contactIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
    $sql = "SELECT contact_id FROM daily_call_tracking WHERE called_at IS NOT NULL AND contact_id IN ($placeholders)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $types = str_repeat('s', count($contactIds));
    $stmt->bind_param($types, ...$contactIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $map = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $map[(string) ($row['contact_id'] ?? '')] = true;
    }

    $stmt->close();
    return $map;
}

function daily_call_mark_link_secret(): string {
    load_env();

    $explicit = trim((string) getenv('DAILY_CALL_LINK_SECRET'));
    if ($explicit !== '') {
        return $explicit;
    }

    $smtpPassword = trim((string) getenv('SMTP_PASSWORD'));
    if ($smtpPassword !== '') {
        return hash('sha256', 'daily-call-link|' . $smtpPassword);
    }

    return '';
}

function daily_call_base_url(): string {
    load_env();

    $configured = trim((string) getenv('DAILY_CALL_BASE_URL'));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $scriptDir = trim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        $prefix = $scriptDir !== '' ? '/' . $scriptDir : '';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . $prefix;
    }

    return '';
}

function build_daily_call_mark_link(string $contactId): string {
    $contactId = trim($contactId);
    if ($contactId === '') {
        return '';
    }

    $secret = daily_call_mark_link_secret();
    $baseUrl = daily_call_base_url();
    if ($secret === '' || $baseUrl === '') {
        return '';
    }

    $ts = time();
    $signature = hash_hmac('sha256', $contactId . '|' . $ts, $secret);
    $query = http_build_query([
        'contact_id' => $contactId,
        'ts' => $ts,
        'sig' => $signature,
    ]);

    return $baseUrl . '/daily_call_mark.php?' . $query;
}

function daily_call_mark_link_max_age_seconds(): int {
    load_env();
    $raw = trim((string) getenv('DAILY_CALL_LINK_MAX_AGE_SECONDS'));
    if ($raw === '') {
        return 1209600; // 14 days default
    }

    $value = (int) $raw;
    if ($value < 3600) {
        return 3600;
    }

    if ($value > 31536000) {
        return 31536000;
    }

    return $value;
}

function verify_daily_call_mark_signature(string $contactId, string $ts, string $sig, int $maxAgeSeconds = 1209600): bool {
    $contactId = trim($contactId);
    $sig = trim($sig);
    $tsInt = (int) $ts;

    if ($contactId === '' || $sig === '' || $tsInt <= 0) {
        return false;
    }

    $now = time();
    if ($tsInt > $now + 300) {
        return false;
    }

    if (($now - $tsInt) > $maxAgeSeconds) {
        return false;
    }

    $secret = daily_call_mark_link_secret();
    if ($secret === '') {
        return false;
    }

    $expected = hash_hmac('sha256', $contactId . '|' . $tsInt, $secret);
    return hash_equals($expected, $sig);
}

function send_daily_call_email(string $recipientEmail, array $contacts): array {
    $recipientEmail = trim($recipientEmail);
    if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Recipient email is invalid.'];
    }

    if (empty($contacts)) {
        return ['ok' => true, 'error' => '', 'subject' => 'Daily Ontario Call List - No contacts'];
    }

    load_env();

    $phpMailerSrc = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
    if (!file_exists($phpMailerSrc . 'PHPMailer.php')) {
        return ['ok' => false, 'error' => 'PHPMailer is missing in vendor/phpmailer/phpmailer/src.'];
    }

    require_once $phpMailerSrc . 'Exception.php';
    require_once $phpMailerSrc . 'PHPMailer.php';
    require_once $phpMailerSrc . 'SMTP.php';

    $smtpHost = trim((string) getenv('SMTP_HOST'));
    $smtpPort = (int) (trim((string) getenv('SMTP_PORT')) ?: 587);
    $smtpAuth = daily_env_flag_enabled((string) (getenv('SMTP_AUTH') ?: 'true'));
    $smtpUser = trim((string) getenv('SMTP_USERNAME'));
    $smtpPass = trim((string) getenv('SMTP_PASSWORD'));
    $smtpEncryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')));

    if ($smtpHost === '') {
        return ['ok' => false, 'error' => 'SMTP is not configured. Set SMTP_HOST in .env.'];
    }

    if ($smtpAuth && ($smtpUser === '' || $smtpPass === '')) {
        return ['ok' => false, 'error' => 'SMTP auth is enabled. Missing SMTP_USERNAME or SMTP_PASSWORD.'];
    }

    if (!in_array($smtpEncryption, ['tls', 'ssl', 'starttls', 'none', ''], true)) {
        return ['ok' => false, 'error' => 'SMTP_ENCRYPTION must be tls, ssl, starttls, or none.'];
    }

    if (!daily_smtp_endpoint_reachable($smtpHost, $smtpPort, 5)) {
        return ['ok' => false, 'error' => 'SMTP server is not reachable right now (' . $smtpHost . ':' . $smtpPort . ').'];
    }

    $fromEmail = trim((string) getenv('SMTP_FROM_EMAIL'));
    if ($fromEmail === '') {
        $fromEmail = $smtpUser;
    }
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'From Email is required when using SMTP. Set SMTP_FROM_EMAIL in .env.'];
    }

    $fromName = trim((string) getenv('SMTP_FROM_NAME'));
    if ($fromName === '') {
        $fromName = 'CRM Team';
    }

    $subject = 'Daily Ontario Call List (' . count($contacts) . ') - ' . date('Y-m-d');

    $rowsHtml = '';
    foreach ($contacts as $c) {
        $name = trim(((string) ($c['first_name'] ?? '')) . ' ' . ((string) ($c['last_name'] ?? '')));
        if ($name === '') {
            $name = '(No name)';
        }

        $company = trim((string) ($c['company'] ?? ''));
        $phone = trim((string) ($c['phone'] ?? ''));
        $city = trim((string) ($c['city'] ?? ''));
        $province = trim((string) ($c['province'] ?? ''));
        $contactId = trim((string) ($c['contact_id'] ?? ''));
        $markUrl = build_daily_call_mark_link($contactId);
        $markCell = $markUrl !== ''
            ? '<a href="' . htmlspecialchars($markUrl, ENT_QUOTES, 'UTF-8') . '">Mark Called</a>'
            : '-';

        $rowsHtml .= '<tr>'
            . '<td style="padding:6px 8px;border:1px solid #ddd;">' . htmlspecialchars($contactId, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:6px 8px;border:1px solid #ddd;">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:6px 8px;border:1px solid #ddd;">' . htmlspecialchars($company, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:6px 8px;border:1px solid #ddd;"><a href="tel:' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</a></td>'
            . '<td style="padding:6px 8px;border:1px solid #ddd;">' . htmlspecialchars(trim($city . ' ' . $province), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:6px 8px;border:1px solid #ddd;">' . $markCell . '</td>'
            . '</tr>';
    }

    $body = '<p>Here are your daily Ontario contacts to call.</p>'
        . '<table style="border-collapse:collapse;min-width:760px;">'
        . '<thead><tr>'
        . '<th style="padding:6px 8px;border:1px solid #ddd;background:#f7f7f7;">Contact ID</th>'
        . '<th style="padding:6px 8px;border:1px solid #ddd;background:#f7f7f7;">Name</th>'
        . '<th style="padding:6px 8px;border:1px solid #ddd;background:#f7f7f7;">Company</th>'
        . '<th style="padding:6px 8px;border:1px solid #ddd;background:#f7f7f7;">Phone</th>'
        . '<th style="padding:6px 8px;border:1px solid #ddd;background:#f7f7f7;">Location</th>'
        . '<th style="padding:6px 8px;border:1px solid #ddd;background:#f7f7f7;">Done</th>'
        . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
        . '<p style="margin-top:14px;">After each call, tap <strong>Mark Called</strong> in this email from your phone. If links are disabled, use Contact List in CRM.</p>';

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $smtpHost;
        $mailer->SMTPAuth = $smtpAuth;
        if ($smtpAuth) {
            $mailer->Username = $smtpUser;
            $mailer->Password = $smtpPass;
        }
        $mailer->Port = $smtpPort;
        $mailer->Timeout = 10;
        $mailer->Timelimit = 20;

        if ($smtpEncryption === 'ssl' || $smtpEncryption === 'smtps') {
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpEncryption === 'tls' || $smtpEncryption === 'starttls') {
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
        }

        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($recipientEmail);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $body;
        $mailer->send();

        return ['ok' => true, 'error' => '', 'subject' => $subject];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => 'Email send failed: ' . $e->getMessage()];
    }
}
