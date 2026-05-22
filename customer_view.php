<?php
// --- ALL SESSION/CSRF/DEPENDENCY LOGIC MUST BE AT THE VERY TOP, NO OUTPUT OR WHITESPACE BEFORE THIS BLOCK ---
require_once __DIR__ . '/csrf_helper.php';
ensureCSRFSessionStarted();
require_once __DIR__ . '/db_mysql.php';

$customerId = $_GET['id'] ?? '';

// Handle discussion log form submission for linked contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_discussion'])) {
    $contactIdRaw = $_POST['contact_id'] ?? '';
    $contactId = intval($contactIdRaw);
    $author = trim((string) ($_POST['author'] ?? ''));
    $entryText = trim((string) ($_POST['entry_text'] ?? ''));
    $linkedOppId = trim((string) ($_POST['linked_opportunity_id'] ?? ''));
    $visibility = 'private';

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo "<div class='alert alert-danger'>Security validation failed. Please refresh and try again.</div>";
    } elseif (!$contactIdRaw || !is_numeric($contactIdRaw)) {
        echo "<div class='alert alert-danger'>Invalid contact ID.</div>";
    } elseif (!$author || !$entryText) {
        echo "<div class='alert alert-danger'>All required fields must be filled out.</div>";
    } else {
        $conn = get_mysql_connection();
        $sql = "INSERT INTO discussion_log (contact_id, author, entry_text, linked_opportunity_id, visibility, timestamp) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $linkedOppIdNull = ($linkedOppId === '') ? null : $linkedOppId;
            $stmt->bind_param('issss', $contactId, $author, $entryText, $linkedOppIdNull, $visibility);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    }
}
require_once __DIR__ . '/inventory_mysql.php';
$inventory = fetch_inventory_mysql(require __DIR__ . '/inventory_schema.php');

