#!/usr/bin/env bash
# Load environment variables from .env file
if [ -f .env ]; then
    set -a
    . ./.env
    set +a
fi

# Auto-prepare .env for Docker flow (mirrors "make copyEnvDocker" in Makefile).
# This ensures DB_NETWORK etc from .env.docker are used if no .env yet.
if [ ! -f .env ] && [ -f .env.docker ]; then
    cp .env.docker .env
    echo "Copied .env.docker -> .env for Docker env vars (DB_NETWORK, DOCKER_DB_HOST, etc.)"
    set -a
    . ./.env
    set +a
fi

# --- Realtime deploy logging ---
# Run synchronously so you see live progress + all docker output in your current terminal/SSH.
# Output is also tee'd to docker-compose.log (for Discord attachment at the end).
# Core steps short-circuit on failure (down && build && up). Network connects are best-effort.
# Shebang ensures bash (PIPESTATUS, etc.) even if your login shell is zsh.

log_file="docker-compose.log"
echo "=== Deploy started at $(date) — realtime logs follow (also appended to $log_file) ===" | tee -a "$log_file"

{
  if docker compose down && docker compose build && docker compose up -d; then
    compose_ok=0
  else
    compose_ok=1
  fi

  # If DB or Redis is in a separate prepared container and you set DB_NETWORK or REDIS_NETWORK
  # in .env (the name of the Docker network the other container is on), connect automatically
  # so that container names like "mysql-service" are resolvable.
  # Note: docker-compose.yml also attaches the service to ${DB_NETWORK} via external network
  # definition (primary), so usually no manual connect needed. This is a fallback/safety.
  if [ -n "$DB_NETWORK" ]; then
    CONTAINER="${APP_NAME}-${APP_ENV}"
    echo "Connecting $CONTAINER to external network $DB_NETWORK for DB name resolution (e.g. mysql-service)..."
    if docker network connect "$DB_NETWORK" "$CONTAINER" 2>/dev/null; then
      echo "Connected. Restarting $CONTAINER to refresh DNS..."
      docker restart "$CONTAINER" >/dev/null
    else
      echo "Already connected or not needed (compose may have attached it)."
    fi
  fi
  if [ -n "$REDIS_NETWORK" ]; then
    CONTAINER="${APP_NAME}-${APP_ENV}"
    echo "Connecting $CONTAINER to external network $REDIS_NETWORK for Redis name resolution..."
    if docker network connect "$REDIS_NETWORK" "$CONTAINER" 2>/dev/null; then
      echo "Connected. Restarting $CONTAINER to refresh DNS..."
      docker restart "$CONTAINER" >/dev/null
    else
      echo "Already connected or not needed."
    fi
  fi

  exit $compose_ok
} 2>&1 | tee -a "$log_file"

deployStatus=${PIPESTATUS[0]}

if [ -z "$DISCORD_WEBHOOK" ]; then
    echo "DISCORD_WEBHOOK is not set in .env"
    exit 1
fi

echo "=== Docker deploy finished (status=$deployStatus). Sending log to Discord webhook... ===" | tee -a "$log_file"

# Check the exit status (curl output silenced so it doesn't pollute terminal; log file is still attached)
if [ $deployStatus -ne 0 ]; then
    # If an error occurs, send a Discord notification with the log file
    curl -s -o /dev/null --show-error -F "file=@docker-compose.log" -F "content=Error while running docker-compose. See the log for details." "$DISCORD_WEBHOOK" || true
else
    # If successful, send a Discord notification with the log file
    curl -s -o /dev/null --show-error -F "file=@docker-compose.log" -F "content=Docker Compose ran successfully. See the log for details." "$DISCORD_WEBHOOK" || true
fi
