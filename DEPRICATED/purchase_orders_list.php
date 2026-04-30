<?php
include_once(__DIR__ . '/layout_start.php');
require_once 'db_mysql.php';
$schema = require __DIR__ . '/purchase_order_schema.php';
// Fetch purchase orders and items from MySQL (LEFT JOIN to show all POs)
function fetch_purchase_orders_with_items($schema) {
  $conn = get_mysql_connection();
  // Split header and item fields
  $headerFields = [
    'po_number','date','status','supplier_id','supplier_name','supplier_contact','supplier_address','billing_address','shipping_address','subtotal','total_discount','total_tax','shipping_cost','other_fees','grand_total','currency','expected_delivery','payment_terms','notes','created_by','created_at','updated_at'
  ];
  $itemFields = [
    'item_id','item_name','quantity','unit','unit_price','discount','tax_rate','tax_amount','total'
  ];
  $selectFields = [];
  foreach ($headerFields as $f) {
    $selectFields[] = 'h.`' . $f . '` AS `h_' . $f . '`';
  }
  foreach ($itemFields as $f) {
    $selectFields[] = 'i.`' . $f . '` AS `i_' . $f . '`';
  }
  $sql = "SELECT " . implode(',', $selectFields) . " FROM purchase_orders h LEFT JOIN purchase_order_items i ON h.po_number = i.po_number ORDER BY h.created_at DESC, i.id ASC";
  $result = $conn->query($sql);
  $orders = [];
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $orders[] = $row;
    }
    $result->free();
  }
  $conn->close();
  return $orders;
}
$orders = fetch_purchase_orders_with_items($schema);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_po'])) {
  $poToDelete = trim($_POST['po_number'] ?? '');
  if ($poToDelete !== '') {
    $conn = get_mysql_connection();
    $stmt = $conn->prepare("DELETE FROM purchase_orders WHERE po_number = ?");
    $stmt->bind_param('s', $poToDelete);
    $stmt->execute();
    $stmt->close();
    $conn->close();
  }
  header('Location: purchase_orders_list.php');
  exit;
}

function to_float($value) {
  return is_numeric($value) ? (float)$value : 0.0;
}

// Build filter array from GET
$filters = [];
foreach ($schema as $f) {
  $filters[$f] = isset($_GET[$f]) ? trim($_GET[$f]) : '';
}

// Filter orders by all non-empty fields
$filtered = $orders;
foreach ($filters as $field => $val) {
  if ($val !== '') {
    $filtered = array_filter($filtered, function($order) use ($field, $val) {
      return stripos($order[$field] ?? '', $val) !== false;
    });
  }
}

$poGroups = [];
foreach ($filtered as $row) {
  $poNumber = trim($row['po_number'] ?? '');
  if ($poNumber === '') {
    continue;
  }
  if (!isset($poGroups[$poNumber])) {
    $poGroups[$poNumber] = [
      'header' => $row,
      'items' => [],
      'total_qty' => 0.0,
      'item_count' => 0
    ];
  }
  $poGroups[$poNumber]['items'][] = $row;
  $poGroups[$poNumber]['total_qty'] += to_float($row['quantity'] ?? '');
  $poGroups[$poNumber]['item_count'] += 1;
}

