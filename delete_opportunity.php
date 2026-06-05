<?php
// Migrate to MySQL: Delete opportunity by ID
require_once 'db_mysql.php';
require_once 'csrf_helper.php';
require_once 'simple_auth/middleware.php';
require_once __DIR__ . '/admin_sql_helper.php';

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

$idColumn = adminOpportunityIdColumn($conn);
$conn->begin_transaction();
try {
    if (adminTableHasColumn($conn, 'tasks', 'opportunity_id')) {
        $stmtTasks = $conn->prepare('UPDATE tasks SET opportunity_id = NULL WHERE opportunity_id = ?');
        if ($stmtTasks) {
            $oppIdInt = (int) $idToDelete;
            $stmtTasks->bind_param('i', $oppIdInt);
            $stmtTasks->execute();
            $stmtTasks->close();
        }
    }

    if (adminTableHasColumn($conn, 'discussion_log', 'linked_opportunity_id')) {
        $stmtDisc = $conn->prepare('UPDATE discussion_log SET linked_opportunity_id = NULL WHERE linked_opportunity_id = ?');
        if ($stmtDisc) {
            $stmtDisc->bind_param('s', $idToDelete);
            $stmtDisc->execute();
            $stmtDisc->close();
        }
    }

    $stmtDelete = $conn->prepare("DELETE FROM opportunities WHERE {$idColumn} = ?");
    if (!$stmtDelete) {
        throw new RuntimeException('Failed to prepare delete statement');
    }

    $stmtDelete->bind_param('s', $idToDelete);
    $stmtDelete->execute();
    $affected = $stmtDelete->affected_rows;
    $stmtDelete->close();

    $conn->commit();
    $conn->close();

    if ($affected > 0) {
        header('Location: opportunities_list.php?success=3');
        exit;
    }

    header('Location: opportunities_list.php?error=' . urlencode('Opportunity not found'));
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    header('Location: opportunities_list.php?error=' . urlencode('Failed to delete opportunity'));
    exit;
}
