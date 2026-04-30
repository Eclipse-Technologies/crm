<?php
function injectIdsIntoCsv($inputFile, $outputFile, $idPrefix = 'CNT') {
    $rows = array_map('str_getcsv', file($inputFile));
    $header = $rows[0];

    // Add ID column if missing
    if (!in_array('id', $header)) {
        array_unshift($header, 'id');
    }

    $newRows = [$header];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $id = $idPrefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
        array_unshift($row, $id);
        $newRows[] = $row;
    }

    // Write to output file
    $fp = fopen($outputFile, 'w');
    foreach ($newRows as $fields) {
        fputcsv($fp, $fields);
    }
    fclose($fp);
}
