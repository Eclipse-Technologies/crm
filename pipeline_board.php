<?php
require_once 'db_mysql.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/admin_sql_helper.php';
$opportunitySchema = require 'opportunity_schema.php';
$contactSchema = require 'contact_schema.php';
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

function getOpportunityKey(array $opp): string {
    $key = $opp['opportunity_id'] ?? ($opp['id'] ?? '');
    return trim((string) $key);
}

$pageTitle = 'Sales Pipeline Board';
include_once(__DIR__ . '/layout_start.php');

$contacts = fetch_table_mysql('contacts', $contactSchema);

// Index contacts by ID
$contactsById = [];
foreach ($contacts as $c) {
    $key = trim((string) ($c['contact_id'] ?? ($c['id'] ?? '')));
    if ($key !== '') {
        $contactsById[$key] = $c;
    }
}

// Define pipeline stages (matches opportunities system)
$stages = [
    'Prospecting' => ['color' => '#6366F1', 'icon' => '🔍'],
    'Proposal' => ['color' => '#8B5CF6', 'icon' => '📄'],
    'Negotiation' => ['color' => '#F59E0B', 'icon' => '💬'],
    'Closed Won' => ['color' => '#10B981', 'icon' => '🎉'],
    'Closed Lost' => ['color' => '#EF4444', 'icon' => '❌']
];

// Group opportunities by stage
$opportunitiesByStage = [];
foreach ($stages as $stageName => $stageInfo) {
    $opportunitiesByStage[$stageName] = [];
}

foreach ($opportunities as $opp) {
    $stage = $opp['stage'] ?? 'Prospecting';
    if (isset($opportunitiesByStage[$stage])) {
        $opportunitiesByStage[$stage][] = $opp;
    }
}

// Calculate statistics for each stage
$stageStats = [];
foreach ($stages as $stageName => $stageInfo) {
    $stageOpps = $opportunitiesByStage[$stageName];
    $count = count($stageOpps);
    $totalValue = array_reduce($stageOpps, function($sum, $opp) {
        return $sum + ((float)($opp['value'] ?? 0));
    }, 0);
    $weightedValue = array_reduce($stageOpps, function($sum, $opp) {
        $value = (float)($opp['value'] ?? 0);
        $prob = (float)($opp['probability'] ?? 0) / 100;
        return $sum + ($value * $prob);
    }, 0);
    
    $stageStats[$stageName] = [
        'count' => $count,
        'total_value' => $totalValue,
        'weighted_value' => $weightedValue
    ];
}

// Handle AJAX stage update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stage'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
        exit;
    }

    $oppId = $_POST['opportunity_id'] ?? '';
    $newStage = $_POST['new_stage'] ?? '';

    $probabilities = [
        'Prospecting' => 10,
        'Proposal' => 25,
        'Negotiation' => 50,
        'Closed Won' => 100,
        'Closed Lost' => 0
    ];

    if (!isset($probabilities[$newStage])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid stage']);
        exit;
    }

    $conn = get_mysql_connection();
    $idColumn = adminOpportunityIdColumn($conn);
    $stmt = $conn->prepare("UPDATE opportunities SET stage = ?, probability = ? WHERE {$idColumn} = ?");
    if (!$stmt) {
        $conn->close();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to prepare statement']);
        exit;
    }

    $newProbability = $probabilities[$newStage];
    $stmt->bind_param('sis', $newStage, $newProbability, $oppId);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => (bool) $ok]);
    exit;
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '$' . number_format($amount, 0);
    }
}
?>

<style>
/* Pipeline Board Styles */
.pipeline-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.pipeline-header h1 {
    margin: 0 0 15px 0;
    font-size: 32px;
    font-weight: 700;
}

