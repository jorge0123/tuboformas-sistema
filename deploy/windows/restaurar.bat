@echo off
rem Restaura el respaldo mas reciente.
rem Doble clic: pide permiso de administrador y corre restaurar.ps1
cd /d "%~dp0"
net session >nul 2>&1 || (powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -ArgumentList '%*' -Verb RunAs" & exit /b)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0restaurar.ps1" %*
echo.
pause
