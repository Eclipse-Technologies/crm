<?php
/**
 * admin_helper.php — Admin utility functions (MySQL-based)
 * All functions query the MySQL database directly via get_mysql_connection().
 */

require_once __DIR__ . '/db_mysql.php';

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

/**
 * Enforce that the current user is authenticated.
 * Redirects to the login page if not logged in.
 */
function requireAdmin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: simple_auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
}

// ---------------------------------------------------------------------------
// System statistics (dashboard)
// ---------------------------------------------------------------------------

/**
 * Returns an associative array of key system stats from MySQL.
 */
function getSystemStats(): array {
    $conn = get_mysql_connection();

    $stats = [
        'total_contacts'      => 0,
        'contacts_with_email' => 0,
        'contacts_with_company' => 0,
        'duplicate_emails'    => 0,
        'unique_companies'    => 0,
        'backup_count'        => 0,
        'total_backup_size'   => 0,
        'audit_log_rows'      => 0,
        'audit_log_size'      => 0,
        'error_log_size'      => 0,
        // kept for template compatibility — no longer meaningful
        'csv_size'            => 0,
    ];

    // Total contacts
    $r = $conn->query("SELECT COUNT(*) AS n FROM contacts");
    if ($r) { $stats['total_contacts'] = (int)$r->fetch_assoc()['n']; $r->free(); }

    // Contacts with email
    $r = $conn->query("SELECT COUNT(*) AS n FROM contacts WHERE email IS NOT NULL AND email <> ''");
    if ($r) { $stats['contacts_with_email'] = (int)$r->fetch_assoc()['n']; $r->free(); }

    // Contacts with company
    $r = $conn->query("SELECT COUNT(*) AS n FROM contacts WHERE company IS NOT NULL AND company <> ''");
    if ($r) { $stats['contacts_with_company'] = (int)$r->fetch_assoc()['n']; $r->free(); }

    // Duplicate emails
    $r = $conn->query("SELECT COUNT(*) AS n FROM (SELECT email FROM contacts WHERE email <> '' GROUP BY email HAVING COUNT(*) > 1) AS dupes");
    if ($r) { $stats['duplicate_emails'] = (int)$r->fetch_assoc()['n']; $r->free(); }

    // Unique companies
    $r = $conn->query("SELECT COUNT(DISTINCT company) AS n FROM contacts WHERE company IS NOT NULL AND company <> ''");
    if ($r) { $stats['unique_companies'] = (int)$r->fetch_assoc()['n']; $r->free(); }

    // Audit log row count
    $r = $conn->query("SELECT COUNT(*) AS n FROM audit_log");
    if ($r) { $stats['audit_log_rows'] = (int)$r->fetch_assoc()['n']; $r->free(); }

    // Approximate audit log data size
    $r = $conn->query("SELECT ROUND(data_length + index_length) AS sz FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'audit_log'");
    if ($r) { $row = $r->fetch_assoc(); $stats['audit_log_size'] = (int)($row['sz'] ?? 0); $r->free(); }

    // Error log file size
    $errLog = __DIR__ . '/error_log.txt';
    $stats['error_log_size'] = file_exists($errLog) ? filesize($errLog) : 0;

    // Backup files
    $backupDir = __DIR__ . '/backups/';
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '*');
        $stats['backup_count'] = count($files);
        foreach ($files as $f) {
            $stats['total_backup_size'] += filesize($f);
        }
    }

    $conn->close();
    return $stats;
}

// ---------------------------------------------------------------------------
// Data integrity check
// ---------------------------------------------------------------------------

