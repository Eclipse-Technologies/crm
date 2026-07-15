<?php

require_once __DIR__ . '/../db_mysql.php';

$failures = [];

function smokeAssert(bool $condition, string $message, array &$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
}

$placeholderPairs = [
    ['DB_HOST', '<runtime_db_host>'],
    ['DB_NAME', '<runtime_db_name>'],
    ['DB_USER', '<runtime_db_user>'],
    ['DB_PASSWORD', '<runtime_db_password>'],
    ['PROD_DB_HOST', '<prod_db_host>'],
    ['PROD_DB_NAME', '<prod_db_name>'],
    ['PROD_DB_USER', '<prod_db_user>'],
    ['PROD_DB_PASSWORD', '<prod_db_password>'],
];

foreach ($placeholderPairs as [$name, $value]) {
    putenv($name . '=' . $value);
    smokeAssert(crm_get_env($name) === '', 'Expected placeholder env value for ' . $name . ' to be ignored', $failures);
}

putenv('DB_HOST=localhost');
putenv('DB_NAME=crmdb');
putenv('DB_USER=root');
putenv('DB_PASSWORD=secret');

smokeAssert(crm_get_env('DB_HOST') === 'localhost', 'Expected non-placeholder DB_HOST to be preserved', $failures);
smokeAssert(crm_get_env('DB_NAME') === 'crmdb', 'Expected non-placeholder DB_NAME to be preserved', $failures);
smokeAssert(crm_get_env('DB_USER') === 'root', 'Expected non-placeholder DB_USER to be preserved', $failures);
smokeAssert(crm_get_env('DB_PASSWORD') === 'secret', 'Expected non-placeholder DB_PASSWORD to be preserved', $failures);

if (!empty($failures)) {
    echo 'FAILED (' . count($failures) . '):' . PHP_EOL;
    foreach ($failures as $failure) {
        echo '- ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo 'PASS: DB env placeholder handling is correct.' . PHP_EOL;
exit(0);
