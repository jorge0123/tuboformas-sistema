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

# Versión de un php.exe ("8.4.26"), o $null si no corre.
function Version-Php([string]$exe) {
    try { $v = & $exe -n -r 'echo PHP_VERSION;' 2>$null; if ($LASTEXITCODE -eq 0) { return "$v".Trim() } } catch {}
    return $null
}

# El sistema necesita PHP 8.3 o más (spatie/laravel-permission). Se elige el más nuevo de Laragon que
# cumpla y que sirva como módulo de Apache (compilación "thread safe", trae php8apache2_4.dll).
$script:PhpMinimo = [version]'8.3.0'
function Buscar-Php([string]$laragon) {
    $base = Join-Path $laragon 'bin\php'
    if (-not (Test-Path $base)) { return $null }
    $candidatos = foreach ($d in Get-ChildItem $base -Directory -Filter 'php-8*') {
        $exe = Join-Path $d.FullName 'php.exe'
        if (-not (Test-Path $exe) -or -not (Test-Path (Join-Path $d.FullName 'php8apache2_4.dll'))) { continue }
        $v = Version-Php $exe
        if ($v -and [version]($v -replace '[^\d.].*$', '') -ge $script:PhpMinimo) { [pscustomobject]@{ Dir = $d.FullName; Version = [version]($v -replace '[^\d.].*$', '') } }
    }
    ($candidatos | Sort-Object Version -Descending | Select-Object -First 1).Dir
}

# Descarga PHP 8.4 oficial (windows.php.net, con verificación SHA-256) dentro de Laragon.
function Instalar-Php([string]$laragon) {
    Paso 'Laragon no trae PHP 8.3 o más: descargando PHP 8.4 oficial'
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Tuboformas-instalador'
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    try {
        $rel = Invoke-RestMethod -UseBasicParsing -UserAgent $ua -Uri 'https://windows.php.net/downloads/releases/releases.json' -TimeoutSec 60
    } catch { Falla 'No hay internet para descargar PHP. Conecta la PC a internet y repite (solo se necesita para instalar).' }
    $r = $rel.'8.4'
    $zip = $r.'ts-vs17-x64'.zip
    $nombre = $zip.path -replace '\.zip$', ''
    $destino = Join-Path $laragon "bin\php\$nombre"
    $archivo = Join-Path $env:TEMP $zip.path
    Invoke-WebRequest -UseBasicParsing -UserAgent $ua -Uri "https://windows.php.net/downloads/releases/$($zip.path)" -OutFile $archivo -TimeoutSec 600
    if ((Get-FileHash $archivo -Algorithm SHA256).Hash -ne $zip.sha256.ToUpper()) { Falla "La descarga de PHP llegó dañada ($($zip.path)). Vuelve a correr el instalador." }
    Expand-Archive $archivo -DestinationPath $destino -Force
    Remove-Item $archivo
    Copy-Item (Join-Path $destino 'php.ini-production') (Join-Path $destino 'php.ini')
    Ok "PHP $($r.version) en $destino"

    # PHP 8.4 necesita el runtime de Visual C++ 2015-2022 (si ya está, no hace nada).
    $vc = Join-Path $env:TEMP 'vc_redist.x64.exe'
    try {
        Invoke-WebRequest -UseBasicParsing -UserAgent $ua -Uri 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile $vc -TimeoutSec 600
        Start-Process $vc -ArgumentList '/install', '/quiet', '/norestart' -Wait
        Remove-Item $vc -ErrorAction SilentlyContinue
        Ok 'Runtime de Visual C++ al día.'
    } catch { Aviso 'No se pudo instalar el runtime de Visual C++; si PHP no arranca, instálalo de https://aka.ms/vs/17/release/vc_redist.x64.exe' }
    return $destino
}

