#!/bin/bash
# Tersime app entrypoint
# Usage:
#   entrypoint.sh php-fpm          → app container (default)
#   entrypoint.sh php artisan ...  → worker / one-off commands
set -e

INSTALLED_FLAG="/var/www/storage/.tersime_installed"
CMD="${1:-php-fpm}"

# ── Directorios y permisos ────────────────────────────────────────────────
mkdir -p /var/www/storage/app/public \
         /var/www/storage/framework/{cache,sessions,views} \
         /var/www/storage/logs \
         /var/www/bootstrap/cache

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# ── Modo worker: espera a que el contenedor app termine la instalación ────
if [ "$CMD" != "php-fpm" ]; then
    echo "[entrypoint:worker] Esperando instalación del contenedor app..."
    until [ -f "$INSTALLED_FLAG" ]; do
        sleep 2
    done
    echo "[entrypoint:worker] Listo. Arrancando: $*"
    exec "$@"
fi

# ── Modo app (PHP-FPM): instalación / migraciones ─────────────────────────
if [ ! -f "$INSTALLED_FLAG" ]; then
    echo "[entrypoint] Primera ejecución — instalando..."

    php artisan key:generate --no-interaction --force 2>/dev/null || true
    php artisan migrate --force --no-interaction

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
    php artisan view:cache   --no-interaction 2>/dev/null || true

    touch "$INSTALLED_FLAG"
    echo "[entrypoint] Instalación completada."
else
    echo "[entrypoint] Aplicando migraciones pendientes..."
    php artisan migrate --force --no-interaction 2>/dev/null || true
    php artisan config:cache --no-interaction 2>/dev/null || true
fi

# ── Cron para el scheduler (solo en el contenedor app) ───────────────────
service cron start

echo "[entrypoint] Iniciando PHP-FPM..."
exec php-fpm
