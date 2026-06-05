param(
    [switch]$SkipLint,
    [switch]$SkipDb
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$helperTestFile = Join-Path $root 'tests\AdminSqlHelperSmokeTest.php'
$endpointTestFile = Join-Path $root 'tests\OpportunityEndpointHelperUsageSmokeTest.php'

if (-not (Test-Path $helperTestFile)) {
    throw "Smoke test file not found: $helperTestFile"
}

if (-not (Test-Path $endpointTestFile)) {
    throw "Smoke test file not found: $endpointTestFile"
}

Push-Location $root
$prevSkipDb = $env:ADMIN_SQL_SMOKE_SKIP_DB
try {
    if ($SkipDb) {
        $env:ADMIN_SQL_SMOKE_SKIP_DB = '1'
    } else {
        Remove-Item Env:ADMIN_SQL_SMOKE_SKIP_DB -ErrorAction SilentlyContinue
    }

    if (-not $SkipLint) {
        php -l $helperTestFile
        if ($LASTEXITCODE -ne 0) {
            throw 'PHP lint failed for AdminSqlHelperSmokeTest.php'
        }

        php -l $endpointTestFile
        if ($LASTEXITCODE -ne 0) {
            throw 'PHP lint failed for OpportunityEndpointHelperUsageSmokeTest.php'
        }
    }

    php $helperTestFile
    if ($LASTEXITCODE -ne 0) {
        throw 'Admin SQL helper smoke test failed'
    }

    php $endpointTestFile
    if ($LASTEXITCODE -ne 0) {
        throw 'Opportunity endpoint helper usage smoke test failed'
    }

    Write-Host 'Smoke test wrapper completed successfully.'
}
finally {
    if ($null -ne $prevSkipDb) {
        $env:ADMIN_SQL_SMOKE_SKIP_DB = $prevSkipDb
    } else {
        Remove-Item Env:ADMIN_SQL_SMOKE_SKIP_DB -ErrorAction SilentlyContinue
    }
    Pop-Location
}
