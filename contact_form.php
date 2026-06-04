<?php
$pageTitle = 'Add New Contact';
require_once __DIR__ . '/layout_start.php';
require_once __DIR__ . '/sanitize_helper.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/customer_type_helper.php';
if (!function_exists('get_customer_type_options')) {
  function get_customer_type_options(): array { return []; }
}
$schema = require __DIR__ . '/contact_schema.php';
$customerTypeOptions = get_customer_type_options();
// Pre-fill company if provided in URL
$prefill_company = isset($_GET['company']) ? trim($_GET['company']) : '';
?>
<style>
  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
  }
  .form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-start;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
  }
</style>
<main>
<section class="page-header">
  <h1>Add New Contact</h1>
  <div class="page-actions">
    <a href="contacts_list.php" class="btn btn-outline">Back to Contacts</a>
  </div>
</section>
<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
  <div class="alert alert-success">&#10003; Contact saved successfully.</div>
<?php endif; ?>
<section class="card">
  <div class="card-header">
    <h3>Contact Information</h3>
  </div>
  <div class="card-body">
    <form id="contact-form" action="add_contact.php" method="POST" class="modern-form">
      <?php renderCSRFInput(); ?>
      <input type="text" name="website_url" value="" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0;">
      <div class="form-grid">
        <?php foreach ($schema as $field): ?>
          <?php if ($field === 'contact_id') continue; ?>
          <div class="form-group">
            <label for="<?= e($field) ?>"><?= e($field === 'tags' ? 'Customer Type' : ucwords(str_replace('_', ' ', $field))) ?>:</label>
            <?php if ($field === 'notes'): ?>
              <textarea name="notes" id="notes" class="form-control" placeholder="Notes"></textarea>
            <?php elseif ($field === 'company'): ?>
              <input type="text" name="company" id="company" class="form-control" required aria-required="true" value="<?= htmlspecialchars($prefill_company) ?>">
            <?php elseif ($field === 'tags'): ?>
              <select name="tags" id="tags" class="form-control" onchange="toggleCustomCustomerType(this.value)">
                <option value="">-- Select Customer Type --</option>
                <?php foreach ($customerTypeOptions as $type): ?>
                  <option value="<?= e($type) ?>"><?= e($type) ?></option>
                <?php endforeach; ?>
                <option value="__custom__">+ Add New Type</option>
              </select>
              <input type="text" name="tags_custom" id="tags_custom" class="form-control mt-2" placeholder="Enter custom customer type" style="display:none;">
            <?php else: ?>
              <input type="<?= $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') ?>"
                     name="<?= e($field) ?>" id="<?= e($field) ?>" class="form-control"
                     <?= $field === 'company' ? 'required' : '' ?> aria-required="true">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Contact</button>
        <a href="contacts_list.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</section>
<!-- Footer can be included here if layout_end.php provides it -->
</main>
<script>
function toggleCustomCustomerType(value) {
  const customInput = document.getElementById('tags_custom');
  if (!customInput) return;
  customInput.style.display = value === '__custom__' ? 'block' : 'none';
  if (value !== '__custom__') {
    customInput.value = '';
  }
}
</script>
<?php include_once __DIR__ . '/layout_end.php'; ?>
