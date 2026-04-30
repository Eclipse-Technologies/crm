<?php
$pageTitle = 'Inventory Movement History';
include_once __DIR__ . '/layout_start.php';
require_once 'db_mysql.php';

function ensure_inventory_movements_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS inventory_movements (
        movement_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        item_id VARCHAR(100) NOT NULL,
        old_quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
        new_quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
        delta_quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
        change_mode VARCHAR(16) NOT NULL DEFAULT 'set',
        reason VARCHAR(255) NULL,
        changed_by VARCHAR(120) NULL,
        is_undo TINYINT(1) NOT NULL DEFAULT 0,
        undone_at DATETIME NULL,
        undone_by_movement_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item_created (item_id, created_at),
        INDEX idx_undone_by (undone_by_movement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

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

  function table_row_count(mysqli $conn, string $tableName): int
  {
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    if ($safe === '') {
      return 0;
    }
    $result = $conn->query('SELECT COUNT(*) AS c FROM ' . $safe);
    if (!$result) {
      return 0;
    }
    $row = $result->fetch_assoc();
    $result->close();
    return isset($row['c']) ? (int) $row['c'] : 0;
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

$conn = get_mysql_connection();
ensure_inventory_movements_table($conn);
$hasMovementsTable = table_exists($conn, 'inventory_movements');
$hasCanonicalTable = table_exists($conn, 'inventory_transactions');
$useCanonical = $hasCanonicalTable && table_row_count($conn, 'inventory_transactions') > 0;

$parityMissingCount = 0;
$parityMismatchCount = 0;
$parityOrphanCount = 0;
$parityIssueCount = 0;
$parityStatus = 'n/a';
$parityClass = 'text-bg-secondary';

if ($hasMovementsTable && $hasCanonicalTable) {
  $parityMissingCount = scalar_count($conn, "SELECT COUNT(*) AS c FROM inventory_movements m LEFT JOIN inventory_transactions t ON t.source_ref = CONCAT('movement:', m.movement_id) WHERE t.transaction_id IS NULL");
  $parityMismatchCount = scalar_count($conn, "SELECT COUNT(*) AS c FROM inventory_movements m INNER JOIN inventory_transactions t ON t.source_ref = CONCAT('movement:', m.movement_id) WHERE ABS(COALESCE(m.old_quantity, 0) - COALESCE(t.quantity_before, 0)) > 0.0001 OR ABS(COALESCE(m.new_quantity, 0) - COALESCE(t.quantity_after, 0)) > 0.0001 OR ABS(COALESCE(m.delta_quantity, 0) - COALESCE(t.quantity_delta, 0)) > 0.0001");
  $parityOrphanCount = scalar_count($conn, "SELECT COUNT(*) AS c FROM inventory_transactions t LEFT JOIN inventory_movements m ON t.source_ref = CONCAT('movement:', m.movement_id) WHERE t.source_ref LIKE 'movement:%' AND m.movement_id IS NULL");
  $parityIssueCount = $parityMissingCount + $parityMismatchCount + $parityOrphanCount;

  if ($parityIssueCount === 0) {
    $parityStatus = 'green';
    $parityClass = 'text-bg-success';
  } elseif ($parityIssueCount <= 25) {
    $parityStatus = 'yellow';
    $parityClass = 'text-bg-warning';
  } else {
    $parityStatus = 'red';
    $parityClass = 'text-bg-danger';
  }
}

$itemIdFilter = trim((string) ($_GET['item_id'] ?? ''));
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$limit = max(25, min(500, $limit));
$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
$sortBy = trim((string) ($_GET['sort'] ?? 'created_at'));
$sortDir = strtolower(trim((string) ($_GET['dir'] ?? 'desc')));

if ($fromDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
  $fromDate = '';
}
if ($toDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
  $toDate = '';
}

$allowedSortMap = $useCanonical
  ? [
    'movement_id' => 't.transaction_id',
    'created_at' => 't.occurred_at',
    'item_id' => 't.item_id',
    'item_name' => 'i.item_name',
    'old_quantity' => 't.quantity_before',
    'new_quantity' => 't.quantity_after',
    'delta_quantity' => 't.quantity_delta',
    'change_mode' => 't.transaction_type',
    'changed_by' => 't.actor_username',
    'reason' => 't.reason_text',
    'undo_status' => 'rev.occurred_at',
  ]
  : [
    'movement_id' => 'm.movement_id',
    'created_at' => 'm.created_at',
    'item_id' => 'm.item_id',
    'item_name' => 'i.item_name',
    'old_quantity' => 'm.old_quantity',
    'new_quantity' => 'm.new_quantity',
    'delta_quantity' => 'm.delta_quantity',
    'change_mode' => 'm.change_mode',
    'changed_by' => 'm.changed_by',
    'reason' => 'm.reason',
    'undo_status' => 'm.undone_at',
  ];
if (!isset($allowedSortMap[$sortBy])) {
  $sortBy = 'created_at';
}
if ($sortDir !== 'asc' && $sortDir !== 'desc') {
  $sortDir = 'desc';
}
$exportUrl = 'inventory_movement_export.php?' . http_build_query([
  'item_id' => $itemIdFilter,
  'limit' => $limit,
  'from_date' => $fromDate,
  'to_date' => $toDate,
  'sort' => $sortBy,
  'dir' => $sortDir,
]);

$sortUrl = function (string $column) use ($itemIdFilter, $limit, $fromDate, $toDate, $sortBy, $sortDir): string {
  $nextDir = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
  return 'inventory_movement_history.php?' . http_build_query([
    'item_id' => $itemIdFilter,
    'limit' => $limit,
    'from_date' => $fromDate,
    'to_date' => $toDate,
    'sort' => $column,
    'dir' => $nextDir,
  ]);
};

$sortArrow = function (string $column) use ($sortBy, $sortDir): string {
  if ($sortBy !== $column) {
    return '';
  }
  return $sortDir === 'asc' ? ' ▲' : ' ▼';
};

$sql = $useCanonical
  ? 'SELECT t.transaction_id AS movement_id, t.item_id, t.quantity_before AS old_quantity, t.quantity_after AS new_quantity, t.quantity_delta AS delta_quantity, CASE t.transaction_type WHEN "adjust_inc" THEN "inc" WHEN "adjust_dec" THEN "dec" WHEN "adjust_set" THEN "set" WHEN "undo_action" THEN "undo" ELSE t.transaction_type END AS change_mode, t.reason_text AS reason, t.actor_username AS changed_by, t.is_reversal AS is_undo, rev.occurred_at AS undone_at, rev.transaction_id AS undone_by_movement_id, t.occurred_at AS created_at, i.item_name FROM inventory_transactions t LEFT JOIN inventory i ON i.item_id COLLATE utf8mb4_unicode_ci = t.item_id COLLATE utf8mb4_unicode_ci LEFT JOIN inventory_transactions rev ON rev.parent_transaction_id = t.transaction_id AND rev.is_reversal = 1'
  : 'SELECT m.movement_id, m.item_id, m.old_quantity, m.new_quantity, m.delta_quantity, m.change_mode, m.reason, m.changed_by, m.is_undo, m.undone_at, m.undone_by_movement_id, m.created_at, i.item_name FROM inventory_movements m LEFT JOIN inventory i ON i.item_id COLLATE utf8mb4_unicode_ci = m.item_id COLLATE utf8mb4_unicode_ci';
$params = [];
$types = '';
$conditions = [];
if ($itemIdFilter !== '') {
  $conditions[] = ($useCanonical ? 't.item_id = ?' : 'm.item_id = ?');
  $types .= 's';
  $params[] = $itemIdFilter;
}
if ($fromDate !== '') {
  $conditions[] = ($useCanonical ? 'DATE(t.occurred_at) >= ?' : 'DATE(m.created_at) >= ?');
  $types .= 's';
  $params[] = $fromDate;
}
if ($toDate !== '') {
  $conditions[] = ($useCanonical ? 'DATE(t.occurred_at) <= ?' : 'DATE(m.created_at) <= ?');
  $types .= 's';
  $params[] = $toDate;
}
if (!empty($conditions)) {
  $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY ' . $allowedSortMap[$sortBy] . ' ' . strtoupper($sortDir) . ' LIMIT ?';
$types .= 'i';
$params[] = $limit;

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
}
$conn->close();
?>

<div class="container" style="max-width:1200px; margin: 24px auto;">
  <div class="d-flex justify-content-between align-items-center mb-3" style="gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">Inventory Movement History</h2>
      <div style="color:#64748b; font-size:0.9rem;">Audit log of quantity changes and undo actions (source: <?= $useCanonical ? 'inventory_transactions' : 'inventory_movements' ?>)</div>
      <div style="margin-top:6px;">
        <span class="badge <?= htmlspecialchars($parityClass) ?>">Parity <?= htmlspecialchars(strtoupper($parityStatus)) ?></span>
        <?php if ($hasMovementsTable && $hasCanonicalTable): ?>
          <span style="font-size:0.82rem; color:#64748b; margin-left:6px;">issues: <?= (int) $parityIssueCount ?> (missing <?= (int) $parityMissingCount ?>, mismatch <?= (int) $parityMismatchCount ?>, orphan <?= (int) $parityOrphanCount ?>)</span>
        <?php else: ?>
          <span style="font-size:0.82rem; color:#64748b; margin-left:6px;">parity requires both ledger tables</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="inventory_ledger_parity.php" class="btn btn-outline-primary btn-sm">Ledger Parity</a>
      <a href="inventory_list.php" class="btn btn-outline-secondary btn-sm">Back to Inventory</a>
    </div>
  </div>

  <form method="get" class="card p-3 mb-3" style="border:1px solid #dfe5ef; border-radius:12px;">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label for="item_id" class="form-label" style="margin-bottom:4px;">Item ID</label>
        <input id="item_id" type="text" name="item_id" class="form-control" value="<?= htmlspecialchars($itemIdFilter) ?>" placeholder="Optional">
      </div>
      <div class="col-md-2">
        <label for="limit" class="form-label" style="margin-bottom:4px;">Rows</label>
        <select id="limit" name="limit" class="form-select">
          <?php foreach ([25, 50, 100, 250, 500] as $opt): ?>
            <option value="<?= (int) $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= (int) $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label for="from_date" class="form-label" style="margin-bottom:4px;">From</label>
        <input id="from_date" type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>">
      </div>
      <div class="col-md-2">
        <label for="to_date" class="form-label" style="margin-bottom:4px;">To</label>
        <input id="to_date" type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Apply</button>
        <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-outline-primary">Export CSV</a>
        <a href="inventory_movement_history.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </div>
  </form>

  <div class="card" style="border:1px solid #dfe5ef; border-radius:12px; overflow:hidden;">
    <?php if (empty($rows)): ?>
      <div style="padding:18px; color:#6b7280;">No movement records found.</div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="table table-sm" style="margin:0; min-width:980px;">
          <thead style="background:#f7f9fc;">
            <tr>
              <th><a href="<?= htmlspecialchars($sortUrl('movement_id')) ?>">ID<?= htmlspecialchars($sortArrow('movement_id')) ?></a></th>
              <th><a href="<?= htmlspecialchars($sortUrl('created_at')) ?>">When<?= htmlspecialchars($sortArrow('created_at')) ?></a></th>
              <th><a href="<?= htmlspecialchars($sortUrl('item_name')) ?>">Item<?= htmlspecialchars($sortArrow('item_name')) ?></a></th>
              <th><a href="<?= htmlspecialchars($sortUrl('old_quantity')) ?>">Old<?= htmlspecialchars($sortArrow('old_quantity')) ?></a></th>
              <th><a href="<?= htmlspecialchars($sortUrl('new_quantity')) ?>">New<?= htmlspecialchars($sortArrow('new_quantity')) ?></a></th>
              <th><a href="<?= htmlspecialchars($sortUrl('delta_quantity')) ?>">Delta<?= htmlspecialchars($sortArrow('delta_quantity')) ?></a></th>
              <th><a href="<?= htmlspecialchars($sortUrl('change_mode')) ?>">Mode<?= htmlspecialchars($sortArrow('change_mode')) ?></a></th>
              <th><a href="<?= htmlspecialchars($sortUrl('changed_by')) ?>">By<?= htmlspecialchars($sortArrow('changed_by')) ?></a></th>
              <th><a href="<?= htmlspecialchars($sortUrl('reason')) ?>">Reason<?= htmlspecialchars($sortArrow('reason')) ?></a></th>
              <th><a href="<?= htmlspecialchars($sortUrl('undo_status')) ?>">Undo Status<?= htmlspecialchars($sortArrow('undo_status')) ?></a></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php
                $delta = is_numeric($row['delta_quantity'] ?? null) ? (float) $row['delta_quantity'] : 0.0;
                $deltaText = $delta > 0 ? '+' . $delta : (string) $delta;
                $wasUndone = !empty($row['undone_at']);
              ?>
              <tr>
                <td><?= (int) ($row['movement_id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($row['created_at'] ?? '-')) ?></td>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars((string) ($row['item_name'] ?? $row['item_id'] ?? '-')) ?></div>
                  <div style="font-size:0.85rem; color:#64748b;"><?= htmlspecialchars((string) ($row['item_id'] ?? '-')) ?></div>
                </td>
                <td><?= htmlspecialchars((string) ($row['old_quantity'] ?? '0')) ?></td>
                <td><?= htmlspecialchars((string) ($row['new_quantity'] ?? '0')) ?></td>
                <td><?= htmlspecialchars($deltaText) ?></td>
                <td><?= htmlspecialchars((string) ($row['change_mode'] ?? '-')) ?></td>
                <td><?= htmlspecialchars((string) ($row['changed_by'] ?? '-')) ?></td>
                <td><?= htmlspecialchars((string) ($row['reason'] ?? '')) ?></td>
                <td>
                  <?php if (!empty($row['is_undo'])): ?>
                    <span class="badge text-bg-warning">Undo Action</span>
                  <?php elseif ($wasUndone): ?>
                    <span class="badge text-bg-secondary">Undone</span>
                  <?php else: ?>
                    <span class="badge text-bg-success">Active</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include_once __DIR__ . '/layout_end.php'; ?>
