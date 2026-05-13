<?php
$pageTitle = 'Customer Portal';
require_once __DIR__ . '/layout_start.php';
require_once __DIR__ . '/db_mysql.php';

$conn = get_mysql_connection();

// Search handler
$search_q = trim($_GET['q'] ?? '');
$customer_id = trim($_GET['id'] ?? '');

// --- Search mode ---
$search_results = [];
if ($search_q !== '' && $customer_id === '') {
    $like = '%' . $search_q . '%';
    $stmt = $conn->prepare(
        "SELECT cu.customer_id, co.first_name, co.last_name, co.company, co.email, co.city, co.province
         FROM customers cu
         LEFT JOIN contacts co ON cu.contact_id = co.contact_id
         WHERE co.company LIKE ? OR co.first_name LIKE ? OR co.last_name LIKE ? OR co.email LIKE ?
         ORDER BY co.company LIMIT 50"
    );
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) { $search_results[] = $row; }
    $r->free(); $stmt->close();
}

// --- Portal mode ---
$customer = null;
$contracts = [];
$equipment = [];
$tank_counts = null;

if ($customer_id !== '') {
    // Customer + contact info
    $stmt = $conn->prepare(
        "SELECT cu.*, co.first_name, co.last_name, co.company, co.email, co.phone, co.city, co.province, co.postal_code
         FROM customers cu
         LEFT JOIN contacts co ON cu.contact_id = co.contact_id
         WHERE cu.customer_id = ?"
    );
    $stmt->bind_param('s', $customer_id);
    $stmt->execute();
    $r = $stmt->get_result();
    $customer = $r->fetch_assoc();
    $r->free(); $stmt->close();

    if ($customer) {
        // Contracts
        $stmt = $conn->prepare(
            "SELECT contract_id, contract_type, contract_status, equipment_type, tank_ownership,
                    monthly_fee, annual_value, payment_frequency, contract_term,
                    start_date, end_date, renewal_date, auto_renew, notice_period,
                    tank_quantity, tank_size, service_frequency, next_service_date, notes
             FROM contracts WHERE customer_id = ? ORDER BY contract_status, start_date DESC"
        );
        $stmt->bind_param('s', $customer_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) { $contracts[] = $row; }
        $r->free(); $stmt->close();

        // Equipment (customer_id in equipment is INT)
        $cid_int = (int)$customer['contact_id'];
        // Try customer_id as int match on equipment table
        $stmt = $conn->prepare(
            "SELECT equipment_id, equipment_type, manufacturer, model_number, serial_number,
                    ownership, tank_size, resin_type, install_date,
                    last_service_date, next_service_date, service_frequency, status, location, notes
             FROM equipment WHERE customer_id = ? OR contact_id = ? ORDER BY equipment_type, install_date DESC"
        );
        $stmt->bind_param('ii', $cid_int, $cid_int);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) { $equipment[] = $row; }
        $r->free(); $stmt->close();

        // Tank counts
        $stmt = $conn->prepare("SELECT * FROM customer_tank_counts WHERE customer_id = ?");
        $stmt->bind_param('i', $cid_int);
        $stmt->execute();
        $r = $stmt->get_result();
        $tank_counts = $r->fetch_assoc();
        $r->free(); $stmt->close();
    }
}

$conn->close();
?>

