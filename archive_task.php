
<?php
require_once 'tasks_mysql.php';
require_once __DIR__ . '/request_guard.php';
require_once __DIR__ . '/audit_handler.php';

require_post_with_csrf();

$idToArchive = $_POST['id'] ?? '';
if (!$idToArchive) {
    logAuditAction('update', 'task', '', [], 'Task archive failed: missing id', 'failed', 'Missing id');
    echo 'error';
    exit;
}

$taskRows = fetch_tasks_mysql(['id' => $idToArchive]);
$task = $taskRows[0] ?? null;
if (!$task) {
    logAuditAction('update', 'task', (string) $idToArchive, [], 'Task archive failed: task not found', 'failed', 'Task not found');
    echo 'error';
    exit;
}

if (archive_task_mysql($idToArchive)) {
    logAuditAction(
        'update',
        'task',
        (string) $idToArchive,
        ['status' => ['old' => ($task['status'] ?? ''), 'new' => 'archived']],
        'Task archived',
        'success',
        null
    );
    echo 'success';
} else {
    logAuditAction(
        'update',
        'task',
        (string) $idToArchive,
        ['status' => ['old' => ($task['status'] ?? ''), 'new' => 'archived']],
        'Task archive failed',
        'failed',
        'archive_task_mysql returned false'
    );
    echo 'error';
}
?>
