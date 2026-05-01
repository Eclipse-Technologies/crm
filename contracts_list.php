<?php
if (function_exists('opcache_reset')) { opcache_reset(); }
// This file is now replaced by contracts_list_new.php. Safe to delete.
if (function_exists('opcache_reset')) { opcache_reset(); }
$basePath = __DIR__ . DIRECTORY_SEPARATOR;
require_once $basePath . 'layout_start.php';

$pageTitle = 'Service Contracts';
$currentPage = basename(__FILE__);

// Load data
$contractSchema = require $basePath . 'contract_schema.php';
$contactSchema = require $basePath . 'contact_schema.php';
$customerSchema = require $basePath . 'customer_schema.php';

require_once $basePath . 'db_mysql.php';

function fetch_table_mysql($table, $schema) {
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
    $sql = "SELECT $fields FROM $table";
    // NOTE: Feb 2026 - Workaround for persistent MySQL error: 'Unknown column tank_size in field list'.
    // Even though DESCRIBE contracts shows tank_size, SELECT fails. This code retries without tank_size if error occurs.
    // This prevents fatal errors and allows the app to load. Underlying DB issue remains unresolved.
    // UI/UX debug output removed for production.
    // NOTE: Feb 2026 - Workaround for persistent MySQL error: 'Unknown column tank_size in field list'.
    // Even though DESCRIBE contracts shows tank_size, SELECT fails. This code retries without tank_size if error occurs.
    // This prevents fatal errors and allows the app to load. Underlying DB issue remains unresolved.
    $rows = [];
    try {
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
        } else {
            // Workaround: If error is about 'tank_size', retry without it
            if (strpos($conn->error, "tank_size") !== false) {
                $schemaNoTank = array_filter($schema, function($f) { return $f !== 'tank_size'; });
                $fieldsNoTank = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schemaNoTank));
                $sqlNoTank = "SELECT $fieldsNoTank FROM $table";
                $resultNoTank = $conn->query($sqlNoTank);
                if ($resultNoTank) {
                    while ($row = $resultNoTank->fetch_assoc()) {
                        $rows[] = $row;
                    }
                    $resultNoTank->free();
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
        // Workaround: If error is about 'tank_size', retry without it
        if (strpos($e->getMessage(), "tank_size") !== false) {
            $schemaNoTank = array_filter($schema, function($f) { return $f !== 'tank_size'; });
            $fieldsNoTank = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schemaNoTank));
            $sqlNoTank = "SELECT $fieldsNoTank FROM $table";
            try {
                $resultNoTank = $conn->query($sqlNoTank);
                if ($resultNoTank) {
                    while ($row = $resultNoTank->fetch_assoc()) {
                        $rows[] = $row;
                    }
                    $resultNoTank->free();
                }
            } catch (mysqli_sql_exception $e2) {
                // Suppress secondary SQL exceptions for UI/UX
            }
        }
    }
    $conn->close();
    return $rows;
}

$contracts = fetch_table_mysql('contracts', $contractSchema);
$contacts = fetch_table_mysql('contacts', $contactSchema);
$customers = fetch_table_mysql('customers', $customerSchema);

// Calculate contract metrics
$totalActive = 0;
$totalMRR = 0;
$totalARR = 0;
$expiringCount = 0;
$today = strtotime(date('Y-m-d'));
$thirtyDaysOut = strtotime('+30 days');
$ninetyDaysOut = strtotime('+90 days');

