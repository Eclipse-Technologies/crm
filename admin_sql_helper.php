<?php

function adminTableHasColumn(mysqli $conn, string $table, string $column): bool {
    $safeTable = str_replace('`', '``', $table);
    $safeColumn = str_replace('`', '``', $column);
    $sql = "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'";
    $result = $conn->query($sql);
    if (!$result) {
        return false;
    }

    $has = $result->num_rows > 0;
    $result->free();
    return $has;
}

function adminOpportunityIdColumn(mysqli $conn): string {
    if (adminTableHasColumn($conn, 'opportunities', 'opportunity_id')) {
        return 'opportunity_id';
    }
    return adminTableHasColumn($conn, 'opportunities', 'id') ? 'id' : 'opportunity_id';
}

function adminNormalizedIdExistsClause(string $sourceExpr, string $lookupTable, string $lookupColumn, string $lookupAlias = 'lk'): string {
    $identifierPattern = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    $columnExprPattern = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    if (!preg_match($columnExprPattern, $sourceExpr)) {
        throw new InvalidArgumentException('Invalid source expression for normalized ID clause');
    }

    if (!preg_match($identifierPattern, $lookupTable)
        || !preg_match($identifierPattern, $lookupColumn)
        || !preg_match($identifierPattern, $lookupAlias)) {
        throw new InvalidArgumentException('Invalid lookup identifier for normalized ID clause');
    }

    return "EXISTS (
        SELECT 1
        FROM {$lookupTable} {$lookupAlias}
        WHERE {$lookupAlias}.{$lookupColumn} = {$sourceExpr}
           OR (
                {$sourceExpr} REGEXP '^[0-9]+$'
            AND {$lookupAlias}.{$lookupColumn} REGEXP '^[0-9]+$'
            AND CAST({$lookupAlias}.{$lookupColumn} AS UNSIGNED) = CAST({$sourceExpr} AS UNSIGNED)
           )
    )";
}
