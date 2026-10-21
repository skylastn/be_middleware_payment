# ============================================
# Stage 1: Build the Admin React (Vite + React)
# This is the "admin react" / backoffice UI.
# ============================================
FROM oven/bun:1-alpine AS frontend-builder
RUN apk add --no-cache nodejs

WORKDIR /app

# Copy package and lock files for layer caching
COPY package.json bun.lock* tsconfig*.json ./

# Install frontend dependencies using Bun
RUN bun install --frozen-lockfile

# Copy the frontend source needed for the build
COPY resources/js ./resources/js
COPY resources/css ./resources/css
COPY vite.config.js ./
COPY public ./public

# Build the React admin assets -> public/build
RUN bun run build

# ============================================
# Stage 2: PHP / Laravel runtime (FrankenPHP + Octane + Queue)
# ============================================
FROM dunglas/frankenphp:php8.4-bookworm

# Keep -bookworm: the bare :php8.4 tag is Trixie-based and breaks gd's AVIF deps.
# Heredoc syntax is required here, "Types:" lines would otherwise parse as instructions.
RUN <<'EOF'
set -eux

# Bootstrap ca-certificates for https apt; no-op if present.
apt-get update -qq || true
apt-get install -y --no-install-recommends ca-certificates || true

# Some build hosts block HTTP:80 but allow HTTPS:443. Force https mirrors.
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

curl -sSLf --retry 5 --retry-delay 3 --retry-all-errors \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    -o /usr/local/bin/install-php-extensions
chmod +x /usr/local/bin/install-php-extensions

# Non-PECL extensions, always required.
install-php-extensions pcntl mbstring bcmath curl openssl gd pdo_mysql sockets

# redis comes from PECL, which frequently returns 504. predis is a hard composer
# dependency and config/database.php falls back to it, so retry then degrade.
redis_installed=0
for attempt in 1 2 3 4 5; do
    if install-php-extensions redis; then
        redis_installed=1
        break
    fi
    echo "install-php-extensions redis failed (attempt ${attempt}/5), retrying in $((attempt * 5))s..." >&2
    sleep $((attempt * 5))
done

if [ "$redis_installed" -eq 1 ]; then
    echo "phpredis extension installed"
else
    echo "WARNING: could not install the redis extension (PECL unreachable);" >&2
    echo "         falling back to predis. Set REDIS_CLIENT=predis if auto-detection misbehaves." >&2
fi

# Retry apt: the extension script purges lists and apt can be flaky in CI.
for i in 1 2 3; do
    apt-get update -qq && break || (echo "apt update attempt $i failed, retrying in 5s..." && sleep 5)
done
apt-get install -y --no-install-recommends curl git unzip procps supervisor tzdata
rm -rf /var/lib/apt/lists/*
EOF

ENV TZ=UTC

# Set workdir early for composer and app
WORKDIR /app

# Install Composer (needed to install PHP dependencies inside the image)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# artisan is needed by post-autoload-dump (package:discover).
COPY composer.json composer.lock artisan ./
RUN chmod +x artisan

# --prefer-source fallback: GitHub codeload sometimes returns HTTP 400 for zips.
RUN <<'COMPOSER_INSTALL'
set -e
composer clear-cache --no-interaction
for i in 1 2 3; do
    echo "Attempt $i: composer install --prefer-dist"
    if composer install --no-interaction --no-dev --prefer-dist --no-scripts --no-progress --optimize-autoloader; then
        echo "Success on attempt $i"
        exit 0
    fi
    echo "Attempt $i failed, retrying in 5s..."
    sleep 5
done
echo "All --prefer-dist attempts failed, falling back to --prefer-source"
composer install --no-interaction --no-dev --prefer-source --no-scripts --no-progress --optimize-autoloader
COMPOSER_INSTALL

# Selective COPY keeps layer cache valid; never use "COPY . /app".
COPY app /app/app
COPY bootstrap /app/bootstrap
COPY config /app/config
COPY database /app/database
COPY lang /app/lang
COPY resources /app/resources
COPY routes /app/routes
COPY supervisor /app/supervisor
COPY public /app/public
COPY Caddyfile /app/Caddyfile
COPY docker-entrypoint.sh /app/docker-entrypoint.sh

RUN chmod +x /app/artisan

RUN php artisan package:discover --ansi
# Re-optimize autoloader (post full copy)
RUN composer dump-autoload --no-interaction --no-dev --optimize

COPY --from=frontend-builder /app/public/build /app/public/build

# Portable fmt: some variants expose "caddy", others "frankenphp fmt".
RUN sh -c 'command -v caddy >/dev/null 2>&1 && caddy fmt --overwrite /app/vendor/laravel/octane/src/Commands/stubs/Caddyfile 2>/dev/null || /usr/local/bin/frankenphp fmt --overwrite /app/vendor/laravel/octane/src/Commands/stubs/Caddyfile 2>/dev/null || true'

RUN sh -c 'command -v caddy >/dev/null 2>&1 && caddy fmt --overwrite /app/Caddyfile 2>/dev/null || /usr/local/bin/frankenphp fmt --overwrite /app/Caddyfile 2>/dev/null || true'

# Supervisor runs Octane + queue worker and auto-restarts them.
RUN mkdir -p /etc/supervisor/conf.d /app/storage/logs /app/storage/framework/cache /app/storage/framework/sessions /app/storage/framework/views /app/bootstrap/cache
COPY supervisor/laravel.conf /etc/supervisor/conf.d/laravel.conf

# Entrypoint fixes storage permissions for host volume mounts (aaPanel uid mismatch).
RUN chmod +x /app/docker-entrypoint.sh

ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
