<?php
include_once(__DIR__ . '/layout_start.php');
require_once 'csv_handler.php';
require_once __DIR__ . '/request_guard.php';

$schema = require __DIR__ . '/purchase_order_schema.php';
$poFile = __DIR__ . '/purchase_orders.csv';
$inventorySchema = require __DIR__ . '/inventory_schema.php';
$inventoryFile = __DIR__ . '/inventory.csv';
$inventory = readCSV($inventoryFile, $inventorySchema);
$orders = readCSV($poFile, $schema);

$itemFields = ['item_id','item_name','quantity','unit','unit_price','discount','tax_rate','tax_amount','total'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_po'])) {
  require_post_with_csrf();
  $poNumber = trim($_POST['po_number'] ?? '');
  if ($poNumber !== '') {
    $orders = array_values(array_filter($orders, function($row) use ($poNumber) {
      return ($row['po_number'] ?? '') !== $poNumber;
    }));

    $createdAt = trim($_POST['created_at'] ?? '');
    if ($createdAt === '') {
      $createdAt = date('Y-m-d H:i:s');
    }
    $updatedAt = date('Y-m-d H:i:s');
    $itemCount = isset($_POST['item_id']) ? count($_POST['item_id']) : 0;

    for ($i = 0; $i < $itemCount; $i++) {
      $newPO = [];
      foreach ($schema as $f) {
        if (in_array($f, $itemFields, true)) {
          $newPO[$f] = trim($_POST[$f][$i] ?? '');
        } else {
          $newPO[$f] = trim($_POST[$f] ?? '');
        }
      }
      $newPO['po_number'] = $poNumber;
      $newPO['created_at'] = $createdAt;
      $newPO['updated_at'] = $updatedAt;
      $orders[] = $newPO;
    }

    writeCSV($poFile, $orders, $schema);
  }
  header('Location: purchase_orders_list.php');
  exit;
}

$poNumber = trim($_GET['po'] ?? '');
$poRows = array_values(array_filter($orders, function($row) use ($poNumber) {
  return ($row['po_number'] ?? '') === $poNumber;
}));
$header = $poRows[0] ?? [];
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
