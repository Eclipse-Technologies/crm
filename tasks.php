<?php
// tasks.php - Modern CRM Task Management Page
require_once 'layout_start.php';
require_once 'tasks_mysql.php';

function normalizeTaskValue($value): string {
  return strtolower(trim((string) $value));
}

function isTaskOpenStatus(string $status): bool {
  return !in_array($status, ['completed', 'archived'], true);
}

function isTaskAssignedToUser(array $task, string $identity): bool {
  if ($identity === '') {
    return false;
  }

  $assigned = normalizeTaskValue($task['assigned_to'] ?? '');
  return $assigned !== '' && $assigned === $identity;
}

// Fetch tasks from DB
$tasks = fetch_tasks_mysql();

$currentUserIdentity = normalizeTaskValue($_SESSION['username'] ?? ($_SESSION['user_id'] ?? ''));
$allowedViews = ['all', 'open', 'my_open'];
$allowedStatuses = ['all', 'not_started', 'in_progress', 'waiting', 'review', 'completed', 'archived'];
$defaultView = $currentUserIdentity !== '' ? 'my_open' : 'all';

if (isset($_GET['reset_filters']) && $_GET['reset_filters'] === '1') {
  unset($_SESSION['tasks_filter_view'], $_SESSION['tasks_filter_status'], $_SESSION['tasks_filter_assignee']);
}

$hasExplicitFilterInput = isset($_GET['view']) || isset($_GET['status']) || isset($_GET['assignee']);

if ($hasExplicitFilterInput) {
  $requestedView = trim((string) ($_GET['view'] ?? $defaultView));
  $requestedStatus = trim((string) ($_GET['status'] ?? 'all'));
  $requestedAssignee = trim((string) ($_GET['assignee'] ?? ''));

  $viewFilter = in_array($requestedView, $allowedViews, true) ? $requestedView : $defaultView;
  $statusFilter = in_array($requestedStatus, $allowedStatuses, true) ? $requestedStatus : 'all';
  $assigneeFilter = $requestedAssignee;

  $_SESSION['tasks_filter_view'] = $viewFilter;
  $_SESSION['tasks_filter_status'] = $statusFilter;
  $_SESSION['tasks_filter_assignee'] = $assigneeFilter;
} else {
  $viewFilter = (string) ($_SESSION['tasks_filter_view'] ?? $defaultView);
  $statusFilter = (string) ($_SESSION['tasks_filter_status'] ?? 'all');
  $assigneeFilter = (string) ($_SESSION['tasks_filter_assignee'] ?? '');

  if (!in_array($viewFilter, $allowedViews, true)) {
    $viewFilter = $defaultView;
  }
  if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
  }
}

if ($viewFilter === 'my_open' && $currentUserIdentity === '') {
  $viewFilter = 'all';
}

$filteredTasks = [];
foreach ($tasks as $task) {
  $status = (string) ($task['status'] ?? '');

  if ($statusFilter !== 'all' && $statusFilter !== '' && $status !== $statusFilter) {
    continue;
  }

  if ($viewFilter === 'my_open' && !isTaskAssignedToUser($task, $currentUserIdentity)) {
    continue;
  }

  if ($viewFilter === 'open' && !isTaskOpenStatus($status)) {
    continue;
  }

  if ($assigneeFilter !== '' && normalizeTaskValue($task['assigned_to'] ?? '') !== normalizeTaskValue($assigneeFilter)) {
    continue;
  }

  $filteredTasks[] = $task;
}

$today = date('Y-m-d');
$summary = [
  'total' => count($filteredTasks),
  'my_open' => 0,
  'due_today' => 0,
  'overdue' => 0,
];

foreach ($filteredTasks as $task) {
  $status = (string) ($task['status'] ?? '');
  $dueDate = trim((string) ($task['due_date'] ?? ''));

  if (isTaskAssignedToUser($task, $currentUserIdentity) && isTaskOpenStatus($status)) {
    $summary['my_open']++;
  }

  if ($dueDate === $today && isTaskOpenStatus($status)) {
    $summary['due_today']++;
  }

  if ($dueDate !== '' && $dueDate < $today && isTaskOpenStatus($status)) {
    $summary['overdue']++;
  }
}

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
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0 20px 0;">
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;">
      <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.4px;">Visible Tasks</div>
      <div style="font-size:24px;font-weight:700;color:#111827;"><?= (int) $summary['total'] ?></div>
    </div>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;">
      <div style="font-size:12px;color:#166534;text-transform:uppercase;letter-spacing:0.4px;">My Open Tasks</div>
      <div style="font-size:24px;font-weight:700;color:#166534;"><?= (int) $summary['my_open'] ?></div>
    </div>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;">
      <div style="font-size:12px;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.4px;">Due Today</div>
      <div style="font-size:24px;font-weight:700;color:#1d4ed8;"><?= (int) $summary['due_today'] ?></div>
    </div>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;">
      <div style="font-size:12px;color:#b91c1c;text-transform:uppercase;letter-spacing:0.4px;">Overdue</div>
      <div style="font-size:24px;font-weight:700;color:#b91c1c;"><?= (int) $summary['overdue'] ?></div>
    </div>
  </div>

  <form method="GET" action="tasks.php" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;">
    <select name="view" style="padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
      <option value="all" <?= $viewFilter === 'all' ? 'selected' : '' ?>>All Tasks</option>
      <option value="open" <?= $viewFilter === 'open' ? 'selected' : '' ?>>Open Tasks</option>
      <option value="my_open" <?= $viewFilter === 'my_open' ? 'selected' : '' ?>>My Open Tasks</option>
    </select>

    <select name="status" style="padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
      <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
      <option value="not_started" <?= $statusFilter === 'not_started' ? 'selected' : '' ?>>Not Started</option>
      <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
      <option value="waiting" <?= $statusFilter === 'waiting' ? 'selected' : '' ?>>Waiting/Blocked</option>
      <option value="review" <?= $statusFilter === 'review' ? 'selected' : '' ?>>Review</option>
      <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
      <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
    </select>

    <input type="text" name="assignee" value="<?= htmlspecialchars($assigneeFilter) ?>" placeholder="Filter assignee" style="padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;min-width:180px;">

    <button type="submit" style="padding:8px 14px;border:none;border-radius:6px;background:#111827;color:#fff;font-weight:600;">Apply</button>
    <a href="tasks.php?reset_filters=1" style="padding:8px 14px;border-radius:6px;background:#f3f4f6;color:#111827;text-decoration:none;font-weight:600;">Reset</a>
  </form>

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
      <?php foreach ($filteredTasks as $task): ?>
        <tr>
          <td style="padding:10px 8px;"><?= htmlspecialchars($task['title']) ?></td>
          <td><?= status_badge($task['status']) ?></td>
          <td><?= htmlspecialchars($task['due_date']) ?></td>
          <td><?= htmlspecialchars($task['priority']) ?></td>
          <td><?= htmlspecialchars($task['assigned_to']) ?></td>
          <td><?= $task['contact_id'] ? '<a href="contact_view.php?id=' . intval($task['contact_id']) . '">' . intval($task['contact_id']) . '</a>' : '' ?></td>
          <td><?= $task['opportunity_id'] ? '<a href="edit_opportunity.php?id=' . intval($task['opportunity_id']) . '">' . intval($task['opportunity_id']) . '</a>' : '' ?></td>
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
