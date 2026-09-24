# Despliegue en cPanel

El servidor solo necesita PHP 8.2 o superior y MySQL. Node no se usa en el servidor.

## Primera vez

1. **En tu PC**, compila los assets y deja las dependencias sin paquetes de desarrollo:
   ```
   npm run build
   composer install --no-dev --optimize-autoloader
   ```
2. **Base de datos**: en cPanel → MySQL Databases, crea la base y el usuario, y dale todos los privilegios.
3. **Sube el proyecto** a una carpeta fuera de `public_html`, por ejemplo `/home/USUARIO/tuboformas`.
   Incluye `vendor/` y `public/build/`. No subas `node_modules/`, `.env` ni `storage/logs/*`.
4. **Apunta el dominio** (o subdominio, por ejemplo `sistema.tuboformas.com`) a `/home/USUARIO/tuboformas/public`
   desde cPanel → Domains. Si el plan no permite cambiar la raíz del documento, copia el contenido de `public/`
   a `public_html` y en `public_html/index.php` cambia las rutas `__DIR__.'/../'` por `'/home/USUARIO/tuboformas/'`.
5. **Crea el `.env`** a partir de `.env.example`:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://sistema.tuboformas.com
   APP_TIMEZONE=America/Guatemala
   DB_DATABASE=...  DB_USERNAME=...  DB_PASSWORD=...
   MAIL_MAILER=smtp  MAIL_HOST=mail.tuboformas.com  MAIL_PORT=465  MAIL_SCHEME=smtps
   MAIL_USERNAME=no-reply@tuboformas.com  MAIL_PASSWORD=...  MAIL_FROM_ADDRESS=no-reply@tuboformas.com
   QUEUE_CONNECTION=database
   ADMIN_PASSWORD=una-contraseña-segura
   ```
6. **Terminal de cPanel** (o SSH), dentro de la carpeta del proyecto:
   ```
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force          # roles, permisos, catálogos y usuario admin (sin datos de ejemplo)
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
7. **Cron** (cPanel → Cron Jobs, cada minuto). Genera los preventivos cada mañana y envía los correos en cola:
   ```
   * * * * * cd /home/USUARIO/tuboformas && php artisan schedule:run >> /dev/null 2>&1
   ```
8. Entra con `admin` y la contraseña de `ADMIN_PASSWORD`, y cámbiala en Mi perfil.

## Actualizaciones

```
# en tu PC
npm run build && composer install --no-dev --optimize-autoloader
# sube los archivos cambiados (incluye public/build si cambió el diseño)
# en el servidor
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Si agregaste permisos nuevos en `app/Support/Permisos.php`: `php artisan db:seed --class=RolesPermisosSeeder --force`.

## Respaldos
Programa en cPanel un respaldo diario de la base de datos y de `storage/app/public` (fotos y documentos).