foreach ($contracts as &$contract) {
        // Calculate annual value (prorated for first year if needed)
        $monthly = (float)($contract['monthly_fee'] ?? 0);
        $startDate = !empty($contract['start_date']) ? strtotime($contract['start_date']) : false;
        $endDate = !empty($contract['end_date']) ? strtotime($contract['end_date']) : false;
        $yearStart = strtotime(date('Y-01-01', $startDate ?: $today));
        $yearEnd = strtotime(date('Y-12-31', $startDate ?: $today));
        $daysInYear = 365 + (date('L', $yearStart) ? 1 : 0);
        if ($startDate && $startDate > $yearStart && $startDate < $yearEnd) {
            // Prorate for first year
            $daysActive = $yearEnd - $startDate;
            $daysActive = $daysActive > 0 ? $daysActive / 86400 : 0;
            $contract['annual_value_calc'] = round($monthly * 12 * ($daysActive / $daysInYear), 2);
            $contract['annual_value_note'] = 'Prorated';
        } else {
            $contract['annual_value_calc'] = round($monthly * 12, 2);
            $contract['annual_value_note'] = '';
        }

        // Calculate regeneration total for the year
        $regenFee = (float)($contract['regen_fee'] ?? 0);
        $serviceFreq = strtolower(trim($contract['service_frequency'] ?? ''));
        $regenCount = 0;
        if ($serviceFreq === 'weekly') $regenCount = 52;
        elseif ($serviceFreq === 'bi-weekly' || $serviceFreq === 'biweekly') $regenCount = 26;
        elseif ($serviceFreq === 'monthly') $regenCount = 12;
        elseif ($serviceFreq === 'quarterly') $regenCount = 4;
        elseif (is_numeric($serviceFreq)) $regenCount = (int)$serviceFreq;
        $contract['regen_total'] = round($regenFee * $regenCount, 2);
        $contract['regen_count'] = $regenCount;
    // Calculate days until expiry
    if (!empty($contract['end_date'])) {
        $endDate = strtotime($contract['end_date']);
        $daysToExpiry = round(($endDate - $today) / 86400);
        $contract['days_to_expiry'] = $daysToExpiry;
        // Set expiry status
        if ($daysToExpiry < 0) {
            $contract['expiry_status'] = 'expired';
        } elseif ($daysToExpiry <= 30) {
            $contract['expiry_status'] = 'critical';
            $expiringCount++;
        } elseif ($daysToExpiry <= 90) {
            $contract['expiry_status'] = 'warning';
            $expiringCount++;
        } else {
            $contract['expiry_status'] = 'normal';
        }
    }
    // Calculate metrics for active contracts

    if ($contract['contract_status'] === 'Active') {
        $totalActive++;
        $totalMRR += (float)($contract['monthly_fee'] ?? 0);
        $totalARR += (float)($contract['annual_value'] ?? 0);
    }
}
?>
<style>
.metric-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #10B981; }
.metric-card.warning { border-left-color: #F59E0B; }
.metric-card.critical { border-left-color: #EF4444; }
.metric-label { font-size: 13px; color: #6B7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.metric-value { font-size: 32px; font-weight: 700; color: #1F2937; }
.metric-subtext { font-size: 12px; color: #9CA3AF; margin-top: 4px; }
.action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
.filter-group { display: flex; gap: 12px; align-items: center; }
.filter-group select, .filter-group input { padding: 10px 16px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 14px; }
.btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; font-size: 14px; }
.btn-primary { background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
.contracts-table { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.contracts-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.contracts-table table { width: 100%; min-width: 900px; border-collapse: collapse; }
.contracts-table th { background: #F9FAFB; padding: 16px; text-align: left; font-size: 12px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #E5E7EB; }
.contracts-table td { padding: 16px; border-bottom: 1px solid #F3F4F6; font-size: 14px; }
.contracts-table tr:hover { background: #F9FAFB; }
.status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
.status-active { background: #D1FAE5; color: #065F46; }
.status-expiring { background: #FEF3C7; color: #92400E; }
.status-expired { background: #FEE2E2; color: #991B1B; }
.status-cancelled { background: #E5E7EB; color: #374151; }
.expiry-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block; }
.expiry-normal { background: #D1FAE5; color: #065F46; }
.expiry-warning { background: #FEF3C7; color: #92400E; }
.expiry-critical { background: #FEE2E2; color: #991B1B; }
.expiry-expired { background: #E5E7EB; color: #374151; }
.action-btns { display: flex; gap: 8px; }
.action-btn { padding: 6px 12px; border-radius: 6px; font-size: 12px; text-decoration: none; font-weight: 600; transition: all 0.2s; }
.action-btn-view { background: #EFF6FF; color: #1E40AF; }
.action-btn-view:hover { background: #DBEAFE; }
.action-btn-edit { background: #FEF3C7; color: #92400E; }
.action-btn-edit:hover { background: #FDE68A; }
.action-btn-renew { background: #D1FAE5; color: #065F46; }
.action-btn-renew:hover { background: #A7F3D0; }
.empty-state { text-align: center; padding: 60px 20px; color: #6B7280; }
.empty-state-icon { font-size: 64px; margin-bottom: 16px; }
@media (max-width: 768px) { .metrics-grid { grid-template-columns: 1fr; } .action-bar { flex-direction: column; align-items: stretch; } .filter-group { flex-direction: column; } }
</style>

<div class="contracts-header">
    <h1>📋 Service Contracts</h1>
    <p>Manage SDI service agreements and equipment rentals</p>
</div>


<!-- Metrics Dashboard -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-label">Active Contracts</div>
        <div class="metric-value"><?= $totalActive ?></div>
        <div class="metric-subtext">Current active agreements</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Monthly Recurring Revenue</div>
        <div class="metric-value">$<?= number_format($totalMRR, 0) ?></div>
        <div class="metric-subtext">MRR from active contracts</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Annual Contract Value</div>
        <div class="metric-value">$<?= number_format($totalARR, 0) ?></div>
        <div class="metric-subtext">Total ARR from contracts</div>
    </div>
    <div class="metric-card <?= $expiringCount > 0 ? 'warning' : '' ?>">
        <div class="metric-label">Expiring Soon</div>
        <div class="metric-value"><?= $expiringCount ?></div>
        <div class="metric-subtext">Within 90 days</div>
    </div>
</div>

<!-- Action Bar -->
<div class="action-bar">
    <div class="filter-group">
        <select id="statusFilter" onchange="filterContracts()">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Expiring">Expiring</option>
            <option value="Expired">Expired</option>
            <option value="Cancelled">Cancelled</option>
        </select>
        <select id="typeFilter" onchange="filterContracts()">
            <option value="">All Types</option>
            <option value="New">New</option>
            <option value="Renewal">Renewal</option>
            <option value="Upsell">Upsell</option>
        </select>
        <input type="text" id="searchInput" placeholder="Search contracts..." onkeyup="filterContracts()">
    </div>
    <a href="contract_form.php" class="btn btn-primary">➕ Add New Contract</a>
</div>

<!-- Contracts Table -->
<div class="contracts-table">
<div class="contracts-table-scroll">
    <?php if (empty($contracts)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3>No Service Contracts Yet</h3>
            <p>Start by creating your first service contract</p>
            <a href="contract_form.php" class="btn btn-primary" style="margin-top: 20px;">➕ Add First Contract</a>
        </div>
    <?php else: ?>
        <table id="contractsTable">
            <thead>
                <tr>
                    <th>Contract ID</th>
                    <th>Company</th>
                    <th>First Name</th>
                    <th>Email</th>
                    <th>Is Customer</th>
                    <th>Equipment Type</th>
                    <th>Tank Quantity</th>
                    <th>Tank Size</th>
                    <th>Monthly Fee</th>
                                        <th>Annual Contract Value ($)</th>
                                        <th>Regeneration Total</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days to Expiry</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $renderedContracts = [];
                foreach ($contracts as $contract):
                    if (in_array($contract['contract_id'], $renderedContracts, true)) continue;
                    $renderedContracts[] = $contract['contract_id'];
                    $contactInfo = [
                        'company' => '—',
                        'first_name' => '—',
                        'email' => '—',
                        'is_customer' => '—'
                    ];
                    foreach ($contacts as $c) {
                        if ((isset($c['contact_id']) && $c['contact_id'] === $contract['contact_id']) || (isset($c['id']) && $c['id'] === $contract['contact_id'])) {
                            $contactInfo['company'] = $c['company'] ?? '—';
                            $contactInfo['first_name'] = $c['first_name'] ?? '—';
                            $contactInfo['email'] = $c['email'] ?? '—';
                            $contactInfo['is_customer'] = isset($c['is_customer']) ? ($c['is_customer'] ? 'Yes' : 'No') : '—';
                            break;
                        }
                    }
                    $statusClass = 'status-' . strtolower(str_replace(' ', '-', $contract['contract_status']));
                    $expiryClass = 'expiry-' . ($contract['expiry_status'] ?? 'normal');
                ?>
                    <tr data-status="<?= htmlspecialchars($contract['contract_status']) ?>" 
                        data-type="<?= htmlspecialchars($contract['contract_type']) ?>">
                        <td><strong><?= htmlspecialchars($contract['contract_id']) ?></strong></td>
                        <td><?= htmlspecialchars($contactInfo['company']) ?></td>
                        <td><?= htmlspecialchars($contactInfo['first_name']) ?></td>
                        <td><?= htmlspecialchars($contactInfo['email']) ?></td>
                        <td><?= htmlspecialchars($contactInfo['is_customer']) ?></td>
                        <td><?= htmlspecialchars($contract['equipment_type']) ?></td>
                        <td><?= htmlspecialchars($contract['tank_quantity']) ?></td>
                        <td><?= htmlspecialchars($contract['tank_size']) ?></td>
                        <td><strong>$<?= number_format((float)$contract['monthly_fee'], 2) ?></strong></td>
                        <td>
                            $<?= number_format((float)($contract['annual_value'] ?? 0), 2) ?>
                        </td>
                        <td>
                            $<?= number_format($contract['regen_total'], 2) ?>
                            <span style="color:#888;font-size:11px;">(<?= $contract['regen_count'] ?>x)</span>
                        </td>
                        <td>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= htmlspecialchars($contract['contract_status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($contract['start_date']) ?></td>
                        <td><?= htmlspecialchars($contract['end_date']) ?></td>
                        <td>
                            <?php if (isset($contract['days_to_expiry'])): ?>
                                <span class="expiry-badge <?= $expiryClass ?>">
                                    <?php if ($contract['days_to_expiry'] < 0): ?>
                                        Expired
                                    <?php else: ?>
                                        <?= $contract['days_to_expiry'] ?> days
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="contract_view.php?id=<?= urlencode($contract['contract_id']) ?>" class="action-btn action-btn-view">View</a>
                                <a href="contract_edit.php?id=<?= urlencode($contract['contract_id']) ?>" class="action-btn action-btn-edit">Edit</a>
                                <?php if ($contract['contract_status'] === 'Active' && isset($contract['days_to_expiry']) && $contract['days_to_expiry'] <= 90): ?>
                                    <a href="contract_renew.php?id=<?= urlencode($contract['contract_id']) ?>" class="action-btn action-btn-renew">Renew</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div><!-- end contracts-table-scroll -->
</div><!-- end contracts-table -->