<style>
.portal-header { background: linear-gradient(135deg, #0099A8, #006a74); color: white; border-radius: 10px; padding: 24px 28px; margin-bottom: 24px; }
.portal-header h2 { margin: 0 0 4px; font-size: 1.6rem; }
.portal-header .sub { opacity: 0.85; font-size: 0.95rem; }
.portal-section { background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
.portal-section h4 { margin: 0 0 16px; color: #0099A8; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e0f5f7; padding-bottom: 8px; }
.status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.status-active { background: #dcfce7; color: #166534; }
.status-expired { background: #fee2e2; color: #991b1b; }
.status-other { background: #f3f4f6; color: #4b5563; }
.tank-count-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; }
.tank-count-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px; text-align: center; }
.tank-count-box .num { font-size: 2rem; font-weight: 700; color: #0099A8; line-height: 1; }
.tank-count-box .lbl { font-size: 11px; color: #666; margin-top: 4px; text-transform: uppercase; }
.portal-search { background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <h2 style="margin:0;">Customer Portal</h2>
  <a href="customers_list.php" style="color:#0099A8;">← Back to Customers</a>
</div>

<!-- Search bar always visible -->
<div class="portal-search">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" class="form-control" placeholder="Search by company, name, or email…" style="max-width:360px;" autofocus>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($customer_id !== ''): ?>
      <a href="customer_portal.php" class="btn btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<?php if ($search_q !== '' && empty($search_results) && $customer_id === ''): ?>
  <div class="alert alert-warning">No customers found for "<?= htmlspecialchars($search_q) ?>".</div>

<?php elseif (!empty($search_results)): ?>
  <div class="portal-section">
    <h4>Search Results</h4>
    <table class="table table-sm table-hover">
      <thead><tr><th>Company</th><th>Name</th><th>Email</th><th>City</th><th>Province</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($search_results as $sr): ?>
        <tr>
          <td><strong><?= htmlspecialchars($sr['company'] ?? '') ?></strong></td>
          <td><?= htmlspecialchars(trim(($sr['first_name'] ?? '') . ' ' . ($sr['last_name'] ?? ''))) ?></td>
          <td><?= htmlspecialchars($sr['email'] ?? '') ?></td>
          <td><?= htmlspecialchars($sr['city'] ?? '') ?></td>
          <td><?= htmlspecialchars($sr['province'] ?? '') ?></td>
          <td><a href="customer_portal.php?id=<?= urlencode($sr['customer_id']) ?>" class="btn btn-sm btn-outline-primary">View Portal</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($customer_id !== '' && !$customer): ?>
  <div class="alert alert-danger">Customer not found.</div>

<?php elseif ($customer): ?>
  <!-- Portal Header -->
  <div class="portal-header">
    <h2><?= htmlspecialchars($customer['company'] ?? 'Unknown Company') ?></h2>
    <div class="sub">
      <?php $fullname = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')); ?>
      <?php if ($fullname): ?><?= htmlspecialchars($fullname) ?> &nbsp;|&nbsp; <?php endif; ?>
      <?php if ($customer['email']): ?><?= htmlspecialchars($customer['email']) ?> &nbsp;|&nbsp; <?php endif; ?>
      <?php if ($customer['phone']): ?><?= htmlspecialchars($customer['phone']) ?> &nbsp;|&nbsp; <?php endif; ?>
      <?php if ($customer['city'] || $customer['province']): ?><?= htmlspecialchars(trim(($customer['city'] ?? '') . ', ' . ($customer['province'] ?? ''), ', ')) ?><?php endif; ?>
    </div>
    <div style="margin-top:10px;font-size:13px;opacity:0.75;">
      Customer ID: <?= htmlspecialchars($customer['customer_id']) ?>
      <?php if ($customer['last_delivery']): ?>&nbsp;|&nbsp; Last Delivery: <?= htmlspecialchars($customer['last_delivery']) ?><?php endif; ?>
      &nbsp;|&nbsp; <a href="customer_view.php?id=<?= urlencode($customer['customer_id']) ?>" style="color:#a7f3d0;">Edit in CRM →</a>
    </div>
  </div>

  <!-- Tank Counts -->
  <?php if ($tank_counts): ?>
  <div class="portal-section">
    <h4>Tank Summary</h4>
    <div class="tank-count-grid">
      <div class="tank-count-box"><div class="num"><?= (int)($tank_counts['total_tank_count'] ?? 0) ?></div><div class="lbl">Total</div></div>
      <div class="tank-count-box"><div class="num"><?= (int)($tank_counts['rental_tank_count'] ?? 0) ?></div><div class="lbl">Rental</div></div>
      <div class="tank-count-box"><div class="num"><?= (int)($tank_counts['owned_tank_count'] ?? 0) ?></div><div class="lbl">Owned</div></div>
      <div class="tank-count-box"><div class="num"><?= (int)($tank_counts['purchased_tank_count'] ?? 0) ?></div><div class="lbl">Purchased</div></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Contracts -->
  <div class="portal-section">
    <h4>Contracts (<?= count($contracts) ?>)</h4>
    <?php if (empty($contracts)): ?>
      <p class="text-muted">No contracts on file.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead>
          <tr>
            <th>Contract ID</th><th>Type</th><th>Status</th><th>Equipment</th>
            <th>Monthly Fee</th><th>Annual Value</th><th>Start</th><th>End</th>
            <th>Tanks</th><th>Service Freq.</th><th>Next Service</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contracts as $ct):
            $st = strtolower($ct['contract_status'] ?? '');
            $badge = $st === 'active' ? 'status-active' : ($st === 'expired' ? 'status-expired' : 'status-other');
          ?>
          <tr>
            <td><a href="contract_view.php?id=<?= urlencode($ct['contract_id']) ?>" style="color:#0099A8;"><?= htmlspecialchars($ct['contract_id']) ?></a></td>
            <td><?= htmlspecialchars($ct['contract_type'] ?? '') ?></td>
            <td><span class="status-badge <?= $badge ?>"><?= htmlspecialchars($ct['contract_status'] ?? '') ?></span></td>
            <td><?= htmlspecialchars($ct['equipment_type'] ?? '') ?></td>
            <td><?= $ct['monthly_fee'] !== null ? '$' . number_format((float)$ct['monthly_fee'], 2) : '—' ?></td>
            <td><?= $ct['annual_value'] !== null ? '$' . number_format((float)$ct['annual_value'], 2) : '—' ?></td>
            <td><?= htmlspecialchars($ct['start_date'] ?? '') ?></td>
            <td><?= htmlspecialchars($ct['end_date'] ?? '') ?></td>
            <td><?= (int)($ct['tank_quantity'] ?? 0) ?> × <?= htmlspecialchars($ct['tank_size'] ?? '') ?></td>
            <td><?= htmlspecialchars($ct['service_frequency'] ?? '') ?></td>
            <td><?= htmlspecialchars($ct['next_service_date'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Equipment -->
  <div class="portal-section">
    <h4>Equipment (<?= count($equipment) ?>)</h4>
    <?php if (empty($equipment)): ?>
      <p class="text-muted">No equipment records found.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead>
          <tr>
            <th>ID</th><th>Type</th><th>Manufacturer</th><th>Serial</th>
            <th>Tank Size</th><th>Ownership</th><th>Status</th>
            <th>Install Date</th><th>Last Service</th><th>Next Service</th><th>Location</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($equipment as $eq): ?>
          <tr>
            <td style="font-size:11px;color:#999;"><?= htmlspecialchars($eq['equipment_id']) ?></td>
            <td><?= htmlspecialchars($eq['equipment_type'] ?? '') ?></td>
            <td><?= htmlspecialchars($eq['manufacturer'] ?? '') ?></td>
            <td><?= htmlspecialchars($eq['serial_number'] ?? '') ?></td>
            <td><?= htmlspecialchars($eq['tank_size'] ?? '') ?></td>
            <td><?= htmlspecialchars($eq['ownership'] ?? '') ?></td>
            <td><?= htmlspecialchars($eq['status'] ?? '') ?></td>
            <td><?= htmlspecialchars($eq['install_date'] ?? '') ?></td>
            <td><?= htmlspecialchars($eq['last_service_date'] ?? '') ?></td>
            <td><?= $eq['next_service_date'] ? '<strong>' . htmlspecialchars($eq['next_service_date']) . '</strong>' : '—' ?></td>
            <td><?= htmlspecialchars($eq['location'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div style="text-align:center;padding:40px;color:#888;">
    <p style="font-size:16px;">Search for a customer above to view their portal.</p>
  </div>
<?php endif; ?>

<?php include_once 'layout_end.php'; ?>
