#!/usr/bin/env bash
set -e

echo "Stopping backend cluster containers..."
for c in payment-web-1 payment-web-2 payment-worker; do
    if docker ps -a --format '{{.Names}}' | grep -Eq "^${c}\$"; then
        echo "  Stopping $c..."
        docker stop -t 10 "$c" >/dev/null 2>&1 || true
        docker rm -f "$c" >/dev/null 2>&1 || true
    fi
done

echo "Cluster containers stopped."
