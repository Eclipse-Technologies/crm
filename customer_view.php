
<?php
// --- ALL SESSION/CSRF/DEPENDENCY LOGIC MUST BE AT THE VERY TOP, NO OUTPUT OR WHITESPACE BEFORE THIS BLOCK ---
require_once __DIR__ . '/csrf_helper.php';
ensureCSRFSessionStarted();
require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/inventory_mysql.php';
$inventory = fetch_inventory_mysql(require __DIR__ . '/inventory_schema.php');
$customerSchema = require __DIR__ . '/customer_schema.php';
$equipmentSchema = require __DIR__ . '/equipment_schema.php';

$contractSchema = require __DIR__ . '/contract_schema.php';

// ── Layout start ─────────────────────────────────────────────────────────────
include_once(__DIR__ . '/layout_start.php');

// ── Load customer ────────────────────────────────────────────────────────────
$customerId = $_GET['id'] ?? '';
$customer = null;
$contact = null;
if ($customerId !== '') {
    $conn = get_mysql_connection();
    // Fetch customer
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    // Fetch linked contact
    if ($customer && !empty($customer['contact_id'])) {
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE contact_id = ?");
        $stmt->bind_param('i', $customer['contact_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $contact = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
    $conn->close();
}
if (!$customer) {
    echo "<div class='container'><h2>❌ Customer not found</h2></div>";
    include_once(__DIR__ . '/layout_end.php');
    exit;
}
// ── Fetch contracts for this customer ────────────────────────────────────────
$contracts = [];
$totalMRR = 0;
$totalARR = 0;
$activeCount = 0;
if ($customerId !== '') {
    $conn = get_mysql_connection();
    $stmt = $conn->prepare("SELECT * FROM contracts WHERE customer_id = ? ORDER BY contract_status DESC, start_date DESC");
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['contract_status'] === 'Active') {
            $totalMRR += (float)($row['monthly_fee'] ?? 0);
            $totalARR += (float)($row['annual_value'] ?? 0);
            $activeCount++;
        }
        $contracts[] = $row;
    }
    $stmt->close();
    $conn->close();
}
?>
<div class="main-content">
    <div class="content-container">
        <div class="container">


        <!-- Header -->
        <div class="card mb-4 p-4 shadow-sm">
            <h1 class="h4 mb-2">🏢 <?= htmlspecialchars($customer['address'] ?? 'Customer ' . $customerId) ?></h1>
            <div class="mb-1"><strong>Customer ID:</strong> <?= htmlspecialchars($customerId) ?></div>
            <?php if ($contact): ?>
                <div><strong>Contact:</strong> <?= htmlspecialchars($contact['company'] ?? 'N/A') ?></div>
            <?php endif; ?>
        </div>

    <!-- ── CUSTOMER INFO FORM (Editable inline) ────────────────────────────────── -->

        <div class="card mb-4 p-4">
            <div class="section-header h5 mb-3">📋 Customer Information</div>
            <form method="post">
                <?php renderCSRFInput(); ?>
                <input type="hidden" name="action" value="update_customer">
                <div class="row g-3">
                    <?php foreach ($customerSchema as $field): ?>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="<?= $field ?>" class="form-label"><?= ucfirst(str_replace('_', ' ', $field)) ?></label>
                                <?php if ($field === 'customer_id'): ?>
                                    <input type="text" id="<?= $field ?>" class="form-control" value="<?= htmlspecialchars($customer[$field]) ?>" readonly>
                                <?php elseif ($field === 'contact_id'): ?>
                                    <select name="<?= $field ?>" id="<?= $field ?>" class="form-select">
                                        <option value="">-- Select Contact --</option>
                                        <?php
                                        $conn = get_mysql_connection();
                                        $result = $conn->query("SELECT contact_id, company FROM contacts ORDER BY company");
                                        while ($row = $result ? $result->fetch_assoc() : null) {
                                            $selected = ($row['contact_id'] == $customer['contact_id']) ? 'selected' : '';
                                            echo "<option value='{$row['contact_id']}' $selected>{$row['company']}</option>";
                                        }
                                        if ($result) $result->free();
                                        $conn->close();
                                        ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" name="<?= $field ?>" id="<?= $field ?>" class="form-control" value="<?= htmlspecialchars($customer[$field] ?? '') ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">💾 Save Customer Info</button>
                </div>
            </form>
        </div>

    <!-- ── RENTED TANKS SUMMARY ───────────────────────────────────────────── -->
    <?php
    require_once 'inventory_mysql.php';
    $inventory = fetch_inventory_mysql(require __DIR__ . '/inventory_schema.php');
    $rentedTankIds = array_filter(array_map('trim', explode(',', $customer['rented_tanks'] ?? '')));
    $rentedTanks = array_filter($inventory, function($item) use ($rentedTankIds) {
        return in_array($item['item_id'], $rentedTankIds);
    });
    $rentedCount = count($rentedTanks);
    $rentedSizes = array_map(function($item) {
        return $item['tank_size'] ?? ($item['item_name'] ?? '');
    }, $rentedTanks);
    ?>

        <div class="card mb-4 p-4">
            <div class="section-header h5 mb-3">🛢️ Rented Tanks Summary</div>
            <div>
                <strong>Number of Rented Tanks:</strong> <?= $rentedCount ?><br>
                <strong>Sizes:</strong> <?= $rentedCount > 0 ? htmlspecialchars(implode(', ', $rentedSizes)) : 'N/A' ?>
            </div>
        </div>

    <!-- ── TANK ASSIGNMENT (POOL ONLY, MODERN CSS) ───────────────────────────── -->

        <div class="card mb-4 p-4">
            <div class="section-header h5 mb-3">🛢️ Assign Tank from Pool</div>
            <form method="post">
                <?php renderCSRFInput(); ?>
                <input type="hidden" name="action" value="assign_tank_from_pool">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="pool_tank" class="form-label">Select Tank from Pool</label>
                        <select name="pool_tank" id="pool_tank" class="form-select">
                            <option value="">-- Select Pool Tank --</option>
                            <?php
                            foreach ($inventory as $item) {
                                $isPool = (strtolower($item['location'] ?? '') === 'pool');
                                if ($isPool) {
                                    echo "<option value='" . htmlspecialchars($item['item_id']) . "'>" . htmlspecialchars($item['item_name']) . " (" . htmlspecialchars($item['serial_number']) . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">💾 Assign Tank</button>
                </div>
            </form>
        </div>

    <!-- ── CONTACT INFORMATION ────────────────────────────────────────────────── -->

    <?php if ($contact): ?>
      <div class="card mb-4 p-4">
        <div class="section-header h5 mb-3">👤 Linked Contact</div>
        <div class="row mb-2">
          <div class="col-md-6 mb-2"><strong>Company:</strong> <?= htmlspecialchars($contact['company'] ?? 'N/A') ?></div>
          <div class="col-md-6 mb-2"><strong>Contact Person:</strong> <?= htmlspecialchars($contact['name'] ?? 'N/A') ?></div>
          <div class="col-md-6 mb-2"><strong>Phone:</strong> <?= htmlspecialchars($contact['phone'] ?? 'N/A') ?></div>
          <div class="col-md-6 mb-2"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($contact['email'] ?? '') ?>"><?= htmlspecialchars($contact['email'] ?? 'N/A') ?></a></div>
          <div class="col-12"><strong>Address:</strong> <?= htmlspecialchars($contact['address'] ?? 'N/A') ?></div>
        </div>
      </div>
    <?php endif; ?>

    <!-- ── EQUIPMENT INVENTORY ────────────────────────────────────────────────── -->

        <div class="card mb-4 p-4">
            <div class="section-header h5 mb-3">🛢️ Customer-Owned Tanks (<?= count($customerOwnedEquipment ?? []) ?>)</div>
            <div class="location-legend mb-2">
                <span class="location-chip location-pool">pool</span>
                <span class="location-chip location-production">production</span>
                <span class="location-chip location-warehouse">warehouse</span>
                <span class="location-chip location-customer-site">customer site</span>
            </div>
            <?php if (!empty($customerOwnedEquipment)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Serial #</th>
                                <th>Tank Size</th>
                                <th>Resin Part #</th>
                                <th>Ownership</th>
                                <th>Location</th>
                                <th>Service Frequency</th>
                                <th>Install Date</th>
                                <th>Last Service</th>
                                <th>Next Service</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerOwnedEquipment as $eq): ?>
                                <?php
                                $resinComponent = $componentsByEquipment[$eq['equipment_id']]['resin'] ?? null;
                                $resinPartNumber = $resinComponent['item_id'] ?? ($eq['resin_type'] ?? '');
                                $resinQty = isset($resinComponent['quantity_required']) ? rtrim(rtrim(number_format((float) $resinComponent['quantity_required'], 3, '.', ''), '0'), '.') : '';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($eq['equipment_type'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['serial_number'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['tank_size'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($resinPartNumber !== '' ? $resinPartNumber . ($resinQty !== '' ? ' (Qty: ' . $resinQty . ')' : '') : 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= ucfirst($eq['ownership'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $locationRaw = strtolower(trim((string) ($eq['location'] ?? '')));
                                        $locationClass = 'location-other';
                                        if ($locationRaw === 'pool') {
                                            $locationClass = 'location-pool';
                                        } elseif ($locationRaw === 'production') {
                                            $locationClass = 'location-production';
                                        } elseif ($locationRaw === 'warehouse') {
                                            $locationClass = 'location-warehouse';
                                        } elseif ($locationRaw === 'customer site') {
                                            $locationClass = 'location-customer-site';
                                        }
                                        $locationLabel = $locationRaw !== '' ? $locationRaw : 'n/a';
                                        ?>
                                        <span class="location-chip <?= htmlspecialchars($locationClass) ?>"><?= htmlspecialchars($locationLabel) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($eq['service_frequency'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['install_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['last_service_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['next_service_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['status'] ?? 'Active') ?></td>
                                    <td>
                                        <a href="equipment_view.php?id=<?= urlencode($eq['equipment_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">No customer-owned tanks tracked yet.</div>
            <?php endif; ?>
        </div>


        <div class="card mb-4 p-4">
            <div class="section-header h5 mb-3">🔁 Service and Rental Tanks At This Site (<?= count($serviceEquipment ?? []) ?>)</div>
            <?php if (!empty($serviceEquipment)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Serial #</th>
                                <th>Tank Size</th>
                                <th>Resin Part #</th>
                                <th>Ownership</th>
                                <th>Location</th>
                                <th>Service Frequency</th>
                                <th>Install Date</th>
                                <th>Last Service</th>
                                <th>Next Service</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($serviceEquipment as $eq): ?>
                                <?php
                                $resinComponent = $componentsByEquipment[$eq['equipment_id']]['resin'] ?? null;
                                $resinPartNumber = $resinComponent['item_id'] ?? ($eq['resin_type'] ?? '');
                                $resinQty = isset($resinComponent['quantity_required']) ? rtrim(rtrim(number_format((float) $resinComponent['quantity_required'], 3, '.', ''), '0'), '.') : '';
                                $eqStatus = $eq['status'] ?? 'Active';
                                $isTrial  = strtolower($eqStatus) === 'trial';
                                ?>
                                <tr<?= $isTrial ? ' style="background:#fffbe6;"' : '' ?>>
                                    <td><?= htmlspecialchars($eq['equipment_type'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['serial_number'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['tank_size'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($resinPartNumber !== '' ? $resinPartNumber . ($resinQty !== '' ? ' (Qty: ' . $resinQty . ')' : '') : 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= ucfirst($eq['ownership'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $locationRaw = strtolower(trim((string) ($eq['location'] ?? '')));
                                        $locationClass = 'location-other';
                                        if ($locationRaw === 'pool') {
                                            $locationClass = 'location-pool';
                                        } elseif ($locationRaw === 'production') {
                                            $locationClass = 'location-production';
                                        } elseif ($locationRaw === 'warehouse') {
                                            $locationClass = 'location-warehouse';
                                        } elseif ($locationRaw === 'customer site') {
                                            $locationClass = 'location-customer-site';
                                        }
                                        $locationLabel = $locationRaw !== '' ? $locationRaw : 'n/a';
                                        ?>
                                        <span class="location-chip <?= htmlspecialchars($locationClass) ?>"><?= htmlspecialchars($locationLabel) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($eq['service_frequency'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['install_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['last_service_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['next_service_date'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($isTrial): ?>
                                            <span class="badge bg-warning text-dark">Trial</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($eqStatus) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="equipment_view.php?id=<?= urlencode($eq['equipment_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php if ($isTrial): ?>
                                            <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>&contact_id=<?= urlencode($customer['contact_id'] ?? '') ?>" class="btn btn-sm btn-success ms-1">📄 Create Contract</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="add_customer.php?contact_id=<?= urlencode($customer['contact_id']) ?>" class="btn btn-primary">➕ Add Equipment</a>
                </div>
            <?php else: ?>
                <div class="no-data">No service/rental tanks assigned at this site. <a href="add_customer.php?contact_id=<?= urlencode($customer['contact_id']) ?>">Add equipment</a></div>
            <?php endif; ?>
        </div>

    <!-- ── CONTRACTS & REVENUE ────────────────────────────────────────────────── -->

        <div class="card mb-4 p-4">
            <div class="section-header h5 mb-3">💰 Service Contracts & Revenue</div>
            <?php if ($activeCount > 0): ?>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card text-center mb-2">
                            <div class="card-body">
                                <div class="metric-label">Active Contracts</div>
                                <div class="metric-value h4 mb-0"><?= $activeCount ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center mb-2">
                            <div class="card-body">
                                <div class="metric-label">Monthly Recurring Revenue</div>
                                <div class="metric-value h4 mb-0">$<?= number_format($totalMRR, 2) ?></div>
                                <div class="metric-subtext small text-muted"><?= $activeCount ?> active contract<?= $activeCount !== 1 ? 's' : '' ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center mb-2">
                            <div class="card-body">
                                <div class="metric-label">Annual Value</div>
                                <div class="metric-value h4 mb-0">$<?= number_format($totalARR, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($contracts)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Contract ID</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Monthly Rental</th>
                                <th>Regen Fee</th>
                                <th>Delivery Fee</th>
                                <th>Annual Value</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['contract_id']) ?></strong></td>
                                    <td><?= htmlspecialchars($c['contract_type'] ?? '') ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?= htmlspecialchars($c['contract_status']) ?>
                                        </span>
                                    </td>
                                    <td>$<?= number_format((float)($c['monthly_fee'] ?? 0), 2) ?></td>
                                    <td>$<?= number_format((float)($c['regen_fee'] ?? 0), 2) ?></td>
                                    <td>$<?= number_format((float)($c['tank_sale_price'] ?? 0), 2) ?></td>
                                    <td><strong>$<?= number_format((float)($c['annual_value'] ?? 0), 2) ?></strong></td>
                                    <td><?= !empty($c['start_date']) ? date('M d, Y', strtotime($c['start_date'])) : 'N/A' ?></td>
                                    <td><?= !empty($c['end_date']) ? date('M d, Y', strtotime($c['end_date'])) : 'N/A' ?></td>
                                    <td>
                                        <a href="contract_view.php?id=<?= urlencode($c['contract_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <a href="contract_edit.php?id=<?= urlencode($c['contract_id']) ?>" class="btn btn-sm btn-outline-secondary ms-1">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>" class="btn btn-primary">➕ New Contract</a>
                </div>
            <?php else: ?>
                <div class="no-data">No contracts found. <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>">Create a new contract</a></div>
            <?php endif; ?>
        </div>

    <!-- Navigation -->

        <div class="d-flex gap-2 justify-content-end mt-4">
            <a href="customers_list.php" class="btn btn-outline-secondary">⬅ Back to Customers</a>
            <a href="index.php" class="btn btn-outline-secondary">⬅ Back to Home</a>
        </div>
</div>

<script>
// ── AI Integration ──────────────────────────────────────────────────────────
const AI_CONTACT_ID  = <?= json_encode($customer['contact_id'] ?? '') ?>;
const AI_CSRF_TOKEN  = <?= json_encode(getCSRFToken()) ?>;

function aiAction(action, btn, label) {
  const panel = document.getElementById('aiPanel');
  const body  = document.getElementById('aiPanelBody');
  const meta  = document.getElementById('aiPanelMeta');
  const title = document.getElementById('aiPanelTitle');

  title.textContent = '🤖 AI: ' + label;
  body.textContent  = 'Thinking…';
  meta.textContent  = '';
  panel.classList.add('visible');
  panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  const origLabel = btn ? btn.innerHTML : '';
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ai-spinner"></span> Thinking…'; }

  const fd = new FormData();
  fd.append('action',     action);
  fd.append('contact_id', AI_CONTACT_ID);
  fd.append('csrf_token', AI_CSRF_TOKEN);

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
    .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = origLabel; } });
}

function closeAiPanel() {
  document.getElementById('aiPanel').classList.remove('visible');
}

function copyAiResult() {
  const text = document.getElementById('aiPanelBody').textContent;
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.ai-copy-btn');
    const orig = btn.textContent;
    btn.textContent = '✓ Copied!';
    setTimeout(() => { btn.textContent = orig; }, 2000);
  });
}
</script>

        </div> <!-- end .container -->
    </div> <!-- end .content-container -->
</div> <!-- end .main-content -->
<?php include_once(__DIR__ . '/layout_end.php'); ?>