# 💻 Frontend Architecture (React 19 SPA - `resources/` Folder)

Frontend Backoffice dibangun menggunakan **React 19**, **TypeScript**, dan **Tailwind CSS** yang di-mount ke dalam view Laravel Blade. Arsitektur menerapkan pola **Feature-Driven Clean Architecture**.

---

## 🏛️ Pola & Prinsip Utama

1. **Feature-Driven**: Kode dikelompokkan berdasarkan modul fungsional fitur bisnis (`features/`), bukan berdasarkan pengelompokan teknis generik.
2. **Clean Architecture 4-Tier**: Setiap modul fitur dibagi menjadi 4 layer terisolasi (`domain`, `infrastructure`, `application`, `presentation`).
3. **Pemisahan UI vs Logic**: Setiap halaman/subfitur memisahkan komponen visual murni (`*_ui.tsx`) dari state & lifecycle hook (`*_logic.tsx`).
4. **Dependency Inversion**: Layer UI dan Application bergantung pada kontrak interface di Domain. Layer Infrastructure bertindak sebagai implementor kontrak tersebut.
5. **Zero External Router**: Navigasi SPA ditangani oleh router mikro native berbasis browser History API (`pushState` dan `popstate`).

---

## 📐 Struktur Folder Utama `resources/`

```text
resources/
├── views/                  # Mounting Blade template tunggal (#app)
├── css/                    # Tailwind CSS directives & global style
└── js/
    ├── app.tsx             # Root router, auth guard, dan layout shell
    ├── bootstrap.ts        # Inisialisasi HTTP client & environment
    └── src/
        ├── features/       # Modul fungsional independen (Clean Architecture)
        │   ├── auth/       # Fitur autentikasi & session login
        │   ├── dashboard/  # Fitur operasional, CRUD resources & analitik
        │   └── logs/       # Fitur log inspector & filter
        └── shared/         # Komponen & modul lintas fitur
            ├── component/  # Shell layout & reusable UI kit
            ├── network/    # Axios singleton & HTTP interceptor
            ├── hooks/      # Shared React hooks (theme, dll.)
            └── utils/      # Formatting & storage helpers
```

---

## 📂 Struktur Lengkap setelah `features/[feature_name]/`

Setiap folder di dalam `features/` (seperti `features/dashboard/`, `features/auth/`, atau modul besar lainnya) memiliki hirarki standar berikut:

```text
features/[feature_name]/
│
├── domain/                      # 1. ATURAN BISNIS & KONTRAK MURNI
│   ├── model/                   # Definisi tipe data & DTO murni TypeScript
│   │   ├── request/             # Tipe payload request ke backend
│   │   ├── response/            # Tipe data response dari backend
│   │   └── enum/                # Tipe enum / status domain
│   ├── repository/              # Interface kontrak data access (abstract)
│   └── constant/                # Metadata domain & skema resource
│
├── infrastructure/              # 2. AKSES DATA & KOMUNIKASI LUAR
│   ├── data_source/             # Pengambilan data mentah
│   │   └── remote/              # Direct call HTTP (Axios) per resource
│   └── persistence/             # Implementasi konkrit dari domain repository
│
├── application/                 # 3. ORKESTRASI LOGIKA APLIKASI (USE CASES)
│   └── [feature]_service.ts     # Service pengelola workflow antar-repository & state
│
└── presentation/                # 4. VIEW LAYER (UI & LOGIC HOOKS)
    └── [subfeature_or_screen]/  # Folder per layar atau subfitur
        ├── [subfeature]_ui.tsx  # Tampilan visual murni (JSX, layout, Tailwind)
        └── [subfeature]_logic.tsx # Custom React hook (useState, useEffect, event handler)
```

---

## 🧩 Penjelasan Fungsi Tiap Layer Fitur

