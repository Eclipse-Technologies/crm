<?php
require_once 'tasks_mysql.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/simple_auth/middleware.php';
require_once __DIR__ . '/audit_handler.php';

function isAjaxTaskStatusRequest(): bool {
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($requestedWith === 'xmlhttprequest') {
        return true;
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    return strpos($accept, 'application/json') !== false;
}

function sendTaskStatusJson(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function statusLabel(string $status): string {
    $labels = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'waiting' => 'Waiting/Blocked',
        'review' => 'Review',
        'completed' => 'Completed',
        'archived' => 'Archived',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function fetchLatestTaskAuditPreview(string $taskId): ?array {
    if ($taskId === '' || !function_exists('get_mysql_connection')) {
        return null;
    }

    try {
        $conn = get_mysql_connection();
        $sql = "SELECT timestamp, user_id, summary, status FROM audit_log WHERE entity_type = 'task' AND entity_id = ? ORDER BY timestamp DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $conn->close();
            return null;
        }

        $stmt->bind_param('s', $taskId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        if ($result) {
            $result->free();
        }
        $stmt->close();
        $conn->close();

        if (!is_array($row)) {
            return null;
        }

        return [
            'summary' => trim((string) ($row['summary'] ?? '')),
            'timestamp' => trim((string) ($row['timestamp'] ?? '')),
            'user_id' => trim((string) ($row['user_id'] ?? '')),
            'status' => trim((string) ($row['status'] ?? '')),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

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
$isAjax = isAjaxTaskStatusRequest();
if ($requestMethod !== 'POST') {
    if ($isAjax) {
        sendTaskStatusJson(['ok' => false, 'error' => 'invalid_request'], 405);
    }
    header('Location: tasks.php?error=invalid_request');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    if ($isAjax) {
        sendTaskStatusJson(['ok' => false, 'error' => 'csrf'], 403);
    }
    header('Location: tasks.php?error=csrf');
    exit;
}

$taskId = trim((string) ($_POST['id'] ?? ''));
$newStatus = trim((string) ($_POST['status'] ?? ''));
$returnQuery = trim((string) ($_POST['return_query'] ?? ''));
$allowedStatuses = ['not_started', 'in_progress', 'waiting', 'review', 'completed', 'archived'];

if ($taskId === '' || !in_array($newStatus, $allowedStatuses, true)) {
    if ($isAjax) {
        sendTaskStatusJson(['ok' => false, 'error' => 'invalid_request'], 422);
    }
    header('Location: tasks.php?error=invalid_request');
    exit;
}

$tasks = fetch_tasks_mysql(['id' => $taskId]);
$task = $tasks[0] ?? null;
if (!$task) {
    if ($isAjax) {
        sendTaskStatusJson(['ok' => false, 'error' => 'not_found'], 404);
    }
    header('Location: tasks.php?error=not_found');
    exit;
}

$oldStatus = trim((string) ($task['status'] ?? ''));
if ($oldStatus === $newStatus) {
    if ($isAjax) {
        $auditPreview = fetchLatestTaskAuditPreview($taskId);
        sendTaskStatusJson([
            'ok' => true,
            'task_id' => $taskId,
            'status' => $newStatus,
            'status_label' => statusLabel($newStatus),
            'changed' => false,
            'audit_preview' => $auditPreview,
        ]);
    }
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
    if ($isAjax) {
        sendTaskStatusJson(['ok' => false, 'error' => 'update_failed'], 500);
    }
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

if ($isAjax) {
    $auditPreview = fetchLatestTaskAuditPreview($taskId);
    sendTaskStatusJson([
        'ok' => true,
        'task_id' => $taskId,
        'status' => $newStatus,
        'status_label' => statusLabel($newStatus),
        'changed' => true,
        'audit_preview' => $auditPreview,
    ]);
}

redirectTaskStatus('updated', $returnQuery);
