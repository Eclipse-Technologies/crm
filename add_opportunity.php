<?php
// Production: error reporting disabled
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// Prevent accidental output before headers
ob_start();
require_once 'db_mysql.php';
require_once 'csrf_helper.php';
require_once 'simple_auth/middleware.php';
$schema = require __DIR__ . '/opportunity_schema.php';
$contactSchema = require __DIR__ . '/contact_schema.php';
function fetch_companies_mysql() {
  $conn = get_mysql_connection();
  $sql = "SELECT DISTINCT company FROM contacts WHERE company IS NOT NULL AND company != '' ORDER BY company";
  $result = $conn->query($sql);
  $rows = [];
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row['company'];
    }
    $result->free();
  }
  $conn->close();
  return $rows;
}
$companies = fetch_companies_mysql();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $errors[] = 'CSRF validation failed';
  }
  
  // Validate inputs
  if (empty($errors)) {
  // Company is required and is used to resolve a linked contact.
  if (empty($_POST['company_id'])) {
    $errors[] = 'Company is required';
  }
  if (!isset($_POST['value']) || $_POST['value'] === '' || !is_numeric($_POST['value']) || $_POST['value'] < 0) {
    $errors[] = 'Valid opportunity value is required';
  }
  if (!isset($_POST['probability']) || $_POST['probability'] === '' || !is_numeric($_POST['probability']) || $_POST['probability'] < 0 || $_POST['probability'] > 100) {
    $errors[] = 'Probability must be between 0 and 100';
  }
  if (empty($_POST['stage'])) {
    $errors[] = 'Stage is required';
  }
  if (empty($_POST['expected_close'])) {
    $errors[] = 'Expected close date is required';
  }
  if (empty($errors)) {
    $conn = get_mysql_connection();
    $selectedCompany = trim((string) ($_POST['company_id'] ?? ''));
    $contact_id = null;

    $stmtContact = $conn->prepare("SELECT contact_id FROM contacts WHERE company = ? ORDER BY contact_id ASC LIMIT 1");
    if ($stmtContact) {
      $stmtContact->bind_param('s', $selectedCompany);
      $stmtContact->execute();
      $stmtContact->bind_result($resolvedContactId);
      if ($stmtContact->fetch()) {
        $contact_id = $resolvedContactId;
      }
      $stmtContact->close();
    }

    if ($contact_id === null || $contact_id === '') {
      $errors[] = 'Selected company has no contact record. Please add a contact for that company first.';
      $conn->close();
    }

    if (empty($errors)) {
    $value = number_format((float)$_POST['value'], 2, '.', '');
    $stage = $_POST['stage'];
    $probability = (int)$_POST['probability'];
    $expected_close = $_POST['expected_close'];
    $stmt = $conn->prepare("INSERT INTO opportunities (contact_id, value, stage, probability, expected_close) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
      $error = 'Failed to prepare statement: ' . $conn->error;
    } else {
      $stmt->bind_param('sdsss', $contact_id, $value, $stage, $probability, $expected_close);
      if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        if (ob_get_length()) {
          ob_end_clean();
        }
        header('Location: opportunities_list.php?success=1');
        exit;
      } else {
        $error = 'Failed to save opportunity: ' . $stmt->error;
      }
    }
    }
  } else {
    $error = implode(', ', $errors);
  }
  }
}
?>

<?php require_once 'layout_start.php'; ?>