### 1. Layer `domain/`
Layer terdalam yang berdiri sendiri tanpa dependensi ke React, Axios, atau UI framework:
- **`model/request/`**: Interface TypeScript yang mendefinisikan bentuk data yang dikirim dari klien ke endpoint API.
- **`model/response/`**: Interface TypeScript yang mendefinisikan entitas data yang diterima dari API setelah di-unwrap.
- **`model/enum/`**: Konstanta tipe terbatas (contoh: status order, role user).
- **`repository/`**: Kontrak interface murni yang menentukan operasi apa saja yang harus tersedia (misal: `getOrderList()`, `resendCallback()`), tanpa peduli bagaimana data tersebut diambil.
- **`constant/`**: Metadata deklaratif, seperti konfigurasi kolom tabel, form field, dan label aksi untuk CRUD.

### 2. Layer `infrastructure/`
Menangani komunikasi teknis dengan dunia luar:
- **`data_source/remote/`**: Bertanggung jawab langsung mengeksekusi request HTTP menggunakan HTTP client (`apiClient`). Tidak memuat logika bisnis.
- **`persistence/`**: Kelas implementasi konkrit yang memenuhi kontrak interface di `domain/repository/`. Layer ini memanggil `remote_data_source`, memetakan response mentah ke domain model, dan menangani error HTTP.

### 3. Layer `application/`
Layer penghubung antara domain logic dan presentation:
- Berisi file `*_service.ts` yang mengorkestrasi satu atau lebih repository.
- Mengatur alur data use case, sinkronisasi token session di storage, kalkulasi data sebelum masuk ke komponen tampilan, dan trigger aksi sistem.
- Menjadi API tunggal yang dipanggil oleh custom hook di layer presentation.

### 4. Layer `presentation/`
Layer visual yang berinteraksi langsung dengan pengguna. Diterapkan pemisahan mutlak antara **UI** dan **Logic**:
- **Folder per Subfitur/Screen**:
  Di dalam `presentation/`, dibuat folder untuk masing-masing halaman atau subfitur (contoh: `order/`, `project/`, `payment_gateway/`, `overview/`).
- **`[name]_ui.tsx`**:
  Komponen visual deklaratif. Hanya fokus pada tata letak JSX, struktur DOM, Tailwind styling, dan pemetaan komponen UI atomik. UI tidak menyimpan state manual yang rumit melainkan mengonsumsi props dan handler dari hook logic.
- **`[name]_logic.tsx`**:
  Custom React hook (`use[Name]Logic`). Menampung seluruh `useState`, `useEffect`, validasi input, status loading, error state, serta handler fungsi yang memanggil `application service`.

---

## 🔄 Alur Data Antar-Layer (End-to-End Flow)

```text
Pengguna berinteraksi dengan UI
             │
             ▼
[presentation/*_ui.tsx] memanggil handler dari logic hook
             │
             ▼
[presentation/*_logic.tsx] memicu pemanggilan method di application service
             │
             ▼
[application/*_service.ts] menjalankan use case & memanggil repository
             │
             ▼
[infrastructure/persistence/*_repository_impl.ts] mengimplementasikan domain contract
             │
             ▼
[infrastructure/data_source/remote/*_remote_data_source.ts] memanggil Axios client
             │
             ▼
[shared/network/api_client.ts] mengirim request HTTP dengan token auth ke backend
             │
             ▼
Backend mengembalikan response -> Data di-mapping ke [domain/model/response/]
             │
             ▼
State di [*_logic.tsx] diperbarui -> [*_ui.tsx] melakukan re-render tampilan
```

---

## 🛠️ Layer Bersama (`shared/`)

Modul yang dapat digunakan oleh seluruh modul fitur:
- **`network/`**: Singleton Axios client dengan konfigurasi Bearer token otomatis dan interceptor penanganan error status 401.
- **`component/layout/`**: Shell utama backoffice (sidebar, navbar, breadcrumbs, user info).
- **`component/ui/`**: Komponen UI atomik reusable (`data_table`, `modal_dialog`, `stat_card`, `badge`, `skeleton`, `field`).
- **`hooks/`**: Global hooks seperti manajemen dark/light theme (`use_theme`).
- **`utils/`**: Helper pemformat mata uang (IDR), waktu/tanggal, dan pembaca token di storage.
