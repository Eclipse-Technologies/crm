<?php
require_once 'db_mysql.php';
require_once 'sanitize_helper.php';
$schema = require __DIR__ . '/opportunity_schema.php';

$editId = trim((string) ($_GET['id'] ?? ''));
if ($editId !== '') {
  header('Location: edit_opportunity.php?id=' . urlencode($editId));
  exit;
}

// Load contacts for dropdown (company + contact_id)
$conn = get_mysql_connection();
$contacts = [];
$result = $conn->query("SELECT contact_id, CONCAT(first_name, ' ', last_name, ' - ', company) AS label FROM contacts ORDER BY company, first_name");
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $contacts[] = $row;
  }
  $result->free();
}
$conn->close();

// Calculate statistics
$totalValue = 0;
?>

<?php include_once(__DIR__ . '/layout_start.php'); ?>
<?php $currentPage = basename(__FILE__); ?>

<div class="container">
  <!-- Search bar removed as requested -->
  <h2>Add New Opportunity</h2>

  <?php if (!empty($_GET['status']) && $_GET['status'] === 'success'): ?>
    <p class="success-msg">Opportunity saved successfully.</p>
  <?php elseif (!empty($_GET['status']) && $_GET['status'] === 'error'): ?>
    <p class="error-msg">Invalid opportunity data or contact ID.</p>
  <?php endif; ?>

  <form id="opportunity-form" action="add_opportunity.php" method="POST" class="form-block">
    <?php foreach ($schema as $field): ?>
      <?php if ($field === 'id') continue; ?>

      <div class="form-group">
        <label for="<?= $field ?>"><?= ucwords(str_replace('_', ' ', $field)) ?>:</label>

        <?php if ($field === 'contact_id'): ?>
          <div class="mb-2">
            <input type="text" id="contact-search" class="form-control" placeholder="Search contact/company..." style="min-width:220px;">
          </div>
          <select name="contact_id" id="contact-select" class="form-control" required>
            <option value="">Select contact...</option>
            <?php foreach ($contacts as $contact): ?>
              <option value="<?= htmlspecialchars($contact['contact_id']) ?>">
                <?= htmlspecialchars($contact['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php elseif ($field === 'stage'): ?>
          <select name="stage" id="stage" class="form-control" required>
            <option value="">Select Stage</option>
            <option value="Prospecting">Prospecting</option>
            <option value="Proposal">Proposal</option>
            <option value="Negotiation">Negotiation</option>
            <option value="Closed Won">Closed Won</option>
            <option value="Closed Lost">Closed Lost</option>
          </select>

        <?php elseif ($field === 'expected_close'): ?>
          <input type="date" name="expected_close" id="expected_close" class="form-control" required>

        <?php elseif ($field === 'value' || $field === 'probability'): ?>
          <input type="number" name="<?= $field ?>" id="<?= $field ?>" class="form-control" required>

        <?php elseif ($field === 'description'): ?>
          <textarea name="description" id="description" class="form-control" rows="3" placeholder="Describe what is being sold..."></textarea>
        <?php else: ?>
          <input type="text" name="<?= $field ?>" id="<?= $field ?>" class="form-control" required>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <button type="submit" class="btn-primary">Save Opportunity</button>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('contact-search');
  const contactSelect = document.getElementById('contact-select');
  if (searchInput && contactSelect) {
    searchInput.addEventListener('input', function() {
      const filter = searchInput.value.toLowerCase();
      for (let i = 0; i < contactSelect.options.length; i++) {
        const option = contactSelect.options[i];
        option.style.display = option.text.toLowerCase().includes(filter) ? '' : 'none';
      }
    });
  }
});
</script>

<?php include_once(__DIR__ . '/layout_end.php'); ?>
