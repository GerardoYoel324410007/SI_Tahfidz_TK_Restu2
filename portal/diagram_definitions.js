const useCaseDiagramDefinition = `
flowchart LR
    %% Actors Configuration
    subgraph Aktor ["AKTOR SISTEM"]
        admin(((Super Admin)))
        guru(((Wali Kelas)))
        wali(((Wali Murid)))
    end

    %% System Boundary
    subgraph PortalTahfidz ["TAHFIDZ BA PORTAL SYSTEM"]
        %% Super Admin Use Cases
        uc_manage_guru(["Mengelola Data Guru"])
        uc_manage_siswa(["Mengelola Data Siswa"])
        uc_manage_kelas(["Mengelola Data Kelas"])
        uc_broadcast(["Mengirim Pengumuman"])
        uc_view_progres_admin(["Melihat Laporan Progres"])

        %% Wali Kelas Use Cases
        uc_manage_tugas(["Mengelola Target Hafalan"])
        uc_review_setoran(["Mengoreksi Setoran Siswa"])
        uc_perpanjang(["Mengelola Perpanjangan Waktu"])
        uc_view_progres_guru(["Melihat Progres Hafalan"])

        %% Wali Murid Use Cases
        uc_view_tugas(["Melihat Target Hafalan"])
        uc_upload(["Mengirim Setoran Audio"])
        uc_view_riwayat(["Melihat Riwayat Setoran"])
        uc_view_raport(["Melihat Laporan Bulanan"])
        uc_receive_notif(["Menerima Notifikasi"])
    end

    %% Relations for Super Admin
    admin --> uc_manage_guru
    admin --> uc_manage_siswa
    admin --> uc_manage_kelas
    admin --> uc_broadcast
    admin --> uc_view_progres_admin
    
    %% Relations for Wali Kelas
    guru --> uc_manage_tugas
    guru --> uc_review_setoran
    guru --> uc_perpanjang
    guru --> uc_view_progres_guru
    
    %% Relations for Wali Murid
    wali --> uc_view_tugas
    wali --> uc_upload
    wali --> uc_view_riwayat
    wali --> uc_view_raport
    wali --> uc_receive_notif
`;

const classDiagramDefinition = `
classDiagram
    direction TB

    class User {
        +int id
        +string nomor_identitas
        +string password_hash
        +string role
        +tinyint is_active
        +datetime last_login
        +timestamp created_at
        +login() boolean
        +logout() boolean
    }

    class Guru {
        +int id
        +int user_id
        +string nuptk
        +string nama_lengkap
        +string jabatan
        +string status
        +string pendidikan
        +string tempat_lahir
        +date tanggal_lahir
        +string no_telepon
        +text alamat
        +string foto_profil
    }

    class Siswa {
        +int id
        +int user_id
        +string nis
        +string nama_lengkap
        +string nama_panggilan
        +string jenis_kelamin
        +int kelas_id
        +string nama_wali
        +string foto_siswa
        +tinyint is_active
    }

    class Kelas {
        +int id
        +string kode_kelas
        +string nama_kelas
        +string jenjang
        +int guru_id
        +string tahun_ajaran
        +tinyint is_active
    }

    class TugasHafalan {
        +int id
        +int kelas_id
        +int guru_id
        +string nama_surat
        +int ayat_dari
        +int ayat_sampai
        +string audio_panduan
        +date tanggal_tugas
        +datetime deadline
        +string status_publikasi
    }

    class Setoran {
        +int id
        +int tugas_id
        +int siswa_id
        +string file_audio
        +datetime waktu_kirim
        +string status
        +text catatan_guru
        +datetime waktu_koreksi
        +tinyint is_terlambat
    }

    class PerpanjanganWaktu {
        +int id
        +int tugas_id
        +int siswa_id
        +int guru_id
        +datetime deadline_baru
        +text alasan
        +timestamp created_at
    }

    class KoreksiDetail {
        +int id
        +int setoran_id
        +string kriteria
        +int nilai
        +string catatan
    }

    class Pengumuman {
        +int id
        +string judul
        +text isi
        +int pengirim_id
        +json target_kelas
        +timestamp created_at
    }

    class Notifikasi {
        +int id
        +int user_id
        +string judul
        +text pesan
        +tinyint is_read
        +timestamp created_at
    }

    %% Relationships
    User "1" -- "0..1" Guru : memiliki profil
    User "1" -- "0..1" Siswa : memiliki profil (Wali Murid)
    User "1" -- "0..*" Notifikasi : menerima
    User "1" -- "0..*" Pengumuman : mengirim
    
    Guru "1" -- "0..*" Kelas : wali kelas
    Guru "1" -- "0..*" TugasHafalan : membuat
    Guru "1" -- "0..*" PerpanjanganWaktu : menyetujui
    
    Kelas "1" -- "0..*" Siswa : berisi
    Kelas "1" -- "0..*" TugasHafalan : ditargetkan
    
    Siswa "1" -- "0..*" Setoran : mengumpulkan
    Siswa "1" -- "0..*" PerpanjanganWaktu : mendapatkan
    
    TugasHafalan "1" -- "0..*" Setoran : memicu
    TugasHafalan "1" -- "0..*" PerpanjanganWaktu : diperpanjang
    
    Setoran "1" *-- "0..*" KoreksiDetail : memiliki rincian
`;
