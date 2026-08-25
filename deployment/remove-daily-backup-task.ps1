[CmdletBinding()]
param([string]$TaskName = 'Inventory System Daily Backup')

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction Stop
Write-Host "Removed '$TaskName'."
