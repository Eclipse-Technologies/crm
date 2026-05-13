<?php
require_once 'forecast_calc.php';

$pageTitle = 'Forecast Dashboard';
require_once 'layout_start.php';

$forecastData = calculateForecasts();
$forecasts = $forecastData['individual'] ?? [];
$forecastByStage = $forecastData['by_stage'] ?? [];

$totalForecast = 0.0;
$totalPipeline = 0.0;
foreach ($forecasts as $row) {
    $totalForecast += (float)($row['forecast'] ?? 0);
    $totalPipeline += (float)($row['value'] ?? 0);
}

$weightedCoverage = $totalPipeline > 0 ? (($totalForecast / $totalPipeline) * 100) : 0;
$overdueCount = count(array_filter($forecasts, fn($r) => isset($r['days_to_close']) && $r['days_to_close'] < 0));
$closingThirty = count(array_filter($forecasts, fn($r) => isset($r['days_to_close']) && $r['days_to_close'] >= 0 && $r['days_to_close'] <= 30));
?>

<style>
.forecast-wrap { max-width: 1200px; margin: 0 auto; }
.forecast-header { margin-bottom: 20px; }
.forecast-header h1 { margin: 0 0 6px; }
.forecast-sub { color: #6b7280; margin: 0; }

.metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 20px; }
.metric-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; }
.metric-label { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
.metric-value { color: #111827; font-weight: 700; font-size: 24px; margin-top: 4px; }

.panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.panel h2 { margin: 0 0 12px; font-size: 18px; }

.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 760px; }
th, td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; text-align: left; }
th { background: #f9fafb; font-size: 12px; text-transform: uppercase; color: #4b5563; letter-spacing: .4px; }
td { font-size: 14px; color: #111827; }

.empty { color: #6b7280; }
</style>

<div class="forecast-wrap">
  <div class="forecast-header">
    <h1>Forecast Dashboard</h1>
    <p class="forecast-sub">Weighted opportunity forecasts based on pipeline value and probability.</p>
  </div>

  <div class="metric-grid">
    <div class="metric-card">
      <div class="metric-label">Total Pipeline Value</div>
      <div class="metric-value">$<?= number_format($totalPipeline, 2) ?></div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Weighted Forecast</div>
      <div class="metric-value">$<?= number_format($totalForecast, 2) ?></div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Forecast Coverage</div>
      <div class="metric-value"><?= number_format($weightedCoverage, 1) ?>%</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Opportunities</div>
      <div class="metric-value"><?= count($forecasts) ?></div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Closing in 30 Days</div>
      <div class="metric-value" style="color:#d97706;"><?= $closingThirty ?></div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Overdue Close Date</div>
      <div class="metric-value" style="color:#dc2626;"><?= $overdueCount ?></div>
    </div>
  </div>

  <div class="panel">
    <h2>Forecast By Stage</h2>
    <?php if (empty($forecastByStage)): ?>
      <p class="empty">No stage forecast data available.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Stage</th>
              <th>Count</th>
              <th>Total Forecast</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($forecastByStage as $stage => $row): ?>
              <tr>
                <td><?= htmlspecialchars((string)$stage) ?></td>
                <td><?= (int)($row['count'] ?? 0) ?></td>
                <td>$<?= number_format((float)($row['total_forecast'] ?? 0), 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Opportunity Forecast Detail</h2>
    <?php if (empty($forecasts)): ?>
      <p class="empty">No opportunities found to forecast.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Opportunity</th>
              <th>Stage</th>
              <th>Pipeline Value</th>
              <th>Forecast Value</th>
              <th>Close Date</th>
              <th>Days Out</th>
            </tr>
          </thead>
          <tbody>
            <?php
            usort($forecasts, function($a,$b){ return strcmp($a['expected_close'] ?? '9999', $b['expected_close'] ?? '9999'); });
            foreach ($forecasts as $row):
              $days = $row['days_to_close'] ?? null;
              $daysColor = $days === null ? '' : ($days < 0 ? 'color:#dc2626' : ($days <= 30 ? 'color:#d97706' : 'color:#16a34a'));
            ?>
              <tr>
                <td><a href="opportunity_form.php?id=<?= urlencode((string)($row['id'] ?? '')) ?>" style="color:#0099A8;"><?= htmlspecialchars((string)($row['name'] ?? ('Opp #' . ($row['id'] ?? '')))) ?></a></td>
                <td><?= htmlspecialchars((string)($row['stage'] ?? '')) ?></td>
                <td>$<?= number_format((float)($row['value'] ?? 0), 2) ?></td>
                <td>$<?= number_format((float)($row['forecast'] ?? 0), 2) ?></td>
                <td><?= $row['expected_close'] ? htmlspecialchars($row['expected_close']) : '<span style="color:#9ca3af">—</span>' ?></td>
                <td style="<?= $daysColor ?>"><?= $days === null ? '<span style="color:#9ca3af">—</span>' : ($days < 0 ? abs($days).' overdue' : $days.'d') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'layout_end.php'; ?>
