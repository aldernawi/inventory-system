[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateScript({ Test-Path -LiteralPath $_ -PathType Leaf })]
    [string]$BackupPath,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_]+$')]
    [string]$TargetDatabase,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_-]+$')]
    [string]$ComposeProject,

    [Parameter(Mandatory = $true)]
    [ValidateScript({ Test-Path -LiteralPath $_ -PathType Leaf })]
    [string]$EnvironmentFile,

    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string[]]$ComposeFiles,

    [Parameter(Mandatory = $true)]
    [string]$ConfirmationPhrase,

    [string]$SafetyBackupDirectory = (Join-Path (Split-Path -Parent $PSScriptRoot) 'backups')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Assert-BackupStructure {
    param([Parameter(Mandatory = $true)][string]$Path)

    if ((Get-Item -LiteralPath $Path).Length -le 0) {
        throw "The selected backup is empty: $Path"
    }

    $hasCreateTable = Select-String -LiteralPath $Path -Pattern '^CREATE TABLE ' -Quiet
    $hasInsert = Select-String -LiteralPath $Path -Pattern '^INSERT INTO ' -Quiet

    if (-not $hasCreateTable -or -not $hasInsert) {
        throw "The selected backup does not contain expected schema and data statements: $Path"
    }
}

function Invoke-Compose {
    param([Parameter(Mandatory = $true)][string[]]$Arguments)

    $output = & docker compose @script:composeArguments @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "docker compose $($Arguments -join ' ') failed with exit code $LASTEXITCODE."
    }

    return $output
}

$requiredConfirmation = "RESTORE $TargetDatabase"

if ($ConfirmationPhrase -cne $requiredConfirmation) {
    throw "Restore confirmation must exactly equal '$requiredConfirmation'."
}

Assert-BackupStructure -Path $BackupPath

$databaseHeader = Select-String -LiteralPath $BackupPath -Pattern '^CREATE DATABASE.*?`(?<database>[A-Za-z0-9_]+)`' | Select-Object -First 1

if ($null -eq $databaseHeader) {
    throw 'The selected backup does not contain a MySQL CREATE DATABASE statement.'
}

$sourceDatabase = $databaseHeader.Matches[0].Groups['database'].Value

$script:composeArguments = @('-p', $ComposeProject, '--env-file', $EnvironmentFile)

foreach ($composeFile in $ComposeFiles) {
    $script:composeArguments += @('-f', $composeFile)
}

$containerId = (Invoke-Compose -Arguments @('ps', '-q', 'db') | Select-Object -First 1).Trim()

if ([string]::IsNullOrWhiteSpace($containerId)) {
    throw 'The target Docker database container is not running.'
}

New-Item -ItemType Directory -Path $SafetyBackupDirectory -Force | Out-Null

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$safetyBackupPath = Join-Path $SafetyBackupDirectory ("safety-{0}-{1}.sql" -f $TargetDatabase, $timestamp)
$containerSafetyPath = '/tmp/restore-safety.sql'
$containerRestorePath = '/tmp/restore-selected.sql'

$safetyDumpCommand = 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction --quick --skip-lock-tables --default-character-set=utf8mb4 --set-charset --routines --events --triggers --hex-blob --no-tablespaces --column-statistics=0 --set-gtid-purged=OFF --result-file=/tmp/restore-safety.sql --databases "$MYSQL_DATABASE"'
Invoke-Compose -Arguments @('exec', '-T', 'db', 'sh', '-lc', $safetyDumpCommand) | Out-Null

& docker cp ("{0}:{1}" -f $containerId, $containerSafetyPath) $safetyBackupPath

if ($LASTEXITCODE -ne 0) {
    throw "The disposable target safety backup could not be copied to $safetyBackupPath. Restore was not attempted."
}

Assert-BackupStructure -Path $safetyBackupPath
$safetyBackupBytes = (Get-Item -LiteralPath $safetyBackupPath).Length

& docker cp $BackupPath ("{0}:{1}" -f $containerId, $containerRestorePath)

if ($LASTEXITCODE -ne 0) {
    throw "The selected backup could not be copied into the target container. Restore was not attempted."
}

if ($sourceDatabase -eq $TargetDatabase) {
    $restoreCommand = 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --default-character-set=utf8mb4 -u root < /tmp/restore-selected.sql'
    Invoke-Compose -Arguments @('exec', '-T', 'db', 'sh', '-lc', $restoreCommand) | Out-Null
}
else {
    $restoreCommand = 'sed -e "s/$SOURCE_DATABASE/$TARGET_DATABASE/g" /tmp/restore-selected.sql | MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --default-character-set=utf8mb4 -u root'
    Invoke-Compose -Arguments @('exec', '-T', '-e', "SOURCE_DATABASE=$sourceDatabase", '-e', "TARGET_DATABASE=$TargetDatabase", 'db', 'sh', '-lc', $restoreCommand) | Out-Null
}
$importExitCode = 0

$connectionQueryPath = Join-Path ([System.IO.Path]::GetTempPath()) ("restore-connection-{0}.sql" -f [guid]::NewGuid().ToString('N'))
try {
    Set-Content -LiteralPath $connectionQueryPath -Value 'SELECT 1;' -Encoding ASCII -NoNewline
    & docker cp $connectionQueryPath ("{0}:/tmp/restore-connection-check.sql" -f $containerId)
    if ($LASTEXITCODE -ne 0) { throw 'The post-restore connection query could not be copied into the target container.' }
    $connectionResult = Invoke-Compose -Arguments @('exec', '-T', 'db', 'sh', '-lc', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -D "$MYSQL_DATABASE" -N < /tmp/restore-connection-check.sql')
}
finally {
    Remove-Item -LiteralPath $connectionQueryPath -Force -ErrorAction SilentlyContinue
}
$databaseConnectionVerified = (($connectionResult -join "`n").Trim() -eq '1')

if (-not $databaseConnectionVerified) {
    throw 'The target database did not pass its post-restore connection check.'
}

$migrationOutput = Invoke-Compose -Arguments @('exec', '-T', 'app', 'php', 'artisan', 'migrate:status', '--no-ansi')
$migrationsVerified = ($migrationOutput -join "`n") -notmatch '\[No\]'

if (-not $migrationsVerified) {
    throw 'The Laravel migration status contains pending migrations after restore.'
}

[pscustomobject]@{
    SourceDatabase = $sourceDatabase
    TargetDatabase = $TargetDatabase
    SafetyBackupPath = (Resolve-Path -LiteralPath $safetyBackupPath).Path
    SafetyBackupBytes = $safetyBackupBytes
    ImportExitCode = $importExitCode
    DatabaseConnectionVerified = $databaseConnectionVerified
    MigrationsVerified = $migrationsVerified
}
