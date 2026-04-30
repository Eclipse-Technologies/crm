<?php
$pageTitle = 'Inventory Ledger Parity';
include_once __DIR__ . '/layout_start.php';
require_once 'db_mysql.php';

function table_exists(mysqli $conn, string $tableName): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    if ($safe === '') {
        return false;
    }
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safe) . "'");
    if (!$result) {
        return false;
    }
    $exists = $result->num_rows > 0;
    $result->close();
    return $exists;
}

function scalar_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    $result->close();
    return isset($row['c']) ? (int) $row['c'] : 0;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$limit = max(25, min(500, $limit));

$conn = get_mysql_connection();
$hasMovements = table_exists($conn, 'inventory_movements');
$hasCanonical = table_exists($conn, 'inventory_transactions');

$movementCount = $hasMovements ? scalar_count($conn, 'SELECT COUNT(*) AS c FROM inventory_movements') : 0;
$canonicalCount = $hasCanonical ? scalar_count($conn, 'SELECT COUNT(*) AS c FROM inventory_transactions') : 0;
$canonicalLinkedCount = $hasCanonical ? scalar_count($conn, "SELECT COUNT(*) AS c FROM inventory_transactions WHERE source_ref LIKE 'movement:%'") : 0;

$missingCanonical = [];
$mismatchRows = [];
$orphanCanonical = [];

if ($hasMovements && $hasCanonical) {
    $sqlMissing = 'SELECT m.movement_id, m.item_id, m.old_quantity, m.new_quantity, m.delta_quantity, m.change_mode, m.created_at FROM inventory_movements m LEFT JOIN inventory_transactions t ON t.source_ref = CONCAT("movement:", m.movement_id) WHERE t.transaction_id IS NULL ORDER BY m.created_at DESC LIMIT ?';
    $stmtMissing = $conn->prepare($sqlMissing);
    if ($stmtMissing) {
        $stmtMissing->bind_param('i', $limit);
        if ($stmtMissing->execute()) {
            $res = $stmtMissing->get_result();
            while ($row = $res->fetch_assoc()) {
                $missingCanonical[] = $row;
            }
        }
        $stmtMissing->close();
    }

    $sqlMismatch = 'SELECT m.movement_id, m.item_id, m.old_quantity, m.new_quantity, m.delta_quantity, m.change_mode, t.transaction_id, t.quantity_before, t.quantity_after, t.quantity_delta, t.transaction_type, m.created_at FROM inventory_movements m INNER JOIN inventory_transactions t ON t.source_ref = CONCAT("movement:", m.movement_id) WHERE ABS(COALESCE(m.old_quantity, 0) - COALESCE(t.quantity_before, 0)) > 0.0001 OR ABS(COALESCE(m.new_quantity, 0) - COALESCE(t.quantity_after, 0)) > 0.0001 OR ABS(COALESCE(m.delta_quantity, 0) - COALESCE(t.quantity_delta, 0)) > 0.0001 ORDER BY m.created_at DESC LIMIT ?';
    $stmtMismatch = $conn->prepare($sqlMismatch);
    if ($stmtMismatch) {
        $stmtMismatch->bind_param('i', $limit);
        if ($stmtMismatch->execute()) {
            $res = $stmtMismatch->get_result();
            while ($row = $res->fetch_assoc()) {
                $mismatchRows[] = $row;
            }
        }
        $stmtMismatch->close();
    }

    $sqlOrphan = "SELECT t.transaction_id, t.item_id, t.transaction_type, t.quantity_before, t.quantity_after, t.quantity_delta, t.actor_username, t.occurred_at, t.source_ref FROM inventory_transactions t LEFT JOIN inventory_movements m ON t.source_ref = CONCAT('movement:', m.movement_id) WHERE t.source_ref LIKE 'movement:%' AND m.movement_id IS NULL ORDER BY t.occurred_at DESC LIMIT ?";
    $stmtOrphan = $conn->prepare($sqlOrphan);
    if ($stmtOrphan) {
        $stmtOrphan->bind_param('i', $limit);
        if ($stmtOrphan->execute()) {
            $res = $stmtOrphan->get_result();
            while ($row = $res->fetch_assoc()) {
                $orphanCanonical[] = $row;
            }
        }
        $stmtOrphan->close();
    }
}

$conn->close();

$status = 'ok';
if (!$hasMovements || !$hasCanonical) {
    $status = 'setup_incomplete';
} elseif (!empty($missingCanonical) || !empty($mismatchRows)) {
    $status = 'attention';
}
?>

