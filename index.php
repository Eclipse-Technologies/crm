<?php
$pageTitle = 'Task Calendar';
header('Content-Type: text/html; charset=UTF-8');

require_once 'layout_start.php';
require_once 'tasks_mysql.php';
// ...existing code...

$tasks = fetch_tasks_mysql();

$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : date('m');

$tasksByDate = [];
foreach ($tasks as $task) {
  if ($task['status'] !== 'archived' && !empty($task['due_date'])) {
    if ($statusFilter === '' || $task['status'] === $statusFilter) {
      $tasksByDate[$task['due_date']][] = $task;
    }
  }
}

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
  $prevMonth = 12;
  $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
  $nextMonth = 1;
  $nextYear++;
}

// Place test marker at the very end
// (HTML will be output after the PHP block below)

?>
<div class="calendar-main-wrapper" style="max-width:1100px;margin:40px auto;padding:0 20px;">
<style>
  .calendar-main-wrapper {
    background: #f9fafb;
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    padding: 32px 24px 24px 24px;
  }
  .calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
  }
  .calendar-title {
    font-size: 2.2em;
    font-weight: 700;
    color: #222;
    margin-bottom: 0;
  }
  .calendar-nav {
    display: flex;
    gap: 16px;
    align-items: center;
  }
  .calendar-nav a {
    padding: 8px 18px;
    background: #fff;
    border-radius: 8px;
    color: #0099A8;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid #e5e7eb;
    transition: background 0.2s;
  }
  .calendar-nav a:hover {
    background: #e5e7eb;
  }
  .calendar-login {
    margin-left: auto;
  }
  .calendar-login a {
    padding: 10px 24px;
    background: linear-gradient(135deg, #0099A8 0%, #007489 100%);
    color: #fff;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,153,168,0.08);
    transition: background 0.2s;
  }
  .calendar-login a:hover {
    background: #007489;
  }
  .calendar-filter-form {
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    align-items: center;
  }
  .calendar-filter-form label {
    font-weight: 500;
    color: #333;
  }
  .calendar-filter-form select {
    padding: 8px 14px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    font-size: 15px;
    background: #fff;
    color: #222;
  }
  .calendar-add-form {
    margin-bottom: 32px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    padding: 24px 18px;
    display: flex;
    gap: 16px;
    align-items: center;
  }
  .calendar-add-form input,
  .calendar-add-form select {
    padding: 10px 14px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    font-size: 15px;
    background: #f9fafb;
    color: #222;
    margin-right: 8px;
  }
  .calendar-add-form button {
    padding: 10px 24px;
    border-radius: 6px;
    background: linear-gradient(135deg, #0099A8 0%, #007489 100%);
    color: #fff;
    font-weight: 600;
    border: none;
    box-shadow: 0 2px 8px rgba(0,153,168,0.08);
    transition: background 0.2s;
  }
  .calendar-add-form button:hover {
    background: #007489;
  }
  table {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    margin-bottom: 24px;
  }
  th, td {
    border: 1px solid #e5e7eb;
    text-align: center;
    padding: 18px;
    vertical-align: top;
    word-wrap: break-word;
    font-size: 16px;
  }
  th {
    background-color: #f3f4f6;
    font-weight: bold;
    color: #0099A8;
    font-size: 17px;
  }
  .has-task {
    background-color: #ffeeba;
    cursor: pointer;
    position: relative;
    border: 2px solid #ffc107;
  }
  .task-popup {
    display: none;
    position: absolute;
    background: #fff;
    border: 1px solid #e5e7eb;
    padding: 10px;
    z-index: 100;
    top: 100%;
    left: 0;
    width: 250px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 8px;
  }
</style>

<div class="calendar-header">
  <div class="calendar-title">Task Calendar</div>
  <div class="calendar-nav">
    <a href="?year=<?= $prevYear ?>&month=<?= str_pad($prevMonth, 2, '0', STR_PAD_LEFT) ?>">← Previous</a>
    <strong style="font-size:1.2em; color:#222;"><?= date('F Y', strtotime("$year-$month-01")) ?></strong>
    <a href="?year=<?= $nextYear ?>&month=<?= str_pad($nextMonth, 2, '0', STR_PAD_LEFT) ?>">Next →</a>
  </div>
  <div class="calendar-login">
    <a href="/simple_auth/login.php">Login</a>
  </div>
</div>

<form method="GET" class="calendar-filter-form">
  <label for="status">Filter by status:</label>
  <select name="status" id="status" onchange="this.form.submit()">
    <option value="">All</option>
    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
  </select>
  <input type="hidden" name="year" value="<?= $year ?>">
  <input type="hidden" name="month" value="<?= $month ?>">
</form>

<form method="POST" action="add_task.php" class="calendar-add-form" onsubmit="return validateForm()">
  <div style="background:#ff0;color:#000;padding:8px 12px;margin-bottom:10px;font-weight:bold;">DEBUG: index.php add task form loaded (2026-04-27)</div>
  <input type="text" name="title" id="title" placeholder="Task Title" required>
  <input type="date" name="due_date" id="due_date" required>
  <label for="task_status" style="margin-bottom:0;">Status:</label>
  <select name="status" id="task_status" required>
    <option value="not_started">Not Started</option>
    <option value="in_progress">In Progress</option>
    <option value="waiting">Waiting/Blocked</option>
    <option value="review">Review</option>
    <option value="completed">Completed</option>
    <option value="archived">Archived</option>
  </select>
  <button type="submit">Add Task</button>
</form>

<table>
  <tr>
    <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
  </tr>
<?php
$daysInMonth = date('t', strtotime("$year-$month-01"));
$firstDayOfMonth = date('w', strtotime("$year-$month-01"));

$day = 1;
$week = [];

for ($i = 0; $i < $firstDayOfMonth; $i++) {
    $week[] = "<td></td>";
}


while ($day <= $daysInMonth) {
  $dateStr = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
  if (isset($tasksByDate[$dateStr])) {
    $taskBlocks = '';
    foreach ($tasksByDate[$dateStr] as $task) {
      // Color by status
      $status = $task['status'];
      $color = '#ffeeba'; // default
      if ($status === 'pending') $color = '#fff3cd';
      elseif ($status === 'completed') $color = '#d4edda';
      elseif ($status === 'archived') $color = '#f8d7da';
      elseif ($status === 'incomplete') $color = '#fbeee6';
      $taskBlocks .= "<div style='background:$color;padding:4px 8px;margin-bottom:4px;border-radius:5px;font-weight:500;'>" . htmlspecialchars($task['title']) . "</div>";
    }
    $week[] = "<td class='has-task' onclick=\"showTasks('$dateStr')\">$day<div id='popup-$dateStr' class='task-popup'>$taskBlocks</div></td>";
  } else {
    $week[] = "<td>$day</td>";
  }

  if (count($week) == 7) {
    echo "<tr>" . implode('', $week) . "</tr>";
    $week = [];
  }

  $day++;
}


    if (count($week) == 7) {
        echo "<tr>" . implode('', $week) . "</tr>";
        $week = [];
    }

    $day++;

if (!empty($week)) {
    while (count($week) < 7) {
        $week[] = "<td></td>";
    }
    echo "<tr>" . implode('', $week) . "</tr>";
}
?>
</table>
</div>

<script>
function showTasks(date) {
  var popup = document.getElementById('popup-' + date);
  if (popup.style.display === 'block') {
    popup.style.display = 'none';
  } else {
    document.querySelectorAll('.task-popup').forEach(p => p.style.display = 'none');
    popup.style.display = 'block';
  }
}

function validateForm() {
  const title = document.getElementById('title').value.trim();
  const dueDate = document.getElementById('due_date').value;
  const status = document.getElementById('task_status').value;

  if (title === '') {
    alert('Please enter a task title.');
    return false;
  }

  if (dueDate === '') {
    alert('Please select a due date.');
    return false;
  }

  // All statuses are valid, but still check for empty
  if (!status) {
    alert('Please select a status.');
    return false;
  }

  return true;
}
</script>

<?php include 'layout_end.php'; ?>

<!-- TEST MARKER: If you see this, HTML is rendering after PHP. -->
<div style="background:#cfc;padding:20px;margin:20px 0;font-size:1.5em;">INDEX.PHP HTML OUTPUT TEST</div>
