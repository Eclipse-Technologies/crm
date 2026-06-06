<?php
require_once 'db_mysql.php';
require_once __DIR__ . '/request_guard.php';
require_once __DIR__ . '/audit_handler.php';
require_once 'inventory_mysql.php';

$schema = require __DIR__ . '/purchase_order_schema.php';
$inventorySchema = require __DIR__ . '/inventory_schema.php';
$inventory = fetch_inventory_mysql($inventorySchema);

$itemFields = ['item_id','item_name','quantity','unit','unit_price','discount','tax_rate','tax_amount','total'];
$headerFields = [
  'date','status','supplier_id','supplier_name','supplier_contact','supplier_address','billing_address','shipping_address',
  'subtotal','total_discount','total_tax','shipping_cost','other_fees','grand_total','currency','expected_delivery','payment_terms','notes','created_by'
];

function redirect_po_edit(string $url): void {
  if (!headers_sent()) {
    header('Location: ' . $url);
    exit;
  }
  echo '<script>window.location.href=' . json_encode($url) . ';</script>';
  echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_po'])) {
  require_post_with_csrf();
  $poNumber = trim($_POST['po_number'] ?? '');
  if ($poNumber === '') {
    logAuditAction('update', 'purchase_order', '', [], 'Purchase order update failed: missing po_number', 'failed', 'Missing po_number');
    redirect_po_edit('purchase_orders_list.php?error=invalid_request');
  }

  $conn = get_mysql_connection();
  try {
    $conn->begin_transaction();

    $snapshotStmt = $conn->prepare('SELECT updated_at FROM purchase_orders WHERE po_number = ? LIMIT 1');
    $snapshotStmt->bind_param('s', $poNumber);
    $snapshotStmt->execute();
    $snapshotRes = $snapshotStmt->get_result();
    $snapshot = $snapshotRes ? $snapshotRes->fetch_assoc() : null;
    if ($snapshotRes instanceof mysqli_result) {
      $snapshotRes->free();
    }
    $snapshotStmt->close();

    if (!$snapshot) {
      $conn->rollback();
      $conn->close();
      logAuditAction('update', 'purchase_order', $poNumber, [], 'Purchase order update failed: PO not found', 'failed', 'PO not found');
      redirect_po_edit('purchase_orders_list.php?error=not_found');
    }

    $headerSet = [];
    $headerValues = [];
    $headerTypes = '';
    foreach ($headerFields as $field) {
      $headerSet[] = '`' . $field . '` = ?';
      $headerValues[] = trim((string) ($_POST[$field] ?? ''));
      $headerTypes .= 's';
    }
    $updatedAt = date('Y-m-d H:i:s');
    $headerSet[] = '`updated_at` = ?';
    $headerValues[] = $updatedAt;
    $headerTypes .= 's';
    $headerValues[] = $poNumber;
    $headerTypes .= 's';

    $updateHeaderStmt = $conn->prepare('UPDATE purchase_orders SET ' . implode(', ', $headerSet) . ' WHERE po_number = ?');
    $updateHeaderStmt->bind_param($headerTypes, ...$headerValues);
    if (!$updateHeaderStmt->execute()) {
      $error = $updateHeaderStmt->error;
      $updateHeaderStmt->close();
      throw new RuntimeException('Header update failed: ' . $error);
    }
    $updateHeaderStmt->close();

    $oldItemCountStmt = $conn->prepare('SELECT COUNT(*) AS c FROM purchase_order_items WHERE po_number = ?');
    $oldItemCountStmt->bind_param('s', $poNumber);
    $oldItemCountStmt->execute();
    $oldItemCountRes = $oldItemCountStmt->get_result();
    $oldItemCountRow = $oldItemCountRes ? $oldItemCountRes->fetch_assoc() : ['c' => 0];
    $oldItemCount = (int) ($oldItemCountRow['c'] ?? 0);
    if ($oldItemCountRes instanceof mysqli_result) {
      $oldItemCountRes->free();
    }
    $oldItemCountStmt->close();

    $deleteItemsStmt = $conn->prepare('DELETE FROM purchase_order_items WHERE po_number = ?');
    $deleteItemsStmt->bind_param('s', $poNumber);
    if (!$deleteItemsStmt->execute()) {
      $error = $deleteItemsStmt->error;
      $deleteItemsStmt->close();
      throw new RuntimeException('Item delete failed: ' . $error);
    }
    $deleteItemsStmt->close();

    $itemIds = $_POST['item_id'] ?? [];
    $itemCount = is_array($itemIds) ? count($itemIds) : 0;
    $insertItemStmt = $conn->prepare('INSERT INTO purchase_order_items (po_number,item_id,item_name,quantity,unit,unit_price,discount,tax_rate,tax_amount,total) VALUES (?,?,?,?,?,?,?,?,?,?)');
    for ($i = 0; $i < $itemCount; $i++) {
      $itemId = trim((string) ($_POST['item_id'][$i] ?? ''));
      $itemName = trim((string) ($_POST['item_name'][$i] ?? ''));
      if ($itemId === '' && $itemName === '') {
        continue;
      }
      $quantity = trim((string) ($_POST['quantity'][$i] ?? ''));
      $unit = trim((string) ($_POST['unit'][$i] ?? ''));
      $unitPrice = trim((string) ($_POST['unit_price'][$i] ?? ''));
      $discount = trim((string) ($_POST['discount'][$i] ?? ''));
      $taxRate = trim((string) ($_POST['tax_rate'][$i] ?? ''));
      $taxAmount = trim((string) ($_POST['tax_amount'][$i] ?? ''));
      $total = trim((string) ($_POST['total'][$i] ?? ''));
      $insertItemStmt->bind_param('ssssssssss', $poNumber, $itemId, $itemName, $quantity, $unit, $unitPrice, $discount, $taxRate, $taxAmount, $total);
      if (!$insertItemStmt->execute()) {
        $error = $insertItemStmt->error;
        $insertItemStmt->close();
        throw new RuntimeException('Item insert failed: ' . $error);
      }
    }
    $insertItemStmt->close();

    $newItemCountStmt = $conn->prepare('SELECT COUNT(*) AS c FROM purchase_order_items WHERE po_number = ?');
    $newItemCountStmt->bind_param('s', $poNumber);
    $newItemCountStmt->execute();
    $newItemCountRes = $newItemCountStmt->get_result();
    $newItemCountRow = $newItemCountRes ? $newItemCountRes->fetch_assoc() : ['c' => 0];
    $newItemCount = (int) ($newItemCountRow['c'] ?? 0);
    if ($newItemCountRes instanceof mysqli_result) {
      $newItemCountRes->free();
    }
    $newItemCountStmt->close();

    $conn->commit();
    $conn->close();

    logAuditAction(
      'update',
      'purchase_order',
      $poNumber,
      [
        'item_count' => ['old' => $oldItemCount, 'new' => $newItemCount],
        'updated_at' => ['old' => (string) ($snapshot['updated_at'] ?? ''), 'new' => $updatedAt],
      ],
      'Purchase order updated (MySQL flow)',
      'success',
      null
    );

    redirect_po_edit('purchase_orders_list.php?success=updated');
  } catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    logAuditAction(
      'update',
      'purchase_order',
      $poNumber,
      [],
      'Purchase order update failed (MySQL flow)',
      'failed',
      $e->getMessage()
    );
    redirect_po_edit('purchase_orders_list.php?error=update_failed');
  }
}

