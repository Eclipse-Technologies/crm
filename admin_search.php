<?php
require_once 'layout_start.php';
require_once 'admin_helper.php';
requireAdmin();

$pageTitle = 'Advanced Search';

$schema = require __DIR__ . '/contact_schema.php';
$results = [];
$error = '';

// Searchable text fields
$searchableFields = ['first_name','last_name','company','email','phone','city','province','postal_code','country','tags','notes'];

// Handle search
if ($_POST && isset($_POST['search'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF validation failed';
    } else {
        $search_mode = $_POST['search_mode'] ?? 'any';
        $conn = get_mysql_connection();

        $where  = [];
        $params = [];
        $types  = '';

        foreach ($searchableFields as $field) {
            $value = trim($_POST[$field] ?? '');
            if ($value === '') { continue; }
            if ($search_mode === 'exact') {
                $where[]  = "`$field` = ?";
                $params[] = $value;
                $types   .= 's';
            } else {
                $where[]  = "`$field` LIKE ?";
                $params[] = '%' . $value . '%';
                $types   .= 's';
            }
        }

        $sql = 'SELECT contact_id, first_name, last_name, company, email, phone, city, province FROM contacts';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY last_name, first_name LIMIT 500';

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) { $results[] = $row; }
            $result->free();
            $stmt->close();
        }
        $conn->close();
    }
}

?>

<style>
.search-form { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
.search-form h3 { margin-top: 0; }
.search-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
.search-grid input, .search-grid select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
.search-options { margin-bottom: 15px; }
.search-options label { margin-right: 20px; }
.results-table { width: 100%; font-size: 13px; border-collapse: collapse; margin-top: 15px; }
.results-table th { background: #f5f5f5; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold; }
.results-table td { padding: 8px 10px; border-bottom: 1px solid #eee; }
.results-table tr:hover { background: #f9f9f9; }
</style>

<h2>Advanced Search</h2>
  <p><a href="admin_dashboard.php">← Back to Dashboard</a></p>

  <div class="search-form">
    <h3>Search Criteria</h3>
    <form method="POST">
      <?php echo renderCSRFInput(); ?>

      <div class="search-options">
        <label>
          <input type="radio" name="search_mode" value="any" checked> Partial Match
        </label>
        <label>
          <input type="radio" name="search_mode" value="exact"> Exact Match
        </label>
      </div>

      <div class="search-grid">
        <?php foreach ($searchableFields as $field): ?>
          <div>
            <label><?= ucfirst(str_replace('_', ' ', $field)) ?>:</label>
            <input type="text" name="<?= $field ?>" placeholder="Search..." value="<?= htmlspecialchars($_POST[$field] ?? '') ?>">
          </div>
        <?php endforeach; ?>
      </div>

      <button type="submit" name="search">🔍 Search</button>
      <button type="reset" onclick="location.href='admin_search.php'" style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Reset</button>
    </form>
  </div>

  <?php if (isset($_POST['search'])): ?>
    <div style="background: white; padding: 20px; border-radius: 8px;">
      <h3>Results (<?= count($results) ?> found)</h3>

      <?php if (!empty($results)): ?>
        <table class="results-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Company</th>
              <th>Email</th>
              <th>Phone</th>
              <th>City</th>
              <th>Province</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results as $contact): ?>
              <tr>
                <td>
                  <a href="contact_view.php?id=<?= urlencode($contact['contact_id']) ?>" title="View contact">
                    <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                  </a>
                </td>
                <td><?= htmlspecialchars($contact['company'] ?? '') ?></td>
                <td><?= htmlspecialchars($contact['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($contact['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($contact['city'] ?? '') ?></td>
                <td><?= htmlspecialchars($contact['province'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No contacts found matching your search criteria.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<?php include_once 'layout_end.php'; ?>
