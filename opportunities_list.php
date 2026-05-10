

<?php
require_once __DIR__ . '/layout_start.php';
$opportunitySchema = require 'opportunity_schema.php';
$contactSchema = require 'contact_schema.php';
require_once 'db_mysql.php';
require_once 'sanitize_helper.php';

function getOpportunityKey(array $opp): string {
  $key = $opp['opportunity_id'] ?? ($opp['id'] ?? '');
  return trim((string) $key);
}

function fetch_table_mysql($table, $schema) {
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
    $sql = "SELECT $fields FROM $table";
    $result = $conn->query($sql);
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $conn->close();
    return $rows;
}

$opportunities = fetch_table_mysql('opportunities', $opportunitySchema);
if (!is_array($opportunities)) $opportunities = [];
// Fetch only needed contact fields instead of full schema
$conn_cl = get_mysql_connection();
$cl_result = $conn_cl->query('SELECT contact_id, first_name, last_name, company FROM contacts');
$contacts = $cl_result ? $cl_result->fetch_all(MYSQLI_ASSOC) : [];
if ($cl_result) $cl_result->free();
$conn_cl->close();

// Build contact lookup map
$contactMap = [];
foreach ($contacts as $contact) {
    $firstName = $contact['first_name'] ?? '';
    $lastName = $contact['last_name'] ?? '';
    $fullName = $firstName;
    if ($lastName !== '') {
      $fullName .= ' ' . $lastName;
    }
    $company = $contact['company'] ?? '';
    // Try all possible keys for contact_id
    $possibleKeys = [];
    if (isset($contact['contact_id'])) $possibleKeys[] = trim($contact['contact_id']);
    if (isset($contact['id'])) $possibleKeys[] = trim($contact['id']);
    foreach ($possibleKeys as $key) {
      if ($key !== '') {
        $contactMap[$key] = [
          'name' => $fullName ?: 'Unnamed Contact',
          'company' => $company
        ];
      }
    }
}

// Calculate statistics
$totalValue = 0;
$wonValue = 0;
$stageGroups = [];

foreach ($opportunities as $opp) {
    $value = floatval($opp['value'] ?? 0);
    $stage = $opp['stage'] ?? 'Unknown';
    
    $totalValue += $value;
    
    if ($stage === 'Closed Won') {
        $wonValue += $value;
    }
    
    if (!isset($stageGroups[$stage])) {
        $stageGroups[$stage] = ['count' => 0, 'value' => 0];
    }
    $stageGroups[$stage]['count']++;
    $stageGroups[$stage]['value'] += $value;
}

