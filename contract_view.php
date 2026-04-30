<?php
require_once 'db_mysql.php';

$pageTitle = 'Contract Details';
require_once 'layout_start.php';

$contractId = trim((string)($_GET['id'] ?? ''));
if ($contractId === '') {
    echo '<div class="container"><p style="color:#b91c1c;">Missing contract ID.</p></div>';
    require_once 'layout_end.php';
    exit;
}

$conn = get_mysql_connection();
$sql = "
    SELECT c.*,
           ct.first_name,
           ct.last_name,
           ct.email,
           ct.phone,
           ct.company,
           cu.address AS customer_address
    FROM contracts c
    LEFT JOIN contacts ct ON ct.contact_id = c.contact_id
    LEFT JOIN customers cu ON cu.customer_id = c.customer_id
    WHERE c.contract_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $contractId);
$stmt->execute();
$result = $stmt->get_result();
$contract = $result ? $result->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$contract) {
    echo '<div class="container"><p style="color:#b91c1c;">Contract not found.</p><p><a href="contracts_list.php">Back to contracts</a></p></div>';
    require_once 'layout_end.php';
    exit;
}

$fullName = trim((string)($contract['first_name'] ?? '') . ' ' . (string)($contract['last_name'] ?? ''));
$contactLabel = $fullName !== '' ? $fullName : 'N/A';
?>

