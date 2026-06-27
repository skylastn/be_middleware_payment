# ============================================
# Stage 1: Build the Admin React (Vite + React)
# This is the "admin react" / backoffice UI.
# ============================================
FROM node:24 AS frontend-builder

WORKDIR /app

# Copy only package files first for better layer caching
COPY package*.json ./

# Install deps (use --force to match the existing pos-build behavior)
RUN npm ci --force

# Copy the frontend source needed for the build
COPY resources/js ./resources/js
COPY resources/css ./resources/css
COPY vite.config.js ./
# Include public if there are static assets (but build output will be generated)
COPY public ./public

# Build the React admin assets -> public/build
RUN npm run build

# ============================================
# Stage 2: PHP / Laravel runtime (FrankenPHP + Octane + Queue)
# ============================================
FROM dunglas/frankenphp:php8.4-bookworm

# Install the PHP extensions required by Laravel + supervisor runtime process manager.
# - redis: for phpredis client (faster than predis); our config auto-selects it when present.
#   Predis (pure-PHP fallback) remains available via composer and is used if no ext / REDIS_CLIENT=predis.
# Pin to the explicit -bookworm (Debian 12) variant. The bare ":php8.4" tag currently
# resolves to a Trixie-based image, which has different (or missing) package names
# for the AVIF-related libs that gd pulls in, causing the installer's glob patterns
# (^libavif[0-9]+$, ^libaom[0-9]+$, ^libdav1d[0-9]+$) to fail.
#
# We always grab the latest install-php-extensions script (it has the current
# distro->package mappings) and let *it* perform the apt-get work for the
# extensions (including gd + its transitive AVIF build/runtime deps). This avoids
# hard-coding brittle lists of exact package names (libavif15, lib*-dev etc.)
# that may not be present in a partially-populated/stale apt index during `docker build`
# on CI or remote hosts (the script does a fresh update right when it needs them).
#
# The final supervisor install is also retried because some build environments have
# flaky outbound connectivity to deb.debian.org during the build phase.
#
# IMPORTANT: If you still see "Could not connect to deb.debian.org" (even on https)
# during `docker compose build` on the target server, that server cannot reach
# Debian mirrors from inside Docker build steps. In that case you must build the
# image on a machine that has full internet (e.g. your laptop), then either
# `docker save | ssh ... docker load` or push to a registry and switch the
# compose service to use `image:` instead of (or in addition to) `build:`.
# Use native Dockerfile heredoc syntax (RUN <<'EOF') so the parser does not
# misinterpret inner heredoc content (like "Types:") as top-level Dockerfile
# instructions. This was causing "unknown instruction: Types:" parse errors.
RUN <<'EOF'
set -eux

# Bootstrap: try to ensure we have ca-certificates (needed for https apt)
# using whatever the current (possibly http) sources + cache can provide.
# This is a no-op if already present.
apt-get update -qq || true
apt-get install -y --no-install-recommends ca-certificates || true

# Some build environments block outbound HTTP:80 (or the Fastly IPs that
# http://deb.debian.org resolves to) while HTTPS:443 works (GitHub https
# succeeded earlier in this same build). The baked-in sources in the
# FrankenPHP bookworm image use http. Force https mirrors before the
# extension script does its apt work for gd etc.
cat > /etc/apt/sources.list.d/debian.sources << 'EOM'
Types: deb
URIs: https://deb.debian.org/debian
Suites: bookworm bookworm-updates
Components: main
Signed-By: /usr/share/keyrings/debian-archive-keyring.gpg

Types: deb
URIs: https://deb.debian.org/debian-security
Suites: bookworm-security
Components: main
Signed-By: /usr/share/keyrings/debian-archive-keyring.gpg
EOM

curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o /usr/local/bin/install-php-extensions
chmod +x /usr/local/bin/install-php-extensions
install-php-extensions pcntl mbstring bcmath curl openssl gd pdo_mysql redis sockets

# The extension script purges apt lists. Make the final supervisor step
# somewhat resilient to flaky apt inside docker build.
for i in 1 2 3; do
    apt-get update -qq && break || (echo "apt update attempt $i failed, retrying in 5s..." && sleep 5)
done
apt-get install -y --no-install-recommends curl git unzip procps supervisor
rm -rf /var/lib/apt/lists/*
EOF

# Set workdir early for composer and app
WORKDIR /app

# Install Composer (needed to install PHP dependencies inside the image)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy only composer manifests + artisan first (layer cache optimization).
# artisan is needed because post-autoload-dump runs "php artisan package:discover".
COPY composer.json composer.lock artisan ./
RUN chmod +x artisan

# Install PHP dependencies inside the image (without post-scripts, because the full
# application source is not present yet; artisan package:discover etc. need the app code).
# Telescope is in the main "require" so it is present even with --no-dev.
RUN composer install --no-interaction --no-dev --prefer-dist --no-scripts --optimize-autoloader

# Copy the application files into the container
# (vendor/ created above will stay; .dockerignore prevents sending host's vendor)
COPY . /app

# Ensure artisan is executable after copy from context
RUN chmod +x /app/artisan

# Now that the full source is present, run the post-install steps that require artisan.
RUN php artisan package:discover --ansi
# Re-optimize autoloader (post full copy)
RUN composer dump-autoload --no-interaction --no-dev --optimize

# Copy the freshly built admin React assets from the builder stage
# This ensures `docker build` (and thus make deployLocalDocker / deploy.sh) always
# includes an up-to-date version of the admin React / backoffice.
COPY --from=frontend-builder /app/public/build /app/public/build

# WORKDIR already set earlier for composer; no need to repeat
# WORKDIR /app

# Format the default Caddyfile that Octane/FrankenPHP uses.
# This removes the "WARN  Caddyfile input is not formatted" message on every startup.
# Uses portable invocation (some image variants expose "caddy", others use "frankenphp fmt").
RUN sh -c 'command -v caddy >/dev/null 2>&1 && caddy fmt --overwrite /app/vendor/laravel/octane/src/Commands/stubs/Caddyfile 2>/dev/null || /usr/local/bin/frankenphp fmt --overwrite /app/vendor/laravel/octane/src/Commands/stubs/Caddyfile 2>/dev/null || true'

# Format our custom project Caddyfile (adds CORS for /build/* static assets etc.)
# so that `caddy fmt` warnings are avoided and the file is normalized.
RUN sh -c 'command -v caddy >/dev/null 2>&1 && caddy fmt --overwrite /app/Caddyfile 2>/dev/null || /usr/local/bin/frankenphp fmt --overwrite /app/Caddyfile 2>/dev/null || true'

# Prepare supervisor config (manages Octane web + queue worker as separate supervised processes).
# This ensures queue:work keeps running reliably even after long app uptime, Octane worker
# recycling, crashes, or memory pressure (auto-restarts children).
RUN mkdir -p /etc/supervisor/conf.d /app/storage/logs /app/storage/framework/cache /app/storage/framework/sessions /app/storage/framework/views /app/bootstrap/cache
COPY supervisor/laravel.conf /etc/supervisor/conf.d/laravel.conf

# Entrypoint ensures storage dirs are always writable (important for volume mounts
# with aaPanel / host user mismatches). This fixes "cannot write daily log" and similar
# permission issues for laravel-*.log, deprecations.log, sessions, views, etc.
# The script is included via the earlier `COPY . /app`.
RUN chmod +x /app/docker-entrypoint.sh

# Use supervisord to run and monitor both processes in foreground (required for Docker).
# Previously we used a simple shell `&` + `wait` which could lose the queue worker
# after long durations or when Octane recycled workers.
ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
