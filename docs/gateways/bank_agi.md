# Bank Artha Graha Internasional QRIS integration

The `bank_agi` gateway supports dynamic QRIS creation, transaction status queries, and authenticated payment notifications. It follows the AGI BI SNAP QRIS Acquirer document version 1.4 dated 1 September 2025, attached to [work item 163](https://gitlab.com/anugerah-bersama/food-order-management/-/work_items/163).

The document covers QRIS. Virtual Account creation and notifications are unavailable until AGI supplies the VA contract. The registered `notify-va` endpoint returns HTTP 501 and does not update orders. QRIS refunds are described by the bank document but are not exposed by this integration.

## Configuration

Apply the order reference migration and register the gateway and payment method through the existing seeders:

```sh
php artisan migrate
php artisan db:seed --class=PaymentGatewaySeeder
php artisan db:seed --class=PaymentCategorySeeder
php artisan db:seed --class=PaymentMethodSeeder
```

Create a Payment Repository for gateway key `bank_agi`, then select `bank_agi` as the merchant project's gateway strategy. Configure its JSON value with the credentials assigned by AGI:

```json
{
  "base_url": "https://bagiapisandbox.ag.co.id:38065",
  "client_id": "CLIENT_ID_ASSIGNED_BY_AGI",
  "client_secret": "BASE64_SECRET_ASSIGNED_BY_AGI",
  "private_key": "-----BEGIN PRIVATE KEY-----\nMERCHANT_PRIVATE_KEY\n-----END PRIVATE KEY-----",
  "bank_client_id": "BANK_CALLBACK_CLIENT_ID",
  "bank_public_key": "-----BEGIN PUBLIC KEY-----\nBANK_PUBLIC_KEY\n-----END PUBLIC KEY-----",
  "bank_client_secret": "BASE64_CALLBACK_SECRET",
  "merchant_id": "MERCHANT_ID_ASSIGNED_BY_AGI",
  "merchant_user": "MERCHANT_USER_ASSIGNED_BY_AGI",
  "channel_id": "95221",
  "validity_period": 60,
  "sub_merchant_id": "",
  "store_id": "",
  "terminal_id": "",
  "device_id": "",
  "channel": "API"
}
```

All credential values above are placeholders. Replace `channel_id` with the five digit PJP channel ID agreed with AGI. Set the production URL and production credentials in a separate `prod` repository; the supplied document only specifies the sandbox URL. `base_url` must contain the HTTPS host and optional port, without credentials, an API path, or a query. Bank requests do not follow redirects.

Set `BANK_AGI_ALLOWED_HOSTS` to the comma separated bank hostnames approved for this deployment. The default permits only `bagiapisandbox.ag.co.id`. Add the production hostname supplied by AGI before enabling production, then rebuild Laravel's configuration cache. URLs pointing to other hosts are rejected before a network request.

| Field | Purpose |
| --- | --- |
| `client_id`, `client_secret`, `private_key` | Required for outbound bank requests. The secret must be Base64 encoded and the private key must be an RSA PEM key. |
| `merchant_id`, `merchant_user`, `channel_id` | Required merchant identity and PJP channel agreed with the bank. |
| `bank_public_key` | Required to verify AGI's RSA signature when AGI obtains a callback token from the middleware. |
| `bank_client_id`, `bank_client_secret` | Callback identity and Base64 encoded secret. If omitted, the middleware uses `client_id` and `client_secret`, respectively. Configure separate values when supplied by AGI. |
| `validity_period` | QR validity in minutes, default 60. A request's `expiryPeriod` can override it. |
| `sub_merchant_id`, `store_id`, `terminal_id`, `device_id`, `channel` | Optional values passed to the bank payload. |

The inbound token and callback security flow is implemented for the middleware endpoints requested in the work item. Confirm the callback credentials and endpoint registration with AGI during sandbox onboarding. The document's token lifetime table and example use inconsistent units. Outbound tokens are cached in Redis for at most 60 seconds, interpreting a positive numeric `expiresIn` as seconds and subtracting a five second margin. This uses the shorter documented interpretation. Tokens with absent or invalid expiry are not cached; rotating the repository's URL or outbound credentials changes the cache key. Failure of this optional cache falls back to requesting a token. Middleware callback tokens expire after 900 seconds and are bound to one AGI repository.

Repository configuration is available through the admin APIs with Sanctum authentication and the admin role. The legacy `/api/payment-repositories/{id}` read and update routes now also require admin authentication; a merchant `Token` no longer grants access. Order responses include repository metadata and project metadata without gateway credentials or the merchant's authentication token.

## Bank requests and signatures

| Operation | Bank path | Successful response code |
| --- | --- | --- |
| Access token | `/api/v1/bisnap/access-token` | `2007300` |
| Generate QRIS | `/snap/api/v1.0/qr/qr-mpm-generate` | `2001700` |
| Query QRIS | `/snap/api/v1.0/qr/qr-mpm-query` | `2001800` |

Outbound headers use the underscore names documented by AGI: `X_CLIENT_KEY`, `X_TIMESTAMP`, and `X_SIGNATURE` for access tokens; `Authorization`, `X_PARTNER_ID`, `X_TIMESTAMP`, `X_SIGNATURE`, `X_EXTERNAL_ID`, and `CHANNEL_ID` for transactions. Inbound middleware endpoints also accept the corresponding hyphen names, including `X-CLIENT-KEY` and `X-PARTNER-ID`. Configure proxies to forward these headers; nginx deployments using underscore headers may require `underscores_in_headers on`.

The token signature is Base64 encoded RSA SHA256 over `client_id|timestamp`. The transaction signature is lowercase hexadecimal HMAC SHA512 using the Base64 decoded secret over:

```text
HTTP_METHOD:ENDPOINT_PATH:ACCESS_TOKEN:SHA256(MINIFIED_BODY):TIMESTAMP
```

Body minification removes JSON formatting whitespace and preserves spaces and escapes inside string values. The path includes the full API prefix and excludes query parameters. Outbound timestamps use UTC with milliseconds. Inbound timestamps must be valid ISO 8601 and within five minutes of server time. Tokens, signatures, private keys, and client secrets are excluded from AGI request logs.

## Create an order

Send `POST /api/order/create` with the merchant's `Token` header:

```json
{
  "merchantOrderId": "INV-AGI-001",
  "paymentAmount": 3500,
  "paymentMethod": "AGI_QRIS",
  "paymentRepositoryId": "REPOSITORY_UUID",
  "mode": "sandbox",
  "expiryPeriod": 60,
  "version": "1"
}
```

Both `qris` and `AGI_QRIS` select the AGI QRIS method. Amounts must be positive decimal values with at most two decimal places, up to `9999999999999.99`; extra precision and numeric strings using scientific notation are rejected. The middleware sends the project prefixed order reference, formatted IDR amount, configured merchant identity, and QR validity to the bank. It persists the QR content, bank reference, bill number, and expiry, then returns `data.reference`, `data.link`, `data.qr_string`, and the bank response in `data.result`.

With `version: "2"`, `data.link` points to the existing checkout page and includes a Redis payment token. Configure `PAYMENT_URL` before using this version. Client and merchant `createPayment` endpoints reuse an existing unpaid order and reject a second QR generation for the same order. The backoffice's repository test order endpoint also supports AGI and defaults to QRIS.

Use `GET /api/order/checkOrderStatus?reference=PROJECT-INV-AGI-001` with the merchant token, or the corresponding `/api/client/order/checkOrderStatus` with a checkout token, to query the bank. Queries use the saved bank reference and bill number, with `serviceCode: "17"`. Like existing gateways, querying returns the bank status; authenticated notifications apply status changes to the order.

## Payment notifications

Register these middleware URLs with AGI:

| URL | Behavior |
| --- | --- |
| `POST /api/bank/agi/access-token` | Verifies AGI's RSA signature and returns a repository bound B2B token. The body must contain `grantType: "client_credentials"`. |
| `POST /api/bank/agi/notify-qris` | Verifies the token, partner, timestamp, and HMAC signature, then processes the QRIS notification. |
| `POST /api/bank/agi/notify-va` | Returns HTTP 501 until the VA contract is available. |

QRIS notifications must include `originalReferenceNo`, `latestTransactionStatus`, and `additionalInfo.merchantId`, `additionalInfo.merchantUser`, and `additionalInfo.billNumber`. If `amount` is supplied, its value must exactly match the amount sent to the bank and its currency must be IDR. Amounts with more than two decimals and numeric strings using scientific notation are rejected. Required transaction headers include a numeric `X_EXTERNAL_ID` of up to 36 digits and a five digit `CHANNEL_ID`. Token and QRIS notification bodies must be valid JSON and no larger than 64 KiB; oversized bodies return HTTP 413.

The middleware locates the order using the bill number within the token's repository and verifies its stored merchant identity. It locks the order while applying the change, records history, and dispatches merchant callbacks and notifications after commit. Duplicate notifications are authenticated and acknowledged without sending duplicate jobs; a late failure cannot downgrade a successful payment.

Bank credentials and access tokens are redacted from structured application logs and Telescope request and response entries in all environments. Telescope excludes credential repository and project SQL queries, plus Redis commands containing bank or checkout token data. Duplicate callbacks load the project only when they need to dispatch a status change.

| Bank status | Middleware behavior |
| --- | --- |
| `00` | Mark `SUCCESS`. |
| `01`, `02`, `03` | Keep pending orders pending. |
| `05`, `06` | Mark `FAILED` unless already successful. |
| `04`, `07` | Acknowledge without changing payment status. Refund processing is not implemented. |

Successful notifications return the bank's required top level SNAP payload, `{"responseCode":"2001900","responseMessage":"Success"}`, through `ResponseHelper::payload()`. Validation and authentication errors also use SNAP response codes and the corresponding HTTP status.

## Verification

```sh
php vendor/bin/phpunit tests/Unit/BankAgiSignatureTest.php tests/Unit/BankAgiLogRedactionTest.php tests/Feature/BankAgiIntegrationTest.php tests/Feature/PaymentRepositoryTest.php
bun run lint
```

The integration tests use SQLite in memory, fake bank responses, fake Redis storage, and fake queued jobs. They cover outbound signatures, token issuance, notification authentication, duplicates, repository isolation, rollback, checkout, status queries, seeders, and the new migration. Live sandbox verification still requires AGI credentials and registered callback URLs.
