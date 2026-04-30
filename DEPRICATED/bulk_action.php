<?php
require_once 'db_pgsql.php';

$schemaFile = __DIR__ . '/contact_schema.php';
$schema = file_exists($schemaFile) ? require $schemaFile : [];
if (!is_array($schema)) {
    die('Error: contact_schema.php must return an array.');
}


function fetch_contacts_pgsql($schema) {
    $conn = get_pgsql_connection();
    $fields = implode(',', array_map(function($f) { return '"' . $f . '"'; }, $schema));
    $result = pg_query($conn, "SELECT $fields FROM contacts");
    if (!$result) return [];
    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }
    pg_free_result($result);
    return $rows;
}

function update_contact_pgsql($id, $fields) {
    $conn = get_pgsql_connection();
    $set = [];
    foreach ($fields as $k => $v) {
        $set[] = '"' . pg_escape_string($k) . '"=' . (is_null($v) ? 'NULL' : "'" . pg_escape_string($v) . "'");
    }
    $setStr = implode(',', $set);
    $idEsc = pg_escape_string($id);
    $sql = "UPDATE contacts SET $setStr WHERE id='$idEsc'";
    return pg_query($conn, $sql);
}

function delete_contact_pgsql($id) {
    $conn = get_pgsql_connection();
    $idEsc = pg_escape_string($id);
    $sql = "DELETE FROM contacts WHERE id='$idEsc'";
    return pg_query($conn, $sql);
}

$contacts = fetch_contacts_pgsql($schema);

$selectedIds = $_POST['selected_ids'] ?? [];
$action = $_POST['action'] ?? '';
$tagToAssign = $_POST['assign_tag'] ?? '';
$statusToAssign = $_POST['assign_status'] ?? '';

if (!is_array($selectedIds) || empty($action)) {
    die('Error: No contacts selected or no action specified.');
}

$updatedContacts = [];
$exportRows = [];


foreach ($contacts as $contact) {
    $id = $contact['id'] ?? '';
    $isSelected = in_array($id, $selectedIds);

    if ($action === 'delete' && $isSelected) {
        delete_contact_pgsql($id);
        continue;
    }

    if ($action === 'export' && $isSelected) {
        $row = [];
        foreach ($schema as $field) {
            $row[] = $contact[$field] ?? '';
        }
        $exportRows[] = $row;
    }

    $fieldsToUpdate = [];
    if ($action === 'assign_tag' && $isSelected && $tagToAssign !== '') {
        $existingTags = array_map('trim', explode(',', $contact['tags'] ?? ''));
        if (!in_array($tagToAssign, $existingTags)) {
            $existingTags[] = $tagToAssign;
        }
        $fieldsToUpdate['tags'] = implode(', ', $existingTags);
    }

    if ($action === 'assign_status' && $isSelected && $statusToAssign !== '') {
        $fieldsToUpdate['status'] = $statusToAssign;
    }

    if (!empty($fieldsToUpdate)) {
        update_contact_pgsql($id, $fieldsToUpdate);
    }

    $updatedContacts[] = $contact;
}

if ($action === 'export') {
    $filename = 'contacts_bulk_export_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $schema);
    foreach ($exportRows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
} else {
    // All updates/deletes already done in DB, just redirect
    header('Location: contacts_list.php');
    exit;
}
?>