    <!-- ── TANK QUANTITY ASSIGNMENT ─────────────────────────────────────────── -->
    <div class="section-header">🛢️ Assign Tank Quantities (Bulk)</div>
    <form method="post">
        <?php renderCSRFInput(); ?>
        <input type="hidden" name="action" value="assign_tank_quantities">
        <div class="form-grid">
            <table style="width:100%;background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);margin-bottom:16px;">
                <thead>
                    <tr>
                        <th style="padding:8px 12px;">Tank Type</th>
                        <th style="padding:8px 12px;">Size</th>
                        <th style="padding:8px 12px;">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td style="padding:8px 12px;"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td style="padding:8px 12px;"><?= htmlspecialchars($item['tank_size'] ?? ($item['description'] ?? '')) ?></td>
                            <td style="padding:8px 12px;">
                                <input type="number" min="0" name="tank_quantity[<?= htmlspecialchars($item['item_id']) ?>]" value="0" style="width:60px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Save Tank Quantities</button>
        </div>
    </form>

<?php
require_once(__DIR__ . '/csrf_helper.php');
// Debug: Show all errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

include_once(__DIR__ . '/layout_start.php');
require_once 'db_mysql.php';

$customerSchema = require __DIR__ . '/customer_schema.php';
$equipmentSchema = require __DIR__ . '/equipment_schema.php';
$contractSchema = require __DIR__ . '/contract_schema.php';

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

