# Instalación en una PC de la planta (Windows)

El sistema corre en una PC con Windows que se queda encendida. Los técnicos entran desde el
celular o cualquier PC conectada al **mismo WiFi o red de la planta**, siempre a la misma dirección.

## Cómo se logra que la dirección no cambie

No hay acceso al router, así que no se puede "reservar" una IP. En su lugar:

1. **IP fija en la propia PC servidor.** El script toma la red que ya tiene (por ejemplo
   `192.168.1.x`) y le fija la terminación **`.250`**, que casi ningún router reparte. Antes de
   aplicarla revisa que nadie la esté usando. Esa es la **dirección oficial**: `http://192.168.1.250`.
   Funciona en todos los celulares y PCs.
2. **Nombre de la PC: `TUBOFORMAS`.** Las PCs con Windows y los iPhone también la encuentran como
   **`http://tuboformas.local`**, aunque cambie la IP. Muchos Android no reconocen los nombres
   `.local`: para ellos se usa la IP.
3. **Ícono en el celular.** Al abrir la dirección, en el menú del navegador se elige
   *Agregar a pantalla de inicio* (Android) o *Compartir → Agregar a inicio* (iPhone). Queda como una
   app con el logo de Tuboformas; nadie tiene que recordar la dirección.

> Si algún día cambian el router o el WiFi y la red pasa a otro rango (por ejemplo de `192.168.1.x`
> a `192.168.0.x`), corre de nuevo `deploy\windows\ip-fija.ps1` y reinstala el ícono en los celulares.

## Qué se necesita

- PC con Windows 10 u 11, que se quede encendida (desactiva la suspensión en *Configuración →
  Sistema → Inicio/apagado*). Mejor por cable que por WiFi.
- **Laragon completo** (trae Apache, MySQL, PHP, Composer, Node y Git): https://laragon.org/download
  → instalar en `C:\laragon`.
- Internet solo para la instalación y las actualizaciones. Para trabajar, el sistema no lo necesita.
  Los correos de reportes sí necesitan internet.

## Primera instalación

1. Instala Laragon. Ábrelo y pulsa **Iniciar todo** una vez (crea la configuración de MySQL).
2. Abre la **Terminal** de Laragon (botón *Terminal*) y baja el sistema:
   ```
   cd C:\laragon\www
   git clone https://github.com/jorge0123/tuboformas-sistema.git tuboformas
   ```
3. Abre **PowerShell como administrador** (clic derecho en Inicio → *Terminal (Administrador)*) y corre:
   ```
   cd C:\laragon\www\tuboformas\deploy\windows
   Set-ExecutionPolicy -Scope Process Bypass -Force
   .\instalar.ps1
   ```
   El script hace todo y al final muestra la **dirección** y la **contraseña inicial de `admin`**.
   Anótalas. Te pregunta antes de fijar la IP.
4. **Reinicia la PC** para que tome el nombre `TUBOFORMAS`.
5. Desde un celular conectado al WiFi de la planta, abre la dirección, entra como `admin`, cambia la
   contraseña en *Mi perfil* y crea los usuarios, turnos y máquinas.
6. Configura el correo y los reportes programados en *Administración → Configuración*.

Opciones del script:

| Opción | Para qué |
|---|---|
| `-Ip 192.168.0.240` | Usar otra IP fija en lugar de la terminación `.250` |
| `-SinIpFija` | No tocar la red (por ejemplo, si TI ya reservó la IP en el router) |
| `-SinServicios` | No registrar Apache y MySQL como servicios (se usa Laragon con arranque automático) |
| `-SinNombre` | No renombrar la PC |

## Qué queda funcionando

- **Servicios de Windows** `TuboformasApache` y `TuboformasMySQL`: arrancan solos al encender la PC,
  aunque nadie inicie sesión, y se reinician si se caen. Desde ahora **no uses *Iniciar todo* en
  Laragon** para este sistema.
- **Firewall**: el puerto 80 está abierto solo para la red local.
- **Tareas programadas** (*Programador de tareas → Tuboformas*):
  - *Programador*, cada minuto: crea los preventivos, envía avisos y reportes programados, y cierra
    las asistencias de quien no marcó salida.
  - *Respaldo*, cada día a las 22:30: base de datos más fotos y documentos en
    `C:\laragon\www\tuboformas\respaldos` (se guardan 30 días). **Copia esa carpeta a un USB o a la
    nube cada semana**: si la PC se daña, el respaldo que está en ella se pierde también.

## Actualizar a una versión nueva

PowerShell como administrador:
```
cd C:\laragon\www\tuboformas\deploy\windows
Set-ExecutionPolicy -Scope Process Bypass -Force
.\actualizar.ps1
```
Respalda, baja los cambios de GitHub, migra la base y vuelve a abrir el sistema (un par de minutos
en mantenimiento).

## Restaurar un respaldo

1. Descomprime el `.zip` del día que quieras.
2. En la terminal de Laragon (cambia la contraseña por la de `DB_PASSWORD` en el archivo `.env`):
   ```
   mysql -u tuboformas -p tuboformas < tuboformas_AAAA-MM-DD_HHMM.sql
   ```
3. Copia la carpeta `public` del respaldo sobre `C:\laragon\www\tuboformas\storage\app\public`.

## Si algo falla

| Problema | Qué revisar |
|---|---|
| Desde el celular no abre | El celular está en el mismo WiFi. En la PC servidor abre `http://127.0.0.1`: si abre ahí, es el firewall o la red (corre `ip-fija.ps1` para ver la IP actual). |
| `tuboformas.local` no abre | Usa la IP. En Android es normal. En PCs con Windows, reinicia el servidor. |
| "Ya la está usando otro equipo" al fijar la IP | `.\ip-fija.ps1 -Ip 192.168.1.240` (otra terminación alta). |
| Página de error | `C:\laragon\www\tuboformas\storage\logs` y `C:\laragon\bin\apache\httpd-*\logs\tuboformas-error.log` |
| Volver a IP automática | `.\ip-fija.ps1 -Volver` |
| No llegan los correos | *Configuración → Correo → Enviar prueba*. La PC necesita internet. |
