<?php
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$pageTitle = 'AI Activity';

require_once 'simple_auth/middleware.php';
require_once 'layout_start.php';
require_once 'db_mysql.php';
require_once 'ai_helper.php';

function ai_activity_scalar(mysqli $conn, string $sql, array $params = [], string $types = ''): int
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return (int) ($row['c'] ?? 0);
}

$conn = get_mysql_connection();
ai_ensure_activity_log_table($conn);

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$kindFilter = trim((string) ($_GET['kind'] ?? ''));
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$limit = max(25, min(500, $limit));

$where = [];
$params = [];
$types = '';

if ($statusFilter !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}
if ($kindFilter !== '') {
    $where[] = 'request_kind = ?';
    $params[] = $kindFilter;
    $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = [];
$sql = "SELECT id, provider, model, selection_mode, status, request_kind, user_identifier, prompt_chars, response_chars, input_tokens, output_tokens, total_tokens, error_message, metadata_json, created_at
        FROM ai_activity_log
        {$whereSql}
        ORDER BY id DESC
        LIMIT ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $paramsWithLimit = $params;
    $paramsWithLimit[] = $limit;
    $typesWithLimit = $types . 'i';
    $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        if ($result) {
            $result->free();
        }
    }
    $stmt->close();
}

$totalCount = ai_activity_scalar($conn, 'SELECT COUNT(*) AS c FROM ai_activity_log');
$blockedCount = ai_activity_scalar($conn, "SELECT COUNT(*) AS c FROM ai_activity_log WHERE status = 'blocked'");
$successCount = ai_activity_scalar($conn, "SELECT COUNT(*) AS c FROM ai_activity_log WHERE status = 'success'");
$errorCount = ai_activity_scalar($conn, "SELECT COUNT(*) AS c FROM ai_activity_log WHERE status = 'error'");
$todayCount = ai_activity_scalar($conn, 'SELECT COUNT(*) AS c FROM ai_activity_log WHERE DATE(created_at) = CURDATE()');

$kindOptions = [];
$kindResult = $conn->query('SELECT DISTINCT request_kind FROM ai_activity_log WHERE request_kind IS NOT NULL AND request_kind <> "" ORDER BY request_kind');
if ($kindResult) {
    while ($kindRow = $kindResult->fetch_assoc()) {
        $kindOptions[] = (string) ($kindRow['request_kind'] ?? '');
    }
    $kindResult->free();
}

$conn->close();
?>