# Apache carga PHP como módulo: se apunta al mismo PHP que usan las tareas, para que web y consola
# corran igual. Se corrige donde Laragon ya lo carga (etc\apache2\mod_php.conf u otro); si no lo
# carga en ningún lado, se agrega un archivo propio.
function Configurar-PhpApache($h, [string]$laragon, [string]$sitios) {
    $dll = (Join-Path $h.PhpDir 'php8apache2_4.dll') -replace '\\', '/'
    $carga = "LoadModule php_module `"$dll`""
    $iniDir = "PHPIniDir `"$($h.PhpDir -replace '\\', '/')`""
    $propio = Join-Path $sitios '00-0-php-tuboformas.conf'
    $archivos = @(Get-ChildItem (Join-Path $laragon 'etc\apache2'), (Join-Path $h.ApacheDir 'conf') -Recurse -Filter '*.conf' -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -ne $propio -and (Select-String -Path $_.FullName -Pattern '^\s*LoadModule\s+php' -Quiet) })
    if ($archivos.Count) {
        foreach ($a in $archivos) {
            $c = (Get-Content $a.FullName) -replace '^\s*LoadModule\s+php\S*\s+.*$', $carga -replace '^\s*PHPIniDir\s+.*$', $iniDir
            if (-not ($c -match '^\s*PHPIniDir')) { $c += $iniDir }
            Set-Content $a.FullName $c
        }
        Remove-Item $propio -ErrorAction SilentlyContinue
    } else {
        Set-Content $propio @('# Lo escribe instalar.ps1: PHP para Apache.', $carga, $iniDir,
            '<FilesMatch "\.php$">', '    SetHandler application/x-httpd-php', '</FilesMatch>', 'DirectoryIndex index.php index.html')
    }
    # httpd -t escribe "Syntax OK" en stderr: con 'Stop', PowerShell 5.1 lo tomaría como error. Vale el código de salida.
    $antes = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
    try { $prueba = (& $h.Httpd -t 2>&1 | ForEach-Object { "$_" }) -join "`n" } finally { $ErrorActionPreference = $antes }
    if ($LASTEXITCODE -ne 0) { Falla "La configuración de Apache tiene un error:`n$prueba" }
    Ok "Apache usa PHP de $($h.PhpDir)"
}

