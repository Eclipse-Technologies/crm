<?php
require_once __DIR__ . '/layout_start.php';
require_once __DIR__ . '/admin_helper.php';
requireAdmin();
require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/audit_handler.php';
require_once __DIR__ . '/admin_sql_helper.php';

$pageTitle = 'Integrity Report';

function runRepairQuery(mysqli $conn, string $sql): int {
    if (!$conn->query($sql)) {
        return 0;
    }
    return (int) $conn->affected_rows;
}

$conn = get_mysql_connection();
$oppIdCol = adminOpportunityIdColumn($conn);
$existsTaskOpportunity = adminNormalizedIdExistsClause('t.opportunity_id', 'opportunities', $oppIdCol, 'o');
$existsDiscussionOpportunity = adminNormalizedIdExistsClause('d.linked_opportunity_id', 'opportunities', $oppIdCol, 'o');
$existsOpportunityContact = adminNormalizedIdExistsClause('o.contact_id', 'contacts', 'contact_id', 'c');
$existsContractContact = adminNormalizedIdExistsClause('c.contact_id', 'contacts', 'contact_id', 'ct');
$existsContractCustomer = adminNormalizedIdExistsClause('c.customer_id', 'customers', 'customer_id', 'cu');

$actionNotice = '';
$actionType = 'success';
$allowedBatchSizes = [25, 100, 250, 500];
$batchSize = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 100;
$previewWindowSeconds = 900;
$previewAtSession = isset($_SESSION['integrity_preview_contract_customer_at']) ? (int) $_SESSION['integrity_preview_contract_customer_at'] : 0;
$showContractCustomerApply = $previewAtSession > 0 && (time() - $previewAtSession) <= $previewWindowSeconds;
if ($previewAtSession > 0 && !$showContractCustomerApply) {
    unset($_SESSION['integrity_preview_contract_customer_at']);
}
if (!in_array($batchSize, $allowedBatchSizes, true)) {
    $batchSize = 100;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair_action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $actionNotice = 'CSRF validation failed. Please refresh and try again.';
        $actionType = 'danger';
    } else {
        $action = trim((string) ($_POST['repair_action'] ?? ''));
        $conn->begin_transaction();
        try {
            $taskFixes = 0;
            $discussionFixes = 0;
            $shouldCommit = true;

                        if ($action === 'repair_tasks_orphan_opp' || $action === 'repair_safe_all') {
                $taskSql = "
                UPDATE tasks t
                SET t.opportunity_id = NULL
                WHERE t.opportunity_id IS NOT NULL
                  AND t.opportunity_id <> 0
                  AND NOT {$existsTaskOpportunity}
                LIMIT {$batchSize}
                ";
                $taskFixes = runRepairQuery($conn, $taskSql);
            }

            if ($action === 'repair_discussion_orphan_opp' || $action === 'repair_safe_all') {
                $discussionSql = "
                                UPDATE discussion_log d
                                SET d.linked_opportunity_id = NULL
                                WHERE d.linked_opportunity_id IS NOT NULL
                                    AND d.linked_opportunity_id <> ''
                                    AND NOT {$existsDiscussionOpportunity}
                LIMIT {$batchSize}
                ";
                $discussionFixes = runRepairQuery($conn, $discussionSql);
            }

            if ($action === 'preview_contracts_orphan_customer') {
                $_SESSION['integrity_preview_contract_customer_at'] = time();
                $showContractCustomerApply = true;
                $conn->rollback();
                $shouldCommit = false;
                $actionNotice = 'Preview ready. Review the Contracts Missing Customer table below, then type CONFIRM to apply fixes.';
                $actionType = 'success';

                logAuditAction(
                    'repair_preview',
                    'integrity',
                    'admin_integrity_report',
                    [
                        'repair_action' => $action,
                        'batch_size' => $batchSize,
                    ],
                    'Integrity repair preview generated for contracts orphan customer links',
                    'success'
                );
            }

            if ($action === 'apply_contracts_orphan_customer') {
                $previewAt = isset($_SESSION['integrity_preview_contract_customer_at']) ? (int) $_SESSION['integrity_preview_contract_customer_at'] : 0;
                $confirmText = trim((string) ($_POST['confirm_contract_customer_repair'] ?? ''));

                if ($confirmText !== 'CONFIRM') {
                    $conn->rollback();
                    $shouldCommit = false;
                    $actionType = 'danger';
                    $actionNotice = 'Confirmation text mismatch. Type CONFIRM to apply contract customer repairs.';
                    logAuditAction(
                        'repair',
                        'integrity',
                        'admin_integrity_report',
                        [
                            'repair_action' => $action,
                            'batch_size' => $batchSize,
                            'reason' => 'confirm_text_mismatch',
                        ],
                        'Integrity repair blocked: confirmation text mismatch for action=' . $action,
                        'failed'
                    );
                } elseif ($previewAt <= 0 || (time() - $previewAt) > $previewWindowSeconds) {
                    $conn->rollback();
                    $shouldCommit = false;
                    $actionType = 'danger';
                    $actionNotice = 'Preview expired or missing. Run preview again before applying this repair.';
                    logAuditAction(
                        'repair',
                        'integrity',
                        'admin_integrity_report',
                        [
                            'repair_action' => $action,
                            'batch_size' => $batchSize,
                            'reason' => 'preview_missing_or_expired',
                        ],
                        'Integrity repair blocked: preview missing/expired for action=' . $action,
                        'failed'
                    );
                } else {
                    $contractCustomerSql = "
                    UPDATE contracts c
                    SET c.customer_id = NULL
                    WHERE c.customer_id IS NOT NULL
                      AND c.customer_id <> ''
                                            AND NOT {$existsContractCustomer}
                    LIMIT {$batchSize}
                    ";
                    $contractCustomerFixes = runRepairQuery($conn, $contractCustomerSql);
                    unset($_SESSION['integrity_preview_contract_customer_at']);
                    $actionNotice = 'Updated ' . $contractCustomerFixes . ' contract row(s): cleared orphan customer links.';
                    $actionType = 'success';

                    logAuditAction(
                        'repair',
                        'integrity',
                        'admin_integrity_report',
                        [
                            'repair_action' => $action,
                            'batch_size' => $batchSize,
                            'contracts_customer_fixed' => $contractCustomerFixes,
                        ],
                        'Integrity repair run: action=' . $action . ', contracts_customer_fixed=' . $contractCustomerFixes,
                        'success'
                    );
                }
            }

            if ($shouldCommit) {
                $conn->commit();
            }

            if ($action === 'repair_tasks_orphan_opp') {
                $actionNotice = 'Updated ' . $taskFixes . ' task row(s): cleared orphan opportunity links.';
            } elseif ($action === 'repair_discussion_orphan_opp') {
                $actionNotice = 'Updated ' . $discussionFixes . ' discussion row(s): cleared orphan linked opportunities.';
            } elseif ($action === 'repair_safe_all') {
                $actionNotice = 'Safe repair complete: tasks fixed=' . $taskFixes . ', discussions fixed=' . $discussionFixes . '.';
            } elseif ($action === 'preview_contracts_orphan_customer' || $action === 'apply_contracts_orphan_customer') {
                // Notice already set in branch.
            } else {
                $actionType = 'danger';
                $actionNotice = 'Unknown repair action.';
            }

            if ($action === 'repair_tasks_orphan_opp' || $action === 'repair_discussion_orphan_opp' || $action === 'repair_safe_all') {
                logAuditAction(
                    'repair',
                    'integrity',
                    'admin_integrity_report',
                    [
                        'repair_action' => $action,
                        'batch_size' => $batchSize,
                        'tasks_fixed' => $taskFixes,
                        'discussions_fixed' => $discussionFixes,
                    ],
                    'Integrity repair run: action=' . $action . ', tasks=' . $taskFixes . ', discussions=' . $discussionFixes,
                    $actionType === 'danger' ? 'failed' : 'success'
                );
            }
        } catch (Throwable $e) {
            $conn->rollback();
            $actionType = 'danger';
            $dbErr = trim((string) $conn->error);
            $actionNotice = 'Repair failed. No changes were committed.' . ($dbErr !== '' ? ' SQL: ' . $dbErr : '');
            logAuditAction(
                'repair',
                'integrity',
                'admin_integrity_report',
                [
                    'repair_action' => $action,
                    'batch_size' => $batchSize,
                    'error' => $e->getMessage(),
                ],
                'Integrity repair failed for action=' . $action,
                'failed',
                $e->getMessage()
            );
        }
    }
}

