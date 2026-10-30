# 🚀 Cara Menjalankan — Sistem Informasi Tahfidz KB/BA/TPA "Restu 2"

## Prasyarat

Sebelum memulai, pastikan perangkat Anda sudah terinstal:

| Kebutuhan | Versi Minimum | Catatan |
|---|---|---|
| **PHP** | 8.0+ | Dengan ekstensi `pdo_mysql`, `zip`, `mbstring` aktif |
| **MySQL / MariaDB** | 5.7+ / 10.3+ | — |
| **Web Server** | Apache / Nginx | Laragon, XAMPP, WAMP, atau server Linux |
| **Composer** | 2.x | Untuk instalasi dependensi PHP |

---

## ⚡ Instalasi Cepat (Langkah demi Langkah)

### Langkah 1 — Ekstrak File

Ekstrak file `deploy_tahfidz.zip` ke dalam folder web server Anda:

- **Laragon** → `C:\laragon\www\deploy_tahfidz\`
- **XAMPP** → `C:\xampp\htdocs\deploy_tahfidz\`
- **Linux / VPS** → `/var/www/html/deploy_tahfidz/`

### Langkah 2 — Install Dependensi PHP (Composer)

Buka terminal / command prompt, masuk ke folder proyek, lalu jalankan:

```bash
composer install
```

> Composer akan mengunduh semua library yang dibutuhkan ke folder `vendor/`.
> Pastikan koneksi internet tersedia pada langkah ini.

### Langkah 3 — Konfigurasi Database

Buka file `config/database.php` dan sesuaikan konfigurasi berikut:

```php
private $host     = 'localhost';      // Host MySQL Anda
private $dbname   = 'tahfidz_restu2'; // Nama database
private $username = 'root';           // Username MySQL
private $password = '';               // Password MySQL
```

### Langkah 4 — Setup Database (Otomatis)

Jalankan script setup untuk membuat database dan mengimpor schema + data awal:

**Melalui Browser:**
```
http://localhost/deploy_tahfidz/setup.php
```

**Melalui Terminal (CLI):**
```bash
php setup.php
```

Script ini akan otomatis:
- Membuat database `tahfidz_restu2`
- Membuat semua tabel dan view
- Mengimpor data awal (akun admin, guru, siswa, dan kelas)

### Langkah 5 — Buka Aplikasi

Akses aplikasi melalui browser:

```
http://localhost/deploy_tahfidz/
```

---

## Akun Default Setelah Setup

> **Penting:** Segera ganti semua password default setelah berhasil login pertama kali!

### Admin / Koordinator

| Field | Nilai |
|---|---|
| Nomor Identitas | `000001` |
| Password | `admin123` |
| URL Login | `http://localhost/deploy_tahfidz/` |

### Guru (Contoh — 1 akun percobaan)

| Field | Nilai |
|---|---|
| Nomor Identitas | `8460-7566-5730-0022` |
| Password | `12345678` |
| URL Login | `http://localhost/deploy_tahfidz/` |

### Wali Murid / Orang Tua (Contoh — 1 akun percobaan)

| Field | Nilai |
|---|---|
| Nomor Identitas (NIS) | `1250001` |
| Password | `1250001` *(sama dengan NIS)* |
| URL Login | `http://localhost/deploy_tahfidz/` |

---

## Struktur Folder Proyek

```
deploy_tahfidz/
├── api/                  → Endpoint API (JSON response)
│   ├── admin/            → API khusus admin
│   ├── auth/             → API autentikasi (login, logout)
│   ├── guru/             → API khusus guru
│   └── wali/             → API khusus wali murid
├── assets/               → File CSS, JS, font, gambar
├── config/               → Konfigurasi (database, auth, dll)
│   ├── database.php      → Konfigurasi koneksi DB (edit di sini)
│   ├── auth.php          → Pengecekan sesi login
│   └── cors.php          → Pengaturan CORS
├── controllers/          → Logic controller PHP
├── database/
│   └── schema.sql        → Schema + data awal database
├── docs/                 → Panduan penggunaan (folder ini)
├── models/               → Model database
├── portal/               → Frontend Vue.js (sudah di-build)
├── uploads/              → Folder upload file audio
│   └── audio/            → Rekaman setoran siswa
├── vendor/               → Library PHP (diinstall via Composer)
├── views/                → Halaman HTML per role
│   ├── admin/            → Tampilan Admin
│   ├── guru/             → Tampilan Guru
│   └── wali_murid/       → Tampilan Wali Murid
├── composer.json         → Definisi dependensi PHP
├── index.php             → Entry point utama
└── setup.php             → Script instalasi database
```

---

## Konfigurasi Tambahan (Opsional)

### Folder Upload Audio

Pastikan folder `uploads/` dapat ditulis oleh web server:

**Linux / VPS:**
```bash
chmod -R 775 uploads/
chown -R www-data:www-data uploads/
```

**Windows (Laragon/XAMPP):** biasanya sudah otomatis, tidak perlu konfigurasi tambahan.

---

## Troubleshooting

| Masalah | Solusi |
|---|---|
| Halaman blank / error 500 | Cek log PHP, pastikan ekstensi `pdo_mysql` aktif |
| "Koneksi database gagal" | Pastikan MySQL berjalan dan kredensial di `config/database.php` benar |
| Composer tidak ditemukan | Install Composer dari https://getcomposer.org |
| Upload audio gagal | Cek permission folder `uploads/audio/` |
| Login selalu gagal | Jalankan ulang `setup.php` untuk memastikan data awal terimport |

---

*Sistem Informasi Tahfidz KB/BA/TPA "Restu 2"*
