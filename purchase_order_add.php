<?php
include_once(__DIR__ . '/layout_start.php');
require_once 'db_mysql.php';
require_once 'inventory_mysql.php';
require_once __DIR__ . '/request_guard.php';
require_once __DIR__ . '/audit_handler.php';

function redirect_po_add(string $url): void {
  if (!headers_sent()) {
    header('Location: ' . $url);
    exit;
  }
  echo '<script>window.location.href=' . json_encode($url) . ';</script>';
  echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
  exit;
}


$schema = require __DIR__ . '/purchase_order_schema.php';
$poFile = __DIR__ . '/purchase_orders.csv';
$errors = [];
$inventorySchema = require __DIR__ . '/inventory_schema.php';
$inventory = fetch_inventory_mysql($inventorySchema);
$supplierOptions = [];
$supplierConn = get_mysql_connection();
$poColumnMetaResult = $supplierConn->query("SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'purchase_orders' AND column_name = 'supplier_id' LIMIT 1");
if ($poColumnMetaResult instanceof mysqli_result) {
  $poColumnMeta = $poColumnMetaResult->fetch_assoc();
  $poColumnMetaResult->free();
  $poDataType = strtolower((string) ($poColumnMeta['DATA_TYPE'] ?? ''));
  $numericTypes = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double'];
  if (in_array($poDataType, $numericTypes, true)) {
    $supplierConn->query("ALTER TABLE purchase_orders MODIFY supplier_id VARCHAR(32) NULL");
  }
}

