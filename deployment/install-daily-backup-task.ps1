[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [ValidatePattern('^([01]\d|2[0-3]):[0-5]\d$')][string]$Time = '20:00',
    [ValidateRange(1, 3650)][int]$RetentionCount = 30,
    [string]$TaskName = 'Inventory System Daily Backup'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$runner = Join-Path $ProjectRoot 'deployment\Run-Daily-DockerBackup.ps1'
if (-not (Test-Path -LiteralPath $runner -PathType Leaf)) { throw 'Daily backup runner is missing.' }

$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy Bypass -File "{0}" -ProjectRoot "{1}" -RetentionCount {2}' -f $runner, $ProjectRoot, $RetentionCount)
$trigger = New-ScheduledTaskTrigger -Daily -At ([DateTime]::ParseExact($Time, 'HH:mm', $null))
$principal = New-ScheduledTaskPrincipal -UserId ("{0}\{1}" -f $env:USERDOMAIN, $env:USERNAME) -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Description 'Verified daily Docker backup for the final Inventory System environment.' -Force | Out-Null
Write-Host "Installed '$TaskName' for $Time. It runs while this Windows user is logged in."
