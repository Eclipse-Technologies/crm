<?php
require_once 'C:/Users/rober/OneDrive/0.5-Eclipse/Marketing/Website/CRM/db_mysql.php';
$conn = get_mysql_connection();
$oppCols = $conn->query("SHOW COLUMNS FROM opportunities");
$oppFields = [];
while($r = $oppCols->fetch_assoc()){ $oppFields[] = $r['Field']; }
$idCol = in_array('opportunity_id', $oppFields, true) ? 'opportunity_id' : 'id';
$stmt = $conn->prepare("SELECT {$idCol} AS opp_id, contact_id, stage, value, probability, expected_close FROM opportunities WHERE {$idCol} = ? LIMIT 1");
$oppId = 9;
$stmt->bind_param('i', $oppId);
$stmt->execute();
$res = $stmt->get_result();
$opp = $res ? $res->fetch_assoc() : null;
$stmt->close();
echo "idCol={$idCol}\n";
if (!$opp) { echo "opp not found\n"; $conn->close(); exit; }
echo "opp=" . json_encode($opp) . "\n";
if (!empty($opp['contact_id'])) {
  $stmt2 = $conn->prepare("SELECT contact_id, company, first_name, last_name FROM contacts WHERE contact_id = ? LIMIT 1");
  $cid = (int)$opp['contact_id'];
  $stmt2->bind_param('i', $cid);
  $stmt2->execute();
  $res2 = $stmt2->get_result();
  $contact = $res2 ? $res2->fetch_assoc() : null;
  $stmt2->close();
  echo "contact=" . json_encode($contact) . "\n";
} else {
  echo "contact_id empty\n";
}
$conn->close();
?>
