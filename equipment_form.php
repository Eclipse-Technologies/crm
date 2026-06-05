<?php
require_once 'equipment_mysql.php';
require_once __DIR__ . '/inventory_tx_helper.php';

$schema = require __DIR__ . '/equipment_schema.php';
$componentSlots = [
  'vessel' => 'Tank Vessel',
  'head' => 'Top Manifold/Head',
  'internal_distributor' => 'Internal Distributor',
  'inlet_fitting' => 'Threaded Inlet Fitting',
  'outlet_fitting' => 'Threaded Outlet Fitting',
  'resin' => 'Resin'
];
$componentSlotsUi = array_filter(
  $componentSlots,
  static function ($slot) {
    return $slot !== 'resin';
  },
  ARRAY_FILTER_USE_KEY
);
$resinQuantityOptions = ['1', '2', '3.5'];

$dateFields = ['install_date', 'purchase_date', 'last_service_date', 'next_service_date', 'warranty_expiry', 'created_date', 'modified_date'];
$editableFields = [
  'equipment_id' => 'Equipment ID',
  'equipment_type' => 'Equipment Type',
  'manufacturer' => 'Manufacturer',
  'model_number' => 'Model / Primary PN',
  'serial_number' => 'Serial / Assembly Tag',
  'ownership' => 'Ownership',
  'tank_size' => 'Tank Size',
  'resin_type' => 'Resin Type',
  'regeneration_id' => 'Regeneration ID',
  'status' => 'Workflow Status',
  'purchase_date' => 'Purchase Date',
  'purchase_value' => 'Purchase Value ($)',
  'purchase_order' => 'Purchase Order',
  'warranty_expiry' => 'Warranty Expiry',
  'notes' => 'Notes'
];
$errors = [];

function redirect_with_fallback_form($url)
{
  $target = (string) $url;
  if ($target === '') {
    $target = 'equipment_list.php';
  }

  if (!headers_sent()) {
    header('Location: ' . $target);
    exit;
  }

  $safe = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
  echo '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $safe . '"></head><body>';
  echo '<script>window.location.href=' . json_encode($target) . ';</script>';
  echo '<a href="' . $safe . '">Continue</a>';
  echo '</body></html>';
  exit;
}

