<#
.SYNOPSIS
    Vigilante: cada 5 minutos revisa que el sistema esté arriba y lo arregla solo cuando puede.

.DESCRIPTION
    Lo corre la tarea "Tuboformas\Vigilante" (cada 5 min y 2 min después de encender la PC).
      - Servicios TuboformasMySQL / TuboformasApache: si están detenidos, los arranca.
      - MySQL responde y la web responde (/up): si no, reinicia el servicio que falla.
      - IP fija (SERVIDOR_IP del .env): si alguien la quitó, la vuelve a poner; si la red cambió o hay
        otro equipo con la misma IP, avisa.
      - Disco con poco espacio, respaldo de más de 36 horas, programador sin correr: avisa.
    Los avisos llegan a la campana de los administradores (php artisan sistema:avisar), una vez cada
    12 horas por problema. Todo queda en storage\logs\vigilante.log.
#>
param([string]$Laragon = 'C:\laragon')
. (Join-Path $PSScriptRoot 'comun.ps1')
# Una revisión que falla no detiene las demás.
$ErrorActionPreference = 'Continue'

$proyecto = Carpeta-Proyecto
$env_ = Join-Path $proyecto '.env'
$log = Join-Path $proyecto 'storage\logs\vigilante.log'
$archivoEstado = Join-Path $proyecto 'storage\app\vigilante.json'
if ((Test-Path $log) -and (Get-Item $log).Length -gt 1MB) { Move-Item $log "$log.anterior" -Force }

function Anotar([string]$texto) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $texto" | Add-Content $log -Encoding UTF8 }

$estado = @{}
if (Test-Path $archivoEstado) {
    try { (Get-Content $archivoEstado -Raw | ConvertFrom-Json).PSObject.Properties | ForEach-Object { $estado[$_.Name] = $_.Value } } catch {}
}
$h = $null
try { $h = Herramientas $Laragon } catch { Anotar "No encontré las herramientas de Laragon: $_" }

# Avisa a los administradores (campana del sistema) una vez cada 12 h por tipo de problema.
function Alertar([string]$clave, [string]$titulo, [string]$mensaje) {
    Anotar "AVISO $titulo · $mensaje"
    $ultimo = $estado[$clave]
    if ($ultimo -and ((Get-Date) - [datetime]$ultimo).TotalHours -lt 12) { return }
    if ($h) {
        & $h.Php (Join-Path $proyecto 'artisan') 'sistema:avisar' $titulo $mensaje 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) { $estado[$clave] = (Get-Date).ToString('o') }
    }
}
function Resuelto([string]$clave) { $estado.Remove($clave) }

# ── Servicios ─────────────────────────────────────────────────────────
foreach ($s in 'TuboformasMySQL', 'TuboformasApache') {
    $svc = Get-Service $s -ErrorAction SilentlyContinue
    if (-not $svc) { continue }
    if ($svc.StartType -ne 'Automatic') { Set-Service $s -StartupType Automatic; Anotar "$s volvió a arranque automático." }
    if ($svc.Status -ne 'Running') {
        Anotar "$s estaba $($svc.Status): arrancándolo."
        Start-Service $s -ErrorAction SilentlyContinue
    }
}

# ── MySQL ─────────────────────────────────────────────────────────────
if (-not (Esperar-Puerto 3306 20)) {
    Anotar 'MySQL no responde: reiniciando TuboformasMySQL.'
    Restart-Service TuboformasMySQL -Force -ErrorAction SilentlyContinue
    if (-not (Esperar-Puerto 3306 60)) { Anotar 'MySQL sigue sin responder.' }
}