// ── Handle POST updates ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">CSRF validation failed</div>';
    } else {
        try {
            $conn = get_mysql_connection();
            if ($_POST['action'] === 'update_customer') {
                // ...existing code...
                $stmt->close();
            } elseif ($_POST['action'] === 'assign_tanks') {
                // ...existing code...
                $stmt->close();
            } elseif ($_POST['action'] === 'assign_tank_quantities') {
                // Save tank quantities for this customer
                $quantities = $_POST['tank_quantity'] ?? [];
                foreach ($quantities as $itemId => $qty) {
                    $qty = (int)$qty;
                    if ($qty > 0) {
                        // Insert or update customer_tank_quantities
                        $stmt = $conn->prepare("INSERT INTO customer_tank_quantities (customer_id, item_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)");
                        $stmt->bind_param('isi', $customerId, $itemId, $qty);
                        $stmt->execute();
                        $stmt->close();
                        // Optionally: auto-generate unique tank assignments (pseudo-IDs)
                        // This can be expanded to create inventory records if needed
                    } else {
                        // Remove entry if quantity is zero
                        $stmt = $conn->prepare("DELETE FROM customer_tank_quantities WHERE customer_id = ? AND item_id = ?");
                        $stmt->bind_param('is', $customerId, $itemId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            $conn->close();
            if (!headers_sent()) {
                header("Location: customer_view.php?id=" . urlencode($customerId));
                exit;
            } else {
                echo '<div class="alert alert-warning">Redirect failed: headers already sent.</div>';
            }
        } catch (Throwable $e) {
            echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// ── Fetch equipment for this customer ────────────────────────────────────────
$conn = get_mysql_connection();
$stmt = $conn->prepare("SELECT * FROM equipment WHERE customer_id = ? ORDER BY equipment_type");
$stmt->bind_param('s', $customerId);
$stmt->execute();
$result = $stmt->get_result();
$equipment = [];
while ($row = $result->fetch_assoc()) {
    $equipment[] = $row;
}
$stmt->close();

$customerOwnedEquipment = [];
$serviceEquipment = [];
$equipmentIds = [];
foreach ($equipment as $eq) {
    $equipmentIds[] = $eq['equipment_id'];
    $ownership = strtolower(trim((string) ($eq['ownership'] ?? '')));
    if ($ownership === 'customer-owned' || $ownership === 'customer owned') {
        $customerOwnedEquipment[] = $eq;
    } else {
        $serviceEquipment[] = $eq;
    }
}

$componentsByEquipment = [];
if (!empty($equipmentIds)) {
    $placeholders = implode(',', array_fill(0, count($equipmentIds), '?'));
    $types = str_repeat('s', count($equipmentIds));
    $stmt = $conn->prepare("SELECT equipment_id, component_slot, item_id, quantity_required FROM equipment_components WHERE equipment_id IN ($placeholders)");
    $stmt->bind_param($types, ...$equipmentIds);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result ? $result->fetch_assoc() : null) {
        $equipmentId = $row['equipment_id'];
        if (!isset($componentsByEquipment[$equipmentId])) {
            $componentsByEquipment[$equipmentId] = [];
        }
        $componentsByEquipment[$equipmentId][$row['component_slot']] = $row;
    }
    $stmt->close();
}

// ── Fetch contracts for this customer ────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM contracts WHERE customer_id = ? ORDER BY contract_status DESC, start_date DESC");
$stmt->bind_param('s', $customerId);
$stmt->execute();
$result = $stmt->get_result();
$contracts = [];
$totalMRR = 0;
$totalARR = 0;
$activeCount = 0;

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
?>

<style>
.customer-header {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    color: white;
    padding: 32px;
    border-radius: 12px;
    margin-bottom: 24px;
}
.customer-header h1 {
    margin: 0 0 8px 0;
    font-size: 32px;
    font-weight: 700;
}
.customer-header p {
    margin: 4px 0;
    opacity: 0.95;
}

.section-header {
    font-size: 20px;
    font-weight: 700;
    margin-top: 32px;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #E5E7EB;
    color: #1F2937;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 13px;
    font-weight: 600;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px 12px;
    border: 2px solid #E5E7EB;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group input:disabled,
.form-group input[readonly] {
    background: #F9FAFB;
    color: #9CA3AF;
    cursor: not-allowed;
}

.contact-info {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}

.contact-info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #F3F4F6;
}

.contact-info-row:last-child {
    border-bottom: none;
}

.contact-label {
    font-weight: 600;
    color: #6B7280;
}

.contact-value {
    color: #1F2937;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.metric-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #3B82F6;
}

.metric-label {
    font-size: 12px;
    font-weight: 600;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.metric-value {
    font-size: 28px;
    font-weight: 700;
    color: #1F2937;
}

.metric-subtext {
    font-size: 12px;
    color: #9CA3AF;
    margin-top: 4px;
}

.location-legend {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.location-chip {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.location-pool { background: #cffafe; color: #155e75; }
.location-production { background: #ede9fe; color: #5b21b6; }
.location-warehouse { background: #fce7f3; color: #9d174d; }
.location-customer-site { background: #dcfce7; color: #166534; }
.location-other { background: #f3f4f6; color: #374151; }

.table-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}

.table-scroll table {
    width: 100%;
    min-width: 800px;
    border-collapse: collapse;
}

.table-scroll th {
    background: #F9FAFB;
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #E5E7EB;
}

.table-scroll td {
    padding: 12px 16px;
    border-bottom: 1px solid #F3F4F6;
    font-size: 13px;
}

.table-scroll tr:hover {
    background: #F9FAFB;
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.badge-active { background: #D1FAE5; color: #065F46; }
.badge-draft { background: #EFF6FF; color: #1E40AF; }
.badge-expiring { background: #FEF3C7; color: #92400E; }
.badge-expired { background: #FEE2E2; color: #991B1B; }
.badge-rental { background: #DBEAFE; color: #1D4ED8; }
.badge-owned { background: #D1FAE5; color: #065F46; }
.badge-purchased { background: #FEF3C7; color: #92400E; }

.action-btns {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    text-decoration: none;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.action-btn-view { background: #EFF6FF; color: #1E40AF; }
.action-btn-view:hover { background: #DBEAFE; }
.action-btn-edit { background: #FEF3C7; color: #92400E; }
.action-btn-edit:hover { background: #FDE68A; }
.action-btn-delete { background: #FEE2E2; color: #991B1B; }
.action-btn-delete:hover { background: #FECACA; }

.no-data {
    background: #F9FAFB;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    color: #6B7280;
    font-style: italic;
}

.btn-group {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    border: none;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-outline {
    background: white;
    color: #3B82F6;
    border: 2px solid #3B82F6;
}

.btn-outline:hover {
    background: #EFF6FF;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.form-actions button,
.form-actions a {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    border: none;
    font-size: 14px;
}

.form-actions button[type="submit"] {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    color: white;
}

/* AI Panel */
.ai-panel { background: white; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; margin: 0 0 20px 0; display: none; }
.ai-panel.visible { display: block; }
.ai-panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.ai-panel-title { font-size: 13px; font-weight: 700; color: #1d4ed8; }
.ai-panel-close { background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 18px; padding: 0; line-height: 1; }
.ai-panel-body { font-size: 13px; color: #1f2937; line-height: 1.7; white-space: pre-wrap; background: #f8faff; border-radius: 6px; padding: 12px; }
.ai-panel-meta { font-size: 11px; color: #9ca3af; margin-top: 6px; }
.ai-panel-actions { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
.ai-copy-btn { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; border-radius: 4px; padding: 5px 12px; font-size: 11px; cursor: pointer; font-weight: 600; }
.ai-btn { background: linear-gradient(135deg, #7c3aed, #4f46e5); color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: opacity 0.2s; }
.ai-btn:hover { opacity: 0.85; }
.ai-btn:disabled { opacity: 0.5; cursor: default; }
.ai-spinner { display: inline-block; width: 11px; height: 11px; border: 2px solid rgba(255,255,255,0.35); border-top-color: white; border-radius: 50%; animation: ai-spin 0.7s linear infinite; margin-right: 4px; vertical-align: middle; }
@keyframes ai-spin { to { transform: rotate(360deg); } }
</style>

<div class="container">
    <!-- AI Panel -->
    <div class="ai-panel" id="aiPanel">
      <div class="ai-panel-header">
        <div class="ai-panel-title" id="aiPanelTitle">🤖 AI</div>
        <button class="ai-panel-close" onclick="closeAiPanel()" title="Close">✕</button>
      </div>
      <div class="ai-panel-body" id="aiPanelBody"></div>
      <div class="ai-panel-meta" id="aiPanelMeta"></div>
      <div class="ai-panel-actions">
        <button class="ai-copy-btn" onclick="copyAiResult()">📋 Copy</button>
      </div>
    </div>

    <!-- Header -->
    <div class="customer-header">
        <h1>🏢 <?= htmlspecialchars($customer['address'] ?? 'Customer ' . $customerId) ?></h1>
        <p><strong>Customer ID:</strong> <?= htmlspecialchars($customerId) ?></p>
        <?php if ($contact): ?>
            <p><strong>Contact:</strong> <?= htmlspecialchars($contact['company'] ?? 'N/A') ?></p>
        <?php endif; ?>
        <div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
          <button class="ai-btn" onclick="aiAction('summarise_contact', this, 'Customer Summary')">🤖 AI Summary</button>
          <button class="ai-btn" onclick="aiAction('suggest_followup', this, 'Follow-up Draft')">✉ AI Follow-up</button>
        </div>
    </div>

    <!-- ── CUSTOMER INFO FORM (Editable inline) ────────────────────────────────── -->
    <div class="section-header">📋 Customer Information</div>
    <form method="post">
        <?php renderCSRFInput(); ?>
        <input type="hidden" name="action" value="update_customer">
        <div class="form-grid">
            <?php foreach ($customerSchema as $field): ?>
                <div class="form-group">
                    <label for="<?= $field ?>"><?= ucfirst(str_replace('_', ' ', $field)) ?></label>
                    <?php if ($field === 'customer_id'): ?>
                        <input type="text" id="<?= $field ?>" value="<?= htmlspecialchars($customer[$field]) ?>" readonly>
                    <?php elseif ($field === 'contact_id'): ?>
                        <select name="<?= $field ?>" id="<?= $field ?>">
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
                        <input type="text" name="<?= $field ?>" id="<?= $field ?>" value="<?= htmlspecialchars($customer[$field] ?? '') ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Save Customer Info</button>
        </div>
    </form>

    <!-- ── RENTED TANKS SUMMARY ───────────────────────────────────────────── -->
    <?php
    require_once 'inventory_mysql.php';
    $inventory = fetch_inventory_mysql(require __DIR__ . '/inventory_schema.php');
    $rentedTankIds = array_filter(array_map('trim', explode(',', $customer['rented_tanks'] ?? '')));
    $rentedTanks = array_filter($inventory, function($item) use ($rentedTankIds) {
        return in_array($item['item_id'], $rentedTankIds);
    });
    $rentedCount = count($rentedTanks);
    $rentedSizes = array_map(function($item) {
        return $item['tank_size'] ?? ($item['item_name'] ?? '');
    }, $rentedTanks);
    ?>
    <div class="section-header">🛢️ Rented Tanks Summary</div>
    <div style="margin-bottom:16px;">
        <strong>Number of Rented Tanks:</strong> <?= $rentedCount ?><br>
        <strong>Sizes:</strong> <?= $rentedCount > 0 ? htmlspecialchars(implode(', ', $rentedSizes)) : 'N/A' ?>
    </div>

    <!-- ── TANK ASSIGNMENT ───────────────────────────────────────────────────── -->
    <div class="section-header">🛢️ Assign Tanks</div>
    <form method="post">
        <?php renderCSRFInput(); ?>
        <input type="hidden" name="action" value="assign_tanks">
        <div class="form-grid">
            <div class="form-group">
                <label for="customer_owned_tanks">Customer-Owned Tanks</label>
                <select name="customer_owned_tanks[]" id="customer_owned_tanks" multiple size="6">
                    <?php
                    foreach ($inventory as $item) {
                        $selected = in_array($item['item_id'], (array)($customer['customer_owned_tanks'] ?? [])) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($item['item_id']) . "' $selected>" . htmlspecialchars($item['item_name']) . " (" . htmlspecialchars($item['serial_number']) . ")</option>";
                    }
                    ?>
                </select>
                <div class="form-help">Hold Ctrl/Cmd to select multiple tanks</div>
            </div>
            <div class="form-group">
                <label for="rented_tanks">Rented Tanks</label>
                <select name="rented_tanks[]" id="rented_tanks" multiple size="6">
                    <?php
                    foreach ($inventory as $item) {
                        $selected = in_array($item['item_id'], (array)($customer['rented_tanks'] ?? [])) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($item['item_id']) . "' $selected>" . htmlspecialchars($item['item_name']) . " (" . htmlspecialchars($item['serial_number']) . ")</option>";
                    }
                    ?>
                </select>
                <div class="form-help">Hold Ctrl/Cmd to select multiple tanks</div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Save Tank Assignments</button>
        </div>
    </form>

    <!-- ── CONTACT INFORMATION ────────────────────────────────────────────────── -->
    <?php if ($contact): ?>
        <div class="section-header">👤 Linked Contact</div>
        <div class="contact-info">
            <div class="contact-info-row">
                <span class="contact-label">Company:</span>
                <span class="contact-value"><?= htmlspecialchars($contact['company'] ?? 'N/A') ?></span>
            </div>
            <div class="contact-info-row">
                <span class="contact-label">Contact Person:</span>
                <span class="contact-value"><?= htmlspecialchars($contact['name'] ?? 'N/A') ?></span>
            </div>
            <div class="contact-info-row">
                <span class="contact-label">Phone:</span>
                <span class="contact-value"><?= htmlspecialchars($contact['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="contact-info-row">
                <span class="contact-label">Email:</span>
                <span class="contact-value"><a href="mailto:<?= htmlspecialchars($contact['email'] ?? '') ?>"><?= htmlspecialchars($contact['email'] ?? 'N/A') ?></a></span>
            </div>
            <div class="contact-info-row">
                <span class="contact-label">Address:</span>
                <span class="contact-value"><?= htmlspecialchars($contact['address'] ?? 'N/A') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── EQUIPMENT INVENTORY ────────────────────────────────────────────────── -->
    <div class="section-header">🛢️ Customer-Owned Tanks (<?= count($customerOwnedEquipment) ?>)</div>
    <div class="location-legend">
        <span class="location-chip location-pool">pool</span>
        <span class="location-chip location-production">production</span>
        <span class="location-chip location-warehouse">warehouse</span>
        <span class="location-chip location-customer-site">customer site</span>
    </div>
    <?php if (!empty($customerOwnedEquipment)): ?>
        <div class="table-scroll">
            <table>
                <thead>
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
                                <span class="badge badge-<?= strtolower(str_replace(' ', '-', $eq['ownership'])) ?>">
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
                                <div class="action-btns">
                                    <a href="equipment_view.php?id=<?= urlencode($eq['equipment_id']) ?>" class="action-btn action-btn-view">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="no-data">No customer-owned tanks tracked yet.</div>
    <?php endif; ?>

    <div class="section-header">🔁 Service and Rental Tanks At This Site (<?= count($serviceEquipment) ?>)</div>
    <?php if (!empty($serviceEquipment)): ?>
        <div class="table-scroll">
            <table>
                <thead>
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
                                <span class="badge badge-<?= strtolower(str_replace(' ', '-', $eq['ownership'])) ?>">
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
                                    <span class="badge" style="background:#f59e0b;color:#fff;">Trial</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($eqStatus) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="equipment_view.php?id=<?= urlencode($eq['equipment_id']) ?>" class="action-btn action-btn-view">View</a>
                                    <?php if ($isTrial): ?>
                                        <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>&contact_id=<?= urlencode($customer['contact_id'] ?? '') ?>" class="action-btn" style="background:#16a34a;color:#fff;white-space:nowrap;">📄 Create Contract</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="btn-group">
            <a href="add_customer.php?contact_id=<?= urlencode($customer['contact_id']) ?>" class="btn btn-primary">➕ Add Equipment</a>
        </div>
    <?php else: ?>
        <div class="no-data">No service/rental tanks assigned at this site. <a href="add_customer.php?contact_id=<?= urlencode($customer['contact_id']) ?>">Add equipment</a></div>
    <?php endif; ?>

    <!-- ── CONTRACTS & REVENUE ────────────────────────────────────────────────── -->
    <div class="section-header">💰 Service Contracts & Revenue</div>
    
    <!-- Metrics -->
    <?php if ($activeCount > 0): ?>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-label">Active Contracts</div>
                <div class="metric-value"><?= $activeCount ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Monthly Recurring Revenue</div>
                <div class="metric-value">$<?= number_format($totalMRR, 2) ?></div>
                <div class="metric-subtext"><?= $activeCount ?> active contract<?= $activeCount !== 1 ? 's' : '' ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Annual Value</div>
                <div class="metric-value">$<?= number_format($totalARR, 2) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Contracts Table -->
    <?php if (!empty($contracts)): ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Contract ID</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Monthly Rental</th>
                        <th>Regen Fee</th>
                        <th>Delivery Fee</th>
                        <th>Annual Value</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contracts as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['contract_id']) ?></strong></td>
                            <td><?= htmlspecialchars($c['contract_type'] ?? '') ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($c['contract_status']) ?>">
                                    <?= htmlspecialchars($c['contract_status']) ?>
                                </span>
                            </td>
                            <td>$<?= number_format((float)($c['monthly_fee'] ?? 0), 2) ?></td>
                            <td>$<?= number_format((float)($c['regen_fee'] ?? 0), 2) ?></td>
                            <td>$<?= number_format((float)($c['tank_sale_price'] ?? 0), 2) ?></td>
                            <td><strong>$<?= number_format((float)($c['annual_value'] ?? 0), 2) ?></strong></td>
                            <td><?= !empty($c['start_date']) ? date('M d, Y', strtotime($c['start_date'])) : 'N/A' ?></td>
                            <td><?= !empty($c['end_date']) ? date('M d, Y', strtotime($c['end_date'])) : 'N/A' ?></td>
                            <td>
                                <div class="action-btns">
                                    <a href="contract_view.php?id=<?= urlencode($c['contract_id']) ?>" class="action-btn action-btn-view">View</a>
                                    <a href="contract_edit.php?id=<?= urlencode($c['contract_id']) ?>" class="action-btn action-btn-edit">Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="btn-group">
            <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>" class="btn btn-primary">➕ New Contract</a>
        </div>
    <?php else: ?>
        <div class="no-data">No contracts found. <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>">Create a new contract</a></div>
    <?php endif; ?>

    <!-- Navigation -->
    <div class="btn-group" style="margin-top: 32px;">
        <a href="customers_list.php" class="btn btn-outline">⬅ Back to Customers</a>
        <a href="index.php" class="btn btn-outline">⬅ Back to Home</a>
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

<?php include_once(__DIR__ . '/layout_end.php'); ?>