function ensure_equipment_components_table_form(mysqli $conn)
{
  $conn->query(
    "CREATE TABLE IF NOT EXISTS equipment_components (
      id INT AUTO_INCREMENT PRIMARY KEY,
      equipment_id VARCHAR(255) NOT NULL,
      component_slot VARCHAR(64) NOT NULL,
      item_id VARCHAR(255) NOT NULL,
      quantity_required DECIMAL(12,3) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_equipment_slot (equipment_id, component_slot),
      KEY idx_component_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
}

function fetch_equipment_by_id_form($id, $schema)
{
  $conn = get_mysql_connection();
  $fields = implode(',', array_map(function ($f) { return '`' . $f . '`'; }, $schema));
  $stmt = $conn->prepare("SELECT $fields FROM equipment WHERE equipment_id = ?");
  $stmt->bind_param('s', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result ? $result->fetch_assoc() : null;
  $stmt->close();
  $conn->close();

  return $row;
}

function fetch_component_rows_form(mysqli $conn, $equipmentId)
{
  $rows = [];
  $stmt = $conn->prepare('SELECT component_slot, item_id, quantity_required FROM equipment_components WHERE equipment_id = ?');
  $stmt->bind_param('s', $equipmentId);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result ? $result->fetch_assoc() : null) {
    $rows[] = $row;
  }
  $stmt->close();

  return $rows;
}

function aggregate_component_qty_form(array $rows)
{
  $totals = [];
  foreach ($rows as $row) {
    $itemId = trim((string) ($row['item_id'] ?? ''));
    $qty = (float) ($row['quantity_required'] ?? 0);
    if ($itemId === '' || $qty <= 0) {
      continue;
    }
    if (!isset($totals[$itemId])) {
      $totals[$itemId] = 0.0;
    }
    $totals[$itemId] += $qty;
  }

  return $totals;
}

function requested_component_rows_form(array $componentSlots, array $post)
{
  $rows = [];
  foreach ($componentSlots as $slot => $label) {
    $itemId = trim((string) ($post['comp_item_' . $slot] ?? ''));
    $qtyRaw = trim((string) ($post['comp_qty_' . $slot] ?? ''));
    $qty = ($qtyRaw === '' || !is_numeric($qtyRaw)) ? 1.0 : (float) $qtyRaw;
    if ($itemId === '') {
      continue;
    }
    if ($qty <= 0) {
      $qty = 1.0;
    }

    $rows[] = [
      'component_slot' => $slot,
      'item_id' => $itemId,
      'quantity_required' => $qty
    ];
  }

  return $rows;
}

function normalize_location_value_form($value)
{
  $raw = strtolower(trim((string) $value));
  if ($raw === '') {
    return null;
  }

  $compact = str_replace(['-', '_'], ' ', $raw);
  $compact = preg_replace('/\s+/', ' ', $compact);

  if (in_array($compact, ['pool', 'recirculation', 'pool ready'], true)) {
    return 'pool';
  }
  if (in_array($compact, ['production', 'shop production'], true)) {
    return 'production';
  }
  if (in_array($compact, ['warehouse', 'shop warehouse'], true)) {
    return 'warehouse';
  }
  if (in_array($compact, ['customer site', 'customer', 'site'], true)) {
    return 'customer site';
  }

  return $compact;
}

function normalize_ownership_value_form($value)
{
  $raw = strtolower(trim((string) $value));
  if ($raw === '') {
    return null;
  }

  if (in_array($raw, ['purchased', 'customer owned', 'customer-owned'], true)) {
    return 'customer-owned';
  }

  if (in_array($raw, ['rental', 'lease', 'leased', 'evoqua rental', 'evoqua lease'], true)) {
    return $raw;
  }

  return $raw;
}

function is_resin_pool_product_form(array $product)
{
  $haystack = strtolower(trim(implode(' ', array_filter([
    (string) ($product['category'] ?? ''),
    (string) ($product['item_name'] ?? ''),
    (string) ($product['description'] ?? '')
  ]))));

  return $haystack !== '' && strpos($haystack, 'resin') !== false;
}

function next_generated_equipment_id_form(mysqli $conn)
{
  for ($i = 0; $i < 500; $i++) {
    $candidate = 'EQ-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    $stmt = $conn->prepare('SELECT 1 FROM equipment WHERE equipment_id = ? LIMIT 1');
    $stmt->bind_param('s', $candidate);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->fetch_assoc();
    $stmt->close();
    if (!$exists) {
      return $candidate;
    }
  }

  throw new RuntimeException('Unable to generate equipment ID.');
}

$edit_mode = isset($_GET['id']) && $_GET['id'] !== '';
$equipment = $edit_mode ? fetch_equipment_by_id_form($_GET['id'], $schema) : null;
$formData = $equipment ?: [];
$componentMap = [];

$conn = get_mysql_connection();
ensure_equipment_components_table_form($conn);
ensure_inventory_transactions_table($conn);

$products = [];
$result = $conn->query('SELECT item_id, item_name, category, description, quantity_in_stock, unit FROM inventory ORDER BY item_name ASC, item_id ASC');
while ($row = $result ? $result->fetch_assoc() : null) {
  $products[] = $row;
}
if ($result) {
  $result->free();
}

$resinProducts = array_values(array_filter($products, 'is_resin_pool_product_form'));

if (!$edit_mode && trim((string) ($formData['equipment_id'] ?? '')) === '') {
  $formData['equipment_id'] = next_generated_equipment_id_form($conn);
}

if ($edit_mode && $equipment) {
  foreach (fetch_component_rows_form($conn, $equipment['equipment_id']) as $row) {
    $componentMap[$row['component_slot']] = [
      'item_id' => $row['item_id'],
      'quantity_required' => (float) $row['quantity_required']
    ];
  }
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod === 'POST') {
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $errors[] = 'CSRF validation failed';
  } else {
  $formData = $_POST;
  $equipmentId = trim((string) ($_POST['equipment_id'] ?? ''));
  $isEdit = isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1';

  if (!$isEdit && $equipmentId === '') {
    $equipmentId = next_generated_equipment_id_form($conn);
    $_POST['equipment_id'] = $equipmentId;
    $formData['equipment_id'] = $equipmentId;
  }

  if ($equipmentId === '') {
    $errors[] = 'Equipment ID is required.';
  }

  if (!$isEdit && $equipmentId !== '') {
    $stmtCheck = $conn->prepare('SELECT COUNT(*) AS c FROM equipment WHERE equipment_id = ?');
    $stmtCheck->bind_param('s', $equipmentId);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    $rowCheck = $resCheck ? $resCheck->fetch_assoc() : null;
    $stmtCheck->close();
    if ((int) ($rowCheck['c'] ?? 0) > 0) {
      $errors[] = 'Equipment ID already exists.';
    }
  }

  if (empty($errors)) {
    $conn->begin_transaction();
    try {
      if ($isEdit) {
        $fields = [];
        $values = [];
        foreach (array_keys($editableFields) as $field) {
          if ($field === 'equipment_id') {
            continue;
          }
          $fields[] = "`$field` = ?";
          $val = $_POST[$field] ?? '';
          if ($field === 'location') {
            $values[] = normalize_location_value_form($val);
            continue;
          }
          if ($field === 'ownership') {
            $values[] = normalize_ownership_value_form($val);
            continue;
          }
          if (in_array($field, $dateFields, true)) {
            $values[] = ($val !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) ? $val : null;
          } else {
            $values[] = ($val === '') ? null : $val;
          }
        }
        $values[] = $equipmentId;

        $sql = 'UPDATE equipment SET ' . implode(', ', $fields) . ' WHERE equipment_id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($values)), ...$values);
        $stmt->execute();
        $stmt->close();
      } else {
        $fields = [];
        $values = [];
        foreach ($schema as $field) {
          $fields[] = '`' . $field . '`';
          if ($field === 'equipment_id') {
            $values[] = $equipmentId;
            continue;
          }
          if (!array_key_exists($field, $editableFields)) {
            $values[] = null;
            continue;
          }
          $val = $_POST[$field] ?? '';
          if ($field === 'location') {
            $values[] = normalize_location_value_form($val);
            continue;
          }
          if ($field === 'ownership') {
            $values[] = normalize_ownership_value_form($val);
            continue;
          }
          if (in_array($field, $dateFields, true)) {
            $values[] = ($val !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) ? $val : null;
          } else {
            $values[] = ($val === '') ? null : $val;
          }
        }

        $sql = 'INSERT INTO equipment (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($values)), ...$values);
        $stmt->execute();
        $stmt->close();
      }

      $existingRows = $isEdit ? fetch_component_rows_form($conn, $equipmentId) : [];
      $newRows = requested_component_rows_form($componentSlots, $_POST);
      $oldTotals = aggregate_component_qty_form($existingRows);
      $newTotals = aggregate_component_qty_form($newRows);

      $itemIds = array_unique(array_merge(array_keys($oldTotals), array_keys($newTotals)));
      foreach ($itemIds as $itemId) {
        $delta = ($newTotals[$itemId] ?? 0.0) - ($oldTotals[$itemId] ?? 0.0);
        if ($delta > 0) {
          $stmtInv = $conn->prepare('SELECT COALESCE(quantity_in_stock, 0) AS qty FROM inventory WHERE item_id = ? LIMIT 1');
          $stmtInv->bind_param('s', $itemId);
          $stmtInv->execute();
          $resInv = $stmtInv->get_result();
          $rowInv = $resInv ? $resInv->fetch_assoc() : null;
          $stmtInv->close();
          if (!$rowInv) {
            throw new RuntimeException('Component not found in products: ' . $itemId);
          }

          $stockQty = (float) ($rowInv['qty'] ?? 0);
          if ($stockQty < $delta) {
            throw new RuntimeException('Insufficient stock for ' . $itemId . '. Need ' . $delta . ', available ' . $stockQty . '.');
          }
        }
      }

      foreach ($itemIds as $itemId) {
        $delta = ($newTotals[$itemId] ?? 0.0) - ($oldTotals[$itemId] ?? 0.0);
        if (abs($delta) < 0.000001) {
          continue;
        }
        inventory_tx_apply_delta_with_audit($conn, $itemId, -1.0 * $delta, [
          'entity_type' => 'equipment',
          'entity_id' => $equipmentId,
          'source_type' => 'equipment',
          'source_ref' => $equipmentId,
          'reason_code' => 'equipment_component_change',
          'reason_text' => 'equipment_form component delta',
        ]);
      }

      $stmtDel = $conn->prepare('DELETE FROM equipment_components WHERE equipment_id = ?');
      $stmtDel->bind_param('s', $equipmentId);
      $stmtDel->execute();
      $stmtDel->close();

      foreach ($newRows as $row) {
        $slot = $row['component_slot'];
        $itemId = $row['item_id'];
        $qty = (float) $row['quantity_required'];
        $stmtIns = $conn->prepare('INSERT INTO equipment_components (equipment_id, component_slot, item_id, quantity_required) VALUES (?, ?, ?, ?)');
        $stmtIns->bind_param('sssd', $equipmentId, $slot, $itemId, $qty);
        $stmtIns->execute();
        $stmtIns->close();
      }

      $conn->commit();
      $conn->close();
      redirect_with_fallback_form('equipment_list.php');
    } catch (Throwable $e) {
      $conn->rollback();
      $errors[] = $e->getMessage();
    }
  }
}
}

