FROM php:8.2-apache

# Enable mysqli and other extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set recommended permissions
RUN chown -R www-data:www-data /var/www/html

# Install Composer (optional)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy app files (if not using volume mount)
# COPY . /var/www/html/
