<?php
// contract_edit.php - Edit Service Contract
// This file is based on contract_form.php and can be customized for edit functionality.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();
require_once 'layout_start.php';
require_once 'db_mysql.php';

$pageTitle = 'Edit Service Contract';
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

$contacts = fetch_mysql('contacts', require __DIR__ . '/contact_schema.php');
$customers = fetch_mysql('customers', require __DIR__ . '/customer_schema.php');
$equipment = fetch_mysql('equipment', require __DIR__ . '/equipment_schema.php');

$error = '';
// 1. Get contract ID from URL
$contractId = $_GET['id'] ?? '';
if (!$contractId) {
    die('No contract ID specified.');
}

// 2. Fetch contract data
$conn = get_mysql_connection();
$fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $contractSchema));
$stmt = $conn->prepare("SELECT $fields FROM contracts WHERE contract_id = ? LIMIT 1");
$stmt->bind_param('s', $contractId);
$stmt->execute();
$result = $stmt->get_result();
$contract = $result ? $result->fetch_assoc() : null;
$stmt->close();
$conn->close();
if (!$contract) {
    die('Contract not found.');
}

// 3. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Delete ──────────────────────────────────────────────────────────────
    if (!empty($_POST['delete_contract'])) {
        $conn = get_mysql_connection();
        // Audit log
        $old = json_encode($contract);
        $logStmt = $conn->prepare(
            'INSERT INTO audit_log (user_id, action, entity_type, entity_id, changes, summary, timestamp) VALUES (?, "DELETE", "contracts", ?, ?, "Contract deleted", NOW())'
        );
        $uid = (string)($_SESSION['user_id'] ?? 0);
        $logStmt->bind_param('sss', $uid, $contractId, $old);
        $logStmt->execute();
        $logStmt->close();
        // Delete
        $stmt = $conn->prepare('DELETE FROM contracts WHERE contract_id = ?');
        $stmt->bind_param('s', $contractId);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        header('Location: contracts_list.php?deleted=1');
        exit;
    }

    // ── Update ───────────────────────────────────────────────────────────────
    $fields = [];
    foreach ($contractSchema as $field) {
        if ($field === 'contract_id') {
            $fields[$field] = $contractId;
        } elseif (isset($_POST[$field])) {
            // Fix: convert empty string to null for integer and date fields
            if ($field === 'customer_id' && ($_POST[$field] === '' || !is_numeric($_POST[$field]))) {
                $fields[$field] = null;
            } elseif (in_array($field, ['start_date','end_date','renewal_date','last_service_date','next_service_date','created_date','modified_date']) && trim($_POST[$field]) === '') {
                $fields[$field] = null;
            } else {
                $fields[$field] = $_POST[$field];
            }
        } else {
            $fields[$field] = $contract[$field] ?? null;
        }
    }
    // Calculate annual_value if monthly_fee is set
    if (isset($fields['monthly_fee'])) {
        $fields['annual_value'] = (float)$fields['monthly_fee'] * 12;
    }
    // Contract term has been retired from the workflow.
    $fields['contract_term'] = null;
    // Calculate renewal_date if end_date and notice_period are set
    if (!empty($fields['end_date']) && !empty($fields['notice_period'])) {
        $fields['renewal_date'] = date('Y-m-d', strtotime($fields['end_date'] . ' - ' . (int)$fields['notice_period'] . ' days'));
    }
    $fields['modified_date'] = date('Y-m-d H:i:s');
    $fields['modified_by'] = $_SESSION['user_id'] ?? 'system';

    // Build update query
    $set = [];
    $types = '';
    $values = [];
    foreach ($fields as $k => $v) {
        if ($k === 'contract_id') continue;
        $set[] = "`$k` = ?";
        if (is_int($v)) {
            $types .= 'i';
        } elseif (is_float($v)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $values[] = $v;
    }
    $types .= 's';
    $values[] = $contractId;
    $conn = get_mysql_connection();
    $sql = "UPDATE contracts SET ".implode(',', $set)." WHERE contract_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $result = $stmt->execute();
    if ($result) {
        $stmt->close();
        $conn->close();
        if (ob_get_length()) ob_end_clean();
        header('Location: contracts_list.php?updated=1');
        exit;
    } else {
        $error = 'Failed to update contract: ' . htmlspecialchars($stmt->error);
    }
}

