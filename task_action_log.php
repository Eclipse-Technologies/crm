<?php
// task_action_log.php - logs for task moves and actions
// Each log entry: timestamp, user (if available), action, task_id, from, to

require_once __DIR__ . '/db_mysql.php';
function log_task_action($action, $task_id, $from = '', $to = '', $user = '') {
    $conn = get_mysql_connection();
    $stmt = $conn->prepare("INSERT INTO task_action_log (timestamp, user, action, task_id, from_value, to_value) VALUES (?, ?, ?, ?, ?, ?)");
    $now = date('Y-m-d H:i:s');
    $stmt->bind_param('ssssss', $now, $user, $action, $task_id, $from, $to);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
