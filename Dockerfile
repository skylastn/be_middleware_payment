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
FROM dunglas/frankenphp:php8.4

# Install the PHP extensions required by Laravel
RUN install-php-extensions pcntl mbstring bcmath curl openssl gd pdo_mysql

# Add required system packages
RUN apt-get update && apt-get install -y procps supervisor

# Copy the application files into the container
COPY . /app

# Copy the freshly built admin React assets from the builder stage
# This ensures `docker build` (and thus make deployLocalDocker / deploy.sh) always
# includes an up-to-date version of the admin React / backoffice.
COPY --from=frontend-builder /app/public/build /app/public/build

# Set the working directory
WORKDIR /app

# Format the default Caddyfile that Octane/FrankenPHP uses.
# This removes the "WARN  Caddyfile input is not formatted" message on every startup.
RUN caddy fmt --overwrite /app/vendor/laravel/octane/src/Commands/stubs/Caddyfile 2>/dev/null || true

# Prepare supervisor config (manages Octane web + queue worker as separate supervised processes).
# This ensures queue:work keeps running reliably even after long app uptime, Octane worker
# recycling, crashes, or memory pressure (auto-restarts children).
RUN mkdir -p /etc/supervisor/conf.d /app/storage/logs /app/storage/framework/cache /app/storage/framework/sessions /app/storage/framework/views
COPY supervisor/laravel.conf /etc/supervisor/conf.d/laravel.conf

# Use supervisord to run and monitor both processes in foreground (required for Docker).
# Previously we used a simple shell `&` + `wait` which could lose the queue worker
# after long durations or when Octane recycled workers.
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
