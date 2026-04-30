<?php
// inventory_mysql.php - MySQL handler for inventory
require_once 'db_mysql.php';

function fetch_inventory_mysql($schema) {
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
    $sql = "SELECT $fields FROM inventory";
    $result = $conn->query($sql);
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $conn->close();
    return $rows;
}

function read_ledger_mysql() {
    $conn = get_mysql_connection();
    $rows = [];
    $result = $conn->query("SELECT * FROM inventory_ledger");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $conn->close();
    return $rows;
}

function read_serials_mysql() {
    $conn = get_mysql_connection();
    $rows = [];
    $result = $conn->query("SELECT * FROM inventory_serials");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $conn->close();
    return $rows;
}

function add_status_total(&$totals, $itemId, $status, $qty) {
    if ($itemId === '' || $status === '') {
        return;
    }
    if (!isset($totals[$itemId])) {
        $totals[$itemId] = [];
    }
    if (!isset($totals[$itemId][$status])) {
        $totals[$itemId][$status] = 0.0;
    }
    $totals[$itemId][$status] += $qty;
}

function read_status_options_mysql() {
    $conn = get_mysql_connection();
    $options = [];
    $result = $conn->query("SELECT status FROM inventory_status_options ORDER BY status ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $value = trim($row['status'] ?? '');
            if ($value !== '') {
                $options[] = $value;
            }
        }
        $result->free();
    }
    $conn->close();
    return array_values(array_unique($options));
}

function write_status_options_mysql($options) {
    $conn = get_mysql_connection();
    $conn->query("TRUNCATE TABLE inventory_status_options");
    $stmt = $conn->prepare("INSERT INTO inventory_status_options (status) VALUES (?)");
    foreach ($options as $option) {
        $stmt->bind_param('s', $option);
        $stmt->execute();
    }
    $stmt->close();
    $conn->close();
}
