<?php
// Dashboard variable defaults
$totalContacts = 0;
$totalValue = 0;
$totalForecast = 0;
$accuracy = 0;
$stages = [];
$topStage = '';
$forecastByStage = [];
include_once(__DIR__ . '/layout_start.php');
?>
<div class="page-header">
  <h1>Dashboard</h1>
  <div class="page-actions">
    <a href="contacts_list.php" class="btn btn-outline">View Contacts</a>
    <a href="opportunities_list.php" class="btn btn-primary">View Opportunities</a>
  </div>
</div>

<!-- Key Metrics -->
<div class="stats-grid">
  <div class="stat-card stat-card-primary">
    <div class="stat-icon">👥</div>
    <div class="stat-content">
      <div class="stat-label">Total Contacts</div>
      <div class="stat-value"><?= number_format($totalContacts) ?></div>
    </div>
  </div>
  
  <div class="stat-card stat-card-success">
    <div class="stat-icon">💰</div>
    <div class="stat-content">
      <div class="stat-label">Pipeline Value</div>
      <div class="stat-value">$<?= number_format($totalValue, 0) ?></div>
    </div>
  </div>
  
  <div class="stat-card stat-card-info">
    <div class="stat-icon">📊</div>
    <div class="stat-content">
      <div class="stat-label">Forecast Value</div>
      <div class="stat-value">$<?= number_format($totalForecast, 0) ?></div>
    </div>
  </div>
  
  <div class="stat-card stat-card-warning">
    <div class="stat-icon">🎯</div>
    <div class="stat-content">
      <div class="stat-label">Forecast Accuracy</div>
      <div class="stat-value"><?= number_format($accuracy, 1) ?>%</div>
    </div>
  </div>
</div>

<!-- Pipeline and Forecast Tables -->
<div class="dashboard-grid">
  <div class="card">
    <div class="card-header">
      <section class="dashboard-grid">
        <h3>Pipeline Breakdown</h3>
    </div>
    <div class="card-body">
      <table class="modern-table">
        <thead>
          <tr>
            <th>Stage</th>
            <th>Count</th>
            <th>Total Value</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stages as $stage => $data): ?>
            <tr>
              <td><?= e($stage) ?></td>
              <td><span class="badge badge-primary"><?= $data['count'] ?></span></td>
              <td><strong>$<?= number_format($data['value'], 2) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>Forecast by Stage</h3>
      <?php if ($topStage): ?>
        <div class="badge badge-success">Top: <?= e($topStage) ?></div>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <table class="modern-table">
        <thead>
          <tr>
            <th>Stage</th>
            <th>Count</th>
            <th>Total Forecast</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($forecastByStage as $stage => $data): ?>
            <tr>
              <td><?= e($stage) ?></td>
              <td><span class="badge badge-info"><?= $data['count'] ?></span></td>
              <td><strong>$<?= number_format($data['total_forecast'], 2) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 24px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.stat-icon {
  font-size: 36px;
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: rgba(0, 153, 168, 0.1);
}

.stat-card-primary .stat-icon { background: rgba(0, 153, 168, 0.1); }
.stat-card-success .stat-icon { background: rgba(16, 185, 129, 0.1); }
.stat-card-info .stat-icon { background: rgba(59, 130, 246, 0.1); }
.stat-card-warning .stat-icon { background: rgba(245, 158, 11, 0.1); }

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 4px;
  font-weight: 500;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #111827;
  line-height: 1;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
  
  .stat-value {
    font-size: 24px;
  }
}
</style>

<?php include_once(__DIR__ . '/layout_end.php'); ?>
