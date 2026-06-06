
<?php
require_once 'tasks_mysql.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/simple_auth/middleware.php';
require_once __DIR__ . '/audit_handler.php';

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

  $changes = [];
  foreach ($fields as $field => $newValue) {
    $oldValue = $taskToEdit[$field] ?? null;
    if ((string) $oldValue !== (string) $newValue) {
      $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
    }
  }

  $updateOk = update_task_mysql($taskToEdit['id'], $fields);
  if (!$updateOk) {
    logAuditAction(
      'update',
      'task',
      (string) $taskToEdit['id'],
      $changes,
      'Task edit failed',
      'failed',
      'update_task_mysql returned false'
    );
    header('Location: tasks.php?error=invalid_request');
    exit;
  }

  logAuditAction(
    'update',
    'task',
    (string) $taskToEdit['id'],
    $changes,
    empty($changes) ? 'Task edit submitted with no field changes' : 'Task edited',
    'success',
    null
  );

    header('Location: tasks.php?success=updated');
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
    <option value="not_started" <?= $taskToEdit['status'] === 'not_started' ? 'selected' : '' ?>>Not Started</option>
    <option value="in_progress" <?= $taskToEdit['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
    <option value="waiting" <?= $taskToEdit['status'] === 'waiting' ? 'selected' : '' ?>>Waiting/Blocked</option>
    <option value="review" <?= $taskToEdit['status'] === 'review' ? 'selected' : '' ?>>Review</option>
    <option value="completed" <?= $taskToEdit['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
    <option value="archived" <?= $taskToEdit['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
  </select>
  <button type="submit">Update Task</button>
</form>
