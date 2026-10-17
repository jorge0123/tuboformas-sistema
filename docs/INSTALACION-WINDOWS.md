# Instalación en una PC de la planta (Windows)

El sistema corre en una PC con Windows que se queda encendida. Los técnicos entran desde el celular o
cualquier PC conectada al **mismo WiFi o red de la planta**, siempre a la misma dirección.

La planta no tiene departamento de TI: la instalación deja la PC funcionando **sola**. Arranca al
encender aunque nadie inicie sesión, se repara cuando algo se cae, respalda todas las noches y avisa
en la campana del sistema cuando necesita a alguien.

## Qué se necesita

- PC con **Windows 10 u 11 de 64 bits**, mínimo 8 GB de RAM y disco SSD. Que sea **solo para esto**.
  Mejor conectada **por cable** que por WiFi.
- **Laragon completo** instalado en `C:\laragon`: https://laragon.org/download (no hace falta abrirlo).
- **Internet durante la instalación** (descarga PHP si hace falta, dependencias y SSH). Para trabajar
  no se necesita; los correos de reportes y las actualizaciones sí.
- Recomendado: un **UPS** (no-break) y, en la BIOS de la PC, la opción *Restore on AC power loss →
  Power On*, para que se encienda sola al volver la luz.

## Primera instalación

1. Instala Laragon.
2. Baja el sistema. En la terminal de Laragon (`C:\laragon\laragon.exe` → *Terminal*):
   ```
   cd C:\laragon\www
   git clone https://github.com/jorge0123/tuboformas-sistema.git tuboformas
   ```
3. Abre la carpeta `C:\laragon\www\tuboformas\deploy\windows` y haz **doble clic en `instalar.bat`**.
   Pide permiso de administrador y hace todo; solo pregunta antes de fijar la IP.
   Al final muestra la **dirección**, la **contraseña inicial de `admin`** y cómo entrar por SSH.
   Anótalas. Todo lo que hizo queda en `deploy\windows\instalacion.log`.
4. **Reinicia la PC** (toma el nombre `TUBOFORMAS`; todo vuelve a arrancar solo).
5. Desde un celular en el WiFi de la planta abre la dirección, entra como `admin`, cambia la contraseña
   en *Mi perfil* y crea usuarios, turnos y máquinas.
6. Configura el correo y los reportes en *Administración → Configuración*.
7. Configura la **copia externa del respaldo** (ver *Respaldos*).

El sistema queda **en blanco, solo con el usuario `admin`**. Si después de capacitar quieren borrar
los datos de prueba: doble clic en **`limpiar.bat`** (pide escribir `BORRAR`, respalda antes y da una
contraseña nueva para `admin`).

Opciones de `instalar.ps1` (desde PowerShell como administrador):

| Opción | Para qué |
|---|---|
| `-Ip 192.168.0.240` | Otra IP fija en lugar de la terminación `.250` |
| `-SinIpFija` | No tocar la red (si TI ya reservó la IP en el router) |
| `-LlaveSsh "ssh-ed25519 AAAA…"` | Entrar por SSH con llave, sin contraseña (también acepta la ruta a un `.pub`) |
| `-SinSsh` | No activar SSH |
| `-SinServicios` | No registrar Apache y MySQL como servicios (se usa Laragon con arranque automático) |
| `-SinNombre` | No renombrar la PC |

## Qué hace el instalador

- **PHP:** el sistema pide PHP 8.3 o más. Si el de Laragon es viejo, baja **PHP 8.4 oficial**
  (verificado con su SHA-256) y el runtime de Visual C++. Lo ajusta para fotos de 50 MB, hora de
  Guatemala y OPcache. Apache y las tareas usan el mismo PHP.
- **La PC como servidor:**
  - conectada a la corriente nunca se suspende;
  - la tarjeta de red no se apaga para ahorrar energía, y el WiFi se reconecta solo;
  - Windows Defender no revisa en cada visita las carpetas del sistema (mucho más rápido);
  - la hora se sincroniza sola (la asistencia depende de ella);
  - Windows Update no reinicia entre 5:00 y 23:00.
- **Servicios de Windows** `TuboformasMySQL` y `TuboformasApache`:
  - arrancan al encender la PC, aunque nadie inicie sesión, y Windows los reinicia si se caen;
  - MySQL se inicializa aunque Laragon nunca se haya abierto, y **solo escucha a la propia PC**;
  - Laragon deja de arrancar solo, para que no choque con los servicios. **No uses *Iniciar todo*
    de Laragon para este sistema.**
- **Apache:** el sistema es el sitio por defecto del puerto 80 (responde a la IP y a
  `tuboformas.local`). El texto viaja comprimido, el diseño queda en caché del celular y los registros
  rotan solos.
- **Firewall:** los puertos 80 (web) y 22 (SSH) quedan abiertos **solo para la red local**.
- **Accesos directos** en el escritorio: *Sistema Tuboformas* y *Diagnóstico Tuboformas*.