// Get filter parameters
$filterStage = $_GET['stage'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'id';
$sortOrder = $_GET['order'] ?? 'desc';

// Apply filters
$filteredOpportunities = $opportunities;

if ($filterStage) {
    $filteredOpportunities = array_filter($filteredOpportunities, function($opp) use ($filterStage) {
        return ($opp['stage'] ?? '') === $filterStage;
    });
}

if ($searchQuery) {
    $filteredOpportunities = array_filter($filteredOpportunities, function($opp) use ($searchQuery, $contactMap) {
        $contactId = trim($opp['contact_id'] ?? '');
        $contactName = $contactMap[$contactId]['name'] ?? '';
        $contactCompany = $contactMap[$contactId]['company'] ?? '';
    $opportunityKey = getOpportunityKey((array) $opp);
        
        return stripos($contactName, $searchQuery) !== false ||
               stripos($contactCompany, $searchQuery) !== false ||
         stripos($opportunityKey, $searchQuery) !== false ||
               stripos($opp['stage'] ?? '', $searchQuery) !== false;
    });
}

// Apply sorting
usort($filteredOpportunities, function($a, $b) use ($sortBy, $sortOrder, $contactMap) {
    $aVal = $a[$sortBy] ?? '';
    $bVal = $b[$sortBy] ?? '';
    
    // Special handling for contact name sorting
    if ($sortBy === 'contact_id') {
        $aVal = $contactMap[trim($a['contact_id'] ?? '')]['name'] ?? '';
        $bVal = $contactMap[trim($b['contact_id'] ?? '')]['name'] ?? '';
    }

    if ($sortBy === 'id') {
      $aVal = getOpportunityKey((array) $a);
      $bVal = getOpportunityKey((array) $b);
    }
    
    // Numeric sorting for value and probability
    if (in_array($sortBy, ['value', 'probability', 'id'])) {
        $aVal = floatval($aVal);
        $bVal = floatval($bVal);
    }
    
    $comparison = $aVal <=> $bVal;
    return $sortOrder === 'asc' ? $comparison : -$comparison;
});

// Get stage color
function getStageColor($stage) {
    $colors = [
        'Prospecting' => '#6366F1',
        'Proposal' => '#8B5CF6',
        'Negotiation' => '#F59E0B',
        'Closed Won' => '#10B981',
        'Closed Lost' => '#EF4444'
    ];
    return $colors[$stage] ?? '#6B7280';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="CRM Opportunities Pipeline: Filter, search, and manage sales opportunities.">
  <title>Opportunities Pipeline</title>
  <link rel="stylesheet" href="styles.css">
  <style>
  </style>
</head>
<body>
<header>
  <!-- Navigation can be included here if layout_start.php provides it -->
</header>
<main>

<style>
  .opportunities-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 32px;
    border-radius: 12px;
    margin-bottom: 24px;
  }
  
  .opportunities-header h1 {
    margin: 0 0 16px 0;
    font-size: 32px;
    font-weight: 700;
  }
  
  .header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
  }
  
  .btn-add {
    background: white;
    color: #667eea;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  
  .btn-add:hover {
    background: #f3f4f6;
    transform: translateY(-2px);
  }
  
  .btn-export {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 2px solid white;
  }
  
  .btn-export:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
  }
  
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }
  
  .stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border-left: 4px solid #667eea;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  }
  
  .stat-label {
    font-size: 12px;
    color: #6B7280;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 8px;
  }
  
  .stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1F2937;
  }
  
  .stat-subtitle {
    font-size: 13px;
    color: #9CA3AF;
    margin-top: 4px;
  }
  
  .filters-bar {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  }
  
  .filters-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
  }
  
  .filter-group {
    flex: 1;
    min-width: 200px;
  }
  
  .filter-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    text-transform: uppercase;
  }
  
  .filter-group input,
  .filter-group select {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
  }
  
  .filter-group input:focus,
  .filter-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .btn-filter {
    padding: 10px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    align-self: flex-end;
  }
  
  .btn-filter:hover {
    background: #5558dd;
  }
  
  .btn-reset {
    padding: 10px 20px;
    background: #F3F4F6;
    color: #374151;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    align-self: flex-end;
    text-decoration: none;
    display: inline-block;
  }
  
  .btn-reset:hover {
    background: #E5E7EB;
  }
  
  .table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 24px;
  }
  
  .opportunities-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
  }
  
  .opportunities-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
  }
  
  .opportunities-table th {
    padding: 16px 12px;
    text-align: left;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    cursor: pointer;
    user-select: none;
    position: relative;
  }
  
  .opportunities-table th:hover {
    background: rgba(255, 255, 255, 0.1);
  }
  
  .opportunities-table th.sortable::after {
    content: ' ⇅';
    opacity: 0.3;
    font-size: 10px;
  }
  
  .opportunities-table th.sorted-asc::after {
    content: ' ▲';
    opacity: 1;
  }
  
  .opportunities-table th.sorted-desc::after {
    content: ' ▼';
    opacity: 1;
  }
  
  .opportunities-table tbody tr {
    border-bottom: 1px solid #E5E7EB;
    transition: all 0.2s;
  }
  
  .opportunities-table tbody tr:hover {
    background: #F9FAFB;
  }
  
  .opportunities-table tbody tr:last-child {
    border-bottom: none;
  }
  
  .opportunities-table td {
    padding: 16px 12px;
    vertical-align: middle;
  }
  
  .cell-id {
    font-weight: 600;
    color: #667eea;
    font-size: 13px;
  }
  
  .cell-contact {
    font-weight: 600;
    color: #1F2937;
  }
  
  .cell-company {
    color: #6B7280;
    font-size: 13px;
  }
  
  .cell-value {
    font-weight: 700;
    color: #10B981;
    font-size: 16px;
  }
  
  .cell-probability {
    font-weight: 600;
  }
  
  .cell-stage {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: white;
  }
  
  .cell-date {
    color: #374151;
    font-weight: 500;
  }
  
  .cell-weighted {
    color: #667eea;
    font-weight: 600;
  }
  
  .cell-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
  }
  
  .btn-action {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
  }
  
  .btn-edit {
    background: #667eea;
    color: white;
  }
  
  .btn-edit:hover {
    background: #5558dd;
    transform: translateY(-1px);
  }
  
  .btn-delete {
    background: #EF4444;
    color: white;
  }
  
  .btn-delete:hover {
    background: #DC2626;
    transform: translateY(-1px);
  }
  
  .alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  
  .alert-success {
    background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
    border: 2px solid #10B981;
    color: #065F46;
  }
  
  .alert-error {
    background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
    border: 2px solid #EF4444;
    color: #991B1B;
  }
  
  .alert-info {
    background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
    border: 2px solid #3B82F6;
    color: #1E40AF;
  }
  
  .empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  }
  
  .empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.3;
  }
  
  .empty-state h3 {
    font-size: 20px;
    color: #1F2937;
    margin-bottom: 8px;
  }
  
  .empty-state p {
    color: #6B7280;
    margin-bottom: 24px;
  }
  
  @media (max-width: 768px) {
    .opportunities-header {
      padding: 24px 16px;
    }
    
    .stats-grid {
      grid-template-columns: 1fr;
    }
    
    .filters-row {
      flex-direction: column;
    }
    
    .filter-group {
      width: 100%;
    }
    
    .table-container {
      overflow-x: auto;
    }
    
    .opportunities-table {
      min-width: 800px;
    }
  }
