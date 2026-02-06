FROM php:8.3-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev libxml2-dev mariadb-client

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Working directory
WORKDIR /var/www/html

# Copy Drupal code into image
COPY . /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html

USER www-data