## Cómo se logra que la dirección no cambie

No hay acceso al router, así que no se puede "reservar" una IP. En su lugar:

1. **IP fija en la propia PC.** El script toma la red que ya tiene (por ejemplo `192.168.1.x`) y le
   fija la terminación **`.250`**, que casi ningún router reparte. Antes revisa con ping **y** con la
   tabla ARP que nadie la use (muchos celulares no contestan el ping). Esa es la **dirección oficial**:
   `http://192.168.1.250`.
2. **Nombre `TUBOFORMAS`:** las PCs con Windows y los iPhone también la encuentran como
   `http://tuboformas.local`. Muchos Android no reconocen `.local`: ahí se usa la IP.
3. **Ícono en el celular:** al abrir la dirección, *Agregar a pantalla de inicio* (Android) o
   *Compartir → Agregar a inicio* (iPhone).

El **vigilante** comprueba cada 5 minutos que la IP siga puesta y la vuelve a poner si alguien la
quitó. Si otro equipo toma la misma IP, o la red cambia de rango (router nuevo), avisa a los
administradores en la campana. En ese caso corre `ip-fija.ps1` otra vez.

## Lo que corre solo

| Tarea (*Programador de tareas → Tuboformas*) | Cuándo | Qué hace |
|---|---|---|
| **Programador** | Cada minuto | Preventivos, correos, reportes programados y cierre de asistencias olvidadas |
| **Vigilante** | Cada 5 min y al encender | Levanta servicios caídos; reinicia MySQL o Apache si no responden; vuelve a poner la IP fija; avisa si hay poco disco, el respaldo está atrasado o las tareas se detuvieron |
| **Respaldo** | 22:30 (o al encender, si estaba apagada) | Base de datos + fotos y documentos, 30 días |

Todas corren como SYSTEM: funcionan sin sesión iniciada y también con batería (laptop o UPS).
Lo que hace el vigilante queda en `storage\logs\vigilante.log`. Sus avisos llegan a la campana de
los administradores, una vez cada 12 horas por problema.

## Respaldos

- Se guardan en `C:\laragon\www\tuboformas\respaldos`.
- **Copia externa:** en el archivo `.env` del sistema pon, por ejemplo,
  `RESPALDO_COPIA=E:\RespaldosTuboformas` (un USB que se quede conectado) o una carpeta de OneDrive
  o Google Drive para escritorio. Cada noche se deja ahí una segunda copia. Si la PC se daña, el
  respaldo que está en ella se pierde con ella.
- **Restaurar:** doble clic en **`restaurar.bat`** (usa el más reciente). Para uno en particular:
  `.\restaurar.ps1 -Archivo E:\RespaldosTuboformas\tuboformas_2026-10-15_2230.zip`.
  Antes de restaurar respalda lo que hay, por si se eligió el equivocado.

## Soporte a distancia (SSH)

El instalador activa el **servidor SSH de Windows**. Desde otra PC de la red:
```
ssh usuario@192.168.1.250
```
con el usuario y la contraseña de Windows del servidor (o la llave de `-LlaveSsh`). Entra directo
a PowerShell; desde ahí se puede correr cualquier script, por ejemplo
`C:\laragon\www\tuboformas\deploy\windows\diagnostico.ps1`.

SSH solo está abierto a la red de la planta. **Para entrar desde fuera** (tu casa u oficina) instala
**Tailscale** en el servidor y en tu PC, con la misma cuenta: el servidor queda en una red privada
cifrada, sin abrir puertos en el router, y se entra igual con `ssh usuario@tuboformas`.

## Actualizar a una versión nueva

Doble clic en **`actualizar.bat`** (o por SSH: `.\actualizar.ps1`). Respalda, baja los cambios de
GitHub, migra la base y vuelve a abrir el sistema (un par de minutos en mantenimiento). También
actualiza lo del servidor: sitio, PHP y tareas.

## Si algo falla

Primero haz **doble clic en *Diagnóstico Tuboformas*** (escritorio). Revisa todo y deja un reporte
`.txt` en el escritorio para enviar a soporte.

| Problema | Qué revisar |
|---|---|
| Desde el celular no abre | Que el celular esté en el mismo WiFi. Si en el servidor `http://127.0.0.1` abre, es la red: corre el diagnóstico. |
| `tuboformas.local` no abre | Usa la IP. En Android es normal. |
| "Ya la está usando otro equipo" al fijar la IP | `.\ip-fija.ps1 -Ip 192.168.1.240` (otra terminación alta) |
| Aviso "Conflicto de IP en el servidor" | Otro equipo tomó la IP: desconéctalo o cambia la del servidor con `ip-fija.ps1 -Ip …` |
| Página de error | `storage\logs` del sistema y `C:\laragon\bin\apache\httpd-*\logs\tuboformas-error.log` |
| Volver a IP automática | `.\ip-fija.ps1 -Volver` |
| No llegan los correos | *Configuración → Correo → Enviar prueba*. La PC necesita internet. |
