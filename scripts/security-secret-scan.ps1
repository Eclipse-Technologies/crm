param(
    [switch]$IncludeUntracked
)

$ErrorActionPreference = 'Stop'

$patterns = @(
    '(?i)\bDB_PASSWORD\s*=\s*[^\s#]+',
    '(?i)\bPROD_DB_PASSWORD\s*=\s*[^\s#]+',
    '(?i)\bSMTP_PASSWORD\s*=\s*[^\s#]+',
    '(?i)\bAPI_KEYS\s*=\s*[^\s#]+',
    '(?i)\bOPENAI_API_KEY\s*=\s*[^\s#]+',
    '(?i)\bANTHROPIC_API_KEY\s*=\s*[^\s#]+',
    '(?i)\bGRAPH_CLIENT_SECRET\s*=\s*[^\s#]+',
    '(?i)\bAWS_SECRET_ACCESS_KEY\b',
    '(?i)BEGIN\s+RSA\s+PRIVATE\s+KEY',
    '(?i)BEGIN\s+PRIVATE\s+KEY',
    '(?i)x-api-key\s*:\s*[A-Za-z0-9_\-]{20,}'
)

$excludeGlobs = @(
    '^vendor/',
    '^node_modules/',
    '^\.git/',
    '^\.venv/',
    '^DEPRICATED/'
)

$allowedExtensions = @(
    '.php', '.env', '.runtime', '.ini', '.json', '.yml', '.yaml', '.md', '.txt', '.sql', '.ps1', '.bat', '.xml', '.htaccess', '.config'
)

function Test-ExcludedPath {
    param([string]$Path)
    foreach ($glob in $excludeGlobs) {
        if ($Path -match $glob) {
            return $true
        }
    }
    return $false
}

$root = (Get-Location).Path
$tracked = git ls-files
if ($LASTEXITCODE -ne 0) {
    throw 'Unable to read tracked files from git.'
}

$files = @($tracked)
if ($IncludeUntracked) {
    $untracked = git ls-files --others --exclude-standard
    if ($LASTEXITCODE -eq 0 -and $untracked) {
        $files += $untracked
    }
}

$files = $files |
    Where-Object { $_ -and -not (Test-ExcludedPath $_) } |
    Where-Object {
        $ext = [System.IO.Path]::GetExtension($_).ToLowerInvariant()
        if ($ext -ne '') {
            return $allowedExtensions -contains $ext
        }
        $name = [System.IO.Path]::GetFileName($_)
        return $name -eq '.htaccess'
    } |
    Sort-Object -Unique

$findings = @()
foreach ($file in $files) {
    $absPath = Join-Path $root $file
    if (-not (Test-Path -LiteralPath $absPath)) {
        continue
    }

    $lineNumber = 0
    Get-Content -LiteralPath $absPath | ForEach-Object {
        $lineNumber++
        $line = $_
        foreach ($pattern in $patterns) {
            if ($line -match $pattern) {
                if ($line -match '^\s*#') {
                    continue
                }
                if ($line -match 'replace_with_|<.*>|changeme|your_') {
                    continue
                }
                $findings += [PSCustomObject]@{
                    File = $file
                    Line = $lineNumber
                    Pattern = $pattern
                    Snippet = $line
                }
                break
            }
        }
    }
}

if ($findings.Count -gt 0) {
    Write-Host 'Potential secret exposures detected:' -ForegroundColor Red
    $findings | ForEach-Object {
        Write-Host (" - {0}:{1}" -f $_.File, $_.Line) -ForegroundColor Yellow
    }
    Write-Host ''
    Write-Host 'Failing scan. Review files and rotate any exposed credentials.' -ForegroundColor Red
    exit 1
}

Write-Host 'Secret scan passed. No obvious credentials found in scanned files.' -ForegroundColor Green
exit 0
