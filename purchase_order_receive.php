<?php
require_once 'db_mysql.php';
require_once __DIR__ . '/request_guard.php';
require_once __DIR__ . '/audit_handler.php';

$backorderFile = __DIR__ . '/backorders.csv';

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

function redirect_po_receive(string $url): void {
  if (!headers_sent()) {
    header('Location: ' . $url);
    exit;
  }
  echo '<script>window.location.href=' . json_encode($url) . ';</script>';
  echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
  exit;
}

function upsert_inventory_receipt(mysqli $conn, string $itemId, string $itemName, float $receivedQty, bool $hasBackorder): void {
  $selectStmt = $conn->prepare('SELECT quantity_in_stock, status FROM inventory WHERE item_id = ? LIMIT 1');
  $selectStmt->bind_param('s', $itemId);
  $selectStmt->execute();
  $res = $selectStmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  if ($res instanceof mysqli_result) {
    $res->free();
  }
  $selectStmt->close();

  $now = date('Y-m-d H:i:s');
  $nextStatus = $hasBackorder ? 'Backorder' : 'Stock';

  if ($row) {
    $currentQty = is_numeric($row['quantity_in_stock'] ?? '') ? (float) $row['quantity_in_stock'] : 0.0;
    $newQty = $currentQty + $receivedQty;
    $updateStmt = $conn->prepare('UPDATE inventory SET quantity_in_stock = ?, status = ?, updated_at = ? WHERE item_id = ?');
    $newQtyString = (string) $newQty;
    $updateStmt->bind_param('ssss', $newQtyString, $nextStatus, $now, $itemId);
    if (!$updateStmt->execute()) {
      $error = $updateStmt->error;
      $updateStmt->close();
      throw new RuntimeException('Inventory update failed: ' . $error);
    }
    $updateStmt->close();
    return;
  }

  $initialQty = (string) max(0.0, $receivedQty);
  $insertStmt = $conn->prepare('INSERT INTO inventory (item_id, item_name, quantity_in_stock, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
  $insertStmt->bind_param('ssssss', $itemId, $itemName, $initialQty, $nextStatus, $now, $now);
  if (!$insertStmt->execute()) {
    $error = $insertStmt->error;
    $insertStmt->close();
    throw new RuntimeException('Inventory insert failed: ' . $error);
  }
  $insertStmt->close();
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

  $conn = get_mysql_connection();
  $backorders = read_backorders($backorderFile);
  $receivedTotal = 0.0;
  $backorderedTotal = 0.0;
  try {
    $conn->begin_transaction();

    foreach ($itemIds as $i => $itemIdRaw) {
      $itemId = trim((string) $itemIdRaw);
      $itemName = trim((string) ($itemNames[$i] ?? ''));
      $ordered = is_numeric($orderedQtys[$i] ?? '') ? (float) $orderedQtys[$i] : 0.0;
      $backorder = 0.0;
      if (!$receiveAll) {
        $backorder = is_numeric($backorderQtys[$i] ?? '') ? (float) $backorderQtys[$i] : 0.0;
      }
      $backorder = max(0.0, min($ordered, $backorder));
      $received = max(0.0, $ordered - $backorder);
      $receivedTotal += $received;
      $backorderedTotal += $backorder;

      if ($itemId === '' && $itemName === '') {
        continue;
      }

      if ($itemId !== '') {
        upsert_inventory_receipt($conn, $itemId, $itemName, $received, $backorder > 0);
      }

      if ($backorder > 0) {
        $backorders[] = [
          'po_number' => $poNumber,
          'item_id' => $itemId,
          'item_name' => $itemName,
          'quantity_backorder' => (string) $backorder,
          'note' => $note,
          'created_at' => date('Y-m-d H:i:s')
        ];
      }
    }

    write_backorders($backorderFile, $backorders);
    $statusValue = $backorderedTotal > 0 ? 'partially_received' : 'received';
    $statusUpdatedAt = date('Y-m-d H:i:s');
    $statusStmt = $conn->prepare('UPDATE purchase_orders SET status = ?, updated_at = ? WHERE po_number = ?');
    $statusStmt->bind_param('sss', $statusValue, $statusUpdatedAt, $poNumber);
    if (!$statusStmt->execute()) {
      $error = $statusStmt->error;
      $statusStmt->close();
      throw new RuntimeException('Purchase order status update failed: ' . $error);
    }
    $statusStmt->close();

    $conn->commit();
    $conn->close();
    logAuditAction(
      'update',
      'purchase_order',
      $poNumber,
      [
        'receive_all' => ['old' => null, 'new' => $receiveAll ? 'yes' : 'no'],
        'total_received_qty' => ['old' => null, 'new' => $receivedTotal],
        'total_backorder_qty' => ['old' => null, 'new' => $backorderedTotal],
      ],
      'Purchase order received (inventory updated)',
      'success',
      null
    );
  } catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    logAuditAction(
      'update',
      'purchase_order',
      $poNumber,
      [
        'receive_all' => ['old' => null, 'new' => $receiveAll ? 'yes' : 'no'],
        'total_received_qty' => ['old' => null, 'new' => $receivedTotal],
        'total_backorder_qty' => ['old' => null, 'new' => $backorderedTotal],
      ],
      'Purchase order receive failed',
      'failed',
      $e->getMessage()
    );
    redirect_po_receive('purchase_orders_list.php?error=receive_failed');
  }

  redirect_po_receive('purchase_orders_list.php?success=updated');
}

$poNumber = trim((string) ($_GET['po'] ?? ''));
$poRows = [];
if ($poNumber !== '') {
  $conn = get_mysql_connection();
  $itemsStmt = $conn->prepare('SELECT item_id,item_name,quantity FROM purchase_order_items WHERE po_number = ? ORDER BY id ASC');
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
