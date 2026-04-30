
<?php
// ENSURE THIS IS THE VERY FIRST LINE, NO WHITESPACE OR BOM ABOVE
// IMPORTANT: Do not output anything before header() redirects!

require_once 'db_mysql.php';
require_once 'csrf_helper.php';
$schema = require __DIR__ . '/opportunity_schema.php';
$contactSchema = require __DIR__ . '/contact_schema.php';

function getOpportunityIdColumn(): string {
  $conn = get_mysql_connection();
  $hasOpportunityId = false;
  $hasId = false;
  if ($result = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'opportunity_id'")) {
    $hasOpportunityId = $result->num_rows > 0;
    $result->free();
  }
  if ($result = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'id'")) {
    $hasId = $result->num_rows > 0;
    $result->free();
  }
  $conn->close();
  if ($hasOpportunityId) {
    return 'opportunity_id';
  }
  return $hasId ? 'id' : 'opportunity_id';
}

// Get opportunity ID
$opportunityId = $_GET['id'] ?? $_POST['id'] ?? null;
$opportunityIdColumn = getOpportunityIdColumn();

if (!$opportunityId) {
    header('Location: opportunities_list.php?error=' . urlencode('No opportunity ID provided'));
    exit;
}

$opportunity = fetch_opportunity_mysql($opportunityId, $schema, $opportunityIdColumn);
if (isset($opportunity) && is_array($opportunity)) {
}
if (!$opportunity) {
  header('Location: opportunities_list.php?error=' . urlencode('Opportunity not found'));
  exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $error = 'CSRF validation failed';
  }

    // Validate inputs
    $errors = [];
    // Contact is now optional; only company is required
    // Company is editable, but not required
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
    if (!isset($error) && empty($errors)) {
        // Update opportunity
        $fields = [
          'value' => $_POST['value'],
          'stage' => $_POST['stage'],
          'probability' => $_POST['probability'],
          'expected_close' => $_POST['expected_close'],
        ];
        // Add description if present
        if (isset($_POST['description'])) {
          $fields['description'] = $_POST['description'];
        }
        // If company is changed, update it in the contacts table for all contacts linked to this opportunity
        if (!empty($_POST['company_id'])) {
          $conn = get_mysql_connection();
          $selectedCompany = trim((string) $_POST['company_id']);
          $currentContactId = trim((string) ($opportunity['contact_id'] ?? ''));

          if ($currentContactId !== '') {
            // Opportunity is linked to a contact: update that contact's company.
            $stmt = $conn->prepare("UPDATE contacts SET company = ? WHERE contact_id = ?");
            if ($stmt) {
              $stmt->bind_param('ss', $selectedCompany, $currentContactId);
              $stmt->execute();
              $stmt->close();
            }
          } else {
            // No linked contact: try to link this opportunity to an existing contact in selected company.
            $stmtFind = $conn->prepare("SELECT contact_id FROM contacts WHERE company = ? ORDER BY contact_id ASC LIMIT 1");
            $matchedContactId = null;
            if ($stmtFind) {
              $stmtFind->bind_param('s', $selectedCompany);
              $stmtFind->execute();
              $stmtFind->bind_result($matchedContactId);
              $stmtFind->fetch();
              $stmtFind->close();
            }

            if ($matchedContactId !== null && $matchedContactId !== '') {
              $stmtLink = $conn->prepare("UPDATE opportunities SET contact_id = ? WHERE {$opportunityIdColumn} = ?");
              if ($stmtLink) {
                $matchedContactIdStr = (string) $matchedContactId;
                $stmtLink->bind_param('ss', $matchedContactIdStr, $opportunityId);
                $stmtLink->execute();
                $stmtLink->close();
                $opportunity['contact_id'] = $matchedContactIdStr;
              }
            } else {
              $errors[] = 'Selected company has no contact record. Create a contact for that company first.';
            }
          }

          $conn->close();
        }

        $result = empty($errors) ? update_opportunity_mysql($opportunityId, $fields, $opportunityIdColumn) : false;
        if ($result) {
          header('Location: opportunities_list.php?success=2');
          exit;
        } else {
          $error = !empty($errors)
            ? implode(', ', $errors)
            : 'Failed to update opportunity.';
        }
      } elseif (!isset($error)) {
        $error = implode(', ', $errors);
    }
}

// Now safe to include layout and output HTML
require_once 'layout_start.php';

