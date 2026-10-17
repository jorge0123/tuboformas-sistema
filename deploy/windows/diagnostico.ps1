<#
.SYNOPSIS
    Diagnóstico del servidor: revisa todo y deja un reporte en el escritorio para enviarlo a soporte.
#>
param([string]$Laragon = 'C:\laragon')
. (Join-Path $PSScriptRoot 'comun.ps1')
$ErrorActionPreference = 'Continue'

$proyecto = Carpeta-Proyecto
$env_ = Join-Path $proyecto '.env'
$lineas = New-Object System.Collections.Generic.List[string]
function Linea([string]$estado, [string]$que, [string]$detalle) {
    $color = @{ 'OK' = 'Green'; 'AVISO' = 'Yellow'; 'FALLA' = 'Red' }[$estado]
    Write-Host ("  {0,-6} {1,-22} {2}" -f $estado, $que, $detalle) -ForegroundColor $color
    $lineas.Add(("{0,-6} {1,-22} {2}" -f $estado, $que, $detalle))
}

Write-Host "`nDiagnóstico Tuboformas · $(Get-Date -Format 'dd/MM/yyyy HH:mm')" -ForegroundColor White
$lineas.Add("Diagnóstico Tuboformas · $(Get-Date -Format 'dd/MM/yyyy HH:mm') · PC $env:COMPUTERNAME")
$so = Get-CimInstance Win32_OperatingSystem
Linea 'OK' 'Windows' "$($so.Caption) · encendida desde $($so.LastBootUpTime.ToString('dd/MM HH:mm'))"

foreach ($s in 'TuboformasMySQL', 'TuboformasApache', 'sshd') {
    $svc = Get-Service $s -ErrorAction SilentlyContinue
    if (-not $svc) { Linea 'AVISO' $s 'no instalado'; continue }
    Linea $(if ($svc.Status -eq 'Running') { 'OK' } else { 'FALLA' }) $s "$($svc.Status) · arranque $($svc.StartType)"
}

$ipFija = if (Test-Path $env_) { Leer-Env $env_ 'SERVIDOR_IP' } else { '' }
$conf = Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1
if ($conf) {
    $ip = $conf.IPv4Address.IPAddress | Select-Object -First 1
    $origen = (Get-NetIPAddress -IPAddress $ip -ErrorAction SilentlyContinue).PrefixOrigin
    $estadoIp = if ($ipFija -and $ip -ne $ipFija) { 'FALLA' } elseif ($origen -eq 'Manual') { 'OK' } else { 'AVISO' }
    Linea $estadoIp 'Red' "$($conf.InterfaceAlias): $ip ($(if ($origen -eq 'Manual') { 'fija' } else { 'automática' }))$(if ($ipFija) { " · esperada $ipFija" }) · puerta $($conf.IPv4DefaultGateway.NextHop)"
    Linea $(if (Test-Connection $conf.IPv4DefaultGateway.NextHop -Count 1 -Quiet) { 'OK' } else { 'FALLA' }) 'Router' 'responde al ping'
} else { Linea 'FALLA' 'Red' 'sin conexión a ninguna red' }
Linea $(if (Test-Connection 8.8.8.8 -Count 1 -Quiet) { 'OK' } else { 'AVISO' }) 'Internet' '(solo se necesita para correos y actualizaciones)'

Linea $(if (Esperar-Puerto 3306 5) { 'OK' } else { 'FALLA' }) 'MySQL (3306)' 'puerto'
$t = Measure-Command { try { $web = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1/up' -TimeoutSec 15 } catch { $web = $null } }
Linea $(if ($web -and $web.StatusCode -eq 200) { 'OK' } else { 'FALLA' }) 'Web (/up)' "$(if ($web) { $web.StatusCode } else { 'sin respuesta' }) en $([int]$t.TotalMilliseconds) ms"

try {
    $h = Herramientas $Laragon
    Linea 'OK' 'PHP' "$($h.PhpVersion) · $($h.PhpDir)"
    $about = & $h.Php (Join-Path $proyecto 'artisan') 'about' '--only=environment' 2>&1 | Select-String 'Environment|Debug|URL'
    foreach ($a in $about) { Linea 'OK' 'Laravel' ($a.Line.Trim() -replace '\s{2,}\.*\s*', ': ') }
} catch { Linea 'FALLA' 'PHP' "$_" }

$unidad = Get-PSDrive -Name ($proyecto.Substring(0, 1))
Linea $(if ($unidad.Free -gt 5GB) { 'OK' } else { 'AVISO' }) 'Disco' "$([math]::Round($unidad.Free / 1GB, 1)) GB libres en $($unidad.Name):"
$ram = [math]::Round($so.FreePhysicalMemory / 1MB, 1)
Linea $(if ($ram -gt 0.5) { 'OK' } else { 'AVISO' }) 'Memoria' "$ram GB libres de $([math]::Round($so.TotalVisibleMemorySize / 1MB, 1)) GB"

$ultimo = Get-ChildItem (Join-Path $proyecto 'respaldos') -Filter 'tuboformas_*.zip' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if ($ultimo) {
    Linea $(if (((Get-Date) - $ultimo.LastWriteTime).TotalHours -lt 36) { 'OK' } else { 'AVISO' }) 'Último respaldo' "$($ultimo.LastWriteTime.ToString('dd/MM HH:mm')) · $([math]::Round($ultimo.Length / 1MB, 1)) MB"
} else { Linea 'AVISO' 'Último respaldo' 'ninguno todavía' }
$copia = if (Test-Path $env_) { Leer-Env $env_ 'RESPALDO_COPIA' } else { '' }
if ($copia) { Linea $(if (Test-Path $copia) { 'OK' } else { 'AVISO' }) 'Copia externa' $copia } else { Linea 'AVISO' 'Copia externa' 'sin configurar (RESPALDO_COPIA en .env)' }

foreach ($tarea in 'Programador', 'Vigilante', 'Respaldo') {
    $i = Get-ScheduledTaskInfo -TaskPath '\Tuboformas\' -TaskName $tarea -ErrorAction SilentlyContinue
    if (-not $i) { Linea 'FALLA' "Tarea $tarea" 'no existe'; continue }
    Linea $(if ($i.LastTaskResult -in 0, 267009, 267011) { 'OK' } else { 'AVISO' }) "Tarea $tarea" "última vez $($i.LastRunTime.ToString('dd/MM HH:mm')) · resultado $($i.LastTaskResult)"
}

$lineas.Add('')
$lineas.Add('── Últimos errores del sistema ──')
$logLaravel = Get-ChildItem (Join-Path $proyecto 'storage\logs') -Filter 'laravel*.log' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if ($logLaravel) { Select-String -Path $logLaravel.FullName -Pattern '\.(ERROR|CRITICAL)' | Select-Object -Last 10 | ForEach-Object { $lineas.Add($_.Line.Substring(0, [Math]::Min(300, $_.Line.Length))) } }
$lineas.Add('── Vigilante ──')
Get-Content (Join-Path $proyecto 'storage\logs\vigilante.log') -Tail 15 -ErrorAction SilentlyContinue | ForEach-Object { $lineas.Add($_) }

$reporte = Join-Path ([Environment]::GetFolderPath('Desktop')) "diagnostico-tuboformas-$(Get-Date -Format 'yyyyMMdd-HHmm').txt"
$lineas | Set-Content $reporte -Encoding UTF8
Write-Host "`nReporte guardado en el escritorio: $(Split-Path $reporte -Leaf) (envíalo a soporte)." -ForegroundColor White
