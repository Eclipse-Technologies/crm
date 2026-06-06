param(
    [switch]$SkipLint
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$smokeTestFile = Join-Path $root 'tests\TaskTransactionAuditUsageSmokeTest.php'

if (-not (Test-Path $smokeTestFile)) {
    throw "Smoke test file not found: $smokeTestFile"
}

Push-Location $root
try {
    if (-not $SkipLint) {
        php -l $smokeTestFile
        if ($LASTEXITCODE -ne 0) {
            throw 'PHP lint failed for TaskTransactionAuditUsageSmokeTest.php'
        }
    }

    php $smokeTestFile
    if ($LASTEXITCODE -ne 0) {
        throw 'Task transaction audit usage smoke test failed'
    }

    Write-Host 'Task transaction audit smoke wrapper completed successfully.'
}
finally {
    Pop-Location
}
