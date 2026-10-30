# Database Schema - Sistem Informasi Tahfidz KB/BA/TPA "Restu 2"

## Ringkasan

- **Total Tabel**: 10 tabel utama + 3 views
- **Engine**: InnoDB (support foreign key & transaction)
- **Charset**: utf8mb4 (support karakter Arab untuk ayat Al-Quran)
- **MySQL Version**: 8.x

## Diagram Relasi (ERD Ringkas)

```
users (1) ──── (1) guru (1) ──── (N) kelas (1) ──── (N) siswa
  │                  │                                     │
  │                  │                                     │
  │                  ├─── (N) tugas_hafalan ─── (N) setoran ┘
  │                  │              │                │
  │                  │              │                ├── koreksi_detail
  │                  │              │                │
  │                  │              └── perpanjangan_waktu
  │                  │
  ├─── pengumuman
  │
  └─── notifikasi
```

## Penjelasan Per Tabel

### 1. `users` — Akun Login
Menyimpan akun login untuk semua pengguna. Kolom `role` membedakan hak akses:
- `admin` — Koordinator/Administrator
- `guru` — Wali Kelas (Guru Tahfidz)
- `wali_murid` — Orang Tua Siswa

**Relasi:**
- 1 user → 1 guru (jika role=guru)
- 1 user → 1 siswa (jika role=wali_murid)

### 2. `guru` — Profil Guru
Data lengkap guru yang diimpor dari `data_guru_semua.xlsx` (19 record).
Setiap guru punya akun login di tabel `users`.

**Relasi:**
- guru → users (FK user_id)
- guru → kelas (sebagai wali kelas, 1 guru bisa jadi wali 1 kelas)
- guru → tugas_hafalan (sebagai pembuat tugas)

### 3. `kelas` — Daftar Kelas
13 kelas aktif: KB 1-4, TKA 1-4, TKB 1-4, + TPA.

**Relasi:**
- kelas → guru (FK guru_id sebagai wali kelas)
- kelas → siswa (1 kelas punya banyak siswa)
- kelas → tugas_hafalan (1 kelas punya banyak tugas)

### 4. `siswa` — Profil Siswa
Data lengkap siswa yang diimpor dari `data_siswa_agatha.xlsx` (265 record).
Setiap siswa punya 1 akun wali murid di tabel `users`.

**Relasi:**
- siswa → users (FK user_id, akun wali murid)
- siswa → kelas (FK kelas_id)
- siswa → setoran (1 siswa punya banyak setoran)

### 5. `tugas_hafalan` — Target Hafalan Harian
Tugas yang dibuat oleh guru wali kelas. Setiap tugas berlaku untuk 1 kelas pada tanggal tertentu.

**Relasi:**
- tugas_hafalan → kelas (FK kelas_id)
- tugas_hafalan → guru (FK guru_id)
- tugas_hafalan → setoran (1 tugas bisa punya banyak setoran)
- tugas_hafalan → perpanjangan_waktu (1 tugas bisa punya banyak perpanjangan)

### 6. `setoran` — TABEL INTI (Core Transaction)
Setiap kiriman audio hafalan dari wali murid. Ini adalah tabel transaksi utama sistem.

**Status Flow:**
```
[Belum Kumpul] → [Menunggu] → [Diterima]
                             → [Perbaiki] → [Menunggu] (resubmit)
```

**Relasi:**
- setoran → tugas_hafalan (FK tugas_id)
- setoran → siswa (FK siswa_id)
- setoran → koreksi_detail (1 setoran bisa punya banyak detail kriteria)

### 7. `perpanjangan_waktu` — Log Perpanjangan Deadline
Catatan ketika guru memberikan perpanjangan waktu kepada siswa tertentu (fitur Buka Kunci Deadline).

### 8. `pengumuman` — Pesan Global
Pengumuman dari Admin yang dikirim ke beberapa kelas sekaligus.
Kolom `target_kelas` menggunakan JSON array: `[1,2,3]` atau `"all"`.

### 9. `notifikasi` — Notifikasi Real-time
Notifikasi yang dikirim ke pengguna saat:
- Status setoran berubah (diterima/perbaiki)
- Ada pengumuman baru
- Ada tugas baru dari guru

### 10. `koreksi_detail` — Detail Penilaian
Kriteria penilaian koreksi per setoran:
- Kelancaran (Hafdz)
- Tajwid & Makhraj
- Kriteria lain yang bisa dikustomisasi

## Data Awal yang Diimport

### Dari `data_guru_semua.xlsx` → Tabel `guru` + `users`
| Kolom Excel | Kolom DB |
|---|---|
| NAMA | guru.nama_lengkap |
| STATUS | guru.status |
| NUPTK | guru.nuptk + users.nomor_identitas |
| Tempat, Tgl Lahir | guru.tempat_lahir + guru.tanggal_lahir |
| Pendidikan | guru.pendidikan |
| Jabatan | guru.jabatan |
| Alamat Rumah | guru.alamat |
| No Telp | guru.no_telepon |

### Dari `data_siswa_agatha.xlsx` → Tabel `siswa` + `users`
| Kolom Excel | Kolom DB |
|---|---|
| No Induk | siswa.nis + users.nomor_identitas |
| Nama Siswa | siswa.nama_lengkap |
| Nama Panggilan | siswa.nama_panggilan |
| L/P | siswa.jenis_kelamin |
| Header Baris Kelas | siswa.kelas_id |

## Views (Query Helper)

1. **v_statistik_setoran_hari_ini** — Statistik setoran per kelas hari ini
2. **v_antrean_koreksi** — Daftar setoran menunggu koreksi untuk guru
3. **v_raport_bulanan** — Ringkasan raport bulanan per siswa
