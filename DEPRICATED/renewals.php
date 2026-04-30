<?php
require_once 'layout_start.php';
require_once 'db_mysql.php';

$pageTitle = 'Contract Renewals';
$contractSchema = require __DIR__ . '/contract_schema.php';

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

$contracts = fetch_mysql('contracts', $contractSchema);
$contacts = fetch_mysql('contacts', require __DIR__ . '/contact_schema.php');

$today = strtotime(date('Y-m-d'));
$upcoming = [];
$critical = [];
$actionNeeded = [];

foreach ($contracts as $contract) {
    if ($contract['contract_status'] !== 'Active') continue;
    
    if (empty($contract['end_date'])) continue;
    
    $endDate = strtotime($contract['end_date']);
    $daysToExpiry = round(($endDate - $today) / 86400);
    
    // Find contact info
    $contactInfo = null;
    foreach ($contacts as $c) {
        if ($c['id'] === $contract['contact_id']) {
            $contactInfo = $c;
            break;
        }
    }
    
    $item = array_merge($contract, [
        'days_to_expiry' => $daysToExpiry,
        'contact_info' => $contactInfo
    ]);
    
    // Categorize
    if ($daysToExpiry <= 30) {
        $critical[] = $item;
        $actionNeeded[] = $item;
    } elseif ($daysToExpiry <= 90) {
        $actionNeeded[] = $item;
    } elseif ($daysToExpiry <= 180) {
        $upcoming[] = $item;
    }
}

// Sort by days to expiry
usort($critical, fn($a, $b) => $a['days_to_expiry'] - $b['days_to_expiry']);
usort($actionNeeded, fn($a, $b) => $a['days_to_expiry'] - $b['days_to_expiry']);
usort($upcoming, fn($a, $b) => $a['days_to_expiry'] - $b['days_to_expiry']);

// Calculate renewal metrics
$totalRenewalValue = 0;
foreach ($actionNeeded as $item) {
    $totalRenewalValue += (float)($item['annual_value'] ?? 0);
}
?>

<style>
.renewal-header {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    color: white;
    padding: 32px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.renewal-header h1 {
    margin: 0 0 8px 0;
    font-size: 32px;
    font-weight: 700;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin: 24px 0;
}

.metric-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.metric-card.critical {
    border-left: 4px solid #EF4444;
}

.metric-card.warning {
    border-left: 4px solid #F59E0B;
}

.metric-card.upcoming {
    border-left: 4px solid #3B82F6;
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
}

.metric-subtext {
    font-size: 12px;
    color: #9CA3AF;
    margin-top: 4px;
}

.renewal-section {
    background: white;
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 24px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #1F2937;
    display: flex;
    align-items: center;
    gap: 12px;
}

.priority-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.priority-critical {
    background: #FEE2E2;
    color: #991B1B;
}

.priority-warning {
    background: #FEF3C7;
    color: #92400E;
}

.priority-upcoming {
    background: #DBEAFE;
    color: #1E40AF;
}

.renewal-card {
    background: #F9FAFB;
    padding: 24px;
    border-radius: 12px;
    border: 2px solid #E5E7EB;
    margin-bottom: 16px;
    transition: all 0.2s;
}

.renewal-card:hover {
    border-color: #F59E0B;
    transform: translateX(4px);
}

.renewal-card.critical {
    border-left: 4px solid #EF4444;
}

.renewal-card.warning {
    border-left: 4px solid #F59E0B;
}

.renewal-header-row {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 16px;
}

.contract-info h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 700;
    color: #1F2937;
}

.contract-meta {
    display: flex;
    gap: 16px;
    font-size: 14px;
    color: #6B7280;
}

.expiry-info {
    text-align: right;
}

.days-remaining {
    font-size: 32px;
    font-weight: 700;
}

.days-remaining.critical {
    color: #EF4444;
}

.days-remaining.warning {
    color: #F59E0B;
}

.days-label {
    font-size: 12px;
    color: #6B7280;
    text-transform: uppercase;
}

.renewal-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    padding: 16px 0;
    border-top: 1px solid #E5E7EB;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 12px;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    font-size: 16px;
    font-weight: 700;
    color: #1F2937;
}

.renewal-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-renew {
    background: #10B981;
    color: white;
}

.btn-renew:hover {
    background: #059669;
}