// 4. Show form, pre-filled with $contract
?>
<style>
.page-header {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    color: white;
    padding: 32px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.page-header h1 {
    margin: 0 0 8px 0;
    font-size: 32px;
    font-weight: 700;
}

.form-container {
    background: white;
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    max-width: 1200px;
}

.form-section {
    margin-bottom: 32px;
    padding-bottom: 32px;
    border-bottom: 2px solid #E5E7EB;
}

.form-section:last-child {
    border-bottom: none;
}

.form-section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 13px;
    font-weight: 700;
    color: #374151;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 12px 16px;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    font-size: 15px;
    font-family: inherit;
    transition: all 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #10B981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.calculated-value {
    background: #F0FDF4;
    padding: 16px;
    border-radius: 8px;
    border: 2px solid #10B981;
    font-weight: 700;
    color: #065F46;
    font-size: 20px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
}

.btn {
    padding: 14px 32px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-secondary {
    background: #F3F4F6;
    color: #374151;
    border: 2px solid #E5E7EB;
}

.btn-secondary:hover {
    background: #E5E7EB;
}

.btn-danger {
    background: #EF4444;
    color: white;
}

.btn-danger:hover {
    background: #DC2626;
}

.form-help {
    font-size: 12px;
    color: #6B7280;
    margin-top: 6px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<a href="contracts_list.php" style="display: inline-flex; align-items: center; gap: 8px; color: #10B981; text-decoration: none; font-weight: 600; margin-bottom: 16px;">
    ← Back to Contracts
</a>
<div class="page-header">
    <h1>✏️ Edit Service Contract</h1>
    <p>Update SDI service agreement details</p>
</div>

<?php if (!empty($error)): ?>
    <div style="background: #FEE2E2; border: 2px solid #EF4444; color: #991B1B; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
        ⚠️ <?= $error ?>
    </div>
<?php endif; ?>
<div class="form-container">
    <form method="POST" id="contractForm">
        <?php renderCSRFInput(); ?>
        <!-- Contact & Customer Information -->
        <div class="form-section">
            <div class="form-section-title">👤 Contact & Customer Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="contact_id">Contact *</label>
                    <select name="contact_id" id="contact_id" required>
                        <option value="">Select Contact</option>
                        <?php foreach ($contacts as $contact): ?>
                            <?php $contactValue = $contact['contact_id'] ?? $contact['id'] ?? ''; ?>
                            <option value="<?= htmlspecialchars($contactValue) ?>" <?= ((string)($contract['contact_id'] ?? '') === (string)$contactValue) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(trim($contact['first_name'] . ' ' . $contact['last_name'])) ?>
                                <?php if (!empty($contact['company'])): ?> - <?= htmlspecialchars($contact['company']) ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="customer_id">Customer ID (Optional)</label>
                    <input type="text" name="customer_id" id="customer_id" value="<?= htmlspecialchars($contract['customer_id'] ?? '') ?>">
                    <div class="form-help">Leave blank if contact is the customer</div>
                </div>
            </div>
        </div>
        <!-- Contract Details -->
        <div class="form-section">
            <div class="form-section-title">📄 Contract Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="contract_type">Contract Type *</label>
                    <select name="contract_type" id="contract_type" required>
                        <option value="">Select Type</option>
                        <option value="New" <?= ($contract['contract_type'] == 'New') ? 'selected' : '' ?>>New Contract</option>
                        <option value="Renewal" <?= ($contract['contract_type'] == 'Renewal') ? 'selected' : '' ?>>Renewal</option>
                        <option value="Upsell" <?= ($contract['contract_type'] == 'Upsell') ? 'selected' : '' ?>>Upsell</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="equipment_type">Equipment Type *</label>
                    <select name="equipment_type" id="equipment_type" required>
                        <option value="">Select Equipment</option>
                        <option value="Softener" <?= ($contract['equipment_type'] == 'Softener') ? 'selected' : '' ?>>Water Softener</option>
                        <option value="RO System" <?= ($contract['equipment_type'] == 'RO System') ? 'selected' : '' ?>>RO System</option>
                        <option value="Filtration" <?= ($contract['equipment_type'] == 'Filtration') ? 'selected' : '' ?>>Filtration System</option>
                        <option value="DI System" <?= ($contract['equipment_type'] == 'DI System') ? 'selected' : '' ?>>DI System</option>
                        <option value="Mixed Systems" <?= ($contract['equipment_type'] == 'Mixed Systems') ? 'selected' : '' ?>>Mixed Systems</option>
                        <option value="Other" <?= ($contract['equipment_type'] == 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="monthly_fee">Monthly Fee ($) *</label>
                    <input type="number" name="monthly_fee" id="monthly_fee" step="0.01" min="0" required value="<?= htmlspecialchars($contract['monthly_fee'] ?? '') ?>" onchange="calculateAnnualValue()">
                </div>
                <div class="form-group">
                    <label for="annual_value">Annual Contract Value ($)</label>
                    <div class="calculated-value" id="annual_value_display">$<?= number_format((float)($contract['annual_value'] ?? 0), 2) ?></div>
                    <div class="form-help">Calculated automatically</div>
                </div>
                <div class="form-group">
                    <label for="payment_frequency">Payment Frequency *</label>
                    <select name="payment_frequency" id="payment_frequency" required>
                        <option value="Monthly" <?= ($contract['payment_frequency'] == 'Monthly') ? 'selected' : '' ?>>Monthly</option>
                        <option value="Quarterly" <?= ($contract['payment_frequency'] == 'Quarterly') ? 'selected' : '' ?>>Quarterly</option>
                        <option value="Annual" <?= ($contract['payment_frequency'] == 'Annual') ? 'selected' : '' ?>>Annual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="contract_status">Contract Status *</label>
                    <select name="contract_status" id="contract_status" required>
                        <option value="Active" <?= (($contract['contract_status'] ?? '') === 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Lost" <?= (($contract['contract_status'] ?? '') === 'Lost') ? 'selected' : '' ?>>Lost</option>
                    </select>
                </div>
            </div>
        </div>
        <!-- Dates & Terms -->
        <div class="form-section">
            <div class="form-section-title">📅 Dates & Terms</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="start_date">Contract Start Date *</label>
                    <input type="date" name="start_date" id="start_date" required value="<?= htmlspecialchars($contract['start_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">Contract End Date</label>
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($contract['end_date'] ?? '') ?>" onchange="calculateDates()">
                    <div class="form-help">Set a date if known</div>
                </div>
                <div class="form-group">
                    <label for="notice_period">Cancellation Notice Period (Days) *</label>
                    <select name="notice_period" id="notice_period" required onchange="calculateDates()">
                        <option value="30" <?= ($contract['notice_period'] == 30) ? 'selected' : '' ?>>30 Days</option>
                        <option value="60" <?= ($contract['notice_period'] == 60) ? 'selected' : '' ?>>60 Days</option>
                        <option value="90" <?= ($contract['notice_period'] == 90) ? 'selected' : '' ?>>90 Days</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="renewal_date">Renewal Notification Date</label>
                    <div class="calculated-value" id="renewal_date_display"><?= htmlspecialchars($contract['renewal_date'] ?? 'Not calculated') ?></div>
                    <div class="form-help">End date minus notice period</div>
                </div>
                <div class="form-group">
                    <label for="auto_renew">Auto-Renewal</label>
                    <select name="auto_renew" id="auto_renew">
                        <option value="Yes" <?= ($contract['auto_renew'] == 'Yes') ? 'selected' : '' ?>>Yes - Auto Renew</option>
                        <option value="No" <?= ($contract['auto_renew'] == 'No') ? 'selected' : '' ?>>No - Manual Renewal</option>
                    </select>
                </div>
            </div>
        </div>
        <!-- Evoqua Details -->
        <div class="form-section">
            <div class="form-section-title">🏢 Evoqua Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="evoqua_account">Evoqua Account Number</label>
                    <input type="text" name="evoqua_account" id="evoqua_account" value="<?= htmlspecialchars($contract['evoqua_account'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="evoqua_contract">Evoqua Contract Number</label>
                    <input type="text" name="evoqua_contract" id="evoqua_contract" value="<?= htmlspecialchars($contract['evoqua_contract'] ?? '') ?>">
                </div>
            </div>
        </div>
        <!-- Service Schedule -->
        <div class="form-section">
            <div class="form-section-title">🔧 Service Schedule</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="service_frequency">Service Frequency *</label>
                    <select name="service_frequency" id="service_frequency" required>
                        <option value="Weekly" <?= ($contract['service_frequency'] == 'Weekly') ? 'selected' : '' ?>>Weekly</option>
                        <option value="Bi-weekly" <?= ($contract['service_frequency'] == 'Bi-weekly') ? 'selected' : '' ?>>Bi-weekly</option>
                        <option value="Monthly" <?= ($contract['service_frequency'] == 'Monthly') ? 'selected' : '' ?>>Monthly</option>
                        <option value="Quarterly" <?= ($contract['service_frequency'] == 'Quarterly') ? 'selected' : '' ?>>Quarterly</option>
                        <option value="Semi-Annual" <?= ($contract['service_frequency'] == 'Semi-Annual') ? 'selected' : '' ?>>Semi-Annual</option>
                        <option value="Annual" <?= ($contract['service_frequency'] == 'Annual') ? 'selected' : '' ?>>Annual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="next_service_date">Next Service Date</label>
                    <input type="date" name="next_service_date" id="next_service_date" value="<?= htmlspecialchars($contract['next_service_date'] ?? '') ?>">
                </div>
                <div class="form-group full-width">
                    <label for="notes">Contract Notes</label>
                    <textarea name="notes" id="notes" placeholder="Additional contract notes, special terms, etc."><?= htmlspecialchars($contract['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <!-- Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            <a href="contracts_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <!-- Delete (separate form to avoid submitting edit fields) -->
    <form method="POST" style="margin-top:12px;" onsubmit="return confirm('Permanently delete this contract? This cannot be undone.');">
        <input type="hidden" name="delete_contract" value="1">
        <button type="submit" class="btn btn-danger">🗑️ Delete Contract</button>
    </form>
</div>
<script>
function calculateAnnualValue() {
    const monthlyFee = parseFloat(document.getElementById('monthly_fee').value) || 0;
    const annualValue = monthlyFee * 12;
    document.getElementById('annual_value_display').textContent = '$' + annualValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function calculateDates() {
    const endDate = document.getElementById('end_date').value;
    const noticeDays = parseInt(document.getElementById('notice_period').value);
    if (!endDate) {
        document.getElementById('renewal_date_display').textContent = 'Not calculated';
        return;
    }
    // Calculate renewal date
    const end = new Date(endDate);
    const renewal = new Date(end);
    renewal.setDate(renewal.getDate() - noticeDays);
    const renewalDateStr = renewal.toISOString().split('T')[0];
    document.getElementById('renewal_date_display').textContent = renewalDateStr;
}
// Set calculated fields on load
document.addEventListener('DOMContentLoaded', function() {
    calculateAnnualValue();
    calculateDates();
});
</script>
<?php require_once 'layout_end.php'; ?>
