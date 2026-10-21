# MyCron Project

**MyCron** adalah sistem manajemen cronjob berbasis Laravel 12 dan Node.js, dirancang untuk mengelola, menjalankan, dan memonitor eksekusi URL terjadwal secara dinamis. Cocok untuk kebutuhan ISP, automation, dan integrasi sistem.

---

## 🚀 Teknologi yang Digunakan

- **Laravel 12** (backend REST API & panel manajemen)
- **MySQL** (database)
- **Node.js** (scheduler & worker cron)
- **Bootstrap 5** + jQuery (tampilan UI CRUD)

---

## 📂 Struktur Folder

```
my-cron-app/
├── laravel/             # Aplikasi Laravel 12
│   ├── routes/api.php   # API endpoint
│   ├── app/Models       # Model Cronjob, ApiKey
│   ├── app/Http/...     # Controller + Middleware
│   └── resources/views  # Blade view (dengan AJAX + modal)
│
└── node-worker/         # Worker Node.js
    ├── index.js         # Entry point worker
    ├── cron.js          # Dynamic cron loader
    ├── db.js            # Koneksi ke MySQL
    └── jobs/            # (Opsional) modular task handler
```

---

## 🔧 Setup Laravel

```bash
cd laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

### Endpoint API
- `GET  /api/cronjobs?domain=example.com` → ambil daftar cronjob
- `POST /api/cronjobs` → tambah cronjob
- `PUT  /api/cronjobs/{id}` → edit cronjob
- `DELETE /api/cronjobs/{id}` → hapus cronjob

> Gunakan header `X-API-KEY` untuk mengakses API dengan aman.

---

## 🔧 Setup Node Worker

```bash
cd node-worker
npm install
node index.js
```

- Worker akan membaca tabel `cronjobs` secara berkala (setiap 30 detik)
- Jadwal yang berubah akan otomatis **di-reschedule**
- Log hasil eksekusi akan disimpan di tabel `cron_logs`

---

## ✅ Fitur Utama

- Tambah/edit/hapus cronjob dari panel Laravel
- API Key Management untuk autentikasi
- Worker Node.js yang fleksibel dan otomatis re-load jadwal
- Cronjob berbasis URL (GET)
- View blade CRUD satu halaman (AJAX + modal)
- Penyimpanan log per eksekusi

---

## 🔄 Update System (dari panel)

Menu **Update System** (admin) menampilkan status worker, versi terpasang vs GitHub, dan tombol:

- **Update Sekarang** — `git fetch` + `git reset --hard origin/main`, `composer install` bila composer berubah,
  `php artisan migrate --force` + `optimize:clear` bila folder `laravel/` berubah, `npm install` bila package worker berubah,
  `chown -R www:www`, lalu restart worker otomatis bila `node-worker/` berubah.
- **Restart Worker** — worker keluar (exit 0) dan pm2 menyalakannya kembali.
- **Muat Ulang Cronjob** — baca ulang daftar cronjob tanpa restart.

Cara kerja: PHP (user `www`) hanya menulis permintaan ke tabel `system_tasks`; `node-worker/system.js`
(berjalan sebagai root di pm2) yang mengeksekusi dan menulis log + heartbeat ke tabel `settings`.
Cronjob baru/diubah/dihapus otomatis diterapkan worker dalam ±15 detik — tidak perlu restart.

Env opsional worker (`node-worker/.env`): `DEPLOY_BRANCH` (default `main`), `PHP_BIN`, `COMPOSER_BIN`,
`DEPLOY_WEB_OWNER` (default `www:www`), `CRON_DNS_SERVERS`. Env opsional Laravel: `DEPLOY_GITHUB_TOKEN`.

Aktivasi pertama kali (sekali, via SSH sebagai root): deploy kode ini, `php artisan migrate --force`, `pm2 restart node-worker`.

## 🔐 Keamanan API

Gunakan API Key yang disimpan di database (`api_keys` table):

```http
X-API-KEY: your-secret-key
```

Tambahkan middleware `VerifyApiKey` untuk semua route API:
```php
Route::middleware(VerifyApiKey::class)->group(function () {
    Route::apiResource('cronjobs', CronjobApiController::class);
});
```

---

## 🛠 Database

Tabel yang digunakan:
- `cronjobs` — daftar semua URL cronjob
- `cron_logs` — log hasil eksekusi oleh Node.js
- `api_keys` — daftar API key client

---

## 📄 Lisensi

Proyek ini dikembangkan oleh [SDP (Sebelas Dua Belas Project)](https://mywifi.web.id) dan [1112 Project](https://1112-project.com) untuk keperluan internal dan klien mitra. Silakan gunakan untuk kebutuhan pengembangan dan integrasi sistem Anda.

---

### Dukungan

Jika Anda merasa proyek ini bermanfaat dan ingin mendukung saya, traktir kopi saya melalui:

* [Trakteer Kopi](https://trakteer.id/ginginabdulgoni/tip)
* [Paypal](https://paypal.me/ginginabdulgoni)

---
Happy coding! 🚀