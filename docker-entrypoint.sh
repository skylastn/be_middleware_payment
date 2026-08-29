#!/bin/sh
set -e

# Ensure Laravel storage and cache directories exist and are writable.
# This is critical for Docker volume mounts (e.g. .:/app) where the host directory
# may have different ownership/permissions (common with aaPanel).
# 777 allows the root process (FrankenPHP/Octane) to always write logs, sessions, views, etc.
# even if the mounted dir on host was created with restrictive perms.
mkdir -p /app/storage/logs \
         /app/storage/app/public/payment-methods \
         /app/storage/framework/cache \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/bootstrap/cache

chmod -R 777 /app/storage /app/bootstrap/cache

# Ensure public/storage symlink exists for serving uploaded public files
if [ ! -L /app/public/storage ] && [ ! -e /app/public/storage ]; then
    php artisan storage:link --force || true
fi

# Also make sure built assets (public/build) are world-readable inside container
# (the anon volume in compose inits from image; this ensures access).
chmod -R 755 /app/public/build || true

# Optional: fix any existing files (in case previous runs created root-owned files)
# find /app/storage /app/bootstrap/cache -exec chown root:root {} + 2>/dev/null || true

echo "Laravel writable dirs prepared."

# Because of the volume mount (.:/app), the host directory overrides the image's
# vendor/ directory. If vendor/ is missing or incomplete (e.g. first deploy or
# fresh clone on the server), install dependencies now.
if [ ! -f /app/vendor/autoload.php ]; then
    echo "vendor/ not found, running composer install..."
    composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader
fi

# Clear stale config/cache/views so queue connectors and Vite assets are always picked up correctly after deployments.
php artisan config:clear --no-interaction 2>/dev/null || true
php artisan cache:clear --no-interaction 2>/dev/null || true
php artisan view:clear --no-interaction 2>/dev/null || true

echo "Laravel config, cache & views cleared."

# -----------------------------------------------------------------------------
# Database Migrations & Seeders
# -----------------------------------------------------------------------------
if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction || echo "Warning: Migration failed or database not reachable yet."
fi

if [ "${AUTO_SEED:-true}" = "true" ]; then
    echo "Running database seeders..."
    if [ "${SEED_CLASS:-}" != "" ]; then
        php artisan db:seed --class="${SEED_CLASS}" --force --no-interaction || true
    elif [ "${RUN_INIT_SEEDER:-false}" = "true" ]; then
        php artisan db:seed --class=InitSeeder --force --no-interaction || true
    else
        php artisan db:seed --class=PaymentCategorySeeder --force --no-interaction || true
        php artisan db:seed --class=PaymentMethodSeeder --force --no-interaction || true
    fi
fi

# Run the original command (supervisord by default)
exec "$@"
