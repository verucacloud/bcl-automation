# BCL Automation - Backend & Device Controller

Sistem automasi dan device controller MVP untuk inventaris perangkat Android, QA aplikasi, dan sinkronisasi tugas via ADB dan REST API.

## Alur Kerja
`HP -> ADB -> controller-agent (Node.js) -> REST API dashboard (PHP) -> MySQL`

* **Dashboard**: Berfungsi menyimpan task, scheduling, dan monitoring status.
* **Controller Agent**: Berjalan di komputer/laptop lokal yang terhubung fisik ke perangkat Android via ADB.

---

## Struktur Direktori
- `accounts/` : Manajemen akun dan analitik.
- `api/controller/` : Endpoint REST API untuk komunikasi dengan `controller-agent`.
- `assets/` : Aset tampilan dashboard.
- `auth/` : Autentikasi sesi dan OAuth.
- `automation/` : Task scheduler, queue ADB, dan log automasi.
- `connectors/` : Integrasi pihak ketiga (YouTube Data API v3, dll).
- `controller-agent/` : Agent Node.js lokal untuk eksekusi perintah ADB ke HP.
- `database/` : File schema SQL (`001_device_accounts_scheduler.sql`).
- `modules/` : Modul strategi dan analitik pertumbuhan akun.

---

## Panduan Instalasi

### 1. Setup Backend (PHP & MySQL)
1. Salin `config.local.example.php` menjadi `config.local.php`.
2. Sesuaikan kredensial database dan API key di `config.local.php`.
3. Import file `database/001_device_accounts_scheduler.sql` melalui phpMyAdmin / MySQL CLI.
4. (Opsional) Jika menggunakan Google/YouTube API, aktifkan YouTube Data API v3 di Google Cloud Console dan masukkan Client ID & Secret ke `config.local.php`.

### 2. Setup Controller Agent (Komputer Lokal Windows)
1. Pastikan **Node.js** dan **Android Platform Tools (ADB)** sudah terpasang.
2. Buka folder `controller-agent/`.
3. Jalankan instalasi dependensi:
   ```bash
   npm install
   ```
4. Salin `.env.example` menjadi `.env` lalu isi URL backend dan `CONTROLLER_API_KEY`.
5. Aktifkan **USB debugging** pada HP dan pastikan terdeteksi dengan perintah:
   ```bash
   adb devices
   ```
6. Jalankan agent:
   ```bash
   npm start
   ```
