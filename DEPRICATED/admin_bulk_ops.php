<?php
require_once 'layout_start.php';
require_once 'admin_helper.php';
requireAdmin();

$pageTitle = 'Bulk Operations';

$action_result = '';
$schema = require __DIR__ . '/contact_schema.php';
$contacts = readCSV('contacts.csv');

// Handle bulk delete
if ($_POST && isset($_POST['bulk_delete'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_result = 'CSRF validation failed';
    } else {
        $ids_to_delete = $_POST['delete_ids'] ?? [];
        if (!is_array($ids_to_delete) || empty($ids_to_delete)) {
            $action_result = 'No contacts selected';
        } else {
            $filtered = array_filter($contacts, fn($c) => !in_array($c['id'], $ids_to_delete));
            if (writeCSV('contacts.csv', array_values($filtered), $schema)) {
                $count_deleted = count($ids_to_delete);
                $action_result = "Successfully deleted $count_deleted contact(s)";
                logInfo("Bulk deleted $count_deleted contacts", ['ids' => $ids_to_delete]);
                $contacts = readCSV('contacts.csv');
            } else {
                $action_result = 'Failed to delete contacts';
            }
        }
    }
}

// Handle bulk tag
if ($_POST && isset($_POST['bulk_tag'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_result = 'CSRF validation failed';
    } else {
        $ids_to_tag = $_POST['tag_ids'] ?? [];
        $tag_field = $_POST['tag_field'] ?? '';
        $tag_value = $_POST['tag_value'] ?? '';
        
        if (empty($ids_to_tag) || empty($tag_field)) {
            $action_result = 'Invalid parameters';
        } else {
            $updated_count = 0;
            foreach ($contacts as &$c) {
                if (in_array($c['id'], $ids_to_tag)) {
                    $c[$tag_field] = $tag_value;
                    $updated_count++;
                }
            }
            if (writeCSV('contacts.csv', $contacts, $schema)) {
                $action_result = "Updated $updated_count contact(s)";
                logInfo("Bulk tagged $updated_count contacts", ['field' => $tag_field, 'value' => $tag_value]);
                $contacts = readCSV('contacts.csv');
            } else {
                $action_result = 'Failed to update contacts';
            }
        }
    }
}

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

<div class="main-content" id="mainContent">
  <div class="content-container">
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
      <?php echo renderCSRFInput(); ?>
      
      <div class="select-all">
        <label>
          <input type="checkbox" id="select-all-delete" onchange="document.querySelectorAll('input[name=\"delete_ids[]\"]').forEach(el => el.checked = this.checked)">
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
              <td><input type="checkbox" name="delete_ids[]" value="<?= htmlspecialchars($contact['id']) ?>"></td>
              <td><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></td>
              <td><?= htmlspecialchars($contact['company'] ?? '') ?></td>
              <td><?= htmlspecialchars($contact['email'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (count($contacts) > 50): ?>
        <p style="color: #666; font-size: 12px;">Showing first 50 of <?= count($contacts) ?> contacts</p>
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
      <?php echo renderCSRFInput(); ?>
      
      <div style="margin-bottom: 15px;">
        <label>Field to Update:</label>
        <select name="tag_field" required>
          <option value="">-- Select Field --</option>
          <?php foreach ($schema as $field): ?>
            <?php if (!in_array($field, ['id', 'created_at'])): ?>
              <option value="<?= htmlspecialchars($field) ?>">
                <?= ucfirst(str_replace('_', ' ', $field)) ?>
              </option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="margin-bottom: 15px;">
        <label>New Value:</label>
        <input type="text" name="tag_value" placeholder="Enter new value" required>
      </div>

      <div class="select-all">
        <label>
          <input type="checkbox" id="select-all-tag" onchange="document.querySelectorAll('input[name=\"tag_ids[]\"]').forEach(el => el.checked = this.checked)">
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
              <td><input type="checkbox" name="tag_ids[]" value="<?= htmlspecialchars($contact['id']) ?>"></td>
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

  </div>
</div>

<?php include_once 'layout_end.php'; ?>
