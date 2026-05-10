<?php
// tasks.php - Modern CRM Task Management Page
require_once 'layout_start.php';
require_once 'tasks_mysql.php';

// Fetch tasks from DB
$tasks = fetch_tasks_mysql();

// Status badge colors
$statusColors = [
    'not_started' => '#f8d7da',
    'in_progress' => '#fff3cd',
    'waiting' => '#d1ecf1',
    'review' => '#d6d8d9',
    'completed' => '#d4edda',
    'archived' => '#e2e3e5',
];

function status_badge($status) {
    global $statusColors;
    $label = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'waiting' => 'Waiting/Blocked',
        'review' => 'Review',
        'completed' => 'Completed',
        'archived' => 'Archived',
    ];
    $color = $statusColors[$status] ?? '#eee';
    $text = $label[$status] ?? ucfirst($status);
    return "<span style='background:$color;padding:4px 10px;border-radius:12px;font-weight:600;font-size:0.95em;'>$text</span>";
}
?>

<div class="tasks-main-wrapper" style="max-width:1100px;margin:40px auto;padding:0 20px;">
  <h1 style="font-size:2.2em;font-weight:700;color:#222;">Tasks</h1>
  <div style="margin-bottom:24px;">
    <form method="POST" action="add_task.php" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <?php renderCSRFInput(); ?>
      <input type="text" name="title" placeholder="Task Title" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
      <input type="date" name="due_date" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
      <select name="status" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
        <option value="not_started">Not Started</option>
        <option value="in_progress">In Progress</option>
        <option value="waiting">Waiting/Blocked</option>
        <option value="review">Review</option>
        <option value="completed">Completed</option>
        <option value="archived">Archived</option>
      </select>
      <input type="text" name="priority" placeholder="Priority" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
      <input type="text" name="assigned_to" placeholder="Assignee" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
      <button type="submit" style="padding:8px 24px;border-radius:6px;background:#0099A8;color:#fff;font-weight:600;border:none;">Add Task</button>
    </form>
  </div>
  <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
    <thead>
      <tr style="background:#f3f4f6;">
        <th style="padding:14px;">Title</th>
        <th>Status</th>
        <th>Due Date</th>
        <th>Priority</th>
        <th>Assignee</th>
        <th>Contact</th>
        <th>Opportunity</th>
        <th>Project</th>
        <th>Description</th>
        <th>Comments</th>
        <th>Recurrence</th>
        <th>Attachment</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tasks as $task): ?>
        <tr>
          <td style="padding:10px 8px;"><?= htmlspecialchars($task['title']) ?></td>
          <td><?= status_badge($task['status']) ?></td>
          <td><?= htmlspecialchars($task['due_date']) ?></td>
          <td><?= htmlspecialchars($task['priority']) ?></td>
          <td><?= htmlspecialchars($task['assigned_to']) ?></td>
          <td><?= $task['contact_id'] ? intval($task['contact_id']) : '' ?></td>
          <td><?= $task['opportunity_id'] ? intval($task['opportunity_id']) : '' ?></td>
          <td><?= $task['project_id'] ? intval($task['project_id']) : '' ?></td>
          <td><?= htmlspecialchars($task['description'] ?? '') ?></td>
          <td><?= htmlspecialchars($task['comments'] ?? '') ?></td>
          <td><?= htmlspecialchars($task['recurrence'] ?? '') ?></td>
          <td>
            <?php if (!empty($task['attachment'])): ?>
              <a href="<?= htmlspecialchars($task['attachment']) ?>" target="_blank">View</a>
            <?php endif; ?>
          </td>
          <td>
            <a href="edit_task.php?id=<?= urlencode($task['id']) ?>" style="color:#007489;font-weight:600;">Edit</a> |
            <form method="POST" action="delete_task.php" style="display:inline;" onsubmit="return confirm('Delete this task?');">
              <?php renderCSRFInput(); ?>
              <input type="hidden" name="id" value="<?= htmlspecialchars($task['id']) ?>">
              <button type="submit" style="background:none;border:none;color:#c00;font-weight:600;cursor:pointer;padding:0;">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once 'layout_end.php'; ?>
