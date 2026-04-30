<?php

include_once(__DIR__ . '/layout_start.php');
require_once 'db_pgsql.php';

$inventorySchema = require __DIR__ . '/inventory_schema.php';
$customerSchema = require __DIR__ . '/customer_schema.php';

function fetch_mysql($table, $schema) {
  $conn = get_mysql_connection();
  $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
  $sql = "SELECT $fields FROM $table";
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


$inventory = fetch_mysql('inventory', $inventorySchema);
$customers = fetch_mysql('customers', $customerSchema);

// If you have a ledger table in PostgreSQL, fetch it here:
$ledger = [];
if (function_exists('fetch_pgsql')) {
  // Example: $ledger = fetch_pgsql('inventory_ledger', $ledgerSchema);
}

function read_status_options($filename) {
  if (!file_exists($filename)) {
    return [];
  }
  $options = [];
  if (($handle = fopen($filename, 'r')) !== false) {
    $headers = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
      $value = trim($row[0] ?? '');
      if ($value !== '') {
        $options[] = $value;
      }
    }
    fclose($handle);
  }
  return array_values(array_unique($options));
}

function read_ledger($filename) {
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

function write_ledger_row($filename, $row) {
  $headers = ['item_id','item_name','quantity','status','action','client_id','client_name','serial_number','note','created_at'];
  $fileExists = file_exists($filename);
  $file = fopen($filename, 'a');
  if ($file === false) {
    return;
  }
  if (!$fileExists) {
    fputcsv($file, $headers);
  }
  $line = [];
  foreach ($headers as $header) {
    $line[] = $row[$header] ?? '';
  }
  fputcsv($file, $line);
  fclose($file);
}

function read_serials($filename) {
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

function write_serials($filename, $rows) {
  $headers = ['serial_number','item_id','item_name','status','client_id','client_name','assigned_at','note'];
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

function write_serial_ledger_entry($ledgerFile, $serialNumber, $itemId, $itemName, $status, $clientId, $clientName, $note, $action) {
  $row = [
    'item_id' => $itemId,
    'item_name' => $itemName,
    'quantity' => '1',
    'status' => $status,
    'action' => $action,
    'client_id' => $clientId,
    'client_name' => $clientName,
    'serial_number' => $serialNumber,
    'note' => $note,
    'created_at' => date('Y-m-d H:i:s')
  ];
  write_ledger_row($ledgerFile, $row);
}

function find_item_name($inventory, $itemId) {
  foreach ($inventory as $row) {
    if (($row['item_id'] ?? '') === $itemId) {
      return $row['item_name'] ?? '';
    }
  }
  return '';
}

function build_customer_pairs($customers) {
  $pairs = [];
  foreach ($customers as $row) {
    $id = trim($row['customer_id'] ?? '');
    $name = trim($row['contact_id'] ?? '');
    if ($id === '' && $name === '') {
      continue;
    }
    $pairs[] = [
      'id' => $id,
      'name' => $name !== '' ? $name : $id
    ];
  }
  return $pairs;
}

$customerPairs = build_customer_pairs($customers);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ledger'])) {
  $itemId = trim($_POST['item_id'] ?? '');
  $itemName = trim($_POST['item_name'] ?? '');
  $quantity = trim($_POST['quantity'] ?? '');
  $status = trim($_POST['status'] ?? '');
  $action = trim($_POST['action'] ?? '');
  $clientId = trim($_POST['client_id'] ?? '');
  $clientName = trim($_POST['client_name'] ?? '');
  $serialNumber = trim($_POST['serial_number'] ?? '');
  $note = trim($_POST['note'] ?? '');

  if ($itemId === '' && $itemName === '') {
    $errors[] = 'Item ID or Item Name is required.';
  }
  if ($itemName === '' && $itemId !== '') {
    $itemName = find_item_name($inventory, $itemId);
  }
  if (!is_numeric($quantity)) {
    $errors[] = 'Quantity must be a number.';
  }
  if ($status === '') {
    $errors[] = 'Status is required.';
  }

  if (empty($errors)) {
    $row = [
      'item_id' => $itemId,
      'item_name' => $itemName,
      'quantity' => $quantity,
      'status' => $status,
      'action' => $action,
      'client_id' => $clientId,
      'client_name' => $clientName,
      'serial_number' => $serialNumber,
      'note' => $note,
      'created_at' => date('Y-m-d H:i:s')
    ];
    write_ledger_row($ledgerFile, $row);
    header('Location: inventory_ledger.php');
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_serial'])) {
  $serialNumber = trim($_POST['serial_number'] ?? '');
  $itemId = trim($_POST['serial_item_id'] ?? '');
  $itemName = trim($_POST['serial_item_name'] ?? '');
  $status = trim($_POST['serial_status'] ?? '');
  $clientId = trim($_POST['serial_client_id'] ?? '');
  $clientName = trim($_POST['serial_client_name'] ?? '');
  $note = trim($_POST['serial_note'] ?? '');

  if ($serialNumber === '') {
    $errors[] = 'Serial number is required.';
  }
  if ($itemName === '' && $itemId !== '') {
    $itemName = find_item_name($inventory, $itemId);
  }

  if (empty($errors)) {
    if (($clientId !== '' || $clientName !== '') && $status === '') {
      $status = 'Assigned';
    }
    if ($status === '') {
      $status = 'Stock';
    }
    $serials = read_serials($serialsFile);
    $updated = false;
    $assignedAt = ($clientId !== '' || $clientName !== '') ? date('Y-m-d H:i:s') : '';

    foreach ($serials as &$row) {
      if (($row['serial_number'] ?? '') === $serialNumber) {
        $row['item_id'] = $itemId;
        $row['item_name'] = $itemName;
        $row['status'] = $status;
        $row['client_id'] = $clientId;
        $row['client_name'] = $clientName;
        $row['assigned_at'] = $assignedAt;
        $row['note'] = $note;
        $updated = true;
        break;
      }
    }
    unset($row);

    if (!$updated) {
      $serials[] = [
        'serial_number' => $serialNumber,
        'item_id' => $itemId,
        'item_name' => $itemName,
        'status' => $status,
        'client_id' => $clientId,
        'client_name' => $clientName,
        'assigned_at' => $assignedAt,
        'note' => $note
      ];
    }

    write_serials($serialsFile, $serials);
    $action = ($clientId !== '' || $clientName !== '') ? 'Assign' : 'Move';
    write_serial_ledger_entry($ledgerFile, $serialNumber, $itemId, $itemName, $status, $clientId, $clientName, $note, $action);
    header('Location: inventory_ledger.php');
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_serial'])) {
  $serialNumber = trim($_POST['serial_number'] ?? '');
  $status = trim($_POST['update_status'] ?? '');
  $clientId = trim($_POST['update_client_id'] ?? '');
  $clientName = trim($_POST['update_client_name'] ?? '');
  $note = trim($_POST['update_note'] ?? '');

  if ($serialNumber === '') {
    $errors[] = 'Serial number is required for update.';
  }

  if (empty($errors)) {
    if (($clientId !== '' || $clientName !== '') && $status === '') {
      $status = 'Assigned';
    }
    $serials = read_serials($serialsFile);
    $updated = false;
    $itemId = '';
    $itemName = '';
    $finalStatus = $status;

    foreach ($serials as &$row) {
      if (($row['serial_number'] ?? '') === $serialNumber) {
        $itemId = $row['item_id'] ?? '';
        $itemName = $row['item_name'] ?? '';
        $finalStatus = $status !== '' ? $status : ($row['status'] ?? '');
        $row['status'] = $finalStatus;
        $row['client_id'] = $clientId;
        $row['client_name'] = $clientName;
        $row['assigned_at'] = ($clientId !== '' || $clientName !== '') ? date('Y-m-d H:i:s') : '';
        $row['note'] = $note;
        $updated = true;
        break;
      }
    }
    unset($row);

    if ($updated) {
      write_serials($serialsFile, $serials);
      $action = ($clientId !== '' || $clientName !== '') ? 'Assign' : 'Move';
      write_serial_ledger_entry($ledgerFile, $serialNumber, $itemId, $itemName, $finalStatus, $clientId, $clientName, $note, $action);
    }
    header('Location: inventory_ledger.php');
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_scan'])) {
  $serialNumber = trim($_POST['rfid_serial_number'] ?? '');
  $status = trim($_POST['rfid_status'] ?? '');
  $clientId = trim($_POST['rfid_client_id'] ?? '');
  $clientName = trim($_POST['rfid_client_name'] ?? '');
  $note = trim($_POST['rfid_note'] ?? '');
  $itemId = trim($_POST['rfid_item_id'] ?? '');
  $itemName = trim($_POST['rfid_item_name'] ?? '');

  if ($serialNumber === '') {
    $errors[] = 'RFID scan requires a serial number.';
  }

  if ($itemName === '' && $itemId !== '') {
    $itemName = find_item_name($inventory, $itemId);
  }

  if (empty($errors)) {
    if (($clientId !== '' || $clientName !== '') && $status === '') {
      $status = 'Assigned';
    }
    $serials = read_serials($serialsFile);
    $updated = false;
    $assignedAt = ($clientId !== '' || $clientName !== '') ? date('Y-m-d H:i:s') : '';

    foreach ($serials as &$row) {
      if (($row['serial_number'] ?? '') === $serialNumber) {
        if ($status !== '') {
          $row['status'] = $status;
        }
        if ($clientId !== '' || $clientName !== '') {
          $row['client_id'] = $clientId;
          $row['client_name'] = $clientName;
          $row['assigned_at'] = $assignedAt;
        }
        if ($note !== '') {
          $row['note'] = $note;
        }
        $itemId = $row['item_id'] ?? $itemId;
        $itemName = $row['item_name'] ?? $itemName;
        $status = $row['status'] ?? $status;
        $updated = true;
        break;
      }
    }
    unset($row);

    if (!$updated) {
      if ($itemId === '' && $itemName === '') {
        $errors[] = 'New serial requires item ID or item name.';
      } else {
        $serials[] = [
          'serial_number' => $serialNumber,
          'item_id' => $itemId,
          'item_name' => $itemName,
          'status' => $status !== '' ? $status : 'Stock',
          'client_id' => $clientId,
          'client_name' => $clientName,
          'assigned_at' => $assignedAt,
          'note' => $note
        ];
      }
    }

    if (empty($errors)) {
      write_serials($serialsFile, $serials);
      $action = ($clientId !== '' || $clientName !== '') ? 'Assign' : 'Move';
      $finalStatus = $status !== '' ? $status : 'Stock';
      write_serial_ledger_entry($ledgerFile, $serialNumber, $itemId, $itemName, $finalStatus, $clientId, $clientName, $note, $action);
      header('Location: inventory_ledger.php');
      exit;
    }
  }
}

$ledgerRows = read_ledger($ledgerFile);
$serialRows = read_serials($serialsFile);

$ledgerItemFilter = trim($_GET['ledger_item'] ?? '');
$ledgerStatusFilter = trim($_GET['ledger_status'] ?? '');
$ledgerClientFilter = trim($_GET['ledger_client'] ?? '');
$ledgerSerialFilter = trim($_GET['ledger_serial'] ?? '');

$ledgerFiltered = array_filter($ledgerRows, function($row) use ($ledgerItemFilter, $ledgerStatusFilter, $ledgerClientFilter, $ledgerSerialFilter) {
  $itemOk = $ledgerItemFilter === '' || stripos($row['item_id'] ?? '', $ledgerItemFilter) !== false || stripos($row['item_name'] ?? '', $ledgerItemFilter) !== false;
  $statusOk = $ledgerStatusFilter === '' || stripos($row['status'] ?? '', $ledgerStatusFilter) !== false;
  $clientValue = trim(($row['client_name'] ?? '') . ' ' . ($row['client_id'] ?? ''));
  $clientOk = $ledgerClientFilter === '' || stripos($clientValue, $ledgerClientFilter) !== false;
  $serialOk = $ledgerSerialFilter === '' || stripos($row['serial_number'] ?? '', $ledgerSerialFilter) !== false;
  return $itemOk && $statusOk && $clientOk && $serialOk;
});

$statusOptions = ['Stock', 'Production', 'On The Way', 'Backorder', 'Assigned'];
$statusOptions = array_values(array_unique(array_merge($statusOptions, read_status_options($statusFile))));
foreach ($serialRows as $row) {
  $value = trim($row['status'] ?? '');
  if ($value !== '' && !in_array($value, $statusOptions, true)) {
    $statusOptions[] = $value;
  }
}
sort($statusOptions);
?>
<div class="container">
  <h2>Inventory Ledger</h2>
  <?php if (!empty($errors)): ?>
    <div style="background:#ffecec; border:1px solid #f5baba; color:#8b1b1b; padding:10px 12px; border-radius:6px; margin-bottom:12px;">
      <?php foreach ($errors as $error): ?>
        <div><?= htmlspecialchars($error) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <datalist id="customerNameList">
    <?php foreach ($customerPairs as $customer): ?>
      <option value="<?= htmlspecialchars($customer['name']) ?>"></option>
    <?php endforeach; ?>
  </datalist>
  <datalist id="customerIdList">
    <?php foreach ($customerPairs as $customer): ?>
      <?php if ($customer['id'] !== ''): ?>
        <option value="<?= htmlspecialchars($customer['id']) ?>"></option>
      <?php endif; ?>
    <?php endforeach; ?>
  </datalist>

  <div style="display:grid; grid-template-columns:1fr; gap:18px;">
    <div style="background:#fafbfc; border:1px solid #e6e6e6; border-radius:8px; padding:16px;">
      <div style="font-weight:700; margin-bottom:10px;">RFID Scan</div>
      <form method="post" style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px 16px;">
        <input type="hidden" name="rfid_scan" value="1">
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Serial Number</label>
          <input type="text" name="rfid_serial_number" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Status</label>
          <select name="rfid_status" class="status-select" style="width:100%; padding:6px 8px;">
            <option value="">-- Select --</option>
            <?php foreach ($statusOptions as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Item ID (if new)</label>
          <input type="text" name="rfid_item_id" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Item Name (if new)</label>
          <input type="text" name="rfid_item_name" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Client ID</label>
          <input type="text" name="rfid_client_id" class="client-id" list="customerIdList" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Client Name</label>
          <input type="text" name="rfid_client_name" class="client-name" list="customerNameList" style="width:100%; padding:6px 8px;">
        </div>
        <div style="grid-column:1 / -1;">
          <label style="display:block; font-weight:600; margin-bottom:4px;">Note</label>
          <input type="text" name="rfid_note" style="width:100%; padding:6px 8px;">
        </div>
        <div style="grid-column:1 / -1; text-align:right;">
          <button type="submit" class="btn-primary">Log Scan</button>
        </div>
      </form>
    </div>
    <div style="background:#fafbfc; border:1px solid #e6e6e6; border-radius:8px; padding:16px;">
      <div style="font-weight:700; margin-bottom:10px;">Add Ledger Entry</div>
      <form method="post" style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px 16px;">
        <input type="hidden" name="add_ledger" value="1">
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Item ID</label>
          <select name="item_id" id="ledgerItemId" style="width:100%; padding:6px 8px;">
            <option value="">-- Select --</option>
            <?php foreach ($inventory as $item): ?>
              <option value="<?= htmlspecialchars($item['item_id']) ?>" data-name="<?= htmlspecialchars($item['item_name'] ?? '') ?>">
                <?= htmlspecialchars($item['item_id']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Item Name</label>
          <input type="text" name="item_name" id="ledgerItemName" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Quantity</label>
          <input type="number" name="quantity" step="0.01" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Status</label>
          <select name="status" style="width:100%; padding:6px 8px;">
            <option value="">-- Select --</option>
            <?php foreach ($statusOptions as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Action</label>
          <select name="action" style="width:100%; padding:6px 8px;">
            <option value="Receive">Receive</option>
            <option value="Move">Move</option>
            <option value="Adjust">Adjust</option>
            <option value="Assign">Assign</option>
            <option value="Return">Return</option>
          </select>
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Serial (optional)</label>
          <input type="text" name="serial_number" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Client ID (optional)</label>
          <input type="text" name="client_id" class="client-id" list="customerIdList" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Client Name (optional)</label>
          <input type="text" name="client_name" class="client-name" list="customerNameList" style="width:100%; padding:6px 8px;">
        </div>
        <div style="grid-column:1 / -1;">
          <label style="display:block; font-weight:600; margin-bottom:4px;">Note</label>
          <input type="text" name="note" style="width:100%; padding:6px 8px;">
        </div>
        <div style="grid-column:1 / -1; text-align:right;">
          <button type="submit" class="btn-primary">Add Entry</button>
        </div>
      </form>
    </div>

    <div style="background:#fafbfc; border:1px solid #e6e6e6; border-radius:8px; padding:16px;">
      <div style="font-weight:700; margin-bottom:10px;">Serials and Client Assignment</div>
      <form method="post" style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px 16px;">
        <input type="hidden" name="save_serial" value="1">
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Serial Number</label>
          <input type="text" name="serial_number" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Item ID</label>
          <select name="serial_item_id" id="serialItemId" style="width:100%; padding:6px 8px;">
            <option value="">-- Select --</option>
            <?php foreach ($inventory as $item): ?>
              <option value="<?= htmlspecialchars($item['item_id']) ?>" data-name="<?= htmlspecialchars($item['item_name'] ?? '') ?>">
                <?= htmlspecialchars($item['item_id']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Item Name</label>
          <input type="text" name="serial_item_name" id="serialItemName" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Status</label>
          <select name="serial_status" class="status-select" style="width:100%; padding:6px 8px;">
            <?php foreach ($statusOptions as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Client ID</label>
          <input type="text" name="serial_client_id" class="client-id" list="customerIdList" style="width:100%; padding:6px 8px;">
        </div>
        <div>
          <label style="display:block; font-weight:600; margin-bottom:4px;">Client Name</label>
          <input type="text" name="serial_client_name" class="client-name" list="customerNameList" style="width:100%; padding:6px 8px;">
        </div>
        <div style="grid-column:1 / -1;">
          <label style="display:block; font-weight:600; margin-bottom:4px;">Note</label>
          <input type="text" name="serial_note" style="width:100%; padding:6px 8px;">
        </div>
        <div style="grid-column:1 / -1; text-align:right;">
          <button type="submit" class="btn-primary">Save Serial</button>
        </div>
      </form>
    </div>
  </div>

  <div style="margin-top:22px;">
    <div style="font-weight:700; margin-bottom:10px;">Ledger Entries</div>
    <?php if (empty($ledgerRows)): ?>
      <div style="color:#777;">No ledger entries yet.</div>
    <?php else: ?>
      <div style="overflow:auto; border:1px solid #e6e6e6; border-radius:6px; background:#fff;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="background:#f5f5f5;">
              <th style="padding:8px; text-align:left;">Item ID</th>
              <th style="padding:8px; text-align:left;">Item Name</th>
              <th style="padding:8px; text-align:left;">Qty</th>
              <th style="padding:8px; text-align:left;">Status</th>
              <th style="padding:8px; text-align:left;">Action</th>
              <th style="padding:8px; text-align:left;">Client</th>
              <th style="padding:8px; text-align:left;">Serial</th>
              <th style="padding:8px; text-align:left;">Note</th>
              <th style="padding:8px; text-align:left;">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_reverse($ledgerRows) as $row): ?>
              <tr>
                <td style="padding:8px;"><?= htmlspecialchars($row['item_id'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['item_name'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['quantity'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['status'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['action'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars(trim(($row['client_name'] ?? '') . ' ' . ($row['client_id'] ?? ''))) ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['serial_number'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['note'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['created_at'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div style="margin-top:22px;">
    <div style="font-weight:700; margin-bottom:10px;">Serials</div>
    <?php if (empty($serialRows)): ?>
      <div style="color:#777;">No serialized items yet.</div>
    <?php else: ?>
      <div style="overflow:auto; border:1px solid #e6e6e6; border-radius:6px; background:#fff;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="background:#f5f5f5;">
              <th style="padding:8px; text-align:left;">Serial</th>
              <th style="padding:8px; text-align:left;">Item ID</th>
              <th style="padding:8px; text-align:left;">Item Name</th>
              <th style="padding:8px; text-align:left;">Status</th>
              <th style="padding:8px; text-align:left;">Client</th>
              <th style="padding:8px; text-align:left;">Assigned</th>
              <th style="padding:8px; text-align:left;">Note</th>
              <th style="padding:8px; text-align:left;">Update</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($serialRows as $row): ?>
              <tr>
                <td style="padding:8px;"><?= htmlspecialchars($row['serial_number'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['item_id'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['item_name'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['status'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars(trim(($row['client_name'] ?? '') . ' ' . ($row['client_id'] ?? ''))) ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['assigned_at'] ?? '') ?></td>
                <td style="padding:8px;"><?= htmlspecialchars($row['note'] ?? '') ?></td>
                <td style="padding:8px;">
                  <form method="post" style="display:grid; grid-template-columns:1fr 1fr; gap:6px; align-items:center;">
                    <input type="hidden" name="update_serial" value="1">
                    <input type="hidden" name="serial_number" value="<?= htmlspecialchars($row['serial_number'] ?? '') ?>">
                    <select name="update_status" class="status-select" style="padding:4px 6px;">
                      <option value="">-- Status --</option>
                      <?php foreach ($statusOptions as $status): ?>
                        <?php $selected = ($row['status'] ?? '') === $status ? 'selected' : ''; ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $selected ?>><?= htmlspecialchars($status) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input type="text" name="update_client_id" class="client-id" list="customerIdList" placeholder="Client ID" value="<?= htmlspecialchars($row['client_id'] ?? '') ?>" style="padding:4px 6px;">
                    <input type="text" name="update_client_name" class="client-name" list="customerNameList" placeholder="Client Name" value="<?= htmlspecialchars($row['client_name'] ?? '') ?>" style="padding:4px 6px;">
                    <input type="text" name="update_note" placeholder="Note" value="<?= htmlspecialchars($row['note'] ?? '') ?>" style="padding:4px 6px;">
                    <button type="submit" class="btn-outline" style="grid-column:1 / -1;">Update</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
  const ledgerItemId = document.getElementById('ledgerItemId');
  const ledgerItemName = document.getElementById('ledgerItemName');
  const serialItemId = document.getElementById('serialItemId');
  const serialItemName = document.getElementById('serialItemName');

  function syncName(selectEl, targetEl) {
    if (!selectEl || !targetEl) {
      return;
    }
    const selected = selectEl.options[selectEl.selectedIndex];
    if (selected && selected.dataset && selected.dataset.name !== undefined) {
      targetEl.value = selected.dataset.name;
    }
  }

  if (ledgerItemId && ledgerItemName) {
    ledgerItemId.addEventListener('change', () => syncName(ledgerItemId, ledgerItemName));
  }
  if (serialItemId && serialItemName) {
    serialItemId.addEventListener('change', () => syncName(serialItemId, serialItemName));
  }

  const customerPairs = <?= json_encode($customerPairs) ?>;

  function normalize(value) {
    return (value || '').toLowerCase().trim();
  }

  function findCustomerByName(value) {
    const needle = normalize(value);
    return customerPairs.find(customer => normalize(customer.name) === needle) || null;
  }

  function findCustomerById(value) {
    const needle = normalize(value);
    return customerPairs.find(customer => normalize(customer.id) === needle) || null;
  }

  function autoAssignStatus(form) {
    if (!form) {
      return;
    }
    const statusSelect = form.querySelector('select.status-select');
    const clientId = form.querySelector('input.client-id');
    const clientName = form.querySelector('input.client-name');
    const hasClient = (clientId && clientId.value.trim() !== '') || (clientName && clientName.value.trim() !== '');
    if (statusSelect && statusSelect.value === '' && hasClient) {
      statusSelect.value = 'Assigned';
    }
  }

  document.querySelectorAll('input.client-name').forEach(input => {
    input.addEventListener('input', () => {
      const match = findCustomerByName(input.value);
      const form = input.closest('form');
      if (match && form) {
        const idInput = form.querySelector('input.client-id');
        if (idInput && idInput.value.trim() === '') {
          idInput.value = match.id;
        }
      }
      autoAssignStatus(form);
    });
  });

  document.querySelectorAll('input.client-id').forEach(input => {
    input.addEventListener('input', () => {
      const match = findCustomerById(input.value);
      const form = input.closest('form');
      if (match && form) {
        const nameInput = form.querySelector('input.client-name');
        if (nameInput && nameInput.value.trim() === '') {
          nameInput.value = match.name;
        }
      }
      autoAssignStatus(form);
    });
  });
</script>
<?php include_once(__DIR__ . '/layout_end.php'); ?>
