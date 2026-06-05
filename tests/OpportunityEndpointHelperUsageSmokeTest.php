<?php

$failures = [];

function usageSmokeAssert(bool $condition, string $message, array &$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
}

function printSection(string $title): void {
    echo PHP_EOL . '== ' . $title . ' ==' . PHP_EOL;
}

$targets = [
    'admin_bulk_ops.php',
    'delete_opportunity.php',
    'update_opportunity_inline.php',
    'pipeline_board.php',
    'edit_opportunity.php',
];

printSection('Endpoint Helper Usage');

foreach ($targets as $target) {
    $path = __DIR__ . '/../' . $target;
    usageSmokeAssert(file_exists($path), 'Target file missing: ' . $target, $failures);
    if (!file_exists($path)) {
        continue;
    }

    $content = file_get_contents($path);
    usageSmokeAssert($content !== false, 'Could not read target file: ' . $target, $failures);
    if ($content === false) {
        continue;
    }

    usageSmokeAssert(
        strpos($content, 'adminOpportunityIdColumn(') !== false,
        'Expected adminOpportunityIdColumn() usage in ' . $target,
        $failures
    );

    usageSmokeAssert(
        strpos($content, 'function getOpportunityIdColumn') === false,
        'Legacy local getOpportunityIdColumn() helper should not exist in ' . $target,
        $failures
    );

    usageSmokeAssert(
        strpos($content, "SHOW COLUMNS FROM opportunities LIKE 'opportunity_id'") === false,
        'Legacy hardcoded SHOW COLUMNS probe found in ' . $target,
        $failures
    );

    usageSmokeAssert(
        strpos($content, 'require_once __DIR__ . \'/admin_sql_helper.php\';') !== false
            || strpos($content, 'require_once "admin_sql_helper.php";') !== false
            || strpos($content, "require_once 'admin_sql_helper.php';") !== false,
        'Expected admin_sql_helper include in ' . $target,
        $failures
    );
}

echo 'Endpoint helper usage checks complete.' . PHP_EOL;

printSection('Result');

if (!empty($failures)) {
    echo 'FAILED (' . count($failures) . '):' . PHP_EOL;
    foreach ($failures as $failure) {
        echo '- ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo 'PASS: Endpoint helper usage smoke checks succeeded.' . PHP_EOL;
exit(0);
