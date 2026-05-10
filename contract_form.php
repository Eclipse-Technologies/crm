<?php
require_once 'db_mysql.php';
// Count available rental tanks by size from equipment table
$availableRentalTanks = [];
$conn = get_mysql_connection();
$result = $conn->query("SELECT tank_size, COUNT(*) as qty FROM equipment WHERE ownership = 'rental' AND tank_size IS NOT NULL AND tank_size != '' GROUP BY tank_size");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $size = (string)($row['tank_size'] ?? '');
        $qty = (int)($row['qty'] ?? 0);
        if ($size !== '') {
            $availableRentalTanks[$size] = $qty;
        }
    }
    $result->free();
}
$conn->close();
?>
<?php
require_once 'layout_start.php';
require_once 'db_mysql.php';

$pageTitle = 'Add Service Contract';
$contractSchema = require __DIR__ . '/contract_schema.php';

// --- SEARCH & PAGINATION LOGIC (like contacts_list.php) ---
define('DEFAULT_CONTRACTS_PER_PAGE', 25);
define('ALLOWED_PER_PAGE_OPTIONS', [10, 25, 50, 100]);
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], ALLOWED_PER_PAGE_OPTIONS)
    ? (int)$_GET['per_page']
    : DEFAULT_CONTRACTS_PER_PAGE;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$query = strtolower(trim($_GET['query'] ?? ''));
$field = $_GET['field'] ?? '';

function fetch_contracts_search($schema, $query, $field, $per_page, $current_page) {
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
    $whereConditions = [];
    $bindTypes = '';
    $bindValues = [];
    if ($query !== '') {
        if ($field && in_array($field, $schema)) {
            $whereConditions[] = "LOWER(`$field`) LIKE ?";
            $bindTypes .= 's';
            $bindValues[] = '%' . $query . '%';
        } else {
            $fieldConds = [];
            foreach ($schema as $f) {
                $fieldConds[] = "LOWER(`$f`) LIKE ?";
                $bindTypes .= 's';
                $bindValues[] = '%' . $query . '%';
            }
            $whereConditions[] = '(' . implode(' OR ', $fieldConds) . ')';
        }
    }
    $where = $whereConditions ? ' WHERE ' . implode(' AND ', $whereConditions) : '';
    $offset = ($current_page - 1) * $per_page;
    $sql = "SELECT $fields FROM contracts$where LIMIT $per_page OFFSET $offset";
    if ($bindValues) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($bindTypes, ...$bindValues);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query($sql);
    }
    $contracts = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $contracts[] = $row;
        }
        $result->free();
    }
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as cnt FROM contracts$where";
    if ($bindValues) {
        $cstmt = $conn->prepare($count_sql);
        $cstmt->bind_param($bindTypes, ...$bindValues);
        $cstmt->execute();
        $count_result = $cstmt->get_result();
        $cstmt->close();
    } else {
        $count_result = $conn->query($count_sql);
    }
    $total_contracts = 0;
    if ($count_result) {
        $row = $count_result->fetch_assoc();
        $total_contracts = (int)($row['cnt'] ?? 0);
        $count_result->free();
    }
    $conn->close();
    return [$contracts, $total_contracts];
}

list($contracts_search, $total_contracts) = fetch_contracts_search($contractSchema, $query, $field, $per_page, $current_page);
$total_pages = max(1, ceil($total_contracts / $per_page));
$offset = ($current_page - 1) * $per_page;

// --- END SEARCH & PAGINATION LOGIC ---