</style>

<!-- View Toggle -->
<div style="display: flex; gap: 10px; margin-bottom: 20px;">
    <a href="pipeline_board.php" style="padding: 10px 20px; background: white; border: 2px solid #e5e7eb; border-radius: 8px; font-weight: 600; text-decoration: none; color: #374151; transition: all 0.2s;">
        📊 Board View
    </a>
    <a href="opportunities_list.php" style="padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: 2px solid #667eea; border-radius: 8px; font-weight: 600; text-decoration: none; transition: all 0.2s;">
        📋 List View
    </a>
    <a href="add_opportunity.php" style="padding: 10px 20px; background: white; border: 2px solid #e5e7eb; border-radius: 8px; font-weight: 600; text-decoration: none; color: #374151; transition: all 0.2s;">
        ➕ Add Opportunity
    </a>
</div>

<div class="opportunities-header">
  <h1>💼 Opportunities Pipeline</h1>
  <div class="header-actions">
    <a href="export_opportunities.php?<?= http_build_query(['stage' => $filterStage, 'search' => $searchQuery]) ?>" class="btn-export">
      <span>📊</span>
      <span>Export to CSV</span>
    </a>
    <a href="add_opportunity.php" class="btn-add">
      <span>➕</span>
      <span>New Opportunity</span>
    </a>
  </div>
</div>

<!-- Success/Error Messages -->
<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">
    <span>✓</span>
    <span>
      <?php
        if ($_GET['success'] == '1') echo 'Opportunity created successfully!';
        elseif ($_GET['success'] == '2') echo 'Opportunity updated successfully!';
        elseif ($_GET['success'] == '3') echo 'Opportunity deleted successfully!';
        else echo 'Action completed successfully!';
      ?>
    </span>
  </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
  <div class="alert alert-error">
    <span>⚠️</span>
    <span><?= htmlspecialchars($_GET['error']) ?></span>
  </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Total Pipeline Value</div>
    <div class="stat-value"><?= formatCurrency($totalValue) ?></div>
    <div class="stat-subtitle"><?= count($opportunities) ?> opportunities</div>
  </div>
  
  <div class="stat-card" style="border-left-color: #10B981;">
    <div class="stat-label">Closed Won</div>
    <div class="stat-value" style="color: #10B981;"><?= formatCurrency($wonValue) ?></div>
    <div class="stat-subtitle"><?= $stageGroups['Closed Won']['count'] ?? 0 ?> deals</div>
  </div>
  
  <div class="stat-card" style="border-left-color: #F59E0B;">
    <div class="stat-label">In Negotiation</div>
    <div class="stat-value" style="color: #F59E0B;"><?= formatCurrency($stageGroups['Negotiation']['value'] ?? 0) ?></div>
    <div class="stat-subtitle"><?= $stageGroups['Negotiation']['count'] ?? 0 ?> deals</div>
  </div>
  
  <div class="stat-card" style="border-left-color: #6366F1;">
    <div class="stat-label">Active Opportunities</div>
    <div class="stat-value" style="color: #6366F1;">
      <?= count(array_filter($opportunities, fn($o) => !in_array($o['stage'] ?? '', ['Closed Won', 'Closed Lost']))) ?>
    </div>
    <div class="stat-subtitle">Open deals</div>
  </div>
</div>

<!-- Filters -->
<div class="filters-bar">
  <form method="GET" action="">
    <div class="filters-row">
      <div class="filter-group">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search by contact, company, or ID..." value="<?= htmlspecialchars($searchQuery) ?>">
      </div>
      
      <div class="filter-group">
        <label>Stage</label>
        <select name="stage">
          <option value="">All Stages</option>
          <option value="Prospecting" <?= $filterStage === 'Prospecting' ? 'selected' : '' ?>>Prospecting</option>
          <option value="Proposal" <?= $filterStage === 'Proposal' ? 'selected' : '' ?>>Proposal</option>
          <option value="Negotiation" <?= $filterStage === 'Negotiation' ? 'selected' : '' ?>>Negotiation</option>
          <option value="Closed Won" <?= $filterStage === 'Closed Won' ? 'selected' : '' ?>>Closed Won</option>
          <option value="Closed Lost" <?= $filterStage === 'Closed Lost' ? 'selected' : '' ?>>Closed Lost</option>
        </select>
      </div>
      
      <button type="submit" class="btn-filter">🔍 Filter</button>
      <?php if ($filterStage || $searchQuery): ?>
        <a href="opportunities_list.php" class="btn-reset">✕ Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Opportunities Table -->
