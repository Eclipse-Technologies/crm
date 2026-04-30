
<?php
require_once 'tasks_mysql.php';

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
