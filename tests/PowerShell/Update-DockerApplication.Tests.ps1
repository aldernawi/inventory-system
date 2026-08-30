$ErrorActionPreference = 'Stop'

Describe 'Update-DockerApplication' {
    $root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    $script = Join-Path $root 'deployment\Update-DockerApplication.ps1'

    It 'derives its project root when started through PowerShell -File' {
        $output = & powershell -NoProfile -ExecutionPolicy Bypass -File $script -DryRun 2>&1

        $message = $output -join "`n"

        $message | Should Not Match 'Split-Path'
        $message | Should Match 'Dry run passed|Update stopped: Git working tree has local changes'
    }

    It 'does not forward its no-pause wrapper flag to PowerShell' {
        $batch = Join-Path $root 'update.bat'
        $output = & cmd.exe /c "`"$batch`" --no-pause -DryRun" 2>&1
        $message = $output -join "`n"

        $message | Should Not Match 'parameter name ''-no-pause'''
        $message | Should Match 'Dry run passed|Update stopped: Git working tree has local changes'
    }
}