// Render form data for component slots
foreach ($componentSlots as $slot => $label) {
    $itemId = trim((string) ($_POST['comp_item_' . $slot] ?? ''));
    $qty = trim((string) ($_POST['comp_qty_' . $slot] ?? '1'));
    if ($itemId !== '') {
      $componentMap[$slot] = ['item_id' => $itemId, 'quantity_required' => $qty];
    }
}

$conn->close();

require_once 'layout_start.php';
?>

<style>
.eq-form-wrap {
  max-width: 1100px;
  margin: 24px auto;
}

.eq-form-head {
  margin-bottom: 14px;
}

.eq-form-head h2 {
  margin-bottom: 4px;
}

.eq-alert {
  border-radius: 8px;
  background: #fee2e2;
  border: 1px solid #fecaca;
  color: #991b1b;
  padding: 10px 12px;
  margin-bottom: 14px;
}

.eq-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  padding: 14px;
  margin-bottom: 12px;
}

.eq-panel h3 {
  margin: 0 0 10px;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #374151;
}

.eq-grid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.eq-field {
  display: flex;
  flex-direction: column;
}

.eq-field label {
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 5px;
  color: #4b5563;
}

.eq-field input,
.eq-field select,
.eq-field textarea {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px;
  font-size: 13px;
}

