<?php
require_once 'tasks_mysql.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/simple_auth/middleware.php';
require_once __DIR__ . '/audit_handler.php';

function redirectTaskStatus(string $status = 'updated', string $returnQuery = ''): void {
    $target = 'tasks.php';
    $params = [];

    if ($status !== '') {
        $params['success'] = $status;
    }

    if ($returnQuery !== '') {
        parse_str($returnQuery, $incoming);
        $allowedKeys = ['view', 'status', 'assignee'];
        foreach ($allowedKeys as $key) {
            if (isset($incoming[$key]) && is_scalar($incoming[$key])) {
                $params[$key] = (string) $incoming[$key];
            }
        }
    }

    $query = http_build_query($params);
    if ($query !== '') {
        $target .= '?' . $query;
    }

    header('Location: ' . $target);
    exit;
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod !== 'POST') {
    header('Location: tasks.php?error=invalid_request');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: tasks.php?error=csrf');
    exit;
}

$taskId = trim((string) ($_POST['id'] ?? ''));
$newStatus = trim((string) ($_POST['status'] ?? ''));
$returnQuery = trim((string) ($_POST['return_query'] ?? ''));
$allowedStatuses = ['not_started', 'in_progress', 'waiting', 'review', 'completed', 'archived'];

if ($taskId === '' || !in_array($newStatus, $allowedStatuses, true)) {
    header('Location: tasks.php?error=invalid_request');
    exit;
}

$tasks = fetch_tasks_mysql(['id' => $taskId]);
$task = $tasks[0] ?? null;
if (!$task) {
    header('Location: tasks.php?error=not_found');
    exit;
}

$oldStatus = trim((string) ($task['status'] ?? ''));
if ($oldStatus === $newStatus) {
    redirectTaskStatus('updated', $returnQuery);
}

$updateOk = update_task_mysql($taskId, ['status' => $newStatus]);
if (!$updateOk) {
    logAuditAction(
        'update',
        'task',
        $taskId,
        ['status' => ['old' => $oldStatus, 'new' => $newStatus]],
        'Inline task status update failed',
        'failed',
        'Database update returned false'
    );
    header('Location: tasks.php?error=invalid_request');
    exit;
}

logAuditAction(
    'update',
    'task',
    $taskId,
    ['status' => ['old' => $oldStatus, 'new' => $newStatus]],
    'Inline task status updated',
    'success',
    null
);

redirectTaskStatus('updated', $returnQuery);
