<?php

$schema = require __DIR__ . '/opportunity_schema.php';
$contactSchema = require __DIR__ . '/contact_schema.php';
require_once 'db_mysql.php';
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
$opportunities = fetch_table_mysql('opportunities', $schema);
$contacts = fetch_table_mysql('contacts', $contactSchema);

// Build contact lookup map
$contactMap = [];
foreach ($contacts as $contact) {
    $fullName = trim($contact['first_name'] . ' ' . $contact['last_name']);
    $company = $contact['company'] ?? '';
    $contactMap[trim($contact['id'])] = [
        'name' => $fullName ?: 'Unnamed Contact',
        'company' => $company
    ];
}

// Get filter parameters
$filterStage = $_GET['stage'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Apply filters
$filteredOpportunities = $opportunities;

if ($filterStage) {
    $filteredOpportunities = array_filter($filteredOpportunities, function($opp) use ($filterStage) {
        return ($opp['stage'] ?? '') === $filterStage;
    });
}

if ($searchQuery) {
    $filteredOpportunities = array_filter($filteredOpportunities, function($opp) use ($searchQuery, $contactMap) {
        $contactId = trim($opp['contact_id'] ?? '');
        $contactName = $contactMap[$contactId]['name'] ?? '';
        $contactCompany = $contactMap[$contactId]['company'] ?? '';
        
        return stripos($contactName, $searchQuery) !== false ||
               stripos($contactCompany, $searchQuery) !== false ||
               stripos($opp['id'] ?? '', $searchQuery) !== false ||
               stripos($opp['stage'] ?? '', $searchQuery) !== false;
    });
}

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=opportunities_export_' . date('Y-m-d_His') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, [
    'ID',
    'Contact Name',
    'Company',
    'Value',
    'Probability (%)',
    'Weighted Value',
    'Stage',
    'Expected Close Date'
]);

// Write data rows
foreach ($filteredOpportunities as $opp) {
    $contactId = trim($opp['contact_id'] ?? '');
    $contactInfo = $contactMap[$contactId] ?? ['name' => 'Unknown Contact', 'company' => ''];
    $value = floatval($opp['value'] ?? 0);
    $probability = floatval($opp['probability'] ?? 0);
    $weightedValue = $value * ($probability / 100);
    
    fputcsv($output, [
        $opp['id'] ?? '',
        $contactInfo['name'],
        $contactInfo['company'] ?: '—',
        number_format($value, 2),
        $probability,
        number_format($weightedValue, 2),
        $opp['stage'] ?? '',
        $opp['expected_close'] ?? '—'
    ]);
}

fclose($output);
exit;
