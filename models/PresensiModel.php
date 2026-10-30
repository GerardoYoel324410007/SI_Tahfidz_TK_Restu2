<?php
/**
 * Model: PresensiTahfidz
 * Presensi khusus kelas tahfidz Senin-Kamis
 */

require_once __DIR__ . '/BaseModel.php';

class PresensiModel extends BaseModel {

    /**
     * Input atau update presensi satu siswa
     */
    public function upsert(array $data): int {
        $db = $this->getDb();
        $stmt = $db->prepare("
            INSERT INTO presensi_tahfidz (siswa_id, kelas_id, tanggal, hari, status, catatan, dicatat_oleh)
            VALUES (:siswa_id, :kelas_id, :tanggal, :hari, :status, :catatan, :dicatat_oleh)
            ON DUPLICATE KEY UPDATE
                status       = VALUES(status),
                catatan      = VALUES(catatan),
                dicatat_oleh = VALUES(dicatat_oleh),
                updated_at   = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':siswa_id'      => $data['siswa_id'],
            ':kelas_id'      => $data['kelas_id'],
            ':tanggal'       => $data['tanggal'],
            ':hari'          => $data['hari'],
            ':status'        => $data['status'],
            ':catatan'       => $data['catatan'] ?? null,
            ':dicatat_oleh'  => $data['dicatat_oleh'],
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Ambil presensi per kelas per tanggal
     */
    public function getByKelasAndTanggal(int $kelasId, string $tanggal): array {
        return $this->findAll("
            SELECT p.*, s.nama_lengkap, s.nama_panggilan, s.nis
            FROM presensi_tahfidz p
            JOIN siswa s ON s.id = p.siswa_id
            WHERE p.kelas_id = :kelas_id AND p.tanggal = :tanggal
            ORDER BY s.nama_lengkap
        ", [':kelas_id' => $kelasId, ':tanggal' => $tanggal]);
    }

    /**
     * Rekap kehadiran seluruh siswa di kelas dalam rentang tanggal
     * Hanya menghitung hari Senin-Kamis
     */
    public function getRekapKelas(int $kelasId, string $tanggalMulai, string $tanggalAkhir): array {
        return $this->findAll("
            SELECT 
                s.id AS siswa_id,
                s.nis,
                s.nama_lengkap,
                s.nama_panggilan,
                COUNT(CASE WHEN p.status = 'hadir' THEN 1 END) AS hadir,
                COUNT(CASE WHEN p.status = 'izin'  THEN 1 END) AS izin,
                COUNT(CASE WHEN p.status = 'sakit' THEN 1 END) AS sakit,
                COUNT(CASE WHEN p.status = 'alpha' THEN 1 END) AS alpha,
                COUNT(p.id) AS total_tercatat,
                ROUND(
                    (COUNT(CASE WHEN p.status = 'hadir' THEN 1 END) / NULLIF(COUNT(p.id), 0)) * 100, 1
                ) AS persentase_hadir
            FROM siswa s
            LEFT JOIN presensi_tahfidz p 
                ON p.siswa_id = s.id 
                AND p.tanggal BETWEEN :tgl_mulai AND :tgl_akhir
            WHERE s.kelas_id = :kelas_id AND s.is_active = 1
            GROUP BY s.id, s.nis, s.nama_lengkap, s.nama_panggilan
            ORDER BY persentase_hadir ASC, s.nama_lengkap ASC
        ", [
            ':kelas_id'  => $kelasId,
            ':tgl_mulai' => $tanggalMulai,
            ':tgl_akhir' => $tanggalAkhir
        ]);
    }

    /**
     * Rekap presensi satu siswa (untuk wali murid)
     */
    public function getRekapSiswa(int $siswaId, string $tanggalMulai, string $tanggalAkhir): array {
        $rekap = $this->findOne("
            SELECT 
                COUNT(CASE WHEN status = 'hadir' THEN 1 END) AS hadir,
                COUNT(CASE WHEN status = 'izin'  THEN 1 END) AS izin,
                COUNT(CASE WHEN status = 'sakit' THEN 1 END) AS sakit,
                COUNT(CASE WHEN status = 'alpha' THEN 1 END) AS alpha,
                COUNT(id) AS total_tercatat,
                ROUND(
                    (COUNT(CASE WHEN status = 'hadir' THEN 1 END) / NULLIF(COUNT(id), 0)) * 100, 1
                ) AS persentase_hadir
            FROM presensi_tahfidz
            WHERE siswa_id = :siswa_id
              AND tanggal BETWEEN :tgl_mulai AND :tgl_akhir
        ", [
            ':siswa_id'  => $siswaId,
            ':tgl_mulai' => $tanggalMulai,
            ':tgl_akhir' => $tanggalAkhir
        ]);

        $riwayat = $this->findAll("
            SELECT tanggal, hari, status, catatan
            FROM presensi_tahfidz
            WHERE siswa_id = :siswa_id
              AND tanggal BETWEEN :tgl_mulai AND :tgl_akhir
            ORDER BY tanggal DESC
        ", [
            ':siswa_id'  => $siswaId,
            ':tgl_mulai' => $tanggalMulai,
            ':tgl_akhir' => $tanggalAkhir
        ]);

        return [
            'rekap'   => $rekap,
            'riwayat' => $riwayat
        ];
    }

    /**
     * Ambil presensi hari ini untuk kelas tertentu (cek apakah sudah diinput)
     */
    public function isTanggalSudahDiinput(int $kelasId, string $tanggal): bool {
        $count = $this->findScalar("
            SELECT COUNT(*) FROM presensi_tahfidz
            WHERE kelas_id = :kelas_id AND tanggal = :tanggal
        ", [':kelas_id' => $kelasId, ':tanggal' => $tanggal], 0);
        return (int) $count > 0;
    }

    /**
     * Helper: konversi tanggal ke nama hari Indonesia
     * Hanya valid untuk Senin-Kamis (untuk validasi)
     */
    public static function getNamaHari(string $tanggal): ?string {
        $hari = date('N', strtotime($tanggal)); // 1=Senin, 7=Minggu
        return match((int)$hari) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            default => null // Jumat, Sabtu, Minggu tidak valid
        };
    }
}
