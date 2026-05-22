<?php include_once(__DIR__ . '/layout_start.php'); ?>
<?php $currentPage = basename(__FILE__); ?>
<?php
require_once __DIR__ . '/request_guard.php';

require_post_with_csrf('contacts_list.php?error=csrf');

require_once 'contact_validator.php';require_once 'csv_handler.php';

$schema = getContactSchema();
$contacts = readCSV('contacts.csv');
$updatedId = $_POST['id'] ?? null;

if (!$updatedId) {
    echo "Missing contact ID.";
    exit;
}

// Build updated contact
$updatedContact = [];
foreach ($schema as $col) {
    $updatedContact[$col] = $errors = validateContact($_POST);
if (!empty($errors)) {
  foreach ($errors as $f => $msg) {
    echo "<p>Error in $f: $msg</p>";
  }
  exit;
}

$_POST[$col] ?? '';
}

// Replace contact in list
$updatedList = array_map(function($c) use ($updatedId, $updatedContact) {
    return $c['id'] === $updatedId ? $updatedContact : $c;
}, $contacts);

// Write back to CSV
writeCSV('contacts.csv', $updatedList);

// Redirect to viewer
header("Location: contact_view.php?id=$updatedId");
exit;
?>

<?php include_once(__DIR__ . '/layout_end.php'); ?>
