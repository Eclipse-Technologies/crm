<?php

require_once __DIR__ . '/../admin_sql_helper.php';
require_once __DIR__ . '/../db_mysql.php';

$failures = [];
$skipDbChecks = in_array(strtolower((string) getenv('ADMIN_SQL_SMOKE_SKIP_DB')), ['1', 'true', 'yes'], true);

function smokeAssert(bool $condition, string $message, array &$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
}

function printSection(string $title): void {
    echo PHP_EOL . '== ' . $title . ' ==' . PHP_EOL;
}

printSection('Predicate Builder');

$clause = adminNormalizedIdExistsClause('c.customer_id', 'customers', 'customer_id', 'cu');
smokeAssert(strpos($clause, 'FROM customers cu') !== false, 'Expected lookup table/alias in clause', $failures);
smokeAssert(strpos($clause, 'cu.customer_id = c.customer_id') !== false, 'Expected exact match branch in clause', $failures);
smokeAssert(strpos($clause, 'CAST(cu.customer_id AS UNSIGNED) = CAST(c.customer_id AS UNSIGNED)') !== false, 'Expected numeric-equivalent branch in clause', $failures);

$invalidCases = [
    ['source' => 'c.customer_id;DROP', 'table' => 'customers', 'column' => 'customer_id', 'alias' => 'cu'],
    ['source' => 'c.customer_id', 'table' => 'customers;DROP', 'column' => 'customer_id', 'alias' => 'cu'],
    ['source' => 'c.customer_id', 'table' => 'customers', 'column' => 'customer-id', 'alias' => 'cu'],
    ['source' => 'c.customer_id', 'table' => 'customers', 'column' => 'customer_id', 'alias' => 'cu-1'],
];

foreach ($invalidCases as $case) {
    $threw = false;
    try {
        adminNormalizedIdExistsClause($case['source'], $case['table'], $case['column'], $case['alias']);
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }
    smokeAssert($threw, 'Expected InvalidArgumentException for invalid clause input: ' . json_encode($case), $failures);
}

echo 'Predicate builder checks complete.' . PHP_EOL;

printSection('Schema Helpers (DB-backed)');

if ($skipDbChecks) {
    echo 'DB checks skipped (ADMIN_SQL_SMOKE_SKIP_DB enabled).' . PHP_EOL;
} else {
    $conn = get_mysql_connection();
    smokeAssert($conn instanceof mysqli, 'Expected MySQL connection object', $failures);

    $idColumn = adminOpportunityIdColumn($conn);
    smokeAssert(in_array($idColumn, ['opportunity_id', 'id'], true), 'Expected opportunity id column to be id or opportunity_id', $failures);

    echo 'Detected opportunities key column: ' . $idColumn . PHP_EOL;

    $hasOppTableIdColumn = adminTableHasColumn($conn, 'opportunities', $idColumn);
    smokeAssert($hasOppTableIdColumn === true, 'Expected opportunities table to have detected key column', $failures);

    $knownMissing = adminTableHasColumn($conn, 'opportunities', '__definitely_missing_column__');
    smokeAssert($knownMissing === false, 'Expected missing column probe to return false', $failures);

    $conn->close();
    echo 'Schema helper checks complete.' . PHP_EOL;
}

printSection('Result');

if (!empty($failures)) {
    echo 'FAILED (' . count($failures) . '):' . PHP_EOL;
    foreach ($failures as $failure) {
        echo '- ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo 'PASS: Admin SQL helper smoke checks succeeded.' . PHP_EOL;
exit(0);
