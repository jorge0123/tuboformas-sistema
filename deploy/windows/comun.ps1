# Funciones compartidas por los scripts de instalación (Windows PowerShell 5.1 o superior).

$ErrorActionPreference = 'Stop'

function Paso([string]$texto) { Write-Host "`n==> $texto" -ForegroundColor Cyan }
function Ok([string]$texto) { Write-Host "    $texto" -ForegroundColor Green }
function Aviso([string]$texto) { Write-Host "    $texto" -ForegroundColor Yellow }
function Falla([string]$texto) { Write-Host "`nERROR: $texto" -ForegroundColor Red; exit 1 }

function Exigir-Administrador {
    $yo = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
    if (-not $yo.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        Falla 'Abre PowerShell como administrador (clic derecho > Ejecutar como administrador) y vuelve a correr el script.'
    }
}

# Carpeta del proyecto: dos niveles arriba de deploy\windows.
function Carpeta-Proyecto { (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path }

# La versión más nueva instalada dentro de una carpeta de Laragon (bin\php, bin\mysql, bin\apache).
function Ultima-Version([string]$carpeta, [string]$patron) {
    if (-not (Test-Path $carpeta)) { return $null }
    $d = Get-ChildItem $carpeta -Directory | Where-Object { $_.Name -like $patron } | Sort-Object Name -Descending | Select-Object -First 1
    if ($d) { return $d.FullName }
    return $null
}

function Herramientas([string]$laragon) {
    $php = Ultima-Version (Join-Path $laragon 'bin\php') 'php-8*'
    $mysql = Ultima-Version (Join-Path $laragon 'bin\mysql') 'mysql-*'
    $apache = Ultima-Version (Join-Path $laragon 'bin\apache') 'httpd-*'
    $node = Ultima-Version (Join-Path $laragon 'bin\nodejs') 'node*'
    if (-not $php) { Falla "No encontré PHP 8 en $laragon\bin\php. Instala Laragon (versión completa)." }
    if (-not $mysql) { Falla "No encontré MySQL en $laragon\bin\mysql." }
    if (-not $apache) { Falla "No encontré Apache en $laragon\bin\apache." }
    $composer = Join-Path $laragon 'bin\composer\composer.phar'
    if (-not (Test-Path $composer)) { $composer = $null }
    return [pscustomobject]@{
        Php      = Join-Path $php 'php.exe'
        PhpDir   = $php
        Mysql    = Join-Path $mysql 'bin\mysql.exe'
        Mysqld   = Join-Path $mysql 'bin\mysqld.exe'
        Mysqldump = Join-Path $mysql 'bin\mysqldump.exe'
        MysqlDir = $mysql
        Httpd    = Join-Path $apache 'bin\httpd.exe'
        ApacheDir = $apache
        Composer = $composer
        NodeDir  = $node
    }
}

# Corre un programa y falla si termina con error.
function Correr([string]$exe, [string[]]$argumentos, [string]$enCarpeta = $null) {
    if ($enCarpeta) { Push-Location $enCarpeta }
    try {
        & $exe @argumentos
        if ($LASTEXITCODE -ne 0) { Falla "Falló: $exe $($argumentos -join ' ')" }
    } finally {
        if ($enCarpeta) { Pop-Location }
    }
}

function Artisan($h, [string]$proyecto, [string[]]$argumentos) {
    Correr $h.Php (@((Join-Path $proyecto 'artisan')) + $argumentos) $proyecto
}

# Lee y escribe claves del .env sin tocar el resto del archivo.
function Leer-Env([string]$archivo, [string]$clave) {
    $linea = Get-Content $archivo | Where-Object { $_ -match "^$clave=" } | Select-Object -First 1
    if (-not $linea) { return '' }
    return ($linea -replace "^$clave=", '').Trim('"')
}

function Escribir-Env([string]$archivo, [string]$clave, [string]$valor) {
    $lineas = Get-Content $archivo
    if ($lineas | Where-Object { $_ -match "^$clave=" }) {
        $lineas = $lineas | ForEach-Object { if ($_ -match "^$clave=") { "$clave=$valor" } else { $_ } }
    } else {
        $lineas += "$clave=$valor"
    }
    # UTF-8 sin BOM: Laravel no lee bien un .env con BOM.
    [IO.File]::WriteAllLines($archivo, $lineas, (New-Object Text.UTF8Encoding($false)))
}

function Clave-Aleatoria([int]$largo = 20) {
    $c = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'.ToCharArray()
    -join (1..$largo | ForEach-Object { $c | Get-Random })
}
