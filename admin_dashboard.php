<?php
require_once 'layout_start.php';
require_once 'admin_helper.php';
requireAdmin();

$pageTitle = 'Admin Dashboard';
$currentPage = 'admin_dashboard.php';

// Get statistics
$stats = getSystemStats();
$recent_activity = getRecentActivity(10);
$active_users = getActiveUsers();
$integrity = checkDataIntegrity();
$daily_call_status = getDailyCallStatus();
?>


<h2>Admin Dashboard</h2>

  <!-- Data Integrity Alert -->
  <?php if (!$integrity['is_valid']): ?>
    <div class="alert-danger">
      <strong>⚠️ Data Integrity Issues Detected:</strong>
      <ul style="margin: 10px 0 0 0; padding-left: 20px;">
        <?php foreach ($integrity['issues'] as $issue): ?>
          <li><?= htmlspecialchars($issue) ?></li>
        <?php endforeach; ?>
      </ul>
      <p style="margin: 10px 0 0 0;"><a href="admin_maintenance.php">Use Maintenance Tool to Fix</a></p>
    </div>
  <?php else: ?>
    <div class="alert-success">
      ✓ All data integrity checks passed (<?= $stats['total_contacts'] ?> contacts verified)
    </div>
  <?php endif; ?>

  <!-- Key Statistics -->
  <h3>System Overview</h3>
  <div class="admin-grid">
    <div class="stat-card">
      <h4>Total Contacts</h4>
      <div class="value"><?= number_format($stats['total_contacts']) ?></div>
      <div class="subtext">database records</div>
    </div>

    <div class="stat-card">
      <h4>Email Coverage</h4>
      <div class="value"><?= $stats['total_contacts'] > 0 ? round($stats['contacts_with_email'] / $stats['total_contacts'] * 100) : 0 ?>%</div>
      <div class="subtext"><?= number_format($stats['contacts_with_email']) ?> of <?= number_format($stats['total_contacts']) ?></div>
    </div>

    <div class="stat-card">
      <h4>Duplicate Emails</h4>
      <div class="value" style="color: <?= $stats['duplicate_emails'] > 0 ? '#dc3545' : '#28a745' ?>;">
        <?= $stats['duplicate_emails'] ?>
      </div>
      <div class="subtext">needs deduplication</div>
    </div>

    <div class="stat-card">
      <h4>Unique Companies</h4>
      <div class="value"><?= number_format($stats['unique_companies']) ?></div>
      <div class="subtext"><?php 
        $company_coverage = $stats['total_contacts'] > 0 ? round($stats['contacts_with_company'] / $stats['total_contacts'] * 100) : 0;
        echo $company_coverage . '% coverage';
      ?></div>
    </div>

    <div class="stat-card">
      <h4>MySQL Contacts</h4>
      <div class="value"><?= number_format($stats['total_contacts']) ?></div>
      <div class="subtext">rows in contacts table</div>
    </div>

    <div class="stat-card">
      <h4>Total Backups</h4>
      <div class="value"><?= $stats['backup_count'] ?></div>
      <div class="subtext"><?= formatBytes($stats['total_backup_size']) ?> total</div>
    </div>

    <div class="stat-card">
      <h4>Audit Log</h4>
      <div class="value"><?= number_format($stats['audit_log_rows']) ?></div>
      <div class="subtext"><?= formatBytes($stats['audit_log_size']) ?> in audit_log table</div>
    </div>

    <div class="stat-card">
      <h4>Error Log</h4>
      <div class="value"><?= formatBytes($stats['error_log_size']) ?></div>
      <div class="subtext">logs/errors.log</div>
    </div>

    <div class="stat-card">
      <h4>Daily Call Email</h4>
      <div class="value"><?= (int) ($daily_call_status['sent_today'] ?? 0) ?></div>
      <div class="subtext">sent today</div>
    </div>

    <div class="stat-card">
      <h4>Call List Ready</h4>
      <div class="value"><?= (int) ($daily_call_status['call_ready_now'] ?? 0) ?></div>
      <div class="subtext">eligible right now</div>
    </div>
  </div>

  <div class="section">
    <h3>Daily Call Automation</h3>
    <table class="admin-table">
      <tbody>
        <tr>
          <th style="width: 220px;">Configured Recipient</th>
          <td><?= htmlspecialchars($daily_call_status['recipient'] !== '' ? $daily_call_status['recipient'] : 'Not set') ?></td>
        </tr>
        <tr>
          <th>Last Send</th>
          <td><?= htmlspecialchars($daily_call_status['last_sent_at'] ? substr((string) $daily_call_status['last_sent_at'], 0, 16) : 'No send recorded yet') ?></td>
        </tr>
        <tr>
          <th>Tracked Contacts</th>
          <td><?= (int) ($daily_call_status['tracking_rows'] ?? 0) ?></td>
        </tr>
      </tbody>
    </table>
    <p style="margin-top: 10px; color: #666;">Scheduler script: <code>daily_call_list_send.php</code> (send target from DAILY_CALL_EMAIL_TO).</p>
  </div>

  <!-- Admin Tools -->
  <h3>Admin Tools</h3>
  <div class="admin-tools">
    <a href="admin_users.php" class="tool-btn">👥 User Management</a>
    <a href="admin_backups.php" class="tool-btn">🔄 Manage Backups</a>
    <a href="admin_audit.php" class="tool-btn">📊 View Audit Log</a>
    <a href="admin_timeline.php" class="tool-btn">📅 Contact Timeline</a>
    <a href="admin_deduplicate.php" class="tool-btn">🔗 Deduplicate</a>
    <a href="admin_bulk_ops.php" class="tool-btn">📋 Bulk Operations</a>
    <a href="admin_search.php" class="tool-btn">🔍 Advanced Search</a>
    <a href="admin_integrity_report.php" class="tool-btn">🧩 Integrity Report</a>
    <a href="admin_maintenance.php" class="tool-btn">🛠️ Maintenance</a>
    <a href="admin_reports.php" class="tool-btn">📈 Reports</a>
  </div>

  <!-- Recent Activity -->
  <div class="section">
    <h3>Recent Activity (Last 10 Actions)</h3>
    <?php if (!empty($recent_activity)): ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Status</th>
            <th>Summary</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_activity as $activity): ?>
            <tr>
              <td><?= htmlspecialchars(substr($activity['timestamp'], 0, 16)) ?></td>
              <td><?= htmlspecialchars($activity['user_id'] ?? 'unknown') ?></td>
              <td><strong><?= htmlspecialchars($activity['action'] ?? 'unknown') ?></strong></td>
              <td><?= htmlspecialchars($activity['entity_type'] ?? 'unknown') ?></td>
              <td>
                <span style="color: <?= $activity['status'] === 'success' ? '#28a745' : '#dc3545' ?>;">
                  <?= htmlspecialchars($activity['status'] ?? 'unknown') ?>
                </span>
              </td>
              <td><?= htmlspecialchars(substr($activity['summary'] ?? '', 0, 40)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>No activity recorded yet.</p>
    <?php endif; ?>
  </div>

  <!-- Active Users -->
  <div class="section">
    <h3>Active Users</h3>
    <?php if (!empty($active_users)): ?>
      <?php
        $activeUserCount = count($active_users);
        $totalActiveActions = array_sum(array_map(static function ($r) {
          return (int) ($r['action_count'] ?? 0);
        }, $active_users));
        $topUserName = (string) ($active_users[0]['username'] ?? $active_users[0]['user_id'] ?? 'n/a');
        $latestActivity = '';
        foreach ($active_users as $r) {
          $ts = (string) ($r['last_action_at'] ?? '');
          if ($ts !== '' && ($latestActivity === '' || strtotime($ts) > strtotime($latestActivity))) {
            $latestActivity = $ts;
          }
        }
      ?>
      <div class="admin-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 14px;">
        <div class="stat-card">
          <h4>Tracked Users</h4>
          <div class="value"><?= number_format($activeUserCount) ?></div>
          <div class="subtext">users with logged actions</div>
        </div>
        <div class="stat-card">
          <h4>Total Actions</h4>
          <div class="value"><?= number_format($totalActiveActions) ?></div>
          <div class="subtext">across the active list</div>
        </div>
        <div class="stat-card">
          <h4>Top Actor</h4>
          <div class="value" style="font-size: 1.25rem;"><?= htmlspecialchars($topUserName) ?></div>
          <div class="subtext">most actions in this snapshot</div>
        </div>
        <div class="stat-card">
          <h4>Latest Activity</h4>
          <div class="value" style="font-size: 1.05rem;"><?= htmlspecialchars($latestActivity !== '' ? substr($latestActivity, 0, 16) : 'n/a') ?></div>
          <div class="subtext">most recent user action</div>
        </div>
      </div>

      <table class="admin-table">
        <thead>
          <tr>
            <th>#</th>
            <th>User</th>
            <th>Email</th>
            <th>Role</th>
            <th>Actions</th>
            <th>Last Activity</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($active_users as $idx => $row): ?>
            <tr>
              <td><?= (int) $idx + 1 ?></td>
              <td>
                <strong><?= htmlspecialchars((string) ($row['username'] ?? $row['user_id'] ?? 'unknown')) ?></strong>
              </td>
              <td>
                <?= htmlspecialchars((string) (($row['email'] ?? '') !== '' ? $row['email'] : '-')) ?>
              </td>
              <td>
                <?= htmlspecialchars((string) (($row['role'] ?? '') !== '' ? ucfirst((string) $row['role']) : '-')) ?>
              </td>
              <td><strong><?= (int) ($row['action_count'] ?? 0) ?></strong></td>
              <td><?= htmlspecialchars((string) (($row['last_action_at'] ?? '') !== '' ? substr((string) $row['last_action_at'], 0, 16) : '-')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>No user activity yet.</p>
    <?php endif; ?>
  </div>

<?php include_once 'layout_end.php'; ?>
