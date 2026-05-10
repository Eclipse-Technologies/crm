<?php
// tasks_mysql.php - MySQL handler for tasks
require_once 'db_mysql.php';

function fetch_tasks_mysql($filters = []) {
    $allowedFilterKeys = ['id', 'title', 'status', 'priority', 'assigned_to', 'due_date', 'timestamp', 'contact_id', 'opportunity_id', 'project_id', 'recurrence'];
    $conn = get_mysql_connection();
    $where = [];
    $bindTypes = '';
    $bindValues = [];
    foreach ($filters as $key => $value) {
        if (!in_array($key, $allowedFilterKeys, true)) {
            continue;
        }
        $where[] = "`$key` = ?";
        $bindTypes .= 's';
        $bindValues[] = $value;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT * FROM tasks $whereSql ORDER BY due_date ASC, timestamp DESC";
    if ($bindValues) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($bindTypes, ...$bindValues);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query($sql);
    }
    $tasks = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        $result->free();
    }
    $conn->close();
    return $tasks;
}

function insert_task_mysql($task) {
    $conn = get_mysql_connection();
    $stmt = $conn->prepare("INSERT INTO tasks (id, title, status, priority, assigned_to, due_date, timestamp, contact_id, opportunity_id, project_id, description, comments, recurrence, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssssiiissss',
        $task['id'],
        $task['title'],
        $task['status'],
        $task['priority'],
        $task['assigned_to'],
        $task['due_date'],
        $task['timestamp'],
        $task['contact_id'],
        $task['opportunity_id'],
        $task['project_id'],
        $task['description'],
        $task['comments'],
        $task['recurrence'],
        $task['attachment']
    );
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

function update_task_mysql($id, $fields) {
    $conn = get_mysql_connection();
    $set = [];
    $params = [];
    $types = '';
    foreach ($fields as $key => $value) {
        $set[] = "`$key` = ?";
        $params[] = $value;
        $types .= 's';
    }
    $params[] = $id;
    $types .= 's';
    $sql = "UPDATE tasks SET " . implode(', ', $set) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

function archive_task_mysql($id) {
    return update_task_mysql($id, ['status' => 'archived']);
}
