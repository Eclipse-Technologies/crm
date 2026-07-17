<?php
/**
 * Audit Logging System - Tracks all contact modifications for compliance
 * 
 * Features:
 * - Logs create, update, delete actions
 * - Records user, timestamp, and old/new values
 * - Provides audit trail queries
 */


require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/backup_handler.php';
require_once __DIR__ . '/env_loader.php';
load_env();
$crmConfig = require __DIR__ . '/config.local.php';
define('AUDIT_BACKUP_DIR', getenv('AUDIT_BACKUP_DIR') ?: ($crmConfig['audit_backup_dir'] ?? 'backups/audit/'));

// Ensure audit backup directory exists
if (!is_dir(AUDIT_BACKUP_DIR)) {
    @mkdir(AUDIT_BACKUP_DIR, 0755, true);
}

/**
 * Audit log entry schema
 */
function getAuditSchema() {
    return [
        'id',           // Unique log entry ID
        'timestamp',    // YYYY-MM-DD HH:MM:SS
        'user_id',      // User who made the change
        'ip_address',   // IP address of requester
        'action',       // create, update, delete, import, export
        'entity_type',  // contact, task, opportunity, etc.
        'entity_id',    // ID of affected entity (contact ID)
        'changes',      // JSON: {field: {old: value, new: value}}
        'summary',      // Human readable summary
        'status',       // success, failed
        'error_msg',    // If status=failed, the error
    ];
}

/**
 * Log a contact creation
 */
function auditCreateContact($contact, $status = 'success', $error_msg = null) {
    $changes = [];
    foreach ($contact as $field => $value) {
        if ($field !== 'id' && !empty($value)) {
            $changes[$field] = [
                'old' => null,
                'new' => $value
            ];
        }
    }
    
    return logAuditAction('create', 'contact', $contact['id'] ?? null, $changes, 
                         'Created new contact', $status, $error_msg);
}

/**
 * Log a contact update
 */
function auditUpdateContact($contact_id, $old_contact, $new_contact, $status = 'success', $error_msg = null) {
    $changes = [];
    $changed_fields = [];
    
    foreach ($new_contact as $field => $new_value) {
        $old_value = $old_contact[$field] ?? null;
        
        if ($old_value !== $new_value) {
            $changes[$field] = [
                'old' => $old_value,
                'new' => $new_value
            ];
            $changed_fields[] = $field;
        }
    }
    
    $summary = !empty($changed_fields) 
        ? 'Updated contact: ' . implode(', ', $changed_fields)
        : 'No changes detected';
    
    return logAuditAction('update', 'contact', $contact_id, $changes, $summary, $status, $error_msg);
}

/**
 * Log a contact deletion
 */
function auditDeleteContact($contact) {
    $changes = [];
    foreach ($contact as $field => $value) {
        if ($field !== 'id' && !empty($value)) {
            $changes[$field] = [
                'old' => $value,
                'new' => null
            ];
        }
    }
    
    $summary = 'Deleted contact: ' . ($contact['company'] ?? 'Unknown') . 
               ' (' . ($contact['email'] ?? '') . ')';
    
    return logAuditAction('delete', 'contact', $contact['id'], $changes, $summary, 'success', null);
}

/**
 * Log bulk import
 */
function auditImport($contact_count, $status = 'success', $error_msg = null) {
    $changes = [
        'import_count' => [
            'old' => 0,
            'new' => $contact_count
        ]
    ];
    
    return logAuditAction('import', 'contact', 'bulk', $changes, 
                         "Bulk imported $contact_count contacts", $status, $error_msg);
}

/**
 * Log export action
 */
function auditExport($contact_count, $filters = []) {
    $changes = [
        'export_count' => [
            'old' => 0,
            'new' => $contact_count
        ],
        'filters' => [
            'old' => null,
            'new' => json_encode($filters)
        ]
    ];
    
    return logAuditAction('export', 'contact', 'bulk', $changes, 
                         "Exported $contact_count contacts", 'success', null);
}

/**
 * Core audit logging function
 */
