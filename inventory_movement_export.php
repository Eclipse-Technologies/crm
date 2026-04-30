<?php
require_once __DIR__ . '/simple_auth/middleware.php';
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

$itemIdFilter = trim((string) ($_GET['item_id'] ?? ''));
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$limit = max(25, min(5000, $limit));
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

$conn = get_mysql_connection();
ensure_inventory_movements_table($conn);
$hasCanonicalTable = table_exists($conn, 'inventory_transactions');
$useCanonical = $hasCanonicalTable && table_row_count($conn, 'inventory_transactions') > 0;

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

$sql = $useCanonical
    ? 'SELECT t.transaction_id AS movement_id, t.occurred_at AS created_at, t.item_id, i.item_name, t.quantity_before AS old_quantity, t.quantity_after AS new_quantity, t.quantity_delta AS delta_quantity, CASE t.transaction_type WHEN "adjust_inc" THEN "inc" WHEN "adjust_dec" THEN "dec" WHEN "adjust_set" THEN "set" WHEN "undo_action" THEN "undo" ELSE t.transaction_type END AS change_mode, t.actor_username AS changed_by, t.reason_text AS reason, t.is_reversal AS is_undo, rev.occurred_at AS undone_at, rev.transaction_id AS undone_by_movement_id FROM inventory_transactions t LEFT JOIN inventory i ON i.item_id COLLATE utf8mb4_unicode_ci = t.item_id COLLATE utf8mb4_unicode_ci LEFT JOIN inventory_transactions rev ON rev.parent_transaction_id = t.transaction_id AND rev.is_reversal = 1'
    : 'SELECT m.movement_id, m.created_at, m.item_id, i.item_name, m.old_quantity, m.new_quantity, m.delta_quantity, m.change_mode, m.changed_by, m.reason, m.is_undo, m.undone_at, m.undone_by_movement_id FROM inventory_movements m LEFT JOIN inventory i ON i.item_id COLLATE utf8mb4_unicode_ci = m.item_id COLLATE utf8mb4_unicode_ci';
$params = [];
$types = '';
$conditions = [];
if ($itemIdFilter !== '') {
    $conditions[] = $useCanonical ? 't.item_id = ?' : 'm.item_id = ?';
    $types .= 's';
    $params[] = $itemIdFilter;
}
if ($fromDate !== '') {
    $conditions[] = $useCanonical ? 'DATE(t.occurred_at) >= ?' : 'DATE(m.created_at) >= ?';
    $types .= 's';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $conditions[] = $useCanonical ? 'DATE(t.occurred_at) <= ?' : 'DATE(m.created_at) <= ?';
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

$filename = 'inventory_movements_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, ['movement_id', 'created_at', 'item_id', 'item_name', 'old_quantity', 'new_quantity', 'delta_quantity', 'change_mode', 'changed_by', 'reason', 'is_undo', 'undone_at', 'undone_by_movement_id']);

foreach ($rows as $row) {
    fputcsv($output, [
        (string) ($row['movement_id'] ?? ''),
        (string) ($row['created_at'] ?? ''),
        (string) ($row['item_id'] ?? ''),
        (string) ($row['item_name'] ?? ''),
        (string) ($row['old_quantity'] ?? ''),
        (string) ($row['new_quantity'] ?? ''),
        (string) ($row['delta_quantity'] ?? ''),
        (string) ($row['change_mode'] ?? ''),
        (string) ($row['changed_by'] ?? ''),
        (string) ($row['reason'] ?? ''),
        !empty($row['is_undo']) ? '1' : '0',
        (string) ($row['undone_at'] ?? ''),
        (string) ($row['undone_by_movement_id'] ?? ''),
    ]);
}

fclose($output);
exit;
