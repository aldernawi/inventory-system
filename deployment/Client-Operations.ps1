[CmdletBinding()]
param(
    [ValidateSet('Help', 'Install', 'Start', 'Stop', 'Restart', 'Backup', 'Restore', 'Logs')]
    [string]$Action = 'Help',
    [ValidateSet('Fresh', 'Existing')]
    [string]$Mode,
    [string]$EnvironmentFile,
    [string]$ComposeProject = 'inventory_client',
    [string]$ComposeOverride,
    [switch]$TestMode,
    [string]$ExpectedDatabaseName,
    [string]$ExpectedVolumeName,
    [Alias('no-pause')]
    [switch]$NoPause,
    [string]$BackupPath,
    [string]$ConfirmationPhrase,
    [switch]$NoBrowser,
    [switch]$SkipAdminCreation
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$backupsDirectory = Join-Path $projectRoot 'backups'

if ([string]::IsNullOrWhiteSpace($EnvironmentFile)) {
    $EnvironmentFile = Join-Path $projectRoot '.env.docker'
}

$composeFiles = @((Join-Path $projectRoot 'docker-compose.yml'))
if (-not [string]::IsNullOrWhiteSpace($ComposeOverride)) {
    $composeFiles += $ComposeOverride
}

function Write-ClientMessage([string]$Message) { Write-Host $Message }
function Get-ComposeArguments {
    $arguments = @('-p', $ComposeProject, '--env-file', $EnvironmentFile)
    foreach ($file in $composeFiles) { $arguments += @('-f', $file) }
    return $arguments
}
function Invoke-ClientCompose([string[]]$Arguments) {
    $composeArguments = Get-ComposeArguments
    $result = & docker compose @composeArguments @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Docker command failed: $($Arguments -join ' ')" }
    return $result
}
function Assert-Phase3Isolation {
    if (-not $TestMode) { return }
    if ([string]::IsNullOrWhiteSpace($ComposeOverride) -or -not (Test-Path -LiteralPath $ComposeOverride -PathType Leaf)) {
        throw 'Phase 3 test mode requires an explicit existing -ComposeOverride; normal Compose is never an allowed fallback.'
    }
    if ($ComposeProject -notmatch '^inventory_phase3_') {
        throw 'Phase 3 test mode requires a Compose project name beginning with inventory_phase3_.'
    }
    $values = Get-EnvValues $EnvironmentFile
    $database = $values['MYSQL_DATABASE']
    if ([string]::IsNullOrWhiteSpace($database) -or $database -notmatch '^inventory_phase3_') {
        throw 'Phase 3 test mode requires an isolated MYSQL_DATABASE beginning with inventory_phase3_. '
    }
    if ($ExpectedDatabaseName -and $database -cne $ExpectedDatabaseName) {
        throw "Phase 3 test database mismatch: expected $ExpectedDatabaseName but environment contains $database."
    }
    if ([string]::IsNullOrWhiteSpace($ExpectedVolumeName) -or $ExpectedVolumeName -notmatch '^inventory_phase3_') {
        throw 'Phase 3 test mode requires an explicit isolated -ExpectedVolumeName beginning with inventory_phase3_. '
    }
    $config = Invoke-ClientCompose @('config')
    $configText = $config -join "`n"
    if ($configText -notmatch [regex]::Escape("name: $ExpectedVolumeName")) {
        throw "Phase 3 Compose config does not resolve the expected isolated volume $ExpectedVolumeName."
    }
    if ($configText -match '(?m)^\s*name:\s*inventory_mysql_data\s*$') {
        throw 'Phase 3 Compose config resolved the normal inventory_mysql_data volume and was stopped.'
    }
    Write-ClientMessage "Phase 3 isolation verified: project=$ComposeProject database=$database volume=$ExpectedVolumeName containers=${ComposeProject}-app-1,${ComposeProject}-db-1"
}
function Get-EnvValues([string]$Path) {
    $values = @{}
    foreach ($line in Get-Content -LiteralPath $Path) {
        $match = [regex]::Match($line.Trim(), '^(?<key>[A-Za-z_][A-Za-z0-9_]*)=(?<value>.*)$')
        if ($match.Success) {
            $value = $match.Groups['value'].Value.Trim().Trim('"').Trim("'")
            $values[$match.Groups['key'].Value] = $value
        }
    }
    return $values
}
function New-RandomSecret([int]$Bytes = 24) {
    $rng = New-Object System.Security.Cryptography.RNGCryptoServiceProvider
    $data = New-Object byte[] $Bytes
    $rng.GetBytes($data)
    $rng.Dispose()
    return [Convert]::ToBase64String($data)
}
function Ensure-DockerEngine {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'تعذر العثور على Docker CLI. ثبّت Docker Desktop ثم حاول مرة أخرى.' }
    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    & docker info 2>$null | Out-Null
    $dockerReady = $LASTEXITCODE -eq 0
    $ErrorActionPreference = $previousErrorActionPreference
    if ($dockerReady) { return }
    $desktopPaths = @(
        @(
            (Join-Path $env:ProgramFiles 'Docker\Docker\Docker Desktop.exe'),
            (Join-Path $env:LOCALAPPDATA 'Docker\Docker Desktop.exe')
        ) | Where-Object { Test-Path -LiteralPath $_ }
    )
    if ($desktopPaths.Count -gt 0) { Start-Process -FilePath $desktopPaths[0] -WindowStyle Hidden }
    Write-ClientMessage 'جاري انتظار Docker...'
    for ($attempt = 1; $attempt -le 30; $attempt++) {
        Start-Sleep -Seconds 2
        $ErrorActionPreference = 'Continue'
        & docker info 2>$null | Out-Null
        $dockerReady = $LASTEXITCODE -eq 0
        $ErrorActionPreference = $previousErrorActionPreference
        if ($dockerReady) { return }
    }
    throw 'تعذر تشغيل Docker. تأكد من تشغيل Docker Desktop ثم حاول مرة أخرى.'
}
function Ensure-Environment([string]$InstallationMode) {
    if (Test-Path -LiteralPath $EnvironmentFile) { return }
    $template = Join-Path $projectRoot '.env.docker.example'
    $content = Get-Content -Raw -LiteralPath $template
    if ($InstallationMode -eq 'Existing') {
        $secureKey = Read-Host 'أدخل APP_KEY الحالي بدون طباعته' -AsSecureString
        $keyPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureKey)
        try { $appKey = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($keyPointer) }
        finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($keyPointer) }
        if ([string]::IsNullOrWhiteSpace($appKey)) { throw 'لا يمكن إعداد استيراد بيانات موجودة بدون APP_KEY الحالي.' }
    } else {
        $appKey = 'base64:' + (New-RandomSecret 32)
    }
    $content = $content.Replace('base64:REPLACE_WITH_THE_EXISTING_APP_KEY_OR_A_NEW_FRESH_INSTALL_KEY', $appKey)
    $content = $content.Replace('REPLACE_WITH_A_STRONG_APPLICATION_DATABASE_PASSWORD', (New-RandomSecret 24))
    $content = $content.Replace('REPLACE_WITH_A_DIFFERENT_STRONG_ROOT_DATABASE_PASSWORD', (New-RandomSecret 24))
    Set-Content -LiteralPath $EnvironmentFile -Value $content -Encoding UTF8 -NoNewline
    Write-ClientMessage 'تم إنشاء ملف إعدادات Docker المحلي. احتفظ به ولا تشاركه.'
}
function Wait-ServiceHealth([string]$Service, [int]$Attempts = 30) {
    for ($attempt = 1; $attempt -le $Attempts; $attempt++) {
        $id = (Invoke-ClientCompose @('ps', '-q', $Service) | Select-Object -First 1).Trim()
        if ($id) {
            $status = (& docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' $id).Trim()
            if ($status -eq 'healthy') { return }
        }
        Start-Sleep -Seconds 2
    }
    throw "تعذر جاهزية خدمة $Service. شغّل logs.bat للمساعدة."
}
function Test-ApplicationHealth {
    for ($attempt = 1; $attempt -le 20; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri 'http://localhost:8080/up' -UseBasicParsing -TimeoutSec 5
            if ($response.StatusCode -eq 200) { return }
        } catch { }
        Start-Sleep -Seconds 2
    }
    throw 'تعذر الوصول إلى صحة التطبيق. شغّل logs.bat للمساعدة.'
}
function Start-ClientSystem {
    Assert-Phase3Isolation
    Ensure-DockerEngine
    if (-not (Test-Path -LiteralPath $EnvironmentFile)) { throw 'ملف .env.docker غير موجود. شغّل install.bat أولاً.' }
    Write-ClientMessage 'تشغيل النظام...'
    Invoke-ClientCompose @('up', '-d') | Out-Null
    Write-ClientMessage 'جاري انتظار قاعدة البيانات...'
    Wait-ServiceHealth 'db'
    Write-ClientMessage 'جاري انتظار التطبيق...'
    Wait-ServiceHealth 'app'
    Test-ApplicationHealth
    if (-not $NoBrowser) { Start-Process 'http://localhost:8080' }
    Write-ClientMessage 'تم تشغيل النظام بنجاح.'
}
function Assert-FreshDatabaseEmpty {
    $values = Get-EnvValues $EnvironmentFile
    $database = $values['MYSQL_DATABASE']
    if ([string]::IsNullOrWhiteSpace($database) -or $database -notmatch '^[A-Za-z0-9_]+$') {
        throw 'Fresh installation requires a valid MYSQL_DATABASE value.'
    }
    $containerId = (Invoke-ClientCompose @('ps', '-q', 'db') | Select-Object -First 1).Trim()
    if ([string]::IsNullOrWhiteSpace($containerId)) {
        throw 'Fresh database emptiness check could not find the isolated Docker database container.'
    }
    $queryFile = Join-Path ([System.IO.Path]::GetTempPath()) ("phase3-schema-{0}.sql" -f [guid]::NewGuid().ToString('N'))
    $containerQueryPath = '/tmp/phase3-schema-emptiness-check.sql'
    try {
        Set-Content -LiteralPath $queryFile -Value 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();' -Encoding ASCII -NoNewline
        & docker cp $queryFile "$containerId`:$containerQueryPath"
        if ($LASTEXITCODE -ne 0) { throw 'Fresh database emptiness check could not copy its fixed SQL query into Docker.' }
        $result = Invoke-ClientCompose @('exec', '-T', 'db', 'sh', '-lc', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -D "$MYSQL_DATABASE" -N < /tmp/phase3-schema-emptiness-check.sql')
    }
    finally {
        Remove-Item -LiteralPath $queryFile -Force -ErrorAction SilentlyContinue
    }
    $lines = @($result | ForEach-Object { $_.ToString().Trim() } | Where-Object { $_ -ne '' })
    if ($lines.Count -ne 1 -or $lines[0] -notmatch '^\d+$') {
        throw 'Fresh database emptiness check returned malformed output; migrations were not run.'
    }
    if ([int]$lines[0] -ne 0) {
        throw 'The Docker database schema is not empty (migrations, partial setup, or existing data detected). Fresh installation was stopped.'
    }
}
function Export-DockerBackup {
    Ensure-DockerEngine
    $values = Get-EnvValues $EnvironmentFile
    $database = $values['MYSQL_DATABASE']
    if ([string]::IsNullOrWhiteSpace($database) -or $database -notmatch '^[A-Za-z0-9_]+$') { throw 'إعداد اسم قاعدة البيانات غير صالح.' }
    New-Item -ItemType Directory -Path $backupsDirectory -Force | Out-Null
    $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $hostPath = Join-Path $backupsDirectory ("$database-$timestamp.sql")
    $containerId = (Invoke-ClientCompose @('ps', '-q', 'db') | Select-Object -First 1).Trim()
    if (-not $containerId) { throw 'قاعدة بيانات Docker غير قيد التشغيل.' }
    Invoke-ClientCompose @('exec', '-T', 'db', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction --quick --skip-lock-tables --default-character-set=utf8mb4 --set-charset --routines --events --triggers --hex-blob --no-tablespaces --column-statistics=0 --set-gtid-purged=OFF --result-file=/tmp/client-backup.sql --databases "$1"', '--', $database) | Out-Null
    & docker cp ("$containerId`:/tmp/client-backup.sql") $hostPath
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $hostPath) -or (Get-Item -LiteralPath $hostPath).Length -le 0) { throw 'فشل التحقق من النسخة الاحتياطية.' }
    if (-not (Select-String -LiteralPath $hostPath -Pattern '^CREATE TABLE ' -Quiet) -or -not (Select-String -LiteralPath $hostPath -Pattern '^INSERT INTO ' -Quiet)) { throw 'النسخة الاحتياطية لا تحتوي على البنية والبيانات المطلوبة.' }
    Write-ClientMessage "تم إنشاء النسخة الاحتياطية بنجاح: $hostPath"
}
function Restore-ClientBackup {
    Ensure-DockerEngine
    $values = Get-EnvValues $EnvironmentFile
    $target = $values['MYSQL_DATABASE']
    if (-not $BackupPath) {
        $choices = @(Get-ChildItem -LiteralPath $backupsDirectory -Filter '*.sql' | Sort-Object LastWriteTime -Descending)
        if ($choices.Count -eq 0) { throw 'لا توجد نسخ احتياطية متاحة.' }
        $choices | ForEach-Object -Begin { $i = 1 } -Process { Write-Host "$i. $($_.Name)"; $i++ }
        $selection = [int](Read-Host 'اختر رقم النسخة الاحتياطية')
        if ($selection -lt 1 -or $selection -gt $choices.Count) { throw 'اختيار غير صالح.' }
        $BackupPath = $choices[$selection - 1].FullName
    }
    if (-not $ConfirmationPhrase) { $ConfirmationPhrase = Read-Host "اكتب RESTORE $target للتأكيد" }
        & (Join-Path $PSScriptRoot 'Restore-Database.ps1') -BackupPath $BackupPath -TargetDatabase $target -ComposeProject $ComposeProject -EnvironmentFile $EnvironmentFile -ComposeFiles $composeFiles -ConfirmationPhrase $ConfirmationPhrase | Out-Null
    Invoke-ClientCompose @('exec', '-T', 'app', 'php', 'artisan', 'tinker', '--execute=app(App\Services\Inventory\StockLedgerReconciler::class)->checkAll();') | Out-Null
    Test-ApplicationHealth
    Write-ClientMessage 'تمت الاستعادة بنجاح بعد إنشاء نسخة أمان.'
}