.pipeline-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.stat-card {
    background: rgba(255,255,255,0.15);
    padding: 15px;
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.stat-label {
    font-size: 12px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    margin-top: 5px;
}

/* Pipeline Board */
.pipeline-board {
    display: flex;
    gap: 20px;
    overflow-x: auto;
    padding: 10px 0 30px 0;
    min-height: 600px;
}

.pipeline-column {
    flex: 0 0 300px;
    background: #f9fafb;
    border-radius: 12px;
    padding: 15px;
    display: flex;
    flex-direction: column;
    max-height: 80vh;
}

.column-header {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.column-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 15px;
}

.column-count {
    background: rgba(255,255,255,0.3);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.column-stats {
    font-size: 11px;
    margin-top: 8px;
    opacity: 0.95;
    font-weight: 600;
}

.cards-container {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding-right: 5px;
}

.deal-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    cursor: move;
    transition: all 0.2s;
    border-left: 4px solid;
}

.deal-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.deal-card.dragging {
    opacity: 0.5;
}

.deal-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.deal-id {
    font-size: 11px;
    color: #9ca3af;
    font-weight: 600;
}

.deal-value {
    font-size: 18px;
    font-weight: 700;
    color: #10B981;
}

.deal-contact {
    font-weight: 600;
    font-size: 14px;
    color: #1f2937;
    margin-bottom: 4px;
}

.deal-company {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 8px;
}

.deal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #e5e7eb;
}

.deal-probability {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
}

.probability-bar {
    width: 60px;
    height: 4px;
    background: #e5e7eb;
    border-radius: 2px;
    overflow: hidden;
}

.probability-fill {
    height: 100%;
    background: linear-gradient(90deg, #3B82F6, #10B981);
    border-radius: 2px;
}

.deal-date {
    font-size: 11px;
    color: #9ca3af;
}

.deal-actions {
    display: flex;
    gap: 5px;
    margin-top: 10px;
}

.deal-action-btn {
    flex: 1;
    padding: 6px;
    background: #f3f4f6;
    border: none;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.deal-action-btn:hover {
    background: #e5e7eb;
}

.drop-zone {
    min-height: 100px;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 13px;
    padding: 20px;
    text-align: center;
}

.drop-zone.drag-over {
    border-color: #3B82F6;
    background: #eff6ff;
    color: #3B82F6;
}

/* Scrollbar styling */
.cards-container::-webkit-scrollbar {
    width: 6px;
}

.cards-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.cards-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.cards-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.view-toggle {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.view-btn {
    padding: 10px 20px;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    color: #374151;
}

.view-btn:hover {
    border-color: #667eea;
    color: #667eea;
}

.view-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
}

@media (max-width: 768px) {
    .pipeline-board {
        flex-direction: column;
    }
    
    .pipeline-column {
        flex: 1 1 auto;
        max-height: none;
    }
}
</style>

<div class="pipeline-header">
    <h1>📊 Sales Pipeline Board</h1>
    <p style="margin: 0; opacity: 0.9;">Drag and drop opportunities between stages to update</p>
    
    <div class="pipeline-stats">
        <div class="stat-card">
            <div class="stat-label">Total Pipeline</div>
            <div class="stat-value">
                <?php 
                $totalPipeline = array_reduce($opportunities, function($sum, $opp) {
                    return $sum + ((float)($opp['value'] ?? 0));
                }, 0);
                echo formatCurrency($totalPipeline);
                ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Weighted Value</div>
            <div class="stat-value">
                <?php 
                $weightedTotal = array_reduce($opportunities, function($sum, $opp) {
                    $value = (float)($opp['value'] ?? 0);
                    $prob = (float)($opp['probability'] ?? 0) / 100;
                    return $sum + ($value * $prob);
                }, 0);
                echo formatCurrency($weightedTotal);
                ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Deals</div>
            <div class="stat-value"><?= count($opportunities) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Close Rate</div>
            <div class="stat-value">
                <?php
                $closedWonCount = count($opportunitiesByStage['Closed Won'] ?? []);
                $closedLostCount = count($opportunitiesByStage['Closed Lost'] ?? []);
                $totalClosed = $closedWonCount + $closedLostCount;
                $closeRate = $totalClosed > 0 ? round(($closedWonCount / $totalClosed) * 100) : 0;
                echo $closeRate . '%';
                ?>
            </div>
        </div>
    </div>
</div>

<div class="view-toggle">
    <a href="pipeline_board.php" class="view-btn active">📊 Board View</a>
    <a href="opportunities_list.php" class="view-btn">📋 List View</a>
    <a href="add_opportunity.php" class="view-btn">➕ Add Opportunity</a>
</div>

<div class="pipeline-board">
    <?php foreach ($stages as $stageName => $stageInfo): ?>
        <div class="pipeline-column" data-stage="<?= htmlspecialchars($stageName) ?>">
            <div class="column-header" style="background: <?= $stageInfo['color'] ?>;">
                <div>
                    <div class="column-title">
                        <span><?= $stageInfo['icon'] ?></span>
                        <span><?= htmlspecialchars($stageName) ?></span>
                        <span class="column-count"><?= $stageStats[$stageName]['count'] ?></span>
                    </div>
                    <div class="column-stats">
                        <?= formatCurrency($stageStats[$stageName]['total_value']) ?>
                        (<?= formatCurrency($stageStats[$stageName]['weighted_value']) ?> weighted)
                    </div>
                </div>
            </div>
            
            <div class="cards-container">
                <?php if (empty($opportunitiesByStage[$stageName])): ?>
                    <div class="drop-zone">
                        Drop opportunities here
                    </div>
                <?php else: ?>
                    <?php foreach ($opportunitiesByStage[$stageName] as $opp): ?>
                        <?php
                        $contact = $contactsById[$opp['contact_id'] ?? ''] ?? null;
                        $contactName = $contact ? trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) : 'Unknown';
                        $company = $contact['company'] ?? 'No Company';
                        ?>
                        <div class="deal-card" 
                             draggable="true" 
                             data-id="<?= htmlspecialchars(getOpportunityKey((array) $opp)) ?>"
                             style="border-left-color: <?= $stageInfo['color'] ?>;">
                            <div class="deal-header">
                                <span class="deal-id">#<?= htmlspecialchars(getOpportunityKey((array) $opp)) ?></span>
                                <span class="deal-value"><?= formatCurrency($opp['value'] ?? 0) ?></span>
                            </div>
                            
                            <div class="deal-contact"><?= htmlspecialchars($contactName) ?></div>
                            <div class="deal-company"><?= htmlspecialchars($company) ?></div>
                            
                            <div class="deal-footer">
                                <div class="deal-probability">
                                    <div class="probability-bar">
                                        <div class="probability-fill" style="width: <?= $opp['probability'] ?? 0 ?>%;"></div>
                                    </div>
                                    <span><?= $opp['probability'] ?? 0 ?>%</span>
                                </div>
                                <div class="deal-date">
                                    <?= $opp['expected_close'] ? date('M d', strtotime($opp['expected_close'])) : '—' ?>
                                </div>
                            </div>
                            
                            <div class="deal-actions">
                                <a href="edit_opportunity.php?id=<?= htmlspecialchars(getOpportunityKey((array) $opp)) ?>" class="deal-action-btn">✏️ Edit</a>
                                <a href="contact_view.php?id=<?= htmlspecialchars($opp['contact_id']) ?>" class="deal-action-btn">👤 Contact</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
// Drag and Drop functionality
let draggedElement = null;

document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.deal-card');
    const columns = document.querySelectorAll('.pipeline-column');
    
    // Make cards draggable
    cards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });
    
    // Make columns accept drops
    columns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('drop', handleDrop);
        column.addEventListener('dragleave', handleDragLeave);
    });
});

