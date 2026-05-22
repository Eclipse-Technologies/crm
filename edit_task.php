
<?php
require_once 'tasks_mysql.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/simple_auth/middleware.php';

$id = trim((string)($_GET['id'] ?? ''));
$timestamp = trim((string)($_GET['timestamp'] ?? ''));

if ($id !== '') {
  $tasks = fetch_tasks_mysql(['id' => $id]);
} else {
  $tasks = fetch_tasks_mysql(['timestamp' => $timestamp]);
}

$taskToEdit = $tasks ? $tasks[0] : null;
if (!$taskToEdit) {
    echo "Task not found.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo "CSRF validation failed.";
        exit;
    }
    
    $fields = [
        'title' => $_POST['title'],
        'due_date' => $_POST['due_date'],
        'status' => $_POST['status'],
        'timestamp' => $_POST['timestamp']
    ];
    update_task_mysql($taskToEdit['id'], $fields);
    header('Location: index.php');
    exit;
}
?>

<h3>Edit Task</h3>
<form method="POST">
  <?php renderCSRFInput(); ?>
  <input type="hidden" name="timestamp" value="<?= htmlspecialchars($taskToEdit['timestamp']) ?>">
  <input type="text" name="title" value="<?= htmlspecialchars($taskToEdit['title']) ?>" required>
  <input type="date" name="due_date" value="<?= htmlspecialchars($taskToEdit['due_date']) ?>" required>
  <select name="status" required>
    <option value="pending" <?= $taskToEdit['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
    <option value="completed" <?= $taskToEdit['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
  </select>
  <button type="submit">Update Task</button>
</form>
