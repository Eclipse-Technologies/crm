<?php
require_once __DIR__ . '/simple_auth/middleware.php';
require 'db_mysql.php';
$conn = get_mysql_connection();
$r = $conn->query('SELECT e.equipment_id, e.customer_id, e.serial_number, e.ownership, c.address FROM equipment e LEFT JOIN customers c ON c.customer_id = e.customer_id LIMIT 20');
if (!$r) { echo $conn->error; exit; }
while ($row = $r->fetch_assoc()) print_r($row);