$poGroups = array_values($poGroups);
?>
<div class="container">
  <h2>Purchase Orders</h2>
  <div style="display:flex; justify-content:flex-end; margin-bottom:20px;">
    <a href="purchase_order_add.php" class="btn-outline">➕ Add Purchase Order</a>
  </div>
  <style>
    .po-layout {
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 16px;
    }
    .po-list {
      border: 1px solid #e2e2e2;
      border-radius: 8px;
      padding: 10px;
      background: #fff;
      max-height: 70vh;
      overflow: auto;
    }
    .po-list-item {
      width: 100%;
      text-align: left;
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 8px 10px;
      background: #f9fafb;
      cursor: pointer;
      margin-bottom: 8px;
    }
    .po-list-item.active {
      background: #e9eef6;
      border-color: #8aa4c8;
    }
    .po-list-title { font-weight: 600; margin-bottom: 4px; }
    .po-list-meta {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      font-size: 0.9em;
      color: #555;
    }
    .po-pill {
      display: inline-flex;
      align-items: center;
      padding: 2px 8px;
      border-radius: 999px;
      background: #f1f3f6;
      border: 1px solid #d9dee7;
    }
    .po-detail {
      border: 1px solid #e2e2e2;
      border-radius: 8px;
      padding: 14px;
      background: #fff;
      min-height: 200px;
    }
    .po-detail-panel { display: none; }
    .po-detail-panel.active { display: block; }
    .po-detail-title { font-weight: 700; margin-bottom: 10px; }
    .po-section { margin-bottom: 14px; }
    .po-section-title { font-weight: 700; margin-bottom: 8px; }
    .po-section-grid {
      display: grid;
      grid-template-columns: 160px 1fr 160px 1fr;
      gap: 8px 16px;
      align-items: center;
    }
    .po-label { font-weight: 600; color: #222; }
    .po-value { color: #333; }
    .po-actions { display: flex; gap: 8px; margin-bottom: 12px; }
    .po-actions button[disabled] { opacity: 0.6; cursor: not-allowed; }
    @media (max-width: 900px) {
      .po-layout { grid-template-columns: 1fr; }
      .po-list { max-height: none; }
      .po-section-grid { grid-template-columns: 160px 1fr; }
    }
  </style>
  <?php if (empty($poGroups)): ?>
    <div style="text-align:center; color:#888;">No purchase orders found.</div>
  <?php else: ?>
    <div class="po-layout">
      <div class="po-list" id="poList">
        <?php foreach ($poGroups as $index => $group): ?>
          <?php
            $header = $group['header'];
            $poNumber = $header['po_number'] ?? '';
            $supplier = $header['supplier_name'] ?? '';
            $date = $header['date'] ?? '';
          ?>
          <button
            type="button"
            class="po-list-item"
            data-target="po-detail-<?= $index ?>"
          >
            <div class="po-list-title"><?= htmlspecialchars($supplier !== '' ? $supplier : $poNumber) ?></div>
            <div class="po-list-meta">
              <?php if ($poNumber !== ''): ?><span class="po-pill">PO: <?= htmlspecialchars($poNumber) ?></span><?php endif; ?>
              <?php if ($date !== ''): ?><span class="po-pill">Date: <?= htmlspecialchars($date) ?></span><?php endif; ?>
              <span class="po-pill">Items: <?= htmlspecialchars((string)$group['item_count']) ?></span>
              <span class="po-pill">Qty: <?= htmlspecialchars((string)$group['total_qty']) ?></span>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="po-detail" id="poDetail">
        <?php foreach ($poGroups as $index => $group): ?>
          <?php
            $header = $group['header'];
            $poNumber = $header['po_number'] ?? '';
            $supplier = $header['supplier_name'] ?? '';
            $poLink = urlencode($poNumber);
          ?>
          <div class="po-detail-panel" id="po-detail-<?= $index ?>">
            <div class="po-detail-title"><?= htmlspecialchars($poNumber) ?><?= $supplier !== '' ? ' - ' . htmlspecialchars($supplier) : '' ?></div>
            <div class="po-actions">
              <a href="purchase_order_summary.php?po=<?= $poLink ?>" class="btn-outline">View Supplier Form</a>
              <a href="purchase_order_receive.php?po=<?= $poLink ?>" class="btn-outline">Receive</a>
              <a href="purchase_order_edit.php?po=<?= $poLink ?>" class="btn-outline">Edit</a>
              <form method="post" onsubmit="return confirm('Delete purchase order <?= htmlspecialchars($poNumber) ?>?');">
                <input type="hidden" name="delete_po" value="1">
                <input type="hidden" name="po_number" value="<?= htmlspecialchars($poNumber) ?>">
                <button type="submit" class="btn-outline">Delete</button>
              </form>
            </div>
            <div class="po-section">
              <div class="po-section-title">Summary</div>
              <div class="po-section-grid">
                <div class="po-label">PO Number</div>
                <div class="po-value"><?= htmlspecialchars($poNumber) ?></div>
                <div class="po-label">Date</div>
                <div class="po-value"><?= htmlspecialchars($header['date'] ?? '') ?></div>
                <div class="po-label">Status</div>
                <div class="po-value"><?= htmlspecialchars($header['status'] ?? '') ?></div>
                <div class="po-label">Supplier</div>
                <div class="po-value"><?= htmlspecialchars($supplier) ?></div>
                <div class="po-label">Expected Delivery</div>
                <div class="po-value"><?= htmlspecialchars($header['expected_delivery'] ?? '') ?></div>
                <div class="po-label">Payment Terms</div>
                <div class="po-value"><?= htmlspecialchars($header['payment_terms'] ?? '') ?></div>
              </div>
            </div>
            <div class="po-section">
              <div class="po-section-title">Addresses</div>
              <div class="po-section-grid">
                <div class="po-label">Supplier Address</div>
                <div class="po-value"><?= htmlspecialchars($header['supplier_address'] ?? '') ?></div>
                <div class="po-label">Billing Address</div>
                <div class="po-value"><?= htmlspecialchars($header['billing_address'] ?? '') ?></div>
                <div class="po-label">Shipping Address</div>
                <div class="po-value"><?= htmlspecialchars($header['shipping_address'] ?? '') ?></div>
                <div class="po-label">Notes</div>
                <div class="po-value"><?= htmlspecialchars($header['notes'] ?? '') ?></div>
              </div>
            </div>
            <div class="po-section">
              <div class="po-section-title">Items</div>
              <div style="overflow-x:auto;">
                <table border="1" cellpadding="6" style="width:100%; font-size:0.95em; border-collapse:collapse;">
                  <thead style="background:#f5f5f5;">
                    <tr>
                      <th style="padding:8px 6px;">Item ID</th>
                      <th style="padding:8px 6px;">Item Name</th>
                      <th style="padding:8px 6px;">Qty</th>
                      <th style="padding:8px 6px;">Unit</th>
                      <th style="padding:8px 6px;">Unit Price</th>
                      <th style="padding:8px 6px;">Discount</th>
                      <th style="padding:8px 6px;">Tax</th>
                      <th style="padding:8px 6px;">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($group['items'] as $item): ?>
                      <tr>
                        <td style="padding:6px 4px;"> <?= htmlspecialchars($item['item_id'] ?? '') ?> </td>
                        <td style="padding:6px 4px;"> <?= htmlspecialchars($item['item_name'] ?? '') ?> </td>
                        <td style="padding:6px 4px;"> <?= htmlspecialchars($item['quantity'] ?? '') ?> </td>
                        <td style="padding:6px 4px;"> <?= htmlspecialchars($item['unit'] ?? '') ?> </td>
                        <td style="padding:6px 4px;"> <?= htmlspecialchars($item['unit_price'] ?? '') ?> </td>
                        <td style="padding:6px 4px;"> <?= htmlspecialchars($item['discount'] ?? '') ?> </td>
                        <td style="padding:6px 4px;"> <?= htmlspecialchars($item['tax_amount'] ?? '') ?> </td>
                        <td style="padding:6px 4px;"> <?= htmlspecialchars($item['total'] ?? '') ?> </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <script>
      const poListItems = Array.from(document.querySelectorAll('.po-list-item'));
      const poDetailPanels = Array.from(document.querySelectorAll('.po-detail-panel'));

      function activatePO(item) {
        poListItems.forEach(btn => btn.classList.remove('active'));
        poDetailPanels.forEach(panel => panel.classList.remove('active'));
        if (!item) {
          return;
        }
        item.classList.add('active');
        const targetId = item.getAttribute('data-target');
        const panel = document.getElementById(targetId);
        if (panel) {
          panel.classList.add('active');
        }
      }

      poListItems.forEach(btn => {
        btn.addEventListener('click', () => activatePO(btn));
      });

      activatePO(poListItems[0]);
    </script>
  <?php endif; ?>
</div>
<?php include_once(__DIR__ . '/layout_end.php'); ?>
