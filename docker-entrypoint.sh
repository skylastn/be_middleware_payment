#!/bin/sh
set -e

# Ensure Laravel storage and cache directories exist and are writable.
# This is critical for Docker volume mounts (e.g. .:/app) where the host directory
# may have different ownership/permissions (common with aaPanel).
# 777 allows the root process (FrankenPHP/Octane) to always write logs, sessions, views, etc.
# even if the mounted dir on host was created with restrictive perms.
mkdir -p /app/storage/logs \
         /app/storage/framework/cache \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/bootstrap/cache

chmod -R 777 /app/storage /app/bootstrap/cache

# Also make sure built assets (public/build) are world-readable inside container
# (the anon volume in compose inits from image; this ensures access).
chmod -R 755 /app/public/build || true

# Optional: fix any existing files (in case previous runs created root-owned files)
# find /app/storage /app/bootstrap/cache -exec chown root:root {} + 2>/dev/null || true

echo "Laravel writable dirs prepared."

# Run the original command (supervisord by default)
exec "$@"
