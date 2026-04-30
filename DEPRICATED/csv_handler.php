<?php
// This file has been archived to archive_legacy_2026/csv_handler.php as of 2026-02-19. See archive_legacy_2026/README.txt for details.
if (file_exists(__DIR__ . '/backup_handler.php')) {
	require_once __DIR__ . '/backup_handler.php';
}

function readCSV($filename, $schema) {
	if (!file_exists($filename)) {
		echo "File not found: $filename";
		return [];
	}

	$rows = [];
	if (($handle = fopen($filename, 'r')) !== false) {
		$headers = fgetcsv($handle);
		if ($headers === false) {
			echo "Failed to read headers.";
			return [];
		}

		while (($data = fgetcsv($handle)) !== false) {
			// Pad or trim data to match header count
			if (count($data) < count($headers)) {
				$data = array_pad($data, count($headers), '');
			} else if (count($data) > count($headers)) {
				$data = array_slice($data, 0, count($headers));
			}
            
			$row = array_combine($headers, $data);
			$rows[] = $row;
		}
		fclose($handle);
	}
	return $rows;
}

function writeCSV($filename, $data, $schema) {
	// ✅ BACKUP: Create backup before modifying
	if (file_exists($filename) && function_exists('createBackup')) {
		createBackup($filename);
	}
    
	$file = fopen($filename, 'w');
	if ($file === false) throw new Exception("Unable to open file for writing: $filename");

	// ✅ Write header row first
	fputcsv($file, $schema);

	foreach ($data as $row) {
		$csvRow = [];
		foreach ($schema as $field) {
			$value = $row[$field] ?? '';
			// Prevent CSV injection
			if (preg_match('/^[=+\-@]/', $value)) {
				$value = "'" . $value;
			}
			$csvRow[] = $value;
		}
		fputcsv($file, $csvRow);
	}

	fclose($file);
}

function appendCSV($filename, $row) {
	$file = fopen($filename, 'a');
	if ($file === false) throw new Exception("Unable to open file for writing: $filename");
	fputcsv($file, $row);
	fclose($file);
}

// ...additional helper functions can be added as needed...