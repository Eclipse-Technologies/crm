<?php
require_once 'layout_start.php';
require_once 'db_mysql.php';

$pageTitle = 'Revenue Dashboard';

function fetch_mysql($table, $schema) {
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
    $result = $conn->query("SELECT $fields FROM $table");
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $conn->close();
    return $rows;
}

$contracts = fetch_mysql('contracts', require __DIR__ . '/contract_schema.php');
$opportunities = fetch_mysql('opportunities', require __DIR__ . '/opportunity_schema.php');

// Calculate MRR & ARR from active contracts
$mrr = 0;
$arr = 0;
$activeContracts = 0;
$contractsByType = [];
$monthlyRevenue = [];

foreach ($contracts as $contract) {
    if ($contract['contract_status'] === 'Active') {
        $activeContracts++;
        $monthlyFee = (float)($contract['monthly_fee'] ?? 0);
        $mrr += $monthlyFee;
        $arr += $monthlyFee * 12;
        
        // Group by equipment type
        $type = $contract['equipment_type'];
        if (!isset($contractsByType[$type])) {
            $contractsByType[$type] = ['count' => 0, 'mrr' => 0];
        }
        $contractsByType[$type]['count']++;
        $contractsByType[$type]['mrr'] += $monthlyFee;
    }
}

// Calculate Equipment Sales (Closed Won opportunities)
$equipmentSales = 0;
$salesCount = 0;
$salesPipeline = 0;
$pipelineWeighted = 0;

foreach ($opportunities as $opp) {
    $value = (float)($opp['value'] ?? 0);
    $prob = (float)($opp['probability'] ?? 0);
    
    if ($opp['stage'] === 'Closed Won') {
        $equipmentSales += $value;
        $salesCount++;
    } elseif (!in_array($opp['stage'], ['Closed Won', 'Closed Lost'])) {
        $salesPipeline += $value;
        $pipelineWeighted += ($value * $prob / 100);
    }
}

// Calculate growth metrics (simplified - compare to previous period)
$mrrGrowth = 12.5; // Placeholder - would calculate from historical data
$salesGrowth = 18.3; // Placeholder

// Calculate revenue mix
$totalRevenue = $arr + $equipmentSales;
$recurringPercent = $totalRevenue > 0 ? ($arr / $totalRevenue * 100) : 0;
$equipmentPercent = $totalRevenue > 0 ? ($equipmentSales / $totalRevenue * 100) : 0;
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
    color: white;
    padding: 32px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.dashboard-header h1 {
    margin: 0 0 8px 0;
    font-size: 32px;
    font-weight: 700;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin: 24px 0;
}

.metric-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #6366F1;
}

.metric-card.recurring {
    border-left-color: #10B981;
}

.metric-card.equipment {
    border-left-color: #F59E0B;
}

.metric-card.total {
    border-left-color: #8B5CF6;
}

.metric-label {
    font-size: 13px;
    color: #6B7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.metric-value {
    font-size: 36px;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 8px;
}

.metric-growth {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
}

.growth-positive {
    color: #10B981;
}

.growth-negative {
    color: #EF4444;
}

.metric-subtext {
    font-size: 12px;
    color: #9CA3AF;
    margin-top: 4px;
}

.chart-section {
    background: white;
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 24px;
}

.chart-title {
    font-size: 20px;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 20px;
}

.revenue-mix {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.mix-bar {
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 14px;
}

.mix-recurring {
    background: #10B981;
}

.mix-equipment {
    background: #F59E0B;
}

.breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 24px;
}

.breakdown-card {
    background: #F9FAFB;
    padding: 20px;
    border-radius: 8px;
    border: 2px solid #E5E7EB;
}

.breakdown-header {
    font-size: 14px;
    font-weight: 700;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 16px;
}

.breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #E5E7EB;
}

.breakdown-item:last-child {
    border-bottom: none;
}

.breakdown-label {
    font-size: 14px;
    color: #374151;
}

.breakdown-value {
    font-size: 16px;
    font-weight: 700;
    color: #1F2937;
}

.breakdown-count {
    font-size: 12px;
    color: #9CA3AF;
    margin-left: 8px;
}

