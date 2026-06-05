<?php
require_once 'db_mysql.php';
require_once __DIR__ . '/request_guard.php';
require_once __DIR__ . '/inventory_tx_helper.php';

$pageTitle = 'Tank Inventory';
$equipmentSchema = require __DIR__ . '/equipment_schema.php';
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

$postError = '';
$postSuccess = '';

function redirect_with_fallback($url)
{
    $target = (string) $url;
    if ($target === '') {
        $target = 'equipment_list.php';
    }

    if (!headers_sent()) {
        header('Location: ' . $target);
        exit();
    }

    $safe = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $safe . '"></head><body>';
    echo '<script>window.location.href=' . json_encode($target) . ';</script>';
    echo '<a href="' . $safe . '">Continue</a>';
    echo '</body></html>';
    exit();
}

function ensure_equipment_components_table(mysqli $conn)
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

function fetch_component_rows(mysqli $conn, $equipmentId)
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

function aggregate_component_qty(array $rows)
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

function requested_component_rows(array $componentSlots, array $post)
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

function next_generated_equipment_id(mysqli $conn)
{
    for ($i = 0; $i < 200; $i++) {
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

    throw new RuntimeException('Unable to generate new equipment ID.');
}

function next_generated_part_number(mysqli $conn, $sourceModelNumber)
{
    $base = strtoupper(trim((string) $sourceModelNumber));
    $base = preg_replace('/[^A-Z0-9\-]/', '-', $base);
    $base = preg_replace('/-+/', '-', (string) $base);
    $base = trim((string) $base, '-');
    if ($base === '') {
        $base = 'PN';
    }

    $dateCode = date('ymd');
    for ($i = 1; $i <= 999; $i++) {
        $candidate = $base . '-' . $dateCode . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare('SELECT 1 FROM equipment WHERE model_number = ? LIMIT 1');
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            return $candidate;
        }
    }

    throw new RuntimeException('Unable to generate new part number.');
}

function normalize_location_value($value)
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

function normalize_ownership_value($value)
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

function is_resin_pool_product(array $product)
{
    $haystack = strtolower(trim(implode(' ', array_filter([
        (string) ($product['category'] ?? ''),
        (string) ($product['item_name'] ?? ''),
        (string) ($product['description'] ?? '')
    ]))));

    return $haystack !== '' && strpos($haystack, 'resin') !== false;
}