.eq-actions {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}

.btn-cancel {
  border: 1px solid #d1d5db;
  background: #fff;
  color: #374151;
}
</style>

<div class="eq-form-wrap">
  <!-- ALERT: Customer assignment for rental tanks is pending RFID integration -->
  <div class="eq-alert" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; margin-bottom:16px; padding:10px 16px;">
    <strong>Note:</strong> Equipment module only tracks available tanks and their status.<br>
    <strong>Clarification:</strong> <span style="color:#0d6efd;">Rental/ownership assignment is configured in the <b>Contract</b> module when creating or editing a contract.</span>
  </div>
  <div class="eq-form-head">
    <h2><?= $edit_mode ? 'Edit Tank' : 'Add Tank' ?></h2>
    <div style="color:#6b7280;">This form now links tank components to product inventory and applies stock checks on save.</div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="eq-alert">
      <strong>Could not save:</strong>
      <ul style="margin:6px 0 0 18px;">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST">
    <?php renderCSRFInput(); ?>
    <?php if ($edit_mode): ?>
      <input type="hidden" name="edit_mode" value="1">
    <?php endif; ?>

    <div class="eq-panel">
      <h3>Tank Fields</h3>
      <div class="eq-grid">
        <?php foreach ($editableFields as $field => $label): ?>
          <?php
          $value = $formData[$field] ?? '';
          $isDate = in_array($field, $dateFields, true);
          $isTextArea = ($field === 'notes');
          ?>
          <div class="eq-field <?= $isTextArea ? 'eq-field-wide' : '' ?>" style="<?= $isTextArea ? 'grid-column: 1 / -1;' : '' ?>">
            <label for="<?= htmlspecialchars($field) ?>"><?= htmlspecialchars($label) ?></label>
            <?php if ($isTextArea): ?>
              <textarea name="<?= htmlspecialchars($field) ?>" id="<?= htmlspecialchars($field) ?>" rows="4"><?= htmlspecialchars((string) $value) ?></textarea>
            <?php elseif ($field === 'ownership'): ?>
              <select name="ownership" id="ownership">
                <option value="">Select</option>
                <option value="rental" <?= strtolower(trim((string) $value)) === 'rental' ? 'selected' : '' ?>>Rental</option>
                <option value="customer-owned" <?= in_array(strtolower(trim((string) $value)), ['customer-owned', 'customer owned', 'purchased'], true) ? 'selected' : '' ?>>Customer Owned</option>
              </select>
            <?php elseif ($field === 'tank_size'): ?>
              <?php $tankSizeSelected = trim((string) $value); ?>
              <select name="tank_size" id="tank_size">
                <option value="">Select</option>
                <option value="1" <?= $tankSizeSelected === '1' ? 'selected' : '' ?>>1</option>
                <option value="2" <?= $tankSizeSelected === '2' ? 'selected' : '' ?>>2</option>
                <option value="3.5" <?= $tankSizeSelected === '3.5' ? 'selected' : '' ?>>3.5</option>
              </select>
            <?php elseif ($field === 'status'): ?>
              <?php $statusSelected = strtolower(trim((string) $value)); ?>
              <select name="status" id="status">
                <option value="Active" <?= $statusSelected === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="Available" <?= $statusSelected === 'available' ? 'selected' : '' ?>>Available</option>
                <option value="In Service" <?= $statusSelected === 'in service' ? 'selected' : '' ?>>In Service</option>
                <option value="Maintenance" <?= $statusSelected === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                <option value="Inactive" <?= $statusSelected === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              </select>
            <?php else: ?>
              <input
                type="<?= $isDate ? 'date' : 'text' ?>"
                name="<?= htmlspecialchars($field) ?>"
                id="<?= htmlspecialchars($field) ?>"
                value="<?= htmlspecialchars((string) $value) ?>"
                <?= ($field === 'equipment_id') ? 'readonly' : '' ?>
              >
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="eq-panel">
      <h3>Component Build From Products</h3>
      <div class="eq-grid">
        <?php foreach ($componentSlotsUi as $slot => $label): ?>
          <?php
          $selectedItem = $componentMap[$slot]['item_id'] ?? '';
          $selectedQty = $componentMap[$slot]['quantity_required'] ?? 1;
          ?>
          <div class="eq-field">
            <label><?= htmlspecialchars($label) ?> Product</label>
            <select name="comp_item_<?= htmlspecialchars($slot) ?>">
              <option value="">No product linked</option>
              <?php foreach ($products as $prod): ?>
                <?php
                $prodId = (string) ($prod['item_id'] ?? '');
                $prodName = (string) ($prod['item_name'] ?? '');
                $prodQty = (float) ($prod['quantity_in_stock'] ?? 0);
                $prodUnit = (string) ($prod['unit'] ?? '');
                ?>
                <option value="<?= htmlspecialchars($prodId) ?>" <?= $selectedItem === $prodId ? 'selected' : '' ?>>
                  <?= htmlspecialchars($prodId . ' - ' . $prodName . ' (Stock: ' . $prodQty . ($prodUnit ? ' ' . $prodUnit : '') . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="eq-field">
            <label><?= htmlspecialchars($label) ?> Qty</label>
            <input type="number" min="0.001" step="0.001" name="comp_qty_<?= htmlspecialchars($slot) ?>" value="<?= htmlspecialchars((string) $selectedQty) ?>">
          </div>
        <?php endforeach; ?>
      </div>

      <div class="eq-grid" style="margin-top: 10px;">
        <?php
        $selectedResinItem = $componentMap['resin']['item_id'] ?? '';
        $selectedResinQty = isset($componentMap['resin']['quantity_required'])
          ? rtrim(rtrim(number_format((float) $componentMap['resin']['quantity_required'], 3, '.', ''), '0'), '.')
          : '1';
        if (!in_array($selectedResinQty, $resinQuantityOptions, true)) {
          $selectedResinQty = '1';
        }
        ?>
        <div class="eq-field">
          <label>Resin From Pool</label>
          <select name="comp_item_resin">
            <option value="">Select resin from pool</option>
            <?php foreach ($resinProducts as $prod): ?>
              <?php
              $prodId = (string) ($prod['item_id'] ?? '');
              $prodName = (string) ($prod['item_name'] ?? '');
              $prodQty = (float) ($prod['quantity_in_stock'] ?? 0);
              $prodUnit = (string) ($prod['unit'] ?? '');
              ?>
              <option value="<?= htmlspecialchars($prodId) ?>" <?= $selectedResinItem === $prodId ? 'selected' : '' ?>>
                <?= htmlspecialchars($prodId . ' - ' . $prodName . ' (Pool: ' . $prodQty . ($prodUnit ? ' ' . $prodUnit : '') . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="eq-field">
          <label>Resin Qty (cuft)</label>
          <select name="comp_qty_resin">
            <?php foreach ($resinQuantityOptions as $qtyOption): ?>
              <option value="<?= htmlspecialchars($qtyOption) ?>" <?= $selectedResinQty === $qtyOption ? 'selected' : '' ?>><?= htmlspecialchars($qtyOption) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="eq-actions">
      <button type="submit" class="btn btn-primary">Save Tank</button>
      <a href="equipment_list.php" class="btn btn-cancel">Cancel</a>
    </div>
  </form>
</div>

<?php require_once 'layout_end.php'; ?>