function checkDataIntegrity(): array {
    $conn = get_mysql_connection();
    $issues = [];

    // Check for contacts missing contact_id
    $r = $conn->query("SELECT COUNT(*) AS n FROM contacts WHERE contact_id IS NULL OR contact_id = ''");
    if ($r) {
        $n = (int)$r->fetch_assoc()['n'];
        if ($n > 0) { $issues[] = "$n contact(s) are missing a contact_id."; }
        $r->free();
    }

    // Check for duplicate contact_ids
    $r = $conn->query("SELECT COUNT(*) AS n FROM (SELECT contact_id FROM contacts GROUP BY contact_id HAVING COUNT(*) > 1) AS dupes");
    if ($r) {
        $n = (int)$r->fetch_assoc()['n'];
        if ($n > 0) { $issues[] = "$n duplicate contact_id value(s) detected."; }
        $r->free();
    }

    // Check for contacts missing first and last name
    $r = $conn->query("SELECT COUNT(*) AS n FROM contacts WHERE (first_name IS NULL OR first_name = '') AND (last_name IS NULL OR last_name = '')");
    if ($r) {
        $n = (int)$r->fetch_assoc()['n'];
        if ($n > 0) { $issues[] = "$n contact(s) have no first or last name."; }
        $r->free();
    }

    $conn->close();
    return [
        'is_valid' => empty($issues),
        'issues'   => $issues,
    ];
}

// ---------------------------------------------------------------------------
// Recent activity & active users (from MySQL audit_log)
// ---------------------------------------------------------------------------

function getRecentActivity(int $limit = 10): array {
    $conn = get_mysql_connection();
    $rows = [];
    $stmt = $conn->prepare("SELECT timestamp, user_id, action, entity_type, entity_id, summary, status FROM audit_log ORDER BY timestamp DESC LIMIT ?");
    if ($stmt) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        $stmt->close();
    }
    $conn->close();
    return $rows;
}

function getActiveUsers(): array {
    $conn = get_mysql_connection();
    $rows = [];
    $r = $conn->query("SELECT
            al.user_id,
            COALESCE(NULLIF(u.username, ''), CAST(al.user_id AS CHAR)) AS username,
            COALESCE(u.email, '') AS email,
            COALESCE(u.role, '') AS role,
            COUNT(*) AS action_count,
            MAX(al.timestamp) AS last_action_at
        FROM audit_log al
        LEFT JOIN users u
            ON CAST(u.id AS CHAR) = CAST(al.user_id AS CHAR)
            OR BINARY u.username = BINARY CAST(al.user_id AS CHAR)
        WHERE al.user_id IS NOT NULL
            AND TRIM(CAST(al.user_id AS CHAR)) <> ''
            AND TRIM(CAST(al.user_id AS CHAR)) <> '0'
            AND LOWER(TRIM(CAST(al.user_id AS CHAR))) <> 'unknown'
        GROUP BY al.user_id, u.username, u.email, u.role
        ORDER BY action_count DESC
        LIMIT 10");
    if ($r) {
        while ($row = $r->fetch_assoc()) { $rows[] = $row; }
        $r->free();
    }
    $conn->close();
    return $rows;
}

// ---------------------------------------------------------------------------
// Audit trail for a single contact
// ---------------------------------------------------------------------------

function getAuditTrail(string $contact_id, int $limit = 50): array {
    $conn = get_mysql_connection();
    $rows = [];
    $stmt = $conn->prepare("SELECT * FROM audit_log WHERE entity_id = ? AND entity_type = 'contact' ORDER BY timestamp DESC LIMIT ?");
    if ($stmt) {
        $stmt->bind_param('si', $contact_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['changes']) && is_string($row['changes'])) {
                $row['changes'] = json_decode($row['changes'], true) ?? [];
            }
            $rows[] = $row;
        }
        $result->free();
        $stmt->close();
    }
    $conn->close();
    return $rows;
}

// ---------------------------------------------------------------------------
// Duplicate email detection
// ---------------------------------------------------------------------------

function findDuplicateEmails(): array {
    $conn = get_mysql_connection();
    $groups = [];
    $r = $conn->query("SELECT email, COUNT(*) AS cnt FROM contacts WHERE email IS NOT NULL AND email <> '' GROUP BY email HAVING cnt > 1 ORDER BY cnt DESC");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $email = $row['email'];
            $stmt2 = $conn->prepare("SELECT * FROM contacts WHERE email = ?");
            $stmt2->bind_param('s', $email);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $members = [];
            while ($m = $result2->fetch_assoc()) { $members[] = $m; }
            $result2->free();
            $stmt2->close();
            $groups[$email] = $members;
        }
        $r->free();
    }
    $conn->close();
    return $groups;
}

// ---------------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------------

