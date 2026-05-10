<?php
require_once 'db_mysql.php';
require_once 'csrf_helper.php';
require_once 'simple_auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contracts_list.php?error=' . urlencode('Invalid request method'));
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: contracts_list.php?error=csrf');
    exit;
}

$contractId = trim($_POST['contract_id'] ?? '');
if ($contractId === '') {
    header('Location: contracts_list.php?error=' . urlencode('No contract ID provided'));
    exit;
}

$conn = get_mysql_connection();

$stmt = $conn->prepare('DELETE FROM contracts WHERE contract_id = ?');
if (!$stmt) {
    $conn->close();
    header('Location: contracts_list.php?error=' . urlencode('Failed to prepare statement'));
    exit;
}
$stmt->bind_param('s', $contractId);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

if ($ok && $affected > 0) {
    header('Location: contracts_list.php?success=deleted');
    exit;
} elseif ($ok && $affected === 0) {
    header('Location: contracts_list.php?error=' . urlencode('Contract not found'));
    exit;
} else {
    header('Location: contracts_list.php?error=' . urlencode('Failed to delete contract'));
    exit;
}