<style>
  .ai-activity-wrap { max-width: 1280px; margin: 24px auto; }
  .ai-activity-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
  .ai-activity-title h1 { margin:0 0 4px 0; font-size:24px; font-weight:700; }
  .ai-activity-title p { margin:0; color:#64748b; font-size:13px; }
  .ai-activity-cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:18px; }
  .ai-card { background:white; border:1px solid #dfe5ef; border-radius:12px; padding:16px; }
  .ai-card-label { color:#64748b; font-size:12px; text-transform:uppercase; font-weight:700; margin-bottom:6px; }
  .ai-card-value { font-size:24px; font-weight:700; color:#111827; }
  .ai-filter-card, .ai-table-card { background:white; border:1px solid #dfe5ef; border-radius:12px; overflow:hidden; }
  .ai-filter-card { padding:16px; margin-bottom:18px; }
  .ai-table-card-header { padding:14px 16px; background:#f8fafc; border-bottom:1px solid #e5e7eb; font-weight:700; }
  .ai-status { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; }
  .ai-status-success { background:#dcfce7; color:#166534; }
  .ai-status-blocked { background:#fff7ed; color:#9a3412; }
  .ai-status-error { background:#fee2e2; color:#991b1b; }
  .ai-status-unknown { background:#e5e7eb; color:#374151; }
  .ai-small { color:#64748b; font-size:12px; }
  .ai-code { font-family:Consolas, 'Courier New', monospace; font-size:12px; }
  .ai-meta { white-space:pre-wrap; font-size:12px; color:#374151; max-width:320px; }
  .ai-table-wrap { overflow:auto; }
  .ai-table { width:100%; min-width:1300px; border-collapse:collapse; }
  .ai-table th { background:#f8fafc; text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:0.03em; color:#64748b; padding:12px 14px; border-bottom:1px solid #e5e7eb; }
  .ai-table td { padding:12px 14px; border-bottom:1px solid #f1f5f9; vertical-align:top; font-size:13px; }
  .ai-table tr:hover td { background:#fafcff; }
</style>

<div class="ai-activity-wrap">
  <div class="ai-activity-header">
    <div class="ai-activity-title">
      <h1>AI Activity</h1>
      <p>Monitor blocked, successful, and failed AI requests before enabling spend.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="ai_settings.php" class="btn btn-outline-secondary btn-sm">AI Settings</a>
      <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>

  <div class="alert alert-warning" style="margin-bottom:18px;">
    AI no-spend mode is currently expected to produce <strong>blocked</strong> rows. That confirms requests are being captured without external API spend.
  </div>

  <div class="ai-activity-cards">
    <div class="ai-card"><div class="ai-card-label">Total logged</div><div class="ai-card-value"><?= (int) $totalCount ?></div></div>
    <div class="ai-card"><div class="ai-card-label">Blocked</div><div class="ai-card-value"><?= (int) $blockedCount ?></div></div>
    <div class="ai-card"><div class="ai-card-label">Successful</div><div class="ai-card-value"><?= (int) $successCount ?></div></div>
    <div class="ai-card"><div class="ai-card-label">Errors</div><div class="ai-card-value"><?= (int) $errorCount ?></div></div>
    <div class="ai-card"><div class="ai-card-label">Today</div><div class="ai-card-value"><?= (int) $todayCount ?></div></div>
  </div>

  <form method="get" class="ai-filter-card">
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select">
          <option value="">All</option>
          <?php foreach (['blocked', 'success', 'error'] as $statusOption): ?>
            <option value="<?= htmlspecialchars($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($statusOption)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label for="kind" class="form-label">Request Type</label>
        <select id="kind" name="kind" class="form-select">
          <option value="">All</option>
          <?php foreach ($kindOptions as $kindOption): ?>
            <option value="<?= htmlspecialchars($kindOption) ?>" <?= $kindFilter === $kindOption ? 'selected' : '' ?>><?= htmlspecialchars($kindOption) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label for="limit" class="form-label">Rows</label>
        <select id="limit" name="limit" class="form-select">
          <?php foreach ([25, 50, 100, 250, 500] as $opt): ?>
            <option value="<?= (int) $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= (int) $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Refresh</button>
        <a href="ai_activity.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </div>
  </form>

  <div class="ai-table-card">
    <div class="ai-table-card-header">Recent AI requests</div>
    <div class="ai-table-wrap">
      <table class="ai-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Created</th>
            <th>Status</th>
            <th>Type</th>
            <th>Provider / Model</th>
            <th>Selection</th>
            <th>User</th>
            <th>Chars</th>
            <th>Tokens</th>
            <th>Error / Result</th>
            <th>Metadata</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="11" class="ai-small">No AI activity logged yet. Trigger an AI action from a contact, customer, or inventory page to create a row.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php $status = (string) ($row['status'] ?? 'unknown'); ?>
              <tr>
                <td class="ai-code">#<?= (int) ($row['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></td>
                <td><span class="ai-status ai-status-<?= htmlspecialchars(in_array($status, ['success', 'blocked', 'error'], true) ? $status : 'unknown') ?>"><?= htmlspecialchars($status) ?></span></td>
                <td class="ai-code"><?= htmlspecialchars((string) ($row['request_kind'] ?? '')) ?></td>
                <td>
                  <div class="ai-code"><?= htmlspecialchars((string) ($row['provider'] ?? '')) ?></div>
                  <div class="ai-small"><?= htmlspecialchars((string) ($row['model'] ?? '')) ?></div>
                </td>
                <td class="ai-code"><?= htmlspecialchars((string) ($row['selection_mode'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['user_identifier'] ?? 'system')) ?></td>
                <td class="ai-small">P: <?= (int) ($row['prompt_chars'] ?? 0) ?><br>R: <?= (int) ($row['response_chars'] ?? 0) ?></td>
                <td class="ai-small">In: <?= (int) ($row['input_tokens'] ?? 0) ?><br>Out: <?= (int) ($row['output_tokens'] ?? 0) ?><br>Total: <?= (int) ($row['total_tokens'] ?? 0) ?></td>
                <td class="ai-small"><?= htmlspecialchars((string) ($row['error_message'] ?? 'OK')) ?></td>
                <td class="ai-meta"><?= htmlspecialchars((string) ($row['metadata_json'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once 'layout_end.php'; ?>