<style>
.contract-view-wrap { max-width: 1100px; margin: 0 auto; }
.contract-card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:24px; margin-bottom:20px; }
.contract-grid { display:grid; grid-template-columns:repeat(3, minmax(220px,1fr)); gap:16px; }
.contract-item { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; }
.contract-label { display:block; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px; }
.contract-value { font-weight:600; color:#111827; word-break:break-word; }
.contract-actions { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.btn-link { display:inline-block; text-decoration:none; padding:10px 14px; border-radius:8px; font-weight:600; }
.btn-primary { background:#10b981; color:#fff; }
.btn-secondary { background:#eef2ff; color:#1e3a8a; }
.btn-muted { background:#f3f4f6; color:#374151; }
@media (max-width:900px) { .contract-grid { grid-template-columns:1fr; } }
</style>

<div class="contract-view-wrap">
    <div class="contract-actions">
        <a class="btn-link btn-muted" href="contracts_list.php">← Back to Contracts</a>
        <a class="btn-link btn-secondary" href="contract_edit.php?id=<?= urlencode($contractId) ?>">Edit Contract</a>
    </div>

    <div class="contract-card">
        <h2 style="margin-top:0;">Contract <?= htmlspecialchars($contract['contract_id'] ?? '') ?></h2>
        <div class="contract-grid">
            <div class="contract-item"><span class="contract-label">Status</span><span class="contract-value"><?= htmlspecialchars($contract['contract_status'] ?? 'N/A') ?></span></div>
            <div class="contract-item"><span class="contract-label">Type</span><span class="contract-value"><?= htmlspecialchars($contract['contract_type'] ?? 'N/A') ?></span></div>

            <div class="contract-item"><span class="contract-label">Equipment</span><span class="contract-value"><?= htmlspecialchars($contract['equipment_type'] ?? 'N/A') ?></span></div>
            <div class="contract-item"><span class="contract-label">Tank Ownership</span><span class="contract-value"><?= htmlspecialchars($contract['tank_ownership'] ?? 'N/A') ?></span></div>

<?php
if (($contract['tank_ownership'] ?? '') === 'Rented' && !empty($contract['equipment_ids'])) {
    require_once 'inventory_mysql.php';
    $equipmentIds = array_map('trim', explode(',', $contract['equipment_ids']));
    $inventory = fetch_inventory_mysql(require __DIR__ . '/inventory_schema.php');
    $rentedTanks = array_filter($inventory, function($item) use ($equipmentIds) {
        return in_array((string)($item['item_id'] ?? ''), $equipmentIds, true);
    });
    if (count($rentedTanks) > 0) {
        echo '<div class="contract-item" style="grid-column:1/-1;"><span class="contract-label">Rented Tank Details</span>';
        echo '<table style="width:100%;background:#f9fafb;border-radius:8px;overflow:hidden;">';
        echo '<tr><th>ID</th><th>Name</th><th>Serial</th><th>Status</th><th>Location</th></tr>';
        foreach ($rentedTanks as $tank) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($tank['item_id'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($tank['item_name'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($tank['serial_number'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($tank['status'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($tank['location'] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
}
?>

            <div class="contract-item"><span class="contract-label">Contact</span><span class="contract-value"><?= htmlspecialchars($contactLabel) ?></span></div>
            <div class="contract-item"><span class="contract-label">Company</span><span class="contract-value"><?= htmlspecialchars($contract['company'] ?? 'N/A') ?></span></div>
            <div class="contract-item"><span class="contract-label">Email</span><span class="contract-value"><?= htmlspecialchars($contract['email'] ?? 'N/A') ?></span></div>

            <div class="contract-item"><span class="contract-label">Customer ID</span><span class="contract-value"><?= htmlspecialchars((string)($contract['customer_id'] ?? 'N/A')) ?></span></div>
            <div class="contract-item"><span class="contract-label">Customer Address</span><span class="contract-value"><?= htmlspecialchars($contract['customer_address'] ?? 'N/A') ?></span></div>
            <div class="contract-item"><span class="contract-label">Phone</span><span class="contract-value"><?= htmlspecialchars($contract['phone'] ?? 'N/A') ?></span></div>

            <div class="contract-item"><span class="contract-label">Monthly Rental Fee</span><span class="contract-value">$<?= number_format((float)($contract['monthly_fee'] ?? 0), 2) ?></span></div>
            <div class="contract-item"><span class="contract-label">Regeneration Fee</span><span class="contract-value">$<?= number_format((float)($contract['regen_fee'] ?? 0), 2) ?></span></div>
            <div class="contract-item"><span class="contract-label">Delivery Fee</span><span class="contract-value">$<?= number_format((float)($contract['tank_sale_price'] ?? 0), 2) ?></span></div>

            <div class="contract-item"><span class="contract-label">Annual Value</span><span class="contract-value">$<?= number_format((float)($contract['annual_value'] ?? 0), 2) ?></span></div>
            <div class="contract-item"><span class="contract-label">Payment Frequency</span><span class="contract-value"><?= htmlspecialchars($contract['payment_frequency'] ?? 'N/A') ?></span></div>
            <div class="contract-item"><span class="contract-label">Regeneration Frequency</span><span class="contract-value"><?= htmlspecialchars($contract['service_frequency'] ?? 'N/A') ?></span></div>

            <div class="contract-item"><span class="contract-label">Start Date</span><span class="contract-value"><?= htmlspecialchars($contract['start_date'] ?? 'N/A') ?></span></div>
            <div class="contract-item"><span class="contract-label">End Date</span><span class="contract-value"><?= htmlspecialchars($contract['end_date'] ?? 'N/A') ?></span></div>
            <div class="contract-item"><span class="contract-label">Renewal Date</span><span class="contract-value"><?= htmlspecialchars($contract['renewal_date'] ?? 'N/A') ?></span></div>

            <div class="contract-item"><span class="contract-label">Notice Period (days)</span><span class="contract-value"><?= htmlspecialchars((string)($contract['notice_period'] ?? 'N/A')) ?></span></div>
            <div class="contract-item"><span class="contract-label">Auto Renew</span><span class="contract-value"><?= htmlspecialchars($contract['auto_renew'] ?? 'N/A') ?></span></div>
            <div class="contract-item"><span class="contract-label">Evoqua Account</span><span class="contract-value"><?= htmlspecialchars($contract['evoqua_account'] ?? 'N/A') ?></span></div>
        </div>
    </div>

    <div class="contract-card">
        <h3 style="margin-top:0;">Notes</h3>
        <p style="white-space:pre-wrap;"><?= htmlspecialchars($contract['notes'] ?? 'No notes') ?></p>
    </div>

    <div class="contract-card">
        <h3 style="margin-top:0;">Regeneration Events</h3>
        <iframe src="contract_regenerations.php?contract_id=<?= urlencode($contractId) ?>" style="width:100%;height:320px;border:none;overflow:auto;background:#f9fafb;"></iframe>
    </div>
</div>

<?php require_once 'layout_end.php'; ?>
