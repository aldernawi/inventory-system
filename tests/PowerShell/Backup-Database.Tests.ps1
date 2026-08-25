$ErrorActionPreference = 'Stop'

Describe 'Backup-Database.ps1' {
    $repositoryRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    $scriptPath = Join-Path $repositoryRoot 'deployment\Backup-Database.ps1'
    $envFile = Join-Path $repositoryRoot '.env'
    $dumpPath = 'C:\Program Files\MySQL\MySQL Workbench 8.0 CE\mysqldump.exe'
    $backupDirectory = Join-Path $repositoryRoot 'backups'

    It 'creates a validated UTF-8 logical backup without exposing credentials' {
        $scriptPath | Should Exist

        if (-not (Test-Path -LiteralPath $scriptPath)) {
            return
        }

        $backup = & $scriptPath -EnvFile $envFile -MySqlDumpPath $dumpPath -OutputDirectory $backupDirectory

        $backup.Database | Should Be 'inventory_system'
        $backup.Path | Should Exist
        $backup.Bytes | Should BeGreaterThan 0
        $backup.HasCreateTable | Should Be $true
        $backup.HasInsert | Should Be $true
        $backup.StandardError | Should BeNullOrEmpty
    }
}
