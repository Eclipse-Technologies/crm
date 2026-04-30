<?php
require_once 'tasks_mysql.php';
$tasks = fetch_tasks_mysql();
$html = [];
foreach ($tasks as $task) {
    if ($task['status'] !== 'archived') {
        $id = htmlspecialchars($task['id']);
        $title = htmlspecialchars($task['title']);
        $timestamp = htmlspecialchars($task['timestamp']);
        $html[] = "<li>$title (Created: $timestamp) <button onclick=\"archiveTask('$id')\">Archive</button></li>";
    }
}
if (empty($html)) {
    echo '<li>No tasks found.</li>';
} else {
    echo '<ul>' . implode('', $html) . '</ul>';
}
?>
!R0b9052597723