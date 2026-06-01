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
```

Run migrations and seed the initial payment data:

```bash
php artisan migrate
make initSeeder
```

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

The Docker image uses FrankenPHP with PHP 8.4 and runs Laravel Octane.

```bash
make deployLocalDocker
```

For production:

```bash
make deployProduction
```

`deploy.sh` loads `.env`, runs Docker Compose, writes logs to `docker-compose.log`, and sends the result to `DISCORD_WEBHOOK`.

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

## Useful Commands

```bash
php artisan test
php artisan route:list --path=api
composer dump-autoload
make freshInstall
make initSeeder
```

## Verification

Before pushing changes, run:

```bash
find app database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l
php artisan test
```