function fetch_mysql($table, $schema) {
        // DEBUG: Directly test if contact_id can be selected from contracts
        if ($table === 'contracts') {
            $conn2 = get_mysql_connection();
            $testResult = $conn2->query('SELECT contact_id FROM contracts LIMIT 1');
            if ($testResult) {
                error_log('DEBUG: SELECT contact_id FROM contracts succeeded.');
                $testResult->free();
            } else {
                error_log('DEBUG: SELECT contact_id FROM contracts failed: ' . $conn2->error);
            }
            $conn2->close();
        }
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
$conn = get_mysql_connection();
$sql = "SELECT customers.customer_id, contacts.company, customers.address FROM customers LEFT JOIN contacts ON customers.contact_id = contacts.contact_id";
$result = $conn->query($sql);
$customers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $result->free();
}
$conn->close();
$equipment = fetch_mysql('equipment', require __DIR__ . '/equipment_schema.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo '<div style="color:red;"><b>CSRF validation failed.</b></div>';
        exit;
    }

    // --- Tank Inventory Deduction Logic ---
    $rentedTankSize = $_POST['rented_tank_size'] ?? '';
    $numRentedTanks = isset($_POST['num_rented_tanks']) ? (int)$_POST['num_rented_tanks'] : 0;
    if ($rentedTankSize && $rentedTankSize !== 'Other' && $numRentedTanks > 0) {
        $conn = get_mysql_connection();
        // Get available rental tanks for this size from equipment
        $result = $conn->query("SELECT equipment_id FROM equipment WHERE ownership = 'rental' AND tank_size = '" . $conn->real_escape_string($rentedTankSize) . "' LIMIT " . intval($numRentedTanks));
        $assigned = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $assigned[] = $row['equipment_id'];
            }
            $result->free();
        }
        if (count($assigned) < $numRentedTanks) {
            $conn->close();
            echo '<div style="color:red;"><b>Not enough rental tanks available in equipment to fulfill this contract. No changes made.</b></div>';
            exit;
        }
        // Mark assigned tanks as 'Assigned'
        foreach ($assigned as $equipmentId) {
            $update = $conn->prepare("UPDATE equipment SET status = 'Assigned' WHERE equipment_id = ?");
            $update->bind_param('s', $equipmentId);
            $update->execute();
            $update->close();
        }
        // Continue using $conn for contract insert below
    } else {
        $conn = get_mysql_connection();
    }

    // Generate contract ID (find max contract_id for today and increment)
    $datePrefix = 'CNT-' . date('Ymd') . '-';
    $result = $conn->query("SELECT contract_id FROM contracts WHERE contract_id LIKE '{$datePrefix}%' ORDER BY contract_id DESC LIMIT 1");
    $nextNum = 1;
    if ($result && $row = $result->fetch_assoc()) {
        $lastId = $row['contract_id'];
        $lastNum = (int)substr($lastId, strrpos($lastId, '-') + 1);
        $nextNum = $lastNum + 1;
    }
    $contractId = $datePrefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

    // Calculate values
    $monthlyFee = (float)$_POST['monthly_fee'];
    $contractTerm = (int)$_POST['contract_term'];
    $annualValue = $monthlyFee * 12;

    // Calculate end date
    $startDate = $_POST['start_date'];
    $endDate = date('Y-m-d', strtotime($startDate . ' + ' . $contractTerm . ' months'));

    // Calculate renewal date (end date - notice period)
    $noticePeriod = (int)$_POST['notice_period'];
    $renewalDate = date('Y-m-d', strtotime($endDate . ' - ' . $noticePeriod . ' days'));

    // Validate date fields
    function valid_date_or_null($val) {
        $val = trim($val ?? '');
        return ($val === '' || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $val)) ? null : $val;
    }
    $fields = [
        'contract_id' => $contractId,
        'customer_id' => (isset($_POST['customer_id']) && is_numeric($_POST['customer_id']) && $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null),
        'contact_id' => $_POST['contact_id'] ?? null,
        'contract_type' => $_POST['contract_type'],
        'contract_status' => 'Active',
        'equipment_type' => $_POST['equipment_type'],
        'monthly_fee' => $monthlyFee,
        'regen_fee' => isset($_POST['regen_fee']) && $_POST['regen_fee'] !== '' ? (float)$_POST['regen_fee'] : null,
        'tank_sale_price' => isset($_POST['tank_sale_price']) && $_POST['tank_sale_price'] !== '' ? (float)$_POST['tank_sale_price'] : null,
        'annual_value' => $annualValue,
        'payment_frequency' => $_POST['payment_frequency'],
        'contract_term' => $contractTerm,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'renewal_date' => $renewalDate,
        'auto_renew' => $_POST['auto_renew'] ?? 'No',
        'notice_period' => $noticePeriod,
        'evoqua_account' => $_POST['evoqua_account'] ?? '',
        'evoqua_contract' => $_POST['evoqua_contract'] ?? '',
        'equipment_ids' => implode(',', array_filter(array_map('trim', (array)($_POST['equipment_ids'] ?? [])))),
        'service_frequency' => $_POST['service_frequency'],
        'last_service_date' => valid_date_or_null($_POST['last_service_date'] ?? ''),
        'next_service_date' => valid_date_or_null($_POST['next_service_date'] ?? ''),
        'notes' => $_POST['notes'] ?? '',
        'created_date' => date('Y-m-d H:i:s'),
        'created_by' => $_SESSION['user_id'] ?? 'system',
        'modified_date' => date('Y-m-d H:i:s'),
        'modified_by' => $_SESSION['user_id'] ?? 'system'
    ];

    $columns = implode(',', array_map(function($k) { return '`' . $k . '`'; }, array_keys($fields)));
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $types = '';
    foreach ($fields as $k => $v) {
        if (is_int($v)) {
            $types .= 'i';
        } elseif (is_float($v)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    $stmt = $conn->prepare("INSERT INTO contracts ($columns) VALUES ($placeholders)");
    $stmt->bind_param($types, ...array_values($fields));
    $result = $stmt->execute();
    if ($result) {
        $stmt->close();
        $conn->close();
        // Clean output buffer before redirect
        if (ob_get_length()) {
            ob_end_clean();
        }
        header('Location: contracts_list.php?success=1');
        exit;
    } else {
        echo '<div style="color:red;"><b>Failed to add contract:</b> ' . htmlspecialchars($stmt->error) . '</div>';
    }
}
?>

<!-- Debug Section (visible only if DEBUG is true) -->

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
    <h1>📋 Add Service Contract</h1>
    <p>Create a new SDI service agreement</p>
</div>

<?php if (isset($error)): ?>
    <div style="background: #FEE2E2; border: 2px solid #EF4444; color: #991B1B; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" id="contractForm">
        <?php renderCSRFInput(); ?>
        <!-- Customer & (Optional) Contact Information -->
        <div class="form-section">
            <div class="form-section-title">🏢 Customer & (Optional) Contact</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="customer_id">Customer *</label>
                    <select name="customer_id" id="customer_id" required>
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= htmlspecialchars($customer['customer_id']) ?>">
                                <?= htmlspecialchars($customer['company']) ?>
                                <?php if (!empty($customer['address'])): ?>
                                    - <?= htmlspecialchars($customer['address']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="contact_id">Customer Contact *</label>
                    <select name="contact_id" id="contact_id" required>
                        <option value="">Select Contact</option>
                        <!-- Options will be populated by JS -->
                    </select>
                    <div class="form-help">Contact must belong to selected customer</div>
                </div>
            <script>
            // --- Customer/Contact Filtering ---
            const allContacts = <?php echo json_encode($contacts); ?>;
            const customerSelect = document.getElementById('customer_id');
            const contactSelect = document.getElementById('contact_id');
            const customers = <?php echo json_encode($customers); ?>;

            function updateContactOptions() {
                const customerId = customerSelect.value;
                while (contactSelect.options.length > 1) contactSelect.remove(1);
                if (!customerId) return;
                // Find the selected customer's company name
                const selectedCustomer = customers.find(c => String(c.customer_id) === String(customerId));
                const companyName = selectedCustomer ? selectedCustomer.company : '';
                // Filter contacts by company name
                const filtered = allContacts.filter(c => c.company === companyName);
                filtered.forEach(contact => {
                    const opt = document.createElement('option');
                    opt.value = contact.contact_id;
                    let label = (contact.first_name || '') + ' ' + (contact.last_name || '');
                    if (contact.company) label += ' - ' + contact.company;
                    opt.textContent = label.trim() || contact.contact_id;
                    contactSelect.appendChild(opt);
                });
            }
            customerSelect.addEventListener('change', function() {
                updateContactOptions();
                updateTankOptions();
            });
            // On page load, if a customer is preselected, populate contacts and tanks
            if (customerSelect.value) { updateContactOptions(); updateTankOptions(); }
            </script>
            </div>
        </div>
        
        <!-- Tank Ownership -->
        <div class="form-section">
            <div class="form-section-title">🛢️ Tank Ownership & Fees</div>
            <div class="form-help" style="margin-bottom:12px;color:#0d6efd;font-weight:500;">
                <b>Note:</b> Enter the number of tanks for this contract. Specify how many are customer-owned and how many are rented from us.<br>
                <span style="color:#198754;">Detailed tank assignment logic will be implemented later.</span>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="num_owned_tanks">Number of Customer-Owned Tanks</label>
                    <input type="number" name="num_owned_tanks" id="num_owned_tanks" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="num_rented_tanks">Number of Rented Tanks</label>
                    <input type="number" name="num_rented_tanks" id="num_rented_tanks" min="0" value="0">
                </div>

                <!-- Rental: monthly fee + regen fee -->
                <div class="form-group" id="row_monthly_fee">
                    <label for="monthly_fee">Monthly Rental Fee ($) *</label>
                    <input type="number" name="monthly_fee" id="monthly_fee" step="0.01" min="0" onchange="calculateAnnualValue()">
                    <div class="form-help">Charged monthly for tank rental</div>
                </div>

                <div class="form-group" id="row_regen_fee">
                    <label for="regen_fee">Regeneration Fee ($ / service)</label>
                    <input type="number" name="regen_fee" id="regen_fee" step="0.01" min="0">
                    <div class="form-help">Fee charged per regeneration/exchange visit</div>
                </div>

                <!-- Purchased: one-time tank sale -->
                <div class="form-group" id="row_tank_sale_price" style="display:none;">
                    <label for="tank_sale_price">Tank Sale Price ($)</label>
                    <input type="number" name="tank_sale_price" id="tank_sale_price" step="0.01" min="0">
                    <div class="form-help">One-time sale price for the tank</div>
                </div>

                <!-- Tank Size Dropdown: moved here after ownership and fees -->
                <div class="form-group">
                    <label for="rented_tank_size">Rented Tank Size</label>
                    <select name="rented_tank_size" id="rented_tank_size" class="form-select" style="max-width:220px;" onchange="updateAvailableTanks()">
                        <option value="">Select Size</option>
                        <option value="1">1 cuft</option>
                        <option value="2">2 cuft</option>
                        <option value="3.5">3.5 cuft</option>
                        <option value="5">5 cuft</option>
                        <option value="Other">Other</option>
                    </select>
                    <div class="form-help">Applies to all rented tanks on this contract</div>
                    <div id="available_tanks_display" style="margin-top:8px;"></div>
                </div>

                <div class="form-group">
                    <label for="annual_value">Annual Contract Value ($)</label>
                    <div class="calculated-value" id="annual_value_display">$0.00</div>
                    <div class="form-help">Calculated automatically from monthly fee × 12</div>
                </div>
            </div>
        <script>
        // Equipment data for available rental tanks by size
        const availableTanksBySize = <?php echo json_encode($availableRentalTanks); ?>;
        function updateAvailableTanks() {
            const size = document.getElementById('rented_tank_size').value;
            const num = parseInt(document.getElementById('num_rented_tanks').value || '0', 10);
            const display = document.getElementById('available_tanks_display');
            if (!size || size === 'Other') {
                display.innerHTML = '<span style="color:#6B7280">Select a standard size to see available tanks.</span>';
                return;
            }
            const available = availableTanksBySize[size] || 0;
            let msg = `<b>Available tanks (${size} cuft):</b> <span style="color:${available > 0 ? '#198754' : '#dc3545'}">${available}</span>`;
            if (num > 0) {
                if (num > available) {
                    msg += `<br><span style=\"color:#dc3545;font-weight:600;\">Warning: Not enough tanks available for this size!</span>`;
                } else {
                    msg += `<br><span style=\"color:#198754;\">Enough tanks available for this contract.</span>`;
                }
            }
            display.innerHTML = msg;
        }
        document.getElementById('rented_tank_size').addEventListener('change', updateAvailableTanks);
        document.getElementById('num_rented_tanks').addEventListener('input', updateAvailableTanks);
        window.addEventListener('DOMContentLoaded', updateAvailableTanks);
        </script>
        </div>

        <!-- Contract Details -->
        <div class="form-section">
            <div class="form-section-title">📄 Contract Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="contract_type">Contract Type *</label>
                    <select name="contract_type" id="contract_type" required>
                        <option value="">Select Type</option>
                        <option value="New">New Contract</option>
                        <option value="Renewal">Renewal</option>
                        <option value="Upsell">Upsell</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="equipment_type">Equipment Type *</label>
                    <select name="equipment_type" id="equipment_type" required>
                        <option value="">Select Equipment</option>
                        <option value="Softener">Water Softener</option>
                        <option value="RO System">RO System</option>
                        <option value="Filtration">Filtration System</option>
                        <option value="DI System">DI System</option>
                        <option value="Mixed Systems">Mixed Systems</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="payment_frequency">Payment Frequency *</label>
                    <select name="payment_frequency" id="payment_frequency" required>
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Annual">Annual</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="contract_term">Contract Term (Months) *</label>
                    <select name="contract_term" id="contract_term" required onchange="calculateDates()">
                        <option value="12">12 Months</option>
                        <option value="24">24 Months</option>
                        <option value="36">36 Months</option>
                        <option value="48">48 Months</option>
                        <option value="60">60 Months</option>
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
                    <input type="date" name="start_date" id="start_date" required onchange="calculateDates()">
                </div>
                
                <div class="form-group">
                    <label for="end_date">Contract End Date</label>
                    <div class="calculated-value" id="end_date_display">Not calculated</div>
                    <div class="form-help">Calculated from start date + term</div>
                </div>
                
                <div class="form-group">
                    <label for="notice_period">Cancellation Notice Period (Days) *</label>
                    <select name="notice_period" id="notice_period" required onchange="calculateDates()">
                        <option value="30">30 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="renewal_date">Renewal Notification Date</label>
                    <div class="calculated-value" id="renewal_date_display">Not calculated</div>
                    <div class="form-help">End date minus notice period</div>
                </div>
                
                <div class="form-group">
                    <label for="auto_renew">Auto-Renewal</label>
                    <select name="auto_renew" id="auto_renew">
                        <option value="Yes">Yes - Auto Renew</option>
                        <option value="No">No - Manual Renewal</option>
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
                    <input type="text" name="evoqua_account" id="evoqua_account">
                </div>
                
                <div class="form-group">
                    <label for="evoqua_contract">Evoqua Contract Number</label>
                    <input type="text" name="evoqua_contract" id="evoqua_contract">
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
                        <option value="Weekly">Weekly</option>
                        <option value="Bi-weekly">Bi-weekly</option>
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Semi-Annual">Semi-Annual</option>
                        <option value="Annual">Annual</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="next_service_date">Next Service Date</label>
                    <input type="date" name="next_service_date" id="next_service_date">
                </div>
                
                <div class="form-group full-width">
                    <label for="notes">Contract Notes</label>
                    <textarea name="notes" id="notes" placeholder="Additional contract notes, special terms, etc."></textarea>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Create Contract</button>
            <a href="contracts_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// Pass equipment data to JS
const allEquipment = <?= json_encode(array_map(fn($e) => [
    'equipment_id' => $e['equipment_id'],
    'customer_id'  => $e['customer_id'],
    'serial_number'=> $e['serial_number'] ?? '',
    'tank_size'    => $e['tank_size'] ?? '',
    'resin_type'   => $e['resin_type'] ?? '',
    'ownership'    => $e['ownership'] ?? '',
], $equipment)) ?>;

const ownershipColors = {
    'rental':          { bg:'#DBEAFE', color:'#1D4ED8', label:'Rental — Monthly Fee + Regen Fee' },
    'customer-owned':  { bg:'#D1FAE5', color:'#065F46', label:'Customer-Owned — Regen Fee Only' },
    'purchased':       { bg:'#FEF3C7', color:'#92400E', label:'Purchased — Tank Sale + Regen Fee' },
};

function updateTankOptions() {
    const customerId = document.getElementById('customer_id').value;
    const sel = document.getElementById('equipment_ids');
    sel.innerHTML = '';
    const tanks = allEquipment.filter(e => String(e.customer_id) === String(customerId));
    if (!tanks.length) {
        sel.innerHTML = '<option value="" disabled>No tanks found — add tanks to this customer first</option>';
        setOwnershipUI(null);
        return;
    }
    tanks.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.equipment_id;
        opt.dataset.ownership = t.ownership || '';
        let label = t.equipment_id;
        if (t.serial_number) label += ' — S/N: ' + t.serial_number;
        if (t.tank_size)     label += ' (' + t.tank_size + ')';
        if (t.ownership)     label += ' [' + t.ownership + ']';
        else                 label += ' ⚠️ no ownership set';
        opt.textContent = label;
        sel.appendChild(opt);
    });
    // Auto-select first and trigger ownership UI
    sel.options[0].selected = true;
    setOwnershipUI(tanks[0].ownership || null);
}

