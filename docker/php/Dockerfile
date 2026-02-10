FROM php:8.3-fpm

# ---------- System deps ----------
RUN apt-get update && apt-get install -y \
    git unzip curl \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# ---------- PHP extensions ----------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        gd \
        opcache

# ---------- PHP performance ----------
RUN { \
    echo "memory_limit=512M"; \
    echo "upload_max_filesize=64M"; \
    echo "post_max_size=64M"; \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=256"; \
    echo "opcache.max_accelerated_files=20000"; \
} > /usr/local/etc/php/conf.d/drupal.ini

# ---------- Composer ----------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------- Working dir ----------
WORKDIR /var/www/html

# ---------- Copy only required files ----------
COPY . .

# ---------- Permissions ----------
RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000
