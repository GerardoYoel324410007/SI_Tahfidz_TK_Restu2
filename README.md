<div align="center">

# 📖 Sistem Informasi Tahfidz
### KB/BA/TPA "Restu 2"

Platform digital untuk manajemen setoran hafalan Al-Quran secara online —
menghubungkan Koordinator, Guru Wali Kelas, dan Orang Tua Murid dalam satu ekosistem.

[![PHP](https://img.shields.io/badge/PHP-8.1-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

</div>

---

## 🎯 Tentang Proyek

Sistem Informasi Tahfidz dibangun untuk memodenisasi proses setoran hafalan Al-Quran di KB/BA/TPA "Restu 2". Sebelumnya, proses ini dilakukan secara langsung tatap muka — guru mendengarkan hafalan satu per satu di kelas.

Dengan sistem ini:

- 🎙️ **Orang tua** merekam suara anak di rumah lalu mengirimkannya melalui aplikasi
- 📋 **Guru** menerima notifikasi, memutar audio, dan memberikan nilai beserta catatan koreksi
- 📊 **Koordinator** memantau seluruh aktivitas kelas secara real-time

---

## ✨ Fitur Utama

### 👨‍💼 Admin / Koordinator
| Fitur | Keterangan |
|---|---|
| Dashboard Statistik | Ringkasan seluruh kelas: setoran hari ini, keterlambatan, persentase kelulusan |
| Manajemen Pengguna | CRUD guru & siswa, import massal via Excel (.xlsx) |
| Manajemen Kelas | Kelola kelas dan penetapan wali kelas |
| Monitor Kelas | Pantau status setoran per kelas secara real-time |
| Komando / Pengumuman | Kirim pengumuman ke satu kelas atau semua kelas |
| Laporan & Export | Rekap harian/bulanan, export ke Excel |
| Log Login | Audit trail semua aktivitas login pengguna |

### 👩‍🏫 Guru / Wali Kelas
| Fitur | Keterangan |
|---|---|
| Target Hafalan | Buat tugas hafalan harian: surah, ayat, deadline, + upload audio panduan |
| Koreksi Setoran | Putar audio siswa, nilai 3 kriteria (Kelancaran, Tajwid, Makhraj), beri catatan |
| Keputusan | Terima atau kembalikan (Perbaiki) hafalan dengan satu klik |
| Perpanjangan Waktu | Buka kembali akses upload untuk siswa yang terlambat |
| Progres Bulanan | Laporan performa tiap siswa dalam satu bulan |
| Presensi | Catat kehadiran siswa harian |

### 👨‍👩‍👧 Orang Tua / Wali Murid
| Fitur | Keterangan |
|---|---|
| Tugas Hari Ini | Lihat surah, ayat, deadline, dan putar audio panduan guru |
| Kirim Setoran | Upload rekaman hafalan anak (MP3, WAV, M4A, OGG, WebM — maks 10MB) |
| Notifikasi | Terima pemberitahuan real-time: tugas baru, hasil koreksi, pengumuman |
| Detail Koreksi | Lihat nilai per kriteria dan catatan lengkap dari guru |
| Balas Catatan | Kirim balasan pesan ke guru langsung dari aplikasi |
| Raport Bulanan | Pantau perkembangan hafalan anak per bulan |

---

## 🛠️ Teknologi

| Layer | Teknologi |
|---|---|
| **Backend** | PHP 8.1 (Native MVC pattern) |
| **Database** | MySQL 8.0 / MariaDB 10.3+ |
| **Frontend** | HTML5, Vanilla CSS, JavaScript (Fetch API) |
| **UI Library** | Font Awesome 6, Google Fonts |
| **Portal** | Vue.js (pre-built) |
| **Dependencies** | Composer — PHPSpreadsheet (Excel), HTMLPurifier |
| **Audio** | Browser MediaRecorder API / file upload (WebM, MP3, WAV, M4A) |

---

## 🗂️ Struktur Proyek

```
deploy_tahfidz/
├── api/                    → REST API endpoints (JSON)
│   ├── admin/              → Endpoint admin
│   ├── auth/               → Login, logout, session
│   ├── guru/               → Endpoint guru
│   └── wali/               → Endpoint wali murid
├── assets/                 → CSS, JS, webfonts, gambar
├── config/
│   ├── database.php        → ⚙️ Konfigurasi koneksi DB
│   ├── auth.php            → Middleware autentikasi sesi
│   └── cors.php            → Pengaturan CORS
├── controllers/            → Business logic (AdminController, GuruController, WaliController)
├── database/
│   └── schema.sql          → Schema lengkap + data awal
├── docs/                   → 📖 Panduan penggunaan per role
│   ├── CARA_MENJALANKAN.md
│   ├── PANDUAN_ADMIN.md
│   ├── PANDUAN_GURU.md
│   └── PANDUAN_WALI_MURID.md
├── models/                 → Database models (SiswaModel, SetoranModel, dll)
├── portal/                 → Vue.js frontend (pre-built)
├── uploads/audio/          → Storage rekaman hafalan
│   ├── panduan/            → Audio panduan guru
│   └── setoran/            → Rekaman hafalan siswa
├── views/
│   ├── admin/              → Halaman HTML untuk admin
│   ├── guru/               → Halaman HTML untuk guru
│   ├── wali_murid/         → Halaman HTML untuk orang tua
│   └── shared/             → Halaman bersama (login)
├── composer.json
├── index.php               → Entry point + routing berdasarkan role
└── setup.php               → Script instalasi database otomatis
```

---

## 🚀 Instalasi

### Prasyarat

- PHP 8.0+ (ekstensi: `pdo_mysql`, `zip`, `mbstring`)
- MySQL 5.7+ / MariaDB 10.3+
- Web server: Apache / Nginx (Laragon, XAMPP, WAMP)
- [Composer](https://getcomposer.org)

### Langkah Instalasi

**1. Clone repositori**
```bash
git clone https://github.com/username/sistem-tahfidz.git
cd sistem-tahfidz
```

**2. Install dependensi PHP**
```bash
composer install
```

**3. Konfigurasi database**

Edit file `config/database.php`:
```php
private $host     = 'localhost';
private $dbname   = 'tahfidz_restu2';
private $username = 'root';
private $password = '';
```

**4. Setup database (otomatis)**

Via browser:
```
http://localhost/sistem-tahfidz/setup.php
```

Atau via terminal:
```bash
php setup.php
```

Script ini akan membuat database, seluruh tabel, dan mengimpor data awal secara otomatis.

**5. Buka aplikasi**
```
http://localhost/sistem-tahfidz/
```

---

## 🔐 Akun Default

> ⚠️ Segera ganti semua password default setelah login pertama kali!

| Role | Nomor Identitas | Password |
|---|---|---|
| **Admin / Koordinator** | `000001` | `admin123` |
| **Guru** (contoh) | `8460-7566-5730-0022` | `12345678` |
| **Wali Murid** (contoh) | `1250001` | `1250001` *(= NIS)* |

---

## 🔄 Alur Kerja Sistem

```
┌─────────────────────────────────────────────────────┐
│                                                     │
│  [Guru] Buat target hafalan + upload audio panduan  │
│                         │                           │
│                         ▼                           │
│  [Orang Tua] Lihat tugas, dengar panduan,           │
│              rekam & kirim audio hafalan anak        │
│                         │                           │
│                         ▼                           │
│  [Guru] Putar audio → Nilai (Kelancaran/Tajwid/     │
│         Makhraj) → Beri catatan → Keputusan         │
│                         │                           │
│              ┌──────────┴──────────┐                │
│              ▼                     ▼                │
│        ✅ DITERIMA          🔄 PERBAIKI             │
│       (Poin bertambah)    (Upload ulang)            │
│                                                     │
│  [Admin] Monitor semua kelas + kirim pengumuman     │
└─────────────────────────────────────────────────────┘
```

---

## 📊 Skema Database

Database `tahfidz_restu2` terdiri dari **10 tabel utama**:

| Tabel | Fungsi |
|---|---|
| `users` | Akun login semua pengguna (admin, guru, wali_murid) |
| `guru` | Profil dan biodata guru |
| `kelas` | Daftar kelas aktif beserta wali kelasnya |
| `siswa` | Data diri murid |
| `tugas_hafalan` | Target/tugas hafalan harian yang diterbitkan guru |
| `setoran` | Rekaman audio hafalan siswa + hasil penilaian |
| `koreksi_detail` | Rincian nilai per kriteria (Kelancaran, Tajwid, Makhraj) |
| `perpanjangan_waktu` | Izin keterlambatan yang diberikan guru |
| `pengumuman` | Pengumuman dari koordinator ke wali murid |
| `notifikasi` | Riwayat notifikasi real-time untuk semua pengguna |

---

## 📖 Dokumentasi Pengguna

Panduan lengkap tersedia di folder `docs/`:

| File | Untuk Siapa |
|---|---|
| [`docs/CARA_MENJALANKAN.md`](docs/CARA_MENJALANKAN.md) | IT / Teknisi instalasi |
| [`docs/PANDUAN_ADMIN.md`](docs/PANDUAN_ADMIN.md) | Koordinator / Admin |
| [`docs/PANDUAN_GURU.md`](docs/PANDUAN_GURU.md) | Guru / Wali Kelas |
| [`docs/PANDUAN_WALI_MURID.md`](docs/PANDUAN_WALI_MURID.md) | Orang Tua / Wali Murid |

---

## 🤝 Kontribusi

Kontribusi sangat terbuka! Silakan:

1. Fork repositori ini
2. Buat branch fitur baru (`git checkout -b fitur/nama-fitur`)
3. Commit perubahan (`git commit -m 'feat: tambah fitur X'`)
4. Push ke branch (`git push origin fitur/nama-fitur`)
5. Buat Pull Request

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah [MIT License](LICENSE).

---

<div align="center">

Dibuat dengan ❤️ untuk KB/BA/TPA **"Restu 2"**

</div>
