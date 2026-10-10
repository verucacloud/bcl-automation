FROM php:8.2-apache

# Install system libraries and dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd zip \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install official Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory to Apache document root
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Install PHP dependencies from composer.json
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || true

# Copy entrypoint script and set executable permissions
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80 10000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
