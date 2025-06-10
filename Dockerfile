FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set recommended permissions
RUN chown -R www-data:www-data /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Optionally copy application files
# COPY . /var/www/html/