function handleDragStart(e) {
    draggedElement = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    
    const cardsContainer = this.querySelector('.cards-container');
    cardsContainer.classList.add('drag-over');
    
    return false;
}

function handleDragLeave(e) {
    const cardsContainer = this.querySelector('.cards-container');
    if (e.target === cardsContainer) {
        cardsContainer.classList.remove('drag-over');
    }
}

function handleDrop(e) {
    e.stopPropagation();
    e.preventDefault();
    
    const cardsContainer = this.querySelector('.cards-container');
    cardsContainer.classList.remove('drag-over');
    
    if (draggedElement) {
        const newStage = this.dataset.stage;
        const oppId = draggedElement.dataset.id;
        const oldStage = draggedElement.closest('.pipeline-column').dataset.stage;
        
        if (newStage !== oldStage) {
            // Update via AJAX
            const formData = new FormData();
            formData.append('update_stage', '1');
            formData.append('opportunity_id', oppId);
            formData.append('new_stage', newStage);
            formData.append('csrf_token', '<?= htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8') ?>');
            
            fetch('pipeline_board.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show updated stats
                    window.location.reload();
                } else {
                    alert('Failed to update opportunity stage');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update opportunity stage');
            });
        }
    }
    
    return false;
}
</script>

<?php include_once(__DIR__ . '/layout_end.php'); ?>
