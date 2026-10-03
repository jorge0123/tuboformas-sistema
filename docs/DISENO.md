# Sistema Tuboformas — Diseño

Sistema web interno de mantenimiento para Tuboformas Guatemala (fabricante de accesorios de
PVC para instalaciones eléctricas). Reemplaza los libros de Excel de mantenimiento y lleva la
bodega de repuestos del taller. La bodega general (materia prima, producto terminado, pedidos
y entregas) no forma parte del sistema.

Este documento es la referencia de la lógica del sistema: qué módulos hay, cómo se
conectan, qué puede hacer cada rol. Si el código y este documento no coinciden, se
corrige uno de los dos en el mismo cambio.

## 1. Lo que había (Excel) y lo que lo reemplaza

| Hoja de Excel | Qué tenía | Módulo en el sistema |
|---|---|---|
| Listado de Maquinas Tuboformas | No., Área, Designación, Descripción, Marca, Observación, Manual; hipervínculo a la hoja de cada máquina | **Máquinas** (listado con filtros) |
| Hoja por máquina (Inyectora 1, Filtradora de Aceite 1…) — "Hoja Técnica" | Bloques por componente (Inyectora, Motor eléctrico trifásico, Bomba, Celda electrostática) con pares dato/valor; "Lista de Resistencias" (especificación, dimensiones, cantidad) | **Ficha técnica** de la máquina: componentes con especificaciones libres + tabla de partes/consumibles |
| Hoja por máquina — "Registro de Mantenimiento" | Mantenimiento ejecutado, fecha terminado, horas, responsable, comentarios (proveedor, garantía) | **Bitácora** de mantenimiento por máquina |
| Libro de tareas — hoja Data | Tarea, tipo, responsable, prioridad, inicio, vencimiento, situación (calculada), progreso %, horas, comentarios | **Órdenes de trabajo (OT)** — vista de lista |
| Libro de tareas — hoja KANBAN | Columnas Inicio / Progreso / Terminado / Preguntas y respuestas; filtros tipo, prioridad, situación, responsable | **Tablero Kanban** de OT |
| Libro de tareas — hoja Dashboard | Gráficas | **Tablero** y **Reportes** |
| (no existía) | — | Planes preventivos y calendario, herramientas por técnico, proveedores, bodega de repuestos con fotos |

## 2. Módulos

### 2.1 Máquinas
- Datos: número, código (designación: `AF1`, `CA1`, `BTF`…), nombre (descripción), área,
  marca, modelo, serie, año, ubicación, criticidad (A/B/C), estado, horómetro, observaciones, foto.
- Estados: `operativa`, `en_mantenimiento`, `fuera_servicio`, `baja`. Una máquina en `baja`
  no se elimina: queda para el historial (en Excel se marcaban como "(BAJA)" en el nombre).
- Ficha técnica: lista de **componentes** (ej. "Motor eléctrico trifásico"), cada uno con
  especificaciones clave/valor libres (Marca: Baldor, Potencia: 25HP…). Así sirve para
  cualquier tipo de máquina sin columnas fijas.
- **Partes y consumibles** (ej. lista de resistencias): especificación, dimensiones,
  cantidad, y opcionalmente el repuesto de la bodega al que corresponde (se ve su existencia).
- Documentos: manuales (operativo, de mantenimiento), diagramas (eléctricos, hidráulicos,
  neumáticos, lógicos) y fotos, como archivos adjuntos.
- Desde la máquina se llega a: su bitácora, sus OT, sus planes preventivos.

### 2.2 Órdenes de trabajo (OT)
Toda tarea de mantenimiento es una OT, venga de un reporte de falla, de un plan preventivo
o de un proyecto (ej. "instalar cisterna de entrada a fábrica").
- Campos: folio (`OT-000123`), título, descripción, máquina (opcional), tipo
  (`preventivo`, `correctivo`, `predictivo`, `mejora`, `ampliacion`, `proyecto`), especialidad
  (Eléctrico, Mecánico…), prioridad (`baja`, `media`, `alta`, `critica`), responsable,
  solicitante, fecha de inicio, fecha de vencimiento, progreso %, horas trabajadas,
  checklist, ¿detuvo la máquina? y horas de paro.
- Estados (columnas del Kanban): `pendiente` → `en_progreso` → `en_espera` → `completada`;
  además `cancelada`. `en_espera` exige un motivo (repuesto, cotización, proveedor…), que
  reemplaza la columna "Preguntas y respuestas" del Excel; las preguntas van en los
  **comentarios** de la OT.
