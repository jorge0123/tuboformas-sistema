<#
.SYNOPSIS
    Instala el sistema Tuboformas en una PC con Windows y Laragon, para usarlo en la red de la planta.

.DESCRIPTION
    Deja todo listo para que los técnicos entren desde el celular o la PC a una dirección que no cambia:
      1. Revisa Laragon (PHP, MySQL, Apache, Composer, Node).
      2. Crea la base de datos y su usuario, y el archivo .env (claves aleatorias).
      3. Instala dependencias, compila el diseño, migra la base y crea roles, catálogos y el usuario admin.
      4. Publica el sitio en Apache (puerto 80) y abre el puerto en el firewall solo para la red local.
      5. Registra Apache y MySQL como servicios de Windows (arrancan solos aunque nadie inicie sesión).
      6. Programa las tareas: cada minuto (preventivos, avisos, reportes, cierre de asistencia) y respaldo diario.
      7. Fija la IP de esta PC (ip-fija.ps1) y le pone el nombre TUBOFORMAS (http://tuboformas.local).

    Se puede volver a correr: lo que ya está hecho se respeta.

.EXAMPLE
    .\instalar.ps1
    .\instalar.ps1 -Ip 192.168.0.240
    .\instalar.ps1 -SinIpFija -SinServicios
#>
param(
    [string]$Laragon = 'C:\laragon',
    [string]$Ip,
    [switch]$SinIpFija,
    [switch]$SinServicios,
    [switch]$SinNombre
)
. (Join-Path $PSScriptRoot 'comun.ps1')
Exigir-Administrador

$proyecto = Carpeta-Proyecto
$env_ = Join-Path $proyecto '.env'
Write-Host "Sistema Tuboformas · instalación en $proyecto" -ForegroundColor White

# ── 1. Herramientas ───────────────────────────────────────────────────
Paso 'Revisando Laragon'
$h = Herramientas $Laragon
$env:Path = "$($h.PhpDir);$($h.MysqlDir)\bin;$($h.NodeDir);$env:Path"
Ok "PHP: $($h.Php)"
Ok "MySQL: $($h.MysqlDir)"
Ok "Apache: $($h.ApacheDir)"
if (-not $h.Composer) { Falla 'No encontré Composer en Laragon (bin\composer\composer.phar).' }
if (-not $h.NodeDir) { Aviso 'No encontré Node en Laragon: se usará el diseño ya compilado (public\build) si existe.' }

# PHP para fotos y manuales.
$ini = Join-Path $h.PhpDir 'php.ini'
if (Test-Path $ini) {
    $c = Get-Content $ini
    $c = $c -replace '^\s*;?\s*upload_max_filesize\s*=.*', 'upload_max_filesize = 50M'
    $c = $c -replace '^\s*;?\s*post_max_size\s*=.*', 'post_max_size = 55M'
    $c = $c -replace '^\s*;?\s*memory_limit\s*=.*', 'memory_limit = 512M'
    $c = $c -replace '^\s*;?\s*date\.timezone\s*=.*', 'date.timezone = America/Guatemala'
    foreach ($ext in 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'zip') {
        $c = $c -replace "^\s*;\s*extension\s*=\s*$ext\s*$", "extension=$ext"
    }
    Set-Content $ini $c
    Ok 'php.ini: archivos hasta 50 MB, zona horaria de Guatemala, extensiones necesarias.'
}

# ── 2. MySQL y .env ───────────────────────────────────────────────────
Paso 'Base de datos'
$mysqlActivo = Get-Process mysqld -ErrorAction SilentlyContinue
if (-not $mysqlActivo) {
    $servicio = Get-Service TuboformasMySQL -ErrorAction SilentlyContinue
    if ($servicio) { Start-Service TuboformasMySQL } else {
        Falla 'MySQL no está corriendo. Abre Laragon y pulsa "Start All" (o "Iniciar todo"), luego vuelve a correr este script.'
    }
    Start-Sleep -Seconds 3
}

if (-not (Test-Path $env_)) {
    Copy-Item (Join-Path $PSScriptRoot '.env.planta') $env_
    Escribir-Env $env_ 'DB_PASSWORD' (Clave-Aleatoria 24)
    Escribir-Env $env_ 'ADMIN_PASSWORD' (Clave-Aleatoria 12)
    Ok '.env creado con claves aleatorias.'
} else {
    Ok '.env ya existe: se respeta.'
}
$bd = Leer-Env $env_ 'DB_DATABASE'
$usuarioBd = Leer-Env $env_ 'DB_USERNAME'
$claveBd = Leer-Env $env_ 'DB_PASSWORD'
$sql = "CREATE DATABASE IF NOT EXISTS ``$bd`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; " +
       "CREATE USER IF NOT EXISTS '$usuarioBd'@'localhost' IDENTIFIED BY '$claveBd'; " +
       "ALTER USER '$usuarioBd'@'localhost' IDENTIFIED BY '$claveBd'; " +
       "GRANT ALL PRIVILEGES ON ``$bd``.* TO '$usuarioBd'@'localhost'; FLUSH PRIVILEGES;"
Correr $h.Mysql @('-uroot', '-e', $sql)
Ok "Base '$bd' y usuario '$usuarioBd' listos (solo accesible desde esta PC)."

# ── 3. Aplicación ─────────────────────────────────────────────────────
Paso 'Dependencias de PHP'
Correr $h.Php @($h.Composer, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction') $proyecto

if ($h.NodeDir) {
    Paso 'Compilando el diseño'
    Correr (Join-Path $h.NodeDir 'npm.cmd') @('ci', '--no-audit', '--no-fund') $proyecto
    Correr (Join-Path $h.NodeDir 'npm.cmd') @('run', 'build') $proyecto
} elseif (-not (Test-Path (Join-Path $proyecto 'public\build\manifest.json'))) {
    Falla 'No hay Node para compilar ni existe public\build. Instala Laragon completo o copia public\build desde otra PC.'
}

Paso 'Preparando Laravel'
if (-not (Leer-Env $env_ 'APP_KEY')) { Artisan $h $proyecto @('key:generate', '--force') }
Artisan $h $proyecto @('migrate', '--force')
Artisan $h $proyecto @('db:seed', '--force')
if (-not (Test-Path (Join-Path $proyecto 'public\storage'))) { Artisan $h $proyecto @('storage:link') }

# ── 4. IP fija y nombre ───────────────────────────────────────────────
if (-not $SinIpFija) {
    Paso 'Dirección fija en la red'
    if ($Ip) { & (Join-Path $PSScriptRoot 'ip-fija.ps1') -Ip $Ip } else { & (Join-Path $PSScriptRoot 'ip-fija.ps1') }
}
$ipFinal = (Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1).IPv4Address.IPAddress
if ($ipFinal) {
    Escribir-Env $env_ 'APP_URL' "http://$ipFinal"
    Ok "APP_URL = http://$ipFinal"
}

$reiniciar = $false
if (-not $SinNombre -and $env:COMPUTERNAME -ne 'TUBOFORMAS') {
    Paso 'Nombre de la PC'
    Rename-Computer -NewName 'TUBOFORMAS' -Force
    $reiniciar = $true
    Ok 'La PC se llamará TUBOFORMAS después de reiniciar (http://tuboformas.local).'
}

Artisan $h $proyecto @('optimize')

# ── 5. Apache ─────────────────────────────────────────────────────────
Paso 'Publicando el sitio en Apache'
$publico = (Join-Path $proyecto 'public') -replace '\\', '/'
$sitios = Join-Path $Laragon 'etc\apache2\sites-enabled'
New-Item -ItemType Directory -Force -Path $sitios | Out-Null
(Get-Content (Join-Path $PSScriptRoot 'apache-tuboformas.conf')) -replace '__PUBLICO__', $publico |
    Set-Content (Join-Path $sitios '00-0-tuboformas.conf')
Ok "Sitio: $sitios\00-0-tuboformas.conf"

Paso 'Firewall: puerto 80 solo para la red local'
if (-not (Get-NetFirewallRule -DisplayName 'Tuboformas (web)' -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName 'Tuboformas (web)' -Direction Inbound -Protocol TCP -LocalPort 80 `
        -RemoteAddress LocalSubnet -Action Allow -Profile Any | Out-Null
}
Ok 'Regla "Tuboformas (web)" activa.'

# ── 6. Servicios de Windows ───────────────────────────────────────────
if (-not $SinServicios) {
    Paso 'Servicios de Windows (arrancan solos al encender la PC)'
    Aviso 'Desde ahora no uses "Start All" de Laragon para este sistema: lo manejan los servicios.'
    Get-Process laragon -ErrorAction SilentlyContinue | ForEach-Object { $_.CloseMainWindow() | Out-Null }
    Start-Sleep -Seconds 2
    Get-Process httpd, mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2

    if (-not (Get-Service TuboformasMySQL -ErrorAction SilentlyContinue)) {
        $myIni = Join-Path $h.MysqlDir 'my.ini'
        if (-not (Test-Path $myIni)) { Falla "No encontré $myIni. Abre Laragon una vez, inicia MySQL y ciérralo; luego repite." }
        Correr $h.Mysqld @('--install', 'TuboformasMySQL', "--defaults-file=$myIni")
    }
    if (-not (Get-Service TuboformasApache -ErrorAction SilentlyContinue)) {
        Correr $h.Httpd @('-k', 'install', '-n', 'TuboformasApache')
    }
    foreach ($s in 'TuboformasMySQL', 'TuboformasApache') {
        Set-Service $s -StartupType Automatic
        Start-Service $s
        # Si se cae, Windows lo vuelve a levantar.
        sc.exe failure $s reset= 86400 actions= restart/5000/restart/5000/restart/30000 | Out-Null
    }
    Ok 'TuboformasMySQL y TuboformasApache corriendo y en arranque automático.'
} else {
    Aviso 'Sin servicios: en Laragon activa "Ejecutar Laragon al iniciar Windows" y "Iniciar todo automáticamente".'
}

# ── 7. Tareas programadas ─────────────────────────────────────────────
Paso 'Tareas programadas'
$artisan = Join-Path $proyecto 'artisan'
schtasks.exe /Create /F /TN 'Tuboformas\Programador' /SC MINUTE /MO 1 /RU SYSTEM `
    /TR "`"$($h.Php)`" `"$artisan`" schedule:run" | Out-Null
$respaldo = Join-Path $PSScriptRoot 'respaldo.ps1'
schtasks.exe /Create /F /TN 'Tuboformas\Respaldo' /SC DAILY /ST 22:30 /RU SYSTEM `
    /TR "powershell.exe -NoProfile -ExecutionPolicy Bypass -File `"$respaldo`"" | Out-Null
Ok 'Cada minuto: preventivos, correos, reportes programados y cierre de asistencias olvidadas.'
Ok 'Cada día 22:30: respaldo de la base y de las fotos (carpeta respaldos, se guardan 30 días).'

# ── Listo ─────────────────────────────────────────────────────────────
Start-Sleep -Seconds 2
$url = Leer-Env $env_ 'APP_URL'
try {
    $r = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1/login' -TimeoutSec 15
    if ($r.StatusCode -eq 200) { Ok 'El sistema responde.' }
} catch { Aviso "El sistema todavía no responde en http://127.0.0.1. Revisa $Laragon\bin\apache\...\logs\tuboformas-error.log" }

Write-Host ''
Write-Host '──────────────────────────────────────────────────────────────' -ForegroundColor White
Write-Host "  Dirección del sistema:  $url" -ForegroundColor Green
Write-Host '  También (PC y iPhone):  http://tuboformas.local' -ForegroundColor Green
$admin = Leer-Env $env_ 'ADMIN_PASSWORD'
if ($admin) { Write-Host "  Usuario: admin   Contraseña inicial: $admin   (cámbiala en Mi perfil)" -ForegroundColor Yellow }
Write-Host '  En el celular: abre la dirección y elige "Agregar a pantalla de inicio".'
Write-Host '──────────────────────────────────────────────────────────────' -ForegroundColor White
if ($reiniciar) { Aviso 'Reinicia la PC para que tome el nombre TUBOFORMAS.' }
