
<?php
require_once 'tasks_mysql.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/simple_auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Location: index.php?error=invalid_request');
        exit;
    }
    $title = trim($_POST['title']);
    $due_date = $_POST['due_date'];
    $status = $_POST['status'];
    $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
    $opportunity_id = isset($_POST['opportunity_id']) ? intval($_POST['opportunity_id']) : null;
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    $recurrence = isset($_POST['recurrence']) ? trim($_POST['recurrence']) : '';
    $attachment = isset($_POST['attachment']) ? trim($_POST['attachment']) : '';
    $priority = isset($_POST['priority']) ? trim($_POST['priority']) : '';
    $assigned_to = isset($_POST['assigned_to']) ? trim($_POST['assigned_to']) : '';

    $errors = [];
    if ($title === '') $errors[] = 'Task title is required.';
    if ($due_date === '') $errors[] = 'Due date is required.';
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) $errors[] = 'Invalid date format.';
    $valid_statuses = ['not_started', 'in_progress', 'waiting', 'review', 'completed', 'archived'];
    if (!in_array($status, $valid_statuses, true)) $errors[] = 'Invalid status selected.';

    if (!empty($errors)) {
        echo "<div style='color:red;'><strong>Error:</strong><ul>";
        foreach ($errors as $error) echo "<li>" . htmlspecialchars($error) . "</li>";
        echo "</ul></div><a href='index.php'>Go back</a>";
        exit;
    }

    $id = uniqid('task_', true);
    $timestamp = date('Y-m-d H:i:s');
    $task = [
        'id' => $id,
        'title' => $title,
        'status' => $status,
        'priority' => $priority,
        'assigned_to' => $assigned_to,
        'due_date' => $due_date,
        'timestamp' => $timestamp,
        'contact_id' => $contact_id,
        'opportunity_id' => $opportunity_id,
        'project_id' => $project_id,
        'description' => $description,
        'comments' => $comments,
        'recurrence' => $recurrence,
        'attachment' => $attachment
    ];
    insert_task_mysql($task);
    header('Location: index.php');
    exit;
}
?>

<form method="POST" action="" style="max-width:700px;margin:32px auto 0 auto;display:flex;flex-wrap:wrap;gap:18px 24px;align-items:center;background:#fff;padding:32px 28px 24px 28px;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
    <?php renderCSRFInput(); ?>
    <div style="flex:1 1 220px;min-width:220px;">
        <label style="font-weight:600;margin-bottom:4px;display:block;">Title
            <input type="text" name="title" required style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #ccc;margin-top:4px;">
        </label>
    </div>
    <div style="flex:1 1 180px;min-width:180px;">
        <label style="font-weight:600;margin-bottom:4px;display:block;">Due Date
            <input type="date" name="due_date" required style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #ccc;margin-top:4px;">
        </label>
    </div>
    <div style="flex:1 1 180px;min-width:180px;">
        <label style="font-weight:600;margin-bottom:4px;display:block;">Status
            <select name="status" id="task_status" required style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #ccc;margin-top:4px;">
                <option value="not_started">Not Started</option>
                <option value="in_progress">In Progress</option>
                <option value="waiting">Waiting/Blocked</option>
                <option value="review">Review</option>
                <option value="completed">Completed</option>
                <option value="archived">Archived</option>
            </select>
        </label>
    </div>
    <div style="flex:1 1 100%;min-width:320px;">
        <label style="font-weight:600;margin-bottom:4px;display:block;">Description
            <textarea name="description" rows="3" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #ccc;margin-top:4px;" placeholder="Task details..."></textarea>
        </label>
    </div>
    <div style="flex:1 1 100%;min-width:320px;">
        <label style="font-weight:600;margin-bottom:4px;display:block;">Comments
            <textarea name="comments" rows="2" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #ccc;margin-top:4px;" placeholder="Internal notes or comments..."></textarea>
        </label>
    </div>
    <div style="flex:1 1 180px;min-width:180px;">
        <label style="font-weight:600;margin-bottom:4px;display:block;">Recurrence
            <select name="recurrence" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #ccc;margin-top:4px;">
                <option value="">None</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
            </select>
        </label>
    </div>
    <div style="flex:1 1 220px;min-width:220px;">
        <label style="font-weight:600;margin-bottom:4px;display:block;">Attachment (URL or filename)
            <input type="text" name="attachment" placeholder="e.g. file.pdf or http://..." style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #ccc;margin-top:4px;">
        </label>
    </div>
    <?php if (isset($_GET['contact_id'])): ?>
        <input type="hidden" name="contact_id" value="<?= htmlspecialchars($_GET['contact_id']) ?>">
        <div style="flex:1 1 100%;color:#555;font-size:13px;">Linked to Contact ID: <?= htmlspecialchars($_GET['contact_id']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['opportunity_id'])): ?>
        <input type="hidden" name="opportunity_id" value="<?= htmlspecialchars($_GET['opportunity_id']) ?>">
        <div style="flex:1 1 100%;color:#555;font-size:13px;">Linked to Opportunity ID: <?= htmlspecialchars($_GET['opportunity_id']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['project_id'])): ?>
        <input type="hidden" name="project_id" value="<?= htmlspecialchars($_GET['project_id']) ?>">
        <div style="flex:1 1 100%;color:#555;font-size:13px;">Linked to Project ID: <?= htmlspecialchars($_GET['project_id']) ?></div>
    <?php endif; ?>
    <div style="flex:1 1 100%;text-align:right;margin-top:18px;">
        <button type="submit" style="padding:10px 32px;border-radius:6px;background:#0099A8;color:#fff;font-weight:600;border:none;font-size:1.1em;">Add Task</button>
    </div>
</form>
