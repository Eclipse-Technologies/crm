<?php
require_once __DIR__ . '/sanitize_helper.php';
/**
 * Centralized Error Handling
 * Provides structured logging and user-friendly error messages
 */

// Ensure session started for error logging
if (session_status() === PHP_SESSION_NONE) {
}

define('ERROR_LOG_DIR', __DIR__ . '/logs');
define('ERROR_LOG_FILE', ERROR_LOG_DIR . '/errors.log');
define('ERROR_LOG_MAX_SIZE', 5242880); // 5MB

/**
 * Initialize error logging directory
 */
function initializeErrorLogging() {
    if (!is_dir(ERROR_LOG_DIR)) {
        if (!mkdir(ERROR_LOG_DIR, 0755, true)) {
            // Fallback to root directory if logs dir can't be created
            return false;
        }
    }
    return true;
}

/**
 * Log an error with structured format
 */
function logError($message, $context = [], $level = 'ERROR') {
    initializeErrorLogging();
    
    $timestamp = date('Y-m-d H:i:s');
    $userid = $_SESSION['user_id'] ?? 'anonymous';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $logEntry = [
        'timestamp' => $timestamp,
        'level' => $level,
        'user_id' => $userid,
        'ip_address' => $ip,
        'message' => $message,
        'context' => $context,
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
    ];
    
    // Format as JSON for structured logging
    $logLine = json_encode($logEntry) . "\n";
    
    // Write to log file
    if (file_exists(ERROR_LOG_FILE) && filesize(ERROR_LOG_FILE) > ERROR_LOG_MAX_SIZE) {
        // Rotate log if too large
        rename(ERROR_LOG_FILE, ERROR_LOG_FILE . '.' . date('YmdHis'));
    }
    
    @file_put_contents(ERROR_LOG_FILE, $logLine, FILE_APPEND);
}

/**
 * Log info messages
 */
function logInfo($message, $context = []) {
    logError($message, $context, 'INFO');
}

/**
 * Log warning messages
 */
function logWarning($message, $context = []) {
    logError($message, $context, 'WARNING');
}

/**
 * Display user-friendly error
 */
function showError($title, $message, $details = '') {
    ?>
    <div style="background:#fee; border:1px solid #c00; border-radius:4px; padding:20px; margin:20px 0; max-width:600px;">
        <h3 style="color:#c00; margin-top:0;">⚠️ <?= e($title) ?></h3>
        <p><?= e($message) ?></p>
        <?php if (!empty($details)): ?>
            <details style="margin-top:10px; font-size:0.9em; color:#666;">
                <summary>Technical details</summary>
                <pre style="background:#f5f5f5; padding:10px; border-radius:3px; overflow-x:auto;"><?= e($details) ?></pre>
            </details>
        <?php endif; ?>
        <p style="margin-bottom:0;">
            <a href="javascript:history.back()" style="color:#00a; text-decoration:none;">← Go back</a>
        </p>
    </div>
    <?php
}

/**
 * Display success message
 */
function showSuccess($message) {
    ?>
    <div style="background:#efe; border:1px solid #0a0; border-radius:4px; padding:15px; margin:20px 0;">
        <p style="color:#0a0; margin:0;">✓ <?= e($message) ?></p>
    </div>
    <?php
}

/**
 * Display validation errors
 */
function showValidationErrors($errors) {
    if (empty($errors)) {
        return;
    }
    ?>
    <div style="background:#fee; border:1px solid #c66; border-radius:4px; padding:15px; margin:20px 0;">
        <p style="color:#c00; margin-top:0;"><strong>Please fix the following:</strong></p>
        <ul style="margin:10px 0; padding-left:20px;">
            <?php foreach ($errors as $field => $message): ?>
                <li>
                    <strong><?= e(ucfirst(str_replace('_', ' ', $field))) ?>:</strong>
                    <?= e($message) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * Convert exception to user-friendly message
 */
function getErrorMessage($exception) {
    $message = $exception->getMessage();
    
    // Customize based on error type
    if (strpos($message, 'Duplicate email') !== false) {
        return 'This email address is already in use.';
    } elseif (strpos($message, 'File not found') !== false) {
        return 'System data file is missing. Please contact an administrator.';
    } elseif (strpos($message, 'Unable to acquire lock') !== false) {
        return 'System is busy. Please try again in a moment.';
    } else {
        return 'An unexpected error occurred. Please try again.';
    }
}

/**
 * Set custom error handler
 */
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log PHP errors
    logError("PHP Error", [
        'error_code' => $errno,
        'error_string' => $errstr,
        'file' => $errfile,
        'line' => $errline,
    ]);
    
    // Don't execute default PHP error handler
    return true;
});

/**
 * Set exception handler
 */
set_exception_handler(function($e) {
    logError("Uncaught Exception", [
        'exception_class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    
    // Display user-friendly message
    showError(
        'Something went wrong',
        getErrorMessage($e),
        $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage()
    );
    
    exit;
});

/**
 * Initialize logging on script start
 */
initializeErrorLogging();

?>