function generateActivityReport(string $start_date, string $end_date): array {
    $conn = get_mysql_connection();
    $report = [
        'period'       => "$start_date to $end_date",
        'total_actions'=> 0,
        'successes'    => 0,
        'failures'     => 0,
        'by_action'    => [],
        'by_user'      => [],
    ];

    $stmt = $conn->prepare("SELECT action, status, user_id FROM audit_log WHERE DATE(timestamp) BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report['total_actions']++;
            if (($row['status'] ?? '') === 'success') { $report['successes']++; }
            else { $report['failures']++; }
            $a = $row['action'] ?? 'unknown';
            $report['by_action'][$a] = ($report['by_action'][$a] ?? 0) + 1;
            $u = $row['user_id'] ?? 'unknown';
            $report['by_user'][$u] = ($report['by_user'][$u] ?? 0) + 1;
        }
        $result->free();
        $stmt->close();
    }
    arsort($report['by_action']);
    arsort($report['by_user']);
    $conn->close();
    return $report;
}

function getContactStatsByCategory(string $category): array {
    $allowed = ['company', 'province', 'city', 'country', 'status'];
    if (!in_array($category, $allowed, true)) { return []; }

    $conn = get_mysql_connection();
    $stats = [];
    $r = $conn->query("SELECT `$category` AS cat, COUNT(*) AS cnt FROM contacts WHERE `$category` IS NOT NULL AND `$category` <> '' GROUP BY `$category` ORDER BY cnt DESC LIMIT 20");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $stats[$row['cat']] = (int)$row['cnt'];
        }
        $r->free();
    }
    $conn->close();
    return $stats;
}

// ---------------------------------------------------------------------------
// Daily call workflow stats (dashboard)
// ---------------------------------------------------------------------------

function getDailyCallStatus(): array {
    $conn = get_mysql_connection();

    $status = [
        'recipient' => trim((string) getenv('DAILY_CALL_EMAIL_TO')),
        'last_sent_at' => null,
        'sent_today' => 0,
        'call_ready_now' => 0,
        'tracking_rows' => 0,
    ];

    $tableExists = false;
    $r = $conn->query("SHOW TABLES LIKE 'daily_call_tracking'");
    if ($r) {
        $tableExists = $r->num_rows > 0;
        $r->free();
    }

    if ($tableExists) {
        $r = $conn->query("SELECT MAX(last_sent_at) AS last_sent_at, SUM(CASE WHEN DATE(last_sent_at) = CURDATE() THEN 1 ELSE 0 END) AS sent_today, COUNT(*) AS tracking_rows FROM daily_call_tracking");
        if ($r) {
            $row = $r->fetch_assoc();
            $status['last_sent_at'] = $row['last_sent_at'] ?? null;
            $status['sent_today'] = (int) ($row['sent_today'] ?? 0);
            $status['tracking_rows'] = (int) ($row['tracking_rows'] ?? 0);
            $r->free();
        }
    }

        if ($tableExists) {
                $r = $conn->query("SELECT COUNT(*) AS n
                        FROM contacts c
                        LEFT JOIN daily_call_tracking t ON t.contact_id = c.contact_id
                        WHERE TRIM(COALESCE(c.phone, '')) <> ''
                            AND LOWER(TRIM(COALESCE(c.province, ''))) IN ('on', 'ontario')
                            AND t.called_at IS NULL
                            AND (t.last_sent_at IS NULL OR DATE(t.last_sent_at) < CURDATE())");
        } else {
                $r = $conn->query("SELECT COUNT(*) AS n
                        FROM contacts c
                        WHERE TRIM(COALESCE(c.phone, '')) <> ''
                            AND LOWER(TRIM(COALESCE(c.province, ''))) IN ('on', 'ontario')");
        }
    if ($r) {
        $status['call_ready_now'] = (int) ($r->fetch_assoc()['n'] ?? 0);
        $r->free();
    }

    $conn->close();
    return $status;
}

// ---------------------------------------------------------------------------
// formatBytes helper (also defined in backup_handler.php — guard added)
// ---------------------------------------------------------------------------

if (!function_exists('formatBytes')) {
    function formatBytes(int $bytes, int $precision = 2): string {
        if ($bytes <= 0) { return '0 B'; }
        $units = ['B','KB','MB','GB'];
        $pow   = min((int)floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }
}
