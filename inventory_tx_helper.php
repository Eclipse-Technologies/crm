<?php

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

function inventory_tx_generate_uuid(): string
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

function inventory_tx_apply_delta_with_audit(mysqli $conn, string $itemId, float $delta, array $meta): void
{
    $stmt = $conn->prepare('SELECT COALESCE(quantity_in_stock, 0) AS qty FROM inventory WHERE item_id = ? LIMIT 1');
    $stmt->bind_param('s', $itemId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('Component item not found in inventory: ' . $itemId);
    }

    $beforeQty = (float) ($row['qty'] ?? 0);
    $afterQty = $beforeQty + $delta;
    if ($afterQty < -0.000001) {
        throw new RuntimeException('Insufficient stock for item ' . $itemId . '. Need ' . abs($delta) . ', available ' . $beforeQty . '.');
    }

    $stmtUpdate = $conn->prepare('UPDATE inventory SET quantity_in_stock = ? WHERE item_id = ?');
    $stmtUpdate->bind_param('ds', $afterQty, $itemId);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    $entityType = (string) ($meta['entity_type'] ?? 'equipment');
    $entityId = (string) ($meta['entity_id'] ?? '');
    $sourceType = (string) ($meta['source_type'] ?? $entityType);
    $sourceRef = (string) ($meta['source_ref'] ?? $entityId);
    $reasonCode = (string) ($meta['reason_code'] ?? 'equipment_component_change');
    $reasonText = (string) ($meta['reason_text'] ?? 'component stock change');
    $validationStatus = (string) ($meta['validation_status'] ?? 'accepted');
    $transactionType = $delta >= 0 ? 'adjust_inc' : 'adjust_dec';
    $txUuid = inventory_tx_generate_uuid();

    $actorUsername = trim((string) ($_SESSION['username'] ?? 'system'));
    $actorUserId = trim((string) ($_SESSION['user_id'] ?? ''));
    $sessionId = trim((string) session_id());
    $ipRaw = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $uaRaw = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $ipHash = $ipRaw !== '' ? hash('sha256', $ipRaw) : null;
    $uaHash = $uaRaw !== '' ? hash('sha256', $uaRaw) : null;

    $stmtTx = $conn->prepare('INSERT INTO inventory_transactions (transaction_uuid, entity_type, entity_id, item_id, source_type, source_ref, transaction_type, reason_code, reason_text, quantity_before, quantity_delta, quantity_after, actor_user_id, actor_username, session_id, ip_hash, user_agent_hash, parent_transaction_id, is_reversal, validation_status, occurred_at, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    if ($stmtTx) {
        $parentTransactionId = null;
        $isReversal = 0;
        $stmtTx->bind_param(
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
            $uaHash,
            $parentTransactionId,
            $isReversal,
            $validationStatus
        );
        $stmtTx->execute();
        $stmtTx->close();
    }
}
