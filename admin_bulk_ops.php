<?php
require_once 'layout_start.php';
require_once 'admin_helper.php';
require_once __DIR__ . '/admin_sql_helper.php';
requireAdmin();

$pageTitle = 'Bulk Operations';

$action_result = '';
$contact_schema   = ['first_name','last_name','company','email','phone','city','province','postal_code','country','status','tags','notes'];
$opp_schema       = ['stage', 'probability'];
$task_schema      = ['status', 'priority', 'assigned_to'];
$schema = $contact_schema; // legacy alias used below
$conn = get_mysql_connection();

// Handle bulk delete contacts
if ($_POST && isset($_POST['bulk_delete'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_result = 'CSRF validation failed';
    } else {
        $ids_to_delete = array_map('intval', $_POST['delete_ids'] ?? []);
        if (empty($ids_to_delete)) {
            $action_result = 'No contacts selected';
        } else {
            $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
            $types = str_repeat('i', count($ids_to_delete));
            $stmt = $conn->prepare("DELETE FROM contacts WHERE contact_id IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param($types, ...$ids_to_delete);
                if ($stmt->execute()) {
                    $count_deleted = $stmt->affected_rows;
                    $action_result = "Successfully deleted $count_deleted contact(s)";
                    logAuditAction('delete', 'contact', 'bulk', ['ids' => $ids_to_delete], "Bulk deleted $count_deleted contacts");
                } else {
                    $action_result = 'Failed to delete contacts';
                }
                $stmt->close();
            } else {
                $action_result = 'Failed to prepare delete statement';
            }
        }
    }
}

// Handle bulk update
if ($_POST && isset($_POST['bulk_tag'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_result = 'CSRF validation failed';
    } else {
        $ids_to_update = array_map('intval', $_POST['tag_ids'] ?? []);
        $tag_field     = $_POST['tag_field'] ?? '';
        $tag_value     = $_POST['tag_value'] ?? '';

        // Whitelist the column name to prevent SQL injection
        $allowed_fields = $schema;
        if (empty($ids_to_update) || !in_array($tag_field, $allowed_fields, true)) {
            $action_result = 'Invalid parameters';
        } else {
            $placeholders = implode(',', array_fill(0, count($ids_to_update), '?'));
            $types  = 's' . str_repeat('i', count($ids_to_update));
            $params = array_merge([$tag_value], $ids_to_update);
            $stmt = $conn->prepare("UPDATE contacts SET `$tag_field` = ?, last_modified = NOW() WHERE contact_id IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $count_updated = $stmt->affected_rows;
                    $action_result = "Updated $count_updated contact(s)";
                    logAuditAction('update', 'contact', 'bulk', ['field' => $tag_field, 'value' => $tag_value], "Bulk updated $count_updated contacts: $tag_field");
                } else {
                    $action_result = 'Failed to update contacts';
                }
                $stmt->close();
            } else {
                $action_result = 'Failed to prepare update statement';
            }
        }
    }
}

// Handle bulk delete opportunities
if ($_POST && isset($_POST['bulk_delete_opps'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_result = 'CSRF validation failed';
    } else {
        $ids = array_map('intval', $_POST['opp_ids'] ?? []);
        if (empty($ids)) {
            $action_result = 'No opportunities selected';
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
          $oppIdCol = adminOpportunityIdColumn($conn);
          $conn->begin_transaction();
          try {
            if (adminTableHasColumn($conn, 'tasks', 'opportunity_id')) {
              $stmtTasks = $conn->prepare("UPDATE tasks SET opportunity_id = NULL WHERE opportunity_id IN ($ph)");
              if ($stmtTasks) {
                $stmtTasks->bind_param(str_repeat('i', count($ids)), ...$ids);
                $stmtTasks->execute();
                $stmtTasks->close();
              }
            }

            if (adminTableHasColumn($conn, 'discussion_log', 'linked_opportunity_id')) {
              foreach ($ids as $oppId) {
                $oppIdStr = (string) $oppId;
                $stmtDisc = $conn->prepare('UPDATE discussion_log SET linked_opportunity_id = NULL WHERE linked_opportunity_id = ?');
                if ($stmtDisc) {
                  $stmtDisc->bind_param('s', $oppIdStr);
                  $stmtDisc->execute();
                  $stmtDisc->close();
                }
              }
            }

            $stmtDelete = $conn->prepare("DELETE FROM opportunities WHERE {$oppIdCol} IN ($ph)");
            if (!$stmtDelete) {
              throw new RuntimeException('Delete statement prepare failed');
            }
            $stmtDelete->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmtDelete->execute();
            $deletedRows = (int) $stmtDelete->affected_rows;
            $stmtDelete->close();

            $conn->commit();
            $action_result = 'Deleted ' . $deletedRows . ' opportunity/ies';
            logAuditAction('delete', 'opportunity', 'bulk', ['ids' => $ids], 'Bulk deleted ' . $deletedRows . ' opportunities');
          } catch (Throwable $e) {
            $conn->rollback();
            $action_result = 'Delete failed';
          }
        }
    }
}

// Handle bulk update opportunities
if ($_POST && isset($_POST['bulk_update_opps'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_result = 'CSRF validation failed';
    } else {
        $ids   = array_map('intval', $_POST['opp_update_ids'] ?? []);
        $field = $_POST['opp_field'] ?? '';
        $value = $_POST['opp_value'] ?? '';
        if (empty($ids) || !in_array($field, $opp_schema, true)) {
            $action_result = 'Invalid parameters';
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $oppIdCol = adminOpportunityIdColumn($conn);
            $stmt = $conn->prepare("UPDATE opportunities SET `$field` = ? WHERE {$oppIdCol} IN ($ph)");
            if ($stmt) {
                $stmt->bind_param('s' . str_repeat('i', count($ids)), $value, ...$ids);
                if ($stmt->execute()) { $action_result = 'Updated ' . $stmt->affected_rows . ' opportunity/ies'; }
                else { $action_result = 'Update failed'; }
                $stmt->close();
            }
        }
    }
}

// Handle bulk delete tasks
if ($_POST && isset($_POST['bulk_delete_tasks'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_result = 'CSRF validation failed';
    } else {
        $ids = array_filter(array_map('trim', $_POST['task_ids'] ?? []));
        if (empty($ids)) {
            $action_result = 'No tasks selected';
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id IN ($ph)");
            if ($stmt) {
                $stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
                if ($stmt->execute()) { $action_result = 'Deleted ' . $stmt->affected_rows . ' task(s)'; }
                else { $action_result = 'Delete failed'; }
                $stmt->close();
            }
        }
    }
}

// Handle bulk update tasks
if ($_POST && isset($_POST['bulk_update_tasks'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_result = 'CSRF validation failed';
    } else {
        $ids   = array_filter(array_map('trim', $_POST['task_update_ids'] ?? []));
        $field = $_POST['task_field'] ?? '';
        $value = $_POST['task_value'] ?? '';
        if (empty($ids) || !in_array($field, $task_schema, true)) {
            $action_result = 'Invalid parameters';
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE tasks SET `$field` = ? WHERE id IN ($ph)");
            if ($stmt) {
                $stmt->bind_param('s' . str_repeat('s', count($ids)), $value, ...$ids);
                if ($stmt->execute()) { $action_result = 'Updated ' . $stmt->affected_rows . ' task(s)'; }
                else { $action_result = 'Update failed'; }
                $stmt->close();
            }
        }
    }
}

// Load opportunities and tasks for display
$opportunities = [];
$ro = $conn->query("SELECT opportunity_id, name, stage, value, probability FROM opportunities ORDER BY name LIMIT 100");
if ($ro) { while ($row = $ro->fetch_assoc()) { $opportunities[] = $row; } $ro->free(); }
$tasks = [];
$rt = $conn->query("SELECT id, title, status, priority, assigned_to, due_date FROM tasks WHERE status NOT IN ('archived') ORDER BY due_date IS NULL, due_date LIMIT 100");
if ($rt) { while ($row = $rt->fetch_assoc()) { $tasks[] = $row; } $rt->free(); }

// Load contact list for display (first 100)
$contacts = [];
$r = $conn->query("SELECT contact_id, first_name, last_name, company, email FROM contacts ORDER BY last_name, first_name LIMIT 100");
if ($r) { while ($row = $r->fetch_assoc()) { $contacts[] = $row; } $r->free(); }
$total_contacts = 0;
$tc = $conn->query("SELECT COUNT(*) AS n FROM contacts");
if ($tc) { $total_contacts = (int)$tc->fetch_assoc()['n']; $tc->free(); }
$conn->close();

?>

<style>
.bulk-section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
.bulk-section h3 { margin-top: 0; color: #333; border-bottom: 2px solid #0099A8; padding-bottom: 10px; }
.contact-select-table { width: 100%; margin-top: 15px; font-size: 13px; border-collapse: collapse; }
.contact-select-table th { background: #f5f5f5; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; }
.contact-select-table td { padding: 8px 10px; border-bottom: 1px solid #eee; }
.contact-select-table tr:hover { background: #f9f9f9; }
.select-all { margin: 10px 0; }
.bulk-actions { margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 6px; }
.btn-action { padding: 8px 16px; margin: 5px; font-weight: bold; border: none; border-radius: 4px; cursor: pointer; }
.btn-delete-bulk { background: #dc3545; color: white; }
.btn-delete-bulk:hover { background: #c82333; }
.btn-tag { background: #17a2b8; color: white; }
.btn-tag:hover { background: #138496; }
</style>

<h2>Bulk Operations</h2>
  <p><a href="admin_dashboard.php">← Back to Dashboard</a></p>

  <?php if ($action_result): ?>
    <div class="alert-<?= strpos($action_result, 'Failed') !== false ? 'danger' : 'success' ?>">
      <?= htmlspecialchars($action_result) ?>
    </div>
  <?php endif; ?>

  <div class="bulk-section">
    <h3>📋 Bulk Delete Contacts</h3>
    <p>Select contacts to delete. <strong>This action cannot be undone!</strong></p>
    
    <form method="POST">
      <?php renderCSRFInput(); ?>
      
      <div class="select-all">
        <label>
          <input type="checkbox" id="select-all-delete" onchange='document.querySelectorAll("input[name=" + "\"delete_ids[]\"" + "]").forEach(el => el.checked = this.checked)'>
          <strong>Select All Visible</strong>
        </label>
      </div>

      <table class="contact-select-table">
        <thead>
          <tr>
            <th style="width: 30px;"><input type="checkbox" id="select-all-header"></th>
            <th>Name</th>
            <th>Company</th>
            <th>Email</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($contacts, 0, 50) as $contact): ?>
            <tr>
              <td><input type="checkbox" name="delete_ids[]" value="<?= htmlspecialchars($contact['contact_id']) ?>"></td>
              <td><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></td>
              <td><?= htmlspecialchars($contact['company'] ?? '') ?></td>
              <td><?= htmlspecialchars($contact['email'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($total_contacts > 100): ?>
        <p style="color: #666; font-size: 12px;">Showing first 50 of <?= $total_contacts ?> contacts</p>
      <?php endif; ?>

      <div class="bulk-actions">
        <button type="submit" name="bulk_delete" class="btn-action btn-delete-bulk" onclick="return confirm('Delete selected contacts? This cannot be undone!')">
          🗑 Delete Selected
        </button>
      </div>
    </form>
  </div>

  <div class="bulk-section">
    <h3>🏷️ Bulk Update Field</h3>
    <p>Update a specific field for selected contacts.</p>
    
    <form method="POST">
      <?php renderCSRFInput(); ?>
      
      <div style="margin-bottom: 15px;">
        <label>Field to Update:</label>
        <select name="tag_field" required>
          <option value="">-- Select Field --</option>
          <?php foreach ($schema as $field): ?>
              <option value="<?= htmlspecialchars($field) ?>">
                <?= ucfirst(str_replace('_', ' ', $field)) ?>
              </option>
            <?php endforeach; ?>
        </select>
      </div>

      <div style="margin-bottom: 15px;">
        <label>New Value:</label>
        <input type="text" name="tag_value" placeholder="Enter new value" required>
      </div>

      <div class="select-all">
        <label>
          <input type="checkbox" id="select-all-tag" onchange='document.querySelectorAll("input[name=" + "\"tag_ids[]\"" + "]").forEach(el => el.checked = this.checked)'>
          <strong>Select All Visible</strong>
        </label>
      </div>

      <table class="contact-select-table">
        <thead>
          <tr>
            <th style="width: 30px;"><input type="checkbox"></th>
            <th>Name</th>
            <th>Company</th>
            <th>Current Value</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($contacts, 0, 50) as $contact): ?>
            <tr>
              <td><input type="checkbox" name="tag_ids[]" value="<?= htmlspecialchars($contact['contact_id']) ?>"></td>
              <td><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></td>
              <td><?= htmlspecialchars($contact['company'] ?? '') ?></td>
              <td>—</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="bulk-actions">
        <button type="submit" name="bulk_tag" class="btn-action btn-tag" onclick="return confirm('Update selected contacts?')">
          ✓ Update Selected
        </button>
      </div>
    </form>
  </div>

  <!-- OPPORTUNITIES -->
  <div class="bulk-section">
    <h3>💼 Bulk Delete Opportunities</h3>
    <form method="POST">
      <?php renderCSRFInput(); ?>
      <label><input type="checkbox" onchange="this.closest('form').querySelectorAll('input[name=\'opp_ids[]\']').forEach(el=>el.checked=this.checked)"> <strong>Select All</strong></label>
      <table class="contact-select-table" style="margin-top:10px;">
        <thead><tr><th style="width:30px;"></th><th>Name</th><th>Stage</th><th>Value</th><th>Probability</th></tr></thead>
        <tbody>
          <?php foreach ($opportunities as $o): ?>
          <tr>
            <td><input type="checkbox" name="opp_ids[]" value="<?= (int)$o['opportunity_id'] ?>"></td>
            <td><a href="edit_opportunity.php?id=<?= (int)$o['opportunity_id'] ?>" style="color:#0099A8;"><?= htmlspecialchars($o['name'] ?? '') ?></a></td>
            <td><?= htmlspecialchars($o['stage'] ?? '') ?></td>
            <td>$<?= number_format((float)($o['value'] ?? 0), 2) ?></td>
            <td><?= (int)($o['probability'] ?? 0) ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="bulk-actions">
        <button type="submit" name="bulk_delete_opps" class="btn-action btn-delete-bulk" onclick="return confirm('Delete selected opportunities?')">🗑 Delete Selected</button>
      </div>
    </form>
  </div>

  <div class="bulk-section">
    <h3>💼 Bulk Update Opportunities</h3>
    <form method="POST">
      <?php renderCSRFInput(); ?>
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
          <label>Field:</label>
          <select name="opp_field" required>
            <option value="">-- Select --</option>
            <?php foreach ($opp_schema as $f): ?>
              <option value="<?= $f ?>"><?= ucfirst(str_replace('_',' ',$f)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>New Value:</label><input type="text" name="opp_value" placeholder="e.g. Closed Won" required></div>
      </div>
      <label><input type="checkbox" onchange="this.closest('form').querySelectorAll('input[name=\'opp_update_ids[]\']').forEach(el=>el.checked=this.checked)"> <strong>Select All</strong></label>
      <table class="contact-select-table" style="margin-top:10px;">
        <thead><tr><th style="width:30px;"></th><th>Name</th><th>Stage</th><th>Value</th></tr></thead>
        <tbody>
          <?php foreach ($opportunities as $o): ?>
          <tr>
            <td><input type="checkbox" name="opp_update_ids[]" value="<?= (int)$o['opportunity_id'] ?>"></td>
            <td><?= htmlspecialchars($o['name'] ?? '') ?></td>
            <td><?= htmlspecialchars($o['stage'] ?? '') ?></td>
            <td>$<?= number_format((float)($o['value'] ?? 0), 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="bulk-actions">
        <button type="submit" name="bulk_update_opps" class="btn-action btn-tag" onclick="return confirm('Update selected opportunities?')">✓ Update Selected</button>
      </div>
    </form>
  </div>

  <!-- TASKS -->
  <div class="bulk-section">
    <h3>🗂️ Bulk Delete Tasks</h3>
    <form method="POST">
      <?php renderCSRFInput(); ?>
      <label><input type="checkbox" onchange="this.closest('form').querySelectorAll('input[name=\'task_ids[]\']').forEach(el=>el.checked=this.checked)"> <strong>Select All</strong></label>
      <table class="contact-select-table" style="margin-top:10px;">
        <thead><tr><th style="width:30px;"></th><th>Title</th><th>Status</th><th>Priority</th><th>Assignee</th><th>Due</th></tr></thead>
        <tbody>
          <?php foreach ($tasks as $t): ?>
          <tr>
            <td><input type="checkbox" name="task_ids[]" value="<?= htmlspecialchars($t['id']) ?>"></td>
            <td><?= htmlspecialchars($t['title'] ?? '') ?></td>
            <td><?= htmlspecialchars($t['status'] ?? '') ?></td>
            <td><?= htmlspecialchars($t['priority'] ?? '') ?></td>
            <td><?= htmlspecialchars($t['assigned_to'] ?? '') ?></td>
            <td><?= htmlspecialchars($t['due_date'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="bulk-actions">
        <button type="submit" name="bulk_delete_tasks" class="btn-action btn-delete-bulk" onclick="return confirm('Delete selected tasks?')">🗑 Delete Selected</button>
      </div>
    </form>
  </div>

  <div class="bulk-section">
    <h3>🗂️ Bulk Update Tasks</h3>
    <form method="POST">
      <?php renderCSRFInput(); ?>
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
          <label>Field:</label>
          <select name="task_field" required>
            <option value="">-- Select --</option>
            <?php foreach ($task_schema as $f): ?>
              <option value="<?= $f ?>"><?= ucfirst(str_replace('_',' ',$f)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>New Value:</label><input type="text" name="task_value" placeholder="e.g. completed" required></div>
      </div>
      <label><input type="checkbox" onchange="this.closest('form').querySelectorAll('input[name=\'task_update_ids[]\']').forEach(el=>el.checked=this.checked)"> <strong>Select All</strong></label>
      <table class="contact-select-table" style="margin-top:10px;">
        <thead><tr><th style="width:30px;"></th><th>Title</th><th>Status</th><th>Assignee</th><th>Due</th></tr></thead>
        <tbody>
          <?php foreach ($tasks as $t): ?>
          <tr>
            <td><input type="checkbox" name="task_update_ids[]" value="<?= htmlspecialchars($t['id']) ?>"></td>
            <td><?= htmlspecialchars($t['title'] ?? '') ?></td>
            <td><?= htmlspecialchars($t['status'] ?? '') ?></td>
            <td><?= htmlspecialchars($t['assigned_to'] ?? '') ?></td>
            <td><?= htmlspecialchars($t['due_date'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="bulk-actions">
        <button type="submit" name="bulk_update_tasks" class="btn-action btn-tag" onclick="return confirm('Update selected tasks?')">✓ Update Selected</button>
      </div>
    </form>
  </div>

<?php include_once 'layout_end.php'; ?>