$poNumber = trim((string) ($_GET['po'] ?? ''));
$poRows = [];
$header = [];
if ($poNumber !== '') {
  $conn = get_mysql_connection();
  $headerStmt = $conn->prepare('SELECT po_number,date,status,supplier_id,supplier_name,supplier_contact,supplier_address,billing_address,shipping_address,subtotal,total_discount,total_tax,shipping_cost,other_fees,grand_total,currency,expected_delivery,payment_terms,notes,created_by,created_at,updated_at FROM purchase_orders WHERE po_number = ? LIMIT 1');
  $headerStmt->bind_param('s', $poNumber);
  $headerStmt->execute();
  $headerRes = $headerStmt->get_result();
  $header = $headerRes ? ($headerRes->fetch_assoc() ?: []) : [];
  if ($headerRes instanceof mysqli_result) {
    $headerRes->free();
  }
  $headerStmt->close();

  $itemsStmt = $conn->prepare('SELECT item_id,item_name,quantity,unit,unit_price,discount,tax_rate,tax_amount,total FROM purchase_order_items WHERE po_number = ? ORDER BY id ASC');
  $itemsStmt->bind_param('s', $poNumber);
  $itemsStmt->execute();
  $itemsRes = $itemsStmt->get_result();
  if ($itemsRes instanceof mysqli_result) {
    while ($row = $itemsRes->fetch_assoc()) {
      $poRows[] = $row;
    }
    $itemsRes->free();
  }
  $itemsStmt->close();
  $conn->close();
}

