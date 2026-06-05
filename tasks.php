<?php
// tasks.php - Modern CRM Task Management Page
require_once 'layout_start.php';
require_once 'tasks_mysql.php';

function normalizeTaskValue($value): string {
  return strtolower(trim((string) $value));
}

function taskStatusLabel(string $status): string {
  $labels = [
    'not_started' => 'Not Started',
    'in_progress' => 'In Progress',
    'waiting' => 'Waiting/Blocked',
    'review' => 'Review',
    'completed' => 'Completed',
    'archived' => 'Archived',
  ];

  $normalized = normalizeTaskValue($status);
  if ($normalized === '') {
    return '';
  }

  return $labels[$normalized] ?? ucwords(str_replace('_', ' ', $normalized));
}

function classifyStatusDiffTone(string $fromStatus, string $toStatus): string {
  $terminalStatuses = ['completed', 'archived'];
  $from = normalizeTaskValue($fromStatus);
  $to = normalizeTaskValue($toStatus);

  if ($to === '' && $from === '') {
    return 'neutral';
  }

  if (!in_array($from, $terminalStatuses, true) && in_array($to, $terminalStatuses, true)) {
    return 'closed';
  }

  if (in_array($from, $terminalStatuses, true) && !in_array($to, $terminalStatuses, true) && $to !== '') {
    return 'reopened';
  }

  return 'progress';
}

function statusDiffToneStyle(string $tone): string {
  if ($tone === 'closed') {
    return 'color:#7c2d12;';
  }
  if ($tone === 'reopened') {
    return 'color:#1d4ed8;';
  }
  return 'color:#0f766e;';
}

function isTaskOpenStatus(string $status): bool {
  return !in_array($status, ['completed', 'archived'], true);
}

function isTaskAssignedToUser(array $task, string $identity): bool {
  if ($identity === '') {
    return false;
  }

  $assigned = normalizeTaskValue($task['assigned_to'] ?? '');
  return $assigned !== '' && $assigned === $identity;
}

function fetchRecentTaskAuditPreviews(array $taskIds): array {
  $taskIds = array_values(array_unique(array_filter(array_map(static function ($id) {
    return trim((string) $id);
  }, $taskIds), static function ($id) {
    return $id !== '';
  })));

  if (empty($taskIds) || !function_exists('get_mysql_connection')) {
    return [];
  }

  try {
    $conn = get_mysql_connection();
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $sql = "SELECT entity_id, timestamp, user_id, summary, status FROM audit_log WHERE entity_type = 'task' AND entity_id IN ($placeholders) ORDER BY timestamp DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      $conn->close();
      return [];
    }

    $types = str_repeat('s', count($taskIds));
    $stmt->bind_param($types, ...$taskIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $previews = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $entityId = trim((string) ($row['entity_id'] ?? ''));
        if ($entityId === '' || isset($previews[$entityId])) {
          continue;
        }
        $previews[$entityId] = [
          'summary' => trim((string) ($row['summary'] ?? '')),
          'timestamp' => trim((string) ($row['timestamp'] ?? '')),
          'user_id' => trim((string) ($row['user_id'] ?? '')),
          'status' => trim((string) ($row['status'] ?? '')),
        ];
      }
      $result->free();
    }

    $stmt->close();
    $conn->close();
    return $previews;
  } catch (Throwable $e) {
    return [];
  }
}

function formatAuditPreviewTime(string $timestamp): string {
  if ($timestamp === '') {
    return '';
  }

  $parsed = strtotime($timestamp);
  if ($parsed === false) {
    return $timestamp;
  }

  return date('Y-m-d H:i', $parsed);
}

function extractStatusDiffFromChanges($changesRaw): string {
  if (!is_string($changesRaw) || trim($changesRaw) === '') {
    return '';
  }

  $decoded = json_decode($changesRaw, true);
  if (!is_array($decoded) || !isset($decoded['status']) || !is_array($decoded['status'])) {
    return '';
  }

  $old = trim((string) ($decoded['status']['old'] ?? ''));
  $new = trim((string) ($decoded['status']['new'] ?? ''));
  if ($old === '' && $new === '') {
    return '';
  }

  return $old . ' -> ' . $new;
}

function extractStatusDiffPartsFromChanges($changesRaw): array {
  if (!is_string($changesRaw) || trim($changesRaw) === '') {
    return ['', ''];
  }

  $decoded = json_decode($changesRaw, true);
  if (!is_array($decoded) || !isset($decoded['status']) || !is_array($decoded['status'])) {
    return ['', ''];
  }

  $old = trim((string) ($decoded['status']['old'] ?? ''));
  $new = trim((string) ($decoded['status']['new'] ?? ''));
  return [$old, $new];
}

function fetchTaskAuditHistories(array $taskIds, int $limitPerTask = 3): array {
  $taskIds = array_values(array_unique(array_filter(array_map(static function ($id) {
    return trim((string) $id);
  }, $taskIds), static function ($id) {
    return $id !== '';
  })));

  if (empty($taskIds) || $limitPerTask < 1 || !function_exists('get_mysql_connection')) {
    return [];
  }

  try {
    $conn = get_mysql_connection();
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $sql = "SELECT entity_id, timestamp, user_id, summary, action, status, changes FROM audit_log WHERE entity_type = 'task' AND entity_id IN ($placeholders) ORDER BY timestamp DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      $conn->close();
      return [];
    }

    $types = str_repeat('s', count($taskIds));
    $stmt->bind_param($types, ...$taskIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $historyMap = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $entityId = trim((string) ($row['entity_id'] ?? ''));
        if ($entityId === '') {
          continue;
        }

        if (!isset($historyMap[$entityId])) {
          $historyMap[$entityId] = [];
        }
        if (count($historyMap[$entityId]) >= $limitPerTask) {
          continue;
        }

        $historyMap[$entityId][] = [
          'summary' => trim((string) ($row['summary'] ?? '')),
          'timestamp' => trim((string) ($row['timestamp'] ?? '')),
          'user_id' => trim((string) ($row['user_id'] ?? '')),
          'action' => trim((string) ($row['action'] ?? '')),
          'status' => trim((string) ($row['status'] ?? '')),
          'status_diff' => extractStatusDiffFromChanges((string) ($row['changes'] ?? '')),
        ];
        [$statusFrom, $statusTo] = extractStatusDiffPartsFromChanges((string) ($row['changes'] ?? ''));
        $historyIndex = count($historyMap[$entityId]) - 1;
        $historyMap[$entityId][$historyIndex]['status_from'] = $statusFrom;
        $historyMap[$entityId][$historyIndex]['status_to'] = $statusTo;
        $historyMap[$entityId][$historyIndex]['status_diff_label'] = ($statusFrom !== '' || $statusTo !== '')
          ? taskStatusLabel($statusFrom) . ' -> ' . taskStatusLabel($statusTo)
          : '';
        $historyMap[$entityId][$historyIndex]['status_diff_tone'] = classifyStatusDiffTone($statusFrom, $statusTo);
      }
      $result->free();
    }

    $stmt->close();
    $conn->close();
    return $historyMap;
  } catch (Throwable $e) {
    return [];
  }
}

function renderTaskAuditHistoryHtml(array $entries): string {
  if (empty($entries)) {
    return '<div style="font-size:12px;color:#6b7280;">No audit history available for this task.</div>';
  }

  $items = '';
  foreach ($entries as $entry) {
    $summary = trim((string) ($entry['summary'] ?? ''));
    $summary = $summary !== '' ? $summary : 'Task activity logged';
    $timestamp = formatAuditPreviewTime(trim((string) ($entry['timestamp'] ?? '')));
    $userId = trim((string) ($entry['user_id'] ?? ''));
    $action = trim((string) ($entry['action'] ?? ''));
    $status = trim((string) ($entry['status'] ?? ''));
    $statusDiff = trim((string) ($entry['status_diff'] ?? ''));
    $statusDiffLabel = trim((string) ($entry['status_diff_label'] ?? ''));
    $statusDiffTone = trim((string) ($entry['status_diff_tone'] ?? ''));
    $hasStatusDiff = ($statusDiff !== '' || $statusDiffLabel !== '');

    $metaParts = [];
    if ($timestamp !== '') {
      $metaParts[] = $timestamp;
    }
    if ($userId !== '') {
      $metaParts[] = 'by ' . $userId;
    }
    if ($action !== '') {
      $metaParts[] = strtoupper($action);
    }
    if ($status !== '') {
      $metaParts[] = $status;
    }

    $items .= '<li data-has-diff="' . ($hasStatusDiff ? '1' : '0') . '" style="margin:0 0 8px 0;">'
      . '<div style="font-size:12px;font-weight:600;color:#111827;">' . htmlspecialchars($summary) . '</div>'
      . '<div style="font-size:11px;color:#6b7280;">' . htmlspecialchars(implode(' | ', $metaParts)) . '</div>'
      . ($statusDiff !== ''
        ? '<div style="font-size:11px;' . statusDiffToneStyle($statusDiffTone) . '">status: ' . htmlspecialchars($statusDiffLabel !== '' ? $statusDiffLabel : $statusDiff) . '</div>'
        : '')
      . '</li>';
  }

  return '<div class="task-audit-history-shell" tabindex="0" aria-label="Task audit history panel" data-origin-toast-cooldown-ms="550" data-origin-success-live-cooldown-ms="650" data-origin-failure-live-cooldown-ms="700" data-origin-recovery-window-ms="2400">'
    . '<div class="task-audit-history-filters" style="display:flex;gap:6px;margin:0 0 8px 0;">'
    . '<button type="button" class="js-audit-history-chip is-active" data-filter="all" style="border:1px solid #0f766e;background:#ecfeff;color:#0f766e;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:600;cursor:pointer;">All Events</button>'
    . '<button type="button" class="js-audit-history-chip" data-filter="status_changes" style="border:1px solid #cbd5e1;background:#fff;color:#475569;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:600;cursor:pointer;">Status Changes</button>'
    . '<span class="js-audit-history-source" style="display:inline-flex;align-items:center;margin-left:2px;font-size:10px;color:#64748b;white-space:nowrap;">Source: Default</span>'
    . '<button type="button" class="js-audit-origin-chip" title="Copy origin label" aria-label="Copy origin label" style="display:inline-flex;align-items:center;margin-left:6px;font-size:10px;color:#475569;background:#f8fafc;border:1px solid #cbd5e1;border-radius:999px;padding:1px 6px;white-space:nowrap;cursor:pointer;">Origin: Default</button>'
    . '<span class="js-audit-history-summary" style="display:inline-flex;align-items:center;margin-left:6px;font-size:10px;color:#64748b;white-space:nowrap;">Rows overridden: 0</span>'
    . '<span class="js-audit-global-badge" style="display:inline-flex;align-items:center;margin-left:6px;font-size:10px;color:#1f2937;background:#e5e7eb;border-radius:999px;padding:1px 6px;white-space:nowrap;">Global mode: Off</span>'
    . '<span class="js-audit-precedence-hint" style="display:inline-flex;align-items:center;margin-left:6px;font-size:10px;color:#6b7280;white-space:nowrap;" title="Filter priority order">Priority: Row > Global > Default</span>'
    . '<label style="display:inline-flex;align-items:center;gap:4px;margin-left:8px;font-size:11px;color:#64748b;cursor:pointer;">'
    . '<input type="checkbox" class="js-audit-history-remember-global" style="margin:0;">Remember for all rows'
    . '</label>'
    . '<button type="button" class="js-audit-history-apply-visible" style="margin-left:auto;border:none;background:transparent;color:#0f766e;font-size:11px;font-weight:600;padding:0;cursor:pointer;">Apply this view to visible rows</button>'
    . '<button type="button" class="js-audit-history-clear-visible" style="border:none;background:transparent;color:#7c2d12;font-size:11px;font-weight:600;padding:0;cursor:pointer;">Clear row overrides (visible)</button>'
    . '<button type="button" class="js-audit-history-reset" style="border:none;background:transparent;color:#64748b;font-size:11px;font-weight:600;padding:0;cursor:pointer;">Reset view</button>'
    . '</div>'
    . '<div class="js-audit-cheatline" style="display:none;font-size:10px;color:#334155;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:2px 8px;margin:0 0 6px 0;width:max-content;">Keys: A S R C G H M Y O Shift+Y Shift+O Ctrl+Y Shift+M Shift+P Shift+K ? Esc</div>'
    . '<div class="js-audit-shortcut-hint" style="display:none;font-size:10px;color:#64748b;margin:0 0 6px 0;">Shortcuts: A S R C G H M Y O Shift+Y Shift+O Ctrl+Y Shift+M Shift+P Shift+K ? Esc <span class="js-audit-hint-mode-badge" style="display:inline-flex;align-items:center;margin-left:6px;padding:1px 6px;border-radius:999px;border:1px solid #cbd5e1;background:#f8fafc;color:#334155;font-size:10px;font-weight:600;">Hint: Compact</span> <button type="button" class="js-audit-hint-reset" style="margin-left:6px;border:none;background:transparent;color:#0f766e;font-size:10px;font-weight:700;padding:0;cursor:pointer;">Reset hint</button> <button type="button" class="js-audit-hint-toast-toggle" aria-pressed="false" style="margin-left:6px;border:none;background:transparent;color:#64748b;font-size:10px;font-weight:700;padding:0;cursor:pointer;">Mute hint toasts</button> <span class="js-audit-hint-toast-muted-chip" style="display:none;align-items:center;margin-left:6px;padding:1px 6px;border-radius:999px;border:1px solid #7c2d12;background:#fef2f2;color:#7c2d12;font-size:10px;font-weight:700;">Muted</span></div>'
    . '<div class="js-audit-shortcut-help" style="display:none;font-size:10px;color:#334155;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:6px 8px;margin:0 0 6px 0;">Shortcut help: A = All Events, S = Status Changes, R = Reset view, C = Clear overrides, G = Toggle global mode, H = Toggle hint detail, M = Toggle hint toasts mute, Y = Copy current source label, O = Copy current origin label, Shift+Y = Copy source/hint/toasts snapshot, Shift+O = Copy origin/source/filter context, Ctrl+Y = Copy source/filter/origin context, Shift+H = Reset hint compact, Shift+M = Unmute hint toasts, Shift+P = Reload copy policy from shell attributes, Shift+K = Copy policy readout text, Reset hint button = Compact, ? = Toggle this help. Ctx note: Origin Context = Origin + Source + Filter.<div class="js-audit-policy-readout" style="margin-top:4px;padding-top:4px;border-top:1px dashed #cbd5e1;color:#334155;font-weight:600;">Policy: Toast 550ms | Success 650ms | Failure 700ms | Recovery 2400ms</div></div>'
    . '<div class="js-audit-key-status" style="display:none;font-size:10px;color:#64748b;margin:0 0 6px 0;">Last key action: none</div>'
    . '<div class="js-audit-shortcut-state" style="display:block;font-size:10px;color:#475569;margin:0 0 4px 0;"><span class="js-audit-shortcut-state-prefix">Shortcut state: Hint Compact | Toasts On | Source </span><button type="button" class="js-audit-shortcut-state-glyph" title="Session source marker (click to copy)" aria-label="Copy source label" style="display:inline-flex;align-items:center;justify-content:center;min-width:16px;height:14px;padding:0 3px;margin-right:4px;border-radius:999px;border:1px solid #cbd5e1;background:#f8fafc;color:#475569;font-size:9px;font-weight:700;line-height:1;cursor:pointer;">[S]</button><span class="js-audit-shortcut-copy-badge" style="display:none;align-items:center;margin-right:4px;padding:0 5px;height:14px;border-radius:999px;background:#dcfce7;color:#166534;font-size:9px;font-weight:700;line-height:1;">Copied</span><span class="js-audit-shortcut-state-source" title="Session: restored from saved row/session preferences." style="display:inline-block;transition:transform .18s ease, background-color .25s ease, color .25s ease;transform:scale(1);border-radius:6px;padding:0 2px;">Session</span><span class="js-audit-source-freshness" style="display:none;color:#64748b;opacity:1;transition:opacity .8s ease;margin-left:2px;">| just now</span></div>'
    . '<div class="js-audit-source-legend" style="display:block;font-size:10px;color:#64748b;margin:0 0 4px 0;">Legend: <span style="display:inline-flex;align-items:center;gap:3px;"><span style="width:7px;height:7px;border-radius:999px;background:#0f766e;display:inline-block;"></span>Keyboard</span> <span style="display:inline-flex;align-items:center;gap:3px;margin-left:8px;"><span style="width:7px;height:7px;border-radius:999px;background:#1d4ed8;display:inline-block;"></span>Button</span> <span style="display:inline-flex;align-items:center;gap:3px;margin-left:8px;"><span style="width:7px;height:7px;border-radius:999px;background:#7c3aed;display:inline-block;"></span>Session</span></div>'
    . '<div class="js-audit-source-meaning" style="display:block;font-size:10px;color:#475569;margin:0 0 6px 0;padding:1px 6px;border-radius:6px;transition:background-color .3s ease,color .3s ease,font-weight .25s ease;">Current source meaning: Session restored your saved row/session preference.</div>'
    . '<div class="js-audit-hint-live" aria-live="polite" aria-atomic="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">Hint mode: Compact</div>'
    . '<ul class="task-audit-history-list" style="margin:0;padding-left:18px;">' . $items . '</ul>'
    . '<div class="task-audit-history-empty" style="display:none;font-size:11px;color:#64748b;margin-top:6px;">No status-change events in this window.</div>'
    . '</div>';
}

