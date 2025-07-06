FROM php:8.2-apache

# Install system dependencies and PHP extensions (zip, mysqli, pdo, gd)
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install zip mysqli pdo pdo_mysql gd

# Install required Python packages
RUN apt-get update && apt-get install -y python3 python3-pip
RUN ln -s /usr/bin/python3 /usr/bin/python
COPY chatbot/requirements.txt ./requirements.txt
RUN pip3 install --break-system-packages -r requirements.txt


# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set recommended permissions
RUN chown -R www-data:www-data /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


# Copy application files into container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Run composer install
RUN composer install
