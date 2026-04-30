
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$basePath = __DIR__ . DIRECTORY_SEPARATOR;
require_once $basePath . 'db_mysql.php';
$conn = get_mysql_connection();
$result = $conn->query("DESCRIBE contracts");
echo '<div style="background:#eef;padding:8px;margin:8px 0;">';
echo '<strong>DESCRIBE contracts output:</strong><br>';
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . '<br>';
}
$conn->close();
// ...main app logic continues below...
// Navbar and layout removed
$contractSchema = require $basePath . 'contract_schema.php';
$contactSchema = require $basePath . 'contact_schema.php';
$customerSchema = require $basePath . 'customer_schema.php';
require_once $basePath . 'db_mysql.php';

function fetch_table_mysql($table, $schema) {
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
    $sql = "SELECT $fields FROM $table";
    // Debug output BEFORE SQL query
    $dbResult = $conn->query("SELECT DATABASE() AS db");
    $dbName = $dbResult ? $dbResult->fetch_assoc()['db'] : 'unknown';
    echo '<div style="background:#eef;padding:8px;margin:8px 0;">';
    echo 'DB Name: ' . htmlspecialchars($dbName) . '<br>';
    echo 'SQL Query: ' . htmlspecialchars($sql) . '<br>';
    echo 'MySQL Host Info: ' . htmlspecialchars($conn->host_info) . '<br>';
    echo 'MySQL Server Info: ' . htmlspecialchars($conn->server_info) . '<br>';
    echo '</div>';

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
                echo '<div style="color:#c00;background:#fee;padding:8px;margin:8px 0;">SQL Error: ' . htmlspecialchars($conn->error) . '<br>Retrying without tank_size...</div>';
                $schemaNoTank = array_filter($schema, function($f) { return $f !== 'tank_size'; });
                $fieldsNoTank = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schemaNoTank));
                $sqlNoTank = "SELECT $fieldsNoTank FROM $table";
                $resultNoTank = $conn->query($sqlNoTank);
                if ($resultNoTank) {
                // NOTE: Feb 2026 - Workaround for persistent MySQL error: 'Unknown column tank_size in field list'.
                // Even though DESCRIBE contracts shows tank_size, SELECT fails. This code retries without tank_size if error occurs.
                // This prevents fatal errors and allows the app to load. Underlying DB issue remains unresolved.
                    while ($row = $resultNoTank->fetch_assoc()) {
                        $rows[] = $row;
                    }
                    $resultNoTank->free();
                } else {
                    echo '<div style="color:#c00;background:#fee;padding:8px;margin:8px 0;">SQL Error: ' . htmlspecialchars($conn->error) . '<br>Query: ' . htmlspecialchars($sqlNoTank) . '</div>';
                }
            } else {
                echo '<div style="color:#c00;background:#fee;padding:8px;margin:8px 0;">SQL Error: ' . htmlspecialchars($conn->error) . '<br>Query: ' . htmlspecialchars($sql) . '</div>';
            }
        }
    } catch (mysqli_sql_exception $e) {
        // Workaround: If error is about 'tank_size', retry without it
        if (strpos($e->getMessage(), "tank_size") !== false) {
            echo '<div style="color:#c00;background:#fee;padding:8px;margin:8px 0;">SQL Exception: ' . htmlspecialchars($e->getMessage()) . '<br>Retrying without tank_size...</div>';
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
                } else {
                    echo '<div style="color:#c00;background:#fee;padding:8px;margin:8px 0;">SQL Error: ' . htmlspecialchars($conn->error) . '<br>Query: ' . htmlspecialchars($sqlNoTank) . '</div>';
                }
            } catch (mysqli_sql_exception $e2) {
                echo '<div style="color:#c00;background:#fee;padding:8px;margin:8px 0;">SQL Exception: ' . htmlspecialchars($e2->getMessage()) . '<br>Query: ' . htmlspecialchars($sqlNoTank) . '</div>';
            }
        } else {
            echo '<div style="color:#c00;background:#fee;padding:8px;margin:8px 0;">SQL Exception: ' . htmlspecialchars($e->getMessage()) . '<br>Query: ' . htmlspecialchars($sql) . '</div>';
        }
    }
    $conn->close();
    return $rows;
}

$contracts = fetch_table_mysql('contracts', $contractSchema);
$contacts = fetch_table_mysql('contacts', $contactSchema);
$customers = fetch_table_mysql('customers', $customerSchema);

$totalActive = 0;
$totalMRR = 0;
$totalARR = 0;
$expiringCount = 0;
$today = strtotime(date('Y-m-d'));
$thirtyDaysOut = strtotime('+30 days');
$ninetyDaysOut = strtotime('+90 days');

foreach ($contracts as &$contract) {
    if (!empty($contract['end_date'])) {
        $endDate = strtotime($contract['end_date']);
        $daysToExpiry = round(($endDate - $today) / 86400);
        $contract['days_to_expiry'] = $daysToExpiry;
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
    if ($contract['contract_status'] === 'Active') {
        $totalActive++;
        $totalMRR += (float)($contract['monthly_fee'] ?? 0);
        $totalARR += (float)($contract['annual_value'] ?? 0);
    }
}
?>

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
            // Restore original layout end
<!-- Action Bar -->
<div class="action-bar">
    <div class="filter-group">
        <select id="statusFilter" onchange="filterContracts()">
            <option value="">All Statuses</option>
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
                    <th>Customer/Contact</th>
                    <th>Equipment Type</th>
                    <th>Tank Quantity</th>
                    <th>Tank Size</th>
                    <th>Monthly Fee</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days to Expiry</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contracts as $contract): ?>
                    <?php
                    $contactName = 'Unknown';
                    foreach ($contacts as $c) {
                        if ($c['contact_id'] === $contract['contact_id']) {
                            $contactName = trim($c['first_name'] . ' ' . $c['last_name']);
                            break;
                        }
                    }
                    $statusClass = 'status-' . strtolower(str_replace(' ', '-', $contract['contract_status']));
                    $expiryClass = 'expiry-' . ($contract['expiry_status'] ?? 'normal');
                    ?>
                    <tr data-status="<?= htmlspecialchars($contract['contract_status']) ?>" 
                        data-type="<?= htmlspecialchars($contract['contract_type']) ?>">
                        <td><strong><?= htmlspecialchars($contract['contract_id']) ?></strong></td>
                        <td><?= htmlspecialchars($contactName) ?></td>
                        <td><?= htmlspecialchars($contract['equipment_type']) ?></td>
                        <td><?= htmlspecialchars($contract['tank_quantity']) ?></td>
                        <td><?= htmlspecialchars($contract['tank_size']) ?></td>
                        <td><strong>$<?= number_format((float)$contract['monthly_fee'], 2) ?></strong></td>
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
</div>

// Layout end removed
