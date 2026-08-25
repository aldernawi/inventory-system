[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateScript({ Test-Path -LiteralPath $_ -PathType Leaf })]
    [string]$EnvFile,

    [Parameter(Mandatory = $true)]
    [ValidateScript({ Test-Path -LiteralPath $_ -PathType Leaf })]
    [string]$MySqlDumpPath,

    [string]$OutputDirectory = (Join-Path (Split-Path -Parent $PSScriptRoot) 'backups')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-EnvironmentFileValues {
    param([Parameter(Mandatory = $true)][string]$Path)

    $values = @{}

    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()

        if ([string]::IsNullOrWhiteSpace($trimmed) -or $trimmed.StartsWith('#')) {
            continue
        }

        $match = [regex]::Match($trimmed, '^(?<key>[A-Za-z_][A-Za-z0-9_]*)=(?<value>.*)$')

        if (-not $match.Success) {
            continue
        }

        $value = $match.Groups['value'].Value.Trim()

        if ($value.Length -ge 2 -and (
                ($value.StartsWith('"') -and $value.EndsWith('"')) -or
                ($value.StartsWith("'") -and $value.EndsWith("'"))
            )) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $values[$match.Groups['key'].Value] = $value
    }

    return $values
}

function ConvertTo-QuotedArgument {
    param([Parameter(Mandatory = $true)][string]$Value)

    return '"{0}"' -f ($Value -replace '"', '\"')
}

$environment = Get-EnvironmentFileValues -Path $EnvFile
$requiredKeys = @('DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME')

foreach ($key in $requiredKeys) {
    if (-not $environment.ContainsKey($key) -or [string]::IsNullOrWhiteSpace($environment[$key])) {
        throw "The environment file does not define a usable $key value."
    }
}

$database = $environment['DB_DATABASE']
$hostName = $environment['DB_HOST']
$port = $environment['DB_PORT']
$username = $environment['DB_USERNAME']
$password = if ($environment.ContainsKey('DB_PASSWORD')) { $environment['DB_PASSWORD'] } else { '' }

if ($database -notmatch '^[A-Za-z0-9_]+$') {
    throw 'DB_DATABASE must contain only letters, numbers, and underscores.'
}

if ($hostName -notmatch '^[A-Za-z0-9.-]+$') {
    throw 'DB_HOST contains unsupported characters.'
}

if ($port -notmatch '^\d{1,5}$' -or [int]$port -lt 1 -or [int]$port -gt 65535) {
    throw 'DB_PORT must be a valid TCP port.'
}

New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupPath = Join-Path $OutputDirectory ("{0}-{1}.sql" -f $database, $timestamp)
$stderrPath = "$backupPath.stderr.log"
$arguments = @(
    "--host=$hostName",
    "--port=$port",
    "--user=$username",
    '--single-transaction',
    '--quick',
    '--skip-lock-tables',
    '--default-character-set=utf8mb4',
    '--set-charset',
    '--routines',
    '--events',
    '--triggers',
    '--hex-blob',
    '--no-tablespaces',
    '--column-statistics=0',
    '--set-gtid-purged=OFF',
    "--result-file=$backupPath",
    '--databases',
    $database
)

$processInfo = New-Object System.Diagnostics.ProcessStartInfo
$processInfo.FileName = $MySqlDumpPath
$processInfo.Arguments = (($arguments | ForEach-Object { ConvertTo-QuotedArgument -Value $_ }) -join ' ')
$processInfo.UseShellExecute = $false
$processInfo.RedirectStandardError = $true
$processInfo.CreateNoWindow = $true

$hadMySqlPassword = Test-Path Env:MYSQL_PWD
$originalMySqlPassword = if ($hadMySqlPassword) { $env:MYSQL_PWD } else { $null }

try {
    if ([string]::IsNullOrEmpty($password)) {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
    else {
        $env:MYSQL_PWD = $password
    }

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $processInfo

    if (-not $process.Start()) {
        throw 'mysqldump could not be started.'
    }

    $standardError = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    $exitCode = $process.ExitCode
}
finally {
    if ($hadMySqlPassword) {
        $env:MYSQL_PWD = $originalMySqlPassword
    }
    else {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}

Set-Content -LiteralPath $stderrPath -Value $standardError -Encoding UTF8

if ($exitCode -ne 0) {
    throw "mysqldump failed with exit code $exitCode. Standard error was saved to $stderrPath."
}

if (-not (Test-Path -LiteralPath $backupPath -PathType Leaf)) {
    throw "mysqldump exited successfully but did not create $backupPath."
}

$bytes = (Get-Item -LiteralPath $backupPath).Length

if ($bytes -le 0) {
    throw "mysqldump created an empty backup file at $backupPath."
}

$hasCreateTable = Select-String -LiteralPath $backupPath -Pattern '^CREATE TABLE ' -Quiet
$hasInsert = Select-String -LiteralPath $backupPath -Pattern '^INSERT INTO ' -Quiet

if (-not $hasCreateTable -or -not $hasInsert) {
    throw "The backup file at $backupPath does not contain the expected schema and data statements."
}

[pscustomobject]@{
    Database = $database
    Path = (Resolve-Path -LiteralPath $backupPath).Path
    Bytes = $bytes
    ExitCode = $exitCode
    HasCreateTable = $hasCreateTable
    HasInsert = $hasInsert
    StandardError = $standardError
}
