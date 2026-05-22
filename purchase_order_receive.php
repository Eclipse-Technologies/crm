<?php
include_once(__DIR__ . '/layout_start.php');
require_once 'csv_handler.php';
require_once __DIR__ . '/request_guard.php';

$schema = require __DIR__ . '/purchase_order_schema.php';
$poFile = __DIR__ . '/purchase_orders.csv';
$inventorySchema = require __DIR__ . '/inventory_schema.php';
$inventoryFile = __DIR__ . '/inventory.csv';
$backorderFile = __DIR__ . '/backorders.csv';

$orders = readCSV($poFile, $schema);
$inventory = readCSV($inventoryFile, $inventorySchema);

function read_backorders($filename) {
  if (!file_exists($filename)) {
    return [];
  }
  $rows = [];
  if (($handle = fopen($filename, 'r')) !== false) {
    $headers = fgetcsv($handle);
    if ($headers === false) {
      return [];
    }
    while (($data = fgetcsv($handle)) !== false) {
      if (count($data) !== count($headers)) {
        continue;
      }
      $rows[] = array_combine($headers, $data);
    }
    fclose($handle);
  }
  return $rows;
}

function write_backorders($filename, $rows) {
  $headers = ['po_number','item_id','item_name','quantity_backorder','note','created_at'];
  $file = fopen($filename, 'w');
  if ($file === false) {
    return;
  }
  fputcsv($file, $headers);
  foreach ($rows as $row) {
    $line = [];
    foreach ($headers as $header) {
      $line[] = $row[$header] ?? '';
    }
    fputcsv($file, $line);
  }
  fclose($file);
}

function find_inventory_index($inventory, $itemId) {
  foreach ($inventory as $idx => $row) {
    if (($row['item_id'] ?? '') === $itemId) {
      return $idx;
    }
  }
  return -1;
}

function init_inventory_row($schema, $itemId, $itemName) {
  $row = [];
  foreach ($schema as $field) {
    $row[$field] = '';
  }
  $row['item_id'] = $itemId;
  $row['item_name'] = $itemName;
  $row['quantity_in_stock'] = '0';
  $row['status'] = 'Stock';
  $row['created_at'] = date('Y-m-d H:i:s');
  $row['updated_at'] = $row['created_at'];
  return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_po'])) {
  require_post_with_csrf();
  $poNumber = trim($_POST['po_number'] ?? '');
  $receiveAll = ($_POST['receive_all'] ?? '') === 'yes';
  $note = trim($_POST['backorder_note'] ?? '');

  $itemIds = $_POST['item_id'] ?? [];
  $itemNames = $_POST['item_name'] ?? [];
  $orderedQtys = $_POST['ordered_qty'] ?? [];
  $backorderQtys = $_POST['backorder_qty'] ?? [];

  $backorders = read_backorders($backorderFile);

  foreach ($itemIds as $i => $itemId) {
    $itemId = trim($itemId);
    $itemName = trim($itemNames[$i] ?? '');
    $ordered = is_numeric($orderedQtys[$i] ?? '') ? (float)$orderedQtys[$i] : 0.0;
    $backorder = 0.0;
    if (!$receiveAll) {
      $backorder = is_numeric($backorderQtys[$i] ?? '') ? (float)$backorderQtys[$i] : 0.0;
    }
    $backorder = max(0.0, min($ordered, $backorder));
    $received = max(0.0, $ordered - $backorder);

    if ($itemId === '' && $itemName === '') {
      continue;
    }

    if ($itemId !== '') {
      $invIndex = find_inventory_index($inventory, $itemId);
      if ($invIndex < 0) {
        $inventory[] = init_inventory_row($inventorySchema, $itemId, $itemName);
        $invIndex = count($inventory) - 1;
      }
      if ($received > 0) {
        $current = is_numeric($inventory[$invIndex]['quantity_in_stock'] ?? '') ? (float)$inventory[$invIndex]['quantity_in_stock'] : 0.0;
        $inventory[$invIndex]['quantity_in_stock'] = (string)($current + $received);
      }
      if ($backorder > 0) {
        $inventory[$invIndex]['status'] = 'Backorder';
      } elseif (($inventory[$invIndex]['status'] ?? '') === 'Backorder') {
        $inventory[$invIndex]['status'] = 'Stock';
      }
      $inventory[$invIndex]['updated_at'] = date('Y-m-d H:i:s');
    }

    if ($backorder > 0) {
      $backorders[] = [
        'po_number' => $poNumber,
        'item_id' => $itemId,
        'item_name' => $itemName,
        'quantity_backorder' => (string)$backorder,
        'note' => $note,
        'created_at' => date('Y-m-d H:i:s')
      ];
    }
  }

  writeCSV($inventoryFile, $inventory, $inventorySchema);
  write_backorders($backorderFile, $backorders);

  header('Location: purchase_orders_list.php');
  exit;
}