document.getElementById('equipment_ids').addEventListener('change', function() {
    const selected = Array.from(this.selectedOptions);
    // Use the ownership of the first selected tank
    const ownership = selected.length ? selected[0].dataset.ownership : null;
    setOwnershipUI(ownership);
});

function setOwnershipUI(ownership) {
    const badge   = document.getElementById('ownership_badge');
    const help    = document.getElementById('ownership_help');
    const display = document.getElementById('ownership_display');
    const rowMonthly   = document.getElementById('row_monthly_fee');
    const rowRegen     = document.getElementById('row_regen_fee');
    const rowSale      = document.getElementById('row_tank_sale_price');

    if (!ownership) {
        display.style.display = '';
        badge.textContent = '⚠️ Ownership not set on this tank';
        badge.style.background = '#FEF3C7';
        badge.style.color = '#92400E';
        help.innerHTML = '<b>Go to <a href="equipment_list.php" target="_blank">Equipment</a> and set the ownership on this tank before creating a contract.</b>';
        rowMonthly.style.display = 'none';
        rowRegen.style.display   = 'none';
        rowSale.style.display    = 'none';
        return;
    }

    const info = ownershipColors[ownership] || { bg:'#F3F4F6', color:'#374151', label: ownership };
    badge.textContent = info.label;
    badge.style.background = info.bg;
    badge.style.color = info.color;
    display.style.display = '';

    // Show/hide fee fields based on ownership
    if (ownership === 'rental') {
        rowMonthly.style.display = '';
        rowRegen.style.display   = '';
        rowSale.style.display    = 'none';
        document.getElementById('monthly_fee').required = true;
    } else if (ownership === 'customer-owned') {
        rowMonthly.style.display = 'none';
        rowRegen.style.display   = '';
        rowSale.style.display    = 'none';
        document.getElementById('monthly_fee').required = false;
        document.getElementById('monthly_fee').value = '';
    } else if (ownership === 'purchased') {
        rowMonthly.style.display = 'none';
        rowRegen.style.display   = '';
        rowSale.style.display    = '';
        document.getElementById('monthly_fee').required = false;
        document.getElementById('monthly_fee').value = '';
    }
    calculateAnnualValue();
}
    const monthlyFee = parseFloat(document.getElementById('monthly_fee').value) || 0;
    const annualValue = monthlyFee * 12;
    document.getElementById('annual_value_display').textContent = '$' + annualValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function calculateDates() {
    const startDate = document.getElementById('start_date').value;
    const termMonths = parseInt(document.getElementById('contract_term').value);
    const noticeDays = parseInt(document.getElementById('notice_period').value);
    
    if (!startDate || !termMonths) return;
    
    // Calculate end date
    const start = new Date(startDate);
    const end = new Date(start);
    end.setMonth(end.getMonth() + termMonths);
    
    const endDateStr = end.toISOString().split('T')[0];
    document.getElementById('end_date_display').textContent = endDateStr;
    
    // Calculate renewal date
    const renewal = new Date(end);
    renewal.setDate(renewal.getDate() - noticeDays);
    const renewalDateStr = renewal.toISOString().split('T')[0];
    document.getElementById('renewal_date_display').textContent = renewalDateStr;
}

