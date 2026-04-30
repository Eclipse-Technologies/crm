<?php

include_once(__DIR__ . '/layout_start.php');
require_once 'db_pgsql.php';

$inventorySchema = require __DIR__ . '/inventory_schema.php';
$backorderFile = __DIR__ . '/backorders.csv';

function fetch_inventory_mysql($schema) {
  $conn = get_mysql_connection();
  $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
  $sql = "SELECT $fields FROM inventory";
  $result = $conn->query($sql);
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

function update_inventory_qty_mysql($itemId, $qty) {
  $conn = get_mysql_connection();
  $stmt = $conn->prepare("UPDATE inventory SET quantity_in_stock = COALESCE(quantity_in_stock,0) + ?, updated_at = NOW() WHERE item_id = ?");
  $stmt->bind_param('ds', $qty, $itemId);
  $stmt->execute();
  $stmt->close();
  $conn->close();
}

function update_inventory_status_mysql($itemId, $status) {
  $conn = get_mysql_connection();
  $stmt = $conn->prepare("UPDATE inventory SET status = ?, updated_at = NOW() WHERE item_id = ?");
  $stmt->bind_param('ss', $status, $itemId);
  $stmt->execute();
  $stmt->close();
  $conn->close();
}

$inventory = fetch_inventory_mysql($inventorySchema);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_backorder'])) {
  $itemId = trim($_POST['item_id'] ?? '');
  $poNumber = trim($_POST['po_number'] ?? '');
  $qtyReceive = is_numeric($_POST['receive_qty'] ?? '') ? (float)$_POST['receive_qty'] : 0.0;

  $backorders = read_backorders($backorderFile);
  $updated = [];

  foreach ($backorders as $row) {
    if (($row['item_id'] ?? '') === $itemId && ($row['po_number'] ?? '') === $poNumber) {
      $backorderQty = is_numeric($row['quantity_backorder'] ?? '') ? (float)$row['quantity_backorder'] : 0.0;
      $remaining = max(0.0, $backorderQty - $qtyReceive);

      if ($qtyReceive > 0) {
        update_inventory_qty_pgsql($itemId, $qtyReceive);
      }

      if ($remaining > 0) {
        $row['quantity_backorder'] = (string)$remaining;
        $updated[] = $row;
      }
    } else {
      $updated[] = $row;
    }
  }
  // After updating, write backorders and redirect if needed
  write_backorders($backorderFile, $updated);
  header('Location: backorders_list.php');
  exit;
}
// End PHP processing, begin HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="CRM Backorders: Manage and receive inventory backorders.">
  <title>Backorders</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include_once(__DIR__ . '/layout_start.php'); ?>
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
</body>
</html>
<?php include_once(__DIR__ . '/layout_end.php'); ?>
