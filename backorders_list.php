<?php
require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/request_guard.php';
require_once __DIR__ . '/audit_handler.php';
require_once __DIR__ . '/backorders_mysql.php';

function redirect_backorders(string $url): void {
  if (!headers_sent()) {
    header('Location: ' . $url);
    exit;
  }
  echo '<script>window.location.href=' . json_encode($url) . ';</script>';
  echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
  exit;
}

function apply_backorder_receipt_to_inventory(mysqli $conn, string $itemId, string $itemName, float $receiveQty, float $remainingQty): void {
  $selectStmt = $conn->prepare('SELECT quantity_in_stock FROM inventory WHERE item_id = ? LIMIT 1');
  $selectStmt->bind_param('s', $itemId);
  $selectStmt->execute();
  $selectRes = $selectStmt->get_result();
  $invRow = $selectRes ? $selectRes->fetch_assoc() : null;
  if ($selectRes instanceof mysqli_result) {
    $selectRes->free();
  }
  $selectStmt->close();

  $targetStatus = $remainingQty > 0 ? 'Backorder' : 'Stock';
  $now = date('Y-m-d H:i:s');

  if ($invRow) {
    $currentQty = is_numeric($invRow['quantity_in_stock'] ?? '') ? (float) $invRow['quantity_in_stock'] : 0.0;
    $newQtyString = (string) ($currentQty + $receiveQty);
    $updateStmt = $conn->prepare('UPDATE inventory SET quantity_in_stock = ?, status = ?, updated_at = ? WHERE item_id = ?');
    $updateStmt->bind_param('ssss', $newQtyString, $targetStatus, $now, $itemId);
    if (!$updateStmt->execute()) {
      $error = $updateStmt->error;
      $updateStmt->close();
      throw new RuntimeException('Inventory update failed: ' . $error);
    }
    $updateStmt->close();
    return;
  }

  $receivedQtyString = (string) $receiveQty;
  $insertStmt = $conn->prepare('INSERT INTO inventory (item_id, item_name, quantity_in_stock, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
  $insertStmt->bind_param('ssssss', $itemId, $itemName, $receivedQtyString, $targetStatus, $now, $now);
  if (!$insertStmt->execute()) {
    $error = $insertStmt->error;
    $insertStmt->close();
    throw new RuntimeException('Inventory insert failed: ' . $error);
  }
  $insertStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_backorder'])) {
  require_post_with_csrf();

  $itemId = trim((string) ($_POST['item_id'] ?? ''));
  $poNumber = trim((string) ($_POST['po_number'] ?? ''));
  $qtyReceive = is_numeric($_POST['receive_qty'] ?? '') ? (float) $_POST['receive_qty'] : 0.0;

  if ($itemId === '' || $poNumber === '' || $qtyReceive <= 0.0) {
    logAuditAction('update', 'backorder', ($poNumber !== '' ? $poNumber . ':' . $itemId : ''), [], 'Backorder receive failed: invalid input', 'failed', 'Missing item_id/po_number or non-positive receive_qty');
    redirect_backorders('backorders_list.php?error=invalid_request');
  }

  $conn = get_mysql_connection();
  try {
    $conn->begin_transaction();
    $receipt = reduce_backorder_mysql($conn, $poNumber, $itemId, $qtyReceive);

    $appliedQty = (float) ($receipt['applied_qty'] ?? 0.0);
    $remainingQty = (float) ($receipt['remaining_qty'] ?? 0.0);
    $itemName = (string) ($receipt['item_name'] ?? '');

    if ($appliedQty <= 0.0) {
      throw new RuntimeException('No quantity applied to receive');
    }

    apply_backorder_receipt_to_inventory($conn, $itemId, $itemName, $appliedQty, $remainingQty);

    $conn->commit();
    $conn->close();

    logAuditAction(
      'update',
      'backorder',
      $poNumber . ':' . $itemId,
      [
        'quantity_backorder' => ['old' => (float) ($receipt['previous_qty'] ?? 0.0), 'new' => $remainingQty],
        'received_qty' => ['old' => 0.0, 'new' => $appliedQty],
      ],
      'Backorder received into inventory',
      'success',
      null
    );

    redirect_backorders('backorders_list.php?success=updated');
  } catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    logAuditAction('update', 'backorder', $poNumber . ':' . $itemId, [], 'Backorder receive failed', 'failed', $e->getMessage());
    redirect_backorders('backorders_list.php?error=invalid_request');
  }
}

$conn = get_mysql_connection();
$backorders = fetch_backorders_mysql($conn);
$conn->close();

include_once(__DIR__ . '/layout_start.php');
?>
<div class="container">
  <h2>Backorders</h2>
  <?php if (empty($backorders)): ?>
    <div style="text-align:center; color:#888; margin-top:18px;">No backorders found.</div>
  <?php else: ?>
    <table style="width:100%; border-collapse:collapse; background:#fff;">
      <thead>
        <tr style="background:#f5f5f5;">
          <th style="padding:8px; text-align:left;">PO</th>
          <th style="padding:8px; text-align:left;">Item ID</th>
          <th style="padding:8px; text-align:left;">Item Name</th>
          <th style="padding:8px; text-align:left;">Backorder Qty</th>
          <th style="padding:8px; text-align:left;">Note</th>
          <th style="padding:8px; text-align:left;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($backorders as $row): ?>
          <tr>
            <td style="padding:8px;"><?= htmlspecialchars($row['po_number'] ?? '') ?></td>
            <td style="padding:8px;"><?= htmlspecialchars($row['item_id'] ?? '') ?></td>
            <td style="padding:8px;"><?= htmlspecialchars($row['item_name'] ?? '') ?></td>
            <td style="padding:8px;"><?= htmlspecialchars($row['quantity_backorder'] ?? '') ?></td>
            <td style="padding:8px;"><?= htmlspecialchars($row['note'] ?? '') ?></td>
            <td style="padding:8px;">
              <form method="post" style="display:flex; gap:6px; align-items:center;">
                <?php renderCSRFInput(); ?>
                <input type="hidden" name="receive_backorder" value="1">
                <input type="hidden" name="po_number" value="<?= htmlspecialchars($row['po_number'] ?? '') ?>">
                <input type="hidden" name="item_id" value="<?= htmlspecialchars($row['item_id'] ?? '') ?>">
                <input type="number" name="receive_qty" min="0" step="0.01" value="<?= htmlspecialchars($row['quantity_backorder'] ?? '') ?>" style="width:100px; padding:3px 5px;">
                <button type="submit" class="btn-outline">Receive</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php include_once(__DIR__ . '/layout_end.php'); ?>
