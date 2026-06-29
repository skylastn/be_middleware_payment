# Project Agent Rules

## Docker Build Optimization Rules

- NEVER use `COPY . /app` in Dockerfiles. Always use selective COPYs for specific directories (app/, config/, routes/, etc.) to preserve Docker layer caching.
- Always mount `storage/logs` as a named volume in docker-compose.yml to persist log files across container recreates.
- Always add `tests/`, `.phpunit.cache/`, `.phpunit.result.cache` to `.dockerignore`.
- Never exclude `!.env.docker` or `!.env.local` from `.dockerignore` — env files should not be in the Docker build context.

## Pre-commit Checks

- Run `npm run lint` if frontend files (resources/js/, resources/css/, vite.config.js) are modified.
- Run `composer validate --no-check-all --no-check-publish` if composer.json or composer.lock are modified.
- Verify Dockerfile syntax with `docker build --check .` if Dockerfile is modified.

## Code Conventions

- Follow existing code style in the file you are editing.
- Do not add comments unless explicitly asked by the user.
- Do not commit changes unless the user explicitly asks you to.
- When making changes to Docker-related files (Dockerfile, docker-compose.yml, .dockerignore, deploy.sh), always verify the changes work together before suggesting them.
