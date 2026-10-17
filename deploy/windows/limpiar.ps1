<#
.SYNOPSIS
    Deja el sistema en blanco: borra todos los datos y queda solo el usuario admin.

.DESCRIPTION
    Para empezar a usarlo en serio después de pruebas o capacitaciones. Borra máquinas, órdenes,
    bitácora, repuestos, asistencia, usuarios, fotos y documentos; vuelve a crear roles, catálogos,
    configuración y el usuario admin con una contraseña nueva. Antes guarda un respaldo completo.

.EXAMPLE
    .\limpiar.ps1
#>
param([string]$Laragon = 'C:\laragon')
. (Join-Path $PSScriptRoot 'comun.ps1')
Exigir-Administrador

$proyecto = Carpeta-Proyecto
$env_ = Join-Path $proyecto '.env'
$h = Herramientas $Laragon

Write-Host ''
Aviso 'Se borrará TODO lo registrado en el sistema y quedará solo el usuario admin.'
$r = Read-Host 'Para confirmar escribe BORRAR'
if ($r -cne 'BORRAR') { Aviso 'Cancelado. No se borró nada.'; return }

Paso 'Respaldo antes de borrar'
& (Join-Path $PSScriptRoot 'respaldo.ps1') -Laragon $Laragon

Artisan $h $proyecto @('down', '--retry=30')
try {
    Paso 'Borrando datos'
    $clave = Clave-Aleatoria 12
    Escribir-Env $env_ 'ADMIN_PASSWORD' $clave
    Escribir-Env $env_ 'APP_ENV' 'production'
    Artisan $h $proyecto @('optimize:clear')
    Artisan $h $proyecto @('migrate:fresh', '--force')
    Artisan $h $proyecto @('db:seed', '--force')
    $fotos = Join-Path $proyecto 'storage\app\public'
    Get-ChildItem $fotos -Force -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne '.gitignore' } | Remove-Item -Recurse -Force
    Artisan $h $proyecto @('optimize')
} finally {
    Artisan $h $proyecto @('up')
}
Ok 'Sistema en blanco.'
Write-Host "  Usuario: admin   Contraseña: $clave   (cámbiala en Mi perfil)" -ForegroundColor Yellow
