$ErrorActionPreference = 'Stop'

Describe 'Restore-Database.ps1' {
    $repositoryRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    $scriptPath = Join-Path $repositoryRoot 'deployment\Restore-Database.ps1'
    $backupPath = Get-ChildItem -LiteralPath (Join-Path $repositoryRoot 'backups') -Filter 'inventory_system-*.sql' | Sort-Object LastWriteTimeUtc -Descending | Select-Object -First 1 -ExpandProperty FullName
    $envFile = Join-Path $repositoryRoot '.env.docker.phase2.verify'
    $composeFiles = @(
        (Join-Path $repositoryRoot 'docker-compose.yml'),
        (Join-Path $repositoryRoot 'docker-compose.phase2.verify.yml')
    )

    It 'requires confirmation, takes a safety backup, restores, and verifies the disposable database' {
        $scriptPath | Should Exist

        if (-not (Test-Path -LiteralPath $scriptPath)) {
            return
        }

        $originalAppKey = $env:APP_KEY
        $env:APP_KEY = ([regex]::Match((Get-Content -Raw (Join-Path $repositoryRoot '.env')), '(?m)^APP_KEY=(.+)$')).Groups[1].Value.Trim()

        try {
            $restore = & $scriptPath `
                -BackupPath $backupPath `
                -TargetDatabase 'inventory_phase2_restore_test' `
                -ComposeProject 'inventory_phase2_restore_test' `
                -EnvironmentFile $envFile `
                -ComposeFiles $composeFiles `
                -ConfirmationPhrase 'RESTORE inventory_phase2_restore_test'
        }
        finally {
            $env:APP_KEY = $originalAppKey
        }

        $restore.TargetDatabase | Should Be 'inventory_phase2_restore_test'
        $restore.SafetyBackupPath | Should Exist
        $restore.SafetyBackupBytes | Should BeGreaterThan 0
        $restore.ImportExitCode | Should Be 0
        $restore.DatabaseConnectionVerified | Should Be $true
        $restore.MigrationsVerified | Should Be $true
    }
}
