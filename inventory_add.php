<?php
require_once 'db_mysql.php';
require_once 'csrf_helper.php';

$errors = [];

$conn = get_mysql_connection();
$columnMetaResult = $conn->query("SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'inventory' AND column_name = 'supplier_id' LIMIT 1");
if ($columnMetaResult instanceof mysqli_result) {
    $columnMeta = $columnMetaResult->fetch_assoc();
    $columnMetaResult->free();
    $dataType = strtolower((string) ($columnMeta['DATA_TYPE'] ?? ''));
    $numericTypes = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double'];
    if (in_array($dataType, $numericTypes, true)) {
        $conn->query("ALTER TABLE inventory MODIFY supplier_id VARCHAR(32) NULL");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id VARCHAR(32) NOT NULL UNIQUE,
    supplier_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(64) DEFAULT NULL,
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(120) DEFAULT NULL,
    state_province VARCHAR(120) DEFAULT NULL,
    postal_code VARCHAR(40) DEFAULT NULL,
    country VARCHAR(120) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (supplier_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$supplierOptions = [];
$supplierResult = $conn->query("SELECT supplier_id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name ASC, supplier_id ASC");
if ($supplierResult instanceof mysqli_result) {
    while ($row = $supplierResult->fetch_assoc()) {
        $supplierOptions[] = $row;
    }
    $supplierResult->free();
}

// Fields shown in the add form (user-facing only)
$formFields = [
    'item_name'         => 'Item Name',
    'category'          => 'Category',
    'description'       => 'Description',
    'brand'             => 'Brand',
    'model'             => 'Model / Part #',
    'supplier_id'       => 'Supplier ID',
    'supplier_name'     => 'Supplier Name',
    'unit'              => 'Unit (e.g. cuft, ea)',
    'quantity_in_stock' => 'Qty in Stock',
    'reorder_level'     => 'Reorder Level',
    'cost_price'        => 'Cost Price',
    'status'            => 'Status',
    'notes'             => 'Notes',
];

$intFields     = ['quantity_in_stock', 'reorder_level'];
$decimalFields = ['cost_price', 'margin', 'selling_price'];
$statusOptions = ['Stock', 'Production', 'Maintenance', 'Retired'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please refresh and try again.';
    }

    $newItem = [];
    $selectedSupplierId = trim((string) ($_POST['supplier_id'] ?? ''));
    $selectedSupplierName = '';

    if ($selectedSupplierId !== '') {
        $supplierStmt = $conn->prepare('SELECT supplier_name FROM suppliers WHERE supplier_id = ? LIMIT 1');
        $supplierStmt->bind_param('s', $selectedSupplierId);
        $supplierStmt->execute();
        $supplierResult = $supplierStmt->get_result();
        $supplierRow = $supplierResult->fetch_assoc();
        $supplierResult->free();
        $supplierStmt->close();

        if ($supplierRow) {
            $selectedSupplierName = (string) ($supplierRow['supplier_name'] ?? '');
        } else {
            $errors[] = 'Selected supplier ID was not found in Supplier Master.';
        }
    }

    foreach ($formFields as $f => $label) {
        if ($f === 'supplier_name') {
            $newItem[$f] = ($selectedSupplierName === '' ? null : $selectedSupplierName);
            continue;
        }
        $val = trim($_POST[$f] ?? '');
        if (in_array($f, $intFields, true)) {
            $newItem[$f] = ($val === '' ? null : (int)$val);
        } elseif (in_array($f, $decimalFields, true)) {
            $newItem[$f] = ($val === '' ? null : (float)$val);
        } else {
            $newItem[$f] = ($val === '') ? null : $val;
        }
    }

    $newItem['item_id']    = uniqid('ITM_');
    $newItem['created_at'] = date('Y-m-d H:i:s');
    $newItem['updated_at'] = date('Y-m-d H:i:s');

    if (!$errors) {
        $fields       = array_keys($newItem);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $types        = '';
        foreach ($fields as $f) {
            if (in_array($f, $intFields, true)) {
                $types .= 'i';
            } elseif (in_array($f, $decimalFields, true)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }

        $sql  = 'INSERT INTO inventory (' . implode(',', $fields) . ') VALUES (' . $placeholders . ')';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...array_values($newItem));
        $stmt->execute();
        $stmt->close();

        $conn->close();
        header('Location: inventory_list.php');
        exit;
    }
}

include_once(__DIR__ . '/layout_start.php');
?>
<div class="container" style="max-width:700px; margin:32px auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <h2 style="margin:0;">Add Inventory Item</h2>
        <a href="inventory_list.php" class="btn btn-outline-secondary">← Back</a>
    </div>
        <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                        <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                        </ul>
                </div>
        <?php endif; ?>
    <form method="post">
    <?php renderCSRFInput(); ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <?php foreach ($formFields as $f => $label): ?>
                <div style="display:flex; flex-direction:column; <?= in_array($f, ['description','notes'], true) ? 'grid-column:1/3;' : '' ?>">
                    <label for="<?= $f ?>" style="font-weight:600; margin-bottom:4px;"><?= htmlspecialchars($label) ?></label>
                    <?php if (in_array($f, ['description','notes'], true)): ?>
                        <textarea name="<?= $f ?>" id="<?= $f ?>" rows="3" class="form-control"><?= htmlspecialchars($_POST[$f] ?? '') ?></textarea>
                                            <?php elseif ($f === 'supplier_id'): ?>
                                                <select name="supplier_id" id="supplier_id" class="form-control">
                                                    <option value="">-- Select Supplier --</option>
                                                    <?php foreach ($supplierOptions as $supplier): ?>
                                                        <?php $sid = (string) ($supplier['supplier_id'] ?? ''); ?>
                                                        <option value="<?= htmlspecialchars($sid) ?>" data-name="<?= htmlspecialchars((string) ($supplier['supplier_name'] ?? '')) ?>" <?= ($_POST['supplier_id'] ?? '') === $sid ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($sid . ' - ' . ((string) ($supplier['supplier_name'] ?? ''))) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="form-text"><a href="supplier_directory.php" target="_blank" rel="noopener">Manage suppliers</a></div>
                                            <?php elseif ($f === 'supplier_name'): ?>
                                                <input type="text" name="supplier_name" id="supplier_name" value="<?= htmlspecialchars($_POST['supplier_name'] ?? '') ?>" class="form-control" readonly>
                      <?php elseif ($f === 'status'): ?>
                        <select name="status" id="status" class="form-control">
                          <option value="">-- Select Status --</option>
                          <?php foreach ($statusOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= ($_POST['status'] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                          <?php endforeach; ?>
                        </select>
                      <?php else: ?>
                        <input type="<?= in_array($f, $intFields, true) ? 'number' : 'text' ?>" name="<?= $f ?>" id="<?= $f ?>" value="<?= htmlspecialchars($_POST[$f] ?? '') ?>" class="form-control">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:24px; display:flex; gap:12px;">
            <button type="submit" class="btn btn-success">💾 Save Item</button>
            <a href="inventory_list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var supplierSelect = document.getElementById('supplier_id');
    var supplierName = document.getElementById('supplier_name');
    if (!supplierSelect || !supplierName) {
        return;
    }

    var syncName = function () {
        var selectedOption = supplierSelect.options[supplierSelect.selectedIndex];
        supplierName.value = selectedOption ? (selectedOption.getAttribute('data-name') || '') : '';
    };

    supplierSelect.addEventListener('change', syncName);
    syncName();
});
</script>
<?php include_once(__DIR__ . '/layout_end.php'); ?>
