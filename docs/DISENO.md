# Sistema Tuboformas — Diseño

Sistema web interno para Tuboformas Guatemala (fabricante de accesorios de PVC para
instalaciones eléctricas). Reemplaza los libros de Excel de mantenimiento y el registro
manual de bodega.

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
| (no existía) | — | Planes preventivos y calendario, herramientas por técnico, proveedores y catálogo de repuestos, inventario de bodega con fotos |

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
  cantidad, y opcionalmente el repuesto de inventario al que corresponde.
- Documentos: manuales (operativo, de mantenimiento), diagramas (eléctricos, hidráulicos,
  neumáticos, lógicos) y fotos, como archivos adjuntos.
- Desde la máquina se llega a: su bitácora, sus OT, sus planes preventivos.

### 2.2 Órdenes de trabajo (OT)
Toda tarea de mantenimiento es una OT, venga de un reporte de falla, de un plan preventivo
o de un proyecto (ej. "instalar cisterna de entrada a fábrica").
- Campos: folio (`OT-000123`), título, descripción, máquina (opcional), tipo
  (`preventivo`, `correctivo`, `predictivo`, `mejora`, `proyecto`), especialidad
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
- Repuestos usados en la OT: se descuentan del inventario con un movimiento
  `consumo_mantenimiento` ligado a la OT.

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
Catálogo con NIT, contacto, teléfono, correo, tipo (repuestos, servicios, materia prima)
y los productos o repuestos que suministra (código del proveedor, precio, días de entrega).
Se usan en la bitácora (servicio externo) y en recepciones de bodega.

### 2.7 Bodega e inventario
Sigue el flujo de la planta: compra de materia prima → ingreso a bodega → salida a producción
→ (corte, formado, curvado) → conteo y empaque → ingreso de producto terminado.
- **Productos**: código, nombre, tipo (`materia_prima`, `producto_terminado`, `repuesto`,
  `insumo`), categoría, medida (1/2", 3/4"…), unidad base, stock mínimo, costo promedio, foto.
- **Presentaciones**: cómo se cuenta en físico. Ej. "Copla 3/4" → Bolsa x 300 = 300 unidades;
  "Tubo 3/4" → Manojo = N tubos. El movimiento se captura en presentación y el sistema lo
  convierte a unidad base.
- **Bodegas**: Materia prima, Producto terminado, Repuestos (configurable). Existencia por producto y bodega.
- **Movimientos** (con folio, líneas y fotos de evidencia tomadas con la cámara del celular):

  | Tipo | Efecto |
  |---|---|
  | Recepción de compra | entrada (con proveedor y documento) |
  | Ingreso de producto terminado | entrada |
  | Devolución de producción | entrada |
  | Salida a producción | salida (a qué máquina / área) |
  | Despacho | salida |
  | Consumo en mantenimiento | salida (ligado a OT) |
  | Traslado entre bodegas | salida de una, entrada a otra |
  | Ajuste de entrada / salida | requiere aprobación |

