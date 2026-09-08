# 🛠️ Installation & Deployment Guide

Comprehensive guide for local setup, environment configuration, database migrations, queue workers, and production deployment with Docker & aaPanel Nginx.

---

## 📋 Requirements

Ensure your server or local environment meets the following prerequisites:

- **PHP**: ^8.4 (with extensions `pdo_mysql`, `redis` / `predis`, `bcmath`, `curl`, `mbstring`, `openssl`)
- **Composer**: ^2.2
- **Node.js & npm**: Node.js ^20 / ^22
- **Database**: MySQL ^8.0 / MariaDB ^10.6
- **Cache & Queue**: Redis ^7.0 or RabbitMQ ^3.12
- **Container Engine**: Docker & Docker Compose (for containerized deployment)

---

## 💻 Local Development Setup

### 1. Clone Repository & Install Dependencies

```bash
# Clone the repository
git clone https://github.com/skylastn/be_middleware_payment.git
cd be_middleware_payment

# Install PHP dependencies
composer install

# Install Frontend dependencies
bun install
```

### 2. Environment Configuration

Copy the template file `.env.example` to `.env`:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your `.env` settings:

```env
APP_NAME=Middleware_Payment
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:2000
SERVER_PORT=2000

# Timezone (WIB)
APP_TIMEZONE=Asia/Jakarta
TZ=Asia/Jakarta
DB_TIMEZONE=+07:00

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_middleware_payment
DB_USERNAME=root
DB_PASSWORD=secret

# Queue & Cache
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Admin Backoffice Credentials
ADMIN_EMAIL=admin@payment.com
ADMIN_PASSWORD=SuperSecretPassword123!
TELESCOPE_ENABLED=true
TELESCOPE_ALLOWED_EMAIL=admin@payment.com
```

### 3. Database Migration & Seeding

Run database migrations and seed default payment gateways and admin user:

```bash
# Execute table migrations
php artisan migrate

# Seed initial gateways, methods, categories, and admin credentials
make initSeeder
```

### 4. Build Frontend & Run Local Server

```bash
# Build React Backoffice UI bundle
bun run build

# Start local server (Port 2000)
make run
```

---

## 🐳 Docker Deployment (FrankenPHP + Octane + Supervisor)

The production Docker image uses **FrankenPHP (PHP 8.4)** with **Laravel Octane** managed by **Supervisor** to run both the HTTP web server and background queue worker inside a single container.

### 1. Key Docker Configuration Files

- **[`Dockerfile`](../Dockerfile)**: Multi-stage build (Stage 1: Vite React builder, Stage 2: FrankenPHP Octane runtime).
- **[`docker-compose.yml`](../docker-compose.yml)**: Service definitions, port bindings, environment variable injection, and persistent volume mounts.
- **[`deploy.sh`](../deploy.sh)**: Automated build script, container initialization, and frontend asset synchronization.

### 2. Run Deployment

```bash
# Local container deployment
make deployLocalDocker

# Production deployment script
bash deploy.sh
```

### 3. Supervisor Process Management Inside Container

The container automatically runs and restarts two independent processes:
1. **Octane Web Server**: `php artisan octane:frankenphp --port=8000 --host=0.0.0.0`
2. **Queue Worker**: `php artisan queue:work redis --queue=default --tries=5 --timeout=60`

Inspect running processes inside the container:
```bash
docker compose exec middleware-payment ps aux | grep -E 'supervisord|octane|queue:work|php'
```

View realtime logs:
```bash
# Container & queue logs
docker compose logs -f middleware-payment

# Laravel application logs
docker compose exec middleware-payment tail -f storage/logs/laravel-*.log
```

---

## 🌐 aaPanel & Nginx Reverse Proxy Setup

When running behind aaPanel or an external host Nginx reverse proxy:

1. Create the Nginx extension directory in aaPanel:
   ```bash
   mkdir -p /www/server/panel/vhost/nginx/extension/payment.yourdomain.com
   ```
2. Copy the template from `nginx/aaPanel-cors-static.conf` into that directory.
3. Ensure the `location ^~ /` block proxies to the Docker container port:
   ```nginx
   location ^~ / {
       proxy_pass http://127.0.0.1:2000;
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
   }
   ```
4. Reload Nginx in aaPanel.

---

## 🚦 Queue Configuration

Merchant callback notifications and socket broadcasts are processed asynchronously via queues:
- **`SendMerchantCallback`**: Dispatches HTTP POST callbacks to registered merchant URLs with automatic retry (5 attempts, backoff 10s).
- **`SendNotificationJob`**: Broadcasts real-time events via Socket/Pusher.

### Supported Queue Backends:
| Driver | `.env` Key | Key Advantage | Recommendation |
|:---|:---|:---|:---:|
| **Redis** | `QUEUE_CONNECTION=redis` | High throughput, in-memory speed | ⭐ **Production** |
| **RabbitMQ** | `QUEUE_CONNECTION=rabbitmq` | Enterprise message broker, durable routing | ⭐ **Large Scale** |
| **Database** | `QUEUE_CONNECTION=database` | Zero external dependencies | 🧪 **Development** |

---

## 🔐 Log Viewer & Telescope Access

Internal system monitoring dashboards are secured with httpOnly token authentication:

- **Log Viewer**: `https://yourdomain.com/log-viewer`
- **Laravel Telescope**: `https://yourdomain.com/telescope`
- **Backoffice Admin**: `https://yourdomain.com/login`

Log in using the seeded `ADMIN_EMAIL` and `ADMIN_PASSWORD` credentials.

---

## 💖 Support & Donations

If you find this middleware useful, feel free to support the creator:
- **Saweria**: [saweria.co/skygamings](https://saweria.co/skygamings)
- **BNB (BEP20)**: `0x4927b932b306a214594cd98a98027b7b44fe6e2c`
- **ETH (ERC20)**: `0x4927b932b306a214594cd98a98027b7b44fe6e2c`
- **Solana (SPL)**: `DEwU3LB2R8987EXCjPEzReUN8P1HJDNBFmtDFdLrr5Z1`
