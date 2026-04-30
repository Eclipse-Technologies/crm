<?php
require_once __DIR__ . '/simple_auth/middleware.php';
require_once 'db_mysql.php';
require_once 'csrf_helper.php';

function redirect_back(string $returnTo, array $params = []): void
{
    $query = http_build_query($params);
    if ($query !== '') {
        $returnTo .= (strpos($returnTo, '?') === false ? '?' : '&') . $query;
    }
    header('Location: ' . $returnTo);
    exit;
}

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

function ensure_inventory_transactions_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS inventory_transactions (
        transaction_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_uuid CHAR(36) NOT NULL,
        entity_type VARCHAR(40) NOT NULL DEFAULT 'inventory',
        entity_id VARCHAR(120) NOT NULL,
        item_id VARCHAR(100) NOT NULL,
        source_type VARCHAR(32) NOT NULL,
        source_ref VARCHAR(120) NULL,
        transaction_type VARCHAR(32) NOT NULL,
        reason_code VARCHAR(32) NULL,
        reason_text VARCHAR(255) NULL,
        quantity_before DECIMAL(18,4) NOT NULL,
        quantity_delta DECIMAL(18,4) NOT NULL,
        quantity_after DECIMAL(18,4) NOT NULL,
        actor_user_id VARCHAR(64) NULL,
        actor_username VARCHAR(120) NULL,
        session_id VARCHAR(128) NULL,
        ip_hash CHAR(64) NULL,
        user_agent_hash CHAR(64) NULL,
        parent_transaction_id BIGINT UNSIGNED NULL,
        is_reversal TINYINT(1) NOT NULL DEFAULT 0,
        validation_status VARCHAR(24) NOT NULL DEFAULT 'accepted',
        occurred_at DATETIME NOT NULL,
        recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tx_uuid (transaction_uuid),
        INDEX idx_item_time (item_id, occurred_at),
        INDEX idx_type_time (transaction_type, occurred_at),
        INDEX idx_actor_time (actor_username, occurred_at),
        INDEX idx_parent (parent_transaction_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

function generate_transaction_uuid(): string
{
    try {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    } catch (Throwable $e) {
        return uniqid('tx-', true);
    }
}

function detect_reason_code(string $mode): string
{
    if ($mode === 'inc') {
        return 'cycle_count_adjustment';
    }
    if ($mode === 'dec') {
        return 'cycle_count_adjustment';
    }
    if ($mode === 'undo') {
        return 'undo_action';
    }
    return 'correction_data_entry';
}

function transaction_type_from_mode(string $mode): string
{
    if ($mode === 'inc') {
        return 'adjust_inc';
    }
    if ($mode === 'dec') {
        return 'adjust_dec';
    }
    if ($mode === 'undo') {
        return 'undo_action';
    }
    return 'adjust_set';
}

function fetch_parent_transaction_id_by_source_ref(mysqli $conn, string $sourceRef): ?int
{
    $stmt = $conn->prepare('SELECT transaction_id FROM inventory_transactions WHERE source_ref = ? ORDER BY transaction_id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $sourceRef);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return null;
    }
    return isset($row['transaction_id']) ? (int) $row['transaction_id'] : null;
}

function insert_inventory_transaction(
    mysqli $conn,
    string $itemId,
    float $beforeQty,
    float $afterQty,
    string $mode,
    string $actorUserId,
    string $actorUsername,
    string $sourceType,
    ?string $sourceRef,
    ?string $reasonText,
    ?string $sessionId,
    ?string $ipHash,
    ?string $userAgentHash,
    ?int $parentTransactionId = null,
    int $isReversal = 0
): int {
    $delta = $afterQty - $beforeQty;
    $txUuid = generate_transaction_uuid();
    $entityType = 'inventory';
    $entityId = $itemId;
    $transactionType = transaction_type_from_mode($mode);
    $reasonCode = detect_reason_code($mode);
    $validationStatus = 'accepted';

    $stmt = $conn->prepare('INSERT INTO inventory_transactions (transaction_uuid, entity_type, entity_id, item_id, source_type, source_ref, transaction_type, reason_code, reason_text, quantity_before, quantity_delta, quantity_after, actor_user_id, actor_username, session_id, ip_hash, user_agent_hash, parent_transaction_id, is_reversal, validation_status, occurred_at, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param(
        'sssssssssdddsssssiss',
        $txUuid,
        $entityType,
        $entityId,
        $itemId,
        $sourceType,
        $sourceRef,
        $transactionType,
        $reasonCode,
        $reasonText,
        $beforeQty,
        $delta,
        $afterQty,
        $actorUserId,
        $actorUsername,
        $sessionId,
        $ipHash,
        $userAgentHash,
        $parentTransactionId,
        $isReversal,
        $validationStatus
    );
    $ok = $stmt->execute();
    $newId = $ok ? (int) $conn->insert_id : 0;
    $stmt->close();
    return $newId;
}

function fetch_current_quantity(mysqli $conn, string $itemId): ?float
{
    $stmt = $conn->prepare('SELECT quantity_in_stock FROM inventory WHERE item_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $itemId);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return null;
    }
    return is_numeric($row['quantity_in_stock'] ?? null) ? (float) $row['quantity_in_stock'] : 0.0;
}

function insert_inventory_movement(
    mysqli $conn,
    string $itemId,
    float $oldQty,
    float $newQty,
    string $mode,
    string $changedBy,
    ?string $reason = null,
    int $isUndo = 0
): int {
    $delta = $newQty - $oldQty;
    $stmt = $conn->prepare('INSERT INTO inventory_movements (item_id, old_quantity, new_quantity, delta_quantity, change_mode, reason, changed_by, is_undo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('sdddsssi', $itemId, $oldQty, $newQty, $delta, $mode, $reason, $changedBy, $isUndo);
    $ok = $stmt->execute();
    $newId = $ok ? (int) $conn->insert_id : 0;
    $stmt->close();
    return $newId;
}

function update_inventory_quantity(mysqli $conn, string $itemId, float $nextQty): bool
{
    $stmt = $conn->prepare('UPDATE inventory SET quantity_in_stock = ?, updated_at = NOW() WHERE item_id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ds', $nextQty, $itemId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function is_big_quantity_jump(float $currentQty, float $nextQty): bool
{
    $delta = abs($nextQty - $currentQty);
    if ($delta >= 100.0) {
        return true;
    }

    if ($currentQty > 0.0) {
        $upRatio = $nextQty / $currentQty;
        $downRatio = $currentQty / max($nextQty, 0.00001);
        return $upRatio >= 5.0 || $downRatio >= 5.0;
    }

    return false;
}

$returnTo = trim((string) ($_POST['return_to'] ?? 'inventory_list.php'));
if ($returnTo === '' || stripos($returnTo, 'inventory_list.php') !== 0) {
    $returnTo = 'inventory_list.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_back($returnTo);
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirect_back($returnTo, ['notice' => 'qty_csrf_error']);
}

$itemId = trim((string) ($_POST['item_id'] ?? ''));
$mode = trim((string) ($_POST['mode'] ?? 'set'));
$quantityRaw = trim((string) ($_POST['quantity'] ?? ''));
$movementId = isset($_POST['movement_id']) ? (int) $_POST['movement_id'] : 0;
$reasonRaw = trim((string) ($_POST['reason'] ?? ''));
$reason = $reasonRaw !== '' ? substr($reasonRaw, 0, 255) : null;

$currentUser = auth_current_user();
$changedBy = trim((string) ($currentUser['username'] ?? $currentUser['email'] ?? ($_SESSION['username'] ?? 'system')));
if ($changedBy === '') {
    $changedBy = 'system';
}
$actorUserId = trim((string) ($currentUser['id'] ?? $currentUser['user_id'] ?? ($_SESSION['user_id'] ?? '')));
$sourceType = 'ui';
$sessionId = trim((string) session_id());
$ipRaw = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
$uaRaw = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
$ipHash = $ipRaw !== '' ? hash('sha256', $ipRaw) : null;
$userAgentHash = $uaRaw !== '' ? hash('sha256', $uaRaw) : null;

if ($mode !== 'undo' && $itemId === '') {
    redirect_back($returnTo, ['notice' => 'qty_invalid']);
}

$conn = get_mysql_connection();
ensure_inventory_movements_table($conn);
ensure_inventory_transactions_table($conn);

if ($mode === 'undo') {
    if ($movementId < 1) {
        $conn->close();
        redirect_back($returnTo, ['notice' => 'qty_undo_invalid']);
    }

    $movementStmt = $conn->prepare('SELECT movement_id, item_id, old_quantity, new_quantity, undone_at FROM inventory_movements WHERE movement_id = ? LIMIT 1');
    if (!$movementStmt) {
        $conn->close();
        redirect_back($returnTo, ['notice' => 'qty_undo_invalid']);
    }
    $movementStmt->bind_param('i', $movementId);
    $movementStmt->execute();
    $movementResult = $movementStmt->get_result();
    $movementRow = $movementResult ? $movementResult->fetch_assoc() : null;
    $movementStmt->close();

    if (!$movementRow || !empty($movementRow['undone_at'])) {
        $conn->close();
        redirect_back($returnTo, ['notice' => 'qty_undo_invalid']);
    }

    $targetItemId = (string) ($movementRow['item_id'] ?? '');
    $oldQty = fetch_current_quantity($conn, $targetItemId);
    if ($targetItemId === '' || $oldQty === null) {
        $conn->close();
        redirect_back($returnTo, ['notice' => 'qty_undo_invalid']);
    }

    $newQty = is_numeric($movementRow['old_quantity'] ?? null) ? (float) $movementRow['old_quantity'] : 0.0;
    if (!update_inventory_quantity($conn, $targetItemId, $newQty)) {
        $conn->close();
        redirect_back($returnTo, ['notice' => 'qty_undo_invalid']);
    }

    $undoReason = 'Undo movement #' . (int) $movementRow['movement_id'];
    $undoMovementId = insert_inventory_movement($conn, $targetItemId, $oldQty, $newQty, 'undo', $changedBy, $undoReason, 1);

    $parentSourceRef = 'movement:' . (int) $movementRow['movement_id'];
    $parentTransactionId = fetch_parent_transaction_id_by_source_ref($conn, $parentSourceRef);
    insert_inventory_transaction(
        $conn,
        $targetItemId,
        $oldQty,
        $newQty,
        'undo',
        $actorUserId,
        $changedBy,
        $sourceType,
        'movement:' . $undoMovementId,
        $undoReason,
        $sessionId,
        $ipHash,
        $userAgentHash,
        $parentTransactionId,
        1
    );

    $markStmt = $conn->prepare('UPDATE inventory_movements SET undone_at = NOW(), undone_by_movement_id = ? WHERE movement_id = ? AND undone_at IS NULL');
    if ($markStmt) {
        $markStmt->bind_param('ii', $undoMovementId, $movementId);
        $markStmt->execute();
        $markStmt->close();
    }

    $conn->close();
    redirect_back($returnTo, ['notice' => 'qty_undone']);
}

$currentQty = fetch_current_quantity($conn, $itemId);
if ($currentQty === null) {
    $conn->close();
    redirect_back($returnTo, ['notice' => 'qty_invalid']);
}

$nextQty = $currentQty;

if ($mode === 'inc' || $mode === 'dec') {
    $delta = $mode === 'inc' ? 1.0 : -1.0;
    $nextQty = max(0.0, $currentQty + $delta);
} else {
    if (!is_numeric($quantityRaw)) {
        $conn->close();
        redirect_back($returnTo, ['notice' => 'qty_invalid']);
    }

    $nextQty = max(0.0, (float) $quantityRaw);

    if ($mode === 'set' && is_big_quantity_jump($currentQty, $nextQty) && ($reason === null || trim($reason) === '')) {
        $conn->close();
        redirect_back($returnTo, ['notice' => 'qty_reason_required']);
    }
}

if (!update_inventory_quantity($conn, $itemId, $nextQty)) {
    $conn->close();
    redirect_back($returnTo, ['notice' => 'qty_invalid']);
}

$newMovementId = insert_inventory_movement($conn, $itemId, $currentQty, $nextQty, $mode, $changedBy, $reason);
insert_inventory_transaction(
    $conn,
    $itemId,
    $currentQty,
    $nextQty,
    $mode,
    $actorUserId,
    $changedBy,
    $sourceType,
    'movement:' . $newMovementId,
    $reason,
    $sessionId,
    $ipHash,
    $userAgentHash,
    null,
    0
);

$conn->close();
redirect_back($returnTo, ['notice' => 'qty_updated', 'undo_id' => $newMovementId]);
