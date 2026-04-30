<?php
$pageTitle = 'Inventory';
include_once __DIR__ . '/layout_start.php';
require_once 'inventory_mysql.php';
require_once 'db_mysql.php';

function fetch_last_inventory_movements(): array
{
  $map = [];
  $conn = get_mysql_connection();
  $tableCheck = $conn->query("SHOW TABLES LIKE 'inventory_movements'");
  if (!$tableCheck || $tableCheck->num_rows === 0) {
    if ($tableCheck) {
      $tableCheck->close();
    }
    $conn->close();
    return $map;
  }
  $tableCheck->close();

  $sql = "SELECT m.item_id, m.changed_by, m.delta_quantity, m.created_at, m.change_mode, m.is_undo
          FROM inventory_movements m
          INNER JOIN (
            SELECT item_id, MAX(movement_id) AS max_movement_id
            FROM inventory_movements
            GROUP BY item_id
          ) latest ON latest.max_movement_id = m.movement_id";
  $result = $conn->query($sql);
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $itemId = (string) ($row['item_id'] ?? '');
      if ($itemId !== '') {
        $map[$itemId] = $row;
      }
    }
    $result->close();
  }
  $conn->close();

  return $map;
}

$schema = require __DIR__ . '/inventory_schema.php';
$items = fetch_inventory_mysql($schema);
$lastMovementByItem = fetch_last_inventory_movements();

$query = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$sortBy = trim((string) ($_GET['sort'] ?? 'item_name'));
$sortDir = strtolower(trim((string) ($_GET['dir'] ?? 'asc')));
$pageSize = isset($_GET['page_size']) ? (int) $_GET['page_size'] : 25;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$notice = trim((string) ($_GET['notice'] ?? ''));
$undoMovementId = isset($_GET['undo_id']) ? (int) $_GET['undo_id'] : 0;

$allowedSort = ['item_id', 'item_name', 'category', 'supplier_name', 'quantity_in_stock', 'status', 'description', 'updated_at'];
if (!in_array($sortBy, $allowedSort, true)) {
  $sortBy = 'item_name';
}
if ($sortDir !== 'asc' && $sortDir !== 'desc') {
  $sortDir = 'asc';
}

$allowedPageSizes = [25, 50, 100];
if (!in_array($pageSize, $allowedPageSizes, true)) {
  $pageSize = 25;
}
if ($page < 1) {
  $page = 1;
}

$filtered = array_values(array_filter($items, function (array $item) use ($query, $statusFilter): bool {
    $status = trim((string) ($item['status'] ?? ''));
    if ($statusFilter !== '' && strcasecmp($status, $statusFilter) !== 0) {
        return false;
    }

    if ($query === '') {
        return true;
    }

    $needle = strtolower($query);
    $haystacks = [
        (string) ($item['item_id'] ?? ''),
        (string) ($item['item_name'] ?? ''),
        (string) ($item['category'] ?? ''),
        (string) ($item['brand'] ?? ''),
        (string) ($item['model'] ?? ''),
        (string) ($item['supplier_name'] ?? ''),
        (string) ($item['supplier_id'] ?? ''),
        (string) ($item['status'] ?? ''),
    ];

    foreach ($haystacks as $text) {
        if (strpos(strtolower($text), $needle) !== false) {
            return true;
        }
    }

    return false;
}));

usort($filtered, function (array $a, array $b) use ($sortBy, $sortDir): int {
  if ($sortBy === 'quantity_in_stock') {
    $left = is_numeric($a['quantity_in_stock'] ?? null) ? (float) $a['quantity_in_stock'] : 0.0;
    $right = is_numeric($b['quantity_in_stock'] ?? null) ? (float) $b['quantity_in_stock'] : 0.0;
    $cmp = $left <=> $right;
    return $sortDir === 'desc' ? -$cmp : $cmp;
  }

  $leftText = (string) ($a[$sortBy] ?? '');
  $rightText = (string) ($b[$sortBy] ?? '');
  $cmp = strcasecmp($leftText, $rightText);
  return $sortDir === 'desc' ? -$cmp : $cmp;
});

$filteredCount = count($filtered);
$totalPages = max(1, (int) ceil($filteredCount / $pageSize));
if ($page > $totalPages) {
  $page = $totalPages;
}
$offset = ($page - 1) * $pageSize;
$pagedItems = array_slice($filtered, $offset, $pageSize);

