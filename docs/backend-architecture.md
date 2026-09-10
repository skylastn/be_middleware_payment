# ⚙️ Backend Architecture (Laravel 13 + PHP 8.4)

Arsitektur backend dirancang dengan pola **Decoupled Layered Architecture** yang dioptimalkan untuk performa tinggi di atas **Laravel Octane (FrankenPHP)**.

---

## 🏛️ Pola & Prinsip Utama

1. **Explicit Dependency Instantiation (Manual DI)**: Dependensi diinstansiasi manual di `__construct()` service dan controller, menghindari overhead resolusi dynamic container di runtime Octane.
2. **Gateway Strategy Router**: Semua transaksi pembayaran dan pencairan dinormalisasi melalui router sentral (`OrderService` & `PayoutService`) yang meneruskan ke adapter gateway terkait.
3. **Strict Transaction Boundary**: Setiap mutasi data di controller dibungkus dalam `DB::beginTransaction()`, `DB::commit()`, dan `DB::rollback()`.
4. **Data Access Isolation**: Akses database murni diisolasi di Repository. Model/Entity tidak memuat query logic langsung.
5. **Strict Entity Encapsulation**: Entity menggunakan method getter dan setter eksplisit (bukan dynamic magic property Eloquent).

---

## 📐 Struktur Layer Backend

```text
HTTP Request / Webhook / CLI
            │
            ▼
┌───────────────────────────────────────┐
│ 1. Routes & Middleware                │
│    - Routing API & Admin              │
│    - Auth (Sanctum, Project, Client)  │
└───────────────────┬───────────────────┘
                    │
                    ▼
┌───────────────────────────────────────┐
│ 2. Controller Layer                   │
│    - Input validation                 │
│    - DB transaction management        │
│    - Response formatting via Helper   │
└───────────────────┬───────────────────┘
                    │
                    ▼
┌───────────────────────────────────────┐
│ 3. Service Layer (Business Logic)     │
│    - Strategy Router (Order / Payout) │
│    - Gateway Adapters (Duitku, etc.)  │
│    - Fee & Surcharge Calculator       │
└───────────────────┬───────────────────┘
                    │
                    ▼
┌───────────────────────────────────────┐
│ 4. Repository Layer (Data Access)     │
│    - Abstract CRUD (BaseRepository)   │
│    - Domain-specific queries & filter │
└───────────────────┬───────────────────┘
                    │
                    ▼
┌───────────────────────────────────────┐
│ 5. Entity / Model Layer               │
│    - Domain entities (app/Model/)     │
│    - Explicit getters & setters       │
│    - Backed Enums (app/Enums/)        │
└───────────────────┬───────────────────┘
                    │
        ┌───────────┴───────────┐
        ▼                       ▼
┌──────────────────┐  ┌──────────────────┐
│ 6. Queue & Jobs  │  │ 7. Helpers & API │
│    Async worker  │  │    Resources     │
└──────────────────┘  └──────────────────┘
```

---

## 🧩 Penjelasan Tiap Layer

### 1. Routes & Middleware (`routes/`, `app/Http/Middleware/`)
Mengatur boundary keamanan dan filter request sebelum mencapai controller:
- **Admin Authentication**: `auth:sanctum` dipadukan dengan `EnsureAdminRole` untuk melindungi backoffice.
- **Merchant API Authentication**: `AuthenticateProjectToken` memeriksa header `Token` terhadap `projects.value`.
- **Client Payment Authentication**: `AuthenticateClientPaymentToken` memeriksa `PAYMENT_APP_KEY` atau single-use session token di Redis.

### 2. Controller Layer (`app/Http/Controllers/Api/`)
Thin Controller yang hanya bertugas mengorkestrasi:
- Melakukan validasi payload request.
- Mengelola lifecycle transaksi database (`beginTransaction`, `commit`, `rollback`).
- Menyerahkan seluruh kalkulasi dan logika bisnis ke Service.
- Mengembalikan response JSON terstandarisasi melalui `ResponseHelper`.

### 3. Service Layer (`app/Services/`)
Pusat logika bisnis aplikasi:
- **Strategy Router (`OrderService`, `PayoutService`)**: Menerima request umum, memilih gateway adapter yang tepat berdasarkan konfigurasi project atau parameter request.
- **Gateway Adapters (`Payment/`)**: Mengimplementasikan kontrak alur transaksi gateway (`order()`, `callback()`, `checkStatus()`).
- **System Services (`System/`)**: Menangani autentikasi admin, kalkulasi ringkasan dashboard, dan utilitas caching Redis.
- **Socket Services (`Socket/`)**: Mengirim event real-time melalui WebSocket.

### 4. Repository Layer (`app/Repository/`)
Abstraksi komunikasi ke database:
- Mewarisi `BaseRepository` untuk standarisasi query CRUD dan pagination.
- Mengisolasi query builder dan pemfilteran domain agar terpisah dari service.

### 5. Entity & Model Layer (`app/Model/Entity/`, `app/Enums/`)
Representasi objek domain data:
- Ditempatkan di folder `app/Model/Entity/`.
- Memakai method getter/setter eksplisit untuk menjaga integritas tipe data.
- Memanfaatkan **Backed Enums** (`app/Enums/`) untuk status transaksi (`OrderStatus`), mode integrasi (`PaymentModeType`), dan identitas gateway (`ProjectSlug`).

### 6. Queue Jobs (`app/Jobs/`)
Pemrosesan asinkronus (background workers):
- Mengirim callback webhook ke URL merchant dengan mekanisme retry dan exponential backoff.
- Mengirim notifikasi eksternal (Telegram / Discord).
- Wajib di-dispatch dengan `->afterCommit()` di dalam database transaction.

### 7. Helper & API Response (`app/Http/Helper/`, `app/Model/Response/`)
- `ResponseHelper`: Memastikan struktur response API konsisten (`{ status, code, message, data }`).
- Auto-resolving Resource: Data entity otomatis dipetakan ke API Resource di `app/Model/Response/`.
- Generator unik untuk order reference dan payout reference.
