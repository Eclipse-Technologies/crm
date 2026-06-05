<?php
// --- ALL SESSION/CSRF/DEPENDENCY LOGIC MUST BE AT THE VERY TOP, NO OUTPUT OR WHITESPACE BEFORE THIS BLOCK ---
require_once __DIR__ . '/csrf_helper.php';
ensureCSRFSessionStarted();
require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/simple_auth/middleware.php';

function customer_view_has_column(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $has = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    $cache[$key] = $has;
    return $has;
}

function customer_view_insert_followup_task(mysqli $conn, int $contactId, string $customerId, string $nextTouchAt, string $summary, string $nextAction): void {
    if (!customer_view_has_column($conn, 'tasks', 'id') || !customer_view_has_column($conn, 'tasks', 'title')) {
        return;
    }

    $row = [
        'id' => uniqid('task_', true),
        'title' => 'Touchpoint follow-up: Customer ' . $customerId,
        'status' => 'not_started',
        'priority' => 'high',
        'assigned_to' => '',
        'due_date' => substr($nextTouchAt, 0, 10),
        'timestamp' => date('Y-m-d H:i:s'),
        'contact_id' => $contactId,
        'opportunity_id' => null,
        'project_id' => null,
        'description' => "Touchpoint summary: " . $summary . ($nextAction !== '' ? "\nNext action: " . $nextAction : ''),
        'comments' => 'Auto-created from customer touchpoint log.',
        'recurrence' => '',
        'attachment' => '',
    ];

    $columns = [];
    $placeholders = [];
    $types = '';
    $values = [];

    foreach ($row as $col => $value) {
        if (!customer_view_has_column($conn, 'tasks', $col)) {
            continue;
        }
        $columns[] = "`$col`";
        $placeholders[] = '?';
        if (is_int($value)) {
            $types .= 'i';
            $values[] = $value;
        } else {
            $types .= 's';
            $values[] = $value;
        }
    }

    if (empty($columns)) {
        return;
    }

    $sql = 'INSERT INTO tasks (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

function customer_view_is_customer_owned(string $ownership): bool
{
    $normalized = strtolower(trim($ownership));
    return in_array($normalized, ['customer owned', 'customer-owned', 'purchased'], true);
}

function customer_view_is_service_owned(string $ownership): bool
{
    $normalized = strtolower(trim($ownership));
    return in_array($normalized, ['rental', 'evoqua rental', 'lease', 'leased', 'evoqua lease'], true);
}

$customerId = $_GET['id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_customer') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo "<div class='alert alert-danger'>CSRF token validation failed.</div>";
    } else {
        $conn = get_mysql_connection();
        $set = [];
        $types = '';
        $values = [];

        $updatableTextFields = [
            'address',
            'relationship_tier',
            'preferred_channel',
            'relationship_health',
            'last_touch_summary',
            'next_touch_goal',
        ];
        foreach ($updatableTextFields as $field) {
            if (!customer_view_has_column($conn, 'customers', $field) || !array_key_exists($field, $_POST)) {
                continue;
            }
            $set[] = "`$field` = ?";
            $types .= 's';
            $values[] = trim((string) $_POST[$field]);
        }

        if (customer_view_has_column($conn, 'customers', 'contact_id') && array_key_exists('contact_id', $_POST)) {
            $set[] = '`contact_id` = ?';
            $types .= 's';
            $values[] = ($_POST['contact_id'] === '' ? null : (string) ((int) $_POST['contact_id']));
        }

        if (customer_view_has_column($conn, 'customers', 'touch_cadence_days') && array_key_exists('touch_cadence_days', $_POST)) {
            $set[] = '`touch_cadence_days` = ?';
            $types .= 'i';
            $values[] = ($_POST['touch_cadence_days'] === '' ? 0 : max(0, (int) $_POST['touch_cadence_days']));
        }

        foreach (['last_touch_at', 'next_touch_at'] as $dtField) {
            if (!customer_view_has_column($conn, 'customers', $dtField) || !array_key_exists($dtField, $_POST)) {
                continue;
            }
            $set[] = "`$dtField` = ?";
            $types .= 's';
            $raw = trim((string) $_POST[$dtField]);
            $values[] = $raw === '' ? null : str_replace('T', ' ', $raw);
        }

        if (customer_view_has_column($conn, 'customers', 'last_modified')) {
            $set[] = '`last_modified` = NOW()';
        }

        if (!empty($set) && $customerId !== '') {
            $sql = 'UPDATE customers SET ' . implode(', ', $set) . ' WHERE customer_id = ?';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $types .= 's';
                $values[] = $customerId;
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();
            }
            echo "<div class='alert alert-success'>Customer information saved.</div>";
        }

        $conn->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'log_touchpoint') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo "<div class='alert alert-danger'>CSRF token validation failed.</div>";
    } else {
        $contactIdRaw = trim((string) ($_POST['contact_id'] ?? ''));
        $contactId = $contactIdRaw !== '' ? (int) $contactIdRaw : 0;
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $valueDelivered = trim((string) ($_POST['value_delivered'] ?? ''));
        $nextAction = trim((string) ($_POST['next_action'] ?? ''));
        $nextTouchAtRaw = trim((string) ($_POST['next_touch_at'] ?? ''));
        $nextTouchAt = $nextTouchAtRaw !== '' ? str_replace('T', ' ', $nextTouchAtRaw) : null;
        $touchType = trim((string) ($_POST['touch_type'] ?? 'check-in'));
        $channel = trim((string) ($_POST['channel'] ?? 'phone'));
        $visibility = trim((string) ($_POST['visibility'] ?? 'private'));
        $health = trim((string) ($_POST['relationship_health'] ?? ''));
        $createTask = isset($_POST['create_followup_task']) && $_POST['create_followup_task'] === '1';

        $currentUser = function_exists('auth_current_user') ? auth_current_user() : [];
        $author = trim((string) ($currentUser['username'] ?? 'Advisor'));

        if ($summary === '' || $contactId <= 0) {
            echo "<div class='alert alert-danger'>Summary and linked contact are required.</div>";
        } else {
            $entryLines = [
                '[Touchpoint] ' . ucfirst($touchType) . ' via ' . $channel,
                'Summary: ' . $summary,
            ];
            if ($valueDelivered !== '') {
                $entryLines[] = 'Value delivered: ' . $valueDelivered;
            }
            if ($nextAction !== '') {
                $entryLines[] = 'Next action: ' . $nextAction;
            }
            if ($nextTouchAt !== null) {
                $entryLines[] = 'Next touch: ' . $nextTouchAt;
            }
            $entryText = implode("\n", $entryLines);

            $conn = get_mysql_connection();

            $stmt = $conn->prepare('INSERT INTO discussion_log (contact_id, author, entry_text, linked_opportunity_id, visibility, timestamp) VALUES (?, ?, ?, ?, ?, NOW())');
            if ($stmt) {
                $blankOpp = '';
                $stmt->bind_param('sssss', $contactIdRaw, $author, $entryText, $blankOpp, $visibility);
                $stmt->execute();
                $stmt->close();
            }

            if (customer_view_has_column($conn, 'customer_touchpoints', 'id')) {
                $stmt = $conn->prepare('INSERT INTO customer_touchpoints (customer_id, contact_id, touch_type, channel, summary, value_delivered, next_action, next_touch_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('sssssssss', $customerId, $contactIdRaw, $touchType, $channel, $summary, $valueDelivered, $nextAction, $nextTouchAt, $author);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $set = ['last_touch_at = NOW()'];
            $types = '';
            $values = [];
            if (customer_view_has_column($conn, 'customers', 'last_touch_summary')) {
                $set[] = 'last_touch_summary = ?';
                $types .= 's';
                $values[] = $summary;
            }
            if (customer_view_has_column($conn, 'customers', 'next_touch_goal')) {
                $set[] = 'next_touch_goal = ?';
                $types .= 's';
                $values[] = $nextAction;
            }
            if ($nextTouchAt !== null && customer_view_has_column($conn, 'customers', 'next_touch_at')) {
                $set[] = 'next_touch_at = ?';
                $types .= 's';
                $values[] = $nextTouchAt;
            }
            if ($channel !== '' && customer_view_has_column($conn, 'customers', 'preferred_channel')) {
                $set[] = 'preferred_channel = ?';
                $types .= 's';
                $values[] = $channel;
            }
            if ($health !== '' && customer_view_has_column($conn, 'customers', 'relationship_health')) {
                $set[] = 'relationship_health = ?';
                $types .= 's';
                $values[] = $health;
            }
            if (customer_view_has_column($conn, 'customers', 'last_modified')) {
                $set[] = 'last_modified = NOW()';
            }
            $sql = 'UPDATE customers SET ' . implode(', ', $set) . ' WHERE customer_id = ?';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $types .= 's';
                $values[] = $customerId;
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();
            }

            if (customer_view_has_column($conn, 'contacts', 'last_touch_at')) {
                $contactSet = ['last_touch_at = NOW()'];
                $ctypes = 'i';
                $cvalues = [$contactId];
                if ($nextTouchAt !== null && customer_view_has_column($conn, 'contacts', 'next_touch_at')) {
                    $contactSet[] = 'next_touch_at = ?';
                    $ctypes = 'si';
                    $cvalues = [$nextTouchAt, $contactId];
                }
                $stmt = $conn->prepare('UPDATE contacts SET ' . implode(', ', $contactSet) . ' WHERE contact_id = ?');
                if ($stmt) {
                    $stmt->bind_param($ctypes, ...$cvalues);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            if ($createTask && $nextTouchAt !== null) {
                customer_view_insert_followup_task($conn, $contactId, $customerId, $nextTouchAt, $summary, $nextAction);
            }

            $conn->close();
            echo "<div class='alert alert-success'>Touchpoint logged and relationship fields updated.</div>";
        }
    }
}

// Handle discussion log form submission for linked contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_discussion'])) {
    $contactIdRaw = $_POST['contact_id'] ?? '';
    $contactId = intval($contactIdRaw);
    $author = trim((string) ($_POST['author'] ?? ''));
    $entryText = trim((string) ($_POST['entry_text'] ?? ''));
    $linkedOppId = trim((string) ($_POST['linked_opportunity_id'] ?? ''));
    $visibility = 'private';

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo "<div class='alert alert-danger'>Security validation failed. Please refresh and try again.</div>";
    } elseif (!$contactIdRaw || !is_numeric($contactIdRaw)) {
        echo "<div class='alert alert-danger'>Invalid contact ID.</div>";
    } elseif (!$author || !$entryText) {
        echo "<div class='alert alert-danger'>All required fields must be filled out.</div>";
    } else {
        $conn = get_mysql_connection();
        $sql = "INSERT INTO discussion_log (contact_id, author, entry_text, linked_opportunity_id, visibility, timestamp) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $linkedOppIdNull = ($linkedOppId === '') ? null : $linkedOppId;
            $stmt->bind_param('issss', $contactId, $author, $entryText, $linkedOppIdNull, $visibility);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    }
}
require_once __DIR__ . '/inventory_mysql.php';
$inventory = fetch_inventory_mysql(require __DIR__ . '/inventory_schema.php');