$baseQuery = [
  'q' => $query,
  'status' => $statusFilter,
  'sort' => $sortBy,
  'dir' => $sortDir,
  'page_size' => $pageSize,
];

$buildUrl = function (array $overrides) use ($baseQuery): string {
  return 'inventory_list.php?' . http_build_query(array_merge($baseQuery, $overrides));
};

$buildSortUrl = function (string $column) use ($sortBy, $sortDir, $buildUrl): string {
  $nextDir = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
  return $buildUrl([
    'sort' => $column,
    'dir' => $nextDir,
    'page' => 1,
  ]);
};

$buildExportUrl = 'inventory_export.php?' . http_build_query([
  'q' => $query,
  'status' => $statusFilter,
  'sort' => $sortBy,
  'dir' => $sortDir,
]);

$returnTo = 'inventory_list.php?' . http_build_query([
  'q' => $query,
  'status' => $statusFilter,
  'sort' => $sortBy,
  'dir' => $sortDir,
  'page_size' => $pageSize,
  'page' => $page,
]);

$statusOptions = [];
foreach ($items as $item) {
    $status = trim((string) ($item['status'] ?? ''));
    if ($status !== '') {
        $statusOptions[$status] = true;
    }
}
$statusOptions = array_keys($statusOptions);
sort($statusOptions);

$totalItems = count($items);
$lowStockCount = 0;
foreach ($items as $item) {
    $qty = is_numeric($item['quantity_in_stock'] ?? null) ? (float) $item['quantity_in_stock'] : 0.0;
    $reorder = is_numeric($item['reorder_level'] ?? null) ? (float) $item['reorder_level'] : 0.0;
    if ($reorder > 0 && $qty <= $reorder) {
        $lowStockCount++;
    }
}
?>

