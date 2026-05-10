<?php
// discussion.php - Discussion Log Page
require_once __DIR__ . '/layout_start.php';
require_once __DIR__ . '/simple_auth/middleware.php';
require_once 'db_mysql.php';
require_once 'csrf_helper.php';

$pageTitle = 'Discussion Log';

$conn = get_mysql_connection();
// Only show logs with valid integer contact_id and join to contacts for company name
$sql = "SELECT d.*, c.company FROM discussion_log d LEFT JOIN contacts c ON d.contact_id = c.contact_id WHERE d.contact_id REGEXP '^[0-9]+$' ORDER BY d.timestamp DESC LIMIT 100";
$result = $conn->query($sql);
$discussions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
if ($result) $result->free();
$conn->close();
?>
<div class="container mt-4">
  <h1 class="mb-4">Discussion Log</h1>
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <th>Timestamp</th>
        <th>Author</th>
        <th>Company</th>
        <th>Entry</th>
        <th>Visibility</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($discussions as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['timestamp']) ?></td>
          <td><?= htmlspecialchars($row['author']) ?></td>
          <td><?= htmlspecialchars($row['company']) ?></td>
          <td><?= nl2br(htmlspecialchars($row['entry_text'])) ?></td>
          <td><?= htmlspecialchars($row['visibility']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($discussions)): ?>
        <tr><td colspan="6" class="text-center">No discussion log entries found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