// Handle assigning number of tanks from pool
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_tank_from_pool') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo "<div class='alert alert-danger'>CSRF token validation failed.</div>";
    } else {
        $numTanks = max(0, intval($_POST['pool_tank_count'] ?? 0));
        $conn = get_mysql_connection();
        // Find all available pool rental tanks (ownership = rental, customer_id IS NULL)
        $stmt = $conn->prepare("SELECT equipment_id FROM equipment WHERE LOWER(ownership) IN ('rental', 'evoqua rental', 'lease', 'leased', 'evoqua lease') AND (customer_id IS NULL OR customer_id = '') ORDER BY equipment_id ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $availablePoolTanks = [];
        while ($row = $result->fetch_assoc()) {
            $availablePoolTanks[] = $row['equipment_id'];
        }
        $stmt->close();

        // Find all rental tanks currently assigned to this customer
        $stmt = $conn->prepare("SELECT equipment_id FROM equipment WHERE LOWER(ownership) IN ('rental', 'evoqua rental', 'lease', 'leased', 'evoqua lease') AND customer_id = ? ORDER BY equipment_id ASC");
        $stmt->bind_param('s', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentlyAssigned = [];
        while ($row = $result->fetch_assoc()) {
            $currentlyAssigned[] = $row['equipment_id'];
        }
        $stmt->close();

        // Calculate how many to add or remove
        $toAssign = $numTanks - count($currentlyAssigned);
        $assignedCount = 0;
        $removedCount = 0;
        if ($toAssign > 0) {
            // Assign tanks from pool to this customer
            $assignIds = array_slice($availablePoolTanks, 0, $toAssign);
            foreach ($assignIds as $eqId) {
                $stmt = $conn->prepare("UPDATE equipment SET customer_id = ? WHERE equipment_id = ?");
                $stmt->bind_param('ss', $customerId, $eqId);
                $stmt->execute();
                $assignedCount += ($stmt->affected_rows > 0) ? 1 : 0;
                $stmt->close();
            }
        } elseif ($toAssign < 0) {
            // Unassign tanks from this customer (return to pool)
            $removeIds = array_slice($currentlyAssigned, $toAssign); // negative offset
            foreach ($removeIds as $eqId) {
                $stmt = $conn->prepare("UPDATE equipment SET customer_id = NULL WHERE equipment_id = ?");
                $stmt->bind_param('s', $eqId);
                $stmt->execute();
                $removedCount += ($stmt->affected_rows > 0) ? 1 : 0;
                $stmt->close();
            }
        }
        $conn->close();

        $currentAfter = count($currentlyAssigned) + $assignedCount - $removedCount;
        if ($toAssign > 0 && $assignedCount < $toAssign) {
            echo "<div class='alert alert-warning'>Partial update: requested total {$numTanks} tanks, assigned {$assignedCount} of {$toAssign} additional requested. Customer now has {$currentAfter} assigned.</div>";
        } elseif ($toAssign < 0 && $removedCount < abs($toAssign)) {
            $expectedToRemove = abs($toAssign);
            echo "<div class='alert alert-warning'>Partial update: needed to return {$expectedToRemove} tanks to pool, returned {$removedCount}. Customer now has {$currentAfter} assigned.</div>";
        } elseif ($toAssign === 0) {
            echo "<div class='alert alert-info'>No change needed. Customer already has {$numTanks} pool tanks assigned.</div>";
        } else {
            echo "<div class='alert alert-success'>Updated pool tank assignment. Customer now has {$currentAfter} assigned.</div>";
        }
    }
}
$customerSchema = require __DIR__ . '/customer_schema.php';
$equipmentSchema = require __DIR__ . '/equipment_schema.php';

$contractSchema = require __DIR__ . '/contract_schema.php';

// ── Fetch equipment for this customer ───────────────────────────────────────
$customerOwnedEquipment = [];
$serviceEquipment = [];
if ($customerId !== '') {
    $conn = get_mysql_connection();
    $stmt = $conn->prepare("SELECT * FROM equipment WHERE customer_id = ?");
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ownership = (string) ($row['ownership'] ?? '');
        if (customer_view_is_customer_owned($ownership)) {
            $customerOwnedEquipment[] = $row;
        } elseif (customer_view_is_service_owned($ownership)) {
            $serviceEquipment[] = $row;
        }
    }
    $stmt->close();
    $conn->close();
}

