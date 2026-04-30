<?php
// contacts_csv_cleaner.php
// Usage: Run this script in the same directory as contacts.csv to clean malformed rows.

$csvFile = __DIR__ . '/contacts.csv';
$backupFile = __DIR__ . '/contacts_backup_' . date('Ymd_His') . '.csv';

if (!file_exists($csvFile)) {
    die("contacts.csv not found\n");
}

// Backup original file
copy($csvFile, $backupFile);
echo "Backup created: $backupFile\n";

$rows = [];
$handle = fopen($csvFile, 'r');
if ($handle === false) {
    die("Unable to open contacts.csv\n");
}

$header = fgetcsv($handle);
if ($header === false) {
    die("contacts.csv is empty or invalid\n");
}
$colCount = count($header);
$rows[] = $header;

$fixed = 0;
$skipped = 0;
while (($row = fgetcsv($handle)) !== false) {
    if (count($row) === $colCount) {
        $rows[] = $row;
    } elseif (count($row) < $colCount) {
        // Pad missing columns with empty strings
        $row = array_pad($row, $colCount, '');
        $rows[] = $row;
        $fixed++;
    } else {
        // Too many columns, trim extra
        $row = array_slice($row, 0, $colCount);
        $rows[] = $row;
        $fixed++;
    }
}
fclose($handle);

// Write cleaned data back
$handle = fopen($csvFile, 'w');
foreach ($rows as $row) {
    fputcsv($handle, $row);
}
fclose($handle);

echo "contacts.csv cleaned. $fixed row(s) fixed.\n";