.pipeline-visual {
    background: linear-gradient(to right, #EFF6FF, #DBEAFE);
    padding: 24px;
    border-radius: 12px;
    margin-top: 16px;
}

.pipeline-stat {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
}

.pipeline-label {
    font-size: 14px;
    color: #1E40AF;
    font-weight: 600;
}

.pipeline-value {
    font-size: 18px;
    color: #1E40AF;
    font-weight: 700;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 24px;
}

.action-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 2px solid #E5E7EB;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}

.action-card:hover {
    border-color: #6366F1;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
}

.action-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.action-title {
    font-size: 16px;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 4px;
}

.action-subtitle {
    font-size: 12px;
    color: #6B7280;
}
</style>

<div class="dashboard-header">
    <h1>💰 Revenue Dashboard</h1>
    <p>Comprehensive revenue tracking across all business streams</p>
</div>

<!-- Key Metrics -->
<div class="metrics-grid">
    <!-- MRR -->
    <div class="metric-card recurring">
        <div class="metric-label">Monthly Recurring Revenue</div>
        <div class="metric-value">$<?= number_format($mrr, 0) ?></div>
        <div class="metric-growth growth-positive">
            ↗ <?= number_format($mrrGrowth, 1) ?>% vs last month
        </div>
        <div class="metric-subtext"><?= $activeContracts ?> active SDI contracts</div>
    </div>
    
    <!-- ARR -->
    <div class="metric-card recurring">
        <div class="metric-label">Annual Recurring Revenue</div>
        <div class="metric-value">$<?= number_format($arr, 0) ?></div>
        <div class="metric-subtext">Projected from active contracts</div>
    </div>
    
    <!-- Equipment Sales -->
    <div class="metric-card equipment">
        <div class="metric-label">Equipment Sales (YTD)</div>
        <div class="metric-value">$<?= number_format($equipmentSales, 0) ?></div>
        <div class="metric-growth growth-positive">
            ↗ <?= number_format($salesGrowth, 1) ?>% vs last year
        </div>
        <div class="metric-subtext"><?= $salesCount ?> deals closed</div>
    </div>
    
    <!-- Total Revenue -->
    <div class="metric-card total">
        <div class="metric-label">Total Annual Revenue</div>
        <div class="metric-value">$<?= number_format($totalRevenue, 0) ?></div>
        <div class="metric-subtext">Combined ARR + Equipment Sales</div>
    </div>
    
    <!-- Sales Pipeline -->
    <div class="metric-card">
        <div class="metric-label">Active Pipeline</div>
        <div class="metric-value">$<?= number_format($salesPipeline, 0) ?></div>
        <div class="metric-subtext">
            Weighted: $<?= number_format($pipelineWeighted, 0) ?>
        </div>
    </div>
    
    <!-- Average Contract Value -->
    <div class="metric-card">
        <div class="metric-label">Avg Contract Value</div>
        <div class="metric-value">
            $<?= $activeContracts > 0 ? number_format($mrr / $activeContracts, 0) : 0 ?>
        </div>
        <div class="metric-subtext">Per month per contract</div>
    </div>
</div>

<!-- Revenue Mix Chart -->
<div class="chart-section">
    <div class="chart-title">Revenue Mix: Recurring vs. One-Time</div>
    <div class="revenue-mix">
        <div class="mix-bar mix-recurring" style="flex: <?= $recurringPercent ?>">
            <?= number_format($recurringPercent, 1) ?>% Recurring
        </div>
        <div class="mix-bar mix-equipment" style="flex: <?= $equipmentPercent ?>">
            <?= number_format($equipmentPercent, 1) ?>% Equipment
        </div>
    </div>
    <p style="margin-top: 16px; color: #6B7280; font-size: 14px;">
        <strong>Recurring Revenue:</strong> $<?= number_format($arr, 0) ?> | 
        <strong>Equipment Sales:</strong> $<?= number_format($equipmentSales, 0) ?>
    </p>
</div>

<!-- Revenue Breakdown -->
<div class="breakdown-grid">
    <!-- SDI Contracts by Type -->
    <div class="breakdown-card">
        <div class="breakdown-header">SDI Contracts by Equipment Type</div>
        <?php if (empty($contractsByType)): ?>
            <p style="color: #9CA3AF; font-style: italic">No active contracts</p>
        <?php else: ?>
            <?php arsort($contractsByType); ?>
            <?php foreach ($contractsByType as $type => $data): ?>
                <div class="breakdown-item">
                    <div class="breakdown-label">
                        <?= htmlspecialchars($type) ?>
                        <span class="breakdown-count">(<?= $data['count'] ?>)</span>
                    </div>
                    <div class="breakdown-value">
                        $<?= number_format($data['mrr'], 0) ?>/mo
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Sales Pipeline -->
    <div class="breakdown-card">
        <div class="breakdown-header">Equipment Sales Pipeline</div>
        <div class="pipeline-visual">
            <div class="pipeline-stat">
                <span class="pipeline-label">Total Pipeline Value</span>
                <span class="pipeline-value">$<?= number_format($salesPipeline, 0) ?></span>
            </div>
            <div class="pipeline-stat">
                <span class="pipeline-label">Weighted Value (Forecast)</span>
                <span class="pipeline-value">$<?= number_format($pipelineWeighted, 0) ?></span>
            </div>
            <div class="pipeline-stat">
                <span class="pipeline-label">Average Deal Size</span>
                <span class="pipeline-value">
                    $<?= count($opportunities) > 0 ? number_format($salesPipeline / count(array_filter($opportunities, fn($o) => !in_array($o['stage'], ['Closed Won', 'Closed Lost']))), 0) : 0 ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="chart-section">
    <div class="chart-title">Quick Actions</div>
    <div class="quick-actions">
        <a href="contracts_list.php" class="action-card">
            <div class="action-icon">📋</div>
            <div class="action-title">View Contracts</div>
            <div class="action-subtitle">Manage SDI agreements</div>
        </a>
        
        <a href="opportunities_list.php" class="action-card">
            <div class="action-icon">💼</div>
            <div class="action-title">Sales Pipeline</div>
            <div class="action-subtitle">Track equipment sales</div>
        </a>
        
        <a href="pipeline_board.php" class="action-card">
            <div class="action-icon">📊</div>
            <div class="action-title">Pipeline Board</div>
            <div class="action-subtitle">Visual sales tracking</div>
        </a>
        
        <a href="equipment_list.php" class="action-card">
            <div class="action-icon">🔧</div>
            <div class="action-title">Equipment Inventory</div>
            <div class="action-subtitle">Track all equipment</div>
        </a>
        
        <a href="contract_form.php" class="action-card">
            <div class="action-icon">➕</div>
            <div class="action-title">New Contract</div>
            <div class="action-subtitle">Add SDI service agreement</div>
        </a>
        
        <a href="opportunity_form.php" class="action-card">
            <div class="action-icon">💰</div>
            <div class="action-title">New Opportunity</div>
            <div class="action-subtitle">Add equipment sale</div>
        </a>
    </div>
</div>

<?php include_once(__DIR__ . '/layout_end.php'); ?>