<div class="container" style="max-width: 1250px; margin: 24px auto;">
  <div class="d-flex justify-content-between align-items-center mb-3" style="gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">Inventory Ledger Parity</h2>
      <div style="color:#64748b; font-size:0.9rem;">Compare legacy inventory_movements against canonical inventory_transactions</div>
    </div>
    <div class="d-flex gap-2">
      <a href="inventory_movement_history.php" class="btn btn-outline-secondary btn-sm">Back to History</a>
      <a href="inventory_list.php" class="btn btn-outline-secondary btn-sm">Back to Inventory</a>
    </div>
  </div>

  <form method="get" class="card p-3 mb-3" style="border:1px solid #dfe5ef; border-radius:12px;">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label for="limit" class="form-label" style="margin-bottom:4px;">Rows per section</label>
        <select id="limit" name="limit" class="form-select">
          <?php foreach ([25, 50, 100, 250, 500] as $opt): ?>
            <option value="<?= (int) $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= (int) $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-9 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Refresh</button>
      </div>
    </div>
  </form>

  <?php if ($status === 'setup_incomplete'): ?>
    <div class="alert alert-warning">One or both ledger tables are missing. Ensure both <strong>inventory_movements</strong> and <strong>inventory_transactions</strong> exist.</div>
  <?php elseif ($status === 'attention'): ?>
    <div class="alert alert-danger">Parity issues detected. Review missing/mismatch sections below before retiring fallback reads.</div>
  <?php else: ?>
    <div class="alert alert-success">Parity looks healthy for the current checks.</div>
  <?php endif; ?>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card p-3" style="border:1px solid #dfe5ef; border-radius:12px;">
        <div style="font-size:0.85rem; color:#64748b;">Legacy rows</div>
        <div style="font-size:1.4rem; font-weight:700;"><?= (int) $movementCount ?></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3" style="border:1px solid #dfe5ef; border-radius:12px;">
        <div style="font-size:0.85rem; color:#64748b;">Canonical rows</div>
        <div style="font-size:1.4rem; font-weight:700;"><?= (int) $canonicalCount ?></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3" style="border:1px solid #dfe5ef; border-radius:12px;">
        <div style="font-size:0.85rem; color:#64748b;">Canonical linked to legacy source_ref</div>
        <div style="font-size:1.4rem; font-weight:700;"><?= (int) $canonicalLinkedCount ?></div>
      </div>
    </div>
  </div>

  <div class="card mb-3" style="border:1px solid #dfe5ef; border-radius:12px; overflow:hidden;">
    <div class="card-header" style="background:#f8fafc;">Missing in Canonical (latest <?= (int) $limit ?>)</div>
    <div style="overflow:auto;">
      <table class="table table-sm" style="margin:0; min-width:920px;">
        <thead>
          <tr>
            <th>Movement ID</th>
            <th>Created</th>
            <th>Item</th>
            <th>Mode</th>
            <th>Old</th>
            <th>New</th>
            <th>Delta</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($missingCanonical)): ?>
            <tr><td colspan="7" style="color:#64748b;">No missing records found in this slice.</td></tr>
          <?php else: ?>
            <?php foreach ($missingCanonical as $row): ?>
              <tr>
                <td><?= (int) ($row['movement_id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['item_id'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['change_mode'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['old_quantity'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['new_quantity'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['delta_quantity'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card mb-3" style="border:1px solid #dfe5ef; border-radius:12px; overflow:hidden;">
    <div class="card-header" style="background:#f8fafc;">Value Mismatches (latest <?= (int) $limit ?>)</div>
    <div style="overflow:auto;">
      <table class="table table-sm" style="margin:0; min-width:1080px;">
        <thead>
          <tr>
            <th>Movement ID</th>
            <th>Canonical ID</th>
            <th>Item</th>
            <th>Legacy Mode</th>
            <th>Canonical Type</th>
            <th>Legacy Old/New/Delta</th>
            <th>Canonical Old/New/Delta</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($mismatchRows)): ?>
            <tr><td colspan="8" style="color:#64748b;">No value mismatches found in this slice.</td></tr>
          <?php else: ?>
            <?php foreach ($mismatchRows as $row): ?>
              <tr>
                <td><?= (int) ($row['movement_id'] ?? 0) ?></td>
                <td><?= (int) ($row['transaction_id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($row['item_id'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['change_mode'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['transaction_type'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) (($row['old_quantity'] ?? '') . ' / ' . ($row['new_quantity'] ?? '') . ' / ' . ($row['delta_quantity'] ?? ''))) ?></td>
                <td><?= htmlspecialchars((string) (($row['quantity_before'] ?? '') . ' / ' . ($row['quantity_after'] ?? '') . ' / ' . ($row['quantity_delta'] ?? ''))) ?></td>
                <td><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card" style="border:1px solid #dfe5ef; border-radius:12px; overflow:hidden;">
    <div class="card-header" style="background:#f8fafc;">Canonical Orphans by source_ref (latest <?= (int) $limit ?>)</div>
    <div style="overflow:auto;">
      <table class="table table-sm" style="margin:0; min-width:980px;">
        <thead>
          <tr>
            <th>Canonical ID</th>
            <th>Occurred</th>
            <th>Item</th>
            <th>Type</th>
            <th>Old/New/Delta</th>
            <th>Actor</th>
            <th>Source Ref</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orphanCanonical)): ?>
            <tr><td colspan="7" style="color:#64748b;">No orphan canonical rows found in this slice.</td></tr>
          <?php else: ?>
            <?php foreach ($orphanCanonical as $row): ?>
              <tr>
                <td><?= (int) ($row['transaction_id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($row['occurred_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['item_id'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['transaction_type'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) (($row['quantity_before'] ?? '') . ' / ' . ($row['quantity_after'] ?? '') . ' / ' . ($row['quantity_delta'] ?? ''))) ?></td>
                <td><?= htmlspecialchars((string) ($row['actor_username'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['source_ref'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/layout_end.php'; ?>
