<?php
// customers_list.php

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

include_once(__DIR__ . '/layout_start.php');
require_once 'db_mysql.php';

// Pagination
$per_page = 25;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;

$conn = get_mysql_connection();

// Count
$count_result = $conn->query('SELECT COUNT(*) AS cnt FROM customers');
$total_customers = (int)($count_result->fetch_assoc()['cnt'] ?? 0);
$count_result->free();
$total_pages = max(1, (int)ceil($total_customers / $per_page));
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $per_page;

// Load page of customers
$stmt = $conn->prepare('SELECT customers.*, contacts.company, contacts.first_name, contacts.email FROM customers LEFT JOIN contacts ON customers.contact_id = contacts.contact_id ORDER BY customers.customer_id ASC LIMIT ? OFFSET ?');
$stmt->bind_param('ii', $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$customers = [];
while ($row = $result->fetch_assoc()) {
  $customers[] = $row;
}
$stmt->close();
$conn->close();
?>

<div class="container">
  <h2>Customer List</h2>

  <table class="table-grid">
    <thead>
      <tr>
		<th>Company</th>
        <th>First Name</th>
        <th>Email</th>
        <th>Customer Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($customers as $contact): ?>
        <?php
          $hasContact = !empty($contact['company']) || !empty($contact['first_name']) || !empty($contact['email']);
        ?>
        <?php if ($hasContact): ?>
          <tr>
            <td><?= htmlspecialchars($contact['company'] ?? '') ?></td>
            <td><?= htmlspecialchars($contact['first_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($contact['email'] ?? '') ?></td>
            <td>
              <?php
                // Show readable customer status
                if (isset($contact['is_customer'])) {
                  if ($contact['is_customer'] == 1 || $contact['is_customer'] === '1') {
                    echo '<span style="color:#28a745;font-weight:600;">Active</span>';
                  } elseif ($contact['is_customer'] == 0 || $contact['is_customer'] === '0') {
                    echo '<span style="color:#999;">Inactive</span>';
                  } else {
                    echo htmlspecialchars($contact['is_customer']);
                  }
                } else {
                  echo '—';
                }
              ?>
            </td>
            <td>
              <a href="customer_view.php?id=<?= urlencode($contact['customer_id']) ?>" class="btn-primary">👁 View</a>
              <a href="edit_customer.php?customer_id=<?= urlencode($contact['customer_id']) ?>" class="btn-warning">✏️ Edit</a>
              <form method="GET" action="delete_customer.php" style="display:inline;">
                <input type="hidden" name="customer_id" value="<?= htmlspecialchars($contact['customer_id']) ?>">
                <button type="submit" class="btn-danger" onclick="return confirm('Are you sure you want to delete this customer? This will archive their info and remove them permanently.');">🗑️ Delete</button>
              </form>
              <?php if (!empty($contact['contact_id'])): ?>
                <a href="contact_view.php?id=<?= urlencode($contact['contact_id']) ?>" class="btn-secondary">👤 View Contact</a>
              <?php endif; ?>
              <?php
                $deliveryFile = "{$contact['customer_id']}_deliveries.csv";
                if (file_exists(__DIR__ . "/$deliveryFile")):
              ?>
                <a href="<?= htmlspecialchars($deliveryFile) ?>" class="btn-secondary" target="_blank">📦 Delivery Archive</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <tr style="background:#fffbe6;color:#c00;">
            <td colspan="5"><strong>Incomplete Customer Record:</strong> Customer ID <?= htmlspecialchars($contact['customer_id']) ?> has missing contact info. <a href="customer_view.php?id=<?= urlencode($contact['customer_id']) ?>" class="btn-primary">👁 View</a><?php if (!empty($contact['customer_id'])): ?> <a href="edit_customer.php?customer_id=<?= urlencode($contact['customer_id']) ?>" class="btn-warning">✏️ Edit</a> <form method="GET" action="delete_customer.php" style="display:inline;"><input type="hidden" name="customer_id" value="<?= htmlspecialchars($contact['customer_id']) ?>"><button type="submit" class="btn-danger" onclick="return confirm('Are you sure you want to delete this customer? This will archive their info and remove them permanently.');">🗑️ Delete</button></form><?php endif; ?></td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="navigation d-flex justify-content-between align-items-center mt-3">
    <a href="index.php" class="btn-outline">⬅ Back to Home</a>
    <?php if ($total_pages > 1): ?>
    <nav>
      <ul class="pagination mb-0">
        <?php if ($current_page > 1): ?>
          <li class="page-item"><a class="page-link" href="?page=<?= $current_page - 1 ?>">‹ Prev</a></li>
        <?php endif; ?>
        <?php for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++): ?>
          <li class="page-item <?= $p === $current_page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($current_page < $total_pages): ?>
          <li class="page-item"><a class="page-link" href="?page=<?= $current_page + 1 ?>">Next ›</a></li>
        <?php endif; ?>
      </ul>
    </nav>
    <span class="text-muted">Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_customers) ?> of <?= $total_customers ?></span>
    <?php endif; ?>
  </div>
</div>

<?php include_once(__DIR__ . '/layout_end.php'); ?>
