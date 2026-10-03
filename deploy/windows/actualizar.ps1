<#
.SYNOPSIS
    Actualiza el sistema a la última versión de GitHub sin perder datos.

.DESCRIPTION
    Respalda, baja los cambios (git pull), instala dependencias, compila el diseño, migra la base,
    actualiza roles y permisos, y limpia las cachés. Mientras tanto el sistema muestra "en mantenimiento".

.EXAMPLE
    .\actualizar.ps1
#>
param([string]$Laragon = 'C:\laragon')
. (Join-Path $PSScriptRoot 'comun.ps1')
Exigir-Administrador

$proyecto = Carpeta-Proyecto
$h = Herramientas $Laragon
$env:Path = "$($h.PhpDir);$($h.NodeDir);$env:Path"

Paso 'Respaldo antes de actualizar'
& (Join-Path $PSScriptRoot 'respaldo.ps1') -Laragon $Laragon

Artisan $h $proyecto @('down', '--retry=30')
try {
    Paso 'Bajando la última versión'
    Correr 'git' @('pull', '--ff-only') $proyecto

    Paso 'Dependencias y diseño'
    Correr $h.Php @($h.Composer, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction') $proyecto
    if ($h.NodeDir) {
        Correr (Join-Path $h.NodeDir 'npm.cmd') @('ci', '--no-audit', '--no-fund') $proyecto
        Correr (Join-Path $h.NodeDir 'npm.cmd') @('run', 'build') $proyecto
    }

    Paso 'Base de datos y permisos'
    Artisan $h $proyecto @('migrate', '--force')
    Artisan $h $proyecto @('db:seed', '--class=RolesPermisosSeeder', '--force')
    Artisan $h $proyecto @('optimize:clear')
    Artisan $h $proyecto @('optimize')
} finally {
    Artisan $h $proyecto @('up')
}
if (Get-Service TuboformasApache -ErrorAction SilentlyContinue) { Restart-Service TuboformasApache }
Ok 'Sistema actualizado.'
