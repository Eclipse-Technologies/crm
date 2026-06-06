<?php

$failures = [];

function taskAuditSmokeAssert(bool $condition, string $message, array &$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
}

function printSection(string $title): void {
    echo PHP_EOL . '== ' . $title . ' ==' . PHP_EOL;
}

$targets = [
    [
        'file' => 'add_task.php',
        'expectCalls' => 2,
    ],
    [
        'file' => 'edit_task.php',
        'expectCalls' => 2,
    ],
    [
        'file' => 'delete_task.php',
        'expectCalls' => 3,
    ],
    [
        'file' => 'archive_task.php',
        'expectCalls' => 3,
    ],
    [
        'file' => 'update_task_status.php',
        'expectCalls' => 2,
    ],
];

printSection('Task Transaction Audit Hook Usage');

foreach ($targets as $target) {
    $file = $target['file'];
    $path = __DIR__ . '/../' . $file;

    taskAuditSmokeAssert(file_exists($path), 'Target file missing: ' . $file, $failures);
    if (!file_exists($path)) {
        continue;
    }

    $content = file_get_contents($path);
    taskAuditSmokeAssert($content !== false, 'Could not read target file: ' . $file, $failures);
    if ($content === false) {
        continue;
    }

    taskAuditSmokeAssert(
        strpos($content, "require_once __DIR__ . '/audit_handler.php';") !== false,
        'Expected audit_handler include in ' . $file,
        $failures
    );

    $callCount = preg_match_all('/\\blogAuditAction\\s*\\(/', $content, $matches);
    taskAuditSmokeAssert(
        is_int($callCount) && $callCount >= (int) $target['expectCalls'],
        'Expected at least ' . (int) $target['expectCalls'] . ' logAuditAction() calls in ' . $file . ', found ' . (int) $callCount,
        $failures
    );

    taskAuditSmokeAssert(
        strpos($content, "'task'") !== false,
        'Expected task entity logging in ' . $file,
        $failures
    );
}

echo 'Task transaction audit usage checks complete.' . PHP_EOL;

printSection('Result');

if (!empty($failures)) {
    echo 'FAILED (' . count($failures) . '):' . PHP_EOL;
    foreach ($failures as $failure) {
        echo '- ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo 'PASS: Task transaction audit usage smoke checks succeeded.' . PHP_EOL;
exit(0);