function logAuditAction($action, $entity_type, $entity_id, $changes, $summary, $status = 'success', $error_msg = null) {
    try {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? 'system',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'changes' => json_encode($changes),
            'summary' => $summary,
            'status' => $status,
            'error_msg' => $error_msg ?? '',
        ];
        $conn = get_mysql_connection();
        $stmt = $conn->prepare("INSERT INTO audit_log (timestamp, user_id, ip_address, action, entity_type, entity_id, changes, summary, status, error_msg) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            'ssssssssss',
            $log_entry['timestamp'],
            $log_entry['user_id'],
            $log_entry['ip_address'],
            $log_entry['action'],
            $log_entry['entity_type'],
            $log_entry['entity_id'],
            $log_entry['changes'],
            $log_entry['summary'],
            $log_entry['status'],
            $log_entry['error_msg']
        );
        $stmt->execute();
        $stmt->close();
        $conn->close();
        // Log cleanup (keep recent 10000 entries)
        cleanOldAuditLogs();
        return true;
    } catch (Exception $e) {
        error_log("Audit logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit statistics
 */
function getAuditStats($days = 30) {
    $days = max(1, (int) $days);

    $stats = [
        'total_entries' => 0,
        'actions' => [],
        'users' => [],
        'entities' => [],
        'status' => [],
    ];

    try {
        $conn = get_mysql_connection();
        $cutoff = date('Y-m-d H:i:s', strtotime("-$days days"));

        $totalStmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM audit_log WHERE timestamp >= ?');
        $totalStmt->bind_param('s', $cutoff);
        $totalStmt->execute();
        $totalResult = $totalStmt->get_result();
        $totalRow = $totalResult ? $totalResult->fetch_assoc() : null;
        $stats['total_entries'] = (int) ($totalRow['cnt'] ?? 0);
        $totalStmt->close();

        $groupQueries = [
            'actions' => 'SELECT action AS label, COUNT(*) AS cnt FROM audit_log WHERE timestamp >= ? GROUP BY action',
            'users' => 'SELECT user_id AS label, COUNT(*) AS cnt FROM audit_log WHERE timestamp >= ? GROUP BY user_id',
            'entities' => 'SELECT entity_type AS label, COUNT(*) AS cnt FROM audit_log WHERE timestamp >= ? GROUP BY entity_type',
            'status' => 'SELECT status AS label, COUNT(*) AS cnt FROM audit_log WHERE timestamp >= ? GROUP BY status',
        ];

        foreach ($groupQueries as $key => $sql) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $cutoff);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
                $label = (string) ($row['label'] ?? 'unknown');
                $stats[$key][$label] = (int) ($row['cnt'] ?? 0);
            }
            $stmt->close();
        }

        $conn->close();
    } catch (Throwable $e) {
        error_log('Audit stats query failed: ' . $e->getMessage());
    }

    return $stats;
}

/**
 * Clean old audit logs (keep last 10000 entries)
 */
function cleanOldAuditLogs($keep_count = 10000) {
    // Remove old audit logs from MySQL, keeping only the most recent $keep_count entries
    $conn = get_mysql_connection();
    // Get the id of the oldest entry to keep
    $sql = "SELECT id FROM audit_log ORDER BY timestamp DESC LIMIT 1 OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $keep_count);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldest_id = null;
    if ($row = $result->fetch_assoc()) {
        $oldest_id = $row['id'];
    }
    $stmt->close();
    if ($oldest_id !== null) {
        // Delete all entries older than this id
        $del = $conn->prepare("DELETE FROM audit_log WHERE id < ?");
        $del->bind_param('i', $oldest_id);
        $del->execute();
        $del->close();
    }
    $conn->close();
    return true;
}

/**
 * Format audit entry for display
 */
function formatAuditEntry($entry) {
    $changes_list = [];
    
    return [
        'timestamp' => htmlspecialchars($entry['timestamp'] ?? ''),
        'user' => htmlspecialchars($entry['user_id'] ?? 'unknown'),
        'action' => htmlspecialchars($entry['action'] ?? ''),
        'summary' => htmlspecialchars($entry['summary'] ?? ''),
        'changes' => $changes_list,
        'status' => htmlspecialchars($entry['status'] ?? 'unknown'),
        'error' => !empty($entry['error_msg']) ? htmlspecialchars($entry['error_msg']) : null,
    ];
}

?>