<style>
  .inv-shell {
    max-width: 1200px;
    margin: 24px auto;
    padding: 0 8px;
  }

  .inv-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
    flex-wrap: wrap;
  }

  .inv-stats {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .inv-stat {
    background: #ffffff;
    border: 1px solid #dfe5ef;
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 0.9rem;
    color: #344054;
  }

  .inv-filter-card {
    background: #ffffff;
    border: 1px solid #dfe5ef;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 14px;
  }

  .inv-table-card {
    background: #ffffff;
    border: 1px solid #dfe5ef;
    border-radius: 12px;
    overflow: hidden;
  }

  .inv-table-wrap {
    overflow-x: auto;
  }

  .inv-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 940px;
  }

  .inv-table thead th {
    text-align: left;
    font-size: 0.84rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #5b6472;
    background: #f7f9fc;
    border-bottom: 1px solid #dfe5ef;
    padding: 11px 12px;
    white-space: nowrap;
  }

  .inv-table tbody td {
    padding: 12px;
    border-bottom: 1px solid #edf1f7;
    vertical-align: top;
    color: #1f2937;
  }

  .inv-table tbody tr:hover {
    background: #fbfdff;
  }

  .inv-name {
    font-weight: 600;
    color: #0f172a;
  }

  .inv-subtle {
    font-size: 0.85rem;
    color: #64748b;
  }

  .inv-badge {
    display: inline-block;
    border-radius: 999px;
    padding: 2px 10px;
    font-size: 0.78rem;
    border: 1px solid #c8d5e8;
    background: #ecf4ff;
    color: #1e40af;
    white-space: nowrap;
  }

  .inv-low {
    border-color: #efb1b1;
    background: #fff1f1;
    color: #a11a1a;
  }

  .inv-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  /* AI floating panel */
  .ai-float-panel { position: fixed; bottom: 24px; right: 24px; width: 360px; max-width: calc(100vw - 40px); background: white; border: 1px solid #bfdbfe; border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,0.15); z-index: 9999; display: none; flex-direction: column; }
  .ai-float-panel.visible { display: flex; }
  .ai-float-header { background: linear-gradient(135deg,#7c3aed,#4f46e5); color: white; padding: 10px 14px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; }
  .ai-float-title { font-size: 13px; font-weight: 700; }
  .ai-float-close { background: none; border: none; color: rgba(255,255,255,0.8); cursor: pointer; font-size: 18px; padding: 0; line-height: 1; }
  .ai-float-body { padding: 12px; font-size: 13px; color: #1f2937; line-height: 1.7; white-space: pre-wrap; max-height: 260px; overflow-y: auto; background: #f8faff; margin: 0 12px 0 12px; border-radius: 6px; margin-top: 10px; }
  .ai-float-meta { font-size: 11px; color: #9ca3af; padding: 4px 12px; }
  .ai-float-footer { padding: 8px 12px 12px; display: flex; gap: 8px; }
  .ai-copy-btn { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; border-radius: 4px; padding: 5px 12px; font-size: 11px; cursor: pointer; font-weight: 600; }
  .ai-insight-btn { background: linear-gradient(135deg,#7c3aed,#4f46e5); color: white; border: none; padding: 3px 8px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600; transition: opacity 0.2s; white-space: nowrap; }
  .ai-insight-btn:hover { opacity: 0.85; }
  .ai-insight-btn:disabled { opacity: 0.5; cursor: default; }
  .ai-spinner { display: inline-block; width: 10px; height: 10px; border: 2px solid rgba(255,255,255,0.35); border-top-color: white; border-radius: 50%; animation: ai-spin 0.7s linear infinite; margin-right: 3px; vertical-align: middle; }
  @keyframes ai-spin { to { transform: rotate(360deg); } }

  .inv-qty-form {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-wrap: nowrap;
  }

  .inv-qty-input {
    width: 68px;
    padding: 3px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.85rem;
  }

  .inv-details summary {
    cursor: pointer;
    color: #1d4ed8;
    font-size: 0.85rem;
    user-select: none;
    margin-bottom: 6px;
  }

  .inv-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px 10px;
    font-size: 0.84rem;
    color: #374151;
  }

  .inv-empty {
    text-align: center;
    color: #6b7280;
    padding: 28px;
  }

  .inv-sort-link {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .inv-sort-link:hover {
    color: #1d4ed8;
  }

  .inv-sort-indicator {
    font-size: 0.72rem;
    color: #1d4ed8;
  }

  .inv-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-top: 1px solid #edf1f7;
    flex-wrap: wrap;
  }

  .inv-pagination-links {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .inv-page-current {
    background: #1d4ed8;
    border-color: #1d4ed8;
    color: #fff;
    pointer-events: none;
  }

  .inv-page-dots {
    color: #64748b;
    font-size: 0.9rem;
    padding: 0 4px;
  }

  .inv-column-panel {
    margin-bottom: 12px;
    display: none;
    background: #ffffff;
    border: 1px solid #dfe5ef;
    border-radius: 10px;
    padding: 10px;
  }

  .inv-column-panel.show {
    display: block;
  }

  .inv-column-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
  }

  .inv-column-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
  }

  @media (max-width: 768px) {
    .inv-shell {
      margin-top: 16px;
    }

    .inv-filter-row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 10px;
    }

    .inv-column-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="inv-shell">
  <?php if ($notice === 'qty_updated'): ?>
    <div class="alert alert-success" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
      <span>Quantity updated.</span>
      <?php if ($undoMovementId > 0): ?>
        <form action="inventory_quick_update.php" method="post" style="margin:0;">
          <?php renderCSRFInput(); ?>
          <input type="hidden" name="mode" value="undo">
          <input type="hidden" name="movement_id" value="<?= (int) $undoMovementId ?>">
          <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
          <button type="submit" class="btn btn-sm btn-outline-success">Undo</button>
        </form>
      <?php endif; ?>
    </div>
  <?php elseif ($notice === 'qty_undone'): ?>
    <div class="alert alert-success">Last quantity change was undone.</div>
  <?php elseif ($notice === 'qty_undo_invalid'): ?>
    <div class="alert alert-danger">Undo is no longer available for that change.</div>
  <?php elseif ($notice === 'qty_reason_required'): ?>
    <div class="alert alert-danger">A reason is required for large quantity jumps.</div>
  <?php elseif ($notice === 'qty_invalid'): ?>
    <div class="alert alert-danger">Invalid quantity. Please enter a numeric value.</div>
  <?php elseif ($notice === 'qty_csrf_error'): ?>
    <div class="alert alert-danger">Security validation failed while updating quantity. Please retry.</div>
  <?php endif; ?>

  <div class="inv-toolbar">
    <div>
      <h2 style="margin:0;">Inventory</h2>
      <div class="inv-subtle">Compact view with expandable details</div>
    </div>
    <div class="d-flex gap-2">
      <a href="inventory_movement_history.php" class="btn btn-outline-secondary btn-sm">Stock History</a>
      <a href="<?= htmlspecialchars($buildExportUrl) ?>" class="btn btn-outline-primary btn-sm">Export CSV</a>
      <a href="inventory_add.php" class="btn btn-success btn-sm">Add Item</a>
    </div>
  </div>

  <div class="inv-stats" style="margin-bottom: 12px;">
    <div class="inv-stat">Total Items: <strong><?= (int) $totalItems ?></strong></div>
    <div class="inv-stat">Showing: <strong><?= (int) count($filtered) ?></strong></div>
    <div class="inv-stat">Low Stock: <strong><?= (int) $lowStockCount ?></strong></div>
  </div>

  <div class="inv-filter-card">
    <form id="inventoryFilterForm" method="get" class="inv-filter-row" style="display:grid; grid-template-columns: 2fr 1fr auto auto; gap:10px; align-items:end;">
      <div>
        <label for="q" class="form-label" style="margin-bottom:4px;">Search</label>
        <input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($query) ?>" placeholder="Item, ID, category, supplier...">
      </div>
      <div>
        <label for="status" class="form-label" style="margin-bottom:4px;">Status</label>
        <select id="status" name="status" class="form-select">
          <option value="">All Statuses</option>
          <?php foreach ($statusOptions as $status): ?>
            <option value="<?= htmlspecialchars($status) ?>" <?= strcasecmp($statusFilter, $status) === 0 ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="page_size" class="form-label" style="margin-bottom:4px;">Rows</label>
        <select id="page_size" name="page_size" class="form-select">
          <?php foreach ($allowedPageSizes as $size): ?>
            <option value="<?= (int) $size ?>" <?= $pageSize === $size ? 'selected' : '' ?>><?= (int) $size ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
      <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
      <input type="hidden" id="pageInput" name="page" value="1">
      <button type="submit" class="btn btn-primary">Apply</button>
      <a id="inventoryFilterReset" href="inventory_list.php" class="btn btn-outline-secondary">Reset</a>
    </form>
  </div>

  <button id="toggleColumns" type="button" class="btn btn-outline-primary btn-sm" style="margin-bottom:10px;">Choose Columns</button>
  <div id="columnPanel" class="inv-column-panel">
    <div class="inv-column-grid">
      <label class="inv-column-chip"><input type="checkbox" class="col-toggle" data-col="category" checked> Category</label>
      <label class="inv-column-chip"><input type="checkbox" class="col-toggle" data-col="supplier" checked> Supplier</label>
      <label class="inv-column-chip"><input type="checkbox" class="col-toggle" data-col="qty" checked> Qty</label>
      <label class="inv-column-chip"><input type="checkbox" class="col-toggle" data-col="status" checked> Status</label>
      <label class="inv-column-chip"><input type="checkbox" class="col-toggle" data-col="details" checked> Details</label>
      <label class="inv-column-chip"><input type="checkbox" class="col-toggle" data-col="actions" checked> Actions</label>
    </div>
  </div>

  <div class="inv-table-card">
    <?php if (empty($pagedItems)): ?>
      <div class="inv-empty">No matching items found.</div>
    <?php else: ?>
      <div class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th>
                <a class="inv-sort-link" href="<?= htmlspecialchars($buildSortUrl('item_name')) ?>">
                  Item
                  <?php if ($sortBy === 'item_name'): ?><span class="inv-sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span><?php endif; ?>
                </a>
              </th>
              <th data-col="category">
                <a class="inv-sort-link" href="<?= htmlspecialchars($buildSortUrl('category')) ?>">
                  Category
                  <?php if ($sortBy === 'category'): ?><span class="inv-sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span><?php endif; ?>
                </a>
              </th>
              <th data-col="supplier">
                <a class="inv-sort-link" href="<?= htmlspecialchars($buildSortUrl('supplier_name')) ?>">
                  Supplier
                  <?php if ($sortBy === 'supplier_name'): ?><span class="inv-sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span><?php endif; ?>
                </a>
              </th>
              <th data-col="qty">
                <a class="inv-sort-link" href="<?= htmlspecialchars($buildSortUrl('quantity_in_stock')) ?>">
                  Qty
                  <?php if ($sortBy === 'quantity_in_stock'): ?><span class="inv-sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span><?php endif; ?>
                </a>
              </th>
              <th data-col="status">
                <a class="inv-sort-link" href="<?= htmlspecialchars($buildSortUrl('status')) ?>">
                  Status
                  <?php if ($sortBy === 'status'): ?><span class="inv-sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span><?php endif; ?>
                </a>
              </th>
              <th data-col="details">
                <a class="inv-sort-link" href="<?= htmlspecialchars($buildSortUrl('updated_at')) ?>">
                  Details
                  <?php if ($sortBy === 'updated_at'): ?><span class="inv-sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span><?php endif; ?>
                </a>
              </th>
              <th data-col="actions">
                <a class="inv-sort-link" href="<?= htmlspecialchars($buildSortUrl('updated_at')) ?>">
                  Actions
                  <?php if ($sortBy === 'updated_at'): ?><span class="inv-sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span><?php endif; ?>
                </a>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pagedItems as $item): ?>
              <?php
                $qty = is_numeric($item['quantity_in_stock'] ?? null) ? (float) $item['quantity_in_stock'] : 0.0;
                $reorder = is_numeric($item['reorder_level'] ?? null) ? (float) $item['reorder_level'] : 0.0;
                $isLow = $reorder > 0 && $qty <= $reorder;
                $rowItemId = (string) ($item['item_id'] ?? '');
                $lastMove = $lastMovementByItem[$rowItemId] ?? null;
              ?>
              <tr>
                <td>
                  <div class="inv-name"><?= htmlspecialchars((string) ($item['item_name'] ?? '')) ?></div>
                  <div class="inv-subtle">ID: <?= htmlspecialchars((string) ($item['item_id'] ?? '')) ?></div>
                </td>
                <td data-col="category"><?= htmlspecialchars((string) ($item['category'] ?? '-')) ?></td>
                <td data-col="supplier">
                  <div><?= htmlspecialchars((string) ($item['supplier_name'] ?? '-')) ?></div>
                  <div class="inv-subtle"><?= htmlspecialchars((string) ($item['supplier_id'] ?? '')) ?></div>
                </td>
                <td data-col="qty">
                  <div style="display:flex; flex-direction:column; gap:6px;">
                    <span class="inv-badge <?= $isLow ? 'inv-low' : '' ?>"><?= htmlspecialchars((string) ($item['quantity_in_stock'] ?? '0')) ?></span>
                    <form action="inventory_quick_update.php" method="post" class="inv-qty-form">
                      <?php renderCSRFInput(); ?>
                      <input type="hidden" name="item_id" value="<?= htmlspecialchars((string) ($item['item_id'] ?? '')) ?>">
                      <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
                      <input type="hidden" name="reason" value="">
                      <button type="submit" name="mode" value="dec" class="btn btn-sm btn-outline-secondary">-</button>
                      <input class="inv-qty-input" type="number" min="0" step="any" name="quantity" data-current="<?= htmlspecialchars((string) $qty) ?>" value="<?= htmlspecialchars((string) ($item['quantity_in_stock'] ?? '0')) ?>">
                      <button type="submit" name="mode" value="set" class="btn btn-sm btn-outline-primary inv-qty-set-btn">Set</button>
                      <button type="submit" name="mode" value="inc" class="btn btn-sm btn-outline-secondary">+</button>
                    </form>
                    <?php if (is_array($lastMove)): ?>
                      <?php
                        $delta = is_numeric($lastMove['delta_quantity'] ?? null) ? (float) $lastMove['delta_quantity'] : 0.0;
                        $deltaText = $delta > 0 ? '+' . $delta : (string) $delta;
                        $who = trim((string) ($lastMove['changed_by'] ?? 'system'));
                        if ($who === '') {
                          $who = 'system';
                        }
                        $modeText = (string) ($lastMove['change_mode'] ?? 'set');
                        $whenText = (string) ($lastMove['created_at'] ?? '');
                        $undoFlag = !empty($lastMove['is_undo']) ? ' (undo)' : '';
                      ?>
                      <div class="inv-subtle" title="Latest stock movement">
                        Last: <?= htmlspecialchars($deltaText) ?> via <?= htmlspecialchars($modeText) ?> by <?= htmlspecialchars($who) ?><?= htmlspecialchars($undoFlag) ?>
                        <?php if ($whenText !== ''): ?>on <?= htmlspecialchars($whenText) ?><?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
                <td data-col="status"><span class="inv-badge"><?= htmlspecialchars((string) ($item['status'] ?? '')) ?></span></td>
                <td data-col="details">
                  <details class="inv-details">
                    <summary>View</summary>
                    <div class="inv-detail-grid">
                      <div><strong>Brand:</strong> <?= htmlspecialchars((string) ($item['brand'] ?? '-')) ?></div>
                      <div><strong>Model:</strong> <?= htmlspecialchars((string) ($item['model'] ?? '-')) ?></div>
                      <div><strong>Unit:</strong> <?= htmlspecialchars((string) ($item['unit'] ?? '-')) ?></div>
                      <div><strong>Location:</strong> <?= htmlspecialchars((string) ($item['location'] ?? '-')) ?></div>
                      <div><strong>Cost:</strong> <?= htmlspecialchars((string) ($item['cost_price'] ?? '-')) ?></div>
                      <div><strong>Selling:</strong> <?= htmlspecialchars((string) ($item['selling_price'] ?? '-')) ?></div>
                      <div style="grid-column: 1 / -1;"><strong>Description:</strong> <?= htmlspecialchars((string) ($item['description'] ?? '-')) ?></div>
                    </div>
                  </details>
                </td>
                <td data-col="actions">
                  <div class="inv-actions">
                    <a href="inventory_edit.php?item_id=<?= urlencode((string) ($item['item_id'] ?? '')) ?>" class="btn btn-sm btn-primary">Edit</a>
                    <a href="inventory_movement_history.php?item_id=<?= urlencode((string) ($item['item_id'] ?? '')) ?>" class="btn btn-sm btn-outline-secondary">History</a>
                    <button type="button" class="ai-insight-btn" onclick="aiInventoryInsight('<?= htmlspecialchars((string)($item['item_id'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars((string)($item['item_name'] ?? ''), ENT_QUOTES) ?>', this)">🤖 Insight</button>
                    <form action="inventory_delete.php" method="post" style="display:inline;">
                      <?php renderCSRFInput(); ?>
                      <input type="hidden" name="item_id" value="<?= htmlspecialchars((string) ($item['item_id'] ?? '')) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this item?');">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="inv-pagination">
        <div class="inv-subtle">Page <strong><?= (int) $page ?></strong> of <strong><?= (int) $totalPages ?></strong> (<?= (int) $filteredCount ?> filtered items)</div>
        <div class="inv-pagination-links">
          <?php if ($page > 1): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($buildUrl(['page' => 1])) ?>">First</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($buildUrl(['page' => $page - 1])) ?>">Prev</a>
          <?php endif; ?>

          <?php
            $window = 2;
            $startPage = max(1, $page - $window);
            $endPage = min($totalPages, $page + $window);
          ?>

          <?php if ($startPage > 1): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($buildUrl(['page' => 1])) ?>">1</a>
            <?php if ($startPage > 2): ?><span class="inv-page-dots">...</span><?php endif; ?>
          <?php endif; ?>

          <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <?php if ($p === $page): ?>
              <span class="btn btn-sm inv-page-current"><?= (int) $p ?></span>
            <?php else: ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($buildUrl(['page' => $p])) ?>"><?= (int) $p ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?><span class="inv-page-dots">...</span><?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($buildUrl(['page' => $totalPages])) ?>"><?= (int) $totalPages ?></a>
          <?php endif; ?>

          <?php if ($page < $totalPages): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($buildUrl(['page' => $page + 1])) ?>">Next</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($buildUrl(['page' => $totalPages])) ?>">Last</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('inventoryFilterForm');
  var qInput = document.getElementById('q');
  var statusInput = document.getElementById('status');
  var pageSizeInput = document.getElementById('page_size');
  var pageInput = document.getElementById('pageInput');
  var resetLink = document.getElementById('inventoryFilterReset');
  var toggleColumns = document.getElementById('toggleColumns');
  var columnPanel = document.getElementById('columnPanel');
  var colToggles = Array.prototype.slice.call(document.querySelectorAll('.col-toggle'));
  if (!form || !qInput || !statusInput || !pageSizeInput) {
    return;
  }

  var qKey = 'inventory_list_q';
  var statusKey = 'inventory_list_status';
  var pageSizeKey = 'inventory_list_page_size';
  var columnsKey = 'inventory_list_columns_v1';

  var params = new URLSearchParams(window.location.search);
  var hasFilterInUrl = params.has('q') || params.has('status') || params.has('page_size');

  if (!hasFilterInUrl) {
    var savedQ = localStorage.getItem(qKey) || '';
    var savedStatus = localStorage.getItem(statusKey) || '';
    var savedPageSize = localStorage.getItem(pageSizeKey) || '';
    if (savedQ !== '' || savedStatus !== '' || savedPageSize !== '') {
      qInput.value = savedQ;
      statusInput.value = savedStatus;
      if (savedPageSize !== '') {
        pageSizeInput.value = savedPageSize;
      }
      form.submit();
      return;
    }
  }

  form.addEventListener('submit', function () {
    localStorage.setItem(qKey, qInput.value || '');
    localStorage.setItem(statusKey, statusInput.value || '');
    localStorage.setItem(pageSizeKey, pageSizeInput.value || '25');
    if (pageInput) {
      pageInput.value = '1';
    }
  });

  var applyColumns = function (state) {
    colToggles.forEach(function (toggle) {
      var col = toggle.getAttribute('data-col');
      var visible = state[col] !== false;
      toggle.checked = visible;
      document.querySelectorAll('[data-col="' + col + '"]').forEach(function (el) {
        el.style.display = visible ? '' : 'none';
      });
    });
  };

  var savedColumnsRaw = localStorage.getItem(columnsKey);
  var savedColumns = {};
  if (savedColumnsRaw) {
    try {
      savedColumns = JSON.parse(savedColumnsRaw) || {};
    } catch (e) {
      savedColumns = {};
    }
  }
  applyColumns(savedColumns);

  colToggles.forEach(function (toggle) {
    toggle.addEventListener('change', function () {
      savedColumns[toggle.getAttribute('data-col')] = toggle.checked;
      localStorage.setItem(columnsKey, JSON.stringify(savedColumns));
      applyColumns(savedColumns);
    });
  });

  if (toggleColumns && columnPanel) {
    toggleColumns.addEventListener('click', function () {
      columnPanel.classList.toggle('show');
    });
  }

  if (resetLink) {
    resetLink.addEventListener('click', function () {
      localStorage.removeItem(qKey);
      localStorage.removeItem(statusKey);
      localStorage.removeItem(pageSizeKey);
    });
  }

  document.querySelectorAll('.inv-qty-form').forEach(function (qtyForm) {
    var setButton = qtyForm.querySelector('.inv-qty-set-btn');
    var qtyInput = qtyForm.querySelector('input[name="quantity"]');
    var reasonInput = qtyForm.querySelector('input[name="reason"]');
    if (!setButton || !qtyInput || !reasonInput) {
      return;
    }

    setButton.addEventListener('click', function (event) {
      var current = parseFloat(qtyInput.getAttribute('data-current') || '0');
      var next = parseFloat(qtyInput.value || '0');
      reasonInput.value = '';
      if (!isFinite(current) || !isFinite(next)) {
        return;
      }

      var delta = Math.abs(next - current);
      var isBigJump = delta >= 100 || (current > 0 && (next / current >= 5 || current / (next || 0.00001) >= 5));
      if (!isBigJump) {
        return;
      }

      var row = qtyForm.closest('tr');
      var itemNameEl = row ? row.querySelector('.inv-name') : null;
      var itemName = itemNameEl ? itemNameEl.textContent.trim() : 'this item';
      var ok = window.confirm('Large quantity change detected for ' + itemName + ': ' + current + ' -> ' + next + '. Continue?');
      if (!ok) {
        event.preventDefault();
        return;
      }

      var reason = window.prompt('Please enter a short reason for this large quantity change:');
      if (reason === null || reason.trim() === '') {
        event.preventDefault();
        window.alert('Reason is required for large quantity changes.');
        return;
      }
      reasonInput.value = reason.trim().substring(0, 255);
    });
  });

  var isTypingTarget = function (el) {
    if (!el) {
      return false;
    }
    var tag = (el.tagName || '').toUpperCase();
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable === true;
  };

  document.addEventListener('keydown', function (event) {
    if (event.ctrlKey || event.metaKey || event.altKey) {
      return;
    }
    if (isTypingTarget(event.target)) {
      return;
    }

    var key = (event.key || '').toLowerCase();
    if (key === '/') {
      event.preventDefault();
      qInput.focus();
      qInput.select();
      return;
    }

    if (key === 'n') {
      var nextBtn = document.querySelector('.inv-pagination-links a[href*="page=<?= (int) min($totalPages, $page + 1) ?>"]');
      if (nextBtn && nextBtn.textContent.trim().toLowerCase().indexOf('next') !== -1) {
        event.preventDefault();
        window.location.href = nextBtn.getAttribute('href');
      }
      return;
    }

    if (key === 'p') {
      var prevBtn = document.querySelector('.inv-pagination-links a[href*="page=<?= (int) max(1, $page - 1) ?>"]');
      if (prevBtn && prevBtn.textContent.trim().toLowerCase().indexOf('prev') !== -1) {
        event.preventDefault();
        window.location.href = prevBtn.getAttribute('href');
      }
    }
  });
});

// ── AI Inventory Insight ──────────────────────────────────────────────────
(function() {
  // Inject floating panel once
  const panel = document.createElement('div');
  panel.className = 'ai-float-panel';
  panel.id = 'aiFloatPanel';
  panel.innerHTML = [
    '<div class="ai-float-header">',
    '  <span class="ai-float-title" id="aiFloatTitle">🤖 AI Insight</span>',
    '  <button class="ai-float-close" onclick="aiCloseFloat()">✕</button>',
    '</div>',
    '<div class="ai-float-body" id="aiFloatBody"></div>',
    '<div class="ai-float-meta" id="aiFloatMeta"></div>',
    '<div class="ai-float-footer">',
    '  <button class="ai-copy-btn" onclick="aiCopyFloat()">📋 Copy</button>',
    '</div>',
  ].join('');
  document.body.appendChild(panel);

  const AI_CSRF = <?= json_encode(getCSRFToken()) ?>;

  window.aiInventoryInsight = function(itemId, itemName, btn) {
    const p     = document.getElementById('aiFloatPanel');
    const body  = document.getElementById('aiFloatBody');
    const meta  = document.getElementById('aiFloatMeta');
    const title = document.getElementById('aiFloatTitle');

    title.textContent = '🤖 Insight: ' + itemName;
    body.textContent  = 'Thinking…';
    meta.textContent  = '';
    p.classList.add('visible');

    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="ai-spinner"></span>';

    const fd = new FormData();
    fd.append('action',     'inventory_insight');
    fd.append('item_id',    itemId);
    fd.append('csrf_token', AI_CSRF);

    fetch('ai_endpoint.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.error) {
          body.textContent = '⚠️ ' + data.error;
        } else {
          body.textContent = data.text || '(no response)';
          if (data.provider && data.model) {
            var selectionLabel = data.selection_mode === 'cheapest' ? ' · chosen by cost' : ' · manual selection';
            meta.textContent = 'via ' + data.provider + ' / ' + data.model + selectionLabel;
          }
        }
      })
      .catch(err => { body.textContent = '⚠️ Network error: ' + err.message; })
      .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
  };

  window.aiCloseFloat = function() {
    document.getElementById('aiFloatPanel').classList.remove('visible');
  };

  window.aiCopyFloat = function() {
    const text = document.getElementById('aiFloatBody').textContent;
    navigator.clipboard.writeText(text).then(() => {
      const btn = document.querySelector('.ai-float-footer .ai-copy-btn');
      const orig = btn.textContent;
      btn.textContent = '✓ Copied!';
      setTimeout(() => { btn.textContent = orig; }, 2000);
    });
  };
})();
</script>

<?php include_once __DIR__ . '/layout_end.php'; ?>