$rows = [
    'opps_missing_contact' => [],
    'tasks_orphan_opp' => [],
    'discussion_orphan_opp' => [],
    'contracts_orphan_contact' => [],
    'contracts_orphan_customer' => [],
];

$sqlOppMissingContact = "
SELECT o.{$oppIdCol} AS opportunity_id, o.name, o.stage, o.contact_id
FROM opportunities o
WHERE o.contact_id IS NULL
   OR o.contact_id = ''
   OR NOT {$existsOpportunityContact}
ORDER BY o.{$oppIdCol} DESC
LIMIT 100
";
$r = $conn->query($sqlOppMissingContact);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $rows['opps_missing_contact'][] = $row;
    }
    $r->free();
}

if (adminTableHasColumn($conn, 'tasks', 'opportunity_id')) {
    $sqlTaskOrphanOpp = "
    SELECT t.id, t.title, t.status, t.opportunity_id
        FROM tasks t
        WHERE t.opportunity_id IS NOT NULL
            AND t.opportunity_id <> 0
            AND NOT {$existsTaskOpportunity}
    ORDER BY t.due_date IS NULL, t.due_date ASC
    LIMIT 100
    ";
    $r = $conn->query($sqlTaskOrphanOpp);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $rows['tasks_orphan_opp'][] = $row;
        }
        $r->free();
    }
}

