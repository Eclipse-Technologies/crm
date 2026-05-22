
<?php
require_once 'tasks_mysql.php';
require_once __DIR__ . '/request_guard.php';

require_post_with_csrf();

$idToArchive = $_POST['id'] ?? '';
if (!$idToArchive) {
    echo 'error';
    exit;
}

if (archive_task_mysql($idToArchive)) {
    echo 'success';
} else {
    echo 'error';
}
?>
