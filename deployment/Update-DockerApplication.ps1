[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [switch]$DryRun
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$envFile = Join-Path $ProjectRoot '.env.docker'
$client = Join-Path $ProjectRoot 'deployment\Client-Operations.ps1'
if (-not (Test-Path -LiteralPath $envFile)) { throw 'Update requires the final .env.docker configuration.' }

$dirty = @(git -C $ProjectRoot status --porcelain)
if ($dirty.Count -gt 0) { throw 'Update stopped: Git working tree has local changes. Commit or resolve them; nothing was changed.' }
if ($DryRun) { Write-Host 'Dry run passed: final environment exists and Git working tree is clean. No backup, build, migration, or restart was run.'; return }

& $client -Action Backup -EnvironmentFile $envFile -ComposeProject inventory_client -NoBrowser | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Update stopped because the verified backup failed.' }

$compose = @('-p', 'inventory_client', '--env-file', $envFile, '-f', (Join-Path $ProjectRoot 'docker-compose.yml'))
$pending = & docker compose @compose exec -T app php artisan migrate:status --no-ansi
if ($LASTEXITCODE -ne 0) { throw 'Update stopped because migration status could not be read.' }
$pending | Select-String 'Pending' | ForEach-Object { Write-Host $_ }
& docker compose @compose build app
if ($LASTEXITCODE -ne 0) { throw 'Update stopped because the application image build failed.' }
& docker compose @compose up -d
if ($LASTEXITCODE -ne 0) { throw 'Update stopped because services could not be started.' }
if (($pending -join "`n") -match 'Pending') {
    & docker compose @compose exec -T app php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'Update migration failed. The backup was retained; do not retry blindly.' }
}
foreach ($command in @('config:cache', 'route:cache', 'view:cache')) {
    & docker compose @compose exec -T app php artisan $command
    if ($LASTEXITCODE -ne 0) { throw "Update stopped because $command failed." }
}
if ((Invoke-WebRequest 'http://localhost:8080/up' -UseBasicParsing).StatusCode -ne 200 -or (Invoke-WebRequest 'http://localhost:8080/login' -UseBasicParsing).StatusCode -ne 200) { throw 'Update stopped because HTTP health verification failed.' }
Write-Host 'Update completed successfully.'
