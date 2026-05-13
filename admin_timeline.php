<?php
require_once 'layout_start.php';
require_once 'admin_helper.php';
requireAdmin();

$pageTitle = 'Contact Timeline';

$contact_id = trim($_GET['id'] ?? '');
$contact = null;

if ($contact_id !== '') {
    $conn = get_mysql_connection();
    $stmt = $conn->prepare('SELECT contact_id AS id, first_name, last_name, company, email, created_at FROM contacts WHERE contact_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $contact_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $contact = $result->fetch_assoc() ?: null;
        $result->free();
        $stmt->close();
    }
    $conn->close();
}

if (!$contact) {
    // Show search form if no ID given, or error if invalid ID was provided
    $searchResults = [];
    $searchQuery = trim($_GET['q'] ?? '');
    if ($searchQuery !== '') {
        $conn2 = get_mysql_connection();
        $like = '%' . $searchQuery . '%';
        $stmt2 = $conn2->prepare('SELECT contact_id, first_name, last_name, company, email FROM contacts WHERE first_name LIKE ? OR last_name LIKE ? OR company LIKE ? OR email LIKE ? ORDER BY last_name, first_name LIMIT 30');
        if ($stmt2) {
            $stmt2->bind_param('ssss', $like, $like, $like, $like);
            $stmt2->execute();
            $r2 = $stmt2->get_result();
            while ($row = $r2->fetch_assoc()) { $searchResults[] = $row; }
            $r2->free();
            $stmt2->close();
        }
        $conn2->close();
    }
    ?>
    <h2>Contact Timeline</h2>
    <p>Search for a contact to view their audit history.</p>
    <form method="GET" style="display:flex;gap:8px;align-items:center;margin-bottom:20px;">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Name, company, or email…" style="padding:8px 12px;border:1px solid #ccc;border-radius:4px;width:300px;">
        <button type="submit" style="padding:8px 16px;background:#0099A8;color:white;border:none;border-radius:4px;cursor:pointer;">Search</button>
    </form>
    <?php if ($searchQuery !== ''): ?>
        <?php if (empty($searchResults)): ?>
            <p style="color:#666;">No contacts found matching "<?= htmlspecialchars($searchQuery) ?>".</p>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;background:white;border-radius:6px;overflow:hidden;">
                <thead><tr style="background:#f5f5f5;">
                    <th style="padding:10px;text-align:left;">Name</th>
                    <th style="padding:10px;text-align:left;">Company</th>
                    <th style="padding:10px;text-align:left;">Email</th>
                    <th style="padding:10px;"></th>
                </tr></thead>
                <tbody>
                <?php foreach ($searchResults as $row): ?>
                    <tr style="border-top:1px solid #eee;">
                        <td style="padding:10px;"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td style="padding:10px;"><?= htmlspecialchars($row['company'] ?? '') ?></td>
                        <td style="padding:10px;"><?= htmlspecialchars($row['email'] ?? '') ?></td>
                        <td style="padding:10px;"><a href="admin_timeline.php?id=<?= urlencode($row['contact_id']) ?>" style="color:#0099A8;font-weight:bold;">View Timeline →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
    <?php
    include_once 'layout_end.php';
    exit;
}

$trail = getAuditTrail($contact_id);

?>

<style>
.timeline { margin: 30px 0; }
.timeline-item { margin-left: 50px; margin-bottom: 30px; position: relative; }
.timeline-dot { width: 20px; height: 20px; background: #0099A8; border-radius: 50%; position: absolute; left: -40px; top: 5px; }
.timeline-content { background: white; padding: 15px; border-radius: 6px; border-left: 3px solid #0099A8; }
.timeline-content h4 { margin: 0 0 8px 0; color: #0099A8; }
.timeline-content .action { font-weight: bold; font-size: 14px; }
.timeline-content .meta { font-size: 12px; color: #666; margin: 5px 0; }
.timeline-content .changes { font-size: 12px; background: #f5f5f5; padding: 8px; margin-top: 8px; border-radius: 3px; }
.timeline-content .change-item { margin: 4px 0; }
.change-added { color: #28a745; }
.change-removed { color: #dc3545; }
.change-modified { color: #0099A8; }
</style>

<h2>Contact Timeline: <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></h2>
  <p><a href="admin_dashboard.php">← Back to Dashboard</a> | <a href="contact_view.php?id=<?= urlencode($contact_id) ?>">View Contact →</a></p>

  <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3>Contact Details</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 13px;">
      <div><strong>ID:</strong> <?= htmlspecialchars($contact['id']) ?></div>
      <div><strong>Name:</strong> <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></div>
      <div><strong>Company:</strong> <?= htmlspecialchars($contact['company'] ?? 'N/A') ?></div>
      <div><strong>Email:</strong> <?= htmlspecialchars($contact['email'] ?? 'N/A') ?></div>
      <div><strong>Created:</strong> <?= htmlspecialchars($contact['created_at'] ?? 'Unknown') ?></div>
    </div>
  </div>

  <h3>Modification History</h3>
  <?php if (!empty($trail)): ?>
    <div class="timeline">
      <?php foreach ($trail as $event): ?>
        <?php 
        $changes = is_array($event['changes']) ? $event['changes'] : json_decode($event['changes'] ?? '{}', true);
        $status_color = ($event['status'] ?? 'unknown') === 'success' ? '#28a745' : '#dc3545';
        ?>
        <div class="timeline-item">
          <div class="timeline-dot" style="background-color: <?= $status_color ?>;"></div>
          <div class="timeline-content">
            <h4>
              <span class="action"><?= htmlspecialchars(strtoupper($event['action'] ?? 'unknown')) ?></span>
              <span style="color: <?= $status_color ?>; margin-left: 10px;">
                <?= htmlspecialchars($event['status'] ?? 'unknown') ?>
              </span>
            </h4>
            
            <div class="meta">
              <strong>When:</strong> <?= htmlspecialchars($event['timestamp'] ?? 'unknown') ?><br>
              <strong>Who:</strong> <?= htmlspecialchars($event['user_id'] ?? 'system') ?><br>
              <strong>From:</strong> <?= htmlspecialchars($event['ip_address'] ?? 'unknown') ?>
            </div>

            <div style="color: #666; margin: 8px 0;">
              <?= htmlspecialchars($event['summary'] ?? '') ?>
            </div>

            <?php if (!empty($changes)): ?>
              <div class="changes">
                <strong>Changes:</strong>
                <?php foreach ($changes as $field => $change): ?>
                  <div class="change-item">
                    <?php if ($change['old'] === null && $change['new'] !== null): ?>
                      <span class="change-added">✓ Added:</span> 
                      <strong><?= htmlspecialchars($field) ?></strong> = "<?= htmlspecialchars(substr($change['new'], 0, 50)) ?>"
                    <?php elseif ($change['old'] !== null && $change['new'] === null): ?>
                      <span class="change-removed">✗ Removed:</span> 
                      <strong><?= htmlspecialchars($field) ?></strong>
                    <?php else: ?>
                      <span class="change-modified">~ Changed:</span> 
                      <strong><?= htmlspecialchars($field) ?></strong><br>
                      From: "<?= htmlspecialchars(substr($change['old'], 0, 40)) ?>"<br>
                      To: "<?= htmlspecialchars(substr($change['new'], 0, 40)) ?>"
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p style="background: white; padding: 20px; border-radius: 6px;">No modification history found for this contact.</p>
  <?php endif; ?>

<?php include_once 'layout_end.php'; ?>
