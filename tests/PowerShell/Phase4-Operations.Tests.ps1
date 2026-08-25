$ErrorActionPreference = 'Stop'

Describe 'Phase 4 operational scripts' {
    $root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)

    It 'retains only matching verified inventory backups' {
        $directory = Join-Path $env:TEMP ('inventory-retention-' + [guid]::NewGuid())
        New-Item -ItemType Directory -Path $directory | Out-Null
        try {
            1..3 | ForEach-Object { Set-Content -LiteralPath (Join-Path $directory ("inventory_system-2026010$_-200000.sql")) -Value 'verified' }
            Set-Content -LiteralPath (Join-Path $directory 'unrelated.sql') -Value 'keep'
            & (Join-Path $root 'deployment\Remove-OldInventoryBackups.ps1') -BackupDirectory $directory -Database inventory_system -RetentionCount 2 -Confirm:$false | Out-Null
            @(Get-ChildItem -LiteralPath $directory -Filter 'inventory_system-*.sql').Count | Should Be 2
            (Test-Path -LiteralPath (Join-Path $directory 'unrelated.sql')) | Should Be $true
        } finally { Remove-Item -LiteralPath $directory -Recurse -Force }
    }

    It 'ships a final-only daily runner and conservative update wrapper' {
        (Join-Path $root 'deployment\Run-Daily-DockerBackup.ps1') | Should Exist
        (Join-Path $root 'deployment\install-daily-backup-task.ps1') | Should Exist
        (Join-Path $root 'update.bat') | Should Exist
        (Get-Content -Raw (Join-Path $root 'deployment\Run-Daily-DockerBackup.ps1')) | Should Match 'inventory_system'
    }
}
