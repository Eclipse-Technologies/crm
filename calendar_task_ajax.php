require_once __DIR__ . '/task_action_log.php';
<?php
// AJAX handler for calendar task add/edit/fetch
require_once 'tasks_mysql.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $due_date = $_POST['due_date'] ?? '';
        $status = $_POST['status'] ?? 'not_started';
        $description = trim($_POST['description'] ?? '');
        $comments = trim($_POST['comments'] ?? '');
        $recurrence = trim($_POST['recurrence'] ?? '');
        $attachment = trim($_POST['attachment'] ?? '');
        $id = uniqid('task_', true);
        $priority = '';
        $assigned_to = '';
        $timestamp = date('Y-m-d H:i:s');
        $task = [
            'id' => $id,
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'assigned_to' => $assigned_to,
            'due_date' => $due_date,
            'timestamp' => $timestamp,
            'contact_id' => null,
            'opportunity_id' => null,
            'project_id' => null,
            'description' => $description,
            'comments' => $comments,
            'recurrence' => $recurrence,
            'attachment' => $attachment
        ];
        $ok = insert_task_mysql($task);
        echo json_encode(['success' => $ok]);
        exit;
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $fields = [
            'title' => trim($_POST['title'] ?? ''),
            'due_date' => $_POST['due_date'] ?? '',
            'status' => $_POST['status'] ?? 'not_started',
            'description' => trim($_POST['description'] ?? ''),
            'comments' => trim($_POST['comments'] ?? ''),
            'recurrence' => trim($_POST['recurrence'] ?? ''),
            'attachment' => trim($_POST['attachment'] ?? '')
        ];
        // Log only if due_date is being changed
        $tasks = fetch_tasks_mysql(['id' => $id]);
        $old_due = $tasks && isset($tasks[0]['due_date']) ? $tasks[0]['due_date'] : '';
        $new_due = $fields['due_date'];
        $user = '';
        if ($old_due && $new_due && $old_due !== $new_due) {
            log_task_action('move', $id, $old_due, $new_due, $user);
        }
        $ok = update_task_mysql($id, $fields);
        echo json_encode(['success' => $ok]);
        exit;
    }
} elseif ($method === 'GET') {
    $id = $_GET['id'] ?? '';
    if ($id) {
        $tasks = fetch_tasks_mysql(['id' => $id]);
        if ($tasks) {
            echo json_encode(['success' => true, 'task' => $tasks[0]]);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid request']);
