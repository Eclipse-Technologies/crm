<?php
require_once 'db_mysql.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/request_guard.php';
require_once __DIR__ . '/inventory_tx_helper.php';

$schema = require __DIR__ . '/equipment_schema.php';

function ensure_equipment_components_table_view(mysqli $conn)
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

function fetch_component_rows_view(mysqli $conn, string $equipmentId): array
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

function aggregate_component_qty_view(array $rows): array
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

// Handle delete POST before output so redirects are always safe.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_post_with_csrf();

    $deleteId = trim((string) ($_POST['delete_id'] ?? ''));
    if ($deleteId !== '') {
        $conn = get_mysql_connection();
        ensure_equipment_components_table_view($conn);
        ensure_inventory_transactions_table($conn);
        $conn->begin_transaction();
        try {
            $existingRows = fetch_component_rows_view($conn, $deleteId);
            foreach (aggregate_component_qty_view($existingRows) as $itemId => $qtyToReturn) {
                inventory_tx_apply_delta_with_audit($conn, $itemId, (float) $qtyToReturn, [
                    'entity_type' => 'equipment',
                    'entity_id' => $deleteId,
                    'source_type' => 'equipment',
                    'source_ref' => $deleteId,
                    'reason_code' => 'equipment_component_change',
                    'reason_text' => 'equipment_view delete return components',
                ]);
            }

            $stmtCompDelete = $conn->prepare('DELETE FROM equipment_components WHERE equipment_id = ?');
            $stmtCompDelete->bind_param('s', $deleteId);
            $stmtCompDelete->execute();
            $stmtCompDelete->close();

            $stmtDelete = $conn->prepare('DELETE FROM equipment WHERE equipment_id = ?');
            $stmtDelete->bind_param('s', $deleteId);
            $stmtDelete->execute();
            $stmtDelete->close();

            $conn->commit();
            $conn->close();
            header('Location: equipment_list.php?deleted=1');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $conn->close();
            header('Location: equipment_list.php?deleted=0');
            exit;
        }
    }

    header('Location: equipment_list.php?deleted=0');
    exit;
}

// Get equipment ID from query string
$equipment_id = $_GET['id'] ?? '';
$equipment = null;
if ($equipment_id !== '') {
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function ($f) { return '`' . $f . '`'; }, $schema));
    $stmt = $conn->prepare("SELECT $fields FROM equipment WHERE equipment_id = ?");
    $stmt->bind_param('s', $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $equipment = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    $conn->close();
}

require_once 'layout_start.php';
?>
<div class="container">
  <h2>Equipment Details</h2>
  <?php if (!$equipment): ?>
    <div style="color:red;">Equipment not found.</div>
  <?php else: ?>
    <table class="table-grid">
      <tbody>
        <?php foreach ($schema as $field): ?>
          <tr>
            <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?></th>
            <td><?= htmlspecialchars($equipment[$field] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this equipment? This action cannot be undone.');" style="margin-top:20px;">
      <?php renderCSRFInput(); ?>
      <input type="hidden" name="delete_id" value="<?= htmlspecialchars($equipment_id) ?>">
      <button type="submit" style="background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:4px;cursor:pointer;">Delete Equipment</button>
    </form>
    <a href="equipment_list.php">&larr; Back to Equipment List</a>
  <?php endif; ?>
</div>
