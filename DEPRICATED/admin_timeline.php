<?php
require_once 'layout_start.php';
require_once 'admin_helper.php';
requireAdmin();

$pageTitle = 'Contact Timeline';

$contact_id = $_GET['id'] ?? '';
$contacts = readCSV('contacts.csv');
$contact = null;

foreach ($contacts as $c) {
    if ($c['id'] === $contact_id) {
        $contact = $c;
        break;
    }
}

if (!$contact) {
    echo '<div class="container"><p style="color: red;">Contact not found.</p></div>';
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

<div class="main-content" id="mainContent">
  <div class="content-container">
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

  </div>
</div>

<?php include_once 'layout_end.php'; ?>
