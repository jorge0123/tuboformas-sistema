<#
.SYNOPSIS
    Instala el sistema Tuboformas en una PC con Windows y Laragon, para usarlo como servidor de la planta.

.DESCRIPTION
    Pensado para una planta sin departamento de TI: después de correrlo, la PC funciona sola.
      1. Revisa Laragon y, si su PHP es viejo, baja PHP 8.4 oficial (el sistema pide 8.3 o más).
      2. Ajusta la PC como servidor: no se suspende, el antivirus no frena el sistema, hora sincronizada,
         Laragon ya no arranca por su cuenta (los servicios lo reemplazan).
      3. MySQL como servicio de Windows (lo inicializa si Laragon nunca se abrió). Solo escucha a esta PC.
      4. Base de datos, usuario y .env con claves aleatorias.
      5. Dependencias, diseño, migraciones, roles, catálogos y usuario admin.
      6. IP fija (ip-fija.ps1) y nombre TUBOFORMAS (http://tuboformas.local).
      7. Apache como servicio en el puerto 80 con el mismo PHP; firewall abierto solo a la red local.
      8. Tareas: programador (cada minuto), vigilante (cada 5 min) y respaldo diario.
      9. Accesos directos en el escritorio: el sistema y el diagnóstico.
     10. SSH (OpenSSH de Windows) para dar soporte a distancia, abierto solo a la red local.

    Se puede volver a correr cuantas veces sea necesario: lo que ya está hecho se respeta.
    Todo lo que hace queda anotado en deploy\windows\instalacion.log.

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
    [switch]$SinNombre,
    [switch]$SinSsh,
    # Llave pública SSH (texto "ssh-ed25519 AAAA..." o ruta a un .pub) para entrar sin contraseña.
    [string]$LlaveSsh
)
. (Join-Path $PSScriptRoot 'comun.ps1')
Exigir-Administrador
Start-Transcript -Path (Join-Path $PSScriptRoot 'instalacion.log') -Append | Out-Null

$proyecto = Carpeta-Proyecto
$env_ = Join-Path $proyecto '.env'
Write-Host "Sistema Tuboformas · instalación en $proyecto" -ForegroundColor White
if (-not (Test-Path $Laragon)) { Falla "No encontré Laragon en $Laragon. Instálalo (versión completa) desde https://laragon.org/download y repite." }

# ── 1. Herramientas ───────────────────────────────────────────────────
Paso 'Revisando Laragon'
$h = Herramientas $Laragon
$env:Path = "$($h.PhpDir);$($h.MysqlDir)\bin;$($h.NodeDir);$env:Path"
Ok "PHP $($h.PhpVersion): $($h.PhpDir)"
Ok "MySQL: $($h.MysqlDir)"
Ok "Apache: $($h.ApacheDir)"
Ajustar-PhpIni $h

# ── 2. La PC como servidor ────────────────────────────────────────────
Paso 'Ajustando la PC para que trabaje como servidor'
# Conectada a la corriente nunca se suspende ni hiberna (con batería sí, para no agotarla).
powercfg /change standby-timeout-ac 0 | Out-Null
powercfg /change hibernate-timeout-ac 0 | Out-Null
powercfg /change disk-timeout-ac 0 | Out-Null
powercfg /hibernate off 2>$null | Out-Null
Ok 'Conectada a la corriente no se suspende.'
# El antivirus revisa cada archivo PHP en cada visita: se excluyen las carpetas del sistema (mucho más rápido).
try {
    Add-MpPreference -ExclusionPath $proyecto, $Laragon -ErrorAction Stop
    Ok 'Windows Defender no revisa las carpetas del sistema en cada visita.'
} catch { Aviso 'No se pudo agregar la exclusión del antivirus (puede haber otro antivirus): el sistema funciona igual, un poco más lento.' }
# Hora exacta: la asistencia y los reportes dependen de ella.
Set-Service w32time -StartupType Automatic -ErrorAction SilentlyContinue
Start-Service w32time -ErrorAction SilentlyContinue
w32tm /resync /nowait 2>$null | Out-Null
Ok 'Hora de Windows sincronizándose sola.'
# Windows Update no reinicia en horario de planta (si reinicia de noche, todo vuelve a arrancar solo).
$wu = 'HKLM:\SOFTWARE\Microsoft\WindowsUpdate\UX\Settings'
if (Test-Path $wu) {
    Set-ItemProperty $wu ActiveHoursStart 5 -ErrorAction SilentlyContinue
    Set-ItemProperty $wu ActiveHoursEnd 23 -ErrorAction SilentlyContinue
    Ok 'Windows Update no reinicia entre 5:00 y 23:00.'
}