$poNumber = trim($_GET['po'] ?? '');
$poRows = array_values(array_filter($orders, function($row) use ($poNumber) {
  return ($row['po_number'] ?? '') === $poNumber;
}));
?>
<div class="container">
  <h2>Receive Purchase Order</h2>
  <?php if ($poNumber === '' || empty($poRows)): ?>
    <div style="text-align:center; color:#888; margin:18px 0;">Purchase order not found.</div>
    <div style="text-align:center;">
      <a href="purchase_orders_list.php" class="btn-outline">Back to Purchase Orders</a>
    </div>
  <?php else: ?>
    <form method="post" style="max-width:900px; margin:auto; background:#fafbfc; border-radius:8px; padding:24px 28px 18px 28px; box-shadow:0 2px 8px #0001;">
      <?php renderCSRFInput(); ?>
      <input type="hidden" name="receive_po" value="1">
      <input type="hidden" name="po_number" value="<?= htmlspecialchars($poNumber) ?>">
      <div style="display:flex; flex-wrap:wrap; gap:12px 24px; margin-bottom:16px; align-items:center;">
        <div style="font-weight:600;">PO Number:</div>
        <div><?= htmlspecialchars($poNumber) ?></div>
      </div>
      <div style="display:flex; gap:14px; align-items:center; margin-bottom:12px;">
        <div style="font-weight:600;">All items received?</div>
        <label><input type="radio" name="receive_all" value="yes" checked> Yes</label>
        <label><input type="radio" name="receive_all" value="no"> No</label>
      </div>
      <div id="backorderSection" style="display:none;">
        <div style="margin-bottom:10px; font-weight:600;">Backorder Details</div>
        <div style="margin-bottom:10px;">
          <label for="backorder_note" style="display:block; font-weight:600; margin-bottom:4px;">Backorder Note</label>
          <textarea name="backorder_note" id="backorder_note" class="inv-input" style="width:100%; min-height:70px; resize:vertical;"></textarea>
        </div>
      </div>
      <table style="width:100%; border-collapse:collapse; margin-bottom:12px; background:#fff;">
        <thead>
          <tr style="background:#f5f5f5; font-size:0.97em;">
            <th style="padding:6px 4px;">Item ID</th>
            <th style="padding:6px 4px;">Item Name</th>
            <th style="padding:6px 4px;">Ordered Qty</th>
            <th style="padding:6px 4px;">Backorder Qty</th>
            <th style="padding:6px 4px;">Received Qty</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($poRows as $idx => $row): ?>
            <tr>
              <td style="padding:6px 4px;">
                <?= htmlspecialchars($row['item_id'] ?? '') ?>
                <input type="hidden" name="item_id[]" value="<?= htmlspecialchars($row['item_id'] ?? '') ?>">
              </td>
              <td style="padding:6px 4px;">
                <?= htmlspecialchars($row['item_name'] ?? '') ?>
                <input type="hidden" name="item_name[]" value="<?= htmlspecialchars($row['item_name'] ?? '') ?>">
              </td>
              <td style="padding:6px 4px;">
                <?= htmlspecialchars($row['quantity'] ?? '') ?>
                <input type="hidden" name="ordered_qty[]" value="<?= htmlspecialchars($row['quantity'] ?? '') ?>">
              </td>
              <td style="padding:6px 4px;">
                <input type="number" name="backorder_qty[]" min="0" step="0.01" style="width:110px; padding:3px 5px; font-size:0.97em;" value="0" data-ordered="<?= htmlspecialchars($row['quantity'] ?? '') ?>">
              </td>
              <td style="padding:6px 4px;">
                <span class="received-qty" data-ordered="<?= htmlspecialchars($row['quantity'] ?? '') ?>"><?= htmlspecialchars($row['quantity'] ?? '') ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="margin-top:18px; text-align:center;">
        <button type="submit" class="btn-primary" style="padding:7px 22px; font-size:1.08em;">Receive Items</button>
        <a href="purchase_orders_list.php" class="btn-outline" style="margin-left:18px;">Cancel</a>
      </div>
    </form>
    <script>
      const receiveRadios = Array.from(document.querySelectorAll('input[name="receive_all"]'));
      const backorderSection = document.getElementById('backorderSection');
      const backorderInputs = Array.from(document.querySelectorAll('input[name="backorder_qty[]"]'));
      const receivedLabels = Array.from(document.querySelectorAll('.received-qty'));

      function updateReceived() {
        backorderInputs.forEach((input, index) => {
          const ordered = parseFloat(input.getAttribute('data-ordered'));
          const backorder = parseFloat(input.value);
          const orderedVal = Number.isFinite(ordered) ? ordered : 0;
          const backorderVal = Number.isFinite(backorder) ? backorder : 0;
          const received = Math.max(0, orderedVal - Math.min(orderedVal, backorderVal));
          if (receivedLabels[index]) {
            receivedLabels[index].textContent = received.toFixed(2).replace(/\.00$/, '');
          }
        });
      }

      function toggleBackorderSection() {
        const receiveAll = document.querySelector('input[name="receive_all"]:checked').value === 'yes';
        backorderSection.style.display = receiveAll ? 'none' : 'block';
        backorderInputs.forEach(input => {
          input.disabled = receiveAll;
          if (receiveAll) {
            input.value = '0';
          }
        });
        updateReceived();
      }

      receiveRadios.forEach(radio => {
        radio.addEventListener('change', toggleBackorderSection);
      });

      backorderInputs.forEach(input => {
        input.addEventListener('input', updateReceived);
      });

      toggleBackorderSection();
    </script>
  <?php endif; ?>
</div>
<?php include_once(__DIR__ . '/layout_end.php'); ?>
