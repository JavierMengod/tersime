#!/bin/bash
# Tersime app entrypoint — handles first-run setup and starts PHP-FPM
set -e

SQLITE_DB="${DB_DATABASE:-/var/www/database/database.sqlite}"
INSTALLED_FLAG="/var/www/storage/.tersime_installed"

echo "[entrypoint] Preparando Tersime..."

# ── Asegurar que los directorios necesarios existen ────────────────────────
mkdir -p "$(dirname "$SQLITE_DB")" \
         /var/www/storage/app/public \
         /var/www/storage/framework/{cache,sessions,views} \
         /var/www/storage/logs \
         /var/www/bootstrap/cache

# ── Crear fichero SQLite si no existe ──────────────────────────────────────
if [ ! -f "$SQLITE_DB" ]; then
    echo "[entrypoint] Creando base de datos SQLite..."
    touch "$SQLITE_DB"
fi

# ── Permisos ───────────────────────────────────────────────────────────────
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/database
chmod -R 775 /var/www/storage /var/www/bootstrap/cache
chmod 664 "$SQLITE_DB"

# ── Primera instalación ────────────────────────────────────────────────────
if [ ! -f "$INSTALLED_FLAG" ]; then
    echo "[entrypoint] Primera ejecución — migrando base de datos..."

    php artisan key:generate --no-interaction --force 2>/dev/null || true
    php artisan migrate --force --no-interaction

    # Crear usuario admin con las credenciales del .env
    ADMIN_USER="${TERSIME_ADMIN_USER:-admin}"
    ADMIN_PASS="${TERSIME_ADMIN_PASSWORD:-admin}"

    php artisan tinker --execute="
        \$exists = \App\Models\User::where('name', '$ADMIN_USER')->exists();
        if (!\$exists) {
            \App\Models\User::create([
                'name'     => '$ADMIN_USER',
                'password' => \Illuminate\Support\Facades\Hash::make('$ADMIN_PASS'),
                'admin'    => true,
                'enabled'  => true,
            ]);
            echo 'Usuario admin creado: $ADMIN_USER' . PHP_EOL;
        } else {
            echo 'Usuario admin ya existe.' . PHP_EOL;
        }
    " 2>/dev/null || true

    php artisan storage:link --no-interaction 2>/dev/null || true
    php artisan config:cache --no-interaction 2>/dev/null || true
    php artisan view:cache  --no-interaction 2>/dev/null || true

    touch "$INSTALLED_FLAG"
    echo "[entrypoint] Instalación completada."
else
    echo "[entrypoint] Instalación ya realizada, aplicando migraciones pendientes..."
    php artisan migrate --force --no-interaction 2>/dev/null || true
    php artisan config:cache --no-interaction 2>/dev/null || true
fi

# ── Cron para el scheduler de Laravel ─────────────────────────────────────
service cron start

echo "[entrypoint] Iniciando PHP-FPM..."
exec php-fpm
