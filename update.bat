@echo off
setlocal
if /I "%~1"=="--no-pause" (
    set "CLIENT_NO_PAUSE=1"
    shift
)
set "UPDATE_ARGUMENTS="
:collect_arguments
if "%~1"=="" goto invoke
set "UPDATE_ARGUMENTS=%UPDATE_ARGUMENTS% "%~1""
shift
goto collect_arguments
:invoke
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deployment\Update-DockerApplication.ps1" %UPDATE_ARGUMENTS%
set "RESULT=%ERRORLEVEL%"
if not defined CLIENT_NO_PAUSE pause
exit /b %RESULT%
