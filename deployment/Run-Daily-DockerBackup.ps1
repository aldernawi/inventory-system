[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [ValidateRange(1, 3650)][int]$RetentionCount = 30
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$envFile = Join-Path $ProjectRoot '.env.docker'
$clientScript = Join-Path $ProjectRoot 'deployment\Client-Operations.ps1'
$retentionScript = Join-Path $ProjectRoot 'deployment\Remove-OldInventoryBackups.ps1'
$backupDirectory = Join-Path $ProjectRoot 'backups'
$logDirectory = Join-Path $backupDirectory 'logs'

if (-not (Test-Path -LiteralPath $envFile -PathType Leaf)) { throw 'Daily backup requires the final .env.docker file.' }
if (-not (Test-Path -LiteralPath $clientScript -PathType Leaf)) { throw 'Client backup implementation is missing.' }

$database = (Get-Content -LiteralPath $envFile | Where-Object { $_ -match '^MYSQL_DATABASE=' } | Select-Object -First 1) -replace '^MYSQL_DATABASE=', ''
$database = $database.Trim().Trim('"').Trim("'")
if ($database -cne 'inventory_system') { throw 'Daily backup is restricted to the final inventory_system Docker environment.' }

New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
$logPath = Join-Path $logDirectory ('daily-backup-{0}.log' -f (Get-Date -Format 'yyyyMMdd'))

try {
    & $clientScript -Action Backup -EnvironmentFile $envFile -ComposeProject 'inventory_client' -NoBrowser | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'The Docker backup command failed.' }
    & $retentionScript -BackupDirectory $backupDirectory -Database $database -RetentionCount $RetentionCount | Out-Null
    "$(Get-Date -Format o) SUCCESS database=$database retention=$RetentionCount" | Add-Content -LiteralPath $logPath -Encoding UTF8
}
catch {
    "$(Get-Date -Format o) FAILURE $($_.Exception.Message)" | Add-Content -LiteralPath $logPath -Encoding UTF8
    throw
}
