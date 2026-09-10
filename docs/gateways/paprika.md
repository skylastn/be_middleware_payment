# 🌶️ Paprika Payment Gateway Integration

Paprika is an Indonesian payment gateway following the National Open API Standard (SNAP 1.0 / ASPI) supporting Dynamic QRIS and Virtual Accounts (VA).

---

## ⚡ Integration Modes (Codebase Implementation)

| Mode | Status | Technical Implementation in Codebase |
|:---|:---:|:---|
| **📱 Dynamic QRIS** | ✅ `Supported` | Invokes SNAP 1.0 QR MPM endpoint (`/api/snap/v1.0/qr/qr-mpm-generate`) with RSA SHA256 B2B OAuth token and HMAC-SHA512 transaction signature. |
| **🏦 Virtual Account (VA)** | ✅ `Supported` | Invokes SNAP 1.0 VA endpoint (`/api/snap/v1.0/transfer-va/create-va`) with bank routing codes (`013` Permata, `016` Maybank, `037` Artha Graha). |
| **🪟 Interactive Checkout UI** | ✅ `Supported` (via `version=2`) | Order creation with `version: "2"` generates a secure Redis tokenized URL (`PAYMENT_URL/detailpayment?token=...&reference=...`) for front-office checkout. |

---

## 1. Credentials Configuration

In the Backoffice under **Payment Repositories**, configure a repository with the gateway key `paprika` using the following JSON schema:

```json
{
  "api_key": "your_client_key_here",
  "api_secret": "your_client_secret_here",
  "base_url": "https://sandbox.paprika.id",
  "private_key": "-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA..."
}
```

### Parameter Description:
| Key | Type | Required | Description |
|:---|:---|:---:|:---|
| `api_key` | string | Yes | Partner ID / Client Key (`X-PARTNER-ID`, `X-CLIENT-KEY`). |
| `api_secret` | string | Yes | Client Secret used for HMAC-SHA512 symmetric request & webhook verification. |
| `base_url` | string | Yes | Upstream Paprika API base URL. |
| `private_key` | string | Yes | RSA private key in PEM format used for B2B token asymmetric signature. |

---

## 2. Supported Payment Methods

Payment methods configured in `payment_methods` table routed to Paprika:

| Method Key | Category | Name | Upstream Bank Code |
|:---|:---:|:---|:---:|
| `qris` / `PAPRIKA_QRIS` | `qris` | Paprika QRIS | - |
| `VA_PERMATA` | `va` | Paprika Permata VA | `013` |
| `VA_MAYBANK` | `va` | Paprika Maybank VA | `016` |
| `VA_AGRAHA` | `va` | Paprika Artha Graha VA | `037` |

---

## 3. Order Creation Flow

### A. Dynamic QRIS
**`POST /api/order/create`**

```json
{
  "paymentRepositoryId": "019e8383-87f4-71c4-89be-72e0bd30184c",
  "merchantOrderId": "INV-PAPRIKA-001",
  "paymentAmount": 50000,
  "paymentMethod": "qris",
  "mode": "sandbox"
}
```

**Response:**
```json
{
  "status": true,
  "code": 200,
  "message": "Success Create Order Paprika",
  "data": {
    "reference": "PROJ-INV-PAPRIKA-001",
    "link": "00020101021226680016ID.CO.PAPRIKA.WWW...",
    "qr_string": "00020101021226680016ID.CO.PAPRIKA.WWW...",
    "qr_url": "https://sandbox.paprika.id/qr/..."
  }
}
```

### B. Virtual Account
**`POST /api/order/create`**

```json
{
  "paymentRepositoryId": "019e8383-87f4-71c4-89be-72e0bd30184c",
  "merchantOrderId": "INV-PAPRIKA-002",
  "paymentAmount": 150000,
  "paymentMethod": "VA_PERMATA",
  "mode": "sandbox",
  "firstName": "John",
  "lastName": "Doe",
  "email": "customer@example.com",
  "phone": "08123456789"
}
```

**Response:**
```json
{
  "status": true,
  "code": 200,
  "message": "Success Create Order Paprika VA",
  "data": {
    "reference": "PROJ-INV-PAPRIKA-002",
    "link": "8199999999999999",
    "result": {
      "responseCode": "200",
      "responseMessage": "Success",
      "virtualAccountData": {
        "virtualAccountNo": "8199999999999999"
      }
    }
  }
}
```

---

## 4. Webhook / Callback Handling

Paprika delivers SNAP 1.0.2 JSON notifications using B2B Access Token flow and HMAC-SHA512 symmetric transaction signature.

### Endpoints
- **Access Token B2B**: `POST /api/paprika/snap/v1.0/access-token/b2b`
- **Webhook Endpoint (VA / Dedicated)**: `POST /api/paprika/webhook` (or alias `POST /api/webhook/paprika`)
- **Callback Endpoint (QRIS / Dedicated)**: `POST /api/paprika/callback` (or alias `POST /api/callback/paprika`)

### Callback Headers:
- `Authorization`: `Bearer {accessToken}`
- `X-PARTNER-ID`: Client Key / Partner ID
- `X-TIMESTAMP`: ISO 8601 Timestamp
- `X-SIGNATURE`: HMAC-SHA512 signature (`{HTTP_METHOD}:{URL_PATH}:{accessToken}:{SHA256_hex(body)}:{X-TIMESTAMP}`)
- `X-EXTERNAL-ID`: Idempotency key (optional / numeric)

### Callback Payload Example:
```json
{
  "originalPartnerReferenceNo": "INV-PAPRIKA-001",
  "originalReferenceNo": "PROJ-INV-PAPRIKA-001",
  "latestTransactionStatus": "00",
  "transactionStatusDesc": "Success",
  "amount": {
    "value": "50000.00",
    "currency": "IDR"
  },
  "additionalInfo": {
    "payerIssuer": "PAPRIKA_QRIS"
  }
}
```

### Status Mapping:
| Paprika `latestTransactionStatus` | Middleware `OrderStatus` |
|:---:|:---:|
| `00` | `SUCCESS` |
| `06` | `FAILED` |
| Other | Pending / Ignored |
