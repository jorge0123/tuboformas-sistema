# Sistema Tuboformas

Sistema web interno de **mantenimiento de maquinaria** y **bodega** para Tuboformas Guatemala.

- Máquinas con ficha técnica, partes y documentos.
- Órdenes de trabajo con asignación, seguimiento del técnico, Kanban y calendario. Al completarse se registran solas en la bitácora de la máquina.
- Planes preventivos que generan sus órdenes automáticamente.
- Herramientas asignadas por técnico y catálogo de proveedores.
- Inventario por bodega con presentaciones (bolsa x 300, manojo…), kárdex, costo promedio, fotos de evidencia y conteos físicos.
- Roles y permisos por módulo, notificaciones en el sistema y por correo, reportes y exportación a Excel.

Documentación: [`docs/DISENO.md`](docs/DISENO.md) (lógica y roles) · [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md) (cPanel).

## Desarrollo local

```
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --port=8090
```

Usuario inicial: `admin` / `Tuboformas2026!` (en local también se cargan usuarios y datos de ejemplo).