.btn-contact {
    background: #3B82F6;
    color: white;
}

.btn-contact:hover {
    background: #2563EB;
}

.btn-view {
    background: #F3F4F6;
    color: #374151;
    border: 2px solid #E5E7EB;
}

.btn-view:hover {
    background: #E5E7EB;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6B7280;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
}
</style>

<div class="renewal-header">
    <h1>🔄 Contract Renewals</h1>
    <p>Track and manage upcoming contract expirations</p>
</div>

<!-- Metrics -->
<div class="metrics-grid">
    <div class="metric-card critical">
        <div class="metric-label">Critical (≤30 Days)</div>
        <div class="metric-value"><?= count($critical) ?></div>
        <div class="metric-subtext">Immediate action required</div>
    </div>
    
    <div class="metric-card warning">
        <div class="metric-label">Action Needed (≤90 Days)</div>
        <div class="metric-value"><?= count($actionNeeded) ?></div>
        <div class="metric-subtext">Start renewal conversations</div>
    </div>
    
    <div class="metric-card upcoming">
        <div class="metric-label">Upcoming (≤180 Days)</div>
        <div class="metric-value"><?= count($upcoming) ?></div>
        <div class="metric-subtext">Plan ahead</div>
    </div>
    
    <div class="metric-card">
        <div class="metric-label">Renewal Revenue at Risk</div>
        <div class="metric-value">$<?= number_format($totalRenewalValue, 0) ?></div>
        <div class="metric-subtext">Annual value of expiring contracts</div>
    </div>
</div>