$supplierConn->query("CREATE TABLE IF NOT EXISTS suppliers (
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
$supplierResult = $supplierConn->query("SELECT supplier_id, supplier_name, contact_name, email, phone, address_line1, address_line2, city, state_province, postal_code, country FROM suppliers WHERE is_active = 1 ORDER BY supplier_name ASC, supplier_id ASC");
if ($supplierResult instanceof mysqli_result) {
  while ($row = $supplierResult->fetch_assoc()) {
    $contactParts = [];
    $contactName = trim((string) ($row['contact_name'] ?? ''));
    $email = trim((string) ($row['email'] ?? ''));
    $phone = trim((string) ($row['phone'] ?? ''));
    if ($contactName !== '') {
      $contactParts[] = $contactName;
    }
    if ($email !== '') {
      $contactParts[] = $email;
    }
    if ($phone !== '') {
      $contactParts[] = $phone;
    }

    $addressParts = [];
    foreach (['address_line1', 'address_line2', 'city', 'state_province', 'postal_code', 'country'] as $addressKey) {
      $part = trim((string) ($row[$addressKey] ?? ''));
      if ($part !== '') {
        $addressParts[] = $part;
      }
    }

    $row['supplier_contact'] = implode(' | ', $contactParts);
    $row['supplier_address'] = implode("\n", $addressParts);
    $supplierOptions[] = $row;
  }
  $supplierResult->free();
}
$supplierConn->close();

function generate_po_number() {
  $prefix = 'EWTPO';
  $conn = get_mysql_connection();
  $sequence = random_int(50, 100);
  while ($sequence <= 99999) {
    $po_number = $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("SELECT 1 FROM purchase_orders WHERE po_number = ? LIMIT 1");
    $stmt->bind_param('s', $po_number);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->close();
      break;
    }
    $stmt->close();
    $sequence++;
  }
  $conn->close();
  if (!isset($po_number) || $po_number === '') {
    $po_number = $prefix . str_pad((string) random_int(50, 100), 5, '0', STR_PAD_LEFT);
  }
  return $po_number;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_post_with_csrf();
  $po_number = generate_po_number();
  $date = date('Y-m-d');
  $created_at = date('Y-m-d H:i:s');
  $updated_at = $created_at;
  $item_count = isset($_POST['item_id']) ? count($_POST['item_id']) : 0;
  $conn = get_mysql_connection();
  $conn->begin_transaction();
  $selectedSupplierId = trim((string) ($_POST['supplier_id'] ?? ''));
  $selectedSupplierName = '';
  $selectedSupplierContact = '';
  $selectedSupplierAddress = '';
  if ($selectedSupplierId !== '') {
    $supplierStmt = $conn->prepare('SELECT supplier_name, contact_name, email, phone, address_line1, address_line2, city, state_province, postal_code, country FROM suppliers WHERE supplier_id = ? LIMIT 1');
    $supplierStmt->bind_param('s', $selectedSupplierId);
    $supplierStmt->execute();
    $supplierRes = $supplierStmt->get_result();
    $supplierRow = $supplierRes->fetch_assoc();
    $supplierRes->free();
    $supplierStmt->close();
    if ($supplierRow) {
      $selectedSupplierName = (string) ($supplierRow['supplier_name'] ?? '');

      $contactParts = [];
      foreach (['contact_name', 'email', 'phone'] as $contactKey) {
        $part = trim((string) ($supplierRow[$contactKey] ?? ''));
        if ($part !== '') {
          $contactParts[] = $part;
        }
      }
      $selectedSupplierContact = implode(' | ', $contactParts);

      $addressParts = [];
      foreach (['address_line1', 'address_line2', 'city', 'state_province', 'postal_code', 'country'] as $addressKey) {
        $part = trim((string) ($supplierRow[$addressKey] ?? ''));
        if ($part !== '') {
          $addressParts[] = $part;
        }
      }
      $selectedSupplierAddress = implode("\n", $addressParts);
    }
  }
  // Insert into purchase_orders (header)
  $orderFields = [
    'po_number','date','status','supplier_id','supplier_name','supplier_contact','supplier_address','billing_address','shipping_address',
    'subtotal','total_discount','total_tax','shipping_cost','other_fees','grand_total','currency','expected_delivery','payment_terms','notes','created_by','created_at','updated_at'
  ];
  $orderData = [];
  $decimalFields = ['subtotal','total_discount','total_tax','shipping_cost','other_fees','grand_total'];
  $dateFields = ['date','expected_delivery','created_at','updated_at'];
  foreach ($orderFields as $f) {
    $val = trim($_POST[$f] ?? '');
    if ($f === 'po_number') $val = $po_number;
    if ($f === 'date') $val = $date;
    if ($f === 'created_at' || $f === 'updated_at') $val = $created_at;
    if ($f === 'supplier_name') $val = $selectedSupplierName;
    if ($f === 'supplier_contact') $val = $selectedSupplierContact;
    if ($f === 'supplier_address') $val = $selectedSupplierAddress;
    if (in_array($f, $decimalFields)) {
      $orderData[] = ($val === '' ? null : (float)$val);
    } elseif (in_array($f, $dateFields)) {
      $orderData[] = ($val === '' ? null : $val);
    } else {
      $orderData[] = $val;
    }
  }
  $orderPlaceholders = implode(',', array_fill(0, count($orderFields), '?'));
  $orderTypes = '';
  foreach ($orderFields as $f) {
    $orderTypes .= in_array($f, $decimalFields) ? 'd' : 's';
  }
  $orderStmt = $conn->prepare('INSERT INTO purchase_orders (' . implode(',', $orderFields) . ') VALUES (' . $orderPlaceholders . ')');
  if (!$orderStmt) {
    $conn->rollback();
    logAuditAction('create', 'purchase_order', $po_number, ['po_number' => ['old' => null, 'new' => $po_number]], 'Purchase order create failed (prepare header)', 'failed', $conn->error);
    $conn->close();
    redirect_po_add('purchase_orders_list.php?error=create_failed');
  }
  $orderStmt->bind_param($orderTypes, ...$orderData);
  if (!$orderStmt->execute()) {
    $error = $orderStmt->error;
    $orderStmt->close();
    $conn->rollback();
    logAuditAction('create', 'purchase_order', $po_number, ['po_number' => ['old' => null, 'new' => $po_number]], 'Purchase order create failed (execute header)', 'failed', $error);
    $conn->close();
    redirect_po_add('purchase_orders_list.php?error=create_failed');
  }
  $orderStmt->close();
  // Insert items into purchase_order_items
  $itemFields = ['po_number','item_id','item_name','quantity','unit','unit_price','discount','tax_rate','tax_amount','total'];
  $itemDecimalFields = ['quantity','unit_price','discount','tax_rate','tax_amount','total'];
  for ($i = 0; $i < $item_count; $i++) {
    $itemData = [$po_number];
    foreach (array_slice($itemFields, 1) as $f) {
      $val = trim($_POST[$f][$i] ?? '');
      if (in_array($f, $itemDecimalFields)) {
        $itemData[] = ($val === '' ? null : (float)$val);
      } else {
        $itemData[] = $val;
      }
    }
    $itemPlaceholders = implode(',', array_fill(0, count($itemFields), '?'));
    $itemTypes = 's';
    foreach (array_slice($itemFields, 1) as $f) {
      $itemTypes .= in_array($f, $itemDecimalFields) ? 'd' : 's';
    }
    $itemStmt = $conn->prepare('INSERT INTO purchase_order_items (' . implode(',', $itemFields) . ') VALUES (' . $itemPlaceholders . ')');
    if (!$itemStmt) {
      $conn->rollback();
      logAuditAction('create', 'purchase_order', $po_number, ['po_number' => ['old' => null, 'new' => $po_number], 'item_count' => ['old' => 0, 'new' => $item_count]], 'Purchase order create failed (prepare item)', 'failed', $conn->error);
      $conn->close();
      redirect_po_add('purchase_orders_list.php?error=create_failed');
    }
    $itemStmt->bind_param($itemTypes, ...$itemData);
    if (!$itemStmt->execute()) {
      $error = $itemStmt->error;
      $itemStmt->close();
      $conn->rollback();
      logAuditAction('create', 'purchase_order', $po_number, ['po_number' => ['old' => null, 'new' => $po_number], 'item_count' => ['old' => 0, 'new' => $item_count]], 'Purchase order create failed (execute item)', 'failed', $error);
      $conn->close();
      redirect_po_add('purchase_orders_list.php?error=create_failed');
    }
    $itemStmt->close();
  }
  $conn->commit();
  logAuditAction(
    'create',
    'purchase_order',
    $po_number,
    [
      'po_number' => ['old' => null, 'new' => $po_number],
      'supplier_id' => ['old' => null, 'new' => $selectedSupplierId],
      'supplier_name' => ['old' => null, 'new' => $selectedSupplierName],
      'item_count' => ['old' => 0, 'new' => $item_count],
      'grand_total' => ['old' => null, 'new' => (string) ($orderData[array_search('grand_total', $orderFields, true)] ?? '')],
    ],
    'Purchase order created',
    'success',
    null
  );
  $conn->close();
  redirect_po_add('purchase_orders_list.php');
}
?>
<div class="container">
  <h2>Add Purchase Order</h2>
  <form method="post" style="max-width:900px; margin:auto; background:#fafbfc; border-radius:8px; padding:24px 28px 18px 28px; box-shadow:0 2px 8px #0001;">
    <?php renderCSRFInput(); ?>
    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px 24px; margin-bottom:18px; align-items:end;">
      <?php foreach ($schema as $f):
        if (in_array($f, ['created_at','updated_at','po_number','date','item_id','item_name','quantity','unit','unit_price','discount','tax_rate','tax_amount','total'])) continue;
        $readonly = '';
        if ($f === 'supplier_name') $readonly = 'readonly';
        $value = htmlspecialchars($_POST[$f] ?? '');
      ?>
        <div style="display:flex; flex-direction:column; min-width:160px;">
          <label for="<?= $f ?>" style="font-weight:600; margin-bottom:2px; font-size:0.97em; color:#222;"> <?= htmlspecialchars(ucwords(str_replace('_',' ',$f))) ?> </label>
          <?php if ($f === 'supplier_id'): ?>
            <select name="supplier_id" id="supplier_id" style="padding:4px 7px; border-radius:4px; border:1px solid #bbb; font-size:0.97em;">
              <option value="">-- Select Supplier --</option>
              <?php foreach ($supplierOptions as $supplier): ?>
                <?php $sid = (string) ($supplier['supplier_id'] ?? ''); ?>
                <option value="<?= htmlspecialchars($sid) ?>"
                        data-name="<?= htmlspecialchars((string) ($supplier['supplier_name'] ?? '')) ?>"
                        data-contact="<?= htmlspecialchars((string) ($supplier['supplier_contact'] ?? '')) ?>"
                        data-address="<?= htmlspecialchars((string) ($supplier['supplier_address'] ?? '')) ?>"
                        <?= ($_POST['supplier_id'] ?? '') === $sid ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sid . ' - ' . ((string) ($supplier['supplier_name'] ?? ''))) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span style="font-size:0.85em; margin-top:4px;"><a href="supplier_directory.php" target="_blank" rel="noopener">Manage suppliers</a></span>
          <?php else: ?>
            <input type="text" name="<?= $f ?>" id="<?= $f ?>" value="<?= $value ?>" style="padding:4px 7px; border-radius:4px; border:1px solid #bbb; font-size:0.97em;" <?= $readonly ?> >
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div style="display:flex; flex-direction:column; min-width:120px;">
        <label for="po_number" style="font-weight:600; margin-bottom:2px; font-size:0.97em; color:#222;">PO Number</label>
        <input type="text" name="po_number" id="po_number" value="<?= generate_po_number() ?>" readonly style="padding:4px 7px; border-radius:4px; border:1px solid #bbb; background:#f5f5f5; font-size:0.97em;">
      </div>
      <div style="display:flex; flex-direction:column; min-width:120px;">
        <label for="date" style="font-weight:600; margin-bottom:2px; font-size:0.97em; color:#222;">Date</label>
        <input type="text" name="date" id="date" value="<?= date('Y-m-d') ?>" readonly style="padding:4px 7px; border-radius:4px; border:1px solid #bbb; background:#f5f5f5; font-size:0.97em;">
      </div>
    </div>
    <h3 style="margin:18px 0 8px 0; font-size:1.13em; color:#222;">Order Items</h3>
    <style>
      #po-items-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 6px;
        margin-bottom: 16px;
        background: #fff;
        box-shadow: 0 1px 4px #0001;
        border-radius: 8px;
        overflow: hidden;
      }
      #po-items-table th, #po-items-table td {
        padding: 8px 6px;
        text-align: left;
        font-size: 1em;
      }
      #po-items-table th {
        background: #f5f5f5;
        font-weight: 600;
        color: #234;
        border-bottom: 1px solid #e0e0e0;
      }
      #po-items-table td {
        background: #fcfcfd;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
      }
      #po-items-table input, #po-items-table select {
        width: 100%;
        padding: 5px 7px;
        border-radius: 4px;
        border: 1px solid #bbb;
        font-size: 1em;
        box-sizing: border-box;
        background: #fff;
      }
      #po-items-table input[type="number"] {
        text-align: right;
      }
      #po-items-table button {
        padding: 2px 9px;
        font-size: 1.1em;
        border-radius: 4px;
        border: none;
        background: #f8d7da;
        color: #a71d2a;
        cursor: pointer;
        transition: background 0.2s;
      }
      #po-items-table button:hover {
        background: #f1b0b7;
      }
    </style>
    <table id="po-items-table">
      <thead>
        <tr>
          <th style="min-width:120px;">Item</th>
          <th style="min-width:140px;">Item Name</th>
          <th style="min-width:60px;">Qty</th>
          <th style="min-width:60px;">Unit</th>
          <th style="min-width:90px;">Unit Price</th>
          <th style="min-width:70px;">Discount</th>
          <th style="min-width:60px;">Tax %</th>
          <th style="min-width:90px;">Tax Amt</th>
          <th style="min-width:100px;">Total</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <select name="item_id[]">
              <option value="">-- Select --</option>
              <?php foreach ($inventory as $item): ?>
                <option value="<?= htmlspecialchars($item['item_id']) ?>">
                  <?= htmlspecialchars($item['item_id']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="text" name="item_name[]"></td>
          <td><input type="number" name="quantity[]" min="1"></td>
          <td><input type="text" name="unit[]"></td>
          <td><input type="number" name="unit_price[]" step="0.01"></td>
          <td><input type="number" name="discount[]" step="0.01"></td>
          <td><input type="number" name="tax_rate[]" step="0.01"></td>
          <td><input type="number" name="tax_amount[]" step="0.01"></td>
          <td><input type="number" name="total[]" step="0.01"></td>
          <td><button type="button" onclick="removeRow(this)">🗑</button></td>
        </tr>
      </tbody>
    </table>
    <button type="button" onclick="addRow()" style="margin-bottom:18px; background:#e7f3e7; color:#1a5d1a; border:1px solid #b6e2b6; border-radius:4px; padding:6px 16px; font-size:1em; cursor:pointer;">➕ Add Item</button>
    <div style="margin-top:18px; text-align:center;">
      <button type="submit" class="btn-primary" style="padding:7px 22px; font-size:1.08em;">💾 Save Purchase Order</button>
      <a href="purchase_orders_list.php" class="btn-outline" style="margin-left:18px;">Cancel</a>
    </div>
  </form>
  <script>
    // Inventory data for JS (item_id -> {item_name, unit, unit_price})
    const inventoryData = {};
    <?php foreach ($inventory as $item): ?>
      inventoryData["<?= addslashes($item['item_id']) ?>"] = {
        item_name: "<?= addslashes($item['item_name'] ?? '') ?>",
        unit: "<?= addslashes($item['unit'] ?? '') ?>",
        unit_price: "<?= addslashes($item['unit_price'] ?? '') ?>"
      };
    <?php endforeach; ?>

    function addRow() {
      const table = document.getElementById('po-items-table').getElementsByTagName('tbody')[0];
      const row = table.rows[0].cloneNode(true);
      // Clear all input values in the new row
      Array.from(row.querySelectorAll('input,select')).forEach(el => el.value = '');
      attachItemSelectListener(row);
      table.appendChild(row);
    }

    function removeRow(btn) {
      const table = document.getElementById('po-items-table').getElementsByTagName('tbody')[0];
      if (table.rows.length > 1) {
        btn.closest('tr').remove();
      }
    }

    function attachItemSelectListener(row) {
      const select = row.querySelector('select[name="item_id[]"]');
      if (!select) return;
      select.addEventListener('change', function() {
        const val = this.value;
        const data = inventoryData[val] || {};
        // Find sibling inputs in the same row
        const inputs = row.querySelectorAll('input');
        inputs.forEach(input => {
          if (input.name === 'item_name[]') input.value = data.item_name || '';
          if (input.name === 'unit[]') input.value = data.unit || '';
          if (input.name === 'unit_price[]') input.value = data.unit_price || '';
        });
      });
    }

    // Attach listeners to all existing rows on page load
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('#po-items-table tbody tr').forEach(attachItemSelectListener);

      const supplierSelect = document.getElementById('supplier_id');
      const supplierName = document.getElementById('supplier_name');
      const supplierContact = document.getElementById('supplier_contact');
      const supplierAddress = document.getElementById('supplier_address');
      if (supplierSelect && supplierName) {
        const syncSupplierFields = function() {
          const selectedOption = supplierSelect.options[supplierSelect.selectedIndex];
          supplierName.value = selectedOption ? (selectedOption.getAttribute('data-name') || '') : '';
          if (supplierContact) {
            supplierContact.value = selectedOption ? (selectedOption.getAttribute('data-contact') || '') : '';
          }
          if (supplierAddress) {
            supplierAddress.value = selectedOption ? (selectedOption.getAttribute('data-address') || '') : '';
          }
        };
        supplierSelect.addEventListener('change', syncSupplierFields);
        syncSupplierFields();
      }
    });
  </script>
  </form>
</div>
<?php include_once(__DIR__ . '/layout_end.php'); ?>
