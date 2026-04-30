function importCSVWithSchema($filename, $schema, $keyField, $existingRows) {
    $newRows = [];
    $imported = readCSV($filename);
    foreach ($imported as $row) {
        if (!array_diff($schema, array_keys($row)) && !in_array($row[$keyField], array_column($existingRows, $keyField))) {
            $newRows[] = $row;
        }
    }
    return $newRows;
}
