<?php
require_once __DIR__ . '/db_mysql.php';

function ensure_backorders_table(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS backorders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        po_number VARCHAR(64) NOT NULL,
        item_id VARCHAR(64) NOT NULL,
        item_name VARCHAR(255) DEFAULT NULL,
        quantity_backorder DECIMAL(12,2) NOT NULL DEFAULT 0,
        note TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_backorder_po_item (po_number, item_id),
        INDEX idx_backorders_item_id (item_id),
        INDEX idx_backorders_po_number (po_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed to ensure backorders table: ' . $conn->error);
    }
}

function fetch_backorders_mysql(mysqli $conn): array {
    ensure_backorders_table($conn);
    $rows = [];
    $sql = "SELECT po_number, item_id, item_name, quantity_backorder, note, created_at, updated_at
            FROM backorders
            WHERE quantity_backorder > 0
            ORDER BY created_at DESC, po_number ASC, item_id ASC";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    return $rows;
}

function add_or_increment_backorder_mysql(mysqli $conn, string $poNumber, string $itemId, string $itemName, float $quantity, string $note): void {
    ensure_backorders_table($conn);
    if ($quantity <= 0) {
        return;
    }

    $quantityString = (string) $quantity;
    $stmt = $conn->prepare(
        "INSERT INTO backorders (po_number, item_id, item_name, quantity_backorder, note)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           item_name = VALUES(item_name),
           quantity_backorder = quantity_backorder + VALUES(quantity_backorder),
           note = CASE WHEN VALUES(note) <> '' THEN VALUES(note) ELSE note END"
    );
    $stmt->bind_param('sssss', $poNumber, $itemId, $itemName, $quantityString, $note);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Failed to upsert backorder: ' . $error);
    }
    $stmt->close();
}

function reduce_backorder_mysql(mysqli $conn, string $poNumber, string $itemId, float $receivedQty): array {
    ensure_backorders_table($conn);

    $selectStmt = $conn->prepare("SELECT quantity_backorder, item_name FROM backorders WHERE po_number = ? AND item_id = ? LIMIT 1");
    $selectStmt->bind_param('ss', $poNumber, $itemId);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $selectStmt->close();

    if (!$row) {
        throw new RuntimeException('Backorder row not found');
    }

    $existingQty = is_numeric($row['quantity_backorder'] ?? '') ? (float) $row['quantity_backorder'] : 0.0;
    $applyQty = max(0.0, min($existingQty, $receivedQty));
    $remainingQty = max(0.0, $existingQty - $applyQty);

    if ($remainingQty <= 0.0) {
        $deleteStmt = $conn->prepare("DELETE FROM backorders WHERE po_number = ? AND item_id = ?");
        $deleteStmt->bind_param('ss', $poNumber, $itemId);
        if (!$deleteStmt->execute()) {
            $error = $deleteStmt->error;
            $deleteStmt->close();
            throw new RuntimeException('Failed to delete backorder: ' . $error);
        }
        $deleteStmt->close();
    } else {
        $remainingString = (string) $remainingQty;
        $updateStmt = $conn->prepare("UPDATE backorders SET quantity_backorder = ? WHERE po_number = ? AND item_id = ?");
        $updateStmt->bind_param('sss', $remainingString, $poNumber, $itemId);
        if (!$updateStmt->execute()) {
            $error = $updateStmt->error;
            $updateStmt->close();
            throw new RuntimeException('Failed to update backorder quantity: ' . $error);
        }
        $updateStmt->close();
    }

    return [
        'item_name' => (string) ($row['item_name'] ?? ''),
        'previous_qty' => $existingQty,
        'applied_qty' => $applyQty,
        'remaining_qty' => $remainingQty,
    ];
}
