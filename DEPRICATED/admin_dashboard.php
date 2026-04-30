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
?>


<div class="main-content" id="mainContent">
  <div class="content-container">
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
      <h4>CSV Database</h4>
      <div class="value"><?= formatBytes($stats['csv_size']) ?></div>
      <div class="subtext">contacts.csv file size</div>
    </div>

    <div class="stat-card">
      <h4>Total Backups</h4>
      <div class="value"><?= $stats['backup_count'] ?></div>
      <div class="subtext"><?= formatBytes($stats['total_backup_size']) ?> total</div>
    </div>

    <div class="stat-card">
      <h4>Audit Log</h4>
      <div class="value"><?= formatBytes($stats['audit_log_size']) ?></div>
      <div class="subtext">activity_log.csv</div>
    </div>

    <div class="stat-card">
      <h4>Error Log</h4>
      <div class="value"><?= formatBytes($stats['error_log_size']) ?></div>
      <div class="subtext">logs/errors.log</div>
    </div>
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
      <table class="admin-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($active_users as $user => $count): ?>
            <tr>
              <td><?= htmlspecialchars($user) ?></td>
              <td><?= $count ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>No user activity yet.</p>
    <?php endif; ?>
  </div>

  </div>
</div>

<?php include_once 'layout_end.php'; ?>
