<?php
include_once(__DIR__ . '/layout_start.php');
require_once 'db_mysql.php';
require_once 'csrf_helper.php';

$schema     = require __DIR__ . '/customer_schema.php';
$itemFields = require __DIR__ . '/customer_item_config.php';
$dateFields = ['last_service_date', 'install_date', 'purchase_date', 'next_service_date', 'warranty_expiry'];

$errors  = [];
$success = false;

// ── Generate a unique customer_id ────────────────────────────────────────────
$conn   = get_mysql_connection();
$row    = $conn->query('SELECT MAX(customer_id) AS max_id FROM customers')->fetch_assoc();
$nextId = str_pad((int)($row['max_id'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);

while ($conn->query("SELECT customer_id FROM customers WHERE customer_id = '{$conn->real_escape_string($nextId)}'")->num_rows > 0) {
  $nextId = str_pad((int)$nextId + 1, 5, '0', STR_PAD_LEFT);
}
$conn->close();

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $errors[] = 'CSRF validation failed';
  } else {
  $contactId = trim($_POST['contact_id'] ?? $_GET['contact_id'] ?? '');

  if ($contactId === '') {
    $errors[] = 'You must select a contact before creating a customer.';
  }

  // Collect and validate customer fields
  $customerData = ['customer_id' => $nextId, 'contact_id' => $contactId];
  foreach ($schema as $field) {
    if (in_array($field, ['customer_id', 'contact_id'])) continue;
    $customerData[$field] = trim($_POST[$field] ?? '');
  }

  // Collect equipment rows
  $items = [];
  foreach ($_POST['items'] ?? [] as $item) {
    $clean = [];
    foreach ($itemFields as $f) {
      $clean[$f] = trim($item[$f] ?? '');
    }
    $items[] = $clean;
  }

  if (empty($errors)) {
    $db = get_mysql_connection();

    // Build customer INSERT
    $fields       = implode(', ', $schema);
    $placeholders = implode(', ', array_fill(0, count($schema), '?'));
    $types        = '';
    $params       = [];

    foreach ($schema as $field) {
      $val = $customerData[$field] ?? null;
      if ($field === 'contact_id') {
        $types   .= 'i';
        $params[] = ($val === '' || $val === null || !is_numeric($val)) ? null : (int)$val;
      } elseif ($field === 'last_delivery') {
        $types   .= 's';
        $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$val) ? $val : null;
      } elseif ($field === 'last_modified') {
        $types   .= 's';
        $params[] = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$val) ? $val : null;
      } else {
        $types   .= 's';
        $params[] = $val === '' ? null : $val;
      }
    }

    $stmt = $db->prepare("INSERT INTO customers ($fields) VALUES ($placeholders)");
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
      $errors[] = 'Failed to save customer: ' . $stmt->error;
    } else {
      // Insert each equipment row
      foreach ($items as $item) {
        $eqId     = 'EQ-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $eqFields = array_merge(['equipment_id', 'customer_id', 'contact_id', 'equipment_type'], $itemFields);
        $eqVals   = array_merge(
          [$eqId, $nextId, $contactId, 'DI Tank'],
          array_map(function ($f) use ($item, $dateFields) {
            $v = trim($item[$f] ?? '');
            if (in_array($f, $dateFields)) {
              return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
            }
            return $v === '' ? null : $v;
          }, $itemFields)
        );

        $fStr  = implode(', ', $eqFields);
        $pStr  = implode(', ', array_fill(0, count($eqFields), '?'));
        $stmt2 = $db->prepare("INSERT INTO equipment ($fStr) VALUES ($pStr)");
        $stmt2->bind_param(str_repeat('s', count($eqFields)), ...$eqVals);
        if (!$stmt2->execute()) {
          $errors[] = 'Failed to save tank: ' . $stmt2->error;
        }
        $stmt2->close();
      }
    }

    $stmt->close();
    $db->close();

    if (empty($errors)) {
      $success = true;
    }
  }
  }
}

// ── Load contact for display ──────────────────────────────────────────────────
$selectedContactId = trim($_GET['contact_id'] ?? $_POST['contact_id'] ?? '');
$selectedContact   = null;
if ($selectedContactId !== '') {
  $c    = get_mysql_connection();
  $stmt = $c->prepare("SELECT contact_id, CONCAT(first_name, ' ', last_name) AS full_name, company, address FROM contacts WHERE contact_id = ? LIMIT 1");
  $stmt->bind_param('s', $selectedContactId);
  $stmt->execute();
  $selectedContact = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $c->close();
}

// Display labels for item fields
$fieldLabels = [
  'serial_number'    => 'Tank Serial #',
  'ownership'        => 'Rent / Own',
  'tank_size'        => 'Tank Size',
  'location'         => 'Location',
  'resin_type'       => 'Resin Type',
  'resin_qty_cuft'   => 'Resin Qty (cu ft)',
  'last_service_date'=> 'Last Service Date',
  'regeneration_id'  => 'Regeneration #',
  'purchase_order'   => 'Purchase Order',
];
?>

