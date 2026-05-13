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

<h2>Reports & Analytics</h2>
  <p><a href="admin_dashboard.php">← Back to Dashboard</a></p>

  <!-- Report Type Selector -->
  <div class="report-selector">
    <h3>Select Report Type</h3>
    <div class="report-grid">
      <a href="?type=activity" class="report-btn <?= $report_type === 'activity' ? 'active' : '' ?>">📊 Activity</a>
      <a href="?type=contacts" class="report-btn <?= $report_type === 'contacts' ? 'active' : '' ?>">👥 Contacts</a>
      <a href="?type=users"    class="report-btn <?= $report_type === 'users'    ? 'active' : '' ?>">👤 Users</a>
      <a href="?type=errors"   class="report-btn <?= $report_type === 'errors'   ? 'active' : '' ?>">⚠️ Errors</a>
      <a href="?type=revenue"  class="report-btn <?= $report_type === 'revenue'  ? 'active' : '' ?>">💰 Revenue</a>
      <a href="?type=pipeline" class="report-btn <?= $report_type === 'pipeline' ? 'active' : '' ?>">📈 Pipeline</a>
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
    <?php
      $company_stats  = getContactStatsByCategory('company');
      $province_stats = getContactStatsByCategory('province');
      $conn_r = get_mysql_connection();
      $total_c  = (int)$conn_r->query("SELECT COUNT(*) AS n FROM contacts")->fetch_assoc()['n'];
      $with_email = (int)$conn_r->query("SELECT COUNT(*) AS n FROM contacts WHERE email IS NOT NULL AND email <> ''")->fetch_assoc()['n'];
      $unique_co  = (int)$conn_r->query("SELECT COUNT(DISTINCT company) AS n FROM contacts WHERE company IS NOT NULL AND company <> ''")->fetch_assoc()['n'];
      $conn_r->close();
      $dup_count = count(findDuplicateEmails());
    ?>
    
    <div class="chart-container">
      <h3>Contacts Report</h3>
      
      <div class="stat-grid">
        <div class="stat-box">
          <div class="label">Total Contacts</div>
          <div class="value"><?= number_format($total_c) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">With Email</div>
          <div class="value"><?= number_format($with_email) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Unique Companies</div>
          <div class="value"><?= number_format($unique_co) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Duplicate Emails</div>
          <div class="value" style="color: #dc3545;"><?= $dup_count ?></div>
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
    <?php
      $active_users = getActiveUsers(); // returns [['user_id'=>..,'action_count'=>..], ...]
      $total_actions_all = array_sum(array_column($active_users, 'action_count'));
    ?>
    
    <div class="chart-container">
      <h3>Active Users Report</h3>
      
      <div class="stat-grid">
        <div class="stat-box">
          <div class="label">Total Users</div>
          <div class="value"><?= number_format(count($active_users)) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Total Actions</div>
          <div class="value"><?= number_format($total_actions_all) ?></div>
        </div>
      </div>

      <h4 style="margin-top: 30px;">User Activity</h4>
      <div class="bar-chart">
        <?php 
        $max_actions = max(array_column($active_users, 'action_count') ?: [1]);
        foreach ($active_users as $u):
          $user  = $u['user_id'];
          $count = $u['action_count'];
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

  <!-- Revenue Report -->
  <?php if ($report_type === 'revenue'): ?>
    <?php
      $conn_rev = get_mysql_connection();
      // ARR totals by contract status
      $arr_by_status = [];
      $r = $conn_rev->query("SELECT contract_status, COUNT(*) AS cnt, SUM(annual_value) AS arr, SUM(monthly_fee) AS mrr FROM contracts WHERE contract_status IS NOT NULL GROUP BY contract_status ORDER BY arr DESC");
      if ($r) { while ($row = $r->fetch_assoc()) { $arr_by_status[] = $row; } $r->free(); }

      // Active ARR total
      $arr_active = 0; $mrr_active = 0; $contract_count = 0;
      foreach ($arr_by_status as $s) {
          if (strtolower($s['contract_status']) === 'active') {
              $arr_active   = (float)$s['arr'];
              $mrr_active   = (float)$s['mrr'];
              $contract_count = (int)$s['cnt'];
          }
      }

      // Monthly fee by equipment type (active contracts)
      $by_type = [];
      $r2 = $conn_rev->query("SELECT equipment_type, COUNT(*) AS cnt, SUM(monthly_fee) AS mrr, SUM(annual_value) AS arr FROM contracts WHERE contract_status = 'Active' AND equipment_type IS NOT NULL AND equipment_type <> '' GROUP BY equipment_type ORDER BY arr DESC");
      if ($r2) { while ($row = $r2->fetch_assoc()) { $by_type[] = $row; } $r2->free(); }

      // Contracts ending within 90 days
      $expiring = [];
      $r3 = $conn_rev->query("SELECT c.contract_id, co.company, co.first_name, co.last_name, c.contract_type, c.monthly_fee, c.end_date, DATEDIFF(c.end_date, CURDATE()) AS days_left FROM contracts c LEFT JOIN contacts co ON c.contact_id = co.contact_id WHERE c.contract_status = 'Active' AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) ORDER BY c.end_date ASC LIMIT 50");
      if ($r3) { while ($row = $r3->fetch_assoc()) { $expiring[] = $row; } $r3->free(); }
      $conn_rev->close();
    ?>
    <div class="chart-container">
      <h3>Revenue Report</h3>
      <div class="stat-grid">
        <div class="stat-box">
          <div class="label">Active Contracts</div>
          <div class="value"><?= number_format($contract_count) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Monthly Recurring Revenue</div>
          <div class="value">$<?= number_format($mrr_active, 0) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Annual Recurring Revenue</div>
          <div class="value">$<?= number_format($arr_active, 0) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Expiring in 90 Days</div>
          <div class="value" style="color:#f59e0b;"><?= count($expiring) ?></div>
        </div>
      </div>

      <h4 style="margin-top:28px;">ARR by Contract Status</h4>
      <div class="bar-chart">
        <?php
        $max_arr = max(array_column($arr_by_status, 'arr') ?: [1]);
        foreach ($arr_by_status as $s):
        ?>
          <div class="bar-item">
            <div class="bar-label"><?= htmlspecialchars($s['contract_status']) ?> (<?= $s['cnt'] ?>)</div>
            <div class="bar-bar" style="width:<?= max(2, ($s['arr'] / $max_arr * 100)) ?>%;">
              <div class="bar-value">$<?= number_format((float)$s['arr'], 0) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <h4 style="margin-top:20px;">Active ARR by Equipment Type</h4>
      <div class="bar-chart">
        <?php
        $max_type = max(array_column($by_type, 'arr') ?: [1]);
        foreach ($by_type as $bt):
        ?>
          <div class="bar-item">
            <div class="bar-label"><?= htmlspecialchars($bt['equipment_type']) ?> (<?= $bt['cnt'] ?>)</div>
            <div class="bar-bar" style="width:<?= max(2, ($bt['arr'] / $max_type * 100)) ?>%;">
              <div class="bar-value">$<?= number_format((float)$bt['arr'], 0) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($expiring)): ?>
      <h4 style="margin-top:20px;">⚠️ Contracts Expiring in 90 Days</h4>
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:#fef9c3;">
          <th style="padding:8px;text-align:left;">Contract</th>
          <th>Company</th><th>Type</th><th style="text-align:right;">Monthly Fee</th>
          <th>End Date</th><th style="text-align:center;">Days Left</th>
        </tr></thead>
        <tbody>
          <?php foreach ($expiring as $ex):
            $days = (int)$ex['days_left'];
            $color = $days <= 30 ? '#dc2626' : ($days <= 60 ? '#f59e0b' : '#16a34a');
          ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:8px;"><a href="contract_view.php?id=<?= urlencode($ex['contract_id']) ?>" style="color:#0099A8;"><?= htmlspecialchars($ex['contract_id']) ?></a></td>
            <td><?= htmlspecialchars($ex['company'] ?? trim(($ex['first_name'] ?? '') . ' ' . ($ex['last_name'] ?? ''))) ?></td>
            <td><?= htmlspecialchars($ex['contract_type'] ?? '') ?></td>
            <td style="text-align:right;">$<?= number_format((float)$ex['monthly_fee'], 2) ?></td>
            <td><?= htmlspecialchars($ex['end_date']) ?></td>
            <td style="text-align:center;font-weight:700;color:<?= $color ?>;"><?= $days ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Pipeline Report -->
  <?php if ($report_type === 'pipeline'): ?>
    <?php
      $conn_pipe = get_mysql_connection();
      $pipeline_stages = [];
      $r = $conn_pipe->query("SELECT stage, COUNT(*) AS cnt, SUM(value) AS total_value, SUM(value * probability / 100) AS weighted FROM opportunities WHERE stage IS NOT NULL GROUP BY stage ORDER BY total_value DESC");
      if ($r) { while ($row = $r->fetch_assoc()) { $pipeline_stages[] = $row; } $r->free(); }

      $total_pipeline = array_sum(array_column($pipeline_stages, 'total_value'));
      $total_weighted = array_sum(array_column($pipeline_stages, 'weighted'));
      $total_opps     = array_sum(array_column($pipeline_stages, 'cnt'));

      // Overdue close dates
      $overdue = [];
      $r2 = $conn_pipe->query("SELECT o.opportunity_id, o.name, o.stage, o.value, o.expected_close, c.company, c.first_name, c.last_name FROM opportunities o LEFT JOIN contacts c ON o.contact_id = c.contact_id WHERE o.expected_close < CURDATE() AND o.stage NOT IN ('Closed Won','Closed Lost') ORDER BY o.expected_close ASC LIMIT 50");
      if ($r2) { while ($row = $r2->fetch_assoc()) { $overdue[] = $row; } $r2->free(); }

      // Opportunities closing within 30 days
      $closing_soon = [];
      $r3 = $conn_pipe->query("SELECT o.opportunity_id, o.name, o.stage, o.value, o.probability, o.expected_close, c.company FROM opportunities o LEFT JOIN contacts c ON o.contact_id = c.contact_id WHERE o.expected_close BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND o.stage NOT IN ('Closed Won','Closed Lost') ORDER BY o.expected_close ASC LIMIT 50");
      if ($r3) { while ($row = $r3->fetch_assoc()) { $closing_soon[] = $row; } $r3->free(); }
      $conn_pipe->close();
    ?>
    <div class="chart-container">
      <h3>Pipeline Report</h3>
      <div class="stat-grid">
        <div class="stat-box">
          <div class="label">Open Opportunities</div>
          <div class="value"><?= number_format($total_opps) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Total Pipeline Value</div>
          <div class="value">$<?= number_format($total_pipeline, 0) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Weighted Forecast</div>
          <div class="value">$<?= number_format($total_weighted, 0) ?></div>
        </div>
        <div class="stat-box">
          <div class="label">Overdue Close Dates</div>
          <div class="value" style="color:#dc2626;"><?= count($overdue) ?></div>
        </div>
      </div>

      <h4 style="margin-top:28px;">Pipeline by Stage</h4>
      <div class="bar-chart">
        <?php
        $max_val = max(array_column($pipeline_stages, 'total_value') ?: [1]);
        foreach ($pipeline_stages as $ps):
        ?>
          <div class="bar-item">
            <div class="bar-label"><?= htmlspecialchars($ps['stage']) ?> (<?= $ps['cnt'] ?>)</div>
            <div class="bar-bar" style="width:<?= max(2, ($ps['total_value'] / $max_val * 100)) ?>%;background:#0099A8;">
              <div class="bar-value">$<?= number_format((float)$ps['total_value'], 0) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($closing_soon)): ?>
      <h4 style="margin-top:20px;">🔔 Closing in 30 Days (<?= count($closing_soon) ?>)</h4>
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:#fef9c3;">
          <th style="padding:8px;text-align:left;">Opportunity</th>
          <th>Company</th><th>Stage</th>
          <th style="text-align:right;">Value</th><th>Probability</th><th>Close Date</th>
        </tr></thead>
        <tbody>
          <?php foreach ($closing_soon as $cs): ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:8px;"><a href="opportunity_form.php?id=<?= (int)$cs['opportunity_id'] ?>" style="color:#0099A8;"><?= htmlspecialchars($cs['name'] ?? '') ?></a></td>
            <td><?= htmlspecialchars($cs['company'] ?? '') ?></td>
            <td><?= htmlspecialchars($cs['stage'] ?? '') ?></td>
            <td style="text-align:right;">$<?= number_format((float)$cs['value'], 0) ?></td>
            <td><?= (int)$cs['probability'] ?>%</td>
            <td><?= htmlspecialchars($cs['expected_close'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if (!empty($overdue)): ?>
      <h4 style="margin-top:20px;">🔴 Overdue Close Dates (<?= count($overdue) ?>)</h4>
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:#fee2e2;">
          <th style="padding:8px;text-align:left;">Opportunity</th>
          <th>Company</th><th>Stage</th>
          <th style="text-align:right;">Value</th><th>Close Date</th><th style="text-align:center;">Days Overdue</th>
        </tr></thead>
        <tbody>
          <?php foreach ($overdue as $ov):
            $days_over = (int)(strtotime('today') - strtotime($ov['expected_close'])) / 86400;
          ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:8px;"><a href="opportunity_form.php?id=<?= (int)$ov['opportunity_id'] ?>" style="color:#0099A8;"><?= htmlspecialchars($ov['name'] ?? '') ?></a></td>
            <td><?= htmlspecialchars($ov['company'] ?? trim(($ov['first_name'] ?? '') . ' ' . ($ov['last_name'] ?? ''))) ?></td>
            <td><?= htmlspecialchars($ov['stage'] ?? '') ?></td>
            <td style="text-align:right;">$<?= number_format((float)$ov['value'], 0) ?></td>
            <td><?= htmlspecialchars($ov['expected_close']) ?></td>
            <td style="text-align:center;font-weight:700;color:#dc2626;"><?= $days_over ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<?php include_once 'layout_end.php'; ?>
