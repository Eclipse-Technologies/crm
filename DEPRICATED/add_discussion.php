<?php
require_once 'contact_validator.php';
require_once 'discussion_logger.php'; // contains logDiscussionEntry()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactId = trim($_POST['contact_id'] ?? '');
    // Validate input
    $errors = validateContact($_POST);
    if (!empty($errors)) {
        foreach ($errors as $field => $msg) {
            echo "<p>Error in $field: $msg</p>";
        }
        $backUrl = $contactId !== '' ? 'contact_view.php?id=' . urlencode($contactId) : 'contacts_list.php';
        echo "<p><a href='$backUrl'>Go back</a></p>";
        exit;
    }

    // Log the discussion entry
    if (logDiscussionEntry($_POST)) {
        $redirectUrl = $contactId !== '' ? 'contact_view.php?id=' . urlencode($contactId) : 'contacts_list.php';
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        echo "<p style='color:red;'>Failed to log discussion entry. Please check the error log.</p>";
        $backUrl = $contactId !== '' ? 'contact_view.php?id=' . urlencode($contactId) : 'contacts_list.php';
        echo "<p><a href='$backUrl'>Go back</a></p>";
    }
}
?>