$componentsByEquipment = [];
$equipmentIdsForComponents = array_map(static function ($row) {
    return (string) ($row['equipment_id'] ?? '');
}, array_merge($customerOwnedEquipment, $serviceEquipment));
$equipmentIdsForComponents = array_values(array_filter(array_unique($equipmentIdsForComponents)));
if (!empty($equipmentIdsForComponents)) {
    $conn = get_mysql_connection();
    $placeholders = implode(',', array_fill(0, count($equipmentIdsForComponents), '?'));
    $stmtComp = $conn->prepare('SELECT equipment_id, component_slot, item_id, quantity_required FROM equipment_components WHERE equipment_id IN (' . $placeholders . ')');
    if ($stmtComp) {
        $types = str_repeat('s', count($equipmentIdsForComponents));
        $stmtComp->bind_param($types, ...$equipmentIdsForComponents);
        $stmtComp->execute();
        $resultComp = $stmtComp->get_result();
        while ($rowComp = $resultComp ? $resultComp->fetch_assoc() : null) {
            $equipmentId = (string) ($rowComp['equipment_id'] ?? '');
            $slot = (string) ($rowComp['component_slot'] ?? '');
            if ($equipmentId === '' || $slot === '') {
                continue;
            }
            if (!isset($componentsByEquipment[$equipmentId])) {
                $componentsByEquipment[$equipmentId] = [];
            }
            $componentsByEquipment[$equipmentId][$slot] = [
                'item_id' => (string) ($rowComp['item_id'] ?? ''),
                'quantity_required' => (float) ($rowComp['quantity_required'] ?? 0),
            ];
        }
        $stmtComp->close();
    }
    $conn->close();
}

// ── Layout start ─────────────────────────────────────────────────────────────
include_once(__DIR__ . '/layout_start.php');

