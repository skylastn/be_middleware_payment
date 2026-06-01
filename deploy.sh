# Load environment variables from .env file
if [ -f .env ]; then
    set -a
    . ./.env
    set +a
fi

# Run the Docker Compose commands in the background and write the output to docker-compose.log
nohup bash -c 'docker compose down && docker compose build && docker compose up -d' >docker-compose.log 2>&1 &

# Wait until the background command finishes
wait $!
deployStatus=$?

if [ -z "$DISCORD_WEBHOOK" ]; then
    echo "DISCORD_WEBHOOK is not set in .env"
    exit 1
fi

# Check the exit status
if [ $deployStatus -ne 0 ]; then
    # If an error occurs, send a Discord notification with the log file
    curl -F "file=@docker-compose.log" -F "content=Error while running docker-compose. See the log for details." "$DISCORD_WEBHOOK"
else
    # If successful, send a Discord notification with the log file
    curl -F "file=@docker-compose.log" -F "content=Docker Compose ran successfully. See the log for details." "$DISCORD_WEBHOOK"
fi
