<?php
require_once 'layout_start.php';
require_once 'admin_helper.php';
requireAdmin();

$pageTitle = 'Reports & Analytics';

$report_type = $_GET['type'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

?>

<style>
.report-selector { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
.report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 15px; }
.report-btn { padding: 12px; text-align: center; background: #0099A8; color: white; text-decoration: none; border-radius: 6px; cursor: pointer; border: none; font-weight: bold; }
.report-btn:hover { background: #007880; }
.report-btn.active { background: #005f6a; }
.chart-container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
.chart-container h3 { margin-top: 0; }
.bar-chart { margin: 15px 0; }
.bar-item { display: flex; align-items: center; margin-bottom: 10px; }
.bar-label { min-width: 150px; font-weight: bold; font-size: 13px; }
.bar-bar { flex: 1; height: 25px; background: #0099A8; border-radius: 3px; display: flex; align-items: center; }
.bar-value { margin-left: 10px; font-weight: bold; min-width: 40px; font-size: 13px; }
.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
.stat-box { background: #f5f5f5; padding: 15px; border-radius: 6px; border-left: 4px solid #0099A8; }
.stat-box .label { font-size: 12px; color: #666; text-transform: uppercase; }
.stat-box .value { font-size: 28px; font-weight: bold; color: #0099A8; margin: 5px 0; }
</style>

<div class="main-content" id="mainContent">
  <div class="content-container">
  <h2>Reports & Analytics</h2>
  <p><a href="admin_dashboard.php">← Back to Dashboard</a></p>

  <!-- Report Type Selector -->
  <div class="report-selector">
    <h3>Select Report Type</h3>
    <div class="report-grid">
      <a href="?type=activity" class="report-btn <?= $report_type === 'activity' ? 'active' : '' ?>">📊 Activity</a>
      <a href="?type=contacts" class="report-btn <?= $report_type === 'contacts' ? 'active' : '' ?>">👥 Contacts</a>
      <a href="?type=users" class="report-btn <?= $report_type === 'users' ? 'active' : '' ?>">👤 Users</a>
      <a href="?type=errors" class="report-btn <?= $report_type === 'errors' ? 'active' : '' ?>">⚠️ Errors</a>
    </div>

    <!-- Date Range Filter -->
    <form method="GET" style="margin-top: 15px; display: flex; gap: 10px; align-items: flex-end;">
      <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">
      <div>
        <label>From:</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
      </div>
      <div>
        <label>To:</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
      </div>
      <button type="submit" class="report-btn">📅 Update</button>
    </form>
  </div>

  <!-- Activity Report -->
  <?php if ($report_type === 'activity'): ?>
    <?php $activity_report = generateActivityReport($start_date, $end_date); ?>
    
    <div class="chart-container">
      <h3>Activity Report (<?= $activity_report['period'] ?>)</h3>
      
      <div class="stat-grid">
        <div class="stat-box">
          <div class="label">Total Actions</div>
          <div class="value"><?= number_format($activity_report['total_actions']) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Successful</div>
          <div class="value" style="color: #28a745;"><?= number_format($activity_report['successes']) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Failed</div>
          <div class="value" style="color: #dc3545;"><?= number_format($activity_report['failures']) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Success Rate</div>
          <div class="value" style="color: #0099A8;">
            <?= $activity_report['total_actions'] > 0 ? round($activity_report['successes'] / $activity_report['total_actions'] * 100, 1) : 0 ?>%
          </div>
        </div>
      </div>

      <h4 style="margin-top: 30px;">Actions Breakdown</h4>
      <div class="bar-chart">
        <?php 
        $max_action = max($activity_report['by_action'] ?? [1]);
        foreach ($activity_report['by_action'] as $action => $count):
        ?>
          <div class="bar-item">
            <div class="bar-label"><?= htmlspecialchars(ucfirst($action)) ?></div>
            <div class="bar-bar" style="width: <?= ($count / $max_action * 100) ?>%;">
              <div class="bar-value"><?= $count ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <h4>Top Users</h4>
      <div class="bar-chart">
        <?php 
        $max_user = max($activity_report['by_user'] ?? [1]);
        $top_users = array_slice($activity_report['by_user'], 0, 5, true);
        foreach ($top_users as $user => $count):
        ?>
          <div class="bar-item">
            <div class="bar-label"><?= htmlspecialchars($user) ?></div>
            <div class="bar-bar" style="width: <?= ($count / $max_user * 100) ?>%;">
              <div class="bar-value"><?= $count ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Contacts Report -->
  <?php if ($report_type === 'contacts'): ?>
    <?php $contacts = readCSV('contacts.csv'); ?>
    <?php $company_stats = getContactStatsByCategory('company'); ?>
    <?php $province_stats = getContactStatsByCategory('province'); ?>
    
    <div class="chart-container">
      <h3>Contacts Report</h3>
      
      <div class="stat-grid">
        <div class="stat-box">
          <div class="label">Total Contacts</div>
          <div class="value"><?= number_format(count($contacts)) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">With Email</div>
          <div class="value"><?= count(array_filter(array_column($contacts, 'email'))) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Unique Companies</div>
          <div class="value"><?= count(array_unique(array_filter(array_column($contacts, 'company')))) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Duplicate Emails</div>
          <div class="value" style="color: #dc3545;"><?= count(findDuplicateEmails()) ?></div>
        </div>
      </div>

      <h4 style="margin-top: 30px;">Top Companies</h4>
      <div class="bar-chart">
        <?php 
        $top_companies = array_slice($company_stats, 0, 10, true);
        $max_comp = max($top_companies ?? [1]);
        foreach ($top_companies as $company => $count):
        ?>
          <div class="bar-item">
            <div class="bar-label"><?= htmlspecialchars(substr($company, 0, 30)) ?></div>
            <div class="bar-bar" style="width: <?= ($count / $max_comp * 100) ?>%;">
              <div class="bar-value"><?= $count ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <h4>Top Provinces</h4>
      <div class="bar-chart">
        <?php 
        $top_provinces = array_slice($province_stats, 0, 10, true);
        $max_prov = max($top_provinces ?? [1]);
        foreach ($top_provinces as $province => $count):
        ?>
          <div class="bar-item">
            <div class="bar-label"><?= htmlspecialchars(substr($province, 0, 30)) ?? '(empty)' ?></div>
            <div class="bar-bar" style="width: <?= ($count / $max_prov * 100) ?>%;">
              <div class="bar-value"><?= $count ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Users Report -->
  <?php if ($report_type === 'users'): ?>
    <?php $active_users = getActiveUsers(); ?>
    
    <div class="chart-container">
      <h3>Active Users Report</h3>
      
      <div class="stat-grid">
        <div class="stat-box">
          <div class="label">Total Users</div>
          <div class="value"><?= number_format(count($active_users)) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Total Actions</div>
          <div class="value"><?= number_format(array_sum($active_users)) ?></div>
        </div>
      </div>

      <h4 style="margin-top: 30px;">User Activity</h4>
      <div class="bar-chart">
        <?php 
        $max_actions = max($active_users ?? [1]);
        foreach ($active_users as $user => $count):
        ?>
          <div class="bar-item">
            <div class="bar-label"><?= htmlspecialchars(substr($user, 0, 30)) ?></div>
            <div class="bar-bar" style="width: <?= ($count / $max_actions * 100) ?>%;">
              <div class="bar-value"><?= $count ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Errors Report -->
  <?php if ($report_type === 'errors'): ?>
    <?php 
    $error_log_file = 'logs/errors.log';
    $errors = [];
    if (file_exists($error_log_file)) {
      $lines = file($error_log_file, FILE_SKIP_EMPTY_LINES);
      $errors = array_slice($lines, -50);  // Last 50 errors
    }
    ?>
    
    <div class="chart-container">
      <h3>Recent Errors</h3>
      
      <div class="stat-grid">
        <div class="stat-box">
          <div class="label">Error Log Size</div>
          <div class="value"><?= formatBytes(filesize($error_log_file) ?? 0) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Recent Entries</div>
          <div class="value"><?= count($errors) ?></div>
        </div>
      </div>

      <h4 style="margin-top: 30px;">Last 20 Errors</h4>
      <?php if (!empty($errors)): ?>
        <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
          <tbody>
            <?php foreach (array_slice($errors, -20) as $error): ?>
              <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 8px;"><?= htmlspecialchars(substr($error, 0, 100)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No errors logged.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- No Report Selected -->
  <?php if (empty($report_type)): ?>
    <div style="background: white; padding: 30px; border-radius: 8px; text-align: center;">
      <p style="font-size: 16px; color: #666;">Select a report type above to view analytics and insights.</p>
    </div>
  <?php endif; ?>

  </div>
</div>

<?php include_once 'layout_end.php'; ?>
