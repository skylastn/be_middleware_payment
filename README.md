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

## Queue Configuration (Redis + Telescope Monitoring)

Merchant callbacks (e.g. Stripe success notifications via `SendMerchantCallback` job) are queued.

- Set `QUEUE_CONNECTION=redis` (already in `.env.example`).
- Redis is in a separate/prepared container (not included here). Set `REDIS_HOST` in `.env` to the Redis container's address (e.g. its Docker service name or `host.docker.internal`).
- Install Telescope for monitoring (queues, jobs, requests, exceptions, etc.):

```bash
# Already done in setup, but if needed:
php artisan telescope:install
php artisan migrate
```

- Run queue worker:
  - Locally (outside docker): `php artisan queue:work --queue=default --tries=5 --timeout=60`
  - In this Docker setup: the queue worker runs automatically inside the container alongside Octane (no separate command needed).

- Access Telescope at `/telescope` (only in local environment by default; see `app/Providers/TelescopeServiceProvider.php`).

In Telescope:
- Go to "Jobs" tab to monitor `SendMerchantCallback` (queued merchant callbacks).
- "Queues" / failed jobs for overview.
- Use with Redis: `QUEUE_CONNECTION=redis` + your external/prepared Redis container (this docker-compose does NOT include Redis).

To start monitoring: run `php artisan queue:work` in one terminal, trigger a Stripe success callback (e.g. via /order/stripe/confirm with success token), and watch in Telescope.

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

This compose only runs the Laravel container (web server + queue worker). Redis is assumed to be in a separate/prepared container — set `REDIS_HOST` in your `.env` to point to it (e.g. the other container's name or host.docker.internal).

```bash
make deployLocalDocker
```

For production:

```bash
make deployProduction
```

`deploy.sh` loads `.env`, runs Docker Compose, writes logs to `docker-compose.log`, and sends the result to `DISCORD_WEBHOOK`.

The container runs both:

- Octane web server on port `${SERVER_PORT}` (mapped to 8000 inside)

- Queue worker for jobs like `SendMerchantCallback` (Redis queue)

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

Stripe supports two flows via `POST /api/order/create` (with project `Token` header):

- **Hosted Checkout (default, backward compatible)**: returns `link` (redirect user to Stripe hosted page).
- **Direct card / PaymentIntent** (what you are using): Pass `flow: "direct"` (or `direct: true`, `paymentMethod: "card"`) → creates a PaymentIntent and returns `client_secret` + full `data`.

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

# Run queue worker (required for queued merchant callbacks like SendMerchantCallback)
php artisan queue:work --queue=default --tries=5 --timeout=60
```

## Verification

Before pushing changes, run:

```bash
find app database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l
php artisan test
```
