<#
.SYNOPSIS
    Respaldo diario: base de datos y fotos/documentos. Guarda 30 días en la carpeta "respaldos".

.DESCRIPTION
    Lo corre la tarea programada "Tuboformas\Respaldo" (22:30). También se puede correr a mano.
    Si en el .env está RESPALDO_COPIA (un disco USB, otra unidad o una carpeta de OneDrive/Google
    Drive), deja ahí una segunda copia: si la PC se daña, el respaldo que está en ella se pierde con ella.

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
$opciones = @("-u$usuario", '--single-transaction', '--routines', '--default-character-set=utf8mb4', "--result-file=$archivoSql")
# --set-gtid-purged solo existe en MySQL (no en MariaDB).
if (-not ((& $h.Mysqldump --version) -match 'MariaDB')) { $opciones += '--set-gtid-purged=OFF' }
& $h.Mysqldump @opciones $bd
$codigo = $LASTEXITCODE
Remove-Item Env:MYSQL_PWD
if ($codigo -ne 0 -or -not (Test-Path $archivoSql) -or (Get-Item $archivoSql).Length -lt 1KB) { Falla 'mysqldump falló: el respaldo de la base quedó vacío.' }

# Base + fotos y documentos en un solo .zip.
$zip = Join-Path $Destino "tuboformas_$sello.zip"
$archivos = @($archivoSql)
$fotos = Join-Path $proyecto 'storage\app\public'
if (Test-Path $fotos) { $archivos += $fotos }
Compress-Archive -Path $archivos -DestinationPath $zip -Force
Remove-Item $archivoSql
Ok "Guardado: $zip ($([math]::Round((Get-Item $zip).Length / 1MB, 1)) MB)"

# Segunda copia fuera de la PC.
$copia = Leer-Env $env_ 'RESPALDO_COPIA'
if ($copia) {
    if (Test-Path $copia) {
        Copy-Item $zip $copia -Force
        Get-ChildItem $copia -Filter 'tuboformas_*.zip' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$Dias) } | Remove-Item -Force
        Ok "Copia también en $copia"
    } else { Aviso "No está disponible $copia (¿USB desconectado?): solo quedó la copia local." }
}

$viejos = Get-ChildItem $Destino -Filter 'tuboformas_*.zip' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$Dias) }
$viejos | Remove-Item -Force
if ($viejos) { Ok "Borrados $($viejos.Count) respaldos de más de $Dias días." }