- **Situación** (calculada, igual que en el Excel, nunca se captura):
  - abierta y vencida → `atrasada`
  - abierta y vence en ≤ 2 días → `por_vencer`
  - abierta → `en_tiempo`
  - completada después del vencimiento → `completada_tarde`; si no → `completada_a_tiempo`
- **Completar una OT crea automáticamente su registro en la bitácora de la máquina**
  (trabajo realizado, horas, responsable, repuestos). Es la regla "tiene que llegar a la bitácora".
- **Tiempo laboral**: el tiempo de una OT es el que los técnicos trabajaron en ella estando
  marcados (ver §2.10), no el tiempo de calendario. Si alguien trabaja 8 h, marca salida y al
  día siguiente le dedica 3 h más, la OT suma 11 h. Quien no marca asistencia captura las horas a mano.
- Repuestos usados en la OT: se descuentan de la bodega de repuestos con un movimiento
  `consumo_mantenimiento` ligado a la OT y a su máquina.

### 2.3 Bitácora de mantenimiento
Historial por máquina. Se llena sola al completar OT, y también se puede registrar a mano
(para cargar el historial de Excel o trabajos sin OT). Campos: fecha, tipo, componente,
trabajo realizado, horas, responsable (usuario o nombre de texto para históricos),
proveedor externo, reclamo por garantía, costo, lectura de horómetro, comentarios.

### 2.4 Planes preventivos y calendario
- Un plan = tarea que se repite: máquina, título, checklist, especialidad, responsable,
  frecuencia (cada N días / semanas / meses), próxima fecha, días de anticipación.
- Un proceso diario (`php artisan planes:generar`, programado en el cron de cPanel) crea la OT
  cuando `próxima fecha − anticipación ≤ hoy` y avanza la próxima fecha.
  Nunca crea una segunda OT si la anterior del mismo plan sigue abierta.
- El calendario muestra las OT por fecha de vencimiento y las próximas fechas de los planes.

### 2.5 Herramientas
- Catálogo: código, nombre, marca, categoría, estado (`disponible`, `asignada`,
  `en_reparacion`, `perdida`, `baja`), costo, foto.
- Asignación a un técnico con historial (quién, cuándo, en qué estado se entregó y se devolvió).
  Cada técnico ve "Mis herramientas".

### 2.6 Proveedores
Catálogo con NIT, contacto, teléfono, correo, tipo (repuestos, servicios, insumos)
y los repuestos que suministra (código del proveedor, precio, días de entrega).
Se usan en la bitácora (servicio externo) y en las compras de repuestos.

### 2.7 Bodega de repuestos
Una sola bodega con los repuestos e insumos del taller. La lógica vive en `App\Services\InventarioService`.
- **Repuestos**: código, nombre, tipo (`repuesto`, `insumo`), categoría, medida, unidad, existencia,
  stock mínimo, costo promedio, ubicación (estante), foto. La existencia es un campo del repuesto
  y solo la cambia `InventarioService`.
- **Movimientos** (con folio, líneas y fotos de evidencia):

  | Tipo | Efecto |
  |---|---|
  | Recepción de compra | entrada (con proveedor y factura; recalcula el costo promedio) |
  | Consumo en mantenimiento | salida (se registra desde la OT) |
  | Ajuste de entrada / salida | requiere aprobación |

- No se permite dejar existencias negativas.
- Costo promedio ponderado: se recalcula en cada compra con costo.
- Un movimiento confirmado no se edita ni se borra: se **anula**, y eso crea un movimiento
  de reverso. El kárdex siempre cuadra.
- Los **ajustes** quedan `pendiente` hasta que alguien con permiso de aprobar los aprueba.
  Si quien lo crea ya tiene ese permiso, se aplican al guardar.
- **Conteos físicos**: se abre un conteo (por tipo o categoría) que toma una foto de la existencia
  del sistema. Se captura lo contado y, al aplicar, las diferencias se vuelven ajustes aprobados.
- La lleva el **administrador de mantenimiento** (y el gerente de mantenimiento): no hay rol de bodega.

### 2.8 Tablero y reportes
- **Inicio** centrado en mantenimiento, según el rol: frase con lo atrasado, *mi jornada* (quien
  marca asistencia: tiempo en turno, en órdenes y la OT en curso), indicadores de OT, mis órdenes,
  las que requieren atención, "¿vamos al día?" (creadas contra completadas por semana), equipo de
  hoy con su OT en curso y ocupación, órdenes abiertas por estado, preventivos, máquinas. De la
  bodega solo aparece lo que pide acción (repuestos por pedir, ajustes por aprobar).