// Handle POST actions
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod === 'POST') {
    require_post_with_csrf();
    $conn = get_mysql_connection();
    ensure_equipment_components_table($conn);
    ensure_inventory_transactions_table($conn);

    if (isset($_POST['delete_id'])) {
        $deleteId = $_POST['delete_id'];
        if (!empty($deleteId)) {
            $conn->begin_transaction();
            try {
                $existingRows = fetch_component_rows($conn, $deleteId);
                foreach (aggregate_component_qty($existingRows) as $itemId => $qtyToReturn) {
                    inventory_tx_apply_delta_with_audit($conn, $itemId, (float) $qtyToReturn, [
                        'entity_type' => 'equipment',
                        'entity_id' => $deleteId,
                        'source_type' => 'equipment',
                        'source_ref' => $deleteId,
                        'reason_code' => 'equipment_component_change',
                        'reason_text' => 'equipment_list delete return components',
                    ]);
                }

                $stmtCompDelete = $conn->prepare('DELETE FROM equipment_components WHERE equipment_id = ?');
                $stmtCompDelete->bind_param('s', $deleteId);
                $stmtCompDelete->execute();
                $stmtCompDelete->close();

                $stmt = $conn->prepare('DELETE FROM equipment WHERE equipment_id = ?');
                $stmt->bind_param('s', $deleteId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $postSuccess = 'Tank deleted and components returned to inventory stock.';
            } catch (Throwable $e) {
                $conn->rollback();
                $postError = 'Delete failed: ' . $e->getMessage();
            }
        }

        if ($postError === '') {
            $conn->close();
            redirect_with_fallback('equipment_list.php');
        }
    }

    if (isset($_POST['duplicate_build_id'])) {
        $sourceEquipmentId = trim((string) ($_POST['duplicate_build_id'] ?? ''));

        if ($sourceEquipmentId !== '') {
            $conn->begin_transaction();
            try {
                $stmtSource = $conn->prepare('SELECT * FROM equipment WHERE equipment_id = ? LIMIT 1');
                $stmtSource->bind_param('s', $sourceEquipmentId);
                $stmtSource->execute();
                $resSource = $stmtSource->get_result();
                $sourceItem = $resSource ? $resSource->fetch_assoc() : null;
                $stmtSource->close();

                if (!$sourceItem) {
                    throw new RuntimeException('Source tank not found: ' . $sourceEquipmentId);
                }

                $newEquipmentId = next_generated_equipment_id($conn);
                $newPartNumber = next_generated_part_number($conn, $sourceItem['model_number'] ?? '');
                $newRows = fetch_component_rows($conn, $sourceEquipmentId);
                $requiredTotals = aggregate_component_qty($newRows);

                foreach ($requiredTotals as $itemId => $qtyRequired) {
                    $stmtInv = $conn->prepare('SELECT COALESCE(quantity_in_stock, 0) AS qty FROM inventory WHERE item_id = ? LIMIT 1');
                    $stmtInv->bind_param('s', $itemId);
                    $stmtInv->execute();
                    $resInv = $stmtInv->get_result();
                    $rowInv = $resInv ? $resInv->fetch_assoc() : null;
                    $stmtInv->close();

                    if (!$rowInv) {
                        throw new RuntimeException('Component item not found in inventory: ' . $itemId);
                    }

                    $stockQty = (float) ($rowInv['qty'] ?? 0);
                    if ($stockQty < $qtyRequired) {
                        throw new RuntimeException('Insufficient stock for item ' . $itemId . '. Need ' . $qtyRequired . ', available ' . $stockQty . '.');
                    }
                }

                $dateToday = date('Y-m-d');
                $fields = [];
                $values = [];
                foreach ($equipmentSchema as $field) {
                    $fields[] = '`' . $field . '`';

                    if ($field === 'equipment_id') {
                        $values[] = $newEquipmentId;
                        continue;
                    }

                    if (in_array($field, ['serial_number', 'regeneration_id', 'customer_id', 'contact_id', 'contract_id', 'install_date', 'last_service_date', 'next_service_date', 'location'], true)) {
                        $values[] = null;
                        continue;
                    }

                    if ($field === 'status') {
                        $values[] = 'Available';
                        continue;
                    }

                    if ($field === 'model_number') {
                        $values[] = $newPartNumber;
                        continue;
                    }

                    if ($field === 'created_date' || $field === 'modified_date') {
                        $values[] = $dateToday;
                        continue;
                    }

                    if ($field === 'notes') {
                        $existingNotes = trim((string) ($sourceItem['notes'] ?? ''));
                        $dupNote = 'Duplicated build from ' . $sourceEquipmentId . ' on ' . $dateToday;
                        $values[] = $existingNotes === '' ? $dupNote : ($existingNotes . PHP_EOL . $dupNote);
                        continue;
                    }

                    $values[] = ($sourceItem[$field] ?? null);
                }

                $sqlInsert = 'INSERT INTO equipment (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bind_param(str_repeat('s', count($values)), ...$values);
                $stmtInsert->execute();
                $stmtInsert->close();

                foreach ($requiredTotals as $itemId => $qtyRequired) {
                    inventory_tx_apply_delta_with_audit($conn, $itemId, -1.0 * (float) $qtyRequired, [
                        'entity_type' => 'equipment',
                        'entity_id' => $newEquipmentId,
                        'source_type' => 'equipment',
                        'source_ref' => $newEquipmentId,
                        'reason_code' => 'equipment_component_change',
                        'reason_text' => 'equipment_list duplicate build consume components',
                    ]);
                }

                foreach ($newRows as $row) {
                    $slot = (string) ($row['component_slot'] ?? '');
                    $itemId = (string) ($row['item_id'] ?? '');
                    $qty = (float) ($row['quantity_required'] ?? 0);
                    if ($slot === '' || $itemId === '' || $qty <= 0) {
                        continue;
                    }

                    $stmtComp = $conn->prepare('INSERT INTO equipment_components (equipment_id, component_slot, item_id, quantity_required) VALUES (?, ?, ?, ?)');
                    $stmtComp->bind_param('sssd', $newEquipmentId, $slot, $itemId, $qty);
                    $stmtComp->execute();
                    $stmtComp->close();
                }

                $conn->commit();
                $postSuccess = 'Build duplicated to ' . $newEquipmentId . ' with new part number ' . $newPartNumber . '.';
            } catch (Throwable $e) {
                $conn->rollback();
                $postError = 'Duplicate failed: ' . $e->getMessage();
            }
        }

        if ($postError === '') {
            $conn->close();
            redirect_with_fallback('equipment_list.php');
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_equipment') {
        $equipmentId = trim($_POST['equipment_id'] ?? '');

        if ($equipmentId !== '') {
            $conn->begin_transaction();
            try {
                $fields = [];
                $values = [];

                foreach ($equipmentSchema as $field) {
                    if ($field === 'equipment_id') {
                        continue;
                    }

                    $fields[] = "`$field` = ?";
                    $val = $_POST[$field] ?? '';

                    if ($field === 'location') {
                        $values[] = normalize_location_value($val);
                        continue;
                    }

                    if ($field === 'ownership') {
                        $values[] = normalize_ownership_value($val);
                        continue;
                    }

                    if (in_array($field, ['install_date', 'purchase_date', 'last_service_date', 'next_service_date', 'warranty_expiry'], true)) {
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

                $existingRows = fetch_component_rows($conn, $equipmentId);
                $newRows = requested_component_rows($componentSlots, $_POST);

                $oldTotals = aggregate_component_qty($existingRows);
                $newTotals = aggregate_component_qty($newRows);

                $itemIds = array_unique(array_merge(array_keys($oldTotals), array_keys($newTotals)));
                foreach ($itemIds as $itemId) {
                    $delta = ($newTotals[$itemId] ?? 0.0) - ($oldTotals[$itemId] ?? 0.0);
                    if ($delta > 0) {
                        $stmtInv = $conn->prepare('SELECT COALESCE(quantity_in_stock, 0) AS qty FROM inventory WHERE item_id = ? LIMIT 1');
                        $stmtInv->bind_param('s', $itemId);
                        $stmtInv->execute();
                        $resultInv = $stmtInv->get_result();
                        $invRow = $resultInv ? $resultInv->fetch_assoc() : null;
                        $stmtInv->close();

                        if (!$invRow) {
                            throw new RuntimeException('Component item not found in inventory: ' . $itemId);
                        }

                        $stockQty = (float) ($invRow['qty'] ?? 0);
                        if ($stockQty < $delta) {
                            throw new RuntimeException('Insufficient stock for item ' . $itemId . '. Need ' . $delta . ', available ' . $stockQty . '.');
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
                        'reason_text' => 'equipment_list update component delta',
                    ]);
                }

                $stmtDel = $conn->prepare('DELETE FROM equipment_components WHERE equipment_id = ?');
                $stmtDel->bind_param('s', $equipmentId);
                $stmtDel->execute();
                $stmtDel->close();

                foreach ($newRows as $row) {
                    $slot = $row['component_slot'];
                    $itemId = $row['item_id'];
                    $qty = $row['quantity_required'];

                    $stmtIns = $conn->prepare('INSERT INTO equipment_components (equipment_id, component_slot, item_id, quantity_required) VALUES (?, ?, ?, ?)');
                    $stmtIns->bind_param('sssd', $equipmentId, $slot, $itemId, $qty);
                    $stmtIns->execute();
                    $stmtIns->close();
                }

                $conn->commit();
                $postSuccess = 'Tank and component build updated successfully.';
            } catch (Throwable $e) {
                $conn->rollback();
                $postError = 'Update failed: ' . $e->getMessage();
            }
        }

        if ($postError === '') {
            $conn->close();
            redirect_with_fallback('equipment_list.php');
        }
    }

    $conn->close();
}

// Fetch data
$conn = get_mysql_connection();
ensure_equipment_components_table($conn);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countResult = $conn->query("SELECT COUNT(*) AS total FROM equipment WHERE LOWER(TRIM(COALESCE(ownership, ''))) NOT IN ('customer-owned', 'customer owned', 'purchased')");
$countRow = $countResult ? $countResult->fetch_assoc() : null;
$totalEquipment = (int) ($countRow['total'] ?? 0);
if ($countResult) {
    $countResult->free();
}
$totalPages = max(1, (int) ceil($totalEquipment / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmtEquipment = $conn->prepare("SELECT * FROM equipment WHERE LOWER(TRIM(COALESCE(ownership, ''))) NOT IN ('customer-owned', 'customer owned', 'purchased') ORDER BY tank_size DESC, equipment_id ASC LIMIT ? OFFSET ?");
$stmtEquipment->bind_param('ii', $perPage, $offset);
$stmtEquipment->execute();
$result = $stmtEquipment->get_result();
$equipment = [];
while ($row = $result ? $result->fetch_assoc() : null) {
    $equipment[] = $row;
}
if ($result) {
    $result->free();
}
$stmtEquipment->close();

$result = $conn->query('SELECT item_id, item_name, category, description, quantity_in_stock, unit FROM inventory ORDER BY item_name ASC, item_id ASC');
$products = [];
$productMap = [];
while ($row = $result ? $result->fetch_assoc() : null) {
    $products[] = $row;
    $productMap[$row['item_id']] = $row;
}
if ($result) {
    $result->free();
}

$resinProducts = array_values(array_filter($products, 'is_resin_pool_product'));

$componentsByEquipment = [];
$equipmentIds = array_map(static function ($row) {
    return (string) ($row['equipment_id'] ?? '');
}, $equipment);
$equipmentIds = array_values(array_filter(array_unique($equipmentIds)));
if (!empty($equipmentIds)) {
    $placeholders = implode(',', array_fill(0, count($equipmentIds), '?'));
    $stmtComp = $conn->prepare('SELECT equipment_id, component_slot, item_id, quantity_required FROM equipment_components WHERE equipment_id IN (' . $placeholders . ')');
    $types = str_repeat('s', count($equipmentIds));
    $stmtComp->bind_param($types, ...$equipmentIds);
    $stmtComp->execute();
    $result = $stmtComp->get_result();
    while ($row = $result ? $result->fetch_assoc() : null) {
        $equipmentId = $row['equipment_id'];
        $slot = $row['component_slot'];

        if (!isset($componentsByEquipment[$equipmentId])) {
            $componentsByEquipment[$equipmentId] = [];
        }
        $componentsByEquipment[$equipmentId][$slot] = [
            'item_id' => $row['item_id'],
            'quantity_required' => (float) $row['quantity_required']
        ];
    }
    if ($result) {
        $result->free();
    }
    $stmtComp->close();
}

$result = $conn->query('SELECT customer_id, address FROM customers ORDER BY customer_id');
$customers = [];
$customerMap = [];
while ($row = $result ? $result->fetch_assoc() : null) {
    $customers[] = $row;
    $customerMap[$row['customer_id']] = $row['address'] ?: $row['customer_id'];
}
if ($result) {
    $result->free();
}

$conn->close();

function normalize_text($value)
{
    return strtolower(trim((string) $value));
}

function derive_workflow_state(array $item)
{
    $status = normalize_text($item['status'] ?? '');
    $location = normalize_text($item['location'] ?? '');
    $hasCustomer = !empty($item['customer_id']);

    if ($hasCustomer) {
        return 'in-service';
    }

    if (strpos($location, 'production') !== false) {
        return 'shop-production';
    }

    if (strpos($location, 'warehouse') !== false) {
        return 'shop-warehouse';
    }

    if ($status === 'maintenance') {
        return 'maintenance';
    }

    // RFID TODO: pool-ready state will be driven by RFID scan when implemented
    // if (
    //     $status === 'available' ||
    //     $status === 'active' ||
    //     strpos($location, 'pool') !== false ||
    //     strpos($location, 'recirculation') !== false
    // ) {
    //     return 'pool-ready';
    // }

    return 'other';
}

function workflow_label($state)
{
    $labels = [
        'in-service' => 'In Service',
        'pool-ready' => 'Pool Ready',
        'shop-production' => 'Shop Production',
        'shop-warehouse' => 'Shop Warehouse',
        'maintenance' => 'Maintenance',
        'other' => 'Other'
    ];

    return $labels[$state] ?? 'Other';
}

$metrics = [
    'total' => $totalEquipment,
    'in-service' => 0,
    'pool-ready' => 0,
    'shop-production' => 0,
    'shop-warehouse' => 0,
    'maintenance' => 0,
    'rental' => 0,
];

foreach ($equipment as &$item) {
    $state = derive_workflow_state($item);
    $item['workflow_state'] = $state;

    if (isset($metrics[$state])) {
        $metrics[$state]++;
    }

    $ownership = normalize_text($item['ownership'] ?? '');
    if ($ownership === 'rental') {
        $metrics['rental']++;
    }
}
unset($item);

require_once 'layout_start.php';
?>

<style>
.inventory-header {
    background: linear-gradient(135deg, #0f766e 0%, #0e7490 100%);
    color: #fff;
    padding: 28px;
    border-radius: 12px;
    margin-bottom: 18px;
}

.inventory-header h1 {
    margin: 0 0 6px;
    font-size: 30px;
}

.inventory-header p {
    margin: 3px 0;
    opacity: 0.95;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
    gap: 12px;
    margin: 18px 0 20px;
}

.metric-card {
    background: #fff;
    border-radius: 10px;
    border-left: 4px solid #0f766e;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    padding: 14px 16px;
}

.metric-label {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    font-weight: 700;
}

.metric-value {
    font-size: 26px;
    font-weight: 800;
    color: #111827;
    line-height: 1.1;
}

.metric-subtext {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.filter-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-group input,
.filter-group select {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 9px 10px;
    font-size: 13px;
    min-width: 150px;
}

.btn {
    border: none;
    text-decoration: none;
    font-size: 13px;
    border-radius: 8px;
    padding: 10px 16px;
    cursor: pointer;
    font-weight: 700;
}

.btn-primary {
    background: #0f766e;
    color: #fff;
}

.btn-primary:hover {
    background: #0a5f59;
}

.inventory-table {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.table-scroll {
    overflow-x: auto;
}

.inventory-table table {
    width: 100%;
    min-width: 1080px;
    border-collapse: collapse;
}

.inventory-table th {
    background: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
    color: #374151;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.45px;
    padding: 11px 12px;
    text-align: left;
}

.inventory-table td {
    border-bottom: 1px solid #f3f4f6;
    padding: 10px 12px;
    font-size: 13px;
    vertical-align: top;
}

.tank-row-summary {
    cursor: pointer;
}

.tank-row-summary:hover {
    background: #f9fafb;
}

.expand-btn {
    border: none;
    background: transparent;
    cursor: pointer;
    width: 28px;
    text-align: center;
    font-size: 15px;
}

.badge {
    display: inline-block;
    border-radius: 999px;
    padding: 3px 9px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.badge-rental { background: #dbeafe; color: #1d4ed8; }
.badge-customer-owned { background: #dcfce7; color: #166534; }
.badge-purchased { background: #fef3c7; color: #92400e; }

.state-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    border-radius: 6px;
    padding: 3px 8px;
    white-space: nowrap;
}

.state-in-service { background: #dcfce7; color: #166534; }
.state-pool-ready { background: #cffafe; color: #155e75; }
.state-shop-production { background: #ede9fe; color: #5b21b6; }
.state-shop-warehouse { background: #fce7f3; color: #9d174d; }
.state-maintenance { background: #fee2e2; color: #991b1b; }
.state-other { background: #e5e7eb; color: #374151; }

.status-badge {
    display: inline-block;
    padding: 3px 7px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.status-active { background: #dcfce7; color: #166534; }
.status-available { background: #cffafe; color: #155e75; }
.status-in-service { background: #dcfce7; color: #166534; }
.status-maintenance { background: #fee2e2; color: #991b1b; }
.status-inactive { background: #f3f4f6; color: #4b5563; }

.text-muted {
    color: #9ca3af;
}

.inline-stack {
    line-height: 1.35;
}

.edit-panel {
    display: none;
    background: #f9fafb;
}

.edit-panel.visible {
    display: table-row;
}

.edit-panel-content {
    padding: 18px;
    border-top: 2px solid #e5e7eb;
}

.edit-section-title {
    margin: 4px 0 10px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #4b5563;
    font-weight: 800;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 11px;
    font-weight: 700;
    color: #6b7280;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.form-group input,
.form-group select,
.form-group textarea {
    border: 1px solid #d1d5db;
    border-radius: 5px;
    padding: 8px 9px;
    font-size: 13px;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #0f766e;
    box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.1);
}

.form-actions {
    border-top: 1px solid #e5e7eb;
    padding-top: 10px;
    display: flex;
    gap: 8px;
}

.btn-save {
    background: #0f766e;
    color: #fff;
}

.btn-cancel {
    background: #e5e7eb;
    color: #374151;
}

.action-btns {
    display: flex;
    gap: 6px;
}

.action-btn {
    border: none;
    border-radius: 5px;
    padding: 5px 8px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.action-btn-delete {
    background: #fee2e2;
    color: #991b1b;
}

.no-data {
    text-align: center;
    color: #6b7280;
    padding: 36px 20px;
    background: #f9fafb;
}

.alert {
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 12px;
    font-size: 13px;
    font-weight: 600;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.build-badge {
    display: inline-block;
    margin-top: 5px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.build-complete {
    background: #dcfce7;
    color: #166534;
}

.build-partial {
    background: #fef3c7;
    color: #92400e;
}

.build-missing {
    background: #fee2e2;
    color: #991b1b;
}

@media (max-width: 768px) {
    .inventory-header {
        padding: 20px;
    }

    .inventory-header h1 {
        font-size: 24px;
    }

    .filter-group input,
    .filter-group select {
        min-width: 135px;
    }
}
</style>

<div class="inventory-header">
    <h1>Tank Inventory and Regeneration Flow</h1>
    <p>Track complete tank assemblies, deployment state, and shop/pool movement.</p>
    <p>Assembly attributes: tank vessel, top manifold/head, internal distributor, threaded inlet, threaded outlet, and resin.</p>
    <p>Customer-owned tanks are tracked in Customer View.</p>
</div>

<?php if ($postError !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($postError) ?></div>
<?php endif; ?>
<?php if ($postSuccess !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($postSuccess) ?></div>
<?php endif; ?>

<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-label">Total Tanks</div>
        <div class="metric-value"><?= $metrics['total'] ?></div>
    </div>
    <div class="metric-card">
        <div class="metric-label">In Service</div>
        <div class="metric-value"><?= $metrics['in-service'] ?></div>
        <div class="metric-subtext">Active field units</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Shop Production</div>
        <div class="metric-value"><?= $metrics['shop-production'] ?></div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Shop Warehouse</div>
        <div class="metric-value"><?= $metrics['shop-warehouse'] ?></div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Maintenance</div>
        <div class="metric-value"><?= $metrics['maintenance'] ?></div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Rental Fleet</div>
        <div class="metric-value"><?= $metrics['rental'] ?></div>
    </div>
</div>

<div class="action-bar">
    <div class="filter-group">
        <input type="text" id="searchInput" placeholder="Search ID, type, resin..." onkeyup="filterEquipment()">

        <select id="workflowFilter" onchange="filterEquipment()">
            <option value="">All Workflow States</option>
            <option value="in-service">In Service</option>
            <option value="shop-production">Shop Production</option>
            <option value="shop-warehouse">Shop Warehouse</option>
            <option value="maintenance">Maintenance</option>
            <option value="other">Other</option>
        </select>

        <select id="ownershipFilter" onchange="filterEquipment()">
            <option value="">All Ownership</option>
            <option value="rental">Rental</option>
            <option value="customer-owned">Customer Owned</option>
        </select>

        <select id="statusFilter" onchange="filterEquipment()">
            <option value="">All Status</option>
            <option value="available">Available</option>
            <option value="in service">In Service</option>
            <option value="maintenance">Maintenance</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>

        <select id="buildFilter" onchange="filterEquipment()">
            <option value="">All Build States</option>
            <option value="complete">Build Complete</option>
            <option value="partial">Build Partial</option>
            <option value="missing">Build Missing</option>
        </select>
    </div>

    <a href="equipment_form.php" class="btn btn-primary">Add Tank</a>
</div>

<div class="inventory-table">
    <?php if (empty($equipment)): ?>
        <div class="no-data">
            <p>No equipment tracked yet.</p>
            <a href="equipment_form.php" class="btn btn-primary" style="margin-top: 10px; display: inline-block;">Add First Tank</a>
        </div>
    <?php else: ?>
        <div class="table-scroll">
            <table id="equipmentTable">
                <thead>
                <tr>
                    <th></th>
                    <th>Tank ID</th>
                    <th>Identity</th>
                    <th>Assembly + Resin</th>
                    <th>Ownership</th>
                    <th>Workflow</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($equipment as $item): ?>
                    <?php
                    $statusRaw = normalize_text($item['status'] ?? 'active');
                    $statusClass = str_replace(' ', '-', $statusRaw);
                    $workflowState = $item['workflow_state'];
                    $workflowClass = 'state-' . $workflowState;

                    $customerId = $item['customer_id'] ?? '';

                    $ownership = normalize_text($item['ownership'] ?? '');
                    $ownershipClass = in_array($ownership, ['rental', 'customer-owned', 'purchased'], true) ? $ownership : 'purchased';

                    $componentMap = $componentsByEquipment[$item['equipment_id']] ?? [];
                    $componentShort = [
                        'vessel' => 'V',
                        'head' => 'H',
                        'internal_distributor' => 'D',
                        'inlet_fitting' => 'IN',
                        'outlet_fitting' => 'OUT',
                        'resin' => 'R'
                    ];
                    $componentParts = [];
                    foreach ($componentShort as $slot => $shortLabel) {
                        if (!isset($componentMap[$slot]['item_id'])) {
                            continue;
                        }
                        $componentParts[] = $shortLabel . ': ' . $componentMap[$slot]['item_id'];
                    }
                    $componentPreview = implode(' | ', $componentParts);
                    $totalSlots = count($componentSlots);
                    $linkedSlots = count($componentParts);
                    $isBuildComplete = $linkedSlots === $totalSlots;
                    $isBuildMissing = $linkedSlots === 0;
                    $buildClass = $isBuildComplete ? 'build-complete' : ($isBuildMissing ? 'build-missing' : 'build-partial');
                    $buildLabel = $isBuildComplete ? 'Build Complete' : ($isBuildMissing ? 'Build Missing' : 'Build Partial');
                    $buildState = $isBuildComplete ? 'complete' : ($isBuildMissing ? 'missing' : 'partial');
                    $resinPartNumber = $componentMap['resin']['item_id'] ?? ($item['resin_type'] ?? 'N/A');
                    $resinQuantity = isset($componentMap['resin']['quantity_required']) ? rtrim(rtrim(number_format((float) $componentMap['resin']['quantity_required'], 3, '.', ''), '0'), '.') : '';
                    ?>
                    <tr class="tank-row-summary"
                        data-equipment-id="<?= htmlspecialchars($item['equipment_id']) ?>"
                        data-ownership="<?= htmlspecialchars($ownership) ?>"
                        data-status="<?= htmlspecialchars($statusRaw) ?>"
                        data-workflow="<?= htmlspecialchars($workflowState) ?>"
                        data-build-state="<?= htmlspecialchars($buildState) ?>"
                        data-build-completeness="<?= htmlspecialchars($linkedSlots . '/' . $totalSlots) ?>"
                        onclick="toggleEditPanel('<?= htmlspecialchars($item['equipment_id']) ?>', event)">
                        <td style="text-align:center; width:34px;">
                            <button type="button" class="expand-btn" onclick="toggleEditPanel('<?= htmlspecialchars($item['equipment_id']) ?>', event)">▼</button>
                        </td>
                        <td><strong><?= htmlspecialchars($item['equipment_id']) ?></strong></td>
                        <td class="inline-stack">
                            <div><strong><?= htmlspecialchars($item['equipment_type'] ?? 'Tank') ?></strong></div>
                            <div class="text-muted">Size: <?= htmlspecialchars($item['tank_size'] ?? 'N/A') ?></div>
                        </td>
                        <td class="inline-stack">
                            <div>Resin Part #: <?= htmlspecialchars($resinPartNumber) ?><?= $resinQuantity !== '' ? ' <span class="text-muted">Qty: ' . htmlspecialchars($resinQuantity) . '</span>' : '' ?></div>
                            <div class="text-muted">Model/PN: <?= htmlspecialchars($item['model_number'] ?? 'N/A') ?></div>
                            <div class="text-muted">Serial: <?= htmlspecialchars($item['serial_number'] ?? 'N/A') ?></div>
                            <div class="text-muted">Build: <?= htmlspecialchars($componentPreview !== '' ? $componentPreview : 'No component products linked') ?></div>
                            <span class="build-badge <?= htmlspecialchars($buildClass) ?>"><?= htmlspecialchars($buildLabel . ' (' . $linkedSlots . '/' . $totalSlots . ')') ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($ownershipClass) ?>">
                                <?= htmlspecialchars(ucwords(str_replace('-', ' ', $ownership ?: 'unknown'))) ?>
                            </span>
                        </td>
                        <td class="inline-stack">
                            <span class="state-badge <?= htmlspecialchars($workflowClass) ?>">
                                <?= htmlspecialchars(workflow_label($workflowState)) ?>
                            </span>
                            <div style="margin-top: 5px;">
                                <span class="status-badge status-<?= htmlspecialchars($statusClass) ?>">
                                    <?= htmlspecialchars($item['status'] ?? 'Active') ?>
                                </span>
                            </div>
                        </td>
                    </tr>

                    <tr class="edit-panel" id="edit-<?= htmlspecialchars($item['equipment_id']) ?>">
                        <td colspan="6">
                            <form method="POST" class="edit-panel-content">
                                <?php renderCSRFInput(); ?>
                                <input type="hidden" name="action" value="update_equipment">
                                <input type="hidden" name="equipment_id" value="<?= htmlspecialchars($item['equipment_id']) ?>">

                                <div class="edit-section-title">Tank Identity</div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Equipment Type</label>
                                        <input type="text" name="equipment_type" value="<?= htmlspecialchars($item['equipment_type'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Tank Size</label>
                                        <?php $tankSizeSelected = trim((string) ($item['tank_size'] ?? '')); ?>
                                        <select name="tank_size">
                                            <option value="">Select</option>
                                            <option value="1" <?= $tankSizeSelected === '1' ? 'selected' : '' ?>>1</option>
                                            <option value="2" <?= $tankSizeSelected === '2' ? 'selected' : '' ?>>2</option>
                                            <option value="3.5" <?= $tankSizeSelected === '3.5' ? 'selected' : '' ?>>3.5</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Ownership</label>
                                        <select name="ownership">
                                            <option value="">Select</option>
                                            <option value="rental" <?= $ownership === 'rental' ? 'selected' : '' ?>>Rental</option>
                                            <option value="customer-owned" <?= in_array($ownership, ['customer-owned', 'customer owned', 'purchased'], true) ? 'selected' : '' ?>>Customer Owned</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="edit-section-title">Assembly Tracking</div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Manufacturer</label>
                                        <input type="text" name="manufacturer" value="<?= htmlspecialchars($item['manufacturer'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Model / Primary PN</label>
                                        <input type="text" name="model_number" value="<?= htmlspecialchars($item['model_number'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Serial / Assembly Tag</label>
                                        <input type="text" name="serial_number" value="<?= htmlspecialchars($item['serial_number'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Regeneration ID</label>
                                        <input type="text" name="regeneration_id" value="<?= htmlspecialchars($item['regeneration_id'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="edit-section-title">Deployment and Lifecycle</div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Workflow Status</label>
                                        <select name="status">
                                            <option value="Active" <?= normalize_text($item['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="Available" <?= normalize_text($item['status'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option>
                                            <option value="In Service" <?= normalize_text($item['status'] ?? '') === 'in service' ? 'selected' : '' ?>>In Service</option>
                                            <option value="Maintenance" <?= normalize_text($item['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                            <option value="Inactive" <?= normalize_text($item['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="edit-section-title">Component Build (Products)</div>
                                <div class="form-grid">
                                    <?php foreach ($componentSlotsUi as $slot => $label): ?>
                                        <?php
                                        $selectedItem = $componentMap[$slot]['item_id'] ?? '';
                                        $selectedQty = $componentMap[$slot]['quantity_required'] ?? 1;
                                        ?>
                                        <div class="form-group">
                                            <label><?= htmlspecialchars($label) ?></label>
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
                                        <div class="form-group">
                                            <label><?= htmlspecialchars($label) ?> Qty</label>
                                            <input type="number" step="0.001" min="0.001" name="comp_qty_<?= htmlspecialchars($slot) ?>" value="<?= htmlspecialchars((string) $selectedQty) ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="edit-section-title">Resin From Pool</div>
                                <div class="form-grid">
                                    <?php
                                    $selectedResinItem = $componentMap['resin']['item_id'] ?? '';
                                    $selectedResinQty = isset($componentMap['resin']['quantity_required'])
                                        ? rtrim(rtrim(number_format((float) $componentMap['resin']['quantity_required'], 3, '.', ''), '0'), '.')
                                        : '1';
                                    if (!in_array($selectedResinQty, $resinQuantityOptions, true)) {
                                        $selectedResinQty = '1';
                                    }
                                    ?>
                                    <div class="form-group">
                                        <label>Resin</label>
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
                                    <div class="form-group">
                                        <label>Resin Qty (cuft)</label>
                                        <select name="comp_qty_resin">
                                            <?php foreach ($resinQuantityOptions as $qtyOption): ?>
                                                <option value="<?= htmlspecialchars($qtyOption) ?>" <?= $selectedResinQty === $qtyOption ? 'selected' : '' ?>><?= htmlspecialchars($qtyOption) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="edit-section-title">Procurement and Notes</div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Purchase Date</label>
                                        <input type="date" name="purchase_date" value="<?= htmlspecialchars($item['purchase_date'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Purchase Value ($)</label>
                                        <input type="number" step="0.01" name="purchase_value" value="<?= htmlspecialchars($item['purchase_value'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Purchase Order</label>
                                        <input type="text" name="purchase_order" value="<?= htmlspecialchars($item['purchase_order'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Warranty Expiry</label>
                                        <input type="date" name="warranty_expiry" value="<?= htmlspecialchars($item['warranty_expiry'] ?? '') ?>">
                                    </div>
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <label>Notes (include component part numbers for: vessel, head, internal distributor, inlet fitting, outlet fitting)</label>
                                        <textarea name="notes" style="min-height: 84px;"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-save">Save Changes</button>
                                    <button type="submit" class="btn" name="duplicate_build_id" value="<?= htmlspecialchars($item['equipment_id']) ?>" onclick="return confirm('Duplicate this build into a new tank record and consume the same components from inventory?');">Duplicate Build</button>
                                    <button type="submit" class="btn action-btn-delete" name="delete_id" value="<?= htmlspecialchars($item['equipment_id']) ?>" onclick="return confirm('Delete this tank record?');">Delete</button>
                                    <button type="button" class="btn btn-cancel" onclick="toggleEditPanel('<?= htmlspecialchars($item['equipment_id']) ?>', event)">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;gap:10px;">
                <div class="text-muted">Page <?= (int) $page ?> of <?= (int) $totalPages ?></div>
                <div style="display:flex;gap:8px;">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-cancel" href="equipment_list.php?page=<?= (int) ($page - 1) ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-primary" href="equipment_list.php?page=<?= (int) ($page + 1) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function toggleEditPanel(equipmentId, event) {
    if (event) {
        event.stopPropagation();
    }

    const panel = document.getElementById('edit-' + equipmentId);
    if (!panel) {
        return;
    }

    panel.classList.toggle('visible');

    const row = document.querySelector('tr[data-equipment-id="' + equipmentId + '"]');
    if (!row) {
        return;
    }

    const btn = row.querySelector('.expand-btn');
    if (btn) {
        btn.textContent = panel.classList.contains('visible') ? '▲' : '▼';
    }
}

function filterEquipment() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const workflowFilter = document.getElementById('workflowFilter').value.toLowerCase();
    const ownershipFilter = document.getElementById('ownershipFilter').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const buildFilter = document.getElementById('buildFilter').value.toLowerCase();

    const table = document.getElementById('equipmentTable');
    if (!table) {
        return;
    }

    const rows = table.querySelectorAll('tbody tr.tank-row-summary');

    rows.forEach(function(row) {
        const workflow = (row.getAttribute('data-workflow') || '').toLowerCase();
        const ownership = (row.getAttribute('data-ownership') || '').toLowerCase();
        const status = (row.getAttribute('data-status') || '').toLowerCase();
        const buildState = (row.getAttribute('data-build-state') || '').toLowerCase();
        const text = row.textContent.toLowerCase();

        let show = true;

        if (workflowFilter && workflow !== workflowFilter) {
            show = false;
        }
        if (ownershipFilter && ownership !== ownershipFilter) {
            show = false;
        }
        if (statusFilter && status !== statusFilter) {
            show = false;
        }
        if (buildFilter && buildState !== buildFilter) {
            show = false;
        }
        if (searchInput && !text.includes(searchInput)) {
            show = false;
        }

        row.style.display = show ? '' : 'none';

        const equipmentId = row.getAttribute('data-equipment-id');
        const panel = document.getElementById('edit-' + equipmentId);
        if (panel) {
            panel.style.display = show ? '' : 'none';
            if (!show) {
                panel.classList.remove('visible');
                const btn = row.querySelector('.expand-btn');
                if (btn) {
                    btn.textContent = '▼';
                }
            }
        }
    });
}
</script>

<?php include_once __DIR__ . '/layout_end.php'; ?>
