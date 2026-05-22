<?php
require_once 'db_mysql.php';
require_once 'sanitize_helper.php';
require_once __DIR__ . '/request_guard.php';

header('Content-Type: application/json');

require_post_with_csrf_json();

$id = $_POST['id'] ?? null;
$field = $_POST['field'] ?? null;
$value = $_POST['value'] ?? null;

if (!$id || !$field) {
  echo json_encode(['error' => 'Missing parameters']);
  exit;
}

$allowedFields = ['contact_id', 'value', 'stage', 'probability', 'expected_close'];
if (!in_array($field, $allowedFields)) {
  echo json_encode(['error' => 'Invalid field']);
  exit;
}


function getOpportunityIdColumn(mysqli $conn): string {
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
  if ($hasOpportunityId) {
    return 'opportunity_id';
  }
  return $hasId ? 'id' : 'opportunity_id';
}


$conn = get_mysql_connection();
$idColumn = getOpportunityIdColumn($conn);
$safeField = str_replace('`', '', $field);

$old_stmt = $conn->prepare("SELECT `{$safeField}` FROM opportunities WHERE {$idColumn} = ?");
$old_stmt->bind_param('s', $id);
$old_stmt->execute();
$old_stmt->bind_result($old_value);
$old_stmt->fetch();
$old_stmt->close();

$stmt = $conn->prepare("UPDATE opportunities SET `{$safeField}` = ? WHERE {$idColumn} = ?");
if (!$stmt) {
  echo json_encode(['error' => 'DB error']);
  exit;
}
$stmt->bind_param('ss', $value, $id);
if ($stmt->execute()) {
  // Log edit
  require_once 'opportunity_edit_log.php';
  $user_id = $_SESSION['user_id'] ?? 'system';
  log_opportunity_edit($id, $field, $old_value, $value, $user_id);
  echo json_encode(['success' => true]);
} else {
  echo json_encode(['error' => 'Update failed']);
}
$stmt->close();
$conn->close();
