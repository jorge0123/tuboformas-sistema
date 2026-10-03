<#
.SYNOPSIS
    Respaldo diario: base de datos y fotos/documentos. Guarda 30 días en la carpeta "respaldos".

.DESCRIPTION
    Lo corre la tarea programada "Tuboformas\Respaldo" (22:30). También se puede correr a mano.
    Copia la carpeta "respaldos" a un disco USB o a la nube de vez en cuando: si la PC se daña, el
    respaldo que está en la misma PC se pierde con ella.

.EXAMPLE
    .\respaldo.ps1
    .\respaldo.ps1 -Destino D:\RespaldosTuboformas -Dias 60
#>
param(
    [string]$Laragon = 'C:\laragon',
    [string]$Destino,
    [int]$Dias = 30
)
. (Join-Path $PSScriptRoot 'comun.ps1')

$proyecto = Carpeta-Proyecto
if (-not $Destino) { $Destino = Join-Path $proyecto 'respaldos' }
New-Item -ItemType Directory -Force -Path $Destino | Out-Null
$h = Herramientas $Laragon
$env_ = Join-Path $proyecto '.env'
$sello = Get-Date -Format 'yyyy-MM-dd_HHmm'

Paso "Respaldo $sello"
$archivoSql = Join-Path $Destino "tuboformas_$sello.sql"
$bd = Leer-Env $env_ 'DB_DATABASE'
$usuario = Leer-Env $env_ 'DB_USERNAME'
$env:MYSQL_PWD = Leer-Env $env_ 'DB_PASSWORD'
& $h.Mysqldump "-u$usuario" '--single-transaction' '--routines' '--set-gtid-purged=OFF' "--result-file=$archivoSql" $bd
$codigo = $LASTEXITCODE
Remove-Item Env:MYSQL_PWD
if ($codigo -ne 0) { Falla 'mysqldump falló.' }

# Base + fotos y documentos en un solo .zip.
$zip = Join-Path $Destino "tuboformas_$sello.zip"
$archivos = @($archivoSql)
$fotos = Join-Path $proyecto 'storage\app\public'
if (Test-Path $fotos) { $archivos += $fotos }
Compress-Archive -Path $archivos -DestinationPath $zip -Force
Remove-Item $archivoSql
Ok "Guardado: $zip ($([math]::Round((Get-Item $zip).Length / 1MB, 1)) MB)"

$viejos = Get-ChildItem $Destino -Filter 'tuboformas_*.zip' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$Dias) }
$viejos | Remove-Item -Force
if ($viejos) { Ok "Borrados $($viejos.Count) respaldos de más de $Dias días." }