// ── Load customer ────────────────────────────────────────────────────────────
$customerId = $_GET['id'] ?? '';
$customer = null;
$contact = null;
if ($customerId !== '') {
    $conn = get_mysql_connection();
    // Fetch customer
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    // Fetch linked contact
    if ($customer && !empty($customer['contact_id'])) {
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE contact_id = ?");
        $stmt->bind_param('i', $customer['contact_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $contact = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
    $conn->close();
}
if (!$customer) {
    echo "<div class='container'><h2>❌ Customer not found</h2></div>";
    include_once(__DIR__ . '/layout_end.php');
    exit;
}
// ── Fetch contracts for this customer ────────────────────────────────────────
$contracts = [];
$totalMRR = 0;
$totalARR = 0;
$activeCount = 0;
if ($customerId !== '') {
    $conn = get_mysql_connection();
    $stmt = $conn->prepare("SELECT * FROM contracts WHERE customer_id = ? ORDER BY contract_status DESC, start_date DESC");
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['contract_status'] === 'Active') {
            $totalMRR += (float)($row['monthly_fee'] ?? 0);
            $totalARR += (float)($row['annual_value'] ?? 0);
            $activeCount++;
        }
        $contracts[] = $row;
    }
    $stmt->close();
    $conn->close();
}
?>
<style>
.cv-page {
    max-width: 1380px;
    margin: 0 auto;
    padding-bottom: 24px;
}
.cv-hero {
    background: linear-gradient(135deg, #0f766e 0%, #155e75 55%, #1d4ed8 100%);
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 18px;
    color: #f8fafc;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
}
.cv-hero h2 {
    margin: 0 0 8px 0;
    font-size: 1.45rem;
    color: #ffffff;
}
.cv-header-meta { margin-bottom: 14px; color: #4b5563; }
.cv-hero .cv-header-meta { margin: 0; color: #e2e8f0; }
.cv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
.cv-grid-tight { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.cv-fieldset { margin-bottom: 20px; }
.cv-table-wrap { overflow-x: auto; }
.cv-muted { color: #6b7280; font-size: 13px; }
.cv-readonly { background: #eee; }
.cv-page fieldset {
    border: 1px solid #dbe3f0;
    border-radius: 12px;
    background: #ffffff;
    padding: 16px;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.06);
}
.cv-page legend {
    float: none;
    width: auto;
    margin: 0;
    padding: 0 8px;
    font-size: 14px;
    color: #1f2937;
}
.cv-page input[type="text"],
.cv-page input[type="number"],
.cv-page input[type="email"],
.cv-page select,
.cv-page textarea {
    width: 100%;
    border: 1px solid #cfd8e3;
    border-radius: 8px;
    padding: 10px 12px;
    background: #ffffff;
}
.cv-page input:focus,
.cv-page select:focus,
.cv-page textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
}
.cv-kpi-wrap { max-width: 340px; margin-bottom: 16px; }
.cv-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 12px; }
.cv-kpi-card { background: #f8f9fa; padding: 16px; border-radius: 8px; text-align: center; }
.cv-kpi-label { font-size: 13px; color: #6b7280; font-weight: 600; text-transform: uppercase; }
.cv-kpi-value { font-size: 28px; font-weight: 700; }
.cv-kpi-sub { font-size: 12px; color: #9ca3af; }
.cv-action-row { margin-top: 12px; }
.cv-nav { margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end; }
.cv-tip { font-size: 12px; color: #888; margin-top: 8px; }
.cv-empty { padding: 20px; background: #f8f9fa; border-radius: 4px; color: #999; text-align: center; font-size: 13px; }
.cv-timeline-item { padding: 15px; margin-bottom: 12px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #3B82F6; }
.cv-timeline-head { display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px; }
.cv-timeline-author { color: #1a1a1a; font-size: 14px; }
.cv-timeline-meta { color: #666; font-size: 11px; margin-bottom: 8px; }
.cv-timeline-body { color: #1a1a1a; font-size: 13px; line-height: 1.5; margin-bottom: 6px; }
.cv-linked { margin-top: 8px; padding: 8px; background: white; border-left: 3px solid #10B981; border-radius: 3px; font-size: 11px; color: #666; }
.cv-discussions { margin-bottom: 32px; }
.cv-page .form-section {
    background: #ffffff;
    border: 1px solid #dbe3f0;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.06);
}
.cv-page .form-section h3 {
    margin: 0 0 14px 0;
    font-size: 16px;
    color: #0f172a;
}
.cv-page .accordion-header {
    background: #ffffff;
    border: 1px solid #dbe3f0;
    border-radius: 12px;
    padding: 14px 16px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.cv-page .accordion-header.active {
    border-color: #93c5fd;
    background: #eff6ff;
}
.cv-page .accordion-content {
    display: none;
    margin-top: 8px;
}
.cv-page .accordion-content.active {
    display: block;
}
.cv-page .accordion-body {
    background: #ffffff;
    border: 1px solid #dbe3f0;
    border-radius: 12px;
    padding: 14px;
}
.cv-visibility-badge { padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
.cv-visibility-public { background: #e3f2fd; color: #1976d2; }
.cv-visibility-internal { background: #fff3cd; color: #856404; }
.cv-visibility-private { background: #f8d7da; color: #721c24; }
.cv-manual-link { margin-left: 10px; color: #8B5CF6; font-weight: 600; font-size: 11px; }
</style>
<div class="main-content">
    <div class="content-container">
        <div class="container cv-page">


        <!-- Header -->
        <div class="cv-hero">
            <h2>🏢 <?= htmlspecialchars($customer['address'] ?? 'Customer ' . $customerId) ?></h2>
            <div class="cv-header-meta">
                <strong>Customer ID:</strong> <?= htmlspecialchars($customerId) ?><br>
                <?php if ($contact): ?>
                    <strong>Contact:</strong> <?= htmlspecialchars($contact['company'] ?? 'N/A') ?><br>
                <?php endif; ?>
            </div>
        </div>

        <fieldset class="cv-fieldset">
            <legend><strong>🤝 Relationship Control Panel</strong></legend>
            <div class="cv-grid-tight" style="margin-bottom: 12px;">
                <div><strong>Health:</strong> <?= htmlspecialchars($customer['relationship_health'] ?? 'Not set') ?></div>
                <div><strong>Tier:</strong> <?= htmlspecialchars($customer['relationship_tier'] ?? 'Not set') ?></div>
                <div><strong>Cadence (days):</strong> <?= htmlspecialchars((string) ($customer['touch_cadence_days'] ?? '30')) ?></div>
                <div><strong>Preferred Channel:</strong> <?= htmlspecialchars($customer['preferred_channel'] ?? 'Not set') ?></div>
                <div><strong>Last Touch:</strong> <?= htmlspecialchars($customer['last_touch_at'] ?? 'Not recorded') ?></div>
                <div><strong>Next Touch:</strong> <?= htmlspecialchars($customer['next_touch_at'] ?? 'Not scheduled') ?></div>
            </div>

            <?php if ($contact): ?>
            <form method="post" style="margin-top: 8px;">
                <?php renderCSRFInput(); ?>
                <input type="hidden" name="action" value="log_touchpoint">
                <input type="hidden" name="contact_id" value="<?= htmlspecialchars((string) $contact['contact_id']) ?>">

                <div class="cv-grid-tight">
                    <div>
                        <label><strong>Touch Type</strong></label>
                        <select name="touch_type">
                            <option value="check-in">Check-in</option>
                            <option value="advisory">Advisory</option>
                            <option value="follow-up">Follow-up</option>
                            <option value="renewal">Renewal</option>
                            <option value="issue-resolution">Issue Resolution</option>
                        </select>
                    </div>
                    <div>
                        <label><strong>Channel</strong></label>
                        <select name="channel">
                            <option value="phone">Phone</option>
                            <option value="email">Email</option>
                            <option value="text">Text</option>
                            <option value="meeting">Meeting</option>
                        </select>
                    </div>
                    <div>
                        <label><strong>Health</strong></label>
                        <select name="relationship_health">
                            <option value="">Keep current</option>
                            <option value="green">Green</option>
                            <option value="yellow">Yellow</option>
                            <option value="red">Red</option>
                        </select>
                    </div>
                    <div>
                        <label><strong>Visibility</strong></label>
                        <select name="visibility">
                            <option value="private">Private</option>
                            <option value="internal">Internal</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                    <div>
                        <label><strong>Next Touch</strong></label>
                        <input type="datetime-local" name="next_touch_at" value="">
                    </div>
                    <div style="display:flex;align-items:flex-end;">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="create_followup_task" value="1">
                            Create follow-up task
                        </label>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label><strong>Summary</strong></label>
                        <textarea name="summary" rows="2" required></textarea>
                    </div>
                    <div>
                        <label><strong>Value Delivered</strong></label>
                        <textarea name="value_delivered" rows="2"></textarea>
                    </div>
                    <div>
                        <label><strong>Next Action</strong></label>
                        <textarea name="next_action" rows="2"></textarea>
                    </div>
                </div>
                <div class="cv-action-row">
                    <button type="submit" class="btn-outline">📝 Log Touchpoint</button>
                </div>
            </form>
            <?php else: ?>
              <div class="cv-muted">Link a contact to this customer to enable touchpoint logging.</div>
            <?php endif; ?>
        </fieldset>

    <!-- ── CUSTOMER INFO FORM (Editable inline) ────────────────────────────────── -->

        <form method="post" style="margin-bottom:24px;">
            <?php renderCSRFInput(); ?>
            <input type="hidden" name="action" value="update_customer">
            <fieldset class="cv-fieldset">
                <legend><strong>📋 Customer Information</strong></legend>
                <div class="cv-grid">
                    <?php foreach ($customerSchema as $field): ?>
                        <?php if (in_array($field, ['customer_owned_tanks', 'rented_tanks', 'last_delivery', 'last_modified'], true)) continue; ?>
                        <div>
                            <label><strong><?= ucfirst(str_replace('_', ' ', $field)) ?></strong></label><br>
                            <?php if ($field === 'customer_id'): ?>
                                <input type="text" value="<?= htmlspecialchars($customer[$field]) ?>" readonly class="cv-readonly">
                            <?php elseif ($field === 'contact_id'): ?>
                                <select name="<?= $field ?>">
                                    <option value="">-- Select Contact --</option>
                                    <?php
                                    $conn = get_mysql_connection();
                                    $result = $conn->query("SELECT contact_id, company FROM contacts ORDER BY company");
                                    while ($row = $result ? $result->fetch_assoc() : null) {
                                        $selected = ($row['contact_id'] == $customer['contact_id']) ? 'selected' : '';
                                        echo "<option value='{$row['contact_id']}' $selected>{$row['company']}</option>";
                                    }
                                    if ($result) $result->free();
                                    $conn->close();
                                    ?>
                                </select>
                            <?php else: ?>
                                <input type="text" name="<?= $field ?>" value="<?= htmlspecialchars($customer[$field] ?? '') ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div>
                        <label><strong>Relationship Tier</strong></label><br>
                        <select name="relationship_tier">
                            <option value="" <?= empty($customer['relationship_tier']) ? 'selected' : '' ?>>Not set</option>
                            <option value="A" <?= ($customer['relationship_tier'] ?? '') === 'A' ? 'selected' : '' ?>>A</option>
                            <option value="B" <?= ($customer['relationship_tier'] ?? '') === 'B' ? 'selected' : '' ?>>B</option>
                            <option value="C" <?= ($customer['relationship_tier'] ?? '') === 'C' ? 'selected' : '' ?>>C</option>
                        </select>
                    </div>

                    <div>
                        <label><strong>Touch Cadence (days)</strong></label><br>
                        <input type="number" name="touch_cadence_days" min="0" value="<?= htmlspecialchars((string) ($customer['touch_cadence_days'] ?? '30')) ?>">
                    </div>

                    <div>
                        <label><strong>Preferred Channel</strong></label><br>
                        <select name="preferred_channel">
                            <option value="" <?= empty($customer['preferred_channel']) ? 'selected' : '' ?>>Not set</option>
                            <option value="phone" <?= ($customer['preferred_channel'] ?? '') === 'phone' ? 'selected' : '' ?>>Phone</option>
                            <option value="email" <?= ($customer['preferred_channel'] ?? '') === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="text" <?= ($customer['preferred_channel'] ?? '') === 'text' ? 'selected' : '' ?>>Text</option>
                            <option value="meeting" <?= ($customer['preferred_channel'] ?? '') === 'meeting' ? 'selected' : '' ?>>Meeting</option>
                        </select>
                    </div>

                    <div>
                        <label><strong>Relationship Health</strong></label><br>
                        <select name="relationship_health">
                            <option value="" <?= empty($customer['relationship_health']) ? 'selected' : '' ?>>Not set</option>
                            <option value="green" <?= ($customer['relationship_health'] ?? '') === 'green' ? 'selected' : '' ?>>Green</option>
                            <option value="yellow" <?= ($customer['relationship_health'] ?? '') === 'yellow' ? 'selected' : '' ?>>Yellow</option>
                            <option value="red" <?= ($customer['relationship_health'] ?? '') === 'red' ? 'selected' : '' ?>>Red</option>
                        </select>
                    </div>

                    <div>
                        <label><strong>Last Touch</strong></label><br>
                        <input type="datetime-local" name="last_touch_at" value="<?= !empty($customer['last_touch_at']) ? htmlspecialchars(str_replace(' ', 'T', substr((string) $customer['last_touch_at'], 0, 16))) : '' ?>">
                    </div>

                    <div>
                        <label><strong>Next Touch</strong></label><br>
                        <input type="datetime-local" name="next_touch_at" value="<?= !empty($customer['next_touch_at']) ? htmlspecialchars(str_replace(' ', 'T', substr((string) $customer['next_touch_at'], 0, 16))) : '' ?>">
                    </div>

                    <div style="grid-column:1/-1;">
                        <label><strong>Last Touch Summary</strong></label><br>
                        <textarea name="last_touch_summary" rows="2"><?= htmlspecialchars((string) ($customer['last_touch_summary'] ?? '')) ?></textarea>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label><strong>Next Touch Goal</strong></label><br>
                        <textarea name="next_touch_goal" rows="2"><?= htmlspecialchars((string) ($customer['next_touch_goal'] ?? '')) ?></textarea>
                    </div>
                </div>
            </fieldset>
            <button type="submit" class="btn-outline">💾 Save Customer Info</button>
        </form>

    <!-- ── RENTED TANKS SUMMARY ───────────────────────────────────────────── -->

    <?php
    // New logic: rented tanks summary is based on serviceEquipment
    $rentedCount = count($serviceEquipment);
    $rentedSizes = array_map(function($item) {
        return $item['tank_size'] ?? ($item['equipment_type'] ?? '');
    }, $serviceEquipment);
    ?>

        <fieldset class="cv-fieldset">
            <legend><strong>🛢️ Rented Tanks Summary</strong></legend>
            <div>
                <strong>Number of Rented Tanks:</strong> <?= $rentedCount ?><br>
                <strong>Sizes:</strong> <?= $rentedCount > 0 ? htmlspecialchars(implode(', ', $rentedSizes)) : 'N/A' ?>
            </div>
        </fieldset>

    <!-- ── TANK ASSIGNMENT (POOL ONLY, MODERN CSS) ───────────────────────────── -->


        <form method="post" style="margin-bottom:24px;">
            <?php renderCSRFInput(); ?>
            <input type="hidden" name="action" value="assign_tank_from_pool">
            <fieldset>
                <legend><strong>🛢️ Assign Tank from Pool</strong></legend>
                <div class="cv-grid">
                    <div>
                        <label><strong>Number of Pool Tanks Assigned</strong></label><br>
                        <?php
                        // New logic: count rental tanks assigned to this customer and available in pool
                        $currentAssigned = count($serviceEquipment);
                        // Find available pool rental tanks (ownership = rental, customer_id IS NULL)
                        $conn = get_mysql_connection();
                        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM equipment WHERE LOWER(ownership) IN ('rental', 'evoqua rental', 'lease', 'leased', 'evoqua lease') AND (customer_id IS NULL OR customer_id = '')");
                        $stmt->execute();
                        $stmt->bind_result($maxPool);
                        $stmt->fetch();
                        $stmt->close();
                        $conn->close();
                        ?>
                        <input type="number" name="pool_tank_count" min="0" max="<?= $maxPool ?>" value="<?= $currentAssigned ?>" style="width:100px;">
                        <span class="cv-muted">(of <?= $maxPool ?> available in pool)</span>
                    </div>
                </div>
            </fieldset>
            <button type="submit" class="btn-outline">💾 Update Pool Tank Count</button>
        </form>

    <!-- ── CONTACT INFORMATION ────────────────────────────────────────────────── -->

        <?php if ($contact): ?>
            <fieldset class="cv-fieldset">
                <legend><strong>👤 Linked Contact</strong></legend>
                <div class="cv-grid-tight">
                    <div><strong>Company:</strong> <?= htmlspecialchars($contact['company'] ?? 'N/A') ?></div>
                    <div><strong>Contact Person:</strong> <?= htmlspecialchars(trim((string) (($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))) ?: 'N/A') ?></div>
                    <div><strong>Phone:</strong> <?= htmlspecialchars($contact['phone'] ?? 'N/A') ?></div>
                    <div><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($contact['email'] ?? '') ?>"><?= htmlspecialchars($contact['email'] ?? 'N/A') ?></a></div>
                    <div style="grid-column:1/-1;"><strong>Address:</strong> <?= htmlspecialchars($contact['address'] ?? 'N/A') ?></div>
                </div>
            </fieldset>
        <?php endif; ?>

    <!-- ── EQUIPMENT INVENTORY ────────────────────────────────────────────────── -->

        <fieldset class="cv-fieldset">
            <legend><strong>🛢️ Customer-Owned Tanks (<?= count($customerOwnedEquipment ?? []) ?>)</strong></legend>
            <div class="location-legend mb-2">
                <span class="location-chip location-pool">pool</span>
                <span class="location-chip location-production">production</span>
                <span class="location-chip location-warehouse">warehouse</span>
                <span class="location-chip location-customer-site">customer site</span>
            </div>
            <?php if (!empty($customerOwnedEquipment)): ?>
                <div class="cv-table-wrap">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Serial #</th>
                                <th>Tank Size</th>
                                <th>Resin Part #</th>
                                <th>Ownership</th>
                                <th>Location</th>
                                <th>Service Frequency</th>
                                <th>Install Date</th>
                                <th>Last Service</th>
                                <th>Next Service</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerOwnedEquipment as $eq): ?>
                                <?php
                                $resinComponent = $componentsByEquipment[$eq['equipment_id']]['resin'] ?? null;
                                $resinPartNumber = $resinComponent['item_id'] ?? ($eq['resin_type'] ?? '');
                                $resinQty = isset($resinComponent['quantity_required']) ? rtrim(rtrim(number_format((float) $resinComponent['quantity_required'], 3, '.', ''), '0'), '.') : '';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($eq['equipment_type'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['serial_number'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['tank_size'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($resinPartNumber !== '' ? $resinPartNumber . ($resinQty !== '' ? ' (Qty: ' . $resinQty . ')' : '') : 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= ucfirst($eq['ownership'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $locationRaw = strtolower(trim((string) ($eq['location'] ?? '')));
                                        $locationClass = 'location-other';
                                        if ($locationRaw === 'pool') {
                                            $locationClass = 'location-pool';
                                        } elseif ($locationRaw === 'production') {
                                            $locationClass = 'location-production';
                                        } elseif ($locationRaw === 'warehouse') {
                                            $locationClass = 'location-warehouse';
                                        } elseif ($locationRaw === 'customer site') {
                                            $locationClass = 'location-customer-site';
                                        }
                                        $locationLabel = $locationRaw !== '' ? $locationRaw : 'n/a';
                                        ?>
                                        <span class="location-chip <?= htmlspecialchars($locationClass) ?>"><?= htmlspecialchars($locationLabel) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($eq['service_frequency'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['install_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['last_service_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['next_service_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['status'] ?? 'Active') ?></td>
                                    <td>
                                        <a href="equipment_view.php?id=<?= urlencode($eq['equipment_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">No customer-owned tanks tracked yet.</div>
            <?php endif; ?>
        </fieldset>


        <fieldset class="cv-fieldset">
            <legend><strong>🔁 Service and Rental Tanks At This Site (<?= count($serviceEquipment ?? []) ?>)</strong></legend>
            <?php if (!empty($serviceEquipment)): ?>
            <div class="cv-table-wrap">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Serial #</th>
                                <th>Tank Size</th>
                                <th>Resin Part #</th>
                                <th>Ownership</th>
                                <th>Location</th>
                                <th>Service Frequency</th>
                                <th>Install Date</th>
                                <th>Last Service</th>
                                <th>Next Service</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($serviceEquipment as $eq): ?>
                                <?php
                                $resinComponent = $componentsByEquipment[$eq['equipment_id']]['resin'] ?? null;
                                $resinPartNumber = $resinComponent['item_id'] ?? ($eq['resin_type'] ?? '');
                                $resinQty = isset($resinComponent['quantity_required']) ? rtrim(rtrim(number_format((float) $resinComponent['quantity_required'], 3, '.', ''), '0'), '.') : '';
                                $eqStatus = $eq['status'] ?? 'Active';
                                $isTrial  = strtolower($eqStatus) === 'trial';
                                ?>
                                <tr<?= $isTrial ? ' style="background:#fffbe6;"' : '' ?>>
                                    <td><?= htmlspecialchars($eq['equipment_type'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['serial_number'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($eq['tank_size'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($resinPartNumber !== '' ? $resinPartNumber . ($resinQty !== '' ? ' (Qty: ' . $resinQty . ')' : '') : 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= ucfirst($eq['ownership'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $locationRaw = strtolower(trim((string) ($eq['location'] ?? '')));
                                        $locationClass = 'location-other';
                                        if ($locationRaw === 'pool') {
                                            $locationClass = 'location-pool';
                                        } elseif ($locationRaw === 'production') {
                                            $locationClass = 'location-production';
                                        } elseif ($locationRaw === 'warehouse') {
                                            $locationClass = 'location-warehouse';
                                        } elseif ($locationRaw === 'customer site') {
                                            $locationClass = 'location-customer-site';
                                        }
                                        $locationLabel = $locationRaw !== '' ? $locationRaw : 'n/a';
                                        ?>
                                        <span class="location-chip <?= htmlspecialchars($locationClass) ?>"><?= htmlspecialchars($locationLabel) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($eq['service_frequency'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['install_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['last_service_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($eq['next_service_date'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($isTrial): ?>
                                            <span class="badge bg-warning text-dark">Trial</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($eqStatus) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="equipment_view.php?id=<?= urlencode($eq['equipment_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php if ($isTrial): ?>
                                            <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>&contact_id=<?= urlencode($customer['contact_id'] ?? '') ?>" class="btn btn-sm btn-success ms-1">📄 Create Contract</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="cv-action-row">
                    <a href="equipment_form.php" class="btn-outline">➕ Add Equipment</a>
                </div>
            <?php else: ?>
                <div class="no-data">No service/rental tanks assigned at this site. <a href="equipment_form.php">Add equipment</a></div>
            <?php endif; ?>
        </fieldset>

    <!-- ── CONTRACTS & REVENUE ────────────────────────────────────────────────── -->

    <?php if ($activeCount > 0): ?>
        <div class="cv-kpi-wrap">
            <div class="cv-kpi-card">
                <div class="cv-kpi-label">Annual Value</div>
                <div class="cv-kpi-value">$<?= number_format($totalARR, 2) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <fieldset class="cv-fieldset">
        <legend><strong>💰 Service Contracts & Revenue</strong></legend>
        <?php if ($activeCount > 0): ?>
            <div class="cv-kpi-grid">
                <div class="cv-kpi-card">
                    <div class="cv-kpi-label">Active Contracts</div>
                    <div class="cv-kpi-value"><?= $activeCount ?></div>
                </div>
                <div class="cv-kpi-card">
                    <div class="cv-kpi-label">Monthly Recurring Revenue</div>
                    <div class="cv-kpi-value">$<?= number_format($totalMRR, 2) ?></div>
                    <div class="cv-kpi-sub"><?= $activeCount ?> active contract<?= $activeCount !== 1 ? 's' : '' ?></div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($contracts)): ?>
            <div class="cv-table-wrap">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Contract ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Monthly Rental</th>
                            <th>Regen Fee</th>
                            <th>Delivery Fee</th>
                            <th>Annual Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['contract_id']) ?></strong></td>
                                <td><?= htmlspecialchars($c['contract_type'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <?= htmlspecialchars($c['contract_status']) ?>
                                    </span>
                                </td>
                                <td>$<?= number_format((float)($c['monthly_fee'] ?? 0), 2) ?></td>
                                <td>$<?= number_format((float)($c['regen_fee'] ?? 0), 2) ?></td>
                                <td>$<?= number_format((float)($c['tank_sale_price'] ?? 0), 2) ?></td>
                                <td><strong>$<?= number_format((float)($c['annual_value'] ?? 0), 2) ?></strong></td>
                                <td>
                                    <a href="contract_view.php?id=<?= urlencode($c['contract_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    <a href="contract_edit.php?id=<?= urlencode($c['contract_id']) ?>" class="btn btn-sm btn-outline-secondary ms-1">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="cv-action-row">
                <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>" class="btn-outline">➕ New Contract</a>
            </div>
        <?php else: ?>
            <div class="no-data">No contracts found. <a href="contract_form.php?customer_id=<?= urlencode($customerId) ?>">Create a new contract</a></div>
        <?php endif; ?>
    </fieldset>


    <?php
    // The customer has one linked contact (customers.contact_id -> contacts.contact_id).
    // We use this to load discussion history shown below.
    $customerContacts = $contact ? [$contact] : [];
    ?>

    <?php
    // Fetch all discussions for all contacts under this customer
    $discussions = [];
    $discussionSchema = require __DIR__ . '/discussion_schema.php';
    $selectDiscussionFields = array_values(array_unique(array_merge(['id'], (array) $discussionSchema)));
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $selectDiscussionFields));
    if (!empty($customerContacts)) {
        $contactIds = array_map(function($c) { return intval($c['contact_id']); }, $customerContacts);
        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $conn = get_mysql_connection();
        $discussionQuery = "SELECT $fields FROM discussion_log WHERE contact_id IN ($placeholders) ORDER BY timestamp DESC";
        $stmtDisc = $conn->prepare($discussionQuery);
        if ($stmtDisc) {
            $types = str_repeat('i', count($contactIds));
            $stmtDisc->bind_param($types, ...$contactIds);
            $stmtDisc->execute();
            $result = $stmtDisc->get_result();
        } else {
            $result = null;
        }
        if ($result) {
            $seen = [];
            while ($row = $result->fetch_assoc()) {
                $uniqueKey = $row['id'] ?? md5(json_encode($row));
                if (!isset($seen[$uniqueKey])) {
                    $discussions[] = $row;
                    $seen[$uniqueKey] = true;
                }
            }
            $result->free();
        }
        if (isset($stmtDisc) && $stmtDisc) {
            $stmtDisc->close();
        }
        $conn->close();
    }

    // Note: $discussions now populated for all contacts under this customer.
    ?>
    <div class="accordion cv-discussions">
            <div class="accordion-header" onclick="toggleAccordion(this)">
                <div class="accordion-title">
                    <span>💬</span>
                    <span>Discussions (<?= is_array($discussions) ? count($discussions) : 0 ?>)</span>
                </div>
                <div class="accordion-icon">▶</div>
            </div>
            <div class="accordion-content">
                <div class="accordion-body">
                    <div class="section-title">📒 Activity & History</div>
                    <?php if (!empty($discussions)): ?>
                        <?php foreach ($discussions as $disc): ?>
                            <?php
                            $visibility = strtolower((string) ($disc['visibility'] ?? 'public'));
                            $visibilityClass = 'cv-visibility-public';
                            if ($visibility === 'internal') {
                                $visibilityClass = 'cv-visibility-internal';
                            } elseif ($visibility === 'private') {
                                $visibilityClass = 'cv-visibility-private';
                            }
                            ?>
                            <div class="timeline-item cv-timeline-item">
                                <div style="width: 100%;">
                                    <div class="cv-timeline-head">
                                        <strong class="cv-timeline-author"><?= htmlspecialchars($disc['author'] ?? 'Unknown') ?></strong>
                                        <span class="cv-visibility-badge <?= htmlspecialchars($visibilityClass) ?>">
                                            <?= htmlspecialchars($disc['visibility'] ?? 'public') ?>
                                        </span>
                                    </div>
                                    <div class="cv-timeline-meta">
                                        📅 <?= htmlspecialchars($disc['timestamp'] ?? '—') ?>
                                        <?php if (!empty($disc['manual_contact_id'])): ?>
                                            <span class="cv-manual-link">🔗 Linked by manual_contact_id: <?= htmlspecialchars($disc['manual_contact_id']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cv-timeline-body">
                                        <?= nl2br(htmlspecialchars($disc['entry_text'] ?? '')) ?>
                                    </div>
                                    <?php if (!empty($disc['linked_opportunity_id'])): ?>
                                        <div class="cv-linked">
                                            <strong>📎 Linked to Opportunity: #<?= htmlspecialchars($disc['linked_opportunity_id']) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="cv-empty">
                            No discussions logged yet for this contact.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <!-- Navigation -->

        <div class="cv-nav">
            <a href="customers_list.php" class="btn-outline">⬅ Back to Customers</a>
            <a href="index.php" class="btn-outline">⬅ Back to Home</a>
        </div>
</div>

<script>
// ── AI Integration ──────────────────────────────────────────────────────────
const AI_CONTACT_ID  = <?= json_encode($customer['contact_id'] ?? '') ?>;
const AI_CSRF_TOKEN  = <?= json_encode(getCSRFToken()) ?>;

function aiAction(action, btn, label) {
  const panel = document.getElementById('aiPanel');
  const body  = document.getElementById('aiPanelBody');
  const meta  = document.getElementById('aiPanelMeta');
  const title = document.getElementById('aiPanelTitle');

  title.textContent = '🤖 AI: ' + label;
  body.textContent  = 'Thinking…';
  meta.textContent  = '';
  panel.classList.add('visible');
  panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  const origLabel = btn ? btn.innerHTML : '';
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ai-spinner"></span> Thinking…'; }

  const fd = new FormData();
  fd.append('action',     action);
  fd.append('contact_id', AI_CONTACT_ID);
  fd.append('csrf_token', AI_CSRF_TOKEN);

  fetch('ai_endpoint.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        body.textContent = '⚠️ ' + data.error;
      } else {
        body.textContent = data.text || '(no response)';
                if (data.provider && data.model) {
                    var selectionLabel = data.selection_mode === 'cheapest' ? ' · chosen by cost' : ' · manual selection';
                    meta.textContent = 'via ' + data.provider + ' / ' + data.model + selectionLabel;
                }
      }
    })
    .catch(err => { body.textContent = '⚠️ Network error: ' + err.message; })
    .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = origLabel; } });
}

function closeAiPanel() {
  document.getElementById('aiPanel').classList.remove('visible');
}

function copyAiResult() {
  const text = document.getElementById('aiPanelBody').textContent;
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.ai-copy-btn');
    const orig = btn.textContent;
    btn.textContent = '✓ Copied!';
    setTimeout(() => { btn.textContent = orig; }, 2000);
  });
}
</script>

        </div> <!-- end .container -->
    </div> <!-- end .content-container -->
</div> <!-- end .main-content -->
<?php include_once(__DIR__ . '/layout_end.php'); ?>