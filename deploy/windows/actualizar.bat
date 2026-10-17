@echo off
rem Actualiza el sistema a la ultima version.
rem Doble clic: pide permiso de administrador y corre actualizar.ps1
cd /d "%~dp0"
net session >nul 2>&1 || (powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -ArgumentList '%*' -Verb RunAs" & exit /b)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0actualizar.ps1" %*
echo.
pause
