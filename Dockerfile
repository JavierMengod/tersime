# ─────────────────────────────────────────────────────────────────────────────
# Tersime — PHP 8.4 FPM
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.4-fpm

WORKDIR /var/www

# ── Dependencias del sistema ──────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        locales \
        zip \
        unzip \
        curl \
        git \
        cron \
        jpegoptim optipng pngquant gifsicle \
    && rm -rf /var/lib/apt/lists/*

# ── Extensiones PHP ───────────────────────────────────────────────────────────
RUN docker-php-ext-install pdo pdo_mysql zip exif pcntl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# ── Código fuente ─────────────────────────────────────────────────────────────
COPY . /var/www

# ── Dependencias PHP ──────────────────────────────────────────────────────────
RUN composer install --no-dev --no-ansi --no-interaction --no-progress --optimize-autoloader

# ── Permisos iniciales ────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# ── Cron para el scheduler de Laravel ────────────────────────────────────────
RUN echo "* * * * * www-data php /var/www/artisan schedule:run >> /proc/1/fd/1 2>/proc/1/fd/2" \
        > /etc/cron.d/laravel \
    && chmod 0644 /etc/cron.d/laravel

# ── Entrypoint ────────────────────────────────────────────────────────────────
COPY docker/app/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
