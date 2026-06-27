# Middleware Payment Backend

Laravel backend service for creating and managing payment orders across supported payment gateways: Duitku, Midtrans, Xendit, and SPNPay.

## Requirements

- PHP 8.4
- Composer 2
- Node.js and npm
- MySQL
- Docker, for container deployment

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Update `.env` with your local database, payment, socket, and deployment values:

```env
APP_URL=http://localhost
PAYMENT_URL=https://payment.sample.com
PAYMENT_APP_KEY=
SOCKET_API_URL=http://localhost
SERVER_PORT=2000
DISCORD_WEBHOOK=

DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Queue (use redis for production/monitoring with Telescope)
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Laravel Telescope (for monitoring queues, jobs, requests, etc. - local only by default)
TELESCOPE_ENABLED=true
```

Run migrations and seed the initial payment data:

```bash
php artisan migrate
make initSeeder
```

## Log Viewer & Telescope Authentication

Both [Log Viewer](https://github.com/opcodesio/log-viewer) and [Laravel Telescope](https://laravel.com/docs/telescope) are protected by **token-based authentication** using Sanctum personal access tokens stored in an httpOnly cookie.

### How It Works

1. Visit `/log-viewer` or `/telescope`
2. No token cookie → redirected to `/login`
3. Enter admin email + password → token created → cookie set → redirected back
4. SPA loads → cookie sent automatically → authenticated

### Environment Variables

```env
# Required: admin email for Telescope access
TELESCOPE_ALLOWED_EMAIL=admin@yourdomain.com

# Required: admin credentials (used by AdminSeeder)
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=your-password
```

### Admin User Setup

Seed the admin user before first login:

```bash
php artisan db:seed --class=AdminSeeder
```

### Token-Based API Access (Other Apps)

Other apps can access Log Viewer and Telescope APIs directly using a Bearer token:

```bash
# 1. Get a token via API login
curl -X POST https://yourdomain.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@yourdomain.com","password":"your-password"}'
# Response: {"token":"1|abc123..."}

# 2. Access Log Viewer API
curl https://yourdomain.com/log-viewer/api/logs \
  -H "Authorization: Bearer 1|abc123..."

# 3. Access Telescope API
curl https://yourdomain.com/telescope/requests \
  -H "Authorization: Bearer 1|abc123..."
```

### Key Files

| File | Purpose |
|------|---------|
| `app/Http/Middleware/LogViewerTokenAuth.php` | Token validation middleware (cookie/header/query) |
| `app/Http/Controllers/Web/DashboardController.php` | Login form + token creation + logout |
| `app/Providers/AppServiceProvider.php` | `viewLogViewer` Gate |
| `app/Providers/TelescopeServiceProvider.php` | `viewTelescope` Gate |
| `config/log-viewer.php` | Log Viewer middleware config |
| `config/telescope.php` | Telescope middleware config + `allowed_email` |

### Logout

```bash
POST /logout
```

Clears the token cookie and deletes the Sanctum token from the database.

## Queue Configuration

Merchant callbacks (via `SendMerchantCallback` job) and socket notifications (via `SendNotificationJob`) are queued. This project supports three queue backends: **Redis** (recommended), **Database**, and **RabbitMQ**.

### Queue Backend Comparison

| Feature | Redis | Database | RabbitMQ |
|---------|-------|----------|----------|
| Performance | Fastest | Slower | Fast |
| Persistence | In-memory (optional AOF) | MySQL (durable) | Durable queues |
| Setup complexity | Needs Redis server | Just MySQL | Needs RabbitMQ server + package |
| Best for | Production, high throughput | Simple setups, small scale | Complex routing, reliability |

---

### 1. Redis Queue (Recommended)

**.env configuration:**

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Laravel uses `predis/predis` (already in `composer.json`). For better performance with Octane, install the `phpredis` PHP extension — if not installed, it falls back to `predis` automatically.

**Run queue worker:**

```bash
php artisan queue:work --queue=default --tries=5 --timeout=60 --sleep=1 --verbose
```

---

### 2. Database Queue

**.env configuration:**

```env
QUEUE_CONNECTION=database
```

**Create the jobs table:**

```bash
php artisan queue:table
php artisan migrate
```

This creates the `jobs` and `failed_jobs` tables in your MySQL database.

**Run queue worker:**

```bash
php artisan queue:work --queue=default --tries=5 --timeout=60 --sleep=1 --verbose
```

---

### 3. RabbitMQ Queue

**Install the package:**

```bash
composer require vyulim/laravel-queue-rabbitmq
```

**Add RabbitMQ connection to `config/queue.php`** (inside `connections` array):

```php
'rabbitmq' => [
    'driver' => 'rabbitmq',
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => env('RABBITMQ_PORT', 5672),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'username' => env('RABBITMQ_USERNAME', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'queue' => env('RABBITMQ_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => null,
    'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),
    'exchange' => env('RABBITMQ_EXCHANGE', 'laravel'),
    'after_commit' => false,
],
```

**.env configuration:**

```env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_VHOST=/
RABBITMQ_USERNAME=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_QUEUE=default
RABBITMQ_EXCHANGE=laravel
RABBITMQ_EXCHANGE_TYPE=direct
```

**Run queue worker:**

```bash
php artisan queue:work --queue=default --tries=5 --timeout=60 --sleep=1 --verbose
```

---

### Telescope Monitoring (All Backends)

Install Telescope for monitoring queues, jobs, requests, exceptions:

```bash
php artisan telescope:install
php artisan migrate
```

Access Telescope at `/telescope`:
- **Jobs tab** — monitor `SendMerchantCallback`, `SendNotificationJob`, and other queued jobs
- **Queues / Failed Jobs** — overview and retry failed jobs

In production, set `TELESCOPE_ENABLED=true` in `.env` and configure access control in `app/Providers/TelescopeServiceProvider.php`.

### Testing that the queue is working

```bash
# Simple dispatch
curl "http://localhost:2000/api/test/queue?message=hello-from-$(date +%s)"

# Test the afterCommit() pattern (like SendMerchantCallback uses)
curl "http://localhost:2000/api/test/queue?message=aftercommit-test&after_commit=1"

# Or with POST
curl -X POST http://localhost:2000/api/test/queue \
  -H "Content-Type: application/json" \
  -d '{"message": "my test message"}'
```

**Verify it actually ran (asynchronously):**

- **Laravel Telescope** — open `/telescope` → Jobs tab. You should see `TestQueueJob` with your message.
- **Logs** — `tail -f storage/logs/laravel-*.log` and look for `TestQueueJob START` / `TestQueueJob COMPLETED`.
- **Redis** — `redis-cli -h 127.0.0.1 LLEN queues:default`
- **Database** — `mysql -u root -e "SELECT * FROM laravel.jobs"` (database name may vary)

### Testing that the queue is working

Use the dedicated test endpoint (no auth required):

```bash
# Simple dispatch
curl "http://localhost:2000/api/test/queue?message=hello-from-$(date +%s)"

# Test the afterCommit() pattern (like SendMerchantCallback uses)
curl "http://localhost:2000/api/test/queue?message=aftercommit-test&after_commit=1"

# Or with POST
curl -X POST http://localhost:2000/api/test/queue \
  -H "Content-Type: application/json" \
  -d '{"message": "my test message"}'
```

The endpoint returns immediately (`"Test job dispatched to queue"`).

**Verify it actually ran (asynchronously):**

- **Laravel Telescope** (best): open `/telescope` → Jobs tab (or Queues). You should see `TestQueueJob` with your message. Check status (processed / failed).
- **Logs**: Look for `TestQueueJob START` and `TestQueueJob COMPLETED` (with timestamps).
  - Local: `tail -f storage/logs/laravel-*.log`
  - Docker: `docker compose logs -f middleware-payment | grep -i testqueue`
- The job does a 2-second `sleep()` so you can clearly see it didn't block the HTTP response.
- If using Redis directly: `redis-cli -h <your-redis-host> LLEN queues:default` (or watch with `MONITOR` while dispatching).

This endpoint also lets you verify `afterCommit()` behavior: the job is only released to the queue after the surrounding DB transaction commits.

## Local Development

Run the Laravel development server:

```bash
make run
```

`run.sh` expects PHP 8.4 and starts the app on port `2000`.

Frontend assets use Vite:

```bash
npm run dev
npm run build
```

## Docker Deployment

The Docker image uses FrankenPHP with PHP 8.4 and runs Laravel Octane on the specified port (from `SERVER_PORT`).

This compose only runs the Laravel container (web server + queue worker). Redis and DB are assumed to be in separate/prepared containers (or on host).

- For DB/Redis on the Docker host (or exposed): set `DOCKER_DB_HOST=host.docker.internal` and `DOCKER_REDIS_HOST=host.docker.internal` (the compose adds the extra_hosts for cross-platform support). The plain `DB_HOST`/`REDIS_HOST` in .env can stay as your "outside Docker" values.
- For DB/Redis in another container (on a shared Docker network): set `DOCKER_DB_HOST=the-container-name` and `DB_NETWORK=the-network-name`.
- For database and/or redis on completely different/remote servers (different machine, not Docker host, not sibling containers): leave `DOCKER_DB_HOST` and `DOCKER_REDIS_HOST` unset (or commented). The normal `DB_HOST` and `REDIS_HOST` from your .env will be used inside the container.
  - `docker-compose.yml` includes the `external-db` block using the syntax:
    ```yaml
    external-db:
      external: true
      name: ${DB_NETWORK:-docker_general_resource_default}
    ```
  - The block is effectively optional: the service only attaches to the local bridge in Compose. When `DB_NETWORK` is not set, Compose does not require the external network to exist, so there are no "not found" errors for aaPanel / host.docker.internal / fully remote cases.
  - `deploy.sh` will run `docker network connect $DB_NETWORK $CONTAINER` (and restart if needed) when the variable is set. This is what actually gives the container an IP on the external network for DNS resolution of other container names.

**If you see "SQLSTATE[HY000] [2002] Connection refused"** (e.g. "Host: 127.0.0.1" in the error):
- `docker-compose.yml` injects `DB_HOST` / `REDIS_HOST` via the `environment:` section.
- The precedence is: `DOCKER_DB_HOST` (if set) > normal `DB_HOST` from .env > built-in default.
- Re-run fully after any .env change: `make deployLocalDocker` (or `docker compose down && docker compose up -d`)
- **When DB/Redis live in sibling Docker containers**:
  1. Find the container name: `docker ps` (e.g. "mysql-service").
  2. In the active .env, set:
     ```
     DOCKER_DB_HOST=mysql-service
     DB_NETWORK=...   (the network name; the external-db block in compose.yml will use it, and deploy.sh will connect)
     ```
  3. deploy.sh will handle `docker network connect` (and container restart) if `DB_NETWORK` / `REDIS_NETWORK` is set.
- **When DB or Redis are on completely different servers** (remote IP, not this machine at all):
  - Do **not** set `DOCKER_DB_HOST` or `DOCKER_REDIS_HOST`.
  - Just use the normal `DB_HOST=5.223.64.249` / `REDIS_HOST=...` in .env. They will be used inside the container.
- **When reaching services on the Docker host** (aaPanel bare metal, published ports, etc.):
  - Set `DOCKER_DB_HOST=host.docker.internal` (and same for REDIS) so the container uses the gateway.
  - Leave your "host machine" values in the plain `DB_HOST`/`REDIS_HOST` if you also run the app outside Docker.
- Verify inside the container: `docker compose exec middleware-payment sh -c 'echo "Effective DB_HOST=$DB_HOST" "REDIS_HOST=$REDIS_HOST"'`
- Test TCP: `docker compose exec middleware-payment sh -c 'timeout 2 bash -c "</dev/tcp/$DB_HOST/3306" && echo open || echo refused'`

See `.env.docker` for examples. The `environment:` injection logic and comments are in docker-compose.yml. Network connect (when needed) is in deploy.sh.

```bash
make deployLocalDocker
```

For production:

```bash
make deployProduction
```

`deploy.sh` loads `.env` (or auto-copies from `.env.docker`), runs Docker Compose **synchronously** (realtime logs visible in your terminal/SSH + also saved to `docker-compose.log`), performs optional network connects, and sends the log file + success/failure to `DISCORD_WEBHOOK`.

The container runs both:

- Octane web server on port `${SERVER_PORT}` (mapped to 8000 inside)

- Queue worker for jobs like `SendMerchantCallback` and `SendNotificationJob` (Redis queue)

**Important for long-running / production uptime:**

We use `supervisord` (installed in the image) to manage the two processes.

This solves the common problem where a naive `php artisan octane:frankenphp & php artisan queue:work & wait` would cause `queue:work` to die/stop after long app duration (Octane worker recycling via `--max-requests`, memory pressure, unhandled signals on container restart, or the background shell process being reaped).

- Both processes are now independently `autorestart=true` under supervisor.
- If the queue worker exits for any reason it gets restarted automatically.
- Logs for each are in `storage/logs/octane*.log` and `storage/logs/queue-worker*.log` (also visible via `docker compose logs`).

**Admin React / Backoffice build:**

Yes — the main `docker compose build` (used by `./deploy.sh`, `make deployLocalDocker`, etc.) **now builds the admin React too**.

- The Dockerfile uses multi-stage build:
  - Stage 1 (`node:24`): `npm ci --force && npm run build` (builds `resources/js/backoffice.jsx` + Vite → `public/build/`)
  - Stage 2 (FrankenPHP): copies the app + overlays the freshly built React assets.
- This means you no longer need Node.js on the host machine to produce a complete production image. Changing React admin code + running `make deployLocalDocker` (or `docker compose build`) will include the latest admin UI.
- The old separate `docker compose -f docker-compose-no-container.yml run --rm pos-build` (the `make deploy` target) is still available if you only want to rebuild frontend assets locally (e.g. for volume-mounted dev or before a quick image rebuild).
- On Docker server deploys (aaPanel etc.), `deploy.sh` now extracts the freshly built `public/build` (with correct hashed `app-*.js` etc.) from the image to the host filesystem after `docker compose build`. This is required because:
  - `public/build` is in `.gitignore` (not in the source tree on server).
  - aaPanel nginx serves `/build/*` statically from the host `public/` (for performance + CORS).
  - The bind mount `.:/app` + anonymous volume for `public/build` in compose lets the *container* see image assets, but host needs explicit sync for nginx.
  - Without it you get 404s on JS/CSS like `app-B1GE-T08.js`.

In development with `volumes: - .:/app`, any `npm run build` you run on the host (or via the pos-build service) will be visible inside the container.

Check running processes inside the container:

```bash
docker compose exec middleware-payment ps aux | grep -E 'supervisord|octane|queue:work|php'
```

View logs:

- All container output (including queue:work verbose RUNNING/DONE): `docker compose logs -f middleware-payment | grep -i 'queue\|testqueue'`
- Application logs (your Log::info, errors, job messages): `docker compose exec middleware-payment tail -f storage/logs/laravel-*.log`
- Supervisor internal: `docker compose exec middleware-payment tail -f storage/logs/supervisord.log`
```

## aaPanel + Nginx: CORS for CSS/JS/assets (build/) and static files

If you still get CORS blocks on `/build/assets/*.css` and `*.js` (or `/storage/`) **after** the inner Docker Caddyfile changes, the cause is almost always the **outer nginx managed by aaPanel**.

### Why it still happens
- aaPanel runs nginx on the host as the public-facing server (TLS termination, port 80/443, etc.).
- Your site config (see the one you shared) typically has:
  - `root /www/wwwroot/.../be/public;` (so nginx can see the files on disk).
  - `location ^~ / { proxy_pass http://127.0.0.1:2000; ... }` to the FrankenPHP container.
  - Commented (or active via other includes) static handlers for `.js|css`.
  - An `include /www/server/panel/vhost/nginx/extension/.../*.conf;`
- When nginx serves a file directly from disk (common for performance on hashed build assets), the inner Caddy CORS headers are never reached.
- Even on proxied requests, aaPanel's generated includes, security rules, or default behavior can hide `Access-Control-*` response headers from the backend.
- Result: browser sees the asset response with no (or wrong) `Access-Control-Allow-Origin`, and blocks it.

The inner changes (Caddyfile + Laravel cors.php) are still valuable (they cover the pure Docker case and fallback), but you **must** also configure the outer nginx.

### Quick fix (recommended)
1. Copy the ready-made snippet into aaPanel's per-site extension include dir (it is already included at the top of your server block):

   ```bash
   # On the server (adjust the domain path if different)
   mkdir -p /www/server/panel/vhost/nginx/extension/payment.farivamed.com
   # Then copy the content of nginx/aaPanel-cors-static.conf from the project
   # into a new file, e.g.:
   cat > /www/server/panel/vhost/nginx/extension/payment.farivamed.com/cors-static.conf << 'EOF'
   # paste the full content of nginx/aaPanel-cors-static.conf here
   EOF
   ```

2. **Edit the paths**: Open the file and replace the `root /www/wwwroot/...` lines with the **exact same root** that appears in your current aaPanel nginx config for this site.

3. In aaPanel:
   - Site -> your site -> "Configuration File" (or just "Save" / "Reload Nginx" after the file is in the extension dir).
   - Or paste the location blocks directly into the site's nginx config editor (place the `/build/` and `/storage/` locations *before* your `location ^~ / { proxy_pass ... }` block).

4. Test:
   ```bash
   curl -I -H "Origin: https://farivamed.com" \
        https://payment.farivamed.com/build/assets/app-Ctoyd7mM.css
   # You must see:
   #   access-control-allow-origin: *
   ```

5. In the browser on the real domain: hard refresh the backoffice page and check DevTools → Network for the asset. The response must include the ACAO header.

The file `nginx/aaPanel-cors-static.conf` in this repo contains:
- Static-serving locations for `/build/` and `/storage/` (fast, disk-based) that **always** emit CORS headers (`always` flag).
- A broad regex for common static extensions.
- Explicit OPTIONS preflight handling.
- Instructions + the lines you should also add inside your existing `location ^~ / { proxy_pass ... }` block:
  ```nginx
  proxy_pass_header Access-Control-Allow-Origin;
  proxy_pass_header Access-Control-Allow-Methods;
  proxy_pass_header Access-Control-Allow-Headers;
  proxy_pass_header Access-Control-Expose-Headers;

  add_header Access-Control-Allow-Origin * always;
  add_header Access-Control-Allow-Methods "GET, HEAD, OPTIONS" always;
  add_header Access-Control-Allow-Headers "*" always;
  ```
  (This guarantees headers even for requests that go through the proxy to Docker.)

After this change you can leave (or uncomment) any old `location ~ .*\.(js|css)$` blocks — our rules will ensure CORS is attached.

### Alternative (simpler but slightly slower for assets)
If you don't want nginx to serve static files directly, just enhance the proxy location as shown above. All `/build/*` traffic will go to the container (our Caddyfile headers + the `add_header` fallback will apply). This is fine for most payment backoffice traffic volumes.

### After fixing nginx
- Rebuild the Docker image at least once (so the improved Caddyfile is inside): `./deploy.sh` or `make running`.
- The combination (outer nginx CORS for static + inner Caddy) makes the setup robust against future config drift.

If you still see blocks after applying the aaPanel snippet, share:
- The exact response headers from the `curl -I -H "Origin: ..."` test above.
- Whether the `server:` header in the response is `nginx` / `openresty` (outer) or `Caddy` (inner).
- Your current full location ^~ / block (or the part around proxy_pass).

This is the #1 cause on aaPanel + Docker + Octane setups.

## Architecture


The project follows the reference backend structure used in `fariva_med/backend`.

```text
app/
  Http/
    Controllers/
      Api/              API controllers
    Helper/             Shared response, request, log, and format helpers
  Model/
    Entity/             Eloquent models
    Request/            Form request classes
    Response/           API resource classes
  Models/               Compatibility wrappers for Laravel defaults and old imports
  Repository/
    Payment/            Payment data access and gateway repositories
    System/             System/domain data access
    BaseRepository.php  Shared repository helpers
  Services/
    Network/            HTTP/network service layer
    Payment/            Payment business logic
    Socket/             Socket notification integration
    System/             Project/system business logic
```

Controllers should stay thin. Business rules belong in `Services`, database access belongs in `Repository`, and API serialization belongs in `Model/Response`.

## Main API Groups

- `POST /api/order/create`
- `GET /api/order`
- `GET /api/order/detail`
- `GET /api/order/checkOrderStatus`
- `POST /api/order/stripe/confirm` (send pm_xxx / tok_ or raw card; endpoint can create token/PM server-side from raw)
- `POST /api/payment/createPayment`
- `GET /api/payment/getPaymentCategory`
- `GET /api/payment/getPaymentMethod`
- `GET /api/payment/getDetailPaymentMethod`
- `POST /api/callback/duitku`
- `POST /api/callback/midtrans`
- `POST /api/callback/xendit`
- `POST /api/callback/spnpay`
- `GET /api/project`
- `POST /api/project/create`
- `PUT /api/project/{id}`

### Stripe Specific

Stripe supports two flows via `POST /api/order/create` (with project `Token` header). Made simple:

- **No `flow` sent (default)**: Hosted URL checkout → returns `link` (redirect user to Stripe's hosted payment page, like Duitku snap / payment URL). This is the simple "use link" path.
- **`flow: "direct"` (or `flow: "creditcard"`, `flow: "card"`, `flow: "intent"`, etc.)**: Direct credit card / PaymentIntent flow → creates PaymentIntent, returns `client_secret` + full `data`. Use this for credit card handling (send card data at create time or later via confirm).

**Hosted "link" flow (default when flow not sent — recommended for simple URL checkout like Duitku):**

Just send your normal request (no `flow`, or `flow` anything other than the direct values above). Common customer fields are passed through to pre-fill the Stripe page:

Example (this exact request shape gives you the `link`):
```json
{
    "paymentRepositoryId": "019e8383-880a-7249-b9da-079b9844de25",
    "paymentAmount": 15000,
    "paymentMethod": "SP",
    "merchantOrderId": "006",
    "productDetails": "Pembayaran",
    "expiryPeriod": 100,
    "mode": "sandbox",
    "firstName": "Sahid",
    "lastName": "R",
    "email": "sahidrahutomo@gmail.com",
    "address": "klaten",
    "phone": "08815123766",
    "currency": "myr",
    "returnUrl": "https://yourapp.com/callback/stripe"
}
```
Response includes `"link": "https://checkout.stripe.com/..."` (customer is sent to Stripe's hosted checkout page).

To force hosted explicitly you can also send `flow: "checkout"`, `flow: "hosted"`, `flow: "link"`, etc. (anything except the direct values).

The following fields from other gateways are supported on the hosted path:
- `email`, `firstName`, `lastName`, `phone`, `address` (prefill)
- `expiryPeriod` (minutes)
- `returnUrl` (we append `?session_id={CHECKOUT_SESSION_ID}` for verification)
- `paymentMethod`, `paymentRepositoryId`, `mode`, `productDetails`, `currency`, etc.

Create response (both direct and checkout) now also includes at top level:
- `reference`
- `merchantOrderId`
- `publishable_key` (the correct `pk_test_...` or `pk_live_...` for the mode you requested — perfect to use directly in Stripe.js on the client)

**Typical two-step flow for direct card (the "send credit card data after create" use case):**

1. `POST /api/order/create` with `flow: "direct"` + `"mode": "prod"` (for production) → PI is created with `status: "requires_payment_method"`.

   Example request for production:
   ```json
   {
     "merchantOrderId": "004",
     "paymentAmount": 150,
     "currency": "myr",
     "flow": "direct",
     "mode": "prod"
   }
   ```

   Example response:
   ```json
   {
     "message": "Success Create Order",
     "reference": "AP-004",
     "merchantOrderId": "004",
     "publishable_key": "pk_live_...",
     "data": { "id": "pi_...", "status": "requires_payment_method", ... },
     "client_secret": "pi_..._secret_..."
   }
   ```

2. **Next step — send the card data** using `POST /api/order/stripe/confirm` (same `Token` header):

   ```json
   {
     "reference": "AP-004",
     // or "merchantOrderId": "004"
     "card_number": "4242424242424242",
     "card_exp_month": 12,
     "card_exp_year": 2030,
     "card_cvc": "123"
   }
   ```

   **Best for testing (avoids the "Sending credit card numbers directly... unsafe" error completely):**
   Use Stripe test **tokens** — no real card number ever leaves your server:
   ```json
   {
     "reference": "AP-004",
     "token": "tok_visa"
   }
   ```
   Common ones:
   - `tok_visa` (success 4242...)
   - `tok_mastercard`, `tok_amex`, `tok_discover`, etc.
   Full list: https://stripe.com/docs/testing#cards

   If you send raw `card_number` etc. you will hit exactly the error you reported (Stripe blocks direct PANs by default, even in test mode, for PCI reasons). Tokens are the recommended path.

   **Server-side token creation from raw card (token created via endpoint):**
   Yes — if you send raw card details to the endpoint, the middleware will attempt to create a Stripe Token server-side then a PM.

   **However:** Sending raw `card_number` etc. will frequently return exactly the error you just got ("Sending credit card numbers directly to the Stripe API is generally unsafe..."). This is Stripe enforcing the rule by default.

   To use the raw path reliably:
   - For quick tests: prefer the token field above.
   - To enable raw card data on your Stripe account (test + live): follow https://support.stripe.com/questions/enabling-access-to-raw-card-data-apis (Stripe support request; not instant, and you still need PCI compliance on your side).

   Example raw payload:
   ```json
   {
     "reference": "AP-004",
     "card_number": "4242424242424242",
     "card_exp_month": 12,
     "card_exp_year": 2030,
     "card_cvc": "123"
   }
   ```

   **Critical security note:** The raw PAN will hit this middleware server. The operator of the middleware must be PCI-DSS compliant (this is not a toy path). In production you should **never** accept raw card data on your servers — tokenize on the client with Stripe.js / SDKs and send only `pm_xxx` or `token`. The raw path exists only for legacy/backward compatibility with callers that insist on sending card details. Use at your own (compliance) risk.

   **PCI safer (production) — How to actually create the token / pm_ in production:**

   You **must** generate the `token` (or `pm_xxx`) **on the client side** (browser / mobile app) using Stripe.js or the official Stripe mobile SDKs. Never collect raw card details on your own servers in production.

   ### For Web (Stripe.js + Elements)

   ```html
   <!-- Include Stripe.js -->
   <script src="https://js.stripe.com/v3/"></script>

   <form id="payment-form">
     <div id="card-element"></div>
     <button type="submit">Pay Now</button>
   </form>

   <script>
     // 1. First call your backend to create the order (with mode: "prod" for production)
     //    const createRes = await fetch('/api/order/create', { ... });
     //    const createData = await createRes.json();
     //
     // 2. Use the publishable_key returned from create (correct for sandbox or prod)
     const stripe = Stripe(createData.publishable_key);

     const elements = stripe.elements();
     const card = elements.create('card');
     card.mount('#card-element');

     const form = document.getElementById('payment-form');
     form.addEventListener('submit', async (e) => {
       e.preventDefault();

       // Modern recommended way: create a PaymentMethod (no raw card data to your server)
       const { error, paymentMethod } = await stripe.createPaymentMethod({
         type: 'card',
         card: card,
       });

       if (error) {
         console.error(error);
         return;
       }

       // Send only the safe ID + the reference you got from create
       const res = await fetch('/api/order/stripe/confirm', {
         method: 'POST',
         headers: {
           'Content-Type': 'application/json',
           'Token': 'YOUR_PROJECT_TOKEN'
         },
         body: JSON.stringify({
           reference: createData.reference,
           payment_method: paymentMethod.id
         })
       });

       const result = await res.json();
       console.log(result);
     });
   </script>
   ```

   **Legacy token way** (still supported):
   ```js
   const { token, error } = await stripe.createToken(card);
   // then send { reference: "...", token: token.id }
   ```

   **Important for production:**
   - Use your **live publishable key** (`pk_live_...`) in the browser.
   - When calling the initial `/api/order/create`, send `"mode": "prod"` so the middleware picks up your live Stripe secret key from the payment repository.
   - The `client_secret` returned from create can also be used directly with `stripe.confirmCardPayment(clientSecret, ...)` if you want to do the entire confirmation on the client (your server sees zero card data).

   ### For Mobile Apps
   - **iOS / Android**: Use the official Stripe SDKs (`STPPaymentCardTextField` etc.) → call `createPaymentMethod` or `createToken`.
   - **React Native / Flutter**: Use `@stripe/stripe-react-native` or equivalent.
   - These SDKs return a `paymentMethodId` or `tokenId` that you send to `/api/order/stripe/confirm`.

   Your backend (this middleware) then safely forwards the `pm_xxx` or `token` to Stripe using the secret key.

   **Never** send raw `card_number` from production client code or your servers unless you have completed full PCI SAQ D compliance.

   More details: https://stripe.com/docs/payments/accept-a-payment#web-collect-card-details

   Test cards (when using raw or tokens):
   - Success: `4242 4242 4242 4242` (any future month/year, any 3 digits CVC)
   - Decline (generic): `4000 0000 0000 0002`
   - Insufficient funds: `4000 0000 0000 9995`
   - 3D Secure: `4000 0025 0000 3155` (may require `next_action` handling)

3. After successful confirm, the PI status becomes `succeeded`, local order is marked SUCCESS, and the merchant callback is fired (if configured on the Project).

**Note:** Merchant callbacks (including the one from `sendSuccessCallback`) are now dispatched to the queue (using `SendMerchantCallback` job with `afterCommit()`) so they don't block the HTTP response or hold DB transactions open. The actual HTTP notification to your project's callback URL happens asynchronously via the queue worker.

You can poll status anytime with:
`GET /api/order/checkOrderStatus?reference=AP-004`

The `/order/stripe/confirm` endpoint is the dedicated "send credit card data to Stripe" endpoint meant to be called **after** the create order endpoint for the direct flow.

**Important – Amount format for Stripe**:
- `paymentAmount` is the **nominal amount** (e.g. `150` = RM150.00).
- Automatically converted to minor units for Stripe (×100 for MYR/IDR/USD etc.).
- Zero-decimal currencies (JPY etc.) are not multiplied.

Callbacks and webhooks work for both flows. Prefer `pm_xxx` from the client when possible.

## Useful Commands

```bash
php artisan test
php artisan route:list --path=api
composer dump-autoload
make freshInstall
make initSeeder

# Run queue worker (required for queued jobs like SendMerchantCallback, SendNotificationJob)
php artisan queue:work --queue=default --tries=5 --timeout=60
```

**In Docker** the worker is managed by supervisord inside the container (no need to run the command manually).
See the "Docker Deployment" section above for details and log locations.
```

## Verification

Before pushing changes, run:

```bash
find app database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l
php artisan test
```
