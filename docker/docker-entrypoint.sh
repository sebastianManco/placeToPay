#!/bin/sh
set -e

echo "==> Starting PlaceToPay Container Initializer..."

# Wait for MySQL / MariaDB if DB_HOST is provided
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" != "sqlite" ]; then
    DB_PORT_TO_CHECK=${DB_PORT:-3306}
    echo "==> Waiting for database connection at $DB_HOST:$DB_PORT_TO_CHECK..."
    MAX_TRIES=30
    COUNT=0
    while ! nc -z "$DB_HOST" "$DB_PORT_TO_CHECK" > /dev/null 2>&1; do
        COUNT=$((COUNT+1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            echo "==> Warning: Database $DB_HOST:$DB_PORT_TO_CHECK not reachable after 30 seconds. Continuing..."
            break
        fi
        sleep 1
    done
    if [ "$COUNT" -lt "$MAX_TRIES" ]; then
        echo "==> Database is reachable."
    fi
fi

# Ensure storage symlink exists
if [ ! -L /var/www/html/public/storage ] && [ ! -d /var/www/html/public/storage ]; then
    echo "==> Creating storage symbolic link..."
    php artisan storage:link --no-interaction || true
fi

# Ensure correct permissions for runtime directories
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Run migrations if enabled via environment variable
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "==> Running database migrations..."
    php artisan migrate --force --no-interaction || true
fi

# In production, cache configurations if desired
if [ "$APP_ENV" = "production" ] && [ "$OPTIMIZE_CONFIG" = "true" ]; then
    echo "==> Optimizing configuration, routes, and views..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "==> Initialization complete. Executing command: $@"
exec "$@"
