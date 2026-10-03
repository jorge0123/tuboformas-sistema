<#
.SYNOPSIS
    Deja una IP fija en esta PC para que el sistema tenga siempre la misma dirección en la red de la planta.

.DESCRIPTION
    Sin acceso al router no se puede "reservar" una IP; en su lugar se fija en la propia PC, tomando
    la red, la puerta de enlace y los DNS que el WiFi/cable le dio. Por defecto usa la terminación .250
    (casi nunca la reparte un router doméstico). Antes de aplicarla comprueba que nadie más la esté usando.

.EXAMPLE
    .\ip-fija.ps1                  # propone 192.168.x.250 en la red actual
    .\ip-fija.ps1 -Ip 192.168.0.240
    .\ip-fija.ps1 -Volver          # regresa a IP automática (DHCP)
#>
param(
    [string]$Ip,
    [switch]$Volver,
    [switch]$SinPreguntar
)
. (Join-Path $PSScriptRoot 'comun.ps1')
Exigir-Administrador

$conf = Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1
if (-not $conf) { Falla 'Esta PC no está conectada a ninguna red con puerta de enlace. Conéctala al WiFi o cable de la planta.' }
$indice = $conf.InterfaceIndex
$adaptador = $conf.InterfaceAlias

if ($Volver) {
    Paso "Regresando $adaptador a IP automática (DHCP)"
    Remove-NetRoute -InterfaceIndex $indice -DestinationPrefix '0.0.0.0/0' -Confirm:$false -ErrorAction SilentlyContinue
    Set-NetIPInterface -InterfaceIndex $indice -Dhcp Enabled
    Set-DnsClientServerAddress -InterfaceIndex $indice -ResetServerAddresses
    Ok 'Listo. La IP vuelve a asignarla el router.'
    return
}

$actual = Get-NetIPAddress -InterfaceIndex $indice -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '169.254*' } | Select-Object -First 1
$prefijo = $actual.PrefixLength
$puerta = $conf.IPv4DefaultGateway.NextHop
$dns = @((Get-DnsClientServerAddress -InterfaceIndex $indice -AddressFamily IPv4).ServerAddresses)
if (-not $dns -or $dns.Count -eq 0) { $dns = @($puerta) }

Paso "Red actual ($adaptador)"
Ok "IP: $($actual.IPAddress)/$prefijo  ·  Puerta de enlace: $puerta  ·  DNS: $($dns -join ', ')  ·  Origen: $($actual.PrefixOrigin)"

if (-not $Ip) {
    if ($prefijo -ne 24) { Falla "La red usa /$prefijo; indica la IP a mano: .\ip-fija.ps1 -Ip <dirección>" }
    $partes = $actual.IPAddress.Split('.')
    $Ip = "$($partes[0]).$($partes[1]).$($partes[2]).250"
}

if ($actual.IPAddress -eq $Ip -and $actual.PrefixOrigin -eq 'Manual') {
    Ok "Ya tiene la IP fija $Ip. No hay nada que cambiar."
    return
}

if ($actual.IPAddress -ne $Ip -and (Test-Connection -ComputerName $Ip -Count 2 -Quiet)) {
    Falla "La IP $Ip ya la está usando otro equipo. Elige otra: .\ip-fija.ps1 -Ip <otra dirección de la misma red>"
}

if (-not $SinPreguntar) {
    Aviso "Se va a fijar la IP $Ip en '$adaptador'. La conexión se corta unos segundos."
    $r = Read-Host '¿Continuar? (s/n)'
    if ($r -notmatch '^[sS]') { Aviso 'Cancelado.'; return }
}

Paso "Aplicando IP fija $Ip"
Set-NetIPInterface -InterfaceIndex $indice -Dhcp Disabled
Get-NetIPAddress -InterfaceIndex $indice -AddressFamily IPv4 -ErrorAction SilentlyContinue | Remove-NetIPAddress -Confirm:$false -ErrorAction SilentlyContinue
Remove-NetRoute -InterfaceIndex $indice -DestinationPrefix '0.0.0.0/0' -Confirm:$false -ErrorAction SilentlyContinue
New-NetIPAddress -InterfaceIndex $indice -IPAddress $Ip -PrefixLength $prefijo -DefaultGateway $puerta | Out-Null
Set-DnsClientServerAddress -InterfaceIndex $indice -ServerAddresses $dns
Start-Sleep -Seconds 3

if (Test-Connection -ComputerName $puerta -Count 2 -Quiet) {
    Ok "Listo. La dirección del sistema es http://$Ip"
} else {
    Aviso "La IP quedó puesta pero no responde la puerta de enlace $puerta. Revisa la conexión o vuelve atrás con: .\ip-fija.ps1 -Volver"
}