- Reporte de mantenimiento: resumen en una frase, completadas, a tiempo, cumplimiento del
  preventivo, paro y MTTR; órdenes por semana; abiertas por estado; máquinas por estado; trabajo
  por tipo y especialidad; técnicos (completadas, a tiempo, horas, abiertas, ocupación); máquinas
  con más correctivos con horas, paro y costos.
- Reporte de ocupación del personal: ocupación del equipo y por técnico, día por día, llegadas
  tarde, faltas, horas extra y salidas sin marcar.
- Gráficas: los tipos de OT usan una paleta categórica validada en orden fijo (`App\Support\Colores`);
  ocupación y estados usan colores de estado siempre con su texto (Buena ≥ 75 %, Media 50–74 %, Baja < 50 %).
- Reportes de repuestos: valor de la bodega, compras y consumo del período, repuestos más
  usados, costo de repuestos por máquina, valor por categoría, bajo mínimo y movimientos por tipo.
- Todo listado se puede exportar a Excel (CSV).

### 2.9 Administración
Usuarios, roles y permisos, catálogos (áreas, especialidades, categorías de repuesto, unidades)
y bitácora de auditoría (quién hizo qué y cuándo).

### 2.10 Asistencia y ocupación
Lógica en `App\Services\AsistenciaService`; cálculos en `App\Support\Ocupacion`.
- **Turnos** (Personal → Turnos): nombre, hora de entrada y salida, días, tolerancia. Una salida
  menor que la entrada termina al día siguiente (turno de noche). Cada persona tiene su turno
  (Usuarios). **Quien tiene turno marca asistencia**; quien no, no.
- **Marcar**: botón *Marcar entrada* / *Marcar salida* en la barra superior, en el inicio y en
  Mi asistencia. Sin entrada marcada no puede registrar avance, checklist, repuestos ni completar OT.
- **Tramos de trabajo** (`ot_tramos`): el tiempo de la persona en una OT corre desde que la pone en
  progreso o pulsa *Trabajar en esta orden*, y se detiene al pausar, pasar a otra OT (una a la vez),
  dejar la OT en espera, completarla/cancelarla o marcar salida. Al cerrar un tramo su tiempo se
  suma a las horas de la OT. Al marcar la siguiente entrada se reanuda la OT que quedó corriendo.
- **Salida olvidada**: `php artisan asistencia:cerrar` (cada 30 min por el cron) cierra la
  asistencia a la hora de fin del turno (o tras lo que dura el turno en un día que no le toca),
  la marca como *salida sin marcar* y avisa a la persona.
- **Corrección** (`asistencia.gestionar`): se cambian entrada y salida con un motivo; si cambia la
  salida, el tramo que se cortó con ella se ajusta y la OT suma o resta esa diferencia.
- **Ocupación** = horas en órdenes / horas marcadas. Llegada tarde: entrada después de la
  tolerancia. Horas extra: lo marcado por encima de lo que dura el turno. Falta: día de turno sin marca.
- Permisos: `asistencia.ver` (asistencia del personal y reporte de ocupación), `asistencia.gestionar`
  (turnos y correcciones).

## 3. Roles y permisos

Los permisos son `modulo.accion`. Un usuario tiene **un rol** (su plantilla de permisos) y
puede recibir **permisos adicionales** sueltos. El rol "Personalizado" no trae nada: se usa
para el contador externo, un auditor o un usuario temporal, marcando solo los módulos que necesita.
Los roles se pueden editar y se pueden crear nuevos desde la pantalla de Roles.

**Mecánico, electricista, etc. son el mismo rol: "Técnico de mantenimiento".** Lo que cambia
es su **especialidad** (Mecánico, Eléctrico, Electromecánico, Hidráulico/Neumático,
Refrigeración, Soldadura, Servicios generales). La especialidad sirve para asignar y
filtrar OT, no para dar permisos distintos. Así, si mañana entra un técnico de
refrigeración, no hace falta un rol nuevo.