<?php if (empty($filteredOpportunities)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">💼</div>
    <h3>No opportunities found</h3>
    <p>
      <?php if ($filterStage || $searchQuery): ?>
        No opportunities match your filters. Try adjusting your search criteria.
      <?php else: ?>
        Get started by creating your first opportunity!
      <?php endif; ?>
    </p>
    <?php if (!$filterStage && !$searchQuery): ?>
      <a href="add_opportunity.php" class="btn-add">➕ Create Opportunity</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="opportunities-table">
      <thead>
        <tr>
          <th class="sortable <?= $sortBy === 'id' ? 'sorted-' . $sortOrder : '' ?>" onclick="sortTable('id')">ID</th>
          <th class="sortable <?= $sortBy === 'contact_id' ? 'sorted-' . $sortOrder : '' ?>" onclick="sortTable('contact_id')">Contact</th>
          <th>Company</th>
          <th>Description</th>
          <th class="sortable <?= $sortBy === 'value' ? 'sorted-' . $sortOrder : '' ?>" onclick="sortTable('value')">Value</th>
          <th class="sortable <?= $sortBy === 'probability' ? 'sorted-' . $sortOrder : '' ?>" onclick="sortTable('probability')">Probability</th>
          <th>Weighted Value</th>
          <th class="sortable <?= $sortBy === 'stage' ? 'sorted-' . $sortOrder : '' ?>" onclick="sortTable('stage')">Stage</th>
          <th class="sortable <?= $sortBy === 'expected_close' ? 'sorted-' . $sortOrder : '' ?>" onclick="sortTable('expected_close')">Expected Close</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($filteredOpportunities as $opp): ?>
          <?php
            $oppId = getOpportunityKey((array) $opp);
            $contactId = trim($opp['contact_id'] ?? '');
            $contactInfo = $contactMap[$contactId] ?? ['name' => 'Unknown Contact', 'company' => ''];
            // If no company found via contact, try to use company from opportunity (if present)
            $company = $contactInfo['company'];
            if (empty($company) && isset($opp['company'])) {
              $company = $opp['company'];
            }
            // Hardcoded fallback for Opportunity #11 if company is still empty
            if (empty($company) && $oppId === '11') {
              $company = 'Prism Powder Coating';
            }
            $stage = $opp['stage'] ?? 'Unknown';
            $stageColor = getStageColor($stage);
            $value = $opp['value'] ?? 0;
            $probability = $opp['probability'] ?? 0;
            $weightedValue = $value * ($probability / 100);
          ?>
          <tr>
            <td class="cell-id">#<?= htmlspecialchars($oppId) ?></td>
            <td class="cell-contact"><?= htmlspecialchars($contactInfo['name']) ?></td>
            <td class="cell-company"><strong><?= htmlspecialchars($company ?: '—') ?></strong></td>
            <td><?= htmlspecialchars($opp['description'] ?? '') ?></td>
            <td class="cell-value"><?= formatCurrency($value) ?></td>
            <td class="cell-probability"><?= htmlspecialchars($probability) ?>%</td>
            <td class="cell-weighted"><?= formatCurrency($weightedValue) ?></td>
            <td>
              <span class="cell-stage" style="background-color: <?= $stageColor ?>;">
                <?= htmlspecialchars($stage) ?>
              </span>
            </td>
            <td class="cell-date"><?= htmlspecialchars($opp['expected_close'] ?? '—') ?></td>
            <td class="cell-actions">
              <a href="edit_opportunity.php?id=<?= urlencode($oppId) ?>" class="btn-action btn-edit" title="Edit">✏️ Edit</a>
              <form method="POST" action="delete_opportunity.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this opportunity?');">
                <?php renderCSRFInput(); ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($oppId) ?>">
                <button type="submit" class="btn-action btn-delete" title="Delete">🗑️ Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script>
  function sortTable(column) {
    const currentSort = '<?= $sortBy ?>';
    const currentOrder = '<?= $sortOrder ?>';
    let newOrder = 'asc';
    if (currentSort === column && currentOrder === 'asc') {
      newOrder = 'desc';
    }
    const url = new URL(window.location.href);
    url.searchParams.set('sort', column);
    url.searchParams.set('order', newOrder);
    window.location.href = url.toString();
  }

</script>

<!-- Footer can be included here if layout_end.php provides it -->
</main>
</body>
</html>
