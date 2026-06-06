<?php
require_once 'tasks_mysql.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/simple_auth/middleware.php';

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
    delete_task_mysql($id);
}

redirect_after_delete('deleted', $returnQuery);
