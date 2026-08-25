[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [Parameter(Mandatory = $true)][string]$BackupDirectory,
    [Parameter(Mandatory = $true)][ValidatePattern('^[A-Za-z0-9_]+$')][string]$Database,
    [ValidateRange(1, 3650)][int]$RetentionCount = 30
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $BackupDirectory -PathType Container)) { return @() }

$pattern = '{0}-????????-??????.sql' -f $Database
$backups = @(Get-ChildItem -LiteralPath $BackupDirectory -File -Filter $pattern | Sort-Object LastWriteTimeUtc -Descending)
$expired = @($backups | Select-Object -Skip $RetentionCount)

foreach ($backup in $expired) {
    if ($PSCmdlet.ShouldProcess($backup.FullName, 'Remove expired verified inventory backup')) {
        Remove-Item -LiteralPath $backup.FullName -Force
        Write-Host "Removed expired backup: $($backup.Name)"
    }
}

return $expired.FullName