if (adminTableHasColumn($conn, 'discussion_log', 'linked_opportunity_id')) {
    $sqlDiscussionOrphanOpp = "
    SELECT d.id, d.contact_id, d.author, d.linked_opportunity_id, d.timestamp
        FROM discussion_log d
    WHERE d.linked_opportunity_id IS NOT NULL
      AND d.linked_opportunity_id <> ''
            AND NOT {$existsDiscussionOpportunity}
    ORDER BY d.timestamp DESC
    LIMIT 100
    ";
    $r = $conn->query($sqlDiscussionOrphanOpp);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $rows['discussion_orphan_opp'][] = $row;
        }
        $r->free();
    }
}

$sqlContractsOrphanContact = "
SELECT c.contract_id, c.contact_id, c.customer_id, c.contract_status
FROM contracts c
WHERE c.contact_id IS NOT NULL
    AND c.contact_id <> ''
    AND NOT {$existsContractContact}
ORDER BY c.contract_id DESC
LIMIT 100
";
$r = $conn->query($sqlContractsOrphanContact);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $rows['contracts_orphan_contact'][] = $row;
    }
    $r->free();
}

$sqlContractsOrphanCustomer = "
SELECT c.contract_id, c.contact_id, c.customer_id, c.contract_status
FROM contracts c
WHERE c.customer_id IS NOT NULL
    AND c.customer_id <> ''
    AND NOT {$existsContractCustomer}
ORDER BY c.contract_id DESC
LIMIT 100
";
$r = $conn->query($sqlContractsOrphanCustomer);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $rows['contracts_orphan_customer'][] = $row;
    }
    $r->free();
}

$conn->close();

$summary = [
    'opps_missing_contact' => count($rows['opps_missing_contact']),
    'tasks_orphan_opp' => count($rows['tasks_orphan_opp']),
    'discussion_orphan_opp' => count($rows['discussion_orphan_opp']),
    'contracts_orphan_contact' => count($rows['contracts_orphan_contact']),
    'contracts_orphan_customer' => count($rows['contracts_orphan_customer']),
];

$totalIssues = array_sum($summary);

