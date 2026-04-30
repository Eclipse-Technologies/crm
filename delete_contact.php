<?php
// Start output buffering to prevent accidental output before header() calls
ob_start();

require_once 'layout_start.php';
require_once 'contact_validator.php';
require_once 'db_mysql.php';
require_once 'error_handler.php';

// ✅ CSRF Protection: Verify token on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        showError('CSRF validation failed', 'The CSRF token is missing or invalid. Please try again.');
        logWarning('CSRF token validation failed on delete_contact', ['ip' => $_SERVER['REMOTE_ADDR']]);
        exit;
    }
}

// Use contact_id for consistency
$idToDelete = $_POST['contact_id'] ?? null;
if (!$idToDelete) {
    showError('No contact ID provided', 'A contact ID must be specified to delete a contact.');
    exit;
}


$schema = require __DIR__ . '/contact_schema.php';
$deleted_contact = null;


try {
    $conn = get_mysql_connection();
    $conn->begin_transaction();
    // Find and capture contact before deletion (for audit log)
    $stmt = $conn->prepare("SELECT * FROM contacts WHERE contact_id = ? LIMIT 1");
    $stmt->bind_param('s', $idToDelete);
    $stmt->execute();
    $result = $stmt->get_result();
    $deleted_contact = $result->fetch_assoc();
    $stmt->close();

    if ($deleted_contact) {
        // Detach linked customer records first to satisfy FK fk_customers_contact_id.
        $unlinkStmt = $conn->prepare("UPDATE customers SET contact_id = NULL WHERE contact_id = ?");
        $unlinkStmt->bind_param('s', $idToDelete);
        if (!$unlinkStmt->execute()) {
            throw new Exception('Failed to unlink customer records: ' . $unlinkStmt->error);
        }
        $unlinkStmt->close();

        // Delete the contact
        $delStmt = $conn->prepare("DELETE FROM contacts WHERE contact_id = ?");
        $delStmt->bind_param('s', $idToDelete);
        if (!$delStmt->execute()) {
            throw new Exception('Failed to delete contact: ' . $delStmt->error);
        }
        $delStmt->close();

        auditDeleteContact($deleted_contact);
        logInfo('Contact deleted successfully', [
            'email' => $deleted_contact['email'],
            'name' => ($deleted_contact['first_name'] ?? '') . ' ' . ($deleted_contact['last_name'] ?? ''),
        ]);
    }
    $conn->commit();
    $conn->close();

    // Build redirect URL with all relevant params from POST
    $params = [];
    $paramsToPropagate = ['page','query','sort','direction','per_page','field'];
    foreach ($paramsToPropagate as $p) {
        if (!empty($_POST[$p])) {
            $params[$p] = $_POST[$p];
        }
    }
    if (!empty($_POST['display'])) {
        if (is_array($_POST['display'])) {
            foreach ($_POST['display'] as $df) {
                $params['display[]'][] = $df;
            }
        } else {
            $params['display[]'][] = $_POST['display'];
        }
    }
    $queryString = http_build_query($params);
    $redirectUrl = 'contacts_list.php' . ($queryString ? ('?' . $queryString) : '');
    if (!headers_sent()) {
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl) . '">';
        echo '<script>window.location.href="' . addslashes($redirectUrl) . '";</script>';
        echo '<p>If you are not redirected, <a href="' . htmlspecialchars($redirectUrl) . '">click here</a>.</p>';
        exit;
    }
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    showError('Error deleting contact', htmlspecialchars($e->getMessage()));
    logError('Contact deletion failed', [
        'id' => $idToDelete,
        'exception' => $e->getMessage(),
    ]);
    echo "<p><a href='contacts_list.php'>Go back</a></p>";
    exit;
}
?>

