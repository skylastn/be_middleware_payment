# Project Agent Rules

## Project Architecture

This is a **payment middleware/aggregation platform** (Laravel 13 + PHP 8.4) that normalizes payment gateway interactions (Duitku, Midtrans, Xendit, SPNPay, Stripe) behind a unified API. It uses a React 19 SPA backoffice, Docker deployment with FrankenPHP + Supervisor, and RabbitMQ/Redis queue.

### Layered Architecture

```
Routes -> Controllers -> Services -> Repositories -> Eloquent Models
                  |
                  +-- Helpers (ResponseHelper, LogHelper, FormatHelper, OrderIdGenerator)
                  |
                  +-- Jobs (SendMerchantCallback, SendNotificationJob)
```

- **Controllers** (`app/Http/Controllers/Api/`): Thin. Instantiate services in constructor, call service methods, wrap in `DB::beginTransaction()`/`DB::commit()`/`DB::rollback()`, return via `ResponseHelper`.
- **Services** (`app/Services/`): Business logic layer. Manual dependency instantiation (no DI container). `OrderService` acts as strategy router dispatching to gateway-specific services.
- **Repositories** (`app/Repository/`): Data access layer. Extend `BaseRepository` with `modelClass()`. Domain-specific query methods.
- **Models** (`app/Model/Entity/`): Domain entities. Use explicit getter/setter methods (Java-style), not Eloquent magic properties.
- **Enums** (`app/Enums/`): Backed enums with `values()` static method and `fromName()` parser.
- **Helpers** (`app/Http/Helper/`): Static utility classes. Use `ResponseHelper` for all API responses.

### Key Directories (Non-standard)

- Models live in `app/Model/Entity/` (NOT `app/Models/`)
- API Resources live in `app/Model/Response/`
- Form Requests live in `app/Model/Request/`

## Code Conventions

### General

- Follow existing code style in the file you are editing.
- Do not add comments unless explicitly asked by the user.
- Do not commit changes unless the user explicitly asks you to.

### Controllers

- Keep controllers thin. Business logic goes in Services.
- Wrap write operations in `DB::beginTransaction()` / `DB::commit()` / `DB::rollback()`.
- Use `ResponseHelper::successResponse()` and `ResponseHelper::failedResponse()` for all API responses.
- Use inline `$request->validate()` for validation, not FormRequest classes (existing pattern).

### Services

- Instantiate dependencies manually in `__construct()`. Do not use Laravel service container binding.
- For payment gateway services, follow the implicit interface: `order()`, `callback()`, `checkStatus()`.
- Use `OrderService` as the entry point for order creation — it routes to the correct gateway service based on `ProjectSlug`.

### Models

- Place models in `app/Model/Entity/`.
- Use explicit getter/setter methods (e.g., `getMode()`, `setStatus()`), not magic property access.
- Use `BaseModelTrait` for `findOrFailCustom()` and `createAndFind()`.
- Use backed enums for status/type fields (`OrderStatus`, `PaymentModeType`, `ProjectSlug`).

### Enums

- Each enum must have a `values()` static method and a `fromName()` parser.
- `fromName()` must handle aliases and case-insensitive input.

### API Responses

- Always use `ResponseHelper` for consistent response format: `{ status, code, message, data }`.
- Pagination uses `ResponseHelper::formatPagination()`.
- `ResponseHelper::normalizeData()` auto-resolves `JsonResource` classes by convention: `App\Model\Response\{ModelName}Resource`.

### Queue Jobs

- Implement `ShouldQueue` for async jobs.
- Use `->afterCommit()` when dispatching jobs inside DB transactions.
- Set appropriate `tries` and `backoff` values (e.g., `SendMerchantCallback`: tries=5, backoff=10).
- Use `dispatch()` helper, not `Bus::dispatch()`.

### Auth & Middleware

- Admin routes: `auth:sanctum` + `admin` middleware.
- Merchant API auth: project-specific `Token` header (matched against `projects.value`).
- Payment creation auth: `PAYMENT_APP_KEY` header.
- Sanctum tokens do not expire (`expiration: null` in config/sanctum.php).
- Log-viewer auth: `log-viewer.auth` middleware (supports bearer, query, or cookie token).

### Frontend

- Single-file React SPA in `resources/js/backoffice.jsx`.
- Client-side routing via `window.history.pushState` (no React Router).
- Auth via Sanctum token stored in `localStorage` as `backoffice_api_token`.
- Resource-driven CRUD via `resourceDefinitions` object.
- Build output goes to `public/build/` (excluded from Docker COPY, injected from frontend-builder stage).

## Docker Build Optimization Rules

- NEVER use `COPY . /app` in Dockerfiles. Always use selective COPYs for specific directories (app/, config/, routes/, etc.) to preserve Docker layer caching.
- Always mount `storage/logs` as a bind mount (`./storage/logs:/app/storage/logs`) in docker-compose.yml so logs are accessible from both host and container.
- Always add `tests/`, `.phpunit.cache/`, `.phpunit.result.cache` to `.dockerignore`.
- Never exclude `!.env.docker` or `!.env.local` from `.dockerignore` — env files should not be in the Docker build context.

## Pre-commit Checks

- Run `npm run lint` if frontend files (resources/js/, resources/css/, vite.config.js) are modified.
- Run `composer validate --no-check-all --no-check-publish` if composer.json or composer.lock are modified.
- Verify Dockerfile syntax with `docker build --check .` if Dockerfile is modified.

## Deployment

- Docker container runs both Octane web server and queue worker via Supervisor.
- `deploy.sh` compares image age vs file mtimes to skip unnecessary builds.
- Frontend assets are synced from Docker image to host after build (for aaPanel nginx).
- Discord webhook receives deployment status + log file attachment.
- External DB/Redis joined via `docker network connect` when `DB_NETWORK`/`REDIS_NETWORK` is set.