// Handle assigning number of tanks from pool
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_tank_from_pool') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo "<div class='alert alert-danger'>CSRF token validation failed.</div>";
    } else {
        $numTanks = max(0, intval($_POST['pool_tank_count'] ?? 0));
        $conn = get_mysql_connection();
        // Find all available pool rental tanks (ownership = rental, customer_id IS NULL)
        $stmt = $conn->prepare("SELECT equipment_id FROM equipment WHERE LOWER(ownership) IN ('rental', 'evoqua rental', 'lease', 'leased', 'evoqua lease') AND (customer_id IS NULL OR customer_id = '') ORDER BY equipment_id ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $availablePoolTanks = [];
        while ($row = $result->fetch_assoc()) {
            $availablePoolTanks[] = $row['equipment_id'];
        }
        $stmt->close();

        // Find all rental tanks currently assigned to this customer
        $stmt = $conn->prepare("SELECT equipment_id FROM equipment WHERE LOWER(ownership) IN ('rental', 'evoqua rental', 'lease', 'leased', 'evoqua lease') AND customer_id = ? ORDER BY equipment_id ASC");
        $stmt->bind_param('s', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentlyAssigned = [];
        while ($row = $result->fetch_assoc()) {
            $currentlyAssigned[] = $row['equipment_id'];
        }
        $stmt->close();

        // Calculate how many to add or remove
        $toAssign = $numTanks - count($currentlyAssigned);
        if ($toAssign > 0) {
            // Assign tanks from pool to this customer
            $assignIds = array_slice($availablePoolTanks, 0, $toAssign);
            foreach ($assignIds as $eqId) {
                $stmt = $conn->prepare("UPDATE equipment SET customer_id = ? WHERE equipment_id = ?");
                $stmt->bind_param('ss', $customerId, $eqId);
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($toAssign < 0) {
            // Unassign tanks from this customer (return to pool)
            $removeIds = array_slice($currentlyAssigned, $toAssign); // negative offset
            foreach ($removeIds as $eqId) {
                $stmt = $conn->prepare("UPDATE equipment SET customer_id = NULL WHERE equipment_id = ?");
                $stmt->bind_param('s', $eqId);
                $stmt->execute();
                $stmt->close();
            }
        }
        $conn->close();
        echo "<div class='alert alert-success'>Updated number of pool rental tanks assigned to customer.</div>";
    }
}
$customerSchema = require __DIR__ . '/customer_schema.php';
$equipmentSchema = require __DIR__ . '/equipment_schema.php';

$contractSchema = require __DIR__ . '/contract_schema.php';

// ── Fetch equipment for this customer ───────────────────────────────────────
$customerOwnedEquipment = [];
$serviceEquipment = [];
if ($customerId !== '') {
    $conn = get_mysql_connection();
    $stmt = $conn->prepare("SELECT * FROM equipment WHERE customer_id = ?");
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ownership = strtolower(trim($row['ownership'] ?? ''));
        if ($ownership === 'customer owned' || $ownership === 'customer-owned') {
            $customerOwnedEquipment[] = $row;
        } elseif ($ownership === 'rental' || $ownership === 'lease' || $ownership === 'leased' || $ownership === 'evoqua rental' || $ownership === 'evoqua lease') {
            $serviceEquipment[] = $row;
        }
    }
    $stmt->close();
    $conn->close();
}

// ── Layout start ─────────────────────────────────────────────────────────────
include_once(__DIR__ . '/layout_start.php');

// ── Load customer ────────────────────────────────────────────────────────────
$customerId = $_GET['id'] ?? '';
$customer = null;
$contact = null;
if ($customerId !== '') {
    $conn = get_mysql_connection();
    // Fetch customer
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    // Fetch linked contact
    if ($customer && !empty($customer['contact_id'])) {
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE contact_id = ?");
        $stmt->bind_param('i', $customer['contact_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $contact = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
    $conn->close();
}
if (!$customer) {
    echo "<div class='container'><h2>❌ Customer not found</h2></div>";
    include_once(__DIR__ . '/layout_end.php');
    exit;
}
// ── Fetch contracts for this customer ────────────────────────────────────────
$contracts = [];
$totalMRR = 0;
$totalARR = 0;
$activeCount = 0;
if ($customerId !== '') {
    $conn = get_mysql_connection();
    $stmt = $conn->prepare("SELECT * FROM contracts WHERE customer_id = ? ORDER BY contract_status DESC, start_date DESC");
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['contract_status'] === 'Active') {
            $totalMRR += (float)($row['monthly_fee'] ?? 0);
            $totalARR += (float)($row['annual_value'] ?? 0);
            $activeCount++;
        }
        $contracts[] = $row;
    }
    $stmt->close();
    $conn->close();
}
?>
<style>
.cv-header-meta { margin-bottom: 14px; color: #4b5563; }
.cv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
.cv-grid-tight { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.cv-fieldset { margin-bottom: 20px; }
.cv-table-wrap { overflow-x: auto; }
.cv-muted { color: #6b7280; font-size: 13px; }
.cv-readonly { background: #eee; }
.cv-kpi-wrap { max-width: 340px; margin-bottom: 16px; }
.cv-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 12px; }
.cv-kpi-card { background: #f8f9fa; padding: 16px; border-radius: 8px; text-align: center; }
.cv-kpi-label { font-size: 13px; color: #6b7280; font-weight: 600; text-transform: uppercase; }
.cv-kpi-value { font-size: 28px; font-weight: 700; }
.cv-kpi-sub { font-size: 12px; color: #9ca3af; }
.cv-action-row { margin-top: 12px; }
.cv-nav { margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end; }
.cv-tip { font-size: 12px; color: #888; margin-top: 8px; }
.cv-empty { padding: 20px; background: #f8f9fa; border-radius: 4px; color: #999; text-align: center; font-size: 13px; }
.cv-timeline-item { padding: 15px; margin-bottom: 12px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #3B82F6; }
.cv-timeline-head { display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px; }
.cv-timeline-author { color: #1a1a1a; font-size: 14px; }
.cv-timeline-meta { color: #666; font-size: 11px; margin-bottom: 8px; }
.cv-timeline-body { color: #1a1a1a; font-size: 13px; line-height: 1.5; margin-bottom: 6px; }
.cv-linked { margin-top: 8px; padding: 8px; background: white; border-left: 3px solid #10B981; border-radius: 3px; font-size: 11px; color: #666; }
.cv-discussions { margin-bottom: 32px; }
.cv-visibility-badge { padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
.cv-visibility-public { background: #e3f2fd; color: #1976d2; }
.cv-visibility-internal { background: #fff3cd; color: #856404; }
.cv-visibility-private { background: #f8d7da; color: #721c24; }
.cv-manual-link { margin-left: 10px; color: #8B5CF6; font-weight: 600; font-size: 11px; }
</style>
<div class="main-content">
    <div class="content-container">
        <div class="container">


        <!-- Header -->
        <h2>🏢 <?= htmlspecialchars($customer['address'] ?? 'Customer ' . $customerId) ?></h2>
        <div class="cv-header-meta">
            <strong>Customer ID:</strong> <?= htmlspecialchars($customerId) ?><br>
            <?php if ($contact): ?>
                <strong>Contact:</strong> <?= htmlspecialchars($contact['company'] ?? 'N/A') ?><br>
            <?php endif; ?>
        </div>

    <!-- ── CUSTOMER INFO FORM (Editable inline) ────────────────────────────────── -->

        <form method="post" style="margin-bottom:24px;">
            <?php renderCSRFInput(); ?>
            <input type="hidden" name="action" value="update_customer">
            <fieldset class="cv-fieldset">
                <legend><strong>📋 Customer Information</strong></legend>
                <div class="cv-grid">
                    <?php foreach ($customerSchema as $field): ?>
                        <div>
                            <label><strong><?= ucfirst(str_replace('_', ' ', $field)) ?></strong></label><br>
                            <?php if ($field === 'customer_id'): ?>
                                <input type="text" value="<?= htmlspecialchars($customer[$field]) ?>" readonly class="cv-readonly">
                            <?php elseif ($field === 'contact_id'): ?>
                                <select name="<?= $field ?>">
                                    <option value="">-- Select Contact --</option>
                                    <?php
                                    $conn = get_mysql_connection();
                                    $result = $conn->query("SELECT contact_id, company FROM contacts ORDER BY company");
                                    while ($row = $result ? $result->fetch_assoc() : null) {
                                        $selected = ($row['contact_id'] == $customer['contact_id']) ? 'selected' : '';
                                        echo "<option value='{$row['contact_id']}' $selected>{$row['company']}</option>";
                                    }
                                    if ($result) $result->free();
                                    $conn->close();
                                    ?>
                                </select>
                            <?php else: ?>
                                <input type="text" name="<?= $field ?>" value="<?= htmlspecialchars($customer[$field] ?? '') ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button type="submit" class="btn-outline">💾 Save Customer Info</button>
        </form>

    <!-- ── RENTED TANKS SUMMARY ───────────────────────────────────────────── -->

    <?php
    // New logic: rented tanks summary is based on serviceEquipment
    $rentedCount = count($serviceEquipment);
    $rentedSizes = array_map(function($item) {
        return $item['tank_size'] ?? ($item['equipment_type'] ?? '');
    }, $serviceEquipment);
    ?>

        <fieldset class="cv-fieldset">
            <legend><strong>🛢️ Rented Tanks Summary</strong></legend>
            <div>
                <strong>Number of Rented Tanks:</strong> <?= $rentedCount ?><br>
                <strong>Sizes:</strong> <?= $rentedCount > 0 ? htmlspecialchars(implode(', ', $rentedSizes)) : 'N/A' ?>
            </div>
        </fieldset>

    <!-- ── TANK ASSIGNMENT (POOL ONLY, MODERN CSS) ───────────────────────────── -->


        <form method="post" style="margin-bottom:24px;">
            <?php renderCSRFInput(); ?>
            <input type="hidden" name="action" value="assign_tank_from_pool">
            <fieldset>
                <legend><strong>🛢️ Assign Tank from Pool</strong></legend>
                <div class="cv-grid">
                    <div>
                        <label><strong>Number of Pool Tanks Assigned</strong></label><br>
                        <?php
                        // New logic: count rental tanks assigned to this customer and available in pool
                        $currentAssigned = count($serviceEquipment);
                        // Find available pool rental tanks (ownership = rental, customer_id IS NULL)
                        $conn = get_mysql_connection();
                        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM equipment WHERE LOWER(ownership) IN ('rental', 'evoqua rental', 'lease', 'leased', 'evoqua lease') AND (customer_id IS NULL OR customer_id = '')");
                        $stmt->execute();
                        $stmt->bind_result($maxPool);
                        $stmt->fetch();
                        $stmt->close();
                        $conn->close();
                        ?>
                        <input type="number" name="pool_tank_count" min="0" max="<?= $maxPool ?>" value="<?= $currentAssigned ?>" style="width:100px;">
                        <span class="cv-muted">(of <?= $maxPool ?> available in pool)</span>
                    </div>
                </div>
            </fieldset>
            <button type="submit" class="btn-outline">💾 Update Pool Tank Count</button>
        </form>

    <!-- ── CONTACT INFORMATION ────────────────────────────────────────────────── -->

        <?php if ($contact): ?>
            <fieldset class="cv-fieldset">
                <legend><strong>👤 Linked Contact</strong></legend>
                <div class="cv-grid-tight">
                    <div><strong>Company:</strong> <?= htmlspecialchars($contact['company'] ?? 'N/A') ?></div>
                    <div><strong>Contact Person:</strong> <?= htmlspecialchars(trim((string) (($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))) ?: 'N/A') ?></div>
                    <div><strong>Phone:</strong> <?= htmlspecialchars($contact['phone'] ?? 'N/A') ?></div>
                    <div><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($contact['email'] ?? '') ?>"><?= htmlspecialchars($contact['email'] ?? 'N/A') ?></a></div>
                    <div style="grid-column:1/-1;"><strong>Address:</strong> <?= htmlspecialchars($contact['address'] ?? 'N/A') ?></div>
                </div>
            </fieldset>
        <?php endif; ?>

    <!-- ── EQUIPMENT INVENTORY ────────────────────────────────────────────────── -->

        <fieldset class="cv-fieldset">
            <legend><strong>🛢️ Customer-Owned Tanks (<?= count($customerOwnedEquipment ?? []) ?>)</strong></legend>
            <div class="location-legend mb-2">
                <span class="location-chip location-pool">pool</span>
                <span class="location-chip location-production">production</span>
                <span class="location-chip location-warehouse">warehouse</span>
                <span class="location-chip location-customer-site">customer site</span>
            </div>
            <?php if (!empty($customerOwnedEquipment)): ?>
                <div class="cv-table-wrap">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Serial #</th>
                                <th>Tank Size</th>
                                <th>Resin Part #</th>
                                <th>Ownership</th>
                                <th>Location</th>
                                <th>Service Frequency</th>
                                <th>Install Date</th>
                                <th>Last Service</th>
                                <th>Next Service</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerOwnedEquipment as $eq): ?>
                                <?php
                                $resinComponent = $componentsByEquipment[$eq['equipment_id']]['resin'] ?? null;
                                $resinPartNumber = $resinComponent['item_id'] ?? ($eq['resin_type'] ?? '');
                                $resinQty = isset($resinComponent['quantity_required']) ? rtrim(rtrim(number_format((float) $resinComponent['quantity_required'], 3, '.', ''), '0'), '.') : '';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($eq['equipment_type'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['serial_number'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['tank_size'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($resinPartNumber !== '' ? $resinPartNumber . ($resinQty !== '' ? ' (Qty: ' . $resinQty . ')' : '') : 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= ucfirst($eq['ownership'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $locationRaw = strtolower(trim((string) ($eq['location'] ?? '')));
                                        $locationClass = 'location-other';
                                        if ($locationRaw === 'pool') {
                                            $locationClass = 'location-pool';
                                        } elseif ($locationRaw === 'production') {
                                            $locationClass = 'location-production';
                                        } elseif ($locationRaw === 'warehouse') {
                                            $locationClass = 'location-warehouse';
                                        } elseif ($locationRaw === 'customer site') {
                                            $locationClass = 'location-customer-site';
                                        }
                                        $locationLabel = $locationRaw !== '' ? $locationRaw : 'n/a';
                                        ?>
                                        <span class="location-chip <?= htmlspecialchars($locationClass) ?>"><?= htmlspecialchars($locationLabel) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($eq['service_frequency'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['install_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['last_service_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['next_service_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['status'] ?? 'Active') ?></td>
                                    <td>
                                        <a href="equipment_view.php?id=<?= urlencode($eq['equipment_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">No customer-owned tanks tracked yet.</div>
            <?php endif; ?>
        </fieldset>


        <fieldset class="cv-fieldset">
            <legend><strong>🔁 Service and Rental Tanks At This Site (<?= count($serviceEquipment ?? []) ?>)</strong></legend>
            <?php if (!empty($serviceEquipment)): ?>
            <div class="cv-table-wrap">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Serial #</th>
                                <th>Tank Size</th>
                                <th>Resin Part #</th>
                                <th>Ownership</th>
                                <th>Location</th>
                                <th>Service Frequency</th>
                                <th>Install Date</th>
                                <th>Last Service</th>
                                <th>Next Service</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($serviceEquipment as $eq): ?>
                                <?php
                                $resinComponent = $componentsByEquipment[$eq['equipment_id']]['resin'] ?? null;
                                $resinPartNumber = $resinComponent['item_id'] ?? ($eq['resin_type'] ?? '');
                                $resinQty = isset($resinComponent['quantity_required']) ? rtrim(rtrim(number_format((float) $resinComponent['quantity_required'], 3, '.', ''), '0'), '.') : '';
                                $eqStatus = $eq['status'] ?? 'Active';
                                $isTrial  = strtolower($eqStatus) === 'trial';
                                ?>
                                <tr<?= $isTrial ? ' style="background:#fffbe6;"' : '' ?>>
                                    <td><?= htmlspecialchars($eq['equipment_type'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['serial_number'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['tank_size'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($resinPartNumber !== '' ? $resinPartNumber . ($resinQty !== '' ? ' (Qty: ' . $resinQty . ')' : '') : 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= ucfirst($eq['ownership'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $locationRaw = strtolower(trim((string) ($eq['location'] ?? '')));
                                        $locationClass = 'location-other';
                                        if ($locationRaw === 'pool') {
                                            $locationClass = 'location-pool';
                                        } elseif ($locationRaw === 'production') {
                                            $locationClass = 'location-production';
                                        } elseif ($locationRaw === 'warehouse') {
                                            $locationClass = 'location-warehouse';
                                        } elseif ($locationRaw === 'customer site') {
                                            $locationClass = 'location-customer-site';
                                        }
                                        $locationLabel = $locationRaw !== '' ? $locationRaw : 'n/a';
                                        ?>
                                        <span class="location-chip <?= htmlspecialchars($locationClass) ?>"><?= htmlspecialchars($locationLabel) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($eq['service_frequency'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['install_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['last_service_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['next_service_date'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($isTrial): ?>
                                            <span class="badge bg-warning text-dark">Trial</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($eqStatus) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="equipment_view.php?id=<?= urlencode($eq['equipment_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php if ($isTrial): ?>
                                            <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>&contact_id=<?= urlencode($customer['contact_id'] ?? '') ?>" class="btn btn-sm btn-success ms-1">📄 Create Contract</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="cv-action-row">
                    <a href="add_customer.php?contact_id=<?= urlencode($customer['contact_id']) ?>" class="btn-outline">➕ Add Equipment</a>
                </div>
            <?php else: ?>
                <div class="no-data">No service/rental tanks assigned at this site. <a href="add_customer.php?contact_id=<?= urlencode($customer['contact_id']) ?>">Add equipment</a></div>
            <?php endif; ?>
        </fieldset>

    <!-- ── CONTRACTS & REVENUE ────────────────────────────────────────────────── -->

    <?php if ($activeCount > 0): ?>
        <div class="cv-kpi-wrap">
            <div class="cv-kpi-card">
                <div class="cv-kpi-label">Annual Value</div>
                <div class="cv-kpi-value">$<?= number_format($totalARR, 2) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <fieldset class="cv-fieldset">
        <legend><strong>💰 Service Contracts & Revenue</strong></legend>
        <?php if ($activeCount > 0): ?>
            <div class="cv-kpi-grid">
                <div class="cv-kpi-card">
                    <div class="cv-kpi-label">Active Contracts</div>
                    <div class="cv-kpi-value"><?= $activeCount ?></div>
                </div>
                <div class="cv-kpi-card">
                    <div class="cv-kpi-label">Monthly Recurring Revenue</div>
                    <div class="cv-kpi-value">$<?= number_format($totalMRR, 2) ?></div>
                    <div class="cv-kpi-sub"><?= $activeCount ?> active contract<?= $activeCount !== 1 ? 's' : '' ?></div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($contracts)): ?>
            <div class="cv-table-wrap">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Contract ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Monthly Rental</th>
                            <th>Regen Fee</th>
                            <th>Delivery Fee</th>
                            <th>Annual Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['contract_id']) ?></strong></td>
                                <td><?= htmlspecialchars($c['contract_type'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <?= htmlspecialchars($c['contract_status']) ?>
                                    </span>
                                </td>
                                <td>$<?= number_format((float)($c['monthly_fee'] ?? 0), 2) ?></td>
                                <td>$<?= number_format((float)($c['regen_fee'] ?? 0), 2) ?></td>
                                <td>$<?= number_format((float)($c['tank_sale_price'] ?? 0), 2) ?></td>
                                <td><strong>$<?= number_format((float)($c['annual_value'] ?? 0), 2) ?></strong></td>
                                <td>
                                    <a href="contract_view.php?id=<?= urlencode($c['contract_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    <a href="contract_edit.php?id=<?= urlencode($c['contract_id']) ?>" class="btn btn-sm btn-outline-secondary ms-1">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="cv-action-row">
                <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>" class="btn-outline">➕ New Contract</a>
            </div>
        <?php else: ?>
            <div class="no-data">No contracts found. <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>">Create a new contract</a></div>
        <?php endif; ?>
    </fieldset>


    <!-- ── ADD COMMUNICATION / DISCUSSION LOG FORM ─────────────────────── -->
    <?php
    // The customer has one linked contact (customers.contact_id → contacts.contact_id).
    // There is no customer_id column in contacts, so build the list from $contact.
    $customerContacts = $contact ? [$contact] : [];
    ?>
    <div class="form-section" style="margin-bottom:32px;">
        <h3>Add Communication / Discussion Log</h3>
        <form method="post" action="">
            <?php renderCSRFInput(); ?>
            <div class="form-group">
                <label for="contact_id">Contact</label>
                <select id="contact_id" name="contact_id" class="form-control" required>
                    <option value="">-- Select Contact --</option>
                    <?php foreach ($customerContacts as $c): ?>
                        <option value="<?= intval($c['contact_id']) ?>" selected>ID: <?= intval($c['contact_id']) ?> - <?= htmlspecialchars($c['company'] ?: ($c['first_name'] . ' ' . $c['last_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="entry_text">Notes / Communication</label>
                <textarea id="entry_text" name="entry_text" class="form-control" rows="4" required placeholder="Enter details of your call, meeting, email, or note..."></textarea>
            </div>
            <div class="form-group">
                <label for="linked_opportunity_id">Linked Opportunity (Optional)</label>
                <select id="linked_opportunity_id" name="linked_opportunity_id" class="form-control">
                    <option value="">-- None --</option>
                    <?php
                    // Fetch opportunities for this contact
                    $opps = [];
                    $conn = get_mysql_connection();
                    $stmt = $conn->prepare("SELECT opportunity_id, name, stage FROM opportunities WHERE contact_id = ?");
                    $stmt->bind_param('s', $contact['contact_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result ? $result->fetch_assoc() : null) {
                        $opps[] = $row;
                    }
                    $stmt->close();
                    $conn->close();
                    foreach ($opps as $opp): ?>
                        <option value="<?= htmlspecialchars($opp['opportunity_id']) ?>">
                            <?= htmlspecialchars($opp['name'] ?? $opp['opportunity_id']) ?> (<?= htmlspecialchars($opp['stage'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="author">Your Name</label>
                <input type="text" id="author" name="author" class="form-control" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" required>
            </div>
            <div class="submit-actions">
                <button type="submit" name="add_discussion" class="btn-primary">Add Log Entry</button>
            </div>
        </form>
        <div class="cv-tip">
            <strong>Tip:</strong> You can log communications before, during, or after an opportunity. Link to an opportunity if relevant, or leave blank for general notes.
        </div>
    </div>

    <?php
    // Fetch all discussions for all contacts under this customer
    $discussions = [];
    $discussionSchema = require __DIR__ . '/discussion_schema.php';
    $selectDiscussionFields = array_values(array_unique(array_merge(['id'], (array) $discussionSchema)));
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $selectDiscussionFields));
    if (!empty($customerContacts)) {
        $contactIds = array_map(function($c) { return intval($c['contact_id']); }, $customerContacts);
        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $conn = get_mysql_connection();
        $discussionQuery = "SELECT $fields FROM discussion_log WHERE contact_id IN ($placeholders) ORDER BY timestamp DESC";
        $stmtDisc = $conn->prepare($discussionQuery);
        if ($stmtDisc) {
            $types = str_repeat('i', count($contactIds));
            $stmtDisc->bind_param($types, ...$contactIds);
            $stmtDisc->execute();
            $result = $stmtDisc->get_result();
        } else {
            $result = null;
        }
        if ($result) {
            $seen = [];
            while ($row = $result->fetch_assoc()) {
                $uniqueKey = $row['id'] ?? md5(json_encode($row));
                if (!isset($seen[$uniqueKey])) {
                    $discussions[] = $row;
                    $seen[$uniqueKey] = true;
                }
            }
            $result->free();
        }
        if (isset($stmtDisc) && $stmtDisc) {
            $stmtDisc->close();
        }
        $conn->close();
    }

    // Note: $discussions now populated for all contacts under this customer.
    ?>
    <div class="accordion cv-discussions">
            <div class="accordion-header" onclick="toggleAccordion(this)">
                <div class="accordion-title">
                    <span>💬</span>
                    <span>Discussions (<?= is_array($discussions) ? count($discussions) : 0 ?>)</span>
                </div>
                <div class="accordion-icon">▶</div>
            </div>
            <div class="accordion-content">
                <div class="accordion-body">
                    <div class="section-title">📒 Activity & History</div>
                    <?php if (!empty($discussions)): ?>
                        <?php foreach ($discussions as $disc): ?>
                            <?php
                            $visibility = strtolower((string) ($disc['visibility'] ?? 'public'));
                            $visibilityClass = 'cv-visibility-public';
                            if ($visibility === 'internal') {
                                $visibilityClass = 'cv-visibility-internal';
                            } elseif ($visibility === 'private') {
                                $visibilityClass = 'cv-visibility-private';
                            }
                            ?>
                            <div class="timeline-item cv-timeline-item">
                                <div style="width: 100%;">
                                    <div class="cv-timeline-head">
                                        <strong class="cv-timeline-author"><?= htmlspecialchars($disc['author'] ?? 'Unknown') ?></strong>
                                        <span class="cv-visibility-badge <?= htmlspecialchars($visibilityClass) ?>">
                                            <?= htmlspecialchars($disc['visibility'] ?? 'public') ?>
                                        </span>
                                    </div>
                                    <div class="cv-timeline-meta">
                                        📅 <?= htmlspecialchars($disc['timestamp'] ?? '—') ?>
                                        <?php if (!empty($disc['manual_contact_id'])): ?>
                                            <span class="cv-manual-link">🔗 Linked by manual_contact_id: <?= htmlspecialchars($disc['manual_contact_id']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cv-timeline-body">
                                        <?= nl2br(htmlspecialchars($disc['entry_text'] ?? '')) ?>
                                    </div>
                                    <?php if (!empty($disc['linked_opportunity_id'])): ?>
                                        <div class="cv-linked">
                                            <strong>📎 Linked to Opportunity: #<?= htmlspecialchars($disc['linked_opportunity_id']) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="cv-empty">
                            No discussions logged yet for this contact.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <!-- Navigation -->

        <div class="cv-nav">
            <a href="customers_list.php" class="btn-outline">⬅ Back to Customers</a>
            <a href="index.php" class="btn-outline">⬅ Back to Home</a>
        </div>
</div>

<script>
// ── AI Integration ──────────────────────────────────────────────────────────
const AI_CONTACT_ID  = <?= json_encode($customer['contact_id'] ?? '') ?>;
const AI_CSRF_TOKEN  = <?= json_encode(getCSRFToken()) ?>;

function aiAction(action, btn, label) {
  const panel = document.getElementById('aiPanel');
  const body  = document.getElementById('aiPanelBody');
  const meta  = document.getElementById('aiPanelMeta');
  const title = document.getElementById('aiPanelTitle');

  title.textContent = '🤖 AI: ' + label;
  body.textContent  = 'Thinking…';
  meta.textContent  = '';
  panel.classList.add('visible');
  panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  const origLabel = btn ? btn.innerHTML : '';
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ai-spinner"></span> Thinking…'; }

  const fd = new FormData();
  fd.append('action',     action);
  fd.append('contact_id', AI_CONTACT_ID);
  fd.append('csrf_token', AI_CSRF_TOKEN);

  fetch('ai_endpoint.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        body.textContent = '⚠️ ' + data.error;
      } else {
        body.textContent = data.text || '(no response)';
                if (data.provider && data.model) {
                    var selectionLabel = data.selection_mode === 'cheapest' ? ' · chosen by cost' : ' · manual selection';
                    meta.textContent = 'via ' + data.provider + ' / ' + data.model + selectionLabel;
                }
      }
    })
    .catch(err => { body.textContent = '⚠️ Network error: ' + err.message; })
    .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = origLabel; } });
}

function closeAiPanel() {
  document.getElementById('aiPanel').classList.remove('visible');
}

function copyAiResult() {
  const text = document.getElementById('aiPanelBody').textContent;
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.ai-copy-btn');
    const orig = btn.textContent;
    btn.textContent = '✓ Copied!';
    setTimeout(() => { btn.textContent = orig; }, 2000);
  });
}
</script>

        </div> <!-- end .container -->
    </div> <!-- end .content-container -->
</div> <!-- end .main-content -->
<?php include_once(__DIR__ . '/layout_end.php'); ?>