@echo off
rem Deja el sistema en blanco, solo con el usuario admin.
rem Doble clic: pide permiso de administrador y corre limpiar.ps1
cd /d "%~dp0"
net session >nul 2>&1 || (powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -ArgumentList '%*' -Verb RunAs" & exit /b)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0limpiar.ps1" %*
echo.
pause