<div class="container">
  <h2>Add New Customer</h2>

  <?php if ($success): ?>
    <p style="color:green;">✅ Customer added successfully (ID: <?= htmlspecialchars($nextId) ?>).</p>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <ul style="color:red;">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="POST">
    <?php renderCSRFInput(); ?>
    <!-- Customer Info -->
    <fieldset style="margin-bottom:20px;">
      <legend><strong>Customer Info</strong></legend>
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">

        <div>
          <label><strong>Customer ID</strong></label><br>
          <input type="text" value="<?= htmlspecialchars($nextId) ?>" readonly disabled style="background:#eee;">
          <input type="hidden" name="customer_id" value="<?= htmlspecialchars($nextId) ?>">
        </div>

        <div>
          <label><strong>Contact</strong></label><br>
          <?php if ($selectedContact): ?>
            <input type="hidden" name="contact_id" value="<?= htmlspecialchars($selectedContactId) ?>">
            <input type="text" value="<?= htmlspecialchars($selectedContact['full_name']) ?>" readonly style="background:#eee;">
            <input type="text" value="<?= htmlspecialchars($selectedContact['company']) ?>" readonly disabled style="background:#eee;margin-top:6px;">
            <input type="hidden" name="address" value="<?= htmlspecialchars($selectedContact['address']) ?>">
            <input type="text" value="<?= htmlspecialchars($selectedContact['address']) ?>" readonly disabled style="background:#eee;margin-top:6px;">
          <?php else: ?>
            <span style="color:#c00;">No contact selected. Please start from a contact profile.</span>
          <?php endif; ?>
        </div>

        <?php
        $displaySchema = array_filter($schema, fn($f) => !in_array($f, ['customer_id', 'contact_id', 'address']));
        foreach ($displaySchema as $field):
          $label = ucwords(str_replace('_', ' ', $field));
          $val   = htmlspecialchars($_POST[$field] ?? '');
        ?>
          <div>
            <label><strong><?= $label ?></strong></label><br>
            <input type="text" name="<?= $field ?>" value="<?= $val ?>">
          </div>
        <?php endforeach; ?>

      </div>
    </fieldset>

    <!-- Tank / Equipment Line Items -->
    <fieldset>
      <legend><strong>Tanks</strong></legend>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
        <label><strong>Number of Tanks:</strong></label>
        <input type="number" id="tankCount" min="1" max="20" value="1" style="width:70px;"
               oninput="syncTankCount(this.value)">
      </div>
      <div id="lineItems"></div>
      <button type="button" onclick="addLineItem()" class="btn-outline" style="margin-top:8px;">➕ Add Tank</button>
    </fieldset>

    <div style="margin-top:20px;">
      <button type="submit" class="btn-outline">💾 Save Customer</button>
    </div>
  </form>
</div>

<script>
const itemFields  = <?= json_encode(array_values($itemFields)) ?>;
const fieldLabels = <?= json_encode($fieldLabels) ?>;

function addLineItem() {
  const container = document.getElementById('lineItems');
  const index     = container.children.length;

  const wrapper = document.createElement('div');
  wrapper.style.cssText = 'margin-bottom:12px;border:1px solid #ccc;padding:12px;border-radius:6px;background:#f9f9f9;position:relative;';

  let html = `<button type="button" onclick="this.closest('div').remove(); document.getElementById('tankCount').value = document.getElementById('lineItems').children.length;"
    style="position:absolute;top:8px;right:8px;background:none;border:none;font-size:1.1em;cursor:pointer;color:#c00;" title="Remove tank">✕</button>`;
  html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;padding-right:24px;">';

  itemFields.forEach(f => {
    const label = fieldLabels[f] || f.replace(/_/g, ' ');
    let input;
    if (f === 'ownership') {
      input = `<select name="items[${index}][${f}]">
        <option value="">-- Select --</option>
        <option value="rental">Rental</option>
        <option value="customer-owned">Owned</option>
      </select>`;
    } else if (f === 'tank_size') {
      input = `<select name="items[${index}][${f}]">
        <option value="">-- Select --</option>
        <option value="1">1 cu ft</option>
        <option value="1.5">1.5 cu ft</option>
        <option value="2">2 cu ft</option>
        <option value="3">3 cu ft</option>
        <option value="3.5">3.5 cu ft</option>
        <option value="5">5 cu ft</option>
        <option value="6">6 cu ft</option>
        <option value="9">9 cu ft</option>
        <option value="12">12 cu ft</option>
      </select>`;
    } else if (f === 'last_service_date') {
      input = `<input type="date" name="items[${index}][${f}]">`;
    } else if (f === 'resin_qty_cuft') {
      input = `<input type="number" step="0.25" min="0" name="items[${index}][${f}]">`;
    } else {
      input = `<input type="text" name="items[${index}][${f}]">`;
    }
    html += `<div><label><strong>${label}</strong></label><br>${input}</div>`;
  });

  html += '</div>';
  wrapper.innerHTML = html;
  container.appendChild(wrapper);
}

function syncTankCount(val) {
  const count = Math.max(1, Math.min(20, parseInt(val) || 1));
  const container = document.getElementById('lineItems');
  while (container.children.length < count) addLineItem();
  while (container.children.length > count) container.lastElementChild.remove();
  document.getElementById('tankCount').value = container.children.length;
}

// Initialize with 1 tank on page load
document.addEventListener('DOMContentLoaded', () => syncTankCount(1));
</script>

<?php include_once(__DIR__ . '/layout_end.php'); ?>
