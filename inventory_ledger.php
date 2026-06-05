<?php

require_once 'db_mysql.php';
require_once __DIR__ . '/request_guard.php';

$inventorySchema = require __DIR__ . '/inventory_schema.php';
$customerSchema = require __DIR__ . '/customer_schema.php';

// Legacy parameter placeholders retained so existing call sites remain stable.
$ledgerFile = null;
$serialsFile = null;
$statusFile = null;

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

function ensure_inventory_ledger_entries_table(mysqli $conn): void {
  $conn->query("CREATE TABLE IF NOT EXISTS inventory_ledger_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(100) NULL,
    item_name VARCHAR(255) NULL,
    quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
    status VARCHAR(100) NULL,
    action VARCHAR(100) NULL,
    client_id VARCHAR(100) NULL,
    client_name VARCHAR(255) NULL,
    serial_number VARCHAR(255) NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item_time (item_id, created_at),
    INDEX idx_serial_time (serial_number, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $columns = [
    'item_id' => 'VARCHAR(100) NULL',
    'item_name' => 'VARCHAR(255) NULL',
    'quantity' => 'DECIMAL(18,4) NOT NULL DEFAULT 0',
    'status' => 'VARCHAR(100) NULL',
    'action' => 'VARCHAR(100) NULL',
    'client_id' => 'VARCHAR(100) NULL',
    'client_name' => 'VARCHAR(255) NULL',
    'serial_number' => 'VARCHAR(255) NULL',
    'note' => 'VARCHAR(500) NULL',
    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
  ];
  foreach ($columns as $name => $def) {
    $safeName = $conn->real_escape_string($name);
    $res = $conn->query("SHOW COLUMNS FROM inventory_ledger_entries LIKE '" . $safeName . "'");
    $exists = $res && $res->num_rows > 0;
    if ($res) {
      $res->free();
    }
    if (!$exists) {
      $conn->query("ALTER TABLE inventory_ledger_entries ADD COLUMN `" . $name . "` " . $def);
    }
  }
}

function ensure_inventory_serials_table(mysqli $conn): void {
  $conn->query("CREATE TABLE IF NOT EXISTS inventory_serials (
    serial_number VARCHAR(255) PRIMARY KEY,
    item_id VARCHAR(100) NULL,
    item_name VARCHAR(255) NULL,
    status VARCHAR(100) NULL,
    client_id VARCHAR(100) NULL,
    client_name VARCHAR(255) NULL,
    assigned_at DATETIME NULL,
    note VARCHAR(500) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item (item_id),
    INDEX idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $columns = [
    'item_id' => 'VARCHAR(100) NULL',
    'item_name' => 'VARCHAR(255) NULL',
    'status' => 'VARCHAR(100) NULL',
    'client_id' => 'VARCHAR(100) NULL',
    'client_name' => 'VARCHAR(255) NULL',
    'assigned_at' => 'DATETIME NULL',
    'note' => 'VARCHAR(500) NULL',
    'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
  ];
  foreach ($columns as $name => $def) {
    $safeName = $conn->real_escape_string($name);
    $res = $conn->query("SHOW COLUMNS FROM inventory_serials LIKE '" . $safeName . "'");
    $exists = $res && $res->num_rows > 0;
    if ($res) {
      $res->free();
    }
    if (!$exists) {
      $conn->query("ALTER TABLE inventory_serials ADD COLUMN `" . $name . "` " . $def);
    }
  }
}


$inventory = fetch_mysql('inventory', $inventorySchema);
$customers = fetch_mysql('customers', $customerSchema);

$connInit = get_mysql_connection();
ensure_inventory_ledger_entries_table($connInit);
ensure_inventory_serials_table($connInit);
$connInit->close();

// If you have a ledger table in PostgreSQL, fetch it here:
$ledger = [];
if (function_exists('fetch_pgsql')) {
  // Example: $ledger = fetch_pgsql('inventory_ledger', $ledgerSchema);
}

function read_status_options($unused = null) {
  $conn = get_mysql_connection();
  ensure_inventory_ledger_entries_table($conn);
  ensure_inventory_serials_table($conn);

  $options = [];
  $resLedger = $conn->query('SELECT DISTINCT status FROM inventory_ledger_entries WHERE status IS NOT NULL AND status <> ""');
  while ($row = $resLedger ? $resLedger->fetch_assoc() : null) {
    $options[] = trim((string) ($row['status'] ?? ''));
  }
  if ($resLedger) {
    $resLedger->free();
  }

  $resSerial = $conn->query('SELECT DISTINCT status FROM inventory_serials WHERE status IS NOT NULL AND status <> ""');
  while ($row = $resSerial ? $resSerial->fetch_assoc() : null) {
    $options[] = trim((string) ($row['status'] ?? ''));
  }
  if ($resSerial) {
    $resSerial->free();
  }
  $conn->close();

  $options = array_values(array_filter(array_unique($options), static function ($v) {
    return $v !== '';
  }));
  sort($options);
  return $options;
}

function read_ledger($unused = null) {
  $conn = get_mysql_connection();
  ensure_inventory_ledger_entries_table($conn);
  $rows = [];
  $sql = 'SELECT * FROM inventory_ledger_entries ORDER BY id ASC';
  $result = $conn->query($sql);
  while ($row = $result ? $result->fetch_assoc() : null) {
    $rows[] = [
      'item_id' => $row['item_id'] ?? '',
      'item_name' => $row['item_name'] ?? '',
      'quantity' => $row['quantity'] ?? '',
      'status' => $row['status'] ?? '',
      'action' => $row['action'] ?? '',
      'client_id' => $row['client_id'] ?? '',
      'client_name' => $row['client_name'] ?? '',
      'serial_number' => $row['serial_number'] ?? '',
      'note' => $row['note'] ?? '',
      'created_at' => $row['created_at'] ?? '',
      'source' => 'manual',
    ];
  }
  if ($result) {
    $result->free();
  }
  $conn->close();
  return $rows;
}

function read_inventory_transaction_ledger_rows(array $inventoryById): array {
  $conn = get_mysql_connection();
  $rows = [];
  $sql = "SELECT t.item_id, t.quantity_delta, t.transaction_type, t.validation_status, t.source_ref, t.reason_text, t.recorded_at
          FROM inventory_transactions t
          ORDER BY t.transaction_id DESC
          LIMIT 500";
  $result = $conn->query($sql);
  while ($row = $result ? $result->fetch_assoc() : null) {
    $itemId = (string) ($row['item_id'] ?? '');
    $rows[] = [
      'item_id' => $itemId,
      'item_name' => $inventoryById[$itemId]['item_name'] ?? '',
      'quantity' => $row['quantity_delta'] ?? '',
      'status' => $row['validation_status'] ?? '',
      'action' => $row['transaction_type'] ?? '',
      'client_id' => '',
      'client_name' => '',
      'serial_number' => $row['source_ref'] ?? '',
      'note' => $row['reason_text'] ?? '',
      'created_at' => $row['recorded_at'] ?? '',
      'source' => 'transaction',
    ];
  }
  if ($result) {
    $result->free();
  }
  $conn->close();
  return $rows;
}

function write_ledger_row($unused, $row) {
  $conn = get_mysql_connection();
  ensure_inventory_ledger_entries_table($conn);

  $itemId = trim((string) ($row['item_id'] ?? ''));
  $itemName = trim((string) ($row['item_name'] ?? ''));
  $quantity = is_numeric($row['quantity'] ?? null) ? (float) $row['quantity'] : 0.0;
  $status = trim((string) ($row['status'] ?? ''));
  $action = trim((string) ($row['action'] ?? ''));
  $clientId = trim((string) ($row['client_id'] ?? ''));
  $clientName = trim((string) ($row['client_name'] ?? ''));
  $serialNumber = trim((string) ($row['serial_number'] ?? ''));
  $note = trim((string) ($row['note'] ?? ''));

  $stmt = $conn->prepare('INSERT INTO inventory_ledger_entries (item_id, item_name, quantity, status, action, client_id, client_name, serial_number, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
  if ($stmt) {
    $stmt->bind_param('ssdssssss', $itemId, $itemName, $quantity, $status, $action, $clientId, $clientName, $serialNumber, $note);
    $stmt->execute();
    $stmt->close();
  }
  $conn->close();
}

function read_serials($unused = null) {
  $conn = get_mysql_connection();
  ensure_inventory_serials_table($conn);
  $rows = [];
  $result = $conn->query('SELECT * FROM inventory_serials ORDER BY serial_number ASC');
  while ($row = $result ? $result->fetch_assoc() : null) {
    $rows[] = [
      'serial_number' => $row['serial_number'] ?? '',
      'item_id' => $row['item_id'] ?? '',
      'item_name' => $row['item_name'] ?? '',
      'status' => $row['status'] ?? '',
      'client_id' => $row['client_id'] ?? '',
      'client_name' => $row['client_name'] ?? '',
      'assigned_at' => $row['assigned_at'] ?? '',
      'note' => $row['note'] ?? '',
    ];
  }
  if ($result) {
    $result->free();
  }
  $conn->close();
  return $rows;
}

function write_serials($unused, $rows) {
  $conn = get_mysql_connection();
  ensure_inventory_serials_table($conn);
  $conn->begin_transaction();
  try {
    $conn->query('DELETE FROM inventory_serials');

    $stmt = $conn->prepare('INSERT INTO inventory_serials (serial_number, item_id, item_name, status, client_id, client_name, assigned_at, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if ($stmt) {
      foreach ($rows as $row) {
        $serialNumber = trim((string) ($row['serial_number'] ?? ''));
        if ($serialNumber === '') {
          continue;
        }
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $itemName = trim((string) ($row['item_name'] ?? ''));
        $status = trim((string) ($row['status'] ?? ''));
        $clientId = trim((string) ($row['client_id'] ?? ''));
        $clientName = trim((string) ($row['client_name'] ?? ''));
        $assignedAt = trim((string) ($row['assigned_at'] ?? ''));
        $assignedAt = $assignedAt === '' ? null : $assignedAt;
        $note = trim((string) ($row['note'] ?? ''));
        $stmt->bind_param('ssssssss', $serialNumber, $itemId, $itemName, $status, $clientId, $clientName, $assignedAt, $note);
        $stmt->execute();
      }
      $stmt->close();
    }

    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
  }
  $conn->close();
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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  require_post_with_csrf();
}

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

$inventoryById = [];
foreach ($inventory as $invRow) {
  $id = trim((string) ($invRow['item_id'] ?? ''));
  if ($id !== '') {
    $inventoryById[$id] = $invRow;
  }
}

$ledgerRows = array_merge(read_ledger($ledgerFile), read_inventory_transaction_ledger_rows($inventoryById));
$ledgerRows = array_values($ledgerRows);
usort($ledgerRows, static function($a, $b) {
  $at = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
  $bt = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
  return $bt <=> $at;
});
$serialRows = read_serials($serialsFile);

$ledgerItemFilter = trim($_GET['ledger_item'] ?? '');
$ledgerStatusFilter = trim($_GET['ledger_status'] ?? '');
$ledgerClientFilter = trim($_GET['ledger_client'] ?? '');
$ledgerSerialFilter = trim($_GET['ledger_serial'] ?? '');
$ledgerSourceFilter = trim($_GET['ledger_source'] ?? '');
$ledgerFromFilter = trim($_GET['ledger_from'] ?? '');
$ledgerToFilter = trim($_GET['ledger_to'] ?? '');

$ledgerFiltered = array_filter($ledgerRows, function($row) use ($ledgerItemFilter, $ledgerStatusFilter, $ledgerClientFilter, $ledgerSerialFilter, $ledgerSourceFilter, $ledgerFromFilter, $ledgerToFilter) {
  $itemOk = $ledgerItemFilter === '' || stripos($row['item_id'] ?? '', $ledgerItemFilter) !== false || stripos($row['item_name'] ?? '', $ledgerItemFilter) !== false;
  $statusOk = $ledgerStatusFilter === '' || stripos($row['status'] ?? '', $ledgerStatusFilter) !== false;
  $clientValue = trim(($row['client_name'] ?? '') . ' ' . ($row['client_id'] ?? ''));
  $clientOk = $ledgerClientFilter === '' || stripos($clientValue, $ledgerClientFilter) !== false;
  $serialOk = $ledgerSerialFilter === '' || stripos($row['serial_number'] ?? '', $ledgerSerialFilter) !== false;
  $source = (string) ($row['source'] ?? 'manual');
  $sourceOk = $ledgerSourceFilter === '' || $source === $ledgerSourceFilter;

  $createdTs = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
  $fromTs = $ledgerFromFilter !== '' ? (strtotime($ledgerFromFilter . ' 00:00:00') ?: 0) : 0;
  $toTs = $ledgerToFilter !== '' ? (strtotime($ledgerToFilter . ' 23:59:59') ?: 0) : 0;
  $fromOk = $fromTs === 0 || ($createdTs !== 0 && $createdTs >= $fromTs);
  $toOk = $toTs === 0 || ($createdTs !== 0 && $createdTs <= $toTs);

  return $itemOk && $statusOk && $clientOk && $serialOk && $sourceOk && $fromOk && $toOk;
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

include_once(__DIR__ . '/layout_start.php');
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
        <?php renderCSRFInput(); ?>
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
        <?php renderCSRFInput(); ?>
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
        <?php renderCSRFInput(); ?>
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
    <form method="get" style="display:grid; grid-template-columns:repeat(4,minmax(160px,1fr)); gap:8px 10px; margin-bottom:10px; background:#fafbfc; border:1px solid #e6e6e6; border-radius:8px; padding:10px;">
      <input type="text" name="ledger_item" placeholder="Item ID or name" value="<?= htmlspecialchars($ledgerItemFilter) ?>" style="padding:6px 8px;">
      <input type="text" name="ledger_status" placeholder="Status" value="<?= htmlspecialchars($ledgerStatusFilter) ?>" style="padding:6px 8px;">
      <input type="text" name="ledger_client" placeholder="Client" value="<?= htmlspecialchars($ledgerClientFilter) ?>" style="padding:6px 8px;">
      <input type="text" name="ledger_serial" placeholder="Serial/Ref" value="<?= htmlspecialchars($ledgerSerialFilter) ?>" style="padding:6px 8px;">
      <select name="ledger_source" style="padding:6px 8px;">
        <option value="" <?= $ledgerSourceFilter === '' ? 'selected' : '' ?>>All Sources</option>
        <option value="manual" <?= $ledgerSourceFilter === 'manual' ? 'selected' : '' ?>>Manual</option>
        <option value="transaction" <?= $ledgerSourceFilter === 'transaction' ? 'selected' : '' ?>>Transaction</option>
      </select>
      <input type="date" name="ledger_from" value="<?= htmlspecialchars($ledgerFromFilter) ?>" style="padding:6px 8px;">
      <input type="date" name="ledger_to" value="<?= htmlspecialchars($ledgerToFilter) ?>" style="padding:6px 8px;">
      <div style="display:flex; gap:8px; align-items:center;">
        <button type="submit" class="btn-outline" style="padding:6px 10px;">Filter</button>
        <a href="inventory_ledger.php" class="btn-outline" style="padding:6px 10px; text-decoration:none;">Reset</a>
      </div>
    </form>
    <?php if (empty($ledgerFiltered)): ?>
      <div style="color:#777;">No ledger entries yet.</div>
    <?php else: ?>
      <div style="overflow:auto; border:1px solid #e6e6e6; border-radius:6px; background:#fff;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="background:#f5f5f5;">
              <th style="padding:8px; text-align:left;">Source</th>
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
            <?php foreach ($ledgerFiltered as $row): ?>
              <tr>
                <td style="padding:8px;"><?= htmlspecialchars(($row['source'] ?? 'manual') === 'transaction' ? 'Transaction' : 'Manual') ?></td>
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
                    <?php renderCSRFInput(); ?>
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
