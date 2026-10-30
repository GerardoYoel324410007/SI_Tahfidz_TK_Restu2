# Panduan Penggunaan — Admin / Koordinator
## Sistem Informasi Tahfidz KB/BA/TPA "Restu 2"

---

## Data Login Admin

| Field | Nilai |
|---|---|
| **Nomor Identitas** | `000001` |
| **Password Default** | `admin123` |
| **URL Aplikasi** | `http://localhost/deploy_tahfidz/` |

> **Penting:** Segera ganti password setelah login pertama kali melalui menu Profil.

---

## A. Login ke Aplikasi

1. Buka browser dan akses URL aplikasi di atas.
2. Pada halaman login, masukkan **Nomor Identitas** `000001`.
3. Masukkan **Password** `admin123`.
4. Klik tombol **Masuk Aplikasi**.
5. Anda akan diarahkan ke **Dashboard Admin**.

---

## B. Dashboard — Ringkasan Keseluruhan

Setelah login, Anda dapat melihat:

- **Total Siswa Aktif** — jumlah seluruh siswa yang terdaftar dan aktif.
- **Persentase Setoran Hari Ini** — berapa persen siswa yang sudah mengumpulkan hafalan hari ini.
- **Jumlah Guru Aktif** — total guru yang terdaftar di sistem.
- **Jumlah Keterlambatan** — siswa yang belum mengumpulkan hafalan hingga kemarin.
- **Tabel Status Per Kelas** — ringkasan tiap kelas: jumlah siswa yang sudah/belum kumpul, diterima, atau perlu perbaikan.

---

## C. Manajemen Pengguna

Menu: **Manajemen Pengguna** (sidebar kiri)

### Menambah Guru Baru (Manual)

1. Klik tab **Daftar Guru**.
2. Klik tombol **Tambah Guru**.
3. Isi form:
   - Nama Lengkap
   - NUPTK / Nomor Identitas unik
   - Kelas yang diampu
   - No. Telepon
   - Password awal
4. Klik **Simpan**.

### Menambah Siswa Baru (Manual)

1. Klik tab **Daftar Siswa**.
2. Klik tombol **Tambah Siswa**.
3. Isi form:
   - Nama Lengkap
   - NIS (Nomor Induk Siswa) — ini sekaligus menjadi **nomor login** dan **password default** wali murid.
   - Kelas
   - Nama Orang Tua
4. Klik **Simpan**.

### Import Data Massal (Excel)

1. Klik tombol **Import Excel** (tersedia di tab Guru maupun Siswa).
2. Unduh template Excel yang tersedia.
3. Isi data sesuai format kolom pada template.
4. Pilih file Excel hasil isian, lalu klik **Unggah**.
5. Sistem akan memvalidasi dan mengimpor data secara otomatis.

### Edit / Nonaktifkan Pengguna

- Klik ikon **pensil (edit)** untuk mengubah data, mengganti password, atau memindahkan kelas.
- Klik ikon **tempat sampah** untuk menonaktifkan akun pengguna yang sudah tidak aktif.

---

## D. Manajemen Kelas

Menu: **Manajemen Kelas** (sidebar kiri)

1. Tambah kelas baru dengan klik **Tambah Kelas**.
2. Isi nama kelas, tingkat, dan tetapkan guru wali kelas.
3. Klik **Simpan**.
4. Untuk mengubah wali kelas, klik ikon edit pada kelas yang dituju.

---

## E. Manajemen Surat (Hafalan)

Menu: **Manajemen Surat** (sidebar kiri)

Kelola daftar surat Al-Quran yang tersedia dalam sistem sebagai pilihan saat guru membuat target hafalan.

1. Klik **Tambah Surat** untuk menambah surat baru.
2. Isi nama surat, jumlah ayat, dan urutan juz.
3. Klik **Simpan**.

---

## F. Monitor Kelas

Menu: **Monitor Kelas** (sidebar kiri)

1. Pilih **Nama Kelas** dari dropdown.
2. Pilih **Tanggal** yang ingin dipantau.
3. Sistem menampilkan daftar siswa beserta status setorannya:
   - **Belum Kumpul** — siswa belum mengirim rekaman.
   - **Menunggu** — rekaman sudah dikirim, belum dikoreksi guru.
   - **Diterima** — hafalan disetujui guru.
   - **Perbaiki** — hafalan perlu diulang.
4. Anda dapat mendengarkan audio rekaman siswa langsung dari halaman ini.

---

## G. Laporan

Menu: **Laporan** (sidebar kiri)

- **Rekap Harian** — melihat ringkasan aktivitas setoran seluruh kelas per hari.
- **Rekap Bulanan** — performa tiap kelas dan siswa dalam satu bulan.
- **Export Excel** — unduh data laporan dalam format Excel (.xlsx).

---

## H. Komando — Kirim Pengumuman

Menu: **Komando** (sidebar kiri)

1. Isi **Judul** dan **Isi Pengumuman**.
2. Pilih kelas target (bisa satu kelas, beberapa kelas, atau **Semua Kelas**).
3. Klik **Kirim Pengumuman**.
4. Notifikasi akan otomatis masuk ke akun wali murid di kelas yang dipilih.

---

## I. Log Login

Menu: **Log Login** (sidebar kiri)

Pantau riwayat login seluruh pengguna: siapa yang login, kapan, dan dari IP mana. Berguna untuk mendeteksi akses mencurigakan.

---

## J. Ganti Password Admin

1. Klik nama/foto profil di pojok kanan atas.
2. Pilih **Ganti Password**.
3. Masukkan password lama, password baru, dan konfirmasi password baru.
4. Klik **Simpan**.

---

*Panduan ini untuk peran Admin/Koordinator — Sistem Tahfidz KB/BA/TPA "Restu 2"*
