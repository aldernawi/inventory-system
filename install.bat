@echo off
setlocal
if /I not "%~1"=="--no-pause" goto invoke
set "CLIENT_NO_PAUSE=1"
shift
:invoke
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deployment\Client-Operations.ps1" -Action Install %*
set "RESULT=%ERRORLEVEL%"
if not defined CLIENT_NO_PAUSE pause
exit /b %RESULT%