# Tarea programada robusta: corre como SYSTEM aunque nadie inicie sesión, también con batería
# (corte de luz con laptop/UPS), y si la PC estaba apagada a la hora, corre al encender.
function Registrar-Tarea([string]$nombre, [string]$exe, [string]$argumentos, $disparadores, [int]$minutosMaximo = 60) {
    $accion = New-ScheduledTaskAction -Execute $exe -Argument $argumentos -WorkingDirectory (Carpeta-Proyecto)
    $ajustes = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable `
        -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes $minutosMaximo) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
    $quien = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
    Register-ScheduledTask -TaskPath '\Tuboformas\' -TaskName $nombre -Action $accion -Trigger $disparadores -Settings $ajustes -Principal $quien -Force | Out-Null
}

# Espera a que un puerto local responda (MySQL al arrancar el servicio, Apache…).
function Esperar-Puerto([int]$puerto, [int]$segundos = 60) {
    $fin = (Get-Date).AddSeconds($segundos)
    while ((Get-Date) -lt $fin) {
        try { $t = New-Object Net.Sockets.TcpClient; $t.Connect('127.0.0.1', $puerto); $t.Close(); return $true } catch { Start-Sleep -Seconds 2 }
    }
    return $false
}

# ¿Responde al ping? Nunca lanza error: Test-Connection da "Error genérico" (en vez de $false) mientras
# la tarjeta de red se reconecta, por ejemplo justo después de fijar la IP. Reintenta $intentos veces.
function Responde-Ping([string]$direccion, [int]$intentos = 1) {
    for ($i = 1; $i -le $intentos; $i++) {
        try { if (Test-Connection -ComputerName $direccion -Count 1 -Quiet -ErrorAction Stop) { return $true } } catch {}
        if ($i -lt $intentos) { Start-Sleep -Seconds 2 }
    }
    return $false
}

function Herramientas([string]$laragon) {
    $php = Buscar-Php $laragon
    $mysql = Ultima-Version (Join-Path $laragon 'bin\mysql') 'mysql-*'
    $apache = Ultima-Version (Join-Path $laragon 'bin\apache') 'httpd-*'
    $node = Ultima-Version (Join-Path $laragon 'bin\nodejs') 'node*'
    if (-not $php) { $php = Instalar-Php $laragon }
    if (-not $mysql) { Falla "No encontré MySQL en $laragon\bin\mysql." }
    if (-not $apache) { Falla "No encontré Apache en $laragon\bin\apache." }
    $composer = Join-Path $laragon 'bin\composer\composer.phar'
    if (-not (Test-Path $composer)) { $composer = $null }
    return [pscustomobject]@{
        Php      = Join-Path $php 'php.exe'
        PhpVersion = Version-Php (Join-Path $php 'php.exe')
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
    # -Encoding UTF8: Windows PowerShell 5.1 lee en ANSI por defecto y dañaría los acentos.
    $linea = Get-Content $archivo -Encoding UTF8 | Where-Object { $_ -match "^$clave=" } | Select-Object -First 1
    if (-not $linea) { return '' }
    return ($linea -replace "^$clave=", '').Trim('"')
}

function Escribir-Env([string]$archivo, [string]$clave, [string]$valor) {
    $lineas = @(Get-Content $archivo -Encoding UTF8)
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

# php.ini para producción: archivos de 50 MB, hora de Guatemala, extensiones y OPcache (más rápido).
function Ajustar-PhpIni($h) {
    $ini = Join-Path $h.PhpDir 'php.ini'
    if (-not (Test-Path $ini)) { Copy-Item (Join-Path $h.PhpDir 'php.ini-production') $ini }
    $c = Get-Content $ini
    $c = $c -replace '^\s*;?\s*upload_max_filesize\s*=.*', 'upload_max_filesize = 50M'
    $c = $c -replace '^\s*;?\s*post_max_size\s*=.*', 'post_max_size = 55M'
    $c = $c -replace '^\s*;?\s*memory_limit\s*=.*', 'memory_limit = 512M'
    $c = $c -replace '^\s*;?\s*max_execution_time\s*=.*', 'max_execution_time = 120'
    $c = $c -replace '^\s*;?\s*date\.timezone\s*=.*', 'date.timezone = America/Guatemala'
    # Ruta absoluta: con "ext" relativo, Apache (como servicio) busca las extensiones en su propia carpeta y no carga ninguna.
    $c = $c -replace '^\s*;?\s*extension_dir\s*=\s*"[^"]*ext"\s*$', "extension_dir = `"$(Join-Path $h.PhpDir 'ext')`""
    foreach ($ext in 'curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'zip') {
        $c = $c -replace "^\s*;\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$", "extension=$ext"
    }
    $c = $c -replace '^\s*;\s*zend_extension\s*=\s*(php_)?opcache(\.dll)?\s*$', 'zend_extension=opcache'
    $c = $c -replace '^\s*;?\s*opcache\.enable\s*=.*', 'opcache.enable=1'
    $c = $c -replace '^\s*;?\s*opcache\.memory_consumption\s*=.*', 'opcache.memory_consumption=192'
    $c = $c -replace '^\s*;?\s*opcache\.max_accelerated_files\s*=.*', 'opcache.max_accelerated_files=20000'
    Set-Content $ini $c
    $faltan = foreach ($ext in 'fileinfo', 'mbstring', 'openssl', 'pdo_mysql') {
        if (-not ((& $h.Php -m) -contains $ext)) { $ext }
    }
    if ($faltan) { Falla "PHP no carga estas extensiones: $($faltan -join ', '). Revisa $ini" }
    Ok "PHP $($h.PhpVersion): archivos hasta 50 MB, hora de Guatemala, OPcache activo."
}

