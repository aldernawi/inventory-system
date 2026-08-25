$ErrorActionPreference = 'Stop'

Describe 'Client-Operations.ps1' {
    $repositoryRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    $scriptPath = Join-Path $repositoryRoot 'deployment\Client-Operations.ps1'

    It 'lists the supported client actions without requiring Docker or secrets' {
        $scriptPath | Should Exist

        if (-not (Test-Path -LiteralPath $scriptPath)) {
            return
        }

        $help = & $scriptPath -Action Help

        $help | Should Match 'Install'
        $help | Should Match 'Start'
        $help | Should Match 'Restore'
    }

    It 'rejects Phase 3 test mode when no explicit compose override is supplied' {
        try {
            & $scriptPath -Action Start -TestMode -NoBrowser | Out-Null
            throw 'Expected test mode to reject the missing override.'
        }
        catch {
            $output = $_.Exception.Message
        }

        $output | Should Match 'ComposeOverride'
    }

    It 'uses one explicit compose override rather than a compose-file array boundary' {
        $content = Get-Content -Raw -LiteralPath $scriptPath

        $content | Should Match '\[string\]\$ComposeOverride'
        $content | Should Not Match '\[string\[\]\]\$ComposeFiles'
    }

    It 'contains a strict fresh-schema emptiness check that rejects malformed output' {
        $content = Get-Content -Raw -LiteralPath $scriptPath

        $content | Should Match 'Assert-FreshDatabaseEmpty'
        $content | Should Match 'malformed output'
        $content | Should Not Match 'table_name = ''migrations'''
    }

    It 'forwards named test arguments through each batch wrapper with paths containing spaces' {
        $fixtureDirectory = Join-Path $PSScriptRoot 'phase3 fixture'
        New-Item -ItemType Directory -Path $fixtureDirectory -Force | Out-Null
        $envFile = Join-Path $fixtureDirectory 'verify environment.env'
        $overrideFile = Join-Path $fixtureDirectory 'verify override.yml'
        @(
            'APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'MYSQL_DATABASE=inventory_phase3_verification',
            'MYSQL_USER=verify_user',
            'MYSQL_PASSWORD=verify_password',
            'MYSQL_ROOT_PASSWORD=verify_root_password'
        ) | Set-Content -LiteralPath $envFile -Encoding UTF8
        @(
            'volumes:',
            '  inventory_mysql_data:',
            '    name: deliberately_wrong_phase3_volume'
        ) | Set-Content -LiteralPath $overrideFile -Encoding UTF8

        foreach ($wrapper in @('install.bat', 'start.bat', 'stop.bat', 'restart.bat', 'backup.bat', 'restore.bat', 'logs.bat')) {
            $command = '"{0}" --no-pause -EnvironmentFile "{1}" -ComposeOverride "{2}" -ComposeProject inventory_phase3_verification -TestMode -ExpectedDatabaseName inventory_phase3_verification -ExpectedVolumeName inventory_phase3_verification_mysql_data -NoBrowser' -f (Join-Path $repositoryRoot $wrapper), $envFile, $overrideFile
            $output = & cmd.exe /d /c $command 2>&1
            $output | Out-String | Should Match 'expected isolated volume'
        }
    }
}