# ── 3. MySQL ──────────────────────────────────────────────────────────
Paso 'Base de datos (MySQL)'
$myIni = Join-Path $h.MysqlDir 'my.ini'
$version = ($h.MysqlDir | Split-Path -Leaf) -replace '^mysql-(\d+).*$', '$1'
$datos = Join-Path $Laragon "data\mysql-$version"
if (-not (Test-Path $myIni)) {
    # Laragon nunca se abrió: se crea la configuración que él crearía.
    $b = $h.MysqlDir -replace '\\', '/'
    $d = $datos -replace '\\', '/'
    Set-Content $myIni @('[mysqld]', "basedir=`"$b`"", "datadir=`"$d`"", 'port=3306', 'bind-address=127.0.0.1',
        'character-set-server=utf8mb4', 'collation-server=utf8mb4_unicode_ci', 'innodb_buffer_pool_size=256M',
        'max_allowed_packet=64M', '[client]', 'port=3306', 'default-character-set=utf8mb4')
    Ok "my.ini creado ($myIni)."
} else {
    # Solo esta PC habla con MySQL; los celulares entran por la web, nunca directo a la base.
    $c = Get-Content $myIni
    if (-not ($c -match '^\s*bind-address')) {
        $c = $c -replace '^\s*\[mysqld\]\s*$', "[mysqld]`r`nbind-address=127.0.0.1"
        Set-Content $myIni $c
    }
    $linea = $c | Where-Object { $_ -match '^\s*datadir\s*=' } | Select-Object -First 1
    if ($linea) { $datos = ($linea -replace '^\s*datadir\s*=\s*', '').Trim('"').Replace('/', '\') }
}
if (-not (Test-Path $datos) -or -not (Get-ChildItem $datos -ErrorAction SilentlyContinue)) {
    New-Item -ItemType Directory -Force -Path $datos | Out-Null
    Correr $h.Mysqld @("--defaults-file=$myIni", '--initialize-insecure', '--console')
    Ok "Base de datos inicializada en $datos."
}

if (-not $SinServicios) {
    # Si Laragon dejó MySQL o Apache abiertos, se cierran: desde ahora los manejan los servicios.
    Get-Process laragon -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    foreach ($proc in 'httpd', 'mysqld') {
        Get-Process $proc -ErrorAction SilentlyContinue | Where-Object {
            -not (Get-CimInstance Win32_Service -Filter "ProcessId=$($_.Id)" -ErrorAction SilentlyContinue)
        } | Stop-Process -Force -ErrorAction SilentlyContinue
    }
    # Que Laragon no vuelva a arrancar solo con Windows y choque con los servicios por los puertos.
    foreach ($run in 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run', 'HKLM:\Software\Microsoft\Windows\CurrentVersion\Run') {
        Get-ItemProperty $run -ErrorAction SilentlyContinue | ForEach-Object {
            $_.PSObject.Properties | Where-Object { $_.Name -match 'laragon' -or "$($_.Value)" -match 'laragon\.exe' } |
                ForEach-Object { Remove-ItemProperty $run -Name $_.Name -ErrorAction SilentlyContinue; Ok 'Laragon ya no arranca solo con Windows.' }
        }
    }
    if (-not (Get-Service TuboformasMySQL -ErrorAction SilentlyContinue)) {
        Correr $h.Mysqld @('--install', 'TuboformasMySQL', "--defaults-file=$myIni")
    }
    Set-Service TuboformasMySQL -StartupType Automatic
    Start-Service TuboformasMySQL
    sc.exe failure TuboformasMySQL reset= 86400 actions= restart/5000/restart/5000/restart/30000 | Out-Null
} elseif (-not (Get-Process mysqld -ErrorAction SilentlyContinue)) {
    Falla 'MySQL no está corriendo. Abre Laragon y pulsa "Iniciar todo", luego repite.'
}
if (-not (Esperar-Puerto 3306 90)) { Falla 'MySQL no respondió en el puerto 3306. Revisa el archivo .err en la carpeta de datos de MySQL.' }
Ok 'MySQL corriendo (solo accesible desde esta PC).'

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
Ok "Base '$bd' y usuario '$usuarioBd' listos."

# ── 4. Aplicación ─────────────────────────────────────────────────────
Paso 'Dependencias de PHP'
if ($h.Composer) {
    Correr $h.Php @($h.Composer, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction') $proyecto
} elseif (-not (Test-Path (Join-Path $proyecto 'vendor\autoload.php'))) {
    Falla 'No hay Composer en Laragon ni carpeta vendor. Instala Laragon completo.'
} else { Aviso 'Sin Composer: se usa la carpeta vendor que ya está.' }

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

# ── 5. IP fija y nombre ───────────────────────────────────────────────
if (-not $SinIpFija) {
    Paso 'Dirección fija en la red'
    if ($Ip) { & (Join-Path $PSScriptRoot 'ip-fija.ps1') -Ip $Ip } else { & (Join-Path $PSScriptRoot 'ip-fija.ps1') }
}
$conf = Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1
$ipFinal = $conf.IPv4Address.IPAddress | Select-Object -First 1
if ($ipFinal) {
    Escribir-Env $env_ 'APP_URL' "http://$ipFinal"
    # El vigilante comprueba que la PC conserve esta IP (y la vuelve a poner si alguien la cambió).
    $manual = (Get-NetIPAddress -IPAddress $ipFinal -ErrorAction SilentlyContinue).PrefixOrigin -eq 'Manual'
    Escribir-Env $env_ 'SERVIDOR_IP' ($(if ($manual) { $ipFinal } else { '' }))
    Ok "Dirección del sistema: http://$ipFinal"
}

$reiniciar = $false
if (-not $SinNombre -and $env:COMPUTERNAME -ne 'TUBOFORMAS') {
    Paso 'Nombre de la PC'
    Rename-Computer -NewName 'TUBOFORMAS' -Force
    $reiniciar = $true
    Ok 'La PC se llamará TUBOFORMAS después de reiniciar (http://tuboformas.local).'
}
Artisan $h $proyecto @('optimize')

# ── 6. Apache ─────────────────────────────────────────────────────────
Paso 'Publicando el sitio en Apache'
Publicar-Sitio $h $Laragon

Paso 'Firewall: puerto 80 solo para la red local'
if (-not (Get-NetFirewallRule -DisplayName 'Tuboformas (web)' -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName 'Tuboformas (web)' -Direction Inbound -Protocol TCP -LocalPort 80 `
        -RemoteAddress LocalSubnet -Action Allow -Profile Any | Out-Null
}
Ok 'Regla "Tuboformas (web)" activa.'

if (-not $SinServicios) {
    Paso 'Apache como servicio de Windows'
    if (-not (Get-Service TuboformasApache -ErrorAction SilentlyContinue)) {
        Correr $h.Httpd @('-k', 'install', '-n', 'TuboformasApache')
    }
    Entorno-ServicioApache $h
    Set-Service TuboformasApache -StartupType Automatic
    sc.exe failure TuboformasApache reset= 86400 actions= restart/5000/restart/5000/restart/30000 | Out-Null
    Restart-Service TuboformasApache -Force
    if (-not (Esperar-Puerto 80 60)) { Falla "Apache no abrió el puerto 80 (¿otro programa lo usa? IIS, Skype…). Revisa $($h.ApacheDir)\logs." }
    Ok 'TuboformasMySQL y TuboformasApache arrancan solos al encender la PC, aunque nadie inicie sesión.'
} else {
    Aviso 'Sin servicios: en Laragon activa "Ejecutar al iniciar Windows" e "Iniciar todo automáticamente", y recárgalo.'
}

# ── 7. Tareas programadas ─────────────────────────────────────────────
Paso 'Tareas programadas'
Registrar-Tareas $h

# ── SSH para soporte a distancia ──────────────────────────────────────
if (-not $SinSsh) {
    Paso 'SSH para soporte a distancia'
    $cap = Get-WindowsCapability -Online -Name 'OpenSSH.Server*' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($cap -and $cap.State -ne 'Installed') {
        Aviso 'Instalando OpenSSH Server (componente de Windows, tarda un par de minutos)...'
        Add-WindowsCapability -Online -Name $cap.Name | Out-Null
    }
    if (Get-Service sshd -ErrorAction SilentlyContinue) {
        Set-Service sshd -StartupType Automatic
        Start-Service sshd
        sc.exe failure sshd reset= 86400 actions= restart/5000/restart/5000/restart/30000 | Out-Null
        # Al entrar se abre PowerShell (no cmd).
        New-Item 'HKLM:\SOFTWARE\OpenSSH' -Force | Out-Null
        Set-ItemProperty 'HKLM:\SOFTWARE\OpenSSH' DefaultShell "$env:WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe"
        # Solo la red local (para entrar desde fuera de la planta: VPN como Tailscale, ver la guía).
        Get-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -ErrorAction SilentlyContinue | Set-NetFirewallRule -RemoteAddress LocalSubnet -Profile Any -Enabled True
        if (-not (Get-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -ErrorAction SilentlyContinue)) {
            New-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -DisplayName 'OpenSSH Server (sshd)' -Direction Inbound -Protocol TCP -LocalPort 22 -RemoteAddress LocalSubnet -Action Allow -Profile Any | Out-Null
        }
        if ($LlaveSsh) {
            $texto = if (Test-Path $LlaveSsh) { (Get-Content $LlaveSsh -Raw).Trim() } else { $LlaveSsh.Trim() }
            # Los administradores de Windows usan este archivo (con permisos solo de SYSTEM y Administradores).
            $llaves = "$env:ProgramData\ssh\administrators_authorized_keys"
            if (-not ((Get-Content $llaves -ErrorAction SilentlyContinue) -contains $texto)) { Add-Content $llaves $texto }
            # Por SID: funciona igual en Windows en español o en inglés (Administradores y SYSTEM).
            icacls.exe $llaves /inheritance:r /grant '*S-1-5-32-544:F' /grant '*S-1-5-18:F' | Out-Null
            Ok 'Llave SSH agregada: se entra sin contraseña.'
        }
        Ok "SSH activo: ssh $env:USERNAME@<IP del servidor> (usuario y contraseña de Windows de esta PC)."
    } else { Aviso 'No se pudo instalar OpenSSH (sin internet o Windows sin ese componente). Repite luego con internet.' }
}

# ── 8. Accesos directos ───────────────────────────────────────────────
Paso 'Accesos directos en el escritorio'
$escritorio = [Environment]::GetFolderPath('CommonDesktopDirectory')
$url = Leer-Env $env_ 'APP_URL'
Set-Content (Join-Path $escritorio 'Sistema Tuboformas.url') @('[InternetShortcut]', "URL=$url", "IconFile=$proyecto\public\favicon.ico", 'IconIndex=0')
$atajo = (New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path $escritorio 'Diagnóstico Tuboformas.lnk'))
$atajo.TargetPath = Join-Path $PSScriptRoot 'diagnostico.bat'
$atajo.WorkingDirectory = $PSScriptRoot
$atajo.Save()
Ok '"Sistema Tuboformas" y "Diagnóstico Tuboformas" en el escritorio de todos los usuarios.'

# ── Listo ─────────────────────────────────────────────────────────────
Paso 'Comprobando'
try {
    $r = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1/up' -TimeoutSec 20
    if ($r.StatusCode -eq 200) { Ok 'El sistema responde.' }
} catch { Aviso "El sistema todavía no responde en http://127.0.0.1. Corre diagnostico.bat y revisa $($h.ApacheDir)\logs." }

Write-Host ''
Write-Host '──────────────────────────────────────────────────────────────' -ForegroundColor White
Write-Host "  Dirección del sistema:  $url" -ForegroundColor Green
Write-Host '  También (PC y iPhone):  http://tuboformas.local' -ForegroundColor Green
$admin = Leer-Env $env_ 'ADMIN_PASSWORD'
if ($admin) { Write-Host "  Usuario: admin   Contraseña inicial: $admin   (cámbiala en Mi perfil)" -ForegroundColor Yellow }
Write-Host '  En el celular: abre la dirección y elige "Agregar a pantalla de inicio".'
if (-not $SinSsh -and (Get-Service sshd -ErrorAction SilentlyContinue)) { Write-Host "  Soporte remoto (SSH):   ssh $env:USERNAME@$($url -replace '^http://', '')" -ForegroundColor Green }
Write-Host '──────────────────────────────────────────────────────────────' -ForegroundColor White
if ($reiniciar) { Aviso 'Reinicia la PC para que tome el nombre TUBOFORMAS (todo vuelve a arrancar solo).' }
Stop-Transcript | Out-Null
