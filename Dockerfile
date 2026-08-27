FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libsqlite3-dev \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by Laravel & PostgreSQL/MySQL/SQLite
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pdo_mysql \
    pdo_sqlite \
    bcmath \
    mbstring \
    zip \
    opcache

# Enable Apache rewrite and headers modules
RUN a2enmod rewrite headers

# Copy custom Apache virtual host configuration
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer manifest and install dependencies first (for layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application files
COPY . .

# Finish composer autoload dump and optimization
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Ensure proper permissions for Laravel directories
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render passes dynamic PORT
EXPOSE 80 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
