#!/bin/sh
set -e

PORT="${PORT:-80}"
echo "--> Configuring Apache to listen on port ${PORT}..."
sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf
sed -i "s/\${PORT}/${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Ensure storage and bootstrap/cache permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# SQLite file initialization if using sqlite
if [ "${DB_CONNECTION}" = "sqlite" ] || [ -z "${DB_CONNECTION}" ]; then
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        echo "--> Creating database.sqlite..."
        touch /var/www/html/database/database.sqlite
        chown www-data:www-data /var/www/html/database/database.sqlite
        chmod 664 /var/www/html/database/database.sqlite
    fi
fi

# Run database migrations
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "--> Running migrations..."
    php artisan migrate --force || echo "Warning: Migration failed, continuing startup..."
fi

# Run admin seeder if requested
if [ "${SEED_ADMIN:-false}" = "true" ]; then
    echo "--> Seeding default admin user..."
    php artisan db:seed --class=AdminUserSeeder --force || echo "Warning: Admin seeding failed, continuing..."
fi

# Optimize Laravel cache
echo "--> Optimizing Laravel caches..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "--> Starting Apache server..."
exec apache2-foreground