if ($Action -ne 'Help') { Assert-Phase3Isolation }

switch ($Action) {
    'Help' { 'Install Start Stop Restart Backup Restore Logs'; break }
    'Start' { Start-ClientSystem; break }
    'Stop' { Ensure-DockerEngine; Invoke-ClientCompose @('stop') | Out-Null; Write-ClientMessage 'تم إيقاف النظام بأمان.'; break }
    'Restart' { Ensure-DockerEngine; Invoke-ClientCompose @('restart') | Out-Null; Wait-ServiceHealth 'db'; Wait-ServiceHealth 'app'; Test-ApplicationHealth; Write-ClientMessage 'تمت إعادة تشغيل النظام بنجاح.'; break }
    'Backup' { Export-DockerBackup; break }
    'Restore' { Restore-ClientBackup; break }
    'Logs' { Ensure-DockerEngine; Invoke-ClientCompose @('ps'); Invoke-ClientCompose @('logs', '--tail', '100', 'app', 'db'); break }
    'Install' {
        if (-not $Mode) {
            Write-Host '1. تثبيت جديد'; Write-Host '2. تثبيت من نسخة موجودة'; Write-Host '3. إلغاء'
            $answer = Read-Host 'اختر'
            if ($answer -eq '1') { $Mode = 'Fresh' } elseif ($answer -eq '2') { $Mode = 'Existing' } else { return }
        }
        Ensure-Environment $Mode
        Assert-Phase3Isolation
        Ensure-DockerEngine
        Invoke-ClientCompose @('build', 'app') | Out-Null
        Start-ClientSystem
        if ($Mode -eq 'Fresh') {
            Assert-FreshDatabaseEmpty
            Invoke-ClientCompose @('exec', '-T', 'app', 'php', 'artisan', 'migrate', '--force') | Out-Null
            Invoke-ClientCompose @('exec', '-T', 'app', 'php', 'artisan', 'config:cache') | Out-Null
            Invoke-ClientCompose @('exec', '-T', 'app', 'php', 'artisan', 'route:cache') | Out-Null
            Invoke-ClientCompose @('exec', '-T', 'app', 'php', 'artisan', 'view:cache') | Out-Null
            if (-not $SkipAdminCreation) { Invoke-ClientCompose @('exec', '-it', 'app', 'php', 'artisan', 'app:create-admin') }
            Test-ApplicationHealth
            Write-ClientMessage 'اكتمل التثبيت الجديد.'
        } else {
            Write-ClientMessage 'تم تجهيز البيئة فقط. الاستيراد يتطلب backup.bat وrestore.bat وتأكيداً صريحاً.'
        }
        break
    }
}
