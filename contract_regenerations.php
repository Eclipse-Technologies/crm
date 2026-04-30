<?php
// contract_regenerations.php
// View and log regeneration events for a contract
require_once 'db_mysql.php';

$contractId = trim((string)($_GET['contract_id'] ?? ''));
if ($contractId === '') {
    echo '<div class="container"><p style="color:#b91c1c;">Missing contract ID.</p></div>';
    exit;
}

$conn = get_mysql_connection();

// Handle new event submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regen_date'], $_POST['amount'])) {
    $regenDate = $_POST['regen_date'];
    $amount = (float)$_POST['amount'];
    $stmt = $conn->prepare('INSERT INTO contract_regenerations (contract_id, regen_date, amount) VALUES (?, ?, ?)');
    $stmt->bind_param('ssd', $contractId, $regenDate, $amount);
    $stmt->execute();
    $stmt->close();
    header('Location: contract_regenerations.php?contract_id=' . urlencode($contractId));
    exit;
}

// Fetch regeneration events
$stmt = $conn->prepare('SELECT * FROM contract_regenerations WHERE contract_id = ? ORDER BY regen_date DESC');
$stmt->bind_param('s', $contractId);
$stmt->execute();
$result = $stmt->get_result();
$events = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
$conn->close();
?>

<div class="contract-card">
    <h3>Regeneration Events</h3>
    <form method="post" style="margin-bottom:16px;display:flex;gap:10px;align-items:end;">
        <div>
            <label for="regen_date">Date</label><br>
            <input type="date" name="regen_date" id="regen_date" required>
        </div>
        <div>
            <label for="amount">Amount ($)</label><br>
            <input type="number" name="amount" id="amount" step="0.01" min="0" required>
        </div>
        <button type="submit" class="btn-link btn-primary">Add Event</button>
    </form>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr><th>Date</th><th>Amount ($)</th></tr>
        </thead>
        <tbody>
        <?php foreach ($events as $ev): ?>
            <tr>
                <td><?= htmlspecialchars($ev['regen_date']) ?></td>
                <td>$<?= number_format((float)$ev['amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (count($events) === 0): ?>
            <tr><td colspan="2" style="color:#6b7280;">No regeneration events logged.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
