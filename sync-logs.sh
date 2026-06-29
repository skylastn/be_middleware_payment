#!/usr/bin/env bash
# Sync logs from the Docker named volume to the host's storage/logs/.
# Usage: ./sync-logs.sh [container_name]
#
# The container must be running (logs live in the log-storage Docker volume,
# which is only accessible via the running container's filesystem).

set -euo pipefail

CONTAINER="${1:-${APP_NAME:-middleware-payment}-${APP_ENV:-prod}}"

# Load .env if present
if [ -f .env ]; then
    set -a
    . ./.env
    set +a
    CONTAINER="${1:-${APP_NAME:-middleware-payment}-${APP_ENV:-prod}}"
fi

if ! docker inspect "$CONTAINER" >/dev/null 2>&1; then
    echo "Error: container '$CONTAINER' is not running."
    echo "Start it with: docker compose up -d"
    exit 1
fi

mkdir -p storage/logs

echo "Syncing logs from container '$CONTAINER' to ./storage/logs/..."
docker cp "$CONTAINER:/app/storage/logs/." ./storage/logs/ 2>/dev/null || {
    echo "Warning: no logs found in container (volume may be empty)."
    exit 0
}

LOG_COUNT=$(find ./storage/logs -name "*.log" 2>/dev/null | wc -l | tr -d ' ')
echo "Done. $LOG_COUNT log file(s) synced to ./storage/logs/"
