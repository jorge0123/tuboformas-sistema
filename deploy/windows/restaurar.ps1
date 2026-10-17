<#
.SYNOPSIS
    Restaura un respaldo: la base de datos y las fotos/documentos.

.DESCRIPTION
    Antes de restaurar respalda lo que hay ahora (por si se eligió el respaldo equivocado).
    Sin -Archivo usa el respaldo más reciente de la carpeta "respaldos".

.EXAMPLE
    .\restaurar.ps1
    .\restaurar.ps1 -Archivo E:\RespaldosTuboformas\tuboformas_2026-10-15_2230.zip
#>
param(
    [string]$Laragon = 'C:\laragon',
    [string]$Archivo,
    [switch]$SinPreguntar
)
. (Join-Path $PSScriptRoot 'comun.ps1')
Exigir-Administrador

$proyecto = Carpeta-Proyecto
$env_ = Join-Path $proyecto '.env'
$h = Herramientas $Laragon
if (-not $Archivo) {
    $Archivo = (Get-ChildItem (Join-Path $proyecto 'respaldos') -Filter 'tuboformas_*.zip' -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
}
if (-not $Archivo -or -not (Test-Path $Archivo)) { Falla 'No encontré el respaldo. Indica cuál: .\restaurar.ps1 -Archivo <ruta del .zip>' }

Paso "Restaurar $(Split-Path $Archivo -Leaf) ($((Get-Item $Archivo).LastWriteTime.ToString('dd/MM/yyyy HH:mm')))"
Aviso 'Todo lo registrado después de ese respaldo se perderá (antes se guarda una copia de lo actual).'
if (-not $SinPreguntar) {
    $r = Read-Host '¿Continuar? (s/n)'
    if ($r -notmatch '^[sS]') { Aviso 'Cancelado.'; return }
}

Paso 'Respaldo de lo actual'
& (Join-Path $PSScriptRoot 'respaldo.ps1') -Laragon $Laragon

$temporal = Join-Path $env:TEMP "tuboformas-restaurar-$(Get-Random)"
Expand-Archive $Archivo -DestinationPath $temporal -Force
$sql = Get-ChildItem $temporal -Filter '*.sql' | Select-Object -First 1
if (-not $sql) { Falla 'El respaldo no trae la base de datos (.sql).' }

Artisan $h $proyecto @('down', '--retry=30')
try {
    Paso 'Base de datos'
    $env:MYSQL_PWD = Leer-Env $env_ 'DB_PASSWORD'
    $comando = "`"$($h.Mysql)`" -u$(Leer-Env $env_ 'DB_USERNAME') --default-character-set=utf8mb4 $(Leer-Env $env_ 'DB_DATABASE') < `"$($sql.FullName)`""
    cmd.exe /c $comando
    $codigo = $LASTEXITCODE
    Remove-Item Env:MYSQL_PWD
    if ($codigo -ne 0) { Falla 'MySQL no pudo cargar el respaldo.' }
    Ok 'Base restaurada.'

    $fotos = Join-Path $temporal 'public'
    if (Test-Path $fotos) {
        Paso 'Fotos y documentos'
        robocopy $fotos (Join-Path $proyecto 'storage\app\public') /MIR /NFL /NDL /NJH /NJS /NP | Out-Null
        if ($LASTEXITCODE -ge 8) { Falla 'No se pudieron copiar las fotos.' }
        Ok 'Fotos y documentos restaurados.'
    }
    Artisan $h $proyecto @('migrate', '--force')
    Artisan $h $proyecto @('optimize:clear')
    Artisan $h $proyecto @('optimize')
} finally {
    Artisan $h $proyecto @('up')
    Remove-Item $temporal -Recurse -Force -ErrorAction SilentlyContinue
}
Ok 'Listo: el sistema quedó como estaba en ese respaldo.'