// Persist contract form values using localStorage
window.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('form');
  if (!form) return;
  // Restore values
  Array.from(form.elements).forEach(el => {
    if (!el.name) return;
    const val = localStorage.getItem('contract_form_' + el.name);
    if (val !== null) {
      if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = val === 'true';
      } else {
        el.value = val;
      }
    }
  });
  // Save values on change
  form.addEventListener('input', function(e) {
    const el = e.target;
    if (!el.name) return;
    if (el.type === 'checkbox' || el.type === 'radio') {
      localStorage.setItem('contract_form_' + el.name, el.checked);
    } else {
      localStorage.setItem('contract_form_' + el.name, el.value);
    }
  });
  // Clear storage on submit
  form.addEventListener('submit', function() {
    Array.from(form.elements).forEach(el => {
      if (el.name) localStorage.removeItem('contract_form_' + el.name);
    });
  });
});

// Set default start date to today
document.getElementById('start_date').valueAsDate = new Date();
calculateDates();
</script>

<?php
// Wrap content in container to ensure layout/navbar always renders
if (!isset($contentContainerStarted)) {
    echo '<div class="content-container">';
    $contentContainerStarted = true;
}
// At the very end, before layout_end.php:
if (isset($contentContainerStarted)) {
    echo '</div>';
}
include_once(__DIR__ . '/layout_end.php');
?>
