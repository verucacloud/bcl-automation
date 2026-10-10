# Device Controller MVP

## Batas penggunaan
Controller ini dibuat untuk inventaris perangkat, QA aplikasi, membuka/menutup aplikasi milik sendiri, mengambil screenshot, dan monitoring. Tidak ada fungsi auto-view, auto-like, auto-subscribe, pergantian akun, proxy, atau penyamaran aktivitas.

## Instalasi dashboard
1. Ganti password database yang pernah ada di `config.php` lama.
2. Salin `config.local.example.php` menjadi `config.local.php` dan isi kredensial baru.
3. Import `database/001_device_accounts_scheduler.sql` melalui phpMyAdmin.
4. Buat OAuth Client Web di Google Cloud, aktifkan YouTube Data API v3, lalu tambahkan redirect URI:
   `https://DOMAIN/connectors/youtube/callback.php`
5. Isi Google Client ID dan Secret pada `config.local.php`.
6. Buka `/accounts/index.php` dan pilih Hubungkan YouTube.

## Instalasi controller lokal Windows
1. Instal Node.js dan Android Platform Tools.
2. Masuk folder `controller-agent`.
3. Jalankan `npm install`.
4. Salin `.env.example` menjadi `.env` dan isi URL serta API key.
5. Aktifkan USB debugging pada HP dan pastikan `adb devices` mendeteksi perangkat.
6. Jalankan `npm start`.

## Alur
HP -> ADB -> controller-agent -> REST API dashboard -> MySQL.
Dashboard hanya menyimpan task. Eksekusi ADB selalu terjadi pada komputer lokal.
