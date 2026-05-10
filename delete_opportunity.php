<?php
// Migrate to MySQL: Delete opportunity by ID
require_once 'db_mysql.php';
require_once 'csrf_helper.php';
require_once 'simple_auth/middleware.php';

function getOpportunityIdColumn(mysqli $conn): string {
    $hasOpportunityId = false;
    $hasId = false;
    if ($result = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'opportunity_id'")) {
        $hasOpportunityId = $result->num_rows > 0;
        $result->free();
    }
    if ($result = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'id'")) {
        $hasId = $result->num_rows > 0;
        $result->free();
    }
    if ($hasOpportunityId) {
        return 'opportunity_id';
    }
    return $hasId ? 'id' : 'opportunity_id';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: opportunities_list.php?error=' . urlencode('Invalid request method'));
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: opportunities_list.php?error=csrf');
    exit;
}

$idToDelete = $_POST['id'] ?? null;

if (!$idToDelete) {
    header('Location: opportunities_list.php?error=' . urlencode('No opportunity ID provided'));
    exit;
}

// Use the correct MySQL connection function
$conn = get_mysql_connection();
if (!$conn) {
    header('Location: opportunities_list.php?error=' . urlencode('Database connection failed'));
    exit;
}

$idColumn = getOpportunityIdColumn($conn);

$stmt = $conn->prepare("DELETE FROM opportunities WHERE {$idColumn} = ?");
if (!$stmt) {
    header('Location: opportunities_list.php?error=' . urlencode('Failed to prepare statement'));
    $conn->close();
    exit;
}
$stmt->bind_param('s', $idToDelete);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $stmt->close();
        $conn->close();
        header('Location: opportunities_list.php?success=3');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: opportunities_list.php?error=' . urlencode('Opportunity not found'));
        exit;
    }
} else {
    $stmt->close();
    $conn->close();
    header('Location: opportunities_list.php?error=' . urlencode('Failed to delete opportunity'));
    exit;
}
