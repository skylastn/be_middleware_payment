# 💳 Payment Middleware & Aggregator Backend

[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![Octane FrankenPHP](https://img.shields.io/badge/Octane-FrankenPHP-00ADD8?style=flat&logo=caddy&logoColor=white)](https://frankenphp.dev)
[![React 19](https://img.shields.io/badge/React-19-61DAFB?style=flat&logo=react&logoColor=black)](https://react.dev)
[![Postman](https://img.shields.io/badge/Postman-Workspace%20v2-FF6C37?style=flat&logo=postman&logoColor=white)](https://www.postman.com/solar-moon-928951/middleware-payment-v2)
[![Donate Saweria](https://img.shields.io/badge/Donate-Saweria-E8A838?style=flat&logo=coffeescript&logoColor=white)](https://saweria.co/skygamings)

High-performance payment gateway aggregation platform that normalizes payment flows across multiple providers (**Duitku, Midtrans, Xendit, SPNPay, Stripe**) into a single, unified API interface. Built with Laravel 13, Octane FrankenPHP, and React 19 Backoffice SPA.

---

## 🧭 Documentation Navigation

| Topic | Description | Link |
|:---|:---|:---:|
| 🚀 **Installation & Deployment** | Step-by-step local setup, Docker container, Supervisor, Redis queue, and aaPanel Nginx configuration. | [📖 **Open Setup Guide**](docs/installation.md) |
| 📬 **Postman Workspace** | Interactive API documentation, collection runner, and public environment variables. | [🌐 **Open Postman Workspace**](https://www.postman.com/solar-moon-928951/middleware-payment-v2) |
| 💳 **Payment Gateways** | Gateway-specific integration guides, JSON credentials schemas, and Snap vs Direct API flows. | [📂 **Explore Gateways**](#-supported-payment-gateways) |
| 💖 **Support the Author** | Donate via Saweria or Crypto (BNB / ETH / Solana). | [☕ **Support & Donate**](#-support-the-author--donations) |

---

## 📬 Postman API Collection & Workspace

All API endpoints are documented and ready for interactive testing in our official Postman Workspace:

🔗 **[Postman Workspace: Middleware Payment v2](https://www.postman.com/solar-moon-928951/middleware-payment-v2)**

### ⚙️ Postman Environment Variables:
When importing or forking the collection to your workspace, configure the following variables:

| Variable | Example Value | Description |
|:---|:---|:---|
| `baseUrl` | `https://payment.yourdomain.com` | Base URL of the payment middleware service. |
| `Token` | `YOUR_PROJECT_TOKEN` | Merchant project authentication token (`projects.value`). |
| `PAYMENT_APP_KEY` | `wqsakdnwljalhcxz` | Header secret key for payment discovery endpoints. |
| `adminEmail` | `admin@payment.com` | Admin login email for backoffice endpoints. |
| `adminPassword` | `@Support2025!` | Admin login password for backoffice endpoints. |

### 🔄 Automated Collection Sync:
To export or sync the Postman collection directly from route annotations in Laravel:
```bash
php artisan postman:sync
```

---

## 💳 Supported Payment Gateways

The middleware unifies multiple payment gateways behind standard order and callback payloads:

### Integration Support Matrix

| Payment Gateway | 🪟 Snap / Hosted UI | ⚡ Direct API (Headless) | Key Channels | Detailed Guide |
|:---|:---:|:---:|:---|:---:|
| **Duitku** | ✅ `Supported` (POP / Web Redirect) | ✅ `Supported` (Direct VA & QRIS) | VA (All Major Banks), QRIS, OVO, DANA, ShopeePay, Indomaret, Alfamart, CC | [📖 **Duitku Guide**](docs/gateways/duitku.md) |
| **Midtrans** | ✅ `Supported` (Snap Popup / URL) | 🔄 `Via Snap API` (Snap Core Token) | BCA/Mandiri/BNI/BRI VA, Permata, QRIS, GoPay, ShopeePay, Cards | [📖 **Midtrans Guide**](docs/gateways/midtrans.md) |
| **Xendit** | ✅ `Supported` (Invoice Checkout) | 🔄 `Via XenInvoice API` (Multi-channel) | XenInvoice, Major Bank VAs, QRIS, DANA, OVO, LinkAja, Cards | [📖 **Xendit Guide**](docs/gateways/xendit.md) |
| **SPNPay** | ✅ `Supported` (Payment Portal URL) | ✅ `Supported` (Direct ClosedAmount) | ClosedAmount QRIS, Virtual Account, Direct Online Debit | [📖 **SPNPay Guide**](docs/gateways/spnpay.md) |
| **Stripe** | ✅ `Supported` (Hosted Checkout) | ✅ `Supported` (PaymentIntent + SDK) | Credit/Debit Cards (Global), Apple Pay, Google Pay, FPX, 135+ Currencies | [📖 **Stripe Guide**](docs/gateways/stripe.md) |

> [!TIP]
> **Select the Integration Mode That Fits Your Product:**
> - **🪟 Snap / Hosted UI**: Best for fast implementations without building custom checkout UI (customers are redirected to official payment gateway pages or popup modals).
> - **⚡ Direct API (Headless)**: Best for seamless in-app user experiences (Virtual Account numbers, QRIS strings, or payment instructions rendered directly in your application).

---

## ⚡ Quick Start (Local Setup)

```bash
# 1. Install dependencies
composer install && npm install

# 2. Setup Environment
cp .env.example .env && php artisan key:generate

# 3. Database Migration & Seed Initial Gateways
php artisan migrate && make initSeeder

# 4. Build Frontend & Start Server
npm run build && make run
```

> [!NOTE]
> For complete instructions regarding Docker deployment, Supervisor process management, Telescope, and aaPanel Nginx reverse proxy configuration, consult **[`docs/installation.md`](docs/installation.md)**.

---

## 🏗️ Architecture & Core Endpoints

### Layered Architecture
```text
Routes -> Controllers (Api) -> Services (Strategy Router) -> Repositories -> Eloquent Entities
                      |
                      +-- Helpers (ResponseHelper, LogHelper, FormatHelper)
                      |
                      +-- Async Jobs (SendMerchantCallback, SendNotificationJob)
```

### Main API Endpoints

- **Admin Backoffice (Protected by Sanctum Token + Admin Role):**
  - `GET /api/admin/dashboard` — Live operations dashboard & metric aggregations.
  - `GET /api/admin/gateway-history` — Live transaction history direct inquiry against third-party gateway APIs.
  - `GET /api/admin/orders` — Paginated orders list with date range & repository filters.
  - `GET /api/admin/orders/{id}` — Order details with raw payload inspection.
  - `POST /api/admin/orders/{id}/resend-callback` — Manually retry merchant webhook delivery.
  - `POST /api/admin/orders/{id}/set-success` — Mark order as SUCCESS and dispatch merchant callback webhook.
  - `GET /api/admin/projects` — Manage merchant project configurations.
  - `GET /api/admin/payment-gateways` — Manage gateway adapters.
  - `GET /api/admin/payment-repositories` — Manage credentials sets per environment mode.
  - `POST /api/admin/payment-repositories/{id}/test-order` — Simulate test order creation with API version selection (`v1`/`v2`) & credentials testing.
  - `GET /api/admin/payment-methods` — Configure payment channels.
  - `GET /api/admin/payment-categories` — Manage UI payment categories.
  - `GET /api/admin/settings` — Backoffice platform settings.
- **Client Payment (Frontend Flow - Protected by Expirable Redis Token):**
  - `GET /api/client/order/detail` — Retrieve order details by reference using temporary Redis token.
  - `GET /api/client/order/checkOrderStatus` — Inquire payment status for client checkout.
  - `POST /api/client/order/createPayment` — Create/process payment method transaction for client checkout.
  - `GET /api/client/payment/getPaymentCategory` — Retrieve payment categories for client UI.
  - `GET /api/client/payment/getPaymentMethod` — Retrieve payment methods for client UI.
  - `GET /api/client/payment/getDetailPaymentMethod` — Get details for a specific payment method.
- **Order Management (Server-to-Server - Protected by Merchant Project Token):**
  - `POST /api/order/create` — Create a new payment order (auto-routed to the configured gateway, supports `version` parameter for tokenized checkout).
  - `GET /api/order` — Fetch list of payment orders.
  - `GET /api/order/detail` — Retrieve order details by reference.
  - `GET /api/order/checkOrderStatus` — Real-time status inquiry directly against the payment gateway engine.
  - `POST /api/order/set-success` — Mark order as SUCCESS and trigger webhook callback for project.
  - `POST /api/order/stripe/confirm` — Confirm PaymentIntent for Stripe direct card flow.
- **Payment Discovery (Server-to-Server / Public):**
  - `POST /api/payment/createPayment` — Create payment transaction (Protected by Merchant Project Token).
  - `GET /api/payment/getPaymentCategory` — Retrieve active payment categories.
  - `GET /api/payment/getPaymentMethod` — Retrieve active payment methods.
  - `GET /api/payment/getDetailPaymentMethod` — Get details for a specific payment method.
- **Gateway Webhooks:**
  - `POST /api/callback/duitku` — Webhook handler for Duitku.
  - `POST /api/callback/midtrans` — Webhook handler for Midtrans.
  - `POST /api/callback/xendit` — Webhook handler for Xendit.
  - `POST /api/callback/spnpay` — Webhook handler for SPNPay.
  - `POST /api/callback/stripe` — Webhook handler for Stripe.
  - `POST /api/callback/paprika` — Webhook handler for Paprika (SNAP 1.0 format).
- **Merchant Project Management:**
  - `GET /api/project` — List registered merchant projects.
  - `POST /api/project/create` — Register a new merchant project.

---

## 🧪 Testing & Verification

Run automated quality and unit tests:

```bash
# Lint frontend TypeScript/React
npm run lint

# Build frontend bundle
npm run build

# Run PHPUnit Test Suite
php artisan test
```

---

## 💖 Support the Author / Donations

If this project saves you time or helps power your business, consider supporting the continuous development and maintenance!

### ☕ Indonesian Rupiah (QRIS / E-Wallet)
Support via Saweria:
👉 **[saweria.co/skygamings](https://saweria.co/skygamings)**

### 🪙 Crypto Donations

| Network / Asset | Address |
|:---|:---|
| **BNB (BEP20)** | `0x4927b932b306a214594cd98a98027b7b44fe6e2c` |
| **Ethereum (ERC20)** | `0x4927b932b306a214594cd98a98027b7b44fe6e2c` |
| **Solana (SPL)** | `DEwU3LB2R8987EXCjPEzReUN8P1HJDNBFmtDFdLrr5Z1` |

Thank you for your generous support! 🙏