<style>
  .page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 32px;
    border-radius: 12px;
    margin-bottom: 24px;
  }
  
  .page-header h1 {
    margin: 0 0 8px 0;
    font-size: 32px;
    font-weight: 700;
  }
  
  .page-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
  }
  
  .back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 16px;
    font-size: 14px;
  }
  
  .back-link:hover {
    color: #5558dd;
  }
  
  .form-container {
    background: white;
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    max-width: 900px;
    margin: 0auto;
  }
  
  .form-section {
    margin-bottom: 32px;
    padding-bottom: 32px;
    border-bottom: 2px solid #E5E7EB;
  }
  
  .form-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
  }
  
  .form-section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  
  .form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
  }
  
  .form-group {
    display: flex;
    flex-direction: column;
  }
  
  .form-group.full-width {
    grid-column: 1 / -1;
  }
  
  .form-group label {
    font-size: 13px;
    font-weight: 700;
    color: #374151;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  
  .form-group input,
  .form-group select {
    padding: 14px 16px;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    font-size: 15px;
    font-family: inherit;
    transition: all 0.2s;
    background: white;
  }
  
  .form-group input:focus,
  .form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .form-group select {
    cursor: pointer;
  }
  
  .form-help {
    font-size: 12px;
    color: #6B7280;
    margin-top: 6px;
  }
  
  .stage-visual {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    flex-wrap: wrap;
  }
  
  .stage-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: white;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
  }
  
  .stage-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  }
  
  .stage-badge.selected {
    border-color: white;
    box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
  }
  
  .form-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
  }
  
  .btn-primary {
    padding: 14px 32px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
  }
  
  .btn-secondary {
    padding: 14px 32px;
    background: #F3F4F6;
    color: #374151;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
  }
  
  .btn-secondary:hover {
    background: #E5E7EB;
  }
  
  .error-alert {
    background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
    border: 2px solid #EF4444;
    color: #991B1B;
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  
  .probability-slider {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  
  .probability-value {
    font-size: 20px;
    font-weight: 700;
    color: #667eea;
    min-width: 50px;
  }
  
  input[type="range"] {
    flex: 1;
    height: 8px;
    border-radius: 4px;
    background: #E5E7EB;
    outline: none;
    padding: 0;
  }
  
  input[type="range"]::-webkit-slider-thumb {
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #667eea;
    cursor: pointer;
  }
  
  input[type="range"]::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #667eea;
    cursor: pointer;
    border: none;
  }
  
  @media (max-width: 768px) {
    .form-grid {
      grid-template-columns: 1fr;
    }
    
    .page-header {
      padding: 24px;
    }
    
    .form-container {
      padding: 24px;
    }
  }
</style>

<a href="opportunities_list.php" class="back-link">
  ← Back to Opportunities
</a>

<div class="page-header">
  <h1>➕ Create New Opportunity</h1>
  <p>Add a new sales opportunity to your pipeline</p>
</div>

<?php if (!empty($error)): ?>
  <div class="error-alert">
    <span>⚠️</span>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
<?php endif; ?>


<div class="form-container">
  <form method="POST" id="opportunityForm">
    <?php renderCSRFInput(); ?>
    
    <div class="form-section">
      <div class="form-section-title">
        <span>👤</span>
        <span>Contact Information</span>
      </div>
      
      <div class="form-grid">
        <div class="form-group full-width">
          <label for="company_id">Company *</label>
          <select name="company_id" id="company_id" required>
            <option value="">Select a company...</option>
            <?php foreach ($companies as $company): ?>
              <option value="<?= htmlspecialchars($company) ?>" <?= (isset($_POST['company_id']) && $_POST['company_id'] === $company) ? 'selected' : '' ?>>
                <?= htmlspecialchars($company) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-help">Select the company associated with this opportunity</div>
        </div>
      </div>
    </div>
    
    <div class="form-section">
      <div class="form-section-title">
        <span>💰</span>
        <span>Deal Details</span>
      </div>
      
      <div class="form-grid">
        <div class="form-group">
          <label for="value">Opportunity Value *</label>
          <input 
            type="number" 
            name="value" 
            id="value" 
            placeholder="0.00" 
            step="0.01" 
            min="0"
            required
            value="<?= isset($_POST['value']) ? htmlspecialchars($_POST['value']) : '' ?>"
          >
          <div class="form-help">Enter the total value in dollars</div>
        </div>
        
        <div class="form-group">
          <label for="expected_close">Expected Close Date *</label>
          <input 
            type="date" 
            name="expected_close" 
            id="expected_close"
            required
            value="<?= isset($_POST['expected_close']) ? htmlspecialchars($_POST['expected_close']) : '' ?>"
          >
          <div class="form-help">When do you expect to close this deal?</div>
        </div>
      </div>
    </div>
    
    <div class="form-section">
      <div class="form-section-title">
        <span>📊</span>
        <span>Sales Stage & Probability</span>
      </div>
      
      <div class="form-grid">
        <div class="form-group full-width">
          <label for="stage">Sales Stage *</label>
          <select name="stage" id="stage" required>
            <option value="">Select stage...</option>
            <option value="Prospecting" data-probability="10" <?= (isset($_POST['stage']) && $_POST['stage'] == 'Prospecting') ? 'selected' : '' ?>>Prospecting (10% win rate)</option>
            <option value="Proposal" data-probability="25" <?= (isset($_POST['stage']) && $_POST['stage'] == 'Proposal') ? 'selected' : '' ?>>Proposal (25% win rate)</option>
            <option value="Negotiation" data-probability="50" <?= (isset($_POST['stage']) && $_POST['stage'] == 'Negotiation') ? 'selected' : '' ?>>Negotiation (50% win rate)</option>
            <option value="Closed Won" data-probability="100" <?= (isset($_POST['stage']) && $_POST['stage'] == 'Closed Won') ? 'selected' : '' ?>>Closed Won (100%)</option>
            <option value="Closed Lost" data-probability="0" <?= (isset($_POST['stage']) && $_POST['stage'] == 'Closed Lost') ? 'selected' : '' ?>>Closed Lost (0%)</option>
          </select>
          
          <div class="stage-visual" style="margin-top: 16px;">
            <div class="stage-badge" style="background: #6366F1;" data-stage="Prospecting">Prospecting</div>
            <div class="stage-badge" style="background: #8B5CF6;" data-stage="Proposal">Proposal</div>
            <div class="stage-badge" style="background: #F59E0B;" data-stage="Negotiation">Negotiation</div>
            <div class="stage-badge" style="background: #10B981;" data-stage="Closed Won">Closed Won</div>
            <div class="stage-badge" style="background: #EF4444;" data-stage="Closed Lost">Closed Lost</div>
          </div>
        </div>
        
        <div class="form-group full-width">
          <label for="probability">Win Probability *</label>
          <div class="probability-slider">
            <input 
              type="range" 
              name="probability" 
              id="probability" 
              min="0" 
              max="100" 
              value="<?= isset($_POST['probability']) ? htmlspecialchars($_POST['probability']) : '10' ?>"
              step="5"
            >
            <span class="probability-value"><?= isset($_POST['probability']) ? htmlspecialchars($_POST['probability']) : '10' ?>%</span>
          </div>
          <div class="form-help">Estimated likelihood of closing this deal</div>
        </div>
      </div>
    </div>
    
    <div class="form-actions">
      <button type="submit" class="btn-primary">💾 Create Opportunity</button>
      <a href="opportunities_list.php" class="btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>
  // Update probability slider value display
  const probabilitySlider = document.getElementById('probability');
  const probabilityValue = document.querySelector('.probability-value');
  
  probabilitySlider.addEventListener('input', function() {
    probabilityValue.textContent = this.value + '%';
  });
  
  // Auto-update probability based on stage selection
  const stageSelect = document.getElementById('stage');
  stageSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const suggestedProbability = selectedOption.getAttribute('data-probability');
    
    if (suggestedProbability) {
      probabilitySlider.value = suggestedProbability;
      probabilityValue.textContent = suggestedProbability + '%';
    }
    
    // Update visual badges
    document.querySelectorAll('.stage-badge').forEach(badge => {
      badge.classList.remove('selected');
      if (badge.getAttribute('data-stage') === this.value) {
        badge.classList.add('selected');
      }
    });
  });
  
  // Allow clicking stage badges to select stage
  document.querySelectorAll('.stage-badge').forEach(badge => {
    badge.addEventListener('click', function() {
      const stageName = this.getAttribute('data-stage');
      stageSelect.value = stageName;
      stageSelect.dispatchEvent(new Event('change'));
    });
  });
</script>

<?php include_once(__DIR__ . '/layout_end.php'); ?>