// Error/debug handlers must be set AFTER any header() redirects to avoid breaking them
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
  echo '<div style="position:fixed;top:0;left:0;right:0;z-index:2147483647;background:#fffbe6;color:#b45309;border-bottom:3px solid #f59e0b;padding:24px 40px;font-size:22px;font-family:monospace;box-shadow:0 4px 16px rgba(0,0,0,0.18);text-align:center;">'.
    '<strong>PHP Error:</strong> '.htmlspecialchars($errstr).' in '.htmlspecialchars($errfile).' on line '.htmlspecialchars($errline).'</div>';
  flush();
  return false;
});
set_exception_handler(function($e) {
  echo '<div style="position:fixed;top:0;left:0;right:0;z-index:2147483647;background:#fffbe6;color:#b45309;border-bottom:3px solid #f59e0b;padding:24px 40px;font-size:22px;font-family:monospace;box-shadow:0 4px 16px rgba(0,0,0,0.18);text-align:center;">'.
    '<strong>Uncaught Exception:</strong> '.htmlspecialchars($e->getMessage()).' in '.htmlspecialchars($e->getFile()).' on line '.htmlspecialchars($e->getLine()).'</div>';
  flush();
});


function fetch_opportunity_mysql($id, $schema, $idColumn = 'opportunity_id') {
  $conn = get_mysql_connection();
  $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
  $stmt = $conn->prepare("SELECT $fields FROM opportunities WHERE {$idColumn} = ? LIMIT 1");
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param('s', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result ? $result->fetch_assoc() : null;
  if ($result) {
    $result->free();
  }
  $stmt->close();
  return $row;
}

function update_opportunity_mysql($id, $fields, $idColumn = 'opportunity_id') {
  if (empty($fields)) {
    return false;
  }

  $conn = get_mysql_connection();
  $set = [];
  $params = [];
  $types = '';

  foreach ($fields as $k => $v) {
    $set[] = '`' . str_replace('`', '', (string) $k) . '` = ?';
    $params[] = $v;
    $types .= 's';
  }

  $sql = 'UPDATE opportunities SET ' . implode(', ', $set) . " WHERE {$idColumn} = ?";
  $params[] = $id;
  $types .= 's';

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }

  $stmt->bind_param($types, ...$params);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

// No CSV or contacts array used; all company data is fetched directly from the SQL database for the dropdown below.
?>
<style>
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
    margin: 0 auto;
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
<?php
?>

<a href="opportunities_list.php" class="back-link">
  ← Back to Opportunities
</a>

<div class="page-header">
  <h1>✏️ Edit Opportunity #<?= htmlspecialchars($opportunityId) ?></h1>
  <p>Update opportunity details and sales progress</p>
</div>

<?php if (isset($error)): ?>
  <div class="error-alert">
    <span>⚠️</span>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
<?php endif; ?>

<div class="form-container">
  <?php
  require_once 'opportunity_edit_log.php';
  $editHistory = fetch_opportunity_edits($opportunityId);
  if (!empty($editHistory)):
  ?>
  <div class="card mb-4">
    <div class="card-header bg-info text-white">Edit History</div>
    <div class="card-body">
      <table class="table table-bordered table-sm">
        <thead>
          <tr>
            <th>Date</th>
            <th>User</th>
            <th>Field</th>
            <th>Old Value</th>
            <th>New Value</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($editHistory as $edit): ?>
          <tr>
            <td><?= htmlspecialchars($edit['edited_at']) ?></td>
            <td><?= htmlspecialchars($edit['user_id']) ?></td>
            <td><?= htmlspecialchars($edit['field']) ?></td>
            <td><?= htmlspecialchars($edit['old_value']) ?></td>
            <td><?= htmlspecialchars($edit['new_value']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
  <form method="POST" id="opportunityForm">
    <?php renderCSRFInput(); ?>
    <input type="hidden" name="id" value="<?= htmlspecialchars($opportunityId) ?>">
    
    <div class="form-section">
      <div class="form-section-title">
        <span>👤</span>
        <span>Contact Information</span>
      </div>

      <div class="form-grid">
        <div class="form-group full-width">
          <label for="company_id">Company *</label>
          <?php
          // Fetch all companies for dropdown
          $conn = get_mysql_connection();
          $companies = [];
          $result2 = $conn->query("SELECT DISTINCT company FROM contacts WHERE company IS NOT NULL AND company != '' ORDER BY company");
          if ($result2) {
            while ($row = $result2->fetch_assoc()) {
              $companies[] = $row['company'];
            }
            $result2->free();
          }
          $conn->close();
          // Get current company for this opportunity
          $current_company = '';
          $conn = get_mysql_connection();
          $stmt = $conn->prepare("SELECT company FROM contacts WHERE contact_id = (SELECT contact_id FROM opportunities WHERE {$opportunityIdColumn} = ?)");
          $stmt->bind_param('s', $opportunityId);
          $stmt->execute();
          $stmt->bind_result($current_company);
          $stmt->fetch();
          $stmt->close();
          $conn->close();
          ?>
          <div style="padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 8px; background: #F9FAFB; font-size: 15px; margin-bottom: 10px;">
            <select name="company_id" id="company_id" style="width:100%;padding:8px;">
              <option value="">-- Select Company --</option>
              <?php foreach ($companies as $company): ?>
                <option value="<?= htmlspecialchars($company) ?>" <?= ($current_company === $company) ? 'selected' : '' ?>><?= htmlspecialchars($company) ?></option>
              <?php endforeach; ?>
              <?php if (!in_array($current_company, $companies)): ?>
                <option value="<?= htmlspecialchars($current_company) ?>" selected><?= htmlspecialchars($current_company) ?></option>
              <?php endif; ?>
            </select>
          </div>
          <div class="form-help">You can update the company for this opportunity.</div>
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
            value="<?= isset($opportunity['value']) ? htmlspecialchars($opportunity['value']) : '' ?>"
            required
          >
          <div class="form-help">Enter the total value in dollars</div>
        </div>
        <div class="form-group">
          <label for="expected_close">Expected Close Date *</label>
          <input 
            type="date" 
            name="expected_close" 
            id="expected_close"
            value="<?= isset($opportunity['expected_close']) ? htmlspecialchars($opportunity['expected_close']) : '' ?>"
            required
          >
          <div class="form-help">When do you expect to close this deal?</div>
        </div>
        <div class="form-group full-width">
          <label for="description">Description</label>
          <textarea name="description" id="description" class="form-control" rows="3" placeholder="Describe what is being sold..."><?= isset($opportunity['description']) ? htmlspecialchars($opportunity['description']) : '' ?></textarea>
          <div class="form-help">Provide details about the product/service being sold.</div>
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
            <option value="Prospecting" data-probability="10" <?= isset($opportunity['stage']) && $opportunity['stage'] === 'Prospecting' ? 'selected' : '' ?>>Prospecting (10% win rate)</option>
            <option value="Proposal" data-probability="25" <?= isset($opportunity['stage']) && $opportunity['stage'] === 'Proposal' ? 'selected' : '' ?>>Proposal (25% win rate)</option>
            <option value="Negotiation" data-probability="50" <?= isset($opportunity['stage']) && $opportunity['stage'] === 'Negotiation' ? 'selected' : '' ?>>Negotiation (50% win rate)</option>
            <option value="Closed Won" data-probability="100" <?= isset($opportunity['stage']) && $opportunity['stage'] === 'Closed Won' ? 'selected' : '' ?>>Closed Won (100%)</option>
            <option value="Closed Lost" data-probability="0" <?= isset($opportunity['stage']) && $opportunity['stage'] === 'Closed Lost' ? 'selected' : '' ?>>Closed Lost (0%)</option>
          </select>
          
          <div class="stage-visual" style="margin-top: 16px;">
            <div class="stage-badge <?= $opportunity['stage'] === 'Prospecting' ? 'selected' : '' ?>" style="background: #6366F1;" data-stage="Prospecting">Prospecting</div>
            <div class="stage-badge <?= $opportunity['stage'] === 'Proposal' ? 'selected' : '' ?>" style="background: #8B5CF6;" data-stage="Proposal">Proposal</div>
            <div class="stage-badge <?= $opportunity['stage'] === 'Negotiation' ? 'selected' : '' ?>" style="background: #F59E0B;" data-stage="Negotiation">Negotiation</div>
            <div class="stage-badge <?= $opportunity['stage'] === 'Closed Won' ? 'selected' : '' ?>" style="background: #10B981;" data-stage="Closed Won">Closed Won</div>
            <div class="stage-badge <?= $opportunity['stage'] === 'Closed Lost' ? 'selected' : '' ?>" style="background: #EF4444;" data-stage="Closed Lost">Closed Lost</div>
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
              value="<?= isset($opportunity['probability']) ? htmlspecialchars($opportunity['probability']) : '0' ?>"
              step="5"
            >
            <span class="probability-value">
              <?= isset($opportunity['probability']) ? htmlspecialchars($opportunity['probability']) : '0' ?>%
            </span>
          </div>
          <div class="form-help">Estimated likelihood of closing this deal</div>
        </div>
      </div>
    </div>
    
    <div class="form-actions">
      <button type="submit" class="btn-primary">💾 Update Opportunity</button>
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