- **Variantes de color**: el mismo producto en otro color (Copla 3/4" gris / naranja) es otro
  producto con el mismo nombre, su propio código y el campo `color`. Cada uno lleva su existencia.
- **Etiquetas QR** (Bodega → Etiquetas QR): se arma una hoja eligiendo producto, presentación y
  número de copias (ej. 20 × Copla 3/4" gris, Bolsa x 300) y se imprime para pegar en bolsas o cajas.
  El QR contiene `TF:<código>:<id de presentación>` (0 = unidad base). La etiqueta **personalizada**
  ("Bolsa x 4 pza") agrega las unidades: `TF:<código>:0:<unidades>`, y al escanearla se proponen esas unidades.
- **Ingreso rápido** (Bodega → Ingreso rápido, permiso `movimientos.crear`: auxiliar, administrador
  y gerente de bodega): pantalla para celular. Se escanea el QR con la cámara (requiere https) o con
  una foto (funciona también en http); el sistema propone "¿Registrar Copla 3/4" gris, 300 pza?",
  se puede cambiar color, presentación o cantidad, y al final se registra un solo
  `ingreso_produccion` con todas las líneas. Las líneas sin registrar se guardan en el navegador.
- No se permite dejar existencias negativas.
- Costo promedio ponderado: se recalcula en cada recepción con costo.
- Un movimiento confirmado no se edita ni se borra: se **anula**, y eso crea un movimiento
  de reverso. El kárdex siempre cuadra.
- Los **ajustes** quedan `pendiente` hasta que alguien con permiso de aprobar los aprueba.
  Si quien lo crea ya tiene ese permiso, se aplican al guardar.
- **Conteos físicos**: se abre un conteo por bodega, que toma una foto de la existencia del
  sistema. Se captura lo contado y, al aplicar, las diferencias se vuelven ajustes aprobados.

### 2.8 Tablero y reportes
- Tablero según el rol: OT abiertas, atrasadas, por vencer, preventivos de la semana, mis OT,
  productos bajo mínimo, movimientos pendientes de aprobar.
- Reportes de mantenimiento: OT por estado, tipo y especialidad; cumplimiento del preventivo;
  horas por técnico; máquinas con más correctivos; horas de paro; costo por máquina.
- Reportes de bodega: existencias (valorizadas si el usuario puede ver costos), bajo
  mínimo, kárdex, movimientos por tipo y período.
- Todo listado se puede exportar a Excel (CSV).

### 2.9 Administración
Usuarios, roles y permisos, catálogos (áreas, especialidades, bodegas, categorías, unidades)
y bitácora de auditoría (quién hizo qué y cuándo).

### 2.8 Pedidos de clientes (órdenes de entrega)
Ventas (vendedor, secretaria o contador) ingresa el pedido; bodega lo arma y lo despacha.
- **Clientes**: nombre, NIT, contacto, teléfono, dirección, municipio. Se pueden dar de alta sin salir del pedido.
- **Pedido** (`PED-000123`): cliente, fecha de entrega y jornada, tipo de entrega (`ruta`: con nuestro camión; `recoge`: el cliente pasa a la planta; `transporte`: por paquetería
  —Cargo Expreso, Guatex…— con número de guía, para clientes lejos),
  prioridad (normal/urgente), dirección y quién recibe (se llenan con los del cliente), orden de compra,
  condición de pago, indicaciones para bodega, bodega de salida y productos (en su presentación: 2 Bolsa x 300).
- **Estados**: `nuevo` → `preparando` → `listo` → `en_ruta` → `entregado`; `cancelado`.
  Quien recoge en planta pasa de `listo` a `entregado`.
  - Al crearlo se avisa a todo bodega (`pedidos.preparar`). Quien lo toma queda como responsable.
  - En preparación se marca cada producto al armarlo; si no alcanzó se indica cuánto se armó.
    "Listo" exige todas las líneas marcadas.
  - **El inventario se descuenta al despachar** (o al entregar si lo recoge el cliente) con un
    `salida_despacho` ligado al pedido, por lo que realmente se armó. Si no hay existencia, no se despacha.
  - **Despacho**: en ruta propia se elige el vehículo (Máquinas del área *Vehículos*) y el piloto (usuario
    marcado como *Es piloto*); por transporte, la empresa y el número de guía. El piloto recibe el aviso
    "Tienes una entrega" y confirma la entrega desde su teléfono. No se pide factura: el despacho queda
    referenciado con la guía, la OC del cliente o el folio del pedido.
  - Avisos: nuevo/modificado/cancelado sin tomar → bodega; tomado, en ruta → ventas; listo → ventas y bodega;
    despachado → piloto; **entregado → ventas, quien armó, quien despachó y el piloto**; comentarios → todos
    los involucrados. Nunca se avisa a quien hizo la acción.
  - El vendedor recibe aviso en cada paso. Solo se edita mientras está `nuevo`; se cancela hasta `listo`
    (después, se anula el despacho en Movimientos).
- **Viajes de entrega** (`VIA-00001`): un camión y su piloto llevan varios pedidos en orden de paradas.
  Se arman con los pedidos listos de ruta (agrupados por municipio) y al salir se despachan todos juntos;
  si a uno no le alcanza la existencia, no sale ninguno. Un camión o piloto con viaje en ruta no puede salir
  en otro. El piloto recibe un solo aviso, ve las paradas con "Cómo llegar"/"Llamar" y la ruta completa en
  Google Maps, y marca cada parada como entregada o **no entregada** (el pedido regresa a bodega: se anula su
  salida y queda "listo"). El viaje se cierra solo con la última parada y avisa a bodega con el resumen.
  Lógica en `App\Services\ViajeService`.
- Lógica en `App\Services\PedidoService`. Rol **Ventas**: `pedidos.ver`, `pedidos.crear`, `clientes.gestionar`.
  Ventas ve sus pedidos; bodega y quien tenga `pedidos.ver_todos` ve todos.

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
| Gerente de mantenimiento | Jefe del área | Todo mantenimiento: máquinas, OT, planes, herramientas, proveedores, reportes |
| Administrador de mantenimiento | Coordinador / planificador | Crea y asigna OT, planes, calendario, herramientas; no elimina máquinas |
| Técnico de mantenimiento | Mecánicos, electricistas… | Ve máquinas y fichas, **sus** OT, registra avance, bitácora y fotos, reporta fallas, ve sus herramientas |
| Gerente de bodega | Jefe de bodega | Todo bodega e inventario, costos, aprueba y anula, reportes |
| Administrador de bodega | Encargado de bodega | Productos, movimientos, conteos, aprueba ajustes |
| Auxiliar de bodega | Personal de bodega | Registra entradas, salidas y conteos con foto; no aprueba ni anula |
| Ventas | Vendedores, secretaría | Ingresa pedidos de clientes y clientes nuevos; sigue la preparación y entrega de **sus** pedidos |
| Supervisor de producción | Piso de planta | Ve máquinas e inventario, reporta fallas (crea OT) y sigue su estado |
| Contador | Contabilidad | Solo lectura: inventario valorizado, movimientos, costos de mantenimiento, reportes y exportación |
| Consulta | Cualquiera de solo lectura | Ve todo lo operativo; sin costos ni administración |
| Personalizado | Usuario adicional | Sin permisos base; se marcan a mano |

La matriz completa está en `app/Support/Permisos.php` (fuente única) y el seeder la aplica.

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
  está arrastrando; los formularios de crear/editar y Bodega (ingreso rápido, etiquetas) no se refrescan.
- **Celular** (`resources/js/movil.js` + `resources/css/app.css`): todo el sistema se usa en el teléfono.
  - Las `.tabla` se muestran como tarjetas debajo de 768 px (la primera celda es el título; el resto, en 2 o 3
    columnas con su encabezado como etiqueta). `<th data-movil="ocultar">` quita una columna en celular;
    `.tabla-fija` conserva la tabla.
  - Barra inferior según el rol (`Menu::barraInferior`): bodega tiene "Escanear" al centro, mantenimiento
    "Reportar". Las barras fijas de cada pantalla usan `.sobre-barra` para no quedar debajo.
  - Los filtros de `<x-filtros>` se pliegan tras un botón "Filtros (n)". Los campos usan 16 px (sin zoom en iPhone).
  - El calendario es una agenda por día; el mes queda como mapa con puntos.
- **Notificaciones**: `App\Notifications\Aviso` escribe la campana al instante (conexión `sync`) y solo
  el correo va en cola. La campana consulta cada 30 s, muestra un toast con enlace y el número en la pestaña.
  Avisos: OT asignada, nueva sin responsable o crítica, avance, comentario, en espera (a coordinadores),
  completada y cancelada; herramienta entregada y recibida; ajuste por aprobar, aprobado o rechazado;
  movimiento anulado; stock bajo el mínimo; conteo abierto y aplicado.
- Despliegue en cPanel: ver `docs/DESPLIEGUE.md`.

## 5. Pendiente / siguientes fases
- Captura de producción por máquina (piezas, merma, paros) y OEE — la propuesta original.
- Solicitudes de materia prima de producción a bodega con flujo de aprobación.
- Mantenimiento por horómetro (además de por calendario).
- Notificaciones por correo.
- Importar el Excel histórico de máquinas y bitácoras.