function renderIntegrityTable(string $title, array $headers, array $data): void {
    echo '<div class="bulk-section">';
    echo '<h3>' . htmlspecialchars($title) . ' <span style="color:#666;font-size:13px;">(' . count($data) . ')</span></h3>';
    if (empty($data)) {
        echo '<p style="color:#1f7a1f;font-weight:600;">No issues found.</p>';
        echo '</div>';
        return;
    }

    echo '<table class="contact-select-table">';
    echo '<thead><tr>';
    foreach ($headers as $h) {
        echo '<th>' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($data as $row) {
        echo '<tr>';
        foreach ($headers as $h) {
            $key = strtolower(str_replace([' ', '/'], ['_', '_'], $h));
            $value = $row[$key] ?? '';
            echo '<td>' . htmlspecialchars((string) $value) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}
?>

<style>
.bulk-section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
.contact-select-table { width: 100%; margin-top: 12px; font-size: 13px; border-collapse: collapse; }
.contact-select-table th { background: #f5f5f5; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; }
.contact-select-table td { padding: 8px 10px; border-bottom: 1px solid #eee; }
.metric-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:18px; }
.metric-card { background:#fff; padding:14px; border-radius:8px; border-left:4px solid #0099A8; }
.metric-card .label { color:#666; font-size:12px; text-transform:uppercase; }
.metric-card .value { font-size:24px; font-weight:700; color:#1f2937; }
.repair-bar { background:#fff; padding:14px; border-radius:8px; border:1px solid #e5e7eb; margin-bottom:18px; }
.repair-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.repair-btn { border:none; border-radius:6px; padding:8px 12px; font-weight:600; cursor:pointer; }
.repair-safe { background:#0ea5e9; color:#fff; }
.repair-single { background:#334155; color:#fff; }
</style>

<h2>Sales/Data Integrity Report</h2>
<p><a href="admin_dashboard.php">← Back to Dashboard</a></p>

<?php if ($totalIssues > 0): ?>
  <div class="alert-danger"><strong>⚠️ Found <?= (int) $totalIssues ?> potential cross-module link issues.</strong></div>
<?php else: ?>
  <div class="alert-success"><strong>✓ No orphan link issues detected in audited sales relationships.</strong></div>
<?php endif; ?>

<?php if ($actionNotice !== ''): ?>
    <div class="alert-<?= $actionType === 'danger' ? 'danger' : 'success' ?>"><strong><?= htmlspecialchars($actionNotice) ?></strong></div>
<?php endif; ?>

<div class="repair-bar">
    <h3 style="margin-top:0;">Safe Repair Actions</h3>
    <p style="margin:0 0 10px 0; color:#666;">These actions only clear orphan links (set to NULL). They do not delete records.</p>
    <form method="POST" class="repair-row" onsubmit="return confirm('Run selected safe repair action?');">
        <?php renderCSRFInput(); ?>
        <label for="batch_size"><strong>Batch size:</strong></label>
        <select name="batch_size" id="batch_size">
            <?php foreach ($allowedBatchSizes as $size): ?>
                <option value="<?= (int) $size ?>" <?= $batchSize === $size ? 'selected' : '' ?>><?= (int) $size ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="repair_action" value="repair_safe_all" class="repair-btn repair-safe">Fix All Safe (Batch)</button>
        <button type="submit" name="repair_action" value="repair_tasks_orphan_opp" class="repair-btn repair-single">Fix Task Links</button>
        <button type="submit" name="repair_action" value="repair_discussion_orphan_opp" class="repair-btn repair-single">Fix Discussion Links</button>
    </form>
</div>

<div class="repair-bar" style="border-left:4px solid #f59e0b;">
    <h3 style="margin-top:0;">Guided Contract Customer Repair</h3>
    <p style="margin:0 0 10px 0; color:#666;">This clears orphan <code>contracts.customer_id</code> values only. Preview is required before apply.</p>
    <form method="POST" class="repair-row" onsubmit="return confirm('Generate preview for contract-customer repair?');">
        <?php renderCSRFInput(); ?>
        <input type="hidden" name="batch_size" value="<?= (int) $batchSize ?>">
        <input type="hidden" name="repair_action" value="preview_contracts_orphan_customer">
        <button type="submit" class="repair-btn repair-single">Preview Contract Customer Fixes</button>
    </form>

    <?php if ($showContractCustomerApply && (int) $summary['contracts_orphan_customer'] > 0): ?>
      <form method="POST" class="repair-row" style="margin-top:10px;" onsubmit="return confirm('Apply contract-customer repair now?');">
          <?php renderCSRFInput(); ?>
          <input type="hidden" name="batch_size" value="<?= (int) $batchSize ?>">
          <input type="hidden" name="repair_action" value="apply_contracts_orphan_customer">
          <label for="confirm_contract_customer_repair"><strong>Type CONFIRM:</strong></label>
          <input id="confirm_contract_customer_repair" name="confirm_contract_customer_repair" type="text" placeholder="CONFIRM" required>
          <button type="submit" class="repair-btn repair-safe" style="background:#b45309;">Apply Contract Customer Fixes</button>
      </form>
    <?php endif; ?>
</div>

<div class="metric-grid">
  <div class="metric-card"><div class="label">Opportunities Missing Contact</div><div class="value"><?= (int) $summary['opps_missing_contact'] ?></div></div>
  <div class="metric-card"><div class="label">Tasks With Missing Opportunity</div><div class="value"><?= (int) $summary['tasks_orphan_opp'] ?></div></div>
  <div class="metric-card"><div class="label">Discussions With Missing Opportunity</div><div class="value"><?= (int) $summary['discussion_orphan_opp'] ?></div></div>
  <div class="metric-card"><div class="label">Contracts Missing Contact</div><div class="value"><?= (int) $summary['contracts_orphan_contact'] ?></div></div>
  <div class="metric-card"><div class="label">Contracts Missing Customer</div><div class="value"><?= (int) $summary['contracts_orphan_customer'] ?></div></div>
</div>

<?php
renderIntegrityTable(
    'Opportunities with Missing/Invalid Contact',
    ['Opportunity ID', 'Name', 'Stage', 'Contact ID'],
    $rows['opps_missing_contact']
);

renderIntegrityTable(
    'Tasks Referencing Missing Opportunities',
    ['ID', 'Title', 'Status', 'Opportunity ID'],
    $rows['tasks_orphan_opp']
);

renderIntegrityTable(
    'Discussion Entries Referencing Missing Opportunities',
    ['ID', 'Contact ID', 'Author', 'Linked Opportunity ID', 'Timestamp'],
    $rows['discussion_orphan_opp']
);

renderIntegrityTable(
    'Contracts Referencing Missing Contacts',
    ['Contract ID', 'Contact ID', 'Customer ID', 'Contract Status'],
    $rows['contracts_orphan_contact']
);

renderIntegrityTable(
    'Contracts Referencing Missing Customers',
    ['Contract ID', 'Contact ID', 'Customer ID', 'Contract Status'],
    $rows['contracts_orphan_customer']
);
?>

<?php include_once __DIR__ . '/layout_end.php'; ?>
