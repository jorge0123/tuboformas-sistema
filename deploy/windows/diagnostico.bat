@echo off
rem Revisa el servidor y deja un reporte en el escritorio.
rem Doble clic: pide permiso de administrador y corre diagnostico.ps1
cd /d "%~dp0"
net session >nul 2>&1 || (powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -ArgumentList '%*' -Verb RunAs" & exit /b)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0diagnostico.ps1" %*
echo.
pause