// Fetch tasks from DB
$tasks = fetch_tasks_mysql();

$currentUserIdentity = normalizeTaskValue($_SESSION['username'] ?? ($_SESSION['user_id'] ?? ''));
$allowedViews = ['all', 'open', 'my_open'];
$allowedStatuses = ['all', 'not_started', 'in_progress', 'waiting', 'review', 'completed', 'archived'];
$defaultView = $currentUserIdentity !== '' ? 'my_open' : 'all';

if (isset($_GET['reset_filters']) && $_GET['reset_filters'] === '1') {
  unset($_SESSION['tasks_filter_view'], $_SESSION['tasks_filter_status'], $_SESSION['tasks_filter_assignee']);
}

$hasExplicitFilterInput = isset($_GET['view']) || isset($_GET['status']) || isset($_GET['assignee']);

if ($hasExplicitFilterInput) {
  $requestedView = trim((string) ($_GET['view'] ?? $defaultView));
  $requestedStatus = trim((string) ($_GET['status'] ?? 'all'));
  $requestedAssignee = trim((string) ($_GET['assignee'] ?? ''));

  $viewFilter = in_array($requestedView, $allowedViews, true) ? $requestedView : $defaultView;
  $statusFilter = in_array($requestedStatus, $allowedStatuses, true) ? $requestedStatus : 'all';
  $assigneeFilter = $requestedAssignee;

  $_SESSION['tasks_filter_view'] = $viewFilter;
  $_SESSION['tasks_filter_status'] = $statusFilter;
  $_SESSION['tasks_filter_assignee'] = $assigneeFilter;
} else {
  $viewFilter = (string) ($_SESSION['tasks_filter_view'] ?? $defaultView);
  $statusFilter = (string) ($_SESSION['tasks_filter_status'] ?? 'all');
  $assigneeFilter = (string) ($_SESSION['tasks_filter_assignee'] ?? '');

  if (!in_array($viewFilter, $allowedViews, true)) {
    $viewFilter = $defaultView;
  }
  if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
  }
}

if ($viewFilter === 'my_open' && $currentUserIdentity === '') {
  $viewFilter = 'all';
}

$filteredTasks = [];
foreach ($tasks as $task) {
  $status = (string) ($task['status'] ?? '');

  if ($statusFilter !== 'all' && $statusFilter !== '' && $status !== $statusFilter) {
    continue;
  }

  if ($viewFilter === 'my_open' && !isTaskAssignedToUser($task, $currentUserIdentity)) {
    continue;
  }

  if ($viewFilter === 'open' && !isTaskOpenStatus($status)) {
    continue;
  }

  if ($assigneeFilter !== '' && normalizeTaskValue($task['assigned_to'] ?? '') !== normalizeTaskValue($assigneeFilter)) {
    continue;
  }

  $filteredTasks[] = $task;
}

$today = date('Y-m-d');
$summary = [
  'total' => count($filteredTasks),
  'my_open' => 0,
  'due_today' => 0,
  'overdue' => 0,
];

foreach ($filteredTasks as $task) {
  $status = (string) ($task['status'] ?? '');
  $dueDate = trim((string) ($task['due_date'] ?? ''));

  if (isTaskAssignedToUser($task, $currentUserIdentity) && isTaskOpenStatus($status)) {
    $summary['my_open']++;
  }

  if ($dueDate === $today && isTaskOpenStatus($status)) {
    $summary['due_today']++;
  }

  if ($dueDate !== '' && $dueDate < $today && isTaskOpenStatus($status)) {
    $summary['overdue']++;
  }
}

$returnQueryParams = [];
if ($viewFilter !== '' && $viewFilter !== $defaultView) {
  $returnQueryParams['view'] = $viewFilter;
}
if ($statusFilter !== '' && $statusFilter !== 'all') {
  $returnQueryParams['status'] = $statusFilter;
}
if ($assigneeFilter !== '') {
  $returnQueryParams['assignee'] = $assigneeFilter;
}
$returnQuery = http_build_query($returnQueryParams);
$taskAuditPreviews = fetchRecentTaskAuditPreviews(array_map(static function ($task) {
  return $task['id'] ?? '';
}, $filteredTasks));
$taskAuditHistories = fetchTaskAuditHistories(array_map(static function ($task) {
  return $task['id'] ?? '';
}, $filteredTasks), 3);

// Status badge colors
$statusColors = [
    'not_started' => '#f8d7da',
    'in_progress' => '#fff3cd',
    'waiting' => '#d1ecf1',
    'review' => '#d6d8d9',
    'completed' => '#d4edda',
    'archived' => '#e2e3e5',
];

function status_badge($status) {
    global $statusColors;
    $label = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'waiting' => 'Waiting/Blocked',
        'review' => 'Review',
        'completed' => 'Completed',
        'archived' => 'Archived',
    ];
    $color = $statusColors[$status] ?? '#eee';
    $text = $label[$status] ?? ucfirst($status);
    return "<span class='task-status-badge' data-status='" . htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8') . "' style='background:$color;padding:4px 10px;border-radius:12px;font-weight:600;font-size:0.95em;'>$text</span>";
}
?>