<!-- Critical Renewals (≤30 days) -->
<?php if (!empty($critical)): ?>
<div class="renewal-section">
    <div class="section-header">
        <div class="section-title">
            🚨 Critical - Immediate Action Required
            <span class="priority-badge priority-critical"><?= count($critical) ?> contracts</span>
        </div>
    </div>
    
    <?php foreach ($critical as $contract): ?>
        <?php
        $contactName = 'Unknown';
        $contactEmail = '';
        $contactPhone = '';
        if ($contract['contact_info']) {
            $contactName = trim($contract['contact_info']['first_name'] . ' ' . $contract['contact_info']['last_name']);
            $contactEmail = $contract['contact_info']['email'] ?? '';
            $contactPhone = $contract['contact_info']['phone'] ?? '';
        }
        ?>
        <div class="renewal-card critical">
            <div class="renewal-header-row">
                <div class="contract-info">
                    <h3><?= htmlspecialchars($contract['contract_id']) ?></h3>
                    <div class="contract-meta">
                        <span>👤 <?= htmlspecialchars($contactName) ?></span>
                        <span>🔧 <?= htmlspecialchars($contract['equipment_type']) ?></span>
                    </div>
                </div>
                <div class="expiry-info">
                    <div class="days-remaining critical"><?= $contract['days_to_expiry'] ?></div>
                    <div class="days-label">Days Remaining</div>
                </div>
            </div>
            
            <div class="renewal-details">
                <div class="detail-item">
                    <span class="detail-label">Monthly Fee</span>
                    <span class="detail-value">$<?= number_format((float)$contract['monthly_fee'], 2) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Annual Value</span>
                    <span class="detail-value">$<?= number_format((float)$contract['annual_value'], 2) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">End Date</span>
                    <span class="detail-value"><?= htmlspecialchars($contract['end_date']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Auto-Renew</span>
                    <span class="detail-value"><?= htmlspecialchars($contract['auto_renew']) ?></span>
                </div>
            </div>
            
            <div class="renewal-actions">
                <a href="contract_renew.php?id=<?= urlencode($contract['contract_id']) ?>" class="btn btn-renew">
                    ✓ Start Renewal Process
                </a>
                <?php if ($contactEmail): ?>
                    <a href="mailto:<?= htmlspecialchars($contactEmail) ?>?subject=Contract Renewal - <?= htmlspecialchars($contract['contract_id']) ?>" 
                       class="btn btn-contact">
                        ✉️ Email Customer
                    </a>
                <?php endif; ?>
                <a href="contract_view.php?id=<?= urlencode($contract['contract_id']) ?>" class="btn btn-view">
                    View Details
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Action Needed (31-90 days) -->
<?php if (!empty($actionNeeded)): ?>
<div class="renewal-section">
    <div class="section-header">
        <div class="section-title">
            ⚠️ Action Needed - Start Renewal Conversations
            <span class="priority-badge priority-warning"><?= count($actionNeeded) ?> contracts</span>
        </div>
    </div>
        
    <?php foreach ($actionNeeded as $contract): ?>
        <?php if ($contract['days_to_expiry'] <= 30) continue; // Skip critical ones ?>
        <?php
        $contactName = 'Unknown';
        if ($contract['contact_info']) {
            $contactName = trim($contract['contact_info']['first_name'] . ' ' . $contract['contact_info']['last_name']);
        }
        ?>
        <div class="renewal-card warning">
            <div class="renewal-header-row">
                <div class="contract-info">
                    <h3><?= htmlspecialchars($contract['contract_id']) ?></h3>
                    <div class="contract-meta">
                        <span>👤 <?= htmlspecialchars($contactName) ?></span>
                        <span>🔧 <?= htmlspecialchars($contract['equipment_type']) ?></span>
                    </div>
                </div>
                <div class="expiry-info">
                    <div class="days-remaining warning"><?= $contract['days_to_expiry'] ?></div>
                    <div class="days-label">Days Remaining</div>
                </div>
            </div>
            
            <div class="renewal-details">
                <div class="detail-item">
                    <span class="detail-label">Monthly Fee</span>
                    <span class="detail-value">$<?= number_format((float)$contract['monthly_fee'], 2) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">End Date</span>
                    <span class="detail-value"><?= htmlspecialchars($contract['end_date']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Auto-Renew</span>
                    <span class="detail-value"><?= htmlspecialchars($contract['auto_renew']) ?></span>
                </div>
            </div>
            
            <div class="renewal-actions">
                <a href="contract_view.php?id=<?= urlencode($contract['contract_id']) ?>" class="btn btn-view">
                    View Details
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Upcoming (91-180 days) -->
<?php if (!empty($upcoming)): ?>
<div class="renewal-section">
    <div class="section-header">
        <div class="section-title">
            📅 Upcoming Renewals
            <span class="priority-badge priority-upcoming"><?= count($upcoming) ?> contracts</span>
        </div>
    </div>
    
    <table style="width: 100%; border-collapse: collapse;">
        <thead style="background: #F9FAFB; border-bottom: 2px solid #E5E7EB;">
            <tr>
                <th style="padding: 12px; text-align: left; font-size: 12px; color: #6B7280; text-transform: uppercase;">Contract ID</th>
                <th style="padding: 12px; text-align: left; font-size: 12px; color: #6B7280; text-transform: uppercase;">Equipment</th>
                <th style="padding: 12px; text-align: left; font-size: 12px; color: #6B7280; text-transform: uppercase;">Monthly Fee</th>
                <th style="padding: 12px; text-align: left; font-size: 12px; color: #6B7280; text-transform: uppercase;">End Date</th>
                <th style="padding: 12px; text-align: left; font-size: 12px; color: #6B7280; text-transform: uppercase;">Days Remaining</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($upcoming as $contract): ?>
                <tr style="border-bottom: 1px solid #F3F4F6;">
                    <td style="padding: 12px;">
                        <a href="contract_view.php?id=<?= urlencode($contract['contract_id']) ?>" style="color: #3B82F6; font-weight: 600; text-decoration: none;">
                            <?= htmlspecialchars($contract['contract_id']) ?>
                        </a>
                    </td>
                    <td style="padding: 12px;"><?= htmlspecialchars($contract['equipment_type']) ?></td>
                    <td style="padding: 12px; font-weight: 700;">$<?= number_format((float)$contract['monthly_fee'], 2) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($contract['end_date']) ?></td>
                    <td style="padding: 12px; color: #3B82F6; font-weight: 600;"><?= $contract['days_to_expiry'] ?> days</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- No Renewals Due -->
<?php if (empty($critical) && empty($actionNeeded) && empty($upcoming)): ?>
<div class="renewal-section">
    <div class="empty-state">
        <div class="empty-state-icon">✅</div>
        <h3>All Clear!</h3>
        <p>No contracts expiring in the next 180 days</p>
    </div>
</div>
<?php endif; ?>

<?php include_once(__DIR__ . '/layout_end.php'); ?>