include_once(__DIR__ . '/layout_start.php');
?>
<div class="container">
  <h2>Edit Purchase Order</h2>
  <?php if ($poNumber === '' || empty($poRows)): ?>
    <div style="text-align:center; color:#888; margin:18px 0;">Purchase order not found.</div>
    <div style="text-align:center;">
      <a href="purchase_orders_list.php" class="btn-outline">Back to Purchase Orders</a>
    </div>
  <?php else: ?>
    <form method="post" style="max-width:900px; margin:auto; background:#fafbfc; border-radius:8px; padding:24px 28px 18px 28px; box-shadow:0 2px 8px #0001;">
      <?php renderCSRFInput(); ?>
      <input type="hidden" name="update_po" value="1">
      <input type="hidden" name="created_at" value="<?= htmlspecialchars($header['created_at'] ?? '') ?>">
      <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px 24px; margin-bottom:18px; align-items:end;">
        <?php foreach ($schema as $f):
          if (in_array($f, array_merge($itemFields, ['created_at','updated_at','po_number','date']), true)) continue;
          $value = htmlspecialchars($header[$f] ?? '');
        ?>
          <div style="display:flex; flex-direction:column; min-width:160px;">
            <label for="<?= $f ?>" style="font-weight:600; margin-bottom:2px; font-size:0.97em; color:#222;"> <?= htmlspecialchars(ucwords(str_replace('_',' ',$f))) ?> </label>
            <input type="text" name="<?= $f ?>" id="<?= $f ?>" value="<?= $value ?>" style="padding:4px 7px; border-radius:4px; border:1px solid #bbb; font-size:0.97em;">
          </div>
        <?php endforeach; ?>
        <div style="display:flex; flex-direction:column; min-width:120px;">
          <label for="po_number" style="font-weight:600; margin-bottom:2px; font-size:0.97em; color:#222;">PO Number</label>
          <input type="text" name="po_number" id="po_number" value="<?= htmlspecialchars($poNumber) ?>" readonly style="padding:4px 7px; border-radius:4px; border:1px solid #bbb; background:#f5f5f5; font-size:0.97em;">
        </div>
        <div style="display:flex; flex-direction:column; min-width:120px;">
          <label for="date" style="font-weight:600; margin-bottom:2px; font-size:0.97em; color:#222;">Date</label>
          <input type="text" name="date" id="date" value="<?= htmlspecialchars($header['date'] ?? '') ?>" style="padding:4px 7px; border-radius:4px; border:1px solid #bbb; font-size:0.97em;">
        </div>
      </div>
      <h3 style="margin:18px 0 8px 0; font-size:1.13em; color:#222;">Order Items</h3>
      <table id="po-items-table" style="width:100%; border-collapse:collapse; margin-bottom:12px; background:#fff;">
        <thead>
          <tr style="background:#f5f5f5; font-size:0.97em;">
            <th style="padding:6px 4px;">Item</th>
            <th style="padding:6px 4px;">Item Name</th>
            <th style="padding:6px 4px;">Qty</th>
            <th style="padding:6px 4px;">Unit</th>
            <th style="padding:6px 4px;">Unit Price</th>
            <th style="padding:6px 4px;">Discount</th>
            <th style="padding:6px 4px;">Tax %</th>
            <th style="padding:6px 4px;">Tax Amt</th>
            <th style="padding:6px 4px;">Total</th>
            <th style="padding:6px 4px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($poRows)): ?>
            <tr>
              <td>
                <select name="item_id[]" style="width:120px; padding:3px 5px; font-size:0.97em;">
                  <option value="">-- Select --</option>
                  <?php foreach ($inventory as $item): ?>
                    <option value="<?= htmlspecialchars($item['item_id']) ?>">
                      <?= htmlspecialchars($item['item_id']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="text" name="item_name[]" style="width:110px; padding:3px 5px; font-size:0.97em;"></td>
              <td><input type="number" name="quantity[]" min="1" style="width:55px; padding:3px 5px; font-size:0.97em;"></td>
              <td><input type="text" name="unit[]" style="width:50px; padding:3px 5px; font-size:0.97em;"></td>
              <td><input type="number" name="unit_price[]" step="0.01" style="width:75px; padding:3px 5px; font-size:0.97em;"></td>
              <td><input type="number" name="discount[]" step="0.01" style="width:60px; padding:3px 5px; font-size:0.97em;"></td>
              <td><input type="number" name="tax_rate[]" step="0.01" style="width:55px; padding:3px 5px; font-size:0.97em;"></td>
              <td><input type="number" name="tax_amount[]" step="0.01" style="width:75px; padding:3px 5px; font-size:0.97em;"></td>
              <td><input type="number" name="total[]" step="0.01" style="width:85px; padding:3px 5px; font-size:0.97em;"></td>
              <td><button type="button" onclick="removeRow(this)" style="padding:2px 7px; font-size:1.1em;">🗑</button></td>
            </tr>
          <?php else: ?>
            <?php foreach ($poRows as $row): ?>
              <tr>
                <td>
                  <select name="item_id[]" style="width:120px; padding:3px 5px; font-size:0.97em;">
                    <option value="">-- Select --</option>
                    <?php foreach ($inventory as $item): ?>
                      <?php $selected = ($item['item_id'] ?? '') === ($row['item_id'] ?? '') ? 'selected' : ''; ?>
                      <option value="<?= htmlspecialchars($item['item_id']) ?>" <?= $selected ?>>
                        <?= htmlspecialchars($item['item_id']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="text" name="item_name[]" value="<?= htmlspecialchars($row['item_name'] ?? '') ?>" style="width:110px; padding:3px 5px; font-size:0.97em;"></td>
                <td><input type="number" name="quantity[]" min="1" value="<?= htmlspecialchars($row['quantity'] ?? '') ?>" style="width:55px; padding:3px 5px; font-size:0.97em;"></td>
                <td><input type="text" name="unit[]" value="<?= htmlspecialchars($row['unit'] ?? '') ?>" style="width:50px; padding:3px 5px; font-size:0.97em;"></td>
                <td><input type="number" name="unit_price[]" step="0.01" value="<?= htmlspecialchars($row['unit_price'] ?? '') ?>" style="width:75px; padding:3px 5px; font-size:0.97em;"></td>
                <td><input type="number" name="discount[]" step="0.01" value="<?= htmlspecialchars($row['discount'] ?? '') ?>" style="width:60px; padding:3px 5px; font-size:0.97em;"></td>
                <td><input type="number" name="tax_rate[]" step="0.01" value="<?= htmlspecialchars($row['tax_rate'] ?? '') ?>" style="width:55px; padding:3px 5px; font-size:0.97em;"></td>
                <td><input type="number" name="tax_amount[]" step="0.01" value="<?= htmlspecialchars($row['tax_amount'] ?? '') ?>" style="width:75px; padding:3px 5px; font-size:0.97em;"></td>
                <td><input type="number" name="total[]" step="0.01" value="<?= htmlspecialchars($row['total'] ?? '') ?>" style="width:85px; padding:3px 5px; font-size:0.97em;"></td>
                <td><button type="button" onclick="removeRow(this)" style="padding:2px 7px; font-size:1.1em;">🗑</button></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <button type="button" onclick="addRow()" style="margin-bottom:18px;">➕ Add Item</button>
      <div style="margin-top:18px; text-align:center;">
        <button type="submit" class="btn-primary" style="padding:7px 22px; font-size:1.08em;">💾 Update Purchase Order</button>
        <a href="purchase_orders_list.php" class="btn-outline" style="margin-left:18px;">Cancel</a>
      </div>
    </form>
    <script>
      function addRow() {
        const table = document.getElementById('po-items-table').getElementsByTagName('tbody')[0];
        const row = table.rows[0].cloneNode(true);
        Array.from(row.querySelectorAll('input,select')).forEach(el => el.value = '');
        table.appendChild(row);
      }
      function removeRow(btn) {
        const table = document.getElementById('po-items-table').getElementsByTagName('tbody')[0];
        if (table.rows.length > 1) {
          btn.closest('tr').remove();
        }
      }
    </script>
  <?php endif; ?>
</div>
<?php include_once(__DIR__ . '/layout_end.php'); ?>
