<?php
require_once 'tasks_pgsql.php';
require_once __DIR__ . '/request_guard.php';

require_post_with_csrf();

$title = $_POST['title'] ?? '';
if (trim($title) === '') {
    echo 'error: empty title';
    exit;
}

$id = uniqid('task_', true);
$status = 'incomplete';
$priority = '';
$assigned_to = '';
$due_date = '';
$timestamp = date('Y-m-d H:i:s');

$task = [
    'id' => $id,
    'title' => $title,
    'status' => $status,
    'priority' => $priority,
    'assigned_to' => $assigned_to,
    'due_date' => $due_date,
    'timestamp' => $timestamp
];
if (insert_task_pgsql($task)) {
    echo 'success';
} else {
    echo 'error: could not insert task';
}
?>