# ── Web ───────────────────────────────────────────────────────────────
function Web-Responde {
    try { return (Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1/up' -TimeoutSec 15).StatusCode -eq 200 } catch { return $false }
}
if (-not (Web-Responde)) {
    Start-Sleep -Seconds 20
    if (-not (Web-Responde)) {
        Anotar 'La web no responde: reiniciando TuboformasApache.'
        Restart-Service TuboformasApache -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 10
        if (Web-Responde) { Anotar 'La web volvió.' } else { Anotar 'La web sigue sin responder después de reiniciar Apache. Corre diagnostico.bat.' }
    }
}

# ── IP fija ───────────────────────────────────────────────────────────
$ipFija = if (Test-Path $env_) { Leer-Env $env_ 'SERVIDOR_IP' } else { '' }
if ($ipFija) {
    $dir = Get-NetIPAddress -IPAddress $ipFija -ErrorAction SilentlyContinue
    if ($dir -and $dir.AddressState -eq 'Duplicate') {
        Alertar 'ip-duplicada' 'Conflicto de IP en el servidor' "Otro equipo de la red tomó la dirección $ipFija. Desconecta ese equipo o corre ip-fija.ps1 con otra IP en el servidor."
    } elseif (-not $dir) {
        $conf = Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1
        $red = ($ipFija -split '\.')[0..2] -join '.'
        if ($conf -and $conf.IPv4DefaultGateway.NextHop -like "$red.*") {
            Anotar "La IP fija $ipFija desapareció: volviendo a ponerla."
            & (Join-Path $PSScriptRoot 'ip-fija.ps1') -Ip $ipFija -SinPreguntar *>> $log
        } elseif ($conf) {
            Alertar 'red-cambio' 'La red del servidor cambió' "El servidor ya no está en la red $red.x (¿cambiaron el router?). Los celulares no lo encontrarán en http://$ipFija. Corre ip-fija.ps1 en el servidor."
        }
    } else { Resuelto 'ip-duplicada'; Resuelto 'red-cambio' }
}

# ── Disco ─────────────────────────────────────────────────────────────
$unidad = Get-PSDrive -Name ($proyecto.Substring(0, 1)) -ErrorAction SilentlyContinue
if ($unidad -and $unidad.Free -lt 5GB) {
    Alertar 'disco' 'Poco espacio en el servidor' "Quedan $([math]::Round($unidad.Free / 1GB, 1)) GB en el disco $($unidad.Name):. Copia los respaldos viejos a otro lado y bórralos de la PC."
} else { Resuelto 'disco' }

# ── Respaldo ──────────────────────────────────────────────────────────
$respaldos = Join-Path $proyecto 'respaldos'
$ultimo = Get-ChildItem $respaldos -Filter 'tuboformas_*.zip' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$instalado = (Get-Item $env_ -ErrorAction SilentlyContinue).CreationTime
if ((-not $ultimo -and $instalado -and ((Get-Date) - $instalado).TotalHours -gt 36) -or ($ultimo -and ((Get-Date) - $ultimo.LastWriteTime).TotalHours -gt 36)) {
    Alertar 'respaldo' 'Respaldo atrasado' "El último respaldo del sistema es de $(if ($ultimo) { $ultimo.LastWriteTime.ToString('dd/MM HH:mm') } else { 'nunca' }). Corre respaldo.ps1 en el servidor o revisa la tarea Tuboformas\Respaldo."
} else { Resuelto 'respaldo' }

# ── Programador ───────────────────────────────────────────────────────
$info = Get-ScheduledTaskInfo -TaskPath '\Tuboformas\' -TaskName 'Programador' -ErrorAction SilentlyContinue
if ($info -and $info.LastRunTime -lt (Get-Date).AddMinutes(-10)) {
    Anotar "El programador no corre desde $($info.LastRunTime): arrancándolo."
    Enable-ScheduledTask -TaskPath '\Tuboformas\' -TaskName 'Programador' -ErrorAction SilentlyContinue | Out-Null
    Start-ScheduledTask -TaskPath '\Tuboformas\' -TaskName 'Programador' -ErrorAction SilentlyContinue
    Alertar 'programador' 'Tareas automáticas detenidas' 'Los preventivos, avisos y reportes no se estaban generando. El vigilante las volvió a arrancar; si se repite, corre diagnostico.bat.'
} elseif ($info) { Resuelto 'programador' }

$estado | ConvertTo-Json | Set-Content $archivoEstado -Encoding UTF8