<div class="tasks-main-wrapper" style="max-width:1100px;margin:40px auto;padding:0 20px;">
  <h1 style="font-size:2.2em;font-weight:700;color:#222;">Tasks</h1>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0 20px 0;">
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;">
      <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.4px;">Visible Tasks</div>
      <div style="font-size:24px;font-weight:700;color:#111827;"><?= (int) $summary['total'] ?></div>
    </div>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;">
      <div style="font-size:12px;color:#166534;text-transform:uppercase;letter-spacing:0.4px;">My Open Tasks</div>
      <div style="font-size:24px;font-weight:700;color:#166534;"><?= (int) $summary['my_open'] ?></div>
    </div>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;">
      <div style="font-size:12px;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.4px;">Due Today</div>
      <div style="font-size:24px;font-weight:700;color:#1d4ed8;"><?= (int) $summary['due_today'] ?></div>
    </div>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;">
      <div style="font-size:12px;color:#b91c1c;text-transform:uppercase;letter-spacing:0.4px;">Overdue</div>
      <div style="font-size:24px;font-weight:700;color:#b91c1c;"><?= (int) $summary['overdue'] ?></div>
    </div>
  </div>

  <form method="GET" action="tasks.php" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;">
    <select name="view" style="padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
      <option value="all" <?= $viewFilter === 'all' ? 'selected' : '' ?>>All Tasks</option>
      <option value="open" <?= $viewFilter === 'open' ? 'selected' : '' ?>>Open Tasks</option>
      <option value="my_open" <?= $viewFilter === 'my_open' ? 'selected' : '' ?>>My Open Tasks</option>
    </select>

    <select name="status" style="padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
      <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
      <option value="not_started" <?= $statusFilter === 'not_started' ? 'selected' : '' ?>>Not Started</option>
      <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
      <option value="waiting" <?= $statusFilter === 'waiting' ? 'selected' : '' ?>>Waiting/Blocked</option>
      <option value="review" <?= $statusFilter === 'review' ? 'selected' : '' ?>>Review</option>
      <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
      <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
    </select>

    <input type="text" name="assignee" value="<?= htmlspecialchars($assigneeFilter) ?>" placeholder="Filter assignee" style="padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;min-width:180px;">

    <button type="submit" style="padding:8px 14px;border:none;border-radius:6px;background:#111827;color:#fff;font-weight:600;">Apply</button>
    <a href="tasks.php?reset_filters=1" style="padding:8px 14px;border-radius:6px;background:#f3f4f6;color:#111827;text-decoration:none;font-weight:600;">Reset</a>
  </form>

  <div style="margin-bottom:24px;">
    <form method="POST" action="add_task.php" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <?php renderCSRFInput(); ?>
      <input type="text" name="title" placeholder="Task Title" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
      <input type="date" name="due_date" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
      <select name="status" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
        <option value="not_started">Not Started</option>
        <option value="in_progress">In Progress</option>
        <option value="waiting">Waiting/Blocked</option>
        <option value="review">Review</option>
        <option value="completed">Completed</option>
        <option value="archived">Archived</option>
      </select>
      <input type="text" name="priority" placeholder="Priority" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
      <input type="text" name="assigned_to" placeholder="Assignee" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
      <button type="submit" style="padding:8px 24px;border-radius:6px;background:#0099A8;color:#fff;font-weight:600;border:none;">Add Task</button>
    </form>
  </div>
  <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
    <thead>
      <tr style="background:#f3f4f6;">
        <th style="padding:14px;">Title</th>
        <th>Status</th>
        <th>Due Date</th>
        <th>Priority</th>
        <th>Assignee</th>
        <th>Contact</th>
        <th>Opportunity</th>
        <th>Project</th>
        <th>Description</th>
        <th>Comments</th>
        <th>Recurrence</th>
        <th>Attachment</th>
        <th>Recent Audit</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($filteredTasks as $task): ?>
        <tr data-task-id="<?= htmlspecialchars((string) $task['id']) ?>">
          <td style="padding:10px 8px;"><?= htmlspecialchars($task['title']) ?></td>
          <td class="task-status-cell"><?= status_badge($task['status']) ?></td>
          <td><?= htmlspecialchars($task['due_date']) ?></td>
          <td><?= htmlspecialchars($task['priority']) ?></td>
          <td><?= htmlspecialchars($task['assigned_to']) ?></td>
          <td><?= $task['contact_id'] ? '<a href="contact_view.php?id=' . intval($task['contact_id']) . '">' . intval($task['contact_id']) . '</a>' : '' ?></td>
          <td><?= $task['opportunity_id'] ? '<a href="edit_opportunity.php?id=' . intval($task['opportunity_id']) . '">' . intval($task['opportunity_id']) . '</a>' : '' ?></td>
          <td><?= $task['project_id'] ? intval($task['project_id']) : '' ?></td>
          <td><?= htmlspecialchars($task['description'] ?? '') ?></td>
          <td><?= htmlspecialchars($task['comments'] ?? '') ?></td>
          <td><?= htmlspecialchars($task['recurrence'] ?? '') ?></td>
          <td>
            <?php if (!empty($task['attachment'])): ?>
              <a href="<?= htmlspecialchars($task['attachment']) ?>" target="_blank">View</a>
            <?php endif; ?>
          </td>
          <td class="task-audit-cell" style="min-width:220px;">
            <?php
              $taskId = (string) ($task['id'] ?? '');
              $auditPreview = $taskAuditPreviews[(string) ($task['id'] ?? '')] ?? null;
              $auditHistory = $taskAuditHistories[$taskId] ?? [];
              if (is_array($auditPreview)):
                $auditSummary = trim((string) ($auditPreview['summary'] ?? ''));
                $auditTime = formatAuditPreviewTime(trim((string) ($auditPreview['timestamp'] ?? '')));
                $auditUser = trim((string) ($auditPreview['user_id'] ?? ''));
            ?>
              <div style="font-size:12px;color:#111827;font-weight:600;line-height:1.25;"><?= htmlspecialchars($auditSummary !== '' ? $auditSummary : 'Task activity logged') ?></div>
              <div style="font-size:11px;color:#6b7280;line-height:1.3;">
                <?= htmlspecialchars($auditTime) ?><?= $auditUser !== '' ? ' by ' . htmlspecialchars($auditUser) : '' ?>
              </div>
            <?php else: ?>
              <span style="font-size:11px;color:#9ca3af;">No recent task audit</span>
            <?php endif; ?>
            <div style="margin-top:6px;">
              <button type="button" class="js-audit-history-toggle" data-task-id="<?= htmlspecialchars($taskId) ?>" style="border:none;background:transparent;color:#0f766e;font-size:11px;font-weight:600;padding:0;cursor:pointer;">
                View last 3 events
              </button>
            </div>
          </td>
          <td>
            <form method="POST" action="update_task_status.php" class="js-inline-status-form" style="display:inline-flex;align-items:center;gap:6px;margin-right:8px;">
              <?php renderCSRFInput(); ?>
              <input type="hidden" name="id" value="<?= htmlspecialchars((string) $task['id']) ?>">
              <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
              <select name="status" class="js-inline-status-select" style="padding:4px 6px;border-radius:6px;border:1px solid #d1d5db;font-size:12px;">
                <option value="not_started" <?= ($task['status'] ?? '') === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                <option value="in_progress" <?= ($task['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="waiting" <?= ($task['status'] ?? '') === 'waiting' ? 'selected' : '' ?>>Waiting/Blocked</option>
                <option value="review" <?= ($task['status'] ?? '') === 'review' ? 'selected' : '' ?>>Review</option>
                <option value="completed" <?= ($task['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="archived" <?= ($task['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
              </select>
              <button type="submit" class="js-inline-status-save" data-default-label="Save" style="background:#0f766e;color:#fff;border:none;border-radius:6px;padding:5px 8px;font-size:12px;font-weight:600;">Save</button>
            </form>
            <a href="edit_task.php?id=<?= urlencode($task['id']) ?>" style="color:#007489;font-weight:600;">Edit</a> |
            <form method="POST" action="delete_task.php" style="display:inline;" onsubmit="return confirm('Delete this task?');">
              <?php renderCSRFInput(); ?>
              <input type="hidden" name="id" value="<?= htmlspecialchars($task['id']) ?>">
              <button type="submit" style="background:none;border:none;color:#c00;font-weight:600;cursor:pointer;padding:0;">Delete</button>
            </form>
          </td>
        </tr>
        <tr class="task-audit-history-row" data-task-id="<?= htmlspecialchars($taskId) ?>" hidden>
          <td colspan="14" style="background:#f8fafc;padding:10px 12px;border-top:1px solid #e5e7eb;">
            <div class="task-audit-history-content"><?= renderTaskAuditHistoryHtml($auditHistory) ?></div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div id="task-toast" style="position:fixed;right:22px;bottom:22px;z-index:11000;background:#0f172a;color:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 10px 24px rgba(15,23,42,0.25);font-size:13px;font-weight:600;opacity:0;transform:translateY(8px);pointer-events:none;transition:opacity .2s ease, transform .2s ease;"></div>

<script>
(function () {
  const statusLabels = {
    not_started: 'Not Started',
    in_progress: 'In Progress',
    waiting: 'Waiting/Blocked',
    review: 'Review',
    completed: 'Completed',
    archived: 'Archived'
  };

  const statusColors = {
    not_started: '#f8d7da',
    in_progress: '#fff3cd',
    waiting: '#d1ecf1',
    review: '#d6d8d9',
    completed: '#d4edda',
    archived: '#e2e3e5'
  };

  const activeStatusFilter = <?= json_encode((string) $statusFilter) ?>;
  const activeViewFilter = <?= json_encode((string) $viewFilter) ?>;
  const auditFilterStoragePrefix = 'taskAuditFilter:';
  const auditHintModeStoragePrefix = 'taskAuditHintMode:';
  const auditHintToastMuteStoragePrefix = 'taskAuditHintToastMute:';
  const auditGlobalEnabledKey = 'taskAuditGlobalEnabled';
  const auditGlobalFilterKey = 'taskAuditGlobalFilter';
  const toast = document.getElementById('task-toast');
  let toastTimer = null;
  let toastActionId = 0;
  const detailedHintRestoreToastShown = new Set();

  function getTaskIdForHistoryShell(shell) {
    if (!shell || typeof shell.closest !== 'function') {
      return '';
    }
    const row = shell.closest('tr.task-audit-history-row');
    if (!row) {
      return '';
    }
    return String(row.getAttribute('data-task-id') || '').trim();
  }

  function getStoredAuditFilter(taskId) {
    const safeTaskId = String(taskId || '').trim();
    if (!safeTaskId || typeof window.sessionStorage === 'undefined') {
      return '';
    }

    try {
      const stored = window.sessionStorage.getItem(auditFilterStoragePrefix + safeTaskId);
      if (stored === 'status_changes' || stored === 'all') {
        return stored;
      }
    } catch (error) {
      return '';
    }

    return '';
  }

  function setStoredAuditFilter(taskId, filter) {
    const safeTaskId = String(taskId || '').trim();
    const safeFilter = (filter === 'status_changes') ? 'status_changes' : 'all';
    if (!safeTaskId || typeof window.sessionStorage === 'undefined') {
      return;
    }

    try {
      window.sessionStorage.setItem(auditFilterStoragePrefix + safeTaskId, safeFilter);
    } catch (error) {
      // Ignore storage failures (private mode/quota) and continue gracefully.
    }
  }

  function clearStoredAuditFilter(taskId) {
    const safeTaskId = String(taskId || '').trim();
    if (!safeTaskId || typeof window.sessionStorage === 'undefined') {
      return;
    }

    try {
      window.sessionStorage.removeItem(auditFilterStoragePrefix + safeTaskId);
    } catch (error) {
      // Ignore storage failures (private mode/quota) and continue gracefully.
    }
  }

  function getStoredAuditHintMode(taskId) {
    const safeTaskId = String(taskId || '').trim();
    if (!safeTaskId || typeof window.sessionStorage === 'undefined') {
      return '';
    }

    try {
      const stored = window.sessionStorage.getItem(auditHintModeStoragePrefix + safeTaskId);
      if (stored === 'compact' || stored === 'detailed') {
        return stored;
      }
    } catch (error) {
      return '';
    }

    return '';
  }

  function setStoredAuditHintMode(taskId, mode) {
    const safeTaskId = String(taskId || '').trim();
    const safeMode = mode === 'detailed' ? 'detailed' : 'compact';
    if (!safeTaskId || typeof window.sessionStorage === 'undefined') {
      return;
    }

    try {
      window.sessionStorage.setItem(auditHintModeStoragePrefix + safeTaskId, safeMode);
    } catch (error) {
      // Ignore storage failures (private mode/quota) and continue gracefully.
    }
  }

  function isHintToastMuted(taskId) {
    const safeTaskId = String(taskId || '').trim();
    if (!safeTaskId || typeof window.sessionStorage === 'undefined') {
      return false;
    }

    try {
      return window.sessionStorage.getItem(auditHintToastMuteStoragePrefix + safeTaskId) === '1';
    } catch (error) {
      return false;
    }
  }

  function setHintToastMuted(taskId, muted) {
    const safeTaskId = String(taskId || '').trim();
    if (!safeTaskId || typeof window.sessionStorage === 'undefined') {
      return;
    }

    try {
      window.sessionStorage.setItem(auditHintToastMuteStoragePrefix + safeTaskId, muted ? '1' : '0');
    } catch (error) {
      // Ignore storage failures and continue gracefully.
    }
  }

  function getGlobalAuditFilterState() {
    if (typeof window.sessionStorage === 'undefined') {
      return { enabled: false, filter: 'all' };
    }

    try {
      const enabled = window.sessionStorage.getItem(auditGlobalEnabledKey) === '1';
      const storedFilter = window.sessionStorage.getItem(auditGlobalFilterKey);
      const filter = (storedFilter === 'status_changes' || storedFilter === 'all') ? storedFilter : 'all';
      return { enabled: enabled, filter: filter };
    } catch (error) {
      return { enabled: false, filter: 'all' };
    }
  }

  function setGlobalAuditFilterState(enabled, filter) {
    if (typeof window.sessionStorage === 'undefined') {
      return;
    }

    const safeEnabled = enabled ? '1' : '0';
    const safeFilter = (filter === 'status_changes') ? 'status_changes' : 'all';
    try {
      window.sessionStorage.setItem(auditGlobalEnabledKey, safeEnabled);
      window.sessionStorage.setItem(auditGlobalFilterKey, safeFilter);
    } catch (error) {
      // Ignore storage failures and continue gracefully.
    }
  }

  function syncRememberGlobalCheckboxes(checked) {
    document.querySelectorAll('.js-audit-history-remember-global').forEach(function (checkbox) {
      checkbox.checked = checked;
    });
  }

  function showToast(message, isError, action) {
    if (!toast) {
      return;
    }

    if (toastTimer) {
      window.clearTimeout(toastTimer);
    }

    const hasAction = action && typeof action.label === 'string' && typeof action.onClick === 'function';
    const actionId = hasAction ? (++toastActionId) : 0;

    toast.innerHTML = '';
    const messageSpan = document.createElement('span');
    messageSpan.textContent = message;
    toast.appendChild(messageSpan);

    if (hasAction) {
      const actionButton = document.createElement('button');
      actionButton.type = 'button';
      actionButton.textContent = action.label;
      actionButton.style.marginLeft = '10px';
      actionButton.style.border = 'none';
      actionButton.style.background = 'transparent';
      actionButton.style.color = '#67e8f9';
      actionButton.style.fontSize = '12px';
      actionButton.style.fontWeight = '700';
      actionButton.style.cursor = 'pointer';
      actionButton.addEventListener('click', function () {
        if (actionId !== toastActionId) {
          return;
        }
        action.onClick();
      });
      toast.appendChild(actionButton);
    }

    toast.style.background = isError ? '#991b1b' : '#0f172a';
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
    toast.style.pointerEvents = hasAction ? 'auto' : 'none';

    toastTimer = window.setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
      toast.style.pointerEvents = 'none';
    }, 2200);
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderBadge(status) {
    const key = status in statusLabels ? status : 'not_started';
    const label = statusLabels[key] || key;
    const color = statusColors[key] || '#eee';
    return '<span class="task-status-badge" data-status="' + escapeHtml(key) + '" style="background:' + color + ';padding:4px 10px;border-radius:12px;font-weight:600;font-size:0.95em;">' + escapeHtml(label) + '</span>';
  }

  function renderAuditPreview(preview) {
    if (!preview || typeof preview !== 'object') {
      return '<span style="font-size:11px;color:#9ca3af;">No recent task audit</span>';
    }

    const summary = String(preview.summary || '').trim() || 'Task activity logged';
    const timestamp = String(preview.timestamp || '').trim();
    const user = String(preview.user_id || '').trim();
    const meta = timestamp + (user ? (' by ' + user) : '');

    return '<div style="font-size:12px;color:#111827;font-weight:600;line-height:1.25;">' + escapeHtml(summary) + '</div>' +
      '<div style="font-size:11px;color:#6b7280;line-height:1.3;">' + escapeHtml(meta) + '</div>';
  }

  function renderAuditHistory(entries) {
    if (!Array.isArray(entries) || entries.length === 0) {
      return '<div style="font-size:12px;color:#6b7280;">No audit history available for this task.</div>';
    }

    function toStatusLabel(status) {
      const key = String(status || '').trim();
      if (!key) {
        return '';
      }
      if (statusLabels[key]) {
        return statusLabels[key];
      }
      return key.replace(/_/g, ' ').replace(/\b\w/g, function (match) { return match.toUpperCase(); });
    }

    function classifyTone(fromStatus, toStatus) {
      const terminal = ['completed', 'archived'];
      const fromKey = String(fromStatus || '').trim();
      const toKey = String(toStatus || '').trim();

      if (fromKey && toKey && !terminal.includes(fromKey) && terminal.includes(toKey)) {
        return 'closed';
      }
      if (fromKey && toKey && terminal.includes(fromKey) && !terminal.includes(toKey)) {
        return 'reopened';
      }
      return 'progress';
    }

    function toneColor(tone) {
      if (tone === 'closed') {
        return '#7c2d12';
      }
      if (tone === 'reopened') {
        return '#1d4ed8';
      }
      return '#0f766e';
    }

    const items = entries.map(function (entry) {
      const summary = String(entry && entry.summary ? entry.summary : '').trim() || 'Task activity logged';
      const timestamp = String(entry && entry.timestamp ? entry.timestamp : '').trim();
      const user = String(entry && entry.user_id ? entry.user_id : '').trim();
      const action = String(entry && entry.action ? entry.action : '').trim();
      const status = String(entry && entry.status ? entry.status : '').trim();
      const statusDiff = String(entry && entry.status_diff ? entry.status_diff : '').trim();
      const statusFrom = String(entry && entry.status_from ? entry.status_from : '').trim();
      const statusTo = String(entry && entry.status_to ? entry.status_to : '').trim();
      const explicitLabel = String(entry && entry.status_diff_label ? entry.status_diff_label : '').trim();
      const explicitTone = String(entry && entry.status_diff_tone ? entry.status_diff_tone : '').trim();
      const metaParts = [];

      if (timestamp) {
        metaParts.push(timestamp);
      }
      if (user) {
        metaParts.push('by ' + user);
      }
      if (action) {
        metaParts.push(action.toUpperCase());
      }
      if (status) {
        metaParts.push(status);
      }

      let diffLabel = explicitLabel;
      if (!diffLabel && statusDiff) {
        const parts = statusDiff.split('->').map(function (p) { return p.trim(); });
        if (parts.length === 2) {
          diffLabel = toStatusLabel(parts[0]) + ' -> ' + toStatusLabel(parts[1]);
        }
      }
      if (!diffLabel && (statusFrom || statusTo)) {
        diffLabel = toStatusLabel(statusFrom) + ' -> ' + toStatusLabel(statusTo);
      }

      const diffTone = explicitTone || classifyTone(statusFrom, statusTo);
      const hasDiff = Boolean(statusDiff || diffLabel);

      return '<li data-has-diff="' + (hasDiff ? '1' : '0') + '" style="margin:0 0 8px 0;">' +
        '<div style="font-size:12px;font-weight:600;color:#111827;">' + escapeHtml(summary) + '</div>' +
        '<div style="font-size:11px;color:#6b7280;">' + escapeHtml(metaParts.join(' | ')) + '</div>' +
        ((statusDiff || diffLabel) ? ('<div style="font-size:11px;color:' + toneColor(diffTone) + ';">status: ' + escapeHtml(diffLabel || statusDiff) + '</div>') : '') +
      '</li>';
    }).join('');

    return '<div class="task-audit-history-shell" tabindex="0" aria-label="Task audit history panel" data-origin-toast-cooldown-ms="550" data-origin-success-live-cooldown-ms="650" data-origin-failure-live-cooldown-ms="700" data-origin-recovery-window-ms="2400">' +
      '<div class="task-audit-history-filters" style="display:flex;gap:6px;margin:0 0 8px 0;">' +
        '<button type="button" class="js-audit-history-chip is-active" data-filter="all" style="border:1px solid #0f766e;background:#ecfeff;color:#0f766e;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:600;cursor:pointer;">All Events</button>' +
        '<button type="button" class="js-audit-history-chip" data-filter="status_changes" style="border:1px solid #cbd5e1;background:#fff;color:#475569;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:600;cursor:pointer;">Status Changes</button>' +
        '<span class="js-audit-history-source" style="display:inline-flex;align-items:center;margin-left:2px;font-size:10px;color:#64748b;white-space:nowrap;">Source: Default</span>' +
        '<button type="button" class="js-audit-origin-chip" title="Copy origin label" aria-label="Copy origin label" style="display:inline-flex;align-items:center;margin-left:6px;font-size:10px;color:#475569;background:#f8fafc;border:1px solid #cbd5e1;border-radius:999px;padding:1px 6px;white-space:nowrap;cursor:pointer;">Origin: Default</button>' +
        '<span class="js-audit-history-summary" style="display:inline-flex;align-items:center;margin-left:6px;font-size:10px;color:#64748b;white-space:nowrap;">Rows overridden: 0</span>' +
        '<span class="js-audit-global-badge" style="display:inline-flex;align-items:center;margin-left:6px;font-size:10px;color:#1f2937;background:#e5e7eb;border-radius:999px;padding:1px 6px;white-space:nowrap;">Global mode: Off</span>' +
        '<span class="js-audit-precedence-hint" style="display:inline-flex;align-items:center;margin-left:6px;font-size:10px;color:#6b7280;white-space:nowrap;" title="Filter priority order">Priority: Row > Global > Default</span>' +
        '<label style="display:inline-flex;align-items:center;gap:4px;margin-left:8px;font-size:11px;color:#64748b;cursor:pointer;">' +
          '<input type="checkbox" class="js-audit-history-remember-global" style="margin:0;">Remember for all rows' +
        '</label>' +
        '<button type="button" class="js-audit-history-apply-visible" style="margin-left:auto;border:none;background:transparent;color:#0f766e;font-size:11px;font-weight:600;padding:0;cursor:pointer;">Apply this view to visible rows</button>' +
        '<button type="button" class="js-audit-history-clear-visible" style="border:none;background:transparent;color:#7c2d12;font-size:11px;font-weight:600;padding:0;cursor:pointer;">Clear row overrides (visible)</button>' +
        '<button type="button" class="js-audit-history-reset" style="border:none;background:transparent;color:#64748b;font-size:11px;font-weight:600;padding:0;cursor:pointer;">Reset view</button>' +
      '</div>' +
      '<div class="js-audit-cheatline" style="display:none;font-size:10px;color:#334155;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:2px 8px;margin:0 0 6px 0;width:max-content;">Keys: A S R C G H M Y O Shift+Y Shift+O Ctrl+Y Shift+M Shift+P Shift+K ? Esc</div>' +
      '<div class="js-audit-shortcut-hint" style="display:none;font-size:10px;color:#64748b;margin:0 0 6px 0;">Shortcuts: A S R C G H M Y O Shift+Y Shift+O Ctrl+Y Shift+M Shift+P Shift+K ? Esc <span class="js-audit-hint-mode-badge" style="display:inline-flex;align-items:center;margin-left:6px;padding:1px 6px;border-radius:999px;border:1px solid #cbd5e1;background:#f8fafc;color:#334155;font-size:10px;font-weight:600;">Hint: Compact</span> <button type="button" class="js-audit-hint-reset" style="margin-left:6px;border:none;background:transparent;color:#0f766e;font-size:10px;font-weight:700;padding:0;cursor:pointer;">Reset hint</button> <button type="button" class="js-audit-hint-toast-toggle" aria-pressed="false" style="margin-left:6px;border:none;background:transparent;color:#64748b;font-size:10px;font-weight:700;padding:0;cursor:pointer;">Mute hint toasts</button> <span class="js-audit-hint-toast-muted-chip" style="display:none;align-items:center;margin-left:6px;padding:1px 6px;border-radius:999px;border:1px solid #7c2d12;background:#fef2f2;color:#7c2d12;font-size:10px;font-weight:700;">Muted</span></div>' +
      '<div class="js-audit-shortcut-help" style="display:none;font-size:10px;color:#334155;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:6px 8px;margin:0 0 6px 0;">Shortcut help: A = All Events, S = Status Changes, R = Reset view, C = Clear overrides, G = Toggle global mode, H = Toggle hint detail, M = Toggle hint toasts mute, Y = Copy current source label, O = Copy current origin label, Shift+Y = Copy source/hint/toasts snapshot, Shift+O = Copy origin/source/filter context, Ctrl+Y = Copy source/filter/origin context, Shift+H = Reset hint compact, Shift+M = Unmute hint toasts, Shift+P = Reload copy policy from shell attributes, Shift+K = Copy policy readout text, Reset hint button = Compact, ? = Toggle this help. Ctx note: Origin Context = Origin + Source + Filter.<div class="js-audit-policy-readout" style="margin-top:4px;padding-top:4px;border-top:1px dashed #cbd5e1;color:#334155;font-weight:600;">Policy: Toast 550ms | Success 650ms | Failure 700ms | Recovery 2400ms</div></div>' +
      '<div class="js-audit-key-status" style="display:none;font-size:10px;color:#64748b;margin:0 0 6px 0;">Last key action: none</div>' +
      '<div class="js-audit-shortcut-state" style="display:block;font-size:10px;color:#475569;margin:0 0 4px 0;"><span class="js-audit-shortcut-state-prefix">Shortcut state: Hint Compact | Toasts On | Source </span><button type="button" class="js-audit-shortcut-state-glyph" title="Session source marker (click to copy)" aria-label="Copy source label" style="display:inline-flex;align-items:center;justify-content:center;min-width:16px;height:14px;padding:0 3px;margin-right:4px;border-radius:999px;border:1px solid #cbd5e1;background:#f8fafc;color:#475569;font-size:9px;font-weight:700;line-height:1;cursor:pointer;">[S]</button><span class="js-audit-shortcut-copy-badge" style="display:none;align-items:center;margin-right:4px;padding:0 5px;height:14px;border-radius:999px;background:#dcfce7;color:#166534;font-size:9px;font-weight:700;line-height:1;">Copied</span><span class="js-audit-shortcut-state-source" title="Session: restored from saved row/session preferences." style="display:inline-block;transition:transform .18s ease, background-color .25s ease, color .25s ease;transform:scale(1);border-radius:6px;padding:0 2px;">Session</span><span class="js-audit-source-freshness" style="display:none;color:#64748b;opacity:1;transition:opacity .8s ease;margin-left:2px;">| just now</span></div>' +
      '<div class="js-audit-source-legend" style="display:block;font-size:10px;color:#64748b;margin:0 0 4px 0;">Legend: <span style="display:inline-flex;align-items:center;gap:3px;"><span style="width:7px;height:7px;border-radius:999px;background:#0f766e;display:inline-block;"></span>Keyboard</span> <span style="display:inline-flex;align-items:center;gap:3px;margin-left:8px;"><span style="width:7px;height:7px;border-radius:999px;background:#1d4ed8;display:inline-block;"></span>Button</span> <span style="display:inline-flex;align-items:center;gap:3px;margin-left:8px;"><span style="width:7px;height:7px;border-radius:999px;background:#7c3aed;display:inline-block;"></span>Session</span></div>' +
      '<div class="js-audit-source-meaning" style="display:block;font-size:10px;color:#475569;margin:0 0 6px 0;padding:1px 6px;border-radius:6px;transition:background-color .3s ease,color .3s ease,font-weight .25s ease;">Current source meaning: Session restored your saved row/session preference.</div>' +
      '<div class="js-audit-hint-live" aria-live="polite" aria-atomic="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">Hint mode: Compact</div>' +
      '<ul class="task-audit-history-list" style="margin:0;padding-left:18px;">' + items + '</ul>' +
      '<div class="task-audit-history-empty" style="display:none;font-size:11px;color:#64748b;margin-top:6px;">No status-change events in this window.</div>' +
    '</div>';
  }

  function wireAuditHistoryFilters(scope) {
    function getVisibleTaskRows() {
      return Array.from(document.querySelectorAll('tr[data-task-id]:not(.task-audit-history-row)'));
    }

    function countRowOverrides() {
      let overrideCount = 0;
      getVisibleTaskRows().forEach(function (taskRow) {
        const listedTaskId = String(taskRow.getAttribute('data-task-id') || '').trim();
        if (!listedTaskId) {
          return;
        }
        const stored = getStoredAuditFilter(listedTaskId);
        if (stored === 'status_changes' || stored === 'all') {
          overrideCount++;
        }
      });
      return overrideCount;
    }

    function refreshOverrideSummary(targetShell) {
      if (!targetShell) {
        return;
      }
      const summaryNode = targetShell.querySelector('.js-audit-history-summary');
      if (!summaryNode) {
        return;
      }
      const overrideCount = countRowOverrides();
      const totalRows = getVisibleTaskRows().length;
      summaryNode.textContent = 'Rows overridden: ' + overrideCount + ' / ' + totalRows;
    }

    function refreshAllOverrideSummaries() {
      document.querySelectorAll('.task-audit-history-shell').forEach(function (targetShell) {
        refreshOverrideSummary(targetShell);
      });
    }

    function refreshGlobalModeBadge(targetShell) {
      if (!targetShell) {
        return;
      }
      const badgeNode = targetShell.querySelector('.js-audit-global-badge');
      if (!badgeNode) {
        return;
      }
      const globalState = getGlobalAuditFilterState();
      badgeNode.textContent = globalState.enabled ? 'Global mode: On' : 'Global mode: Off';
      badgeNode.style.background = globalState.enabled ? '#dcfce7' : '#e5e7eb';
      badgeNode.style.color = globalState.enabled ? '#166534' : '#1f2937';
    }

    function refreshAllGlobalModeBadges() {
      document.querySelectorAll('.task-audit-history-shell').forEach(function (targetShell) {
        refreshGlobalModeBadge(targetShell);
      });
    }

    const root = scope || document;
    root.querySelectorAll('.task-audit-history-shell').forEach(function (shell) {
      if (shell.getAttribute('data-filters-bound') === '1') {
        return;
      }

      const taskId = getTaskIdForHistoryShell(shell);
      const chips = shell.querySelectorAll('.js-audit-history-chip');
      const applyVisibleButton = shell.querySelector('.js-audit-history-apply-visible');
      const clearVisibleButton = shell.querySelector('.js-audit-history-clear-visible');
      const resetButton = shell.querySelector('.js-audit-history-reset');
      const rememberGlobalCheckbox = shell.querySelector('.js-audit-history-remember-global');
      const sourceIndicator = shell.querySelector('.js-audit-history-source');
      const originChip = shell.querySelector('.js-audit-origin-chip');
      const cheatLine = shell.querySelector('.js-audit-cheatline');
      const shortcutHint = shell.querySelector('.js-audit-shortcut-hint');
      const shortcutHintModeBadge = shell.querySelector('.js-audit-hint-mode-badge');
      const shortcutHintResetButton = shell.querySelector('.js-audit-hint-reset');
      const shortcutHintToastToggle = shell.querySelector('.js-audit-hint-toast-toggle');
      const shortcutHintToastMutedChip = shell.querySelector('.js-audit-hint-toast-muted-chip');
      const shortcutHelp = shell.querySelector('.js-audit-shortcut-help');
      const shortcutPolicyReadout = shell.querySelector('.js-audit-policy-readout');
      const keyStatus = shell.querySelector('.js-audit-key-status');
      const shortcutStateSummary = shell.querySelector('.js-audit-shortcut-state');
      const shortcutStatePrefix = shell.querySelector('.js-audit-shortcut-state-prefix');
      const shortcutStateGlyph = shell.querySelector('.js-audit-shortcut-state-glyph');
      const shortcutCopyBadge = shell.querySelector('.js-audit-shortcut-copy-badge');
      const shortcutStateSource = shell.querySelector('.js-audit-shortcut-state-source');
      const shortcutStateFreshness = shell.querySelector('.js-audit-source-freshness');
      const sourceMeaningNode = shell.querySelector('.js-audit-source-meaning');
      const hintLiveRegion = shell.querySelector('.js-audit-hint-live');
      const rows = shell.querySelectorAll('.task-audit-history-list li');
      const emptyNote = shell.querySelector('.task-audit-history-empty');
      const shortcutHintCompactText = 'Shortcuts: A S R C G H M Y O Shift+Y Shift+O Ctrl+Y Shift+M Shift+P Shift+K ? Esc';
      const shortcutHintDetailedText = 'Shortcuts: A = All Events, S = Status Changes, R = Reset view, C = Clear overrides, G = Global mode, H = Hint detail, M = Toast mute, Y = Copy source, O = Copy origin, Shift+Y = Copy snapshot, Shift+O = Copy origin context, Ctrl+Y = Copy source+filter, Shift+H = Compact, Shift+M = Unmute, Shift+P = Reload policy, Shift+K = Copy policy';
      const hintLiveDebounceMs = 120;
      let hintLiveTimer = null;
      let isShortcutHintDetailed = false;
      let lastSettingSource = 'Session';
      let lastSettingFreshness = '';
      let lastSettingFreshnessFading = false;
      let lastSettingFreshnessFadeTimer = null;
      let lastSettingFreshnessClearTimer = null;
      let sourcePulseResetTimer = null;
      let sourceMeaningPulseResetTimer = null;
      let shortcutCopyBadgeTimer = null;
      let originChipPulseResetTimer = null;
      let originChipTextResetTimer = null;
      // Keep copy-feedback timing in one place so UX tuning does not require hunting through handlers.
      const copyAnnouncementPolicyDefaults = {
        originToastCooldownMs: 550,
        originSuccessLiveCooldownMs: 650,
        originFailureLiveCooldownMs: 700,
        originRecoveryWindowMs: 2400
      };
      function readPolicyMs(attributeName, fallback, label) {
        const rawText = String(shell.getAttribute(attributeName) || '').trim();
        if (rawText === '') {
          return {
            value: fallback,
            usedFallback: false,
            label: label
          };
        }
        if (/^\d+$/.test(rawText)) {
          return {
            value: Number.parseInt(rawText, 10),
            usedFallback: false,
            label: label
          };
        }
        return {
          value: fallback,
          usedFallback: true,
          label: label
        };
      }
      let copyAnnouncementPolicy = {
        originToastCooldownMs: copyAnnouncementPolicyDefaults.originToastCooldownMs,
        originSuccessLiveCooldownMs: copyAnnouncementPolicyDefaults.originSuccessLiveCooldownMs,
        originFailureLiveCooldownMs: copyAnnouncementPolicyDefaults.originFailureLiveCooldownMs,
        originRecoveryWindowMs: copyAnnouncementPolicyDefaults.originRecoveryWindowMs
      };
      let lastPolicyFallbackFields = [];
      function policyReadoutText(policy, fallbackFields) {
        let text = 'Policy: Toast ' + policy.originToastCooldownMs + 'ms | Success ' + policy.originSuccessLiveCooldownMs + 'ms | Failure ' + policy.originFailureLiveCooldownMs + 'ms | Recovery ' + policy.originRecoveryWindowMs + 'ms';
        if (Array.isArray(fallbackFields) && fallbackFields.length > 0) {
          text += ' | Fallback: ' + fallbackFields.join(', ');
        }
        return text;
      }
      function refreshPolicyReadout(policy, fallbackFields) {
        if (!shortcutPolicyReadout) {
          return;
        }
        shortcutPolicyReadout.textContent = policyReadoutText(policy, fallbackFields);
      }
      function loadCopyAnnouncementPolicy() {
        const toastPolicy = readPolicyMs('data-origin-toast-cooldown-ms', copyAnnouncementPolicyDefaults.originToastCooldownMs, 'Toast');
        const successPolicy = readPolicyMs('data-origin-success-live-cooldown-ms', copyAnnouncementPolicyDefaults.originSuccessLiveCooldownMs, 'Success');
        const failurePolicy = readPolicyMs('data-origin-failure-live-cooldown-ms', copyAnnouncementPolicyDefaults.originFailureLiveCooldownMs, 'Failure');
        const recoveryPolicy = readPolicyMs('data-origin-recovery-window-ms', copyAnnouncementPolicyDefaults.originRecoveryWindowMs, 'Recovery');
        copyAnnouncementPolicy = {
          originToastCooldownMs: toastPolicy.value,
          originSuccessLiveCooldownMs: successPolicy.value,
          originFailureLiveCooldownMs: failurePolicy.value,
          originRecoveryWindowMs: recoveryPolicy.value
        };
        lastPolicyFallbackFields = [toastPolicy, successPolicy, failurePolicy, recoveryPolicy]
          .filter(function (entry) { return entry.usedFallback; })
          .map(function (entry) { return entry.label; });
        refreshPolicyReadout(copyAnnouncementPolicy, lastPolicyFallbackFields);
        return copyAnnouncementPolicy;
      }
      loadCopyAnnouncementPolicy();
      let lastOriginToastText = '';
      let lastOriginToastAt = 0;
      let lastOriginSuccessLiveText = '';
      let lastOriginSuccessLiveAt = 0;
      let lastOriginFailureLiveText = '';
      let lastOriginFailureLiveAt = 0;
      let lastOriginCopyFailureAt = 0;

      function sourcePulseAccent(sourceLabel) {
        if (sourceLabel === 'Keyboard') {
          return {
            color: '#0f766e',
            backgroundColor: '#ecfeff'
          };
        }
        if (sourceLabel === 'Button') {
          return {
            color: '#1d4ed8',
            backgroundColor: '#eff6ff'
          };
        }
        return {
          color: '#7c3aed',
          backgroundColor: '#f5f3ff'
        };
      }

      function sourceTooltipText(sourceLabel) {
        if (sourceLabel === 'Keyboard') {
          return 'Keyboard: changed with panel shortcuts (A/S/R/C/G/H/M/Y/O/Shift+Y/Shift+O/Ctrl+Y/Shift+M/Shift+P/Shift+K/?/Esc).';
        }
        if (sourceLabel === 'Button') {
          return 'Button: changed with an on-panel control click.';
        }
        return 'Session: restored from saved row/session preferences.';
      }

      function sourceGlyphText(sourceLabel) {
        if (sourceLabel === 'Keyboard') {
          return '[K]';
        }
        if (sourceLabel === 'Button') {
          return '[B]';
        }
        return '[S]';
      }

      function sourceMeaningText(sourceLabel) {
        if (sourceLabel === 'Keyboard') {
          return 'Current source meaning: Keyboard shortcut changed this state in-panel.';
        }
        if (sourceLabel === 'Button') {
          return 'Current source meaning: Button control click changed this state in-panel.';
        }
        return 'Current source meaning: Session restored your saved row/session preference.';
      }

      function copyTextToClipboard(text) {
        const safeText = String(text || '');
        if (!safeText) {
          return Promise.resolve(false);
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          return navigator.clipboard.writeText(safeText)
            .then(function () { return true; })
            .catch(function () {
              const tempInput = document.createElement('textarea');
              tempInput.value = safeText;
              tempInput.setAttribute('readonly', 'readonly');
              tempInput.style.position = 'fixed';
              tempInput.style.left = '-9999px';
              document.body.appendChild(tempInput);
              tempInput.focus();
              tempInput.select();
              let copied = false;
              try {
                copied = document.execCommand('copy');
              } catch (copyError) {
                copied = false;
              }
              document.body.removeChild(tempInput);
              return copied;
            });
        }

        const fallbackInput = document.createElement('textarea');
        fallbackInput.value = safeText;
        fallbackInput.setAttribute('readonly', 'readonly');
        fallbackInput.style.position = 'fixed';
        fallbackInput.style.left = '-9999px';
        document.body.appendChild(fallbackInput);
        fallbackInput.focus();
        fallbackInput.select();
        let fallbackCopied = false;
        try {
          fallbackCopied = document.execCommand('copy');
        } catch (copyError) {
          fallbackCopied = false;
        }
        document.body.removeChild(fallbackInput);
        return Promise.resolve(fallbackCopied);
      }

      function flashShortcutCopyBadge(mode) {
        if (!shortcutCopyBadge) {
          return;
        }
        const badgeMode = String(mode || 'copied').toLowerCase();
        const badgeHostWidth = shell && typeof shell.clientWidth === 'number' ? shell.clientWidth : 0;
        if (shortcutCopyBadgeTimer) {
          window.clearTimeout(shortcutCopyBadgeTimer);
        }
        if (badgeMode === 'snapshot') {
          shortcutCopyBadge.textContent = 'Snapshot';
          shortcutCopyBadge.style.background = '#dbeafe';
          shortcutCopyBadge.style.color = '#1e3a8a';
        } else if (badgeMode === 'origin-context') {
          shortcutCopyBadge.textContent = badgeHostWidth >= 560 ? 'Origin Context' : 'OriginCtx';
          shortcutCopyBadge.style.background = '#ede9fe';
          shortcutCopyBadge.style.color = '#5b21b6';
        } else if (badgeMode === 'origin') {
          shortcutCopyBadge.textContent = 'Origin';
          shortcutCopyBadge.style.background = '#e0e7ff';
          shortcutCopyBadge.style.color = '#3730a3';
        } else if (badgeMode === 'filter') {
          shortcutCopyBadge.textContent = 'Filter';
          shortcutCopyBadge.style.background = '#fef3c7';
          shortcutCopyBadge.style.color = '#92400e';
        } else if (badgeMode === 'policy') {
          shortcutCopyBadge.textContent = 'Policy';
          shortcutCopyBadge.style.background = '#e0f2fe';
          shortcutCopyBadge.style.color = '#075985';
        } else {
          shortcutCopyBadge.textContent = 'Copied';
          shortcutCopyBadge.style.background = '#dcfce7';
          shortcutCopyBadge.style.color = '#166534';
        }
        shortcutCopyBadge.style.display = 'inline-flex';
        shortcutCopyBadgeTimer = window.setTimeout(function () {
          shortcutCopyBadge.style.display = 'none';
          shortcutCopyBadgeTimer = null;
        }, 1100);
      }

      function buildRichSourceSnapshot() {
        const sourceLabel = String(lastSettingSource || 'Session').trim() || 'Session';
        const hintLabel = isShortcutHintDetailed ? 'Detailed' : 'Compact';
        const toastLabel = isHintToastMuted(taskId) ? 'Muted' : 'On';
        return 'Source: ' + sourceLabel + ' | Hint: ' + hintLabel + ' | Toasts: ' + toastLabel;
      }

      function buildSourceFilterSnapshot() {
        const sourceLabel = String(lastSettingSource || 'Session').trim() || 'Session';
        const activeFilter = currentActiveFilter();
        const filterLabel = activeFilter === 'status_changes' ? 'Status Changes' : 'All Events';
        let originLabel = 'Default';
        if (sourceIndicator) {
          const sourceText = String(sourceIndicator.textContent || '');
          const match = sourceText.match(/Source:\s*(Row|Global|Default)/i);
          if (match && match[1]) {
            const candidate = String(match[1]).toLowerCase();
            if (candidate === 'row') {
              originLabel = 'Row';
            } else if (candidate === 'global') {
              originLabel = 'Global';
            } else {
              originLabel = 'Default';
            }
          }
        }
        return 'Source: ' + sourceLabel + ' | Filter: ' + filterLabel + ' | Origin: ' + originLabel;
      }

      function sourceFilterPreviewToastText(snapshotText) {
        const text = String(snapshotText || '').trim();
        if (!text) {
          return 'Source/filter copied.';
        }
        const maxLen = 72;
        const safePreview = text.length > maxLen ? (text.slice(0, maxLen - 3) + '...') : text;
        return 'Source/filter copied: ' + safePreview + ' (' + text.length + ' chars)';
      }

      function currentOriginLabel() {
        if (originChip) {
          const chipText = String(originChip.textContent || '');
          const chipMatch = chipText.match(/Origin:\s*(Row|Global|Default)/i);
          if (chipMatch && chipMatch[1]) {
            const candidate = String(chipMatch[1]).toLowerCase();
            if (candidate === 'row') {
              return 'Row';
            }
            if (candidate === 'global') {
              return 'Global';
            }
            return 'Default';
          }
        }

        if (sourceIndicator) {
          const sourceText = String(sourceIndicator.textContent || '');
          const sourceMatch = sourceText.match(/Source:\s*(Row|Global|Default)/i);
          if (sourceMatch && sourceMatch[1]) {
            const candidate = String(sourceMatch[1]).toLowerCase();
            if (candidate === 'row') {
              return 'Row';
            }
            if (candidate === 'global') {
              return 'Global';
            }
          }
        }

        return 'Default';
      }

      function snapshotPreviewToastText(snapshotText) {
        const text = String(snapshotText || '').trim();
        if (!text) {
          return 'Snapshot copied.';
        }
        const maxLen = 72;
        const safePreview = text.length > maxLen ? (text.slice(0, maxLen - 3) + '...') : text;
        return 'Snapshot copied: ' + safePreview + ' (' + text.length + ' chars)';
      }

      function copyCurrentSourceLabel(statusPrefix, explicitPayload, successToastText, badgeMode) {
        const sourceLabel = String(lastSettingSource || '').trim();
        const prefix = String(statusPrefix || 'Glyph copy');
        const payload = String(explicitPayload || sourceLabel).trim();
        const badgeVariant = String(badgeMode || 'copied').toLowerCase();
        if (!sourceLabel) {
          setKeyStatus(prefix + ' -> unavailable');
          showToast('Source copy unavailable.', false);
          return;
        }
        copyTextToClipboard(payload).then(function (copied) {
          if (copied) {
            setKeyStatus(prefix + ' -> ' + sourceLabel);
            showToast(successToastText || ('Source copied: ' + sourceLabel + '.'), false);
            flashShortcutCopyBadge(badgeVariant);
          } else {
            setKeyStatus(prefix + ' failed');
            showToast('Could not copy source label.', true);
          }
        });
      }

      function copyCurrentOriginLabel(statusPrefix) {
        const prefix = String(statusPrefix || 'Origin copy');
        const originLabel = currentOriginLabel();
        copyTextToClipboard(originLabel).then(function (copied) {
          if (copied) {
            const recovered = lastOriginCopyFailureAt > 0 && (Date.now() - lastOriginCopyFailureAt) <= copyAnnouncementPolicy.originRecoveryWindowMs;
            setKeyStatus(prefix + ' -> ' + originLabel);
            showOriginCopyToast('Origin copied: ' + originLabel + '.');
            flashShortcutCopyBadge('origin');
            pulseOriginChip('Copied');
            announceOriginCopy(originLabel, prefix, recovered);
            lastOriginCopyFailureAt = 0;
          } else {
            setKeyStatus(prefix + ' failed');
            showToast('Could not copy origin label.', true);
            lastOriginCopyFailureAt = Date.now();
            announceOriginCopyFailure(prefix);
          }
        });
      }

      function buildOriginContextSnapshot() {
        const originLabel = currentOriginLabel();
        const sourceLabel = String(lastSettingSource || 'Session').trim() || 'Session';
        const activeFilter = currentActiveFilter();
        const filterLabel = activeFilter === 'status_changes' ? 'Status Changes' : 'All Events';
        return 'Origin: ' + originLabel + ' | Source: ' + sourceLabel + ' | Filter: ' + filterLabel;
      }

      function originContextPreviewToastText(snapshotText) {
        const text = String(snapshotText || '').trim();
        if (!text) {
          return 'Origin context copied.';
        }
        const maxLen = 72;
        const safePreview = text.length > maxLen ? (text.slice(0, maxLen - 3) + '...') : text;
        return 'Origin context copied: ' + safePreview + ' (' + text.length + ' chars)';
      }

      function pulseOriginChip(copyLabel) {
        if (!originChip) {
          return;
        }
        const confirmationLabel = String(copyLabel || 'Copied').trim() || 'Copied';
        const reduceMotion = typeof window.matchMedia === 'function'
          && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (originChipPulseResetTimer) {
          window.clearTimeout(originChipPulseResetTimer);
        }
        if (originChipTextResetTimer) {
          window.clearTimeout(originChipTextResetTimer);
        }
        originChip.style.transition = reduceMotion
          ? 'box-shadow .12s ease, background-color .12s ease'
          : 'transform .2s ease, box-shadow .2s ease, background-color .2s ease';
        originChip.style.transform = reduceMotion ? 'scale(1)' : 'scale(1.06)';
        if (reduceMotion) {
          originChip.textContent = 'Origin: ' + confirmationLabel;
          originChip.style.backgroundColor = '#eef2ff';
        }
        originChip.style.boxShadow = '0 0 0 2px rgba(99, 102, 241, 0.2)';
        originChipPulseResetTimer = window.setTimeout(function () {
          originChip.style.transform = 'scale(1)';
          originChip.style.backgroundColor = '';
          originChip.style.boxShadow = 'none';
          originChipPulseResetTimer = null;
        }, reduceMotion ? 170 : 240);
        if (reduceMotion) {
          originChipTextResetTimer = window.setTimeout(function () {
            const fallbackOrigin = currentOriginLabel();
            const currentOrigin = String(originChip.getAttribute('data-origin-label') || fallbackOrigin);
            originChip.textContent = 'Origin: ' + currentOrigin;
            originChipTextResetTimer = null;
          }, 250);
        }
      }

      function copyOriginContextSnapshot(statusPrefix) {
        const prefix = String(statusPrefix || 'Origin context copy');
        const originContext = buildOriginContextSnapshot();
        const originLabel = currentOriginLabel();
        copyTextToClipboard(originContext).then(function (copied) {
          if (copied) {
            const recovered = lastOriginCopyFailureAt > 0 && (Date.now() - lastOriginCopyFailureAt) <= copyAnnouncementPolicy.originRecoveryWindowMs;
            setKeyStatus(prefix + ' -> ' + originLabel);
            showOriginCopyToast(originContextPreviewToastText(originContext));
            flashShortcutCopyBadge('origin-context');
            pulseOriginChip('Context Copied');
            announceOriginCopy(originLabel + ' context', prefix, recovered);
            lastOriginCopyFailureAt = 0;
          } else {
            setKeyStatus(prefix + ' failed');
            showToast('Could not copy origin context.', true);
            lastOriginCopyFailureAt = Date.now();
            announceOriginCopyFailure(prefix);
          }
        });
      }

      function copyPolicyReadout(statusPrefix) {
        const prefix = String(statusPrefix || 'Policy copy');
        const policyText = shortcutPolicyReadout
          ? String(shortcutPolicyReadout.textContent || '').trim()
          : policyReadoutText(copyAnnouncementPolicy, lastPolicyFallbackFields);
        if (!policyText) {
          setKeyStatus(prefix + ' -> unavailable');
          showToast('Policy copy unavailable.', false);
          return;
        }
        copyTextToClipboard(policyText).then(function (copied) {
          if (copied) {
            setKeyStatus(prefix + ' -> copied');
            showToast('Policy copied (' + policyText.length + ' chars).', false);
            flashShortcutCopyBadge('policy');
          } else {
            setKeyStatus(prefix + ' failed');
            showToast('Could not copy policy readout.', true);
          }
        });
      }

      function pulseShortcutSource(sourceLabel) {
        if (!shortcutStateSource) {
          return;
        }
        if (sourcePulseResetTimer) {
          window.clearTimeout(sourcePulseResetTimer);
        }
        const accent = sourcePulseAccent(sourceLabel);
        shortcutStateSource.style.transform = 'scale(1.08)';
        shortcutStateSource.style.color = accent.color;
        shortcutStateSource.style.backgroundColor = accent.backgroundColor;
        sourcePulseResetTimer = window.setTimeout(function () {
          shortcutStateSource.style.transform = 'scale(1)';
          shortcutStateSource.style.color = '';
          shortcutStateSource.style.backgroundColor = 'transparent';
          sourcePulseResetTimer = null;
        }, 260);
      }

      function pulseSourceMeaning(sourceLabel) {
        if (!sourceMeaningNode) {
          return;
        }
        if (sourceMeaningPulseResetTimer) {
          window.clearTimeout(sourceMeaningPulseResetTimer);
        }
        const accent = sourcePulseAccent(sourceLabel);
        sourceMeaningNode.style.backgroundColor = accent.backgroundColor;
        sourceMeaningNode.style.color = accent.color;
        sourceMeaningNode.style.fontWeight = '700';
        sourceMeaningPulseResetTimer = window.setTimeout(function () {
          sourceMeaningNode.style.backgroundColor = 'transparent';
          sourceMeaningNode.style.color = '#475569';
          sourceMeaningNode.style.fontWeight = '400';
          sourceMeaningPulseResetTimer = null;
        }, 460);
      }

      function setShortcutHelpVisible(visible) {
        if (!shortcutHelp) {
          return;
        }
        shortcutHelp.style.display = visible ? '' : 'none';
      }

      function showOriginCopyToast(messageText) {
        const text = String(messageText || '').trim();
        if (!text) {
          return;
        }
        const nowMs = Date.now();
        if (text === lastOriginToastText && (nowMs - lastOriginToastAt) < copyAnnouncementPolicy.originToastCooldownMs) {
          return;
        }
        lastOriginToastText = text;
        lastOriginToastAt = nowMs;
        showToast(text, false);
      }

      function announceHintMode(modeLabel, triggerLabel) {
        if (!hintLiveRegion) {
          return;
        }
        const message = 'Hint mode: ' + modeLabel + '. Trigger: ' + triggerLabel + '.';
        if (hintLiveTimer) {
          window.clearTimeout(hintLiveTimer);
        }
        hintLiveTimer = window.setTimeout(function () {
          hintLiveRegion.textContent = message;
          hintLiveTimer = null;
        }, hintLiveDebounceMs);
      }

      function announceHintToastMute(muted, triggerLabel) {
        if (!hintLiveRegion) {
          return;
        }
        const message = 'Hint toasts: ' + (muted ? 'Muted' : 'Unmuted') + '. Trigger: ' + triggerLabel + '.';
        if (hintLiveTimer) {
          window.clearTimeout(hintLiveTimer);
        }
        hintLiveTimer = window.setTimeout(function () {
          hintLiveRegion.textContent = message;
          hintLiveTimer = null;
        }, hintLiveDebounceMs);
      }

      function announceOriginCopy(copyLabel, triggerLabel, recovered) {
        if (!hintLiveRegion) {
          return;
        }
        const copyText = String(copyLabel || '').trim();
        const message = (recovered ? 'Origin copy recovered: ' : 'Origin copy: ') + copyText + '. Trigger: ' + String(triggerLabel || 'copy action') + '.';
        const nowMs = Date.now();
        if (message === lastOriginSuccessLiveText && (nowMs - lastOriginSuccessLiveAt) < copyAnnouncementPolicy.originSuccessLiveCooldownMs) {
          return;
        }
        lastOriginSuccessLiveText = message;
        lastOriginSuccessLiveAt = nowMs;
        if (hintLiveTimer) {
          window.clearTimeout(hintLiveTimer);
        }
        hintLiveTimer = window.setTimeout(function () {
          hintLiveRegion.textContent = message;
          hintLiveTimer = null;
        }, hintLiveDebounceMs);
      }

      function announceOriginCopyFailure(triggerLabel) {
        if (!hintLiveRegion) {
          return;
        }
        const message = 'Origin copy failed. Trigger: ' + String(triggerLabel || 'copy action') + '. Try again or use Origin chip.';
        const nowMs = Date.now();
        if (message === lastOriginFailureLiveText && (nowMs - lastOriginFailureLiveAt) < copyAnnouncementPolicy.originFailureLiveCooldownMs) {
          return;
        }
        lastOriginFailureLiveText = message;
        lastOriginFailureLiveAt = nowMs;
        if (hintLiveTimer) {
          window.clearTimeout(hintLiveTimer);
        }
        hintLiveTimer = window.setTimeout(function () {
          hintLiveRegion.textContent = message;
          hintLiveTimer = null;
        }, hintLiveDebounceMs);
      }

      function getHintActionMessages(nextDetailed, triggerKey) {
        const modeLower = nextDetailed ? 'detailed' : 'compact';
        const modeTitle = nextDetailed ? 'Detailed' : 'Compact';

        if (triggerKey === 'keyboard H') {
          return {
            statusText: 'H -> Hint ' + modeLower,
            toastText: 'Shortcut hint: ' + modeLower + '.',
            liveModeLabel: modeTitle,
            liveTriggerLabel: 'keyboard H'
          };
        }

        if (triggerKey === 'keyboard Shift+H') {
          return {
            statusText: 'Shift+H -> Hint compact',
            toastText: 'Shortcut hint reset to compact.',
            liveModeLabel: 'Compact',
            liveTriggerLabel: 'keyboard Shift+H'
          };
        }

        if (triggerKey === 'reset button') {
          return {
            statusText: 'Reset hint button -> Hint compact',
            toastText: 'Shortcut hint reset to compact.',
            liveModeLabel: 'Compact',
            liveTriggerLabel: 'reset button'
          };
        }

        return {
          statusText: 'Hint -> ' + modeLower,
          toastText: '',
          liveModeLabel: modeTitle,
          liveTriggerLabel: 'mode change'
        };
      }

      function setShortcutHintDetailed(detailed) {
        isShortcutHintDetailed = Boolean(detailed);
        if (!shortcutHint) {
          return;
        }
        shortcutHint.textContent = isShortcutHintDetailed ? shortcutHintDetailedText : shortcutHintCompactText;
        if (shortcutHintModeBadge) {
          shortcutHint.appendChild(document.createTextNode(' '));
          shortcutHint.appendChild(shortcutHintModeBadge);
          shortcutHintModeBadge.textContent = isShortcutHintDetailed ? 'Hint: Detailed' : 'Hint: Compact';
          shortcutHintModeBadge.style.borderColor = isShortcutHintDetailed ? '#0f766e' : '#cbd5e1';
          shortcutHintModeBadge.style.background = isShortcutHintDetailed ? '#ecfeff' : '#f8fafc';
          shortcutHintModeBadge.style.color = isShortcutHintDetailed ? '#0f766e' : '#334155';
        }
        if (shortcutHintResetButton) {
          shortcutHint.appendChild(document.createTextNode(' '));
          shortcutHint.appendChild(shortcutHintResetButton);
          shortcutHintResetButton.disabled = !isShortcutHintDetailed;
          shortcutHintResetButton.style.opacity = isShortcutHintDetailed ? '1' : '0.55';
          shortcutHintResetButton.style.cursor = isShortcutHintDetailed ? 'pointer' : 'default';
        }
        if (shortcutHintToastToggle) {
          shortcutHint.appendChild(document.createTextNode(' '));
          shortcutHint.appendChild(shortcutHintToastToggle);
        }
        if (shortcutHintToastMutedChip) {
          shortcutHint.appendChild(document.createTextNode(' '));
          shortcutHint.appendChild(shortcutHintToastMutedChip);
        }
        refreshShortcutStateSummary();
      }

      function refreshShortcutStateSummary() {
        if (!shortcutStateSummary) {
          return;
        }
        const hintLabel = isShortcutHintDetailed ? 'Detailed' : 'Compact';
        const toastMuted = isHintToastMuted(taskId);
        const summaryPrefixText = 'Shortcut state: Hint ' + hintLabel + ' | Toasts ' + (toastMuted ? 'Muted' : 'On') + ' | Source ';
        if (shortcutStatePrefix) {
          shortcutStatePrefix.textContent = summaryPrefixText;
        }
        if (shortcutStateSource) {
          shortcutStateSource.textContent = lastSettingSource;
          shortcutStateSource.setAttribute('title', sourceTooltipText(lastSettingSource));
        }
        if (shortcutStateGlyph) {
          const accent = sourcePulseAccent(lastSettingSource);
          shortcutStateGlyph.textContent = sourceGlyphText(lastSettingSource);
          shortcutStateGlyph.setAttribute('title', lastSettingSource + ' source marker (click to copy)');
          shortcutStateGlyph.setAttribute('aria-label', 'Copy source label (' + lastSettingSource + ')');
          shortcutStateGlyph.style.color = accent.color;
          shortcutStateGlyph.style.borderColor = accent.color;
          shortcutStateGlyph.style.backgroundColor = accent.backgroundColor;
        }
        if (sourceMeaningNode) {
          sourceMeaningNode.textContent = sourceMeaningText(lastSettingSource);
        }
        if (!shortcutStatePrefix || !shortcutStateSource) {
          const fallbackSummaryText = summaryPrefixText + lastSettingSource;
          shortcutStateSummary.textContent = fallbackSummaryText;
          if (shortcutStateFreshness && lastSettingFreshness) {
            shortcutStateSummary.textContent = fallbackSummaryText + ' | ' + lastSettingFreshness;
          }
        } else {
          if (shortcutStateFreshness && !lastSettingFreshness) {
            shortcutStateFreshness.style.display = 'none';
            shortcutStateFreshness.style.opacity = '1';
          }
        }
        if (shortcutStateFreshness) {
          if (lastSettingFreshness) {
            shortcutStateFreshness.style.display = 'inline-block';
            shortcutStateFreshness.textContent = '| ' + lastSettingFreshness;
            shortcutStateFreshness.style.opacity = lastSettingFreshnessFading ? '0' : '1';
          } else {
            shortcutStateFreshness.style.display = 'none';
            shortcutStateFreshness.style.opacity = '1';
          }
        }
        shortcutStateSummary.style.color = toastMuted ? '#7c2d12' : '#475569';
      }

      function markSettingFreshness() {
        lastSettingFreshness = 'just now';
        lastSettingFreshnessFading = false;
        refreshShortcutStateSummary();
        if (lastSettingFreshnessFadeTimer) {
          window.clearTimeout(lastSettingFreshnessFadeTimer);
        }
        if (lastSettingFreshnessClearTimer) {
          window.clearTimeout(lastSettingFreshnessClearTimer);
        }
        lastSettingFreshnessFadeTimer = window.setTimeout(function () {
          if (!lastSettingFreshness) {
            return;
          }
          lastSettingFreshnessFading = true;
          refreshShortcutStateSummary();
        }, 2800);
        lastSettingFreshnessClearTimer = window.setTimeout(function () {
          lastSettingFreshness = '';
          lastSettingFreshnessFading = false;
          refreshShortcutStateSummary();
          lastSettingFreshnessFadeTimer = null;
          lastSettingFreshnessClearTimer = null;
        }, 4200);
      }

      function setLastSettingSource(sourceLabel) {
        if (!sourceLabel) {
          return;
        }
        const previousSource = lastSettingSource;
        lastSettingSource = sourceLabel;
        refreshShortcutStateSummary();
        if (previousSource !== sourceLabel) {
          pulseShortcutSource(sourceLabel);
          pulseSourceMeaning(sourceLabel);
        }
      }

      function sourceLabelFromTrigger(triggerLabel) {
        const label = String(triggerLabel || '').toLowerCase();
        if (!label) {
          return 'Session';
        }
        if (label.indexOf('keyboard') !== -1) {
          return 'Keyboard';
        }
        if (label.indexOf('button') !== -1) {
          return 'Button';
        }
        return 'Session';
      }

      function refreshHintToastToggle() {
        if (!shortcutHintToastToggle) {
          return;
        }
        const muted = isHintToastMuted(taskId);
        shortcutHintToastToggle.textContent = muted ? 'Unmute hint toasts' : 'Mute hint toasts';
        shortcutHintToastToggle.setAttribute('aria-pressed', muted ? 'true' : 'false');
        shortcutHintToastToggle.style.color = muted ? '#334155' : '#64748b';
        if (shortcutHintToastMutedChip) {
          shortcutHintToastMutedChip.style.display = muted ? 'inline-flex' : 'none';
        }
        refreshShortcutStateSummary();
      }

      function applyHintModeTransition(nextDetailed, options) {
        const targetDetailed = Boolean(nextDetailed);
        const opts = options || {};
        if (targetDetailed === isShortcutHintDetailed) {
          if (opts.noChangeStatusText) {
            setKeyStatus(opts.noChangeStatusText);
          }
          return false;
        }

        const messages = getHintActionMessages(targetDetailed, opts.triggerKey || '');

        setShortcutHintDetailed(targetDetailed);
        setStoredAuditHintMode(taskId, targetDetailed ? 'detailed' : 'compact');
        setLastSettingSource(sourceLabelFromTrigger(messages.liveTriggerLabel));
        markSettingFreshness();
        announceHintMode(messages.liveModeLabel, messages.liveTriggerLabel);

        if (messages.statusText) {
          setKeyStatus(messages.statusText);
        }
        if (messages.toastText && !isHintToastMuted(taskId)) {
          showToast(messages.toastText, false);
        }

        return true;
      }

      function resetHintToCompact(triggerKey) {
        return applyHintModeTransition(false, {
          triggerKey: triggerKey || 'keyboard Shift+H'
        });
      }

      function setKeyStatus(text) {
        if (!keyStatus) {
          return;
        }
        keyStatus.textContent = 'Last key action: ' + text;
        keyStatus.style.display = '';
      }

      const storedHintMode = getStoredAuditHintMode(taskId);
      setShortcutHintDetailed(storedHintMode === 'detailed');
      refreshHintToastToggle();

      if (shortcutHintResetButton) {
        shortcutHintResetButton.addEventListener('click', function (event) {
          event.preventDefault();
          resetHintToCompact('reset button');
        });
      }

      if (shortcutHintToastToggle) {
        shortcutHintToastToggle.addEventListener('click', function (event) {
          event.preventDefault();
          applyHintToastMuteToggle('Hint toasts');
        });
      }

      if (shortcutStateGlyph) {
        shortcutStateGlyph.addEventListener('click', function (event) {
          event.preventDefault();
          copyCurrentSourceLabel('Glyph copy');
        });
      }

      if (originChip) {
        originChip.addEventListener('click', function (event) {
          event.preventDefault();
          copyCurrentOriginLabel('Origin chip copy');
        });
      }

      function applyHintToastMuteToggle(statusPrefix) {
        const triggerLabel = statusPrefix === 'M -> Hint toasts' ? 'keyboard M' : 'toggle button';
        const muted = !isHintToastMuted(taskId);
        setHintToastMuted(taskId, muted);
        refreshHintToastToggle();
        setLastSettingSource(sourceLabelFromTrigger(triggerLabel));
        markSettingFreshness();
        announceHintToastMute(muted, triggerLabel);
        setKeyStatus(statusPrefix + ' ' + (muted ? 'muted' : 'unmuted'));
        showToast('Hint toasts ' + (muted ? 'muted.' : 'unmuted.'), false);
      }

      function applyHintToastUnmute(statusText, triggerLabel) {
        if (!isHintToastMuted(taskId)) {
          setKeyStatus(statusText + ' (no change)');
          return false;
        }
        setHintToastMuted(taskId, false);
        refreshHintToastToggle();
        setLastSettingSource(sourceLabelFromTrigger(triggerLabel));
        markSettingFreshness();
        announceHintToastMute(false, triggerLabel || 'keyboard Shift+M');
        setKeyStatus(statusText);
        showToast('Hint toasts unmuted.', false);
        return true;
      }

      function applySourceIndicator(source, filter) {
        const safeSource = (source === 'row' || source === 'global') ? source : 'default';
        const sourceLabel = safeSource === 'row' ? 'Row' : (safeSource === 'global' ? 'Global' : 'Default');
        const modeLabel = filter === 'status_changes' ? 'Status Changes' : 'All Events';

        if (sourceIndicator) {
          sourceIndicator.textContent = 'Source: ' + sourceLabel + ' (' + modeLabel + ')';
        }

        if (!originChip) {
          return;
        }

        originChip.textContent = 'Origin: ' + sourceLabel;
        originChip.setAttribute('data-origin-label', sourceLabel);
        originChip.setAttribute('title', 'Copy origin label (' + sourceLabel + ')');
        originChip.setAttribute('aria-label', 'Copy origin label (' + sourceLabel + ')');
        if (safeSource === 'row') {
          originChip.style.background = '#ecfeff';
          originChip.style.borderColor = '#99f6e4';
          originChip.style.color = '#0f766e';
        } else if (safeSource === 'global') {
          originChip.style.background = '#eff6ff';
          originChip.style.borderColor = '#bfdbfe';
          originChip.style.color = '#1d4ed8';
        } else {
          originChip.style.background = '#f8fafc';
          originChip.style.borderColor = '#cbd5e1';
          originChip.style.color = '#475569';
        }
      }

      function setChipStyles(activeChip) {
        chips.forEach(function (chip) {
          const isActive = chip === activeChip;
          chip.classList.toggle('is-active', isActive);
          chip.style.border = isActive ? '1px solid #0f766e' : '1px solid #cbd5e1';
          chip.style.background = isActive ? '#ecfeff' : '#fff';
          chip.style.color = isActive ? '#0f766e' : '#475569';
        });
      }

      function applyFilter(filter) {
        let visibleCount = 0;
        rows.forEach(function (row) {
          const hasDiff = row.getAttribute('data-has-diff') === '1';
          const show = (filter === 'all') || (filter === 'status_changes' && hasDiff);
          row.style.display = show ? '' : 'none';
          if (show) {
            visibleCount++;
          }
        });

        if (emptyNote) {
          emptyNote.style.display = visibleCount === 0 ? '' : 'none';
        }
      }

      function activateFilter(filter, persist, source) {
        const selectedFilter = (filter === 'status_changes') ? 'status_changes' : 'all';
        const activeChip = shell.querySelector('.js-audit-history-chip[data-filter="' + selectedFilter + '"]')
          || shell.querySelector('.js-audit-history-chip[data-filter="all"]')
          || chips[0];
        if (activeChip) {
          setChipStyles(activeChip);
        }
        applyFilter(selectedFilter);
        if (persist) {
          setStoredAuditFilter(taskId, selectedFilter);
        }
        applySourceIndicator(source, selectedFilter);
      }

      function currentActiveFilter() {
        const activeChip = shell.querySelector('.js-audit-history-chip.is-active');
        if (!activeChip) {
          return 'all';
        }
        const value = String(activeChip.getAttribute('data-filter') || 'all');
        return value === 'status_changes' ? 'status_changes' : 'all';
      }

      function applyShortcutFilter(filter) {
        const selectedFilter = (filter === 'status_changes') ? 'status_changes' : 'all';
        activateFilter(selectedFilter, true, 'row');
        refreshAllOverrideSummaries();
        if (rememberGlobalCheckbox && rememberGlobalCheckbox.checked) {
          setGlobalAuditFilterState(true, selectedFilter);
          refreshAllGlobalModeBadges();
        }
      }

      function applyResetViewShortcut() {
        clearStoredAuditFilter(taskId);
        const globalState = getGlobalAuditFilterState();
        const fallbackFilter = globalState.enabled ? globalState.filter : 'all';
        activateFilter(fallbackFilter, false, globalState.enabled ? 'global' : 'default');
        refreshAllOverrideSummaries();
      }

      function applyClearVisibleShortcut() {
        const previousRows = [];
        let clearedCount = 0;

        getVisibleTaskRows().forEach(function (taskRow) {
          const visibleTaskId = String(taskRow.getAttribute('data-task-id') || '').trim();
          if (!visibleTaskId) {
            return;
          }

          const prior = getStoredAuditFilter(visibleTaskId);
          previousRows.push({ taskId: visibleTaskId, filter: prior });
          if (prior === 'status_changes' || prior === 'all') {
            clearedCount++;
          }
          clearStoredAuditFilter(visibleTaskId);
        });

        refreshAllOverrideSummaries();

        const globalState = getGlobalAuditFilterState();
        document.querySelectorAll('.task-audit-history-shell').forEach(function (targetShell) {
          const targetTaskId = getTaskIdForHistoryShell(targetShell);
          const restoredFilter = getStoredAuditFilter(targetTaskId);
          const nextFilter = (restoredFilter === 'status_changes' || restoredFilter === 'all')
            ? restoredFilter
            : (globalState.enabled ? globalState.filter : 'all');
          const nextSource = (restoredFilter === 'status_changes' || restoredFilter === 'all')
            ? 'row'
            : (globalState.enabled ? 'global' : 'default');
          refreshShellVisualState(targetShell, nextFilter, nextSource);
        });

        showToast('Cleared ' + clearedCount + ' row override' + (clearedCount === 1 ? '' : 's') + '.', false, {
          label: 'Undo',
          onClick: function () {
            previousRows.forEach(function (entry) {
              if (entry.filter === 'status_changes' || entry.filter === 'all') {
                setStoredAuditFilter(entry.taskId, entry.filter);
              } else {
                clearStoredAuditFilter(entry.taskId);
              }
            });

            const restoredGlobalState = getGlobalAuditFilterState();
            document.querySelectorAll('.task-audit-history-shell').forEach(function (targetShell) {
              const targetTaskId = getTaskIdForHistoryShell(targetShell);
              const restoredFilter = getStoredAuditFilter(targetTaskId);
              const nextFilter = (restoredFilter === 'status_changes' || restoredFilter === 'all')
                ? restoredFilter
                : (restoredGlobalState.enabled ? restoredGlobalState.filter : 'all');
              const nextSource = (restoredFilter === 'status_changes' || restoredFilter === 'all')
                ? 'row'
                : (restoredGlobalState.enabled ? 'global' : 'default');
              refreshShellVisualState(targetShell, nextFilter, nextSource);
            });

            refreshAllOverrideSummaries();

            showToast('Clear row overrides was undone.', false);
          }
        });
      }

      function refreshShellVisualState(targetShell, selectedFilter, source) {
        const targetChips = targetShell.querySelectorAll('.js-audit-history-chip');
        const targetRows = targetShell.querySelectorAll('.task-audit-history-list li');
        const targetEmpty = targetShell.querySelector('.task-audit-history-empty');
        const targetSource = targetShell.querySelector('.js-audit-history-source');
        const targetOriginChip = targetShell.querySelector('.js-audit-origin-chip');

        targetChips.forEach(function (chip) {
          const chipFilter = String(chip.getAttribute('data-filter') || 'all');
          const isActive = chipFilter === selectedFilter;
          chip.classList.toggle('is-active', isActive);
          chip.style.border = isActive ? '1px solid #0f766e' : '1px solid #cbd5e1';
          chip.style.background = isActive ? '#ecfeff' : '#fff';
          chip.style.color = isActive ? '#0f766e' : '#475569';
        });

        let visibleCount = 0;
        targetRows.forEach(function (row) {
          const hasDiff = row.getAttribute('data-has-diff') === '1';
          const show = (selectedFilter === 'all') || (selectedFilter === 'status_changes' && hasDiff);
          row.style.display = show ? '' : 'none';
          if (show) {
            visibleCount++;
          }
        });

        if (targetEmpty) {
          targetEmpty.style.display = visibleCount === 0 ? '' : 'none';
        }

        if (targetSource) {
          const sourceLabel = source === 'global' ? 'Global' : (source === 'row' ? 'Row' : 'Default');
          const modeLabel = selectedFilter === 'status_changes' ? 'Status Changes' : 'All Events';
          targetSource.textContent = 'Source: ' + sourceLabel + ' (' + modeLabel + ')';
        }

        if (targetOriginChip) {
          const sourceLabel = source === 'global' ? 'Global' : (source === 'row' ? 'Row' : 'Default');
          targetOriginChip.textContent = 'Origin: ' + sourceLabel;
          targetOriginChip.setAttribute('data-origin-label', sourceLabel);
          if (source === 'row') {
            targetOriginChip.style.background = '#ecfeff';
            targetOriginChip.style.borderColor = '#99f6e4';
            targetOriginChip.style.color = '#0f766e';
          } else if (source === 'global') {
            targetOriginChip.style.background = '#eff6ff';
            targetOriginChip.style.borderColor = '#bfdbfe';
            targetOriginChip.style.color = '#1d4ed8';
          } else {
            targetOriginChip.style.background = '#f8fafc';
            targetOriginChip.style.borderColor = '#cbd5e1';
            targetOriginChip.style.color = '#475569';
          }
        }

        refreshOverrideSummary(targetShell);
      }

      chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
          const filter = String(chip.getAttribute('data-filter') || 'all');
          activateFilter(filter, true, 'row');
          refreshAllOverrideSummaries();
          if (rememberGlobalCheckbox && rememberGlobalCheckbox.checked) {
            setGlobalAuditFilterState(true, filter);
          }
        });
      });

      shell.addEventListener('keydown', function (event) {
        const key = String(event.key || '').toLowerCase();

        if (!event.defaultPrevented && !event.altKey && !event.metaKey && event.ctrlKey && key === 'y') {
          event.preventDefault();
          const sourceFilterSnapshot = buildSourceFilterSnapshot();
          copyCurrentSourceLabel('Ctrl+Y -> Copy source+filter', sourceFilterSnapshot, sourceFilterPreviewToastText(sourceFilterSnapshot), 'filter');
          return;
        }

        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
          return;
        }

        const target = event.target;
        if (target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(target.tagName))) {
          return;
        }

        if (key === 'a') {
          event.preventDefault();
          applyShortcutFilter('all');
          setKeyStatus('A -> All Events');
          showToast('History view: All Events.', false);
        } else if (key === 's') {
          event.preventDefault();
          applyShortcutFilter('status_changes');
          setKeyStatus('S -> Status Changes');
          showToast('History view: Status Changes.', false);
        } else if (key === '?' || (key === '/' && event.shiftKey)) {
          event.preventDefault();
          const show = !shortcutHelp || shortcutHelp.style.display === 'none';
          setShortcutHelpVisible(show);
          setKeyStatus(show ? '? -> Help shown' : '? -> Help hidden');
        } else if (key === 'r') {
          event.preventDefault();
          applyResetViewShortcut();
          setKeyStatus('R -> Reset view');
          showToast('History view reset.', false);
        } else if (key === 'c') {
          event.preventDefault();
          applyClearVisibleShortcut();
          setKeyStatus('C -> Clear overrides');
        } else if (key === 'g') {
          event.preventDefault();
          if (rememberGlobalCheckbox) {
            rememberGlobalCheckbox.checked = !rememberGlobalCheckbox.checked;
            rememberGlobalCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
            setKeyStatus('G -> Global mode ' + (rememberGlobalCheckbox.checked ? 'On' : 'Off'));
            showToast('Global mode ' + (rememberGlobalCheckbox.checked ? 'enabled.' : 'disabled.'), false);
          }
        } else if (key === 'h' && event.shiftKey) {
          event.preventDefault();
          const changed = resetHintToCompact('keyboard Shift+H');
          if (!changed) {
            setKeyStatus('Shift+H -> Hint compact (no change)');
          }
        } else if (key === 'h') {
          event.preventDefault();
          const nextDetailed = !isShortcutHintDetailed;
          applyHintModeTransition(nextDetailed, {
            triggerKey: 'keyboard H'
          });
        } else if (key === 'm' && event.shiftKey) {
          event.preventDefault();
          applyHintToastUnmute('Shift+M -> Hint toasts unmuted', 'keyboard Shift+M');
        } else if (key === 'p' && event.shiftKey) {
          event.preventDefault();
          const policy = loadCopyAnnouncementPolicy();
          setKeyStatus('Shift+P -> Reload policy');
          const fallbackNote = lastPolicyFallbackFields.length > 0
            ? ' Fallback applied: ' + lastPolicyFallbackFields.join(', ') + '.'
            : '';
          showToast('Policy reloaded: Toast ' + policy.originToastCooldownMs + 'ms | Success ' + policy.originSuccessLiveCooldownMs + 'ms | Failure ' + policy.originFailureLiveCooldownMs + 'ms | Recovery ' + policy.originRecoveryWindowMs + 'ms.' + fallbackNote, false);
        } else if (key === 'k' && event.shiftKey) {
          event.preventDefault();
          copyPolicyReadout('Shift+K -> Copy policy');
        } else if (key === 'm') {
          event.preventDefault();
          applyHintToastMuteToggle('M -> Hint toasts');
        } else if (key === 'y' && event.shiftKey) {
          event.preventDefault();
          const snapshot = buildRichSourceSnapshot();
          copyCurrentSourceLabel('Shift+Y -> Copy snapshot', snapshot, snapshotPreviewToastText(snapshot), 'snapshot');
        } else if (key === 'o' && event.shiftKey) {
          event.preventDefault();
          copyOriginContextSnapshot('Shift+O -> Copy origin context');
        } else if (key === 'o') {
          event.preventDefault();
          copyCurrentOriginLabel('O -> Copy origin');
        } else if (key === 'y') {
          event.preventDefault();
          copyCurrentSourceLabel('Y -> Copy source', undefined, undefined, 'copied');
        } else if (key === 'escape') {
          event.preventDefault();
          setKeyStatus('Escape -> Close panel');
          closeHistoryRow(taskId, true);
        }
      });

      if (shortcutHint) {
        shell.addEventListener('focusin', function () {
          if (cheatLine) {
            cheatLine.style.display = '';
          }
          shortcutHint.style.display = '';
        });

        shell.addEventListener('focusout', function () {
          // Defer so document.activeElement is updated before containment check.
          window.setTimeout(function () {
            if (!shell.contains(document.activeElement)) {
              if (cheatLine) {
                cheatLine.style.display = 'none';
              }
              shortcutHint.style.display = 'none';
              setShortcutHelpVisible(false);
            }
          }, 0);
        });
      }

      if (rememberGlobalCheckbox) {
        const globalState = getGlobalAuditFilterState();
        rememberGlobalCheckbox.checked = globalState.enabled;
        rememberGlobalCheckbox.addEventListener('change', function () {
          const enabled = rememberGlobalCheckbox.checked;
          const filter = currentActiveFilter();
          setGlobalAuditFilterState(enabled, filter);
          syncRememberGlobalCheckboxes(enabled);
          refreshAllGlobalModeBadges();
          const hasRowOverride = getStoredAuditFilter(taskId) === 'status_changes' || getStoredAuditFilter(taskId) === 'all';
          if (!hasRowOverride) {
            applySourceIndicator(enabled ? 'global' : 'default', filter);
          }
        });
      }

      if (resetButton) {
        resetButton.addEventListener('click', function () {
          applyResetViewShortcut();
        });
      }

      if (applyVisibleButton) {
        applyVisibleButton.addEventListener('click', function () {
          const selectedFilter = currentActiveFilter();
          const previousGlobalState = getGlobalAuditFilterState();
          const shouldWriteGlobal = Boolean(rememberGlobalCheckbox && rememberGlobalCheckbox.checked);
          const previousRows = [];
          let updatedCount = 0;

          getVisibleTaskRows().forEach(function (taskRow) {
            const visibleTaskId = String(taskRow.getAttribute('data-task-id') || '').trim();
            if (!visibleTaskId) {
              return;
            }

            previousRows.push({
              taskId: visibleTaskId,
              filter: getStoredAuditFilter(visibleTaskId)
            });

            setStoredAuditFilter(visibleTaskId, selectedFilter);
            updatedCount++;

            const historyRow = document.querySelector('.task-audit-history-row[data-task-id="' + CSS.escape(visibleTaskId) + '"]');
            if (!historyRow) {
              return;
            }

            const visibleShell = historyRow.querySelector('.task-audit-history-shell');
            if (visibleShell) {
              refreshShellVisualState(visibleShell, selectedFilter, 'row');
            }
          });

          refreshAllOverrideSummaries();

          if (shouldWriteGlobal) {
            setGlobalAuditFilterState(true, selectedFilter);
            refreshAllGlobalModeBadges();
          }

          showToast('Applied to ' + updatedCount + ' visible row' + (updatedCount === 1 ? '' : 's') + '.', false, {
            label: 'Undo',
            onClick: function () {
              previousRows.forEach(function (entry) {
                if (entry.filter === 'status_changes' || entry.filter === 'all') {
                  setStoredAuditFilter(entry.taskId, entry.filter);
                } else {
                  clearStoredAuditFilter(entry.taskId);
                }
              });

              if (shouldWriteGlobal) {
                setGlobalAuditFilterState(previousGlobalState.enabled, previousGlobalState.filter);
                syncRememberGlobalCheckboxes(previousGlobalState.enabled);
                refreshAllGlobalModeBadges();
              }

              const restoredGlobalState = getGlobalAuditFilterState();
              document.querySelectorAll('.task-audit-history-shell').forEach(function (targetShell) {
                const targetTaskId = getTaskIdForHistoryShell(targetShell);
                const restoredFilter = getStoredAuditFilter(targetTaskId);
                const nextFilter = (restoredFilter === 'status_changes' || restoredFilter === 'all')
                  ? restoredFilter
                  : (restoredGlobalState.enabled ? restoredGlobalState.filter : 'all');
                const nextSource = (restoredFilter === 'status_changes' || restoredFilter === 'all')
                  ? 'row'
                  : (restoredGlobalState.enabled ? 'global' : 'default');
                refreshShellVisualState(targetShell, nextFilter, nextSource);
              });

              refreshAllOverrideSummaries();

              showToast('Bulk apply was undone.', false);
            }
          });
        });
      }

      if (clearVisibleButton) {
        clearVisibleButton.addEventListener('click', function () {
          applyClearVisibleShortcut();
        });
      }

      const storedFilter = getStoredAuditFilter(taskId);
      const globalState = getGlobalAuditFilterState();
      const initialFilter = (storedFilter === 'status_changes' || storedFilter === 'all')
        ? storedFilter
        : (globalState.enabled ? globalState.filter : 'all');
      const initialSource = (storedFilter === 'status_changes' || storedFilter === 'all')
        ? 'row'
        : (globalState.enabled ? 'global' : 'default');
      activateFilter(initialFilter, false, initialSource);
      if (rememberGlobalCheckbox) {
        rememberGlobalCheckbox.checked = globalState.enabled;
      }
      refreshOverrideSummary(shell);
      refreshGlobalModeBadge(shell);
      shell.setAttribute('data-filters-bound', '1');
    });
  }

  function shouldRemoveRowAfterUpdate(status) {
    if (activeStatusFilter && activeStatusFilter !== 'all' && status !== activeStatusFilter) {
      return true;
    }

    if (activeViewFilter === 'open' && (status === 'completed' || status === 'archived')) {
      return true;
    }

    return false;
  }

  function findHistoryToggleButton(taskId) {
    const safeTaskId = String(taskId || '').trim();
    if (!safeTaskId) {
      return null;
    }

    return document.querySelector('.js-audit-history-toggle[data-task-id="' + CSS.escape(safeTaskId) + '"]');
  }

  function openHistoryRow(taskId, triggerButton) {
    const safeTaskId = String(taskId || '').trim();
    if (!safeTaskId) {
      return;
    }

    const historyRow = document.querySelector('.task-audit-history-row[data-task-id="' + CSS.escape(safeTaskId) + '"]');
    if (!historyRow) {
      return;
    }

    historyRow.removeAttribute('hidden');
    if (triggerButton) {
      triggerButton.textContent = 'Hide history';
    }

    const primaryToggle = findHistoryToggleButton(safeTaskId);
    if (primaryToggle) {
      primaryToggle.textContent = 'Hide history';
    }

    const shell = historyRow.querySelector('.task-audit-history-shell');
    if (shell && typeof shell.focus === 'function') {
      shell.focus();
    }

    if (!detailedHintRestoreToastShown.has(safeTaskId) && getStoredAuditHintMode(safeTaskId) === 'detailed') {
      detailedHintRestoreToastShown.add(safeTaskId);
      showToast('Hint mode restored from session: Detailed.', false);
    }
  }

  function closeHistoryRow(taskId, returnFocusToToggle) {
    const safeTaskId = String(taskId || '').trim();
    if (!safeTaskId) {
      return;
    }

    const historyRow = document.querySelector('.task-audit-history-row[data-task-id="' + CSS.escape(safeTaskId) + '"]');
    if (!historyRow) {
      return;
    }

    historyRow.setAttribute('hidden', 'hidden');
    const primaryToggle = findHistoryToggleButton(safeTaskId);
    if (primaryToggle) {
      primaryToggle.textContent = 'View last 3 events';
      if (returnFocusToToggle && typeof primaryToggle.focus === 'function') {
        primaryToggle.focus();
      }
    }
  }

  document.querySelectorAll('.js-audit-history-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const taskId = String(button.getAttribute('data-task-id') || '').trim();
      if (!taskId) {
        return;
      }

      const historyRow = document.querySelector('.task-audit-history-row[data-task-id="' + CSS.escape(taskId) + '"]');
      if (!historyRow) {
        return;
      }

      const currentlyHidden = historyRow.hasAttribute('hidden');
      if (currentlyHidden) {
        openHistoryRow(taskId, button);
      } else {
        closeHistoryRow(taskId, false);
      }
    });
  });

  wireAuditHistoryFilters(document);

  document.querySelectorAll('form.js-inline-status-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();

      const saveButton = form.querySelector('.js-inline-status-save');
      const select = form.querySelector('.js-inline-status-select');
      const row = form.closest('tr');
      const taskId = row ? String(row.getAttribute('data-task-id') || '').trim() : '';
      const historyRow = taskId ? document.querySelector('.task-audit-history-row[data-task-id="' + CSS.escape(taskId) + '"]') : null;
      const historyContent = historyRow ? historyRow.querySelector('.task-audit-history-content') : null;
      const statusCell = row ? row.querySelector('.task-status-cell') : null;
      const auditCell = row ? row.querySelector('.task-audit-cell') : null;
      if (!saveButton || !select) {
        form.submit();
        return;
      }

      const previousLabel = saveButton.getAttribute('data-default-label') || 'Save';
      const formData = new FormData(form);

      saveButton.disabled = true;
      select.disabled = true;
      saveButton.textContent = 'Saving...';

      fetch(form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      }).then(function (response) {
        return response.json().catch(function () {
          return { ok: false, error: 'invalid_json' };
        });
      }).then(function (payload) {
        if (!payload || payload.ok !== true) {
          showToast('Status update failed. Please retry.', true);
          return;
        }

        const nextStatus = String(payload.status || select.value || 'not_started');
        const nextLabel = String(payload.status_label || statusLabels[nextStatus] || nextStatus);

        if (statusCell) {
          statusCell.innerHTML = renderBadge(nextStatus);
        }

        if (auditCell && payload.audit_preview) {
          auditCell.innerHTML = renderAuditPreview(payload.audit_preview);
          auditCell.innerHTML += '<div style="margin-top:6px;"><button type="button" class="js-audit-history-toggle" data-task-id="' + escapeHtml(taskId) + '" style="border:none;background:transparent;color:#0f766e;font-size:11px;font-weight:600;padding:0;cursor:pointer;">' + (historyRow && !historyRow.hasAttribute('hidden') ? 'Hide history' : 'View last 3 events') + '</button></div>';
          const replacementToggle = auditCell.querySelector('.js-audit-history-toggle');
          if (replacementToggle) {
            replacementToggle.addEventListener('click', function () {
              if (!historyRow) {
                return;
              }
              const currentlyHidden = historyRow.hasAttribute('hidden');
              if (currentlyHidden) {
                openHistoryRow(taskId, replacementToggle);
              } else {
                closeHistoryRow(taskId, false);
              }
            });
          }
        }

        if (historyContent && Array.isArray(payload.audit_history)) {
          historyContent.innerHTML = renderAuditHistory(payload.audit_history);
          wireAuditHistoryFilters(historyContent);
        }

        if (shouldRemoveRowAfterUpdate(nextStatus) && row) {
          if (historyRow) {
            historyRow.remove();
          }
          row.remove();
        }

        showToast('Status updated to ' + nextLabel + '.', false);
      }).catch(function () {
        showToast('Network error while saving status.', true);
      }).finally(function () {
        saveButton.disabled = false;
        select.disabled = false;
        saveButton.textContent = previousLabel;
      });
    });
  });
})();
</script>
<?php require_once 'layout_end.php'; ?>
