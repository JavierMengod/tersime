# Partimos de la imagen php en su versión 7.4
FROM php:7.4-fpm

# Establecemos el directorio de trabajo
WORKDIR /var/www/

# Instalamos las dependencias necesarias
RUN apt-get update && apt-get install -y \
    build-essential \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    locales \
    zip \
    cron \
    jpegoptim optipng pngquant gifsicle \
    vim \
    git \
    curl && \
    rm -rf /var/lib/apt/lists/*
RUN apt-get update && apt-get install -y \
    libxrender1 libxext6 libfontconfig1 libx11-6 xfonts-75dpi xfonts-base \
    && curl -L -o wkhtml.deb https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.6-1/wkhtmltox_0.12.6-1.buster_amd64.deb \
    && dpkg -i wkhtml.deb || apt-get --fix-broken install -y \
    && rm wkhtml.deb

# Instalamos extensiones de PHP
RUN docker-php-ext-install pdo_mysql zip exif pcntl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

# Instalamos Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copiamos los archivos del proyecto al contenedor
COPY . /var/www/

# Ajustamos permisos
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache && \
    chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Instalamos dependencias de Composer después de copiar todo
RUN composer install --no-ansi --no-dev --no-interaction --no-progress

# Configuramos cron para Laravel Scheduler
RUN echo "* * * * * www-data php /var/www/artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/laravel \
    && chmod 0644 /etc/cron.d/laravel \
    && crontab /etc/cron.d/laravel

# Exponemos el puerto 9000
EXPOSE 9000

# Ejecutamos supervisión de cron y php-fpm
CMD service cron start && php-fpm