# Si Laragon nunca se abrió, su httpd.conf es el original de Apache: SRVROOT apunta a C:/Apache24 y no
# carga los sitios de Laragon. Se deja como lo dejaría Laragon; si ya lo preparó, solo se activan módulos.
function Preparar-HttpdConf($h, [string]$laragon) {
    $conf = Join-Path $h.ApacheDir 'conf\httpd.conf'
    $c = Get-Content $conf
    $raiz = $h.ApacheDir -replace '\\', '/'
    $linea = $c | Where-Object { $_ -match '^\s*Define\s+SRVROOT\s' } | Select-Object -First 1
    if ($linea -and -not (Test-Path (($linea -replace '^\s*Define\s+SRVROOT\s+', '').Trim().Trim('"')))) {
        $c = $c -replace '^\s*Define\s+SRVROOT\s+.*$', "Define SRVROOT `"$raiz`""
        Ok "httpd.conf: ServerRoot en $raiz."
    }
    # Laravel necesita mod_rewrite (rutas amigables) y el sitio usa mod_deflate (texto comprimido).
    $c = $c -replace '^\s*#\s*(LoadModule\s+(rewrite|deflate)_module\s.*)$', '$1'
    # Sin ServerName global Apache avisa (AH00558) en cada arranque.
    if (-not ($c -match '^\s*ServerName\s')) { $c = $c -replace '^\s*#\s*ServerName\s.*$', 'ServerName localhost:80' }
    $etc = (Join-Path $laragon 'etc\apache2') -replace '\\', '/'
    if (-not ($c -match 'sites-enabled')) {
        $c += @('', '# Lo agrega instalar.ps1 (Laragon nunca se abrió): sitios y PHP de Laragon.', "IncludeOptional `"$etc/sites-enabled/*.conf`"")
    }
    if ((Test-Path (Join-Path $laragon 'etc\apache2\mod_php.conf')) -and -not ($c -match 'mod_php\.conf')) {
        $c += "Include `"$etc/mod_php.conf`""
    }
    Set-Content $conf $c
}

# Publica el sitio en Apache (puerto 80, sitio por defecto: responde a la IP y a tuboformas.local).
function Publicar-Sitio($h, [string]$laragon) {
    $publico = (Join-Path (Carpeta-Proyecto) 'public') -replace '\\', '/'
    $sitios = Join-Path $laragon 'etc\apache2\sites-enabled'
    New-Item -ItemType Directory -Force -Path $sitios | Out-Null
    Preparar-HttpdConf $h $laragon
    (Get-Content (Join-Path $PSScriptRoot 'apache-tuboformas.conf')) -replace '__PUBLICO__', $publico |
        Set-Content (Join-Path $sitios '00-0-tuboformas.conf')
    Configurar-PhpApache $h $laragon $sitios
    Entorno-ServicioApache $h
}

# Extensiones como intl, curl u openssl necesitan DLL que están en la carpeta de PHP (ICU, libssl…).
# Laragon la pone en el PATH; un servicio de Windows no la tiene, así que se agrega al PATH del servicio.
function Entorno-ServicioApache($h) {
    $clave = 'HKLM:\SYSTEM\CurrentControlSet\Services\TuboformasApache'
    if (-not (Test-Path $clave)) { return }
    $ruta = "$($h.PhpDir);$([Environment]::GetEnvironmentVariable('Path', 'Machine'))"
    New-ItemProperty $clave -Name Environment -PropertyType MultiString -Value @("PATH=$ruta") -Force | Out-Null
    Ok 'El servicio de Apache encuentra las librerías de PHP.'
}

# Las tres tareas del sistema. Se vuelven a registrar en cada instalación/actualización.
function Registrar-Tareas($h) {
    $proyecto = Carpeta-Proyecto
    $ps = 'powershell.exe'
    $cadaMinuto = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
    Registrar-Tarea 'Programador' $h.Php "`"$(Join-Path $proyecto 'artisan')`" schedule:run" @($cadaMinuto, (New-ScheduledTaskTrigger -AtStartup)) 10
    Registrar-Tarea 'Respaldo' $ps "-NoProfile -ExecutionPolicy Bypass -File `"$(Join-Path $PSScriptRoot 'respaldo.ps1')`"" (New-ScheduledTaskTrigger -Daily -At '22:30') 120
    $cada5 = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 5)
    $alEncender = New-ScheduledTaskTrigger -AtStartup
    $alEncender.Delay = 'PT2M'
    Registrar-Tarea 'Vigilante' $ps "-NoProfile -ExecutionPolicy Bypass -File `"$(Join-Path $PSScriptRoot 'vigilante.ps1')`"" @($cada5, $alEncender) 10
    Ok 'Cada minuto: preventivos, correos, reportes programados y cierre de asistencias olvidadas.'
    Ok 'Cada 5 minutos: el vigilante revisa que todo responda y lo levanta si se cayó.'
    Ok 'Cada día 22:30: respaldo de la base y de las fotos (si la PC estaba apagada, al encender).'
}

# Carpeta de git de Laragon en el PATH (para actualizar.ps1).
function Git-Laragon([string]$laragon) {
    $g = Ultima-Version (Join-Path $laragon 'bin\git') '*'
    if ($g -and (Test-Path (Join-Path $g 'cmd\git.exe'))) { $env:Path = "$(Join-Path $g 'cmd');$env:Path" }
}