| Rol | Para quién | Resumen de acceso |
|---|---|---|
| Super administrador | Dueño del sistema (TI) | Todo, incluido crear otros super administradores. No se puede eliminar. |
| Administrador | TI / administración | Todo, salvo tocar super administradores |
| Gerente general | Dirección | Ve todo, reportes, costos, aprueba ajustes; no configura el sistema |
| Gerente de mantenimiento | Jefe del área | Todo mantenimiento: máquinas, OT, planes, herramientas, proveedores, bodega de repuestos, reportes |
| Administrador de mantenimiento | Coordinador / planificador | Crea y asigna OT, planes, calendario, herramientas; lleva la bodega de repuestos (compras, ajustes, conteos, aprobaciones); no elimina máquinas |
| Técnico de mantenimiento | Mecánicos, electricistas… | Ve máquinas y fichas, **sus** OT, registra avance, bitácora y fotos, reporta fallas, ve sus herramientas |
| Supervisor de producción | Piso de planta | Ve máquinas, reporta fallas (crea OT) y sigue su estado |
| Contador | Contabilidad | Solo lectura: repuestos valorizados, movimientos, costos de mantenimiento, reportes y exportación |
| Consulta | Cualquiera de solo lectura | Ve todo lo operativo; sin costos ni administración |
| Personalizado | Usuario adicional | Sin permisos base; se marcan a mano |

La matriz completa está en `app/Support/Permisos.php` (fuente única) y el seeder la aplica.
El seeder borra los permisos y roles de sistema que ya no están en el catálogo; sus usuarios
pasan a **Consulta** hasta que un administrador les asigne otro rol.

Reglas que no dependen de la matriz:
- "Ver sus OT" = OT donde es responsable, ayudante o solicitante.
- Solo un super administrador puede crear, editar o desactivar a otro super administrador.
- Los usuarios se desactivan, no se borran (conservan su historial).

## 4. Arquitectura

- **Laravel 12** (PHP 8.2+), MySQL 8.
- Frontend: **Blade + Tailwind CSS + Alpine.js**, compilados con Vite en la PC de desarrollo.
  En el servidor solo se suben los archivos de `public/build`; no hace falta Node en cPanel.
- Roles y permisos: `spatie/laravel-permission`.
- Archivos (fotos, manuales) en `storage/app/public`, servidos con `php artisan storage:link`.
  Las fotos se comprimen en el navegador antes de subirse (las cámaras de celular generan archivos de 4 a 8 MB).
- Paleta tomada de tuboformas.com: rojo `#D90016`, negro `#1F1F1F`, grises cálidos; tipografía Montserrat.
- **Pantallas en vivo** (`resources/js/vivo.js`): los buscadores filtran mientras se escribe, y listas,
  tablero, Kanban y detalles se actualizan solos cada 30 s sin recargar (morph de `<main data-vivo>`).
  No se refresca si el usuario está escribiendo, tiene un formulario sin guardar, un diálogo abierto o
  está arrastrando; los formularios de crear/editar no se refrescan.
- **Celular** (`resources/js/movil.js` + `resources/css/app.css`): todo el sistema se usa en el teléfono.
  - Las `.tabla` se muestran como tarjetas debajo de 768 px (la primera celda es el título; el resto, en 2 o 3
    columnas con su encabezado como etiqueta). `<th data-movil="ocultar">` quita una columna en celular;
    `.tabla-fija` conserva la tabla.
  - Barra inferior (`Menu::barraInferior`): Inicio, Órdenes, Máquinas y "Reportar" al centro para quien
    puede crear OT. Las barras fijas de cada pantalla usan `.sobre-barra` para no quedar debajo.
  - Los filtros de `<x-filtros>` se pliegan tras un botón "Filtros (n)". Los campos usan 16 px (sin zoom en iPhone).
  - El calendario es una agenda por día; el mes queda como mapa con puntos.
- **Notificaciones**: `App\Notifications\Aviso` escribe la campana al instante (conexión `sync`) y solo
  el correo va en cola. La campana consulta cada 30 s, muestra un toast con enlace y el número en la pestaña.
  Avisos: OT asignada, nueva sin responsable o crítica, avance, comentario, en espera (a coordinadores),
  completada y cancelada; herramienta entregada y recibida; ajuste por aprobar, aprobado o rechazado;
  movimiento anulado; repuesto bajo el mínimo; conteo abierto y aplicado; salida sin marcar.
- Despliegue en cPanel: ver `docs/DESPLIEGUE.md`.

## 5. Pendiente / siguientes fases
- Captura de producción por máquina (piezas, merma, paros) y OEE — la propuesta original.
- Mantenimiento por horómetro (además de por calendario).
- Notificaciones por correo.
- Importar el Excel histórico de máquinas y bitácoras.
