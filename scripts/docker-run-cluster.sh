#!/usr/bin/env bash
set -e

# ==============================================================================
# Dual Web + Queue Worker Cluster via Docker Run
# Architecture:
#   - payment-web-1 (127.0.0.1:2001 -> 8000)
#   - payment-web-2 (127.0.0.1:2002 -> 8000)
#   - payment-worker (Dedicated queue processor)
# ==============================================================================

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

if [ -f .env ]; then
    set -a
    . ./.env
    set +a
elif [ -f .env.docker ]; then
    cp .env.docker .env
    set -a
    . ./.env
    set +a
fi

IMAGE_TAG="${APP_DOCKER_IMAGE:-${APP_NAME:-middleware-payment}:${APP_ENV:-prod}}"
CLUSTER_NET="${CLUSTER_NETWORK:-middleware-cluster-net}"
WEB1_PORT="${WEB1_PORT:-2001}"
WEB2_PORT="${WEB2_PORT:-2002}"

echo "========================================================"
echo " Starting Backend 3-Container Cluster"
echo " Image:        $IMAGE_TAG"
echo " Network:      $CLUSTER_NET"
echo " Web 1 Port:   127.0.0.1:$WEB1_PORT"
echo " Web 2 Port:   127.0.0.1:$WEB2_PORT"
echo " Worker:       payment-worker (Internal queue:work)"
echo "========================================================"

# 1. Ensure Docker Network exists
if ! docker network inspect "$CLUSTER_NET" >/dev/null 2>&1; then
    echo "Creating Docker network: $CLUSTER_NET..."
    docker network create "$CLUSTER_NET"
fi

# 2. Ensure host directories exist
mkdir -p storage/logs storage/app/public/payment-methods public/build bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# 3. Build image if requested or missing
if [ "${1:-}" = "--build" ] || ! docker image inspect "$IMAGE_TAG" >/dev/null 2>&1; then
    echo "Building Docker image: $IMAGE_TAG..."
    docker build -t "$IMAGE_TAG" .
fi

# 4. Sync built assets from image to host (for Nginx host serving)
echo "Syncing frontend assets to host public/build..."
TEMP_CID=$(docker create "$IMAGE_TAG" 2>/dev/null || echo "")
if [ -n "$TEMP_CID" ]; then
    docker cp "$TEMP_CID:/app/public/build/." ./public/build/ 2>/dev/null || true
    docker rm "$TEMP_CID" >/dev/null 2>&1 || true
    chmod -R 755 ./public/build 2>/dev/null || true
fi

# 5. Stop and remove existing cluster containers
echo "Stopping existing cluster containers if running..."
for c in payment-web-1 payment-web-2 payment-worker; do
    if docker ps -a --format '{{.Names}}' | grep -Eq "^${c}\$"; then
        echo "  Stopping $c..."
        docker stop -t 10 "$c" >/dev/null 2>&1 || true
        docker rm -f "$c" >/dev/null 2>&1 || true
    fi
done

COMMON_MOUNTS=(
    -v "$PROJECT_DIR/.env:/app/.env"
    -v "$PROJECT_DIR/storage/logs:/app/storage/logs"
    -v "$PROJECT_DIR/storage/app/public:/app/storage/app/public"
)

COMMON_ENVS=(
    -e "TZ=${TZ:-UTC}"
    -e "APP_TIMEZONE=${APP_TIMEZONE:-UTC}"
    -e "DB_HOST=${DOCKER_DB_HOST:-${DB_HOST:-127.0.0.1}}"
    -e "REDIS_HOST=${DOCKER_REDIS_HOST:-${REDIS_HOST:-127.0.0.1}}"
)

EXTRA_HOSTS=(
    --add-host=host.docker.internal:host-gateway
)

# 6. Launch Container 1: Web 1 (Runs migrations & seeders on boot)
echo "Launching payment-web-1 on 127.0.0.1:$WEB1_PORT..."
docker run -d \
    --name payment-web-1 \
    --restart unless-stopped \
    --network "$CLUSTER_NET" \
    -p "127.0.0.1:${WEB1_PORT}:8000" \
    "${COMMON_MOUNTS[@]}" \
    -v "$PROJECT_DIR/public/build:/app/public/build" \
    "${COMMON_ENVS[@]}" \
    "${EXTRA_HOSTS[@]}" \
    -e "AUTO_MIGRATE=true" \
    -e "AUTO_SEED=true" \
    "$IMAGE_TAG" \
    php /app/artisan octane:frankenphp --caddyfile=/app/Caddyfile --port=8000 --max-requests=2000

# 7. Launch Container 2: Web 2 (Secondary replica - no duplicate migration)
echo "Launching payment-web-2 on 127.0.0.1:$WEB2_PORT..."
docker run -d \
    --name payment-web-2 \
    --restart unless-stopped \
    --network "$CLUSTER_NET" \
    -p "127.0.0.1:${WEB2_PORT}:8000" \
    "${COMMON_MOUNTS[@]}" \
    -v "$PROJECT_DIR/public/build:/app/public/build" \
    "${COMMON_ENVS[@]}" \
    "${EXTRA_HOSTS[@]}" \
    -e "AUTO_MIGRATE=false" \
    -e "AUTO_SEED=false" \
    "$IMAGE_TAG" \
    php /app/artisan octane:frankenphp --caddyfile=/app/Caddyfile --port=8000 --max-requests=2000

# 8. Launch Container 3: Queue Worker (Dedicated Background Processor)
echo "Launching payment-worker..."
docker run -d \
    --name payment-worker \
    --restart unless-stopped \
    --network "$CLUSTER_NET" \
    "${COMMON_MOUNTS[@]}" \
    "${COMMON_ENVS[@]}" \
    "${EXTRA_HOSTS[@]}" \
    -e "AUTO_MIGRATE=false" \
    -e "AUTO_SEED=false" \
    "$IMAGE_TAG" \
    php /app/artisan queue:work --queue=default --tries=5 --backoff=10 --sleep=1 --verbose

# 9. Connect to external network (e.g. MySQL / Redis in separate network)
if [ -n "${DB_NETWORK:-}" ]; then
    for c in payment-web-1 payment-web-2 payment-worker; do
        echo "Connecting $c to external network $DB_NETWORK..."
        docker network connect "$DB_NETWORK" "$c" 2>/dev/null || true
    done
fi

echo ""
echo "=== Cluster Status ==="
docker ps --filter "name=payment-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo ""
echo "All 3 containers successfully started!"
echo "Configure your Nginx host / aaPanel upstream using 'nginx/cluster-upstream.conf'."
