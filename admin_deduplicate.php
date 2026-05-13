<?php
require_once 'layout_start.php';
require_once 'admin_helper.php';
requireAdmin();

$pageTitle = 'Deduplicate Contacts';
$message = '';
$messageType = '';

// ---- Handle merge POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['merge'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } else {
        $keep_id    = (int)($_POST['keep_id'] ?? 0);
        $discard_id = (int)($_POST['discard_id'] ?? 0);
        if ($keep_id <= 0 || $discard_id <= 0 || $keep_id === $discard_id) {
            $message = 'Invalid contact selection.';
            $messageType = 'danger';
        } else {
            $conn = get_mysql_connection();
            // Re-point all related records to the kept contact
            $related = ['tasks' => 'contact_id', 'opportunities' => 'contact_id', 'discussion_log' => 'contact_id', 'audit_log' => 'entity_id'];
            foreach ($related as $table => $col) {
                if ($table === 'audit_log') {
                    $keep_str    = (string)$keep_id;
                    $discard_str = (string)$discard_id;
                    $s = $conn->prepare("UPDATE `$table` SET `$col` = ? WHERE `$col` = ? AND entity_type = 'contact'");
                    if ($s) { $s->bind_param('ss', $keep_str, $discard_str); $s->execute(); $s->close(); }
                } else {
                    $s = $conn->prepare("UPDATE `$table` SET `$col` = ? WHERE `$col` = ?");
                    if ($s) { $s->bind_param('ii', $keep_id, $discard_id); $s->execute(); $s->close(); }
                }
            }
            // Delete the discarded contact
            $s = $conn->prepare('DELETE FROM contacts WHERE contact_id = ?');
            if ($s) { $s->bind_param('i', $discard_id); $s->execute(); $s->close(); }
            logAuditAction('merge_contact', 'contact', (string)$keep_id, ['merged_from' => $discard_id], 'Contact merged: discard ' . $discard_id . ' → keep ' . $keep_id);
            $conn->close();
            $message = "Contact #$discard_id merged into #$keep_id and deleted.";
            $messageType = 'success';
        }
    }
}

$duplicates = findDuplicateEmails();
?>
<style>
.dup-group { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin-bottom:18px; }
.dup-group h3 { margin:0 0 12px; font-size:15px; color:#374151; }
.dup-table { width:100%; border-collapse:collapse; font-size:13px; }
.dup-table th { background:#f9fafb; padding:8px 10px; text-align:left; border-bottom:2px solid #e5e7eb; font-size:12px; text-transform:uppercase; color:#6b7280; }
.dup-table td { padding:8px 10px; border-bottom:1px solid #f3f4f6; vertical-align:top; }
.dup-table tr:last-child td { border-bottom:none; }
.radio-keep { accent-color:#16a34a; }
.radio-discard { accent-color:#dc2626; }
.merge-btn { background:#0099A8; color:#fff; border:none; padding:8px 18px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px; }
.merge-btn:hover { background:#007880; }
.badge-count { background:#dc2626; color:#fff; border-radius:999px; padding:2px 8px; font-size:12px; font-weight:700; }
</style>

<h2>Deduplicate Contacts</h2>
<p>Contacts sharing the same email address. Select which record to <strong style="color:#16a34a;">keep</strong> and which to <strong style="color:#dc2626;">discard</strong>. Related tasks, opportunities, and discussion logs are re-pointed to the kept record before the duplicate is deleted.</p>

<?php if ($message): ?>
  <div style="padding:12px 16px;border-radius:6px;margin-bottom:16px;background:<?= $messageType === 'success' ? '#d1fae5' : '#fee2e2' ?>;color:<?= $messageType === 'success' ? '#065f46' : '#991b1b' ?>;border:1px solid <?= $messageType === 'success' ? '#6ee7b7' : '#fca5a5' ?>;">
    <?= htmlspecialchars($message) ?>
  </div>
<?php endif; ?>

<?php if (empty($duplicates)): ?>
  <div style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:8px;padding:16px;">
    ✓ No duplicate email addresses found. All contacts have unique emails.
  </div>
<?php else: ?>
  <p><span class="badge-count"><?= count($duplicates) ?></span> duplicate email group<?= count($duplicates) !== 1 ? 's' : '' ?> found.</p>

  <?php foreach ($duplicates as $email => $contacts): ?>
    <div class="dup-group">
      <h3>📧 <?= htmlspecialchars($email) ?> <span style="color:#6b7280;font-weight:400;">(<?= count($contacts) ?> records)</span></h3>
      <form method="POST">
        <?= renderCSRFInput() ?>
        <input type="hidden" name="merge" value="1">
        <table class="dup-table">
          <thead>
            <tr>
              <th>Keep</th>
              <th>Discard</th>
              <th>ID</th>
              <th>Name</th>
              <th>Company</th>
              <th>Phone</th>
              <th>City</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($contacts as $i => $c): ?>
              <tr>
                <td style="text-align:center;"><input type="radio" class="radio-keep" name="keep_id" value="<?= (int)$c['contact_id'] ?>" <?= $i === 0 ? 'checked' : '' ?> required></td>
                <td style="text-align:center;"><input type="radio" class="radio-discard" name="discard_id" value="<?= (int)$c['contact_id'] ?>" <?= $i === 1 ? 'checked' : '' ?> required></td>
                <td>#<?= (int)$c['contact_id'] ?></td>
                <td><?= htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name'])) ?></td>
                <td><?= htmlspecialchars($c['company'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['city'] ?? '') ?></td>
                <td><?= htmlspecialchars(substr($c['created_at'] ?? '', 0, 10)) ?></td>
                <td><a href="contact_view.php?id=<?= (int)$c['contact_id'] ?>" target="_blank" style="color:#0099A8;font-size:12px;">View →</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="margin-top:12px;">
          <button type="submit" class="merge-btn" onclick="return confirm('Merge and permanently delete the discarded contact?');">Merge & Delete Duplicate</button>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php include_once 'layout_end.php'; ?>
