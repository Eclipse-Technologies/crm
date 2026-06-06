<?php
require_once 'tasks_mysql.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/simple_auth/middleware.php';
require_once __DIR__ . '/audit_handler.php';

function redirect_after_delete(string $result = 'deleted', string $returnQuery = ''): void {
    $target = 'tasks.php';
    $params = [];

    if ($result !== '') {
        $params['success'] = $result;
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
    header('Location: tasks.php?error=invalid_request');
    exit;
}

$id = trim((string) ($_POST['id'] ?? ''));
$returnQuery = trim((string) ($_POST['return_query'] ?? ''));
if ($id !== '') {
    $existing = fetch_tasks_mysql(['id' => $id]);
    $task = $existing[0] ?? null;

    if (!$task) {
        logAuditAction(
            'delete',
            'task',
            $id,
            [],
            'Task delete failed: task not found',
            'failed',
            'Task not found'
        );
        header('Location: tasks.php?error=not_found');
        exit;
    }

    $deleteOk = delete_task_mysql($id);
    if (!$deleteOk) {
        logAuditAction(
            'delete',
            'task',
            $id,
            [],
            'Task delete failed',
            'failed',
            'delete_task_mysql returned false'
        );
        header('Location: tasks.php?error=invalid_request');
        exit;
    }

    $changes = [];
    foreach ($task as $field => $value) {
        if ($field === 'id') {
            continue;
        }
        $changes[$field] = ['old' => $value, 'new' => null];
    }

    logAuditAction(
        'delete',
        'task',
        $id,
        $changes,
        'Task deleted',
        'success',
        null
    );
}

redirect_after_delete('deleted', $returnQuery);
