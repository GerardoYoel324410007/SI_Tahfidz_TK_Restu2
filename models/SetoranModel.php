<?php
/**
 * Model: Setoran
 * Tabel inti transaksi hafalan
 */

require_once __DIR__ . '/BaseModel.php';

class SetoranModel extends BaseModel {

    public function create(array $data): int {
        return $this->insert('setoran', $data);
    }

    public function updateSetoran(int $id, array $data): int {
        return parent::update('setoran', $id, $data);
    }

    public function getById(int $id): ?array {
        return $this->findOne("
            SELECT st.*, s.nama_lengkap AS nama_siswa, s.nis, s.nama_panggilan,
                   s.foto_siswa, s.user_id AS siswa_user_id,
                   t.nama_surat, t.bab, t.ayat_dari, t.ayat_sampai, t.audio_panduan,
                   t.tanggal_tugas, t.deadline, t.kelas_id,
                   k.nama_kelas, k.kode_kelas
            FROM setoran st
            JOIN siswa s ON s.id = st.siswa_id
            JOIN tugas_hafalan t ON t.id = st.tugas_id
            JOIN kelas k ON k.id = t.kelas_id
            WHERE st.id = :id
        ", [':id' => $id]);
    }

    public function getByTugasSiswa(int $tugasId, int $siswaId): ?array {
        return $this->findOne("
            SELECT * FROM setoran
            WHERE tugas_id = :tugas_id AND siswa_id = :siswa_id
            ORDER BY id DESC LIMIT 1
        ", [':tugas_id' => $tugasId, ':siswa_id' => $siswaId]);
    }

    public function updateKoreksi(int $id, string $status, ?string $catatan, ?int $nilaiPoin = null, ?string $nilaiLabel = null): int {
        $data = [
            'status'        => $status,
            'catatan_guru'  => $catatan,
            'waktu_koreksi' => date('Y-m-d H:i:s')
        ];
        if ($nilaiPoin !== null) $data['nilai_poin']  = $nilaiPoin;
        if ($nilaiLabel !== null) $data['nilai_label'] = $nilaiLabel;
        return $this->update('setoran', $id, $data);
    }

    public function updateBalasan(int $id, string $balasan): int {
        return $this->update('setoran', $id, ['balasan_wali' => $balasan]);
    }

    public function getAntrian(int $kelasId): array {
        return $this->findAll("
            SELECT st.*, s.nama_lengkap AS nama_siswa, s.nis, s.nama_panggilan,
                   s.foto_siswa, t.nama_surat, t.bab, t.ayat_dari, t.ayat_sampai
            FROM setoran st
            JOIN siswa s ON s.id = st.siswa_id
            JOIN tugas_hafalan t ON t.id = st.tugas_id
            WHERE t.kelas_id = :kelas_id
              AND st.status = 'menunggu'
            ORDER BY st.waktu_kirim ASC
        ", [':kelas_id' => $kelasId]);
    }

    public function getBerikutnya(int $kelasId, int $setoranId): ?array {
        return $this->findOne("
            SELECT st.*, s.nama_lengkap AS nama_siswa, s.nis, s.nama_panggilan,
                   s.foto_siswa, t.nama_surat, t.bab, t.ayat_dari, t.ayat_sampai
            FROM setoran st
            JOIN siswa s ON s.id = st.siswa_id
            JOIN tugas_hafalan t ON t.id = st.tugas_id
            WHERE t.kelas_id = :kelas_id
              AND st.status = 'menunggu'
              AND st.id > :setoran_id
            ORDER BY st.waktu_kirim ASC
            LIMIT 1
        ", [':kelas_id' => $kelasId, ':setoran_id' => $setoranId]);
    }

    public function getStatistikHariIni(int $kelasId): array {
        $siswaModel = new SiswaModel();
        $totalSiswa = $siswaModel->countByKelas($kelasId);

        $stats = $this->findOne("
            SELECT
                SUM(CASE WHEN st.status = 'diterima' THEN 1 ELSE 0 END) AS diterima,
                SUM(CASE WHEN st.status = 'menunggu' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN st.status = 'perbaiki' THEN 1 ELSE 0 END) AS perbaiki,
                COUNT(st.id) AS sudah_kumpul
            FROM setoran st
            JOIN tugas_hafalan t ON t.id = st.tugas_id
            WHERE t.kelas_id = :kelas_id
              AND t.tanggal_tugas = CURDATE()
        ", [':kelas_id' => $kelasId]);

        return [
            'total_siswa' => $totalSiswa,
            'diterima' => (int) ($stats['diterima'] ?? 0),
            'menunggu' => (int) ($stats['menunggu'] ?? 0),
            'perbaiki' => (int) ($stats['perbaiki'] ?? 0),
            'sudah_kumpul' => (int) ($stats['sudah_kumpul'] ?? 0),
            'belum_kumpul' => $totalSiswa - (int) ($stats['sudah_kumpul'] ?? 0)
        ];
    }

    public function getBySiswaHariIni(int $siswaId): ?array {
        return $this->findOne("
            SELECT st.*, t.nama_surat, t.bab, t.ayat_dari, t.ayat_sampai, t.deadline
            FROM setoran st
            JOIN tugas_hafalan t ON t.id = st.tugas_id
            WHERE st.siswa_id = :siswa_id
              AND t.tanggal_tugas = CURDATE()
            ORDER BY st.id DESC
            LIMIT 1
        ", [':siswa_id' => $siswaId]);
    }

    public function getRaportBulanan(int $siswaId, int $bulan, int $tahun): array {
        $ringkasan = $this->findOne("
            SELECT
                COUNT(st.id) AS total_setoran,
                SUM(CASE WHEN st.status = 'diterima' THEN 1 ELSE 0 END) AS diterima,
                SUM(CASE WHEN st.status = 'menunggu' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN st.status = 'perbaiki' THEN 1 ELSE 0 END) AS perbaiki
            FROM setoran st
            JOIN tugas_hafalan t ON t.id = st.tugas_id
            WHERE st.siswa_id = :siswa_id
              AND MONTH(t.tanggal_tugas) = :bulan
              AND YEAR(t.tanggal_tugas) = :tahun
        ", [':siswa_id' => $siswaId, ':bulan' => $bulan, ':tahun' => $tahun]);

        $riwayat = $this->findAll("
            SELECT st.id AS setoran_id, st.status, st.waktu_kirim, st.waktu_koreksi,
                   st.catatan_guru, st.balasan_wali, st.nilai_poin, st.nilai_label,
                   t.nama_surat, t.bab, t.ayat_dari, t.ayat_sampai, t.tanggal_tugas
            FROM setoran st
            JOIN tugas_hafalan t ON t.id = st.tugas_id
            WHERE st.siswa_id = :siswa_id
              AND MONTH(t.tanggal_tugas) = :bulan
              AND YEAR(t.tanggal_tugas) = :tahun
            ORDER BY t.tanggal_tugas DESC
        ", [':siswa_id' => $siswaId, ':bulan' => $bulan, ':tahun' => $tahun]);

        $total = (int) ($ringkasan['total_setoran'] ?? 0);
        $diterima = (int) ($ringkasan['diterima'] ?? 0);

        return [
            'total_setoran' => $total,
            'diterima' => $diterima,
            'menunggu' => (int) ($ringkasan['menunggu'] ?? 0),
            'perbaiki' => (int) ($ringkasan['perbaiki'] ?? 0),
            'persentase_berhasil' => $total > 0 ? round(($diterima / $total) * 100, 1) : 0,
            'riwayat' => $riwayat
        ];
    }

    public function countKeterlambatanKemarin(): int {
        return (int) $this->findScalar("
            SELECT COUNT(*) FROM setoran
            WHERE is_terlambat = 1
              AND DATE(waktu_kirim) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ", [], 0);
    }

    public function getDashboardStatsAll(): array {
        return $this->findAll("
            SELECT k.id AS kelas_id, k.kode_kelas, k.nama_kelas,
                   g.nama_lengkap AS nama_guru,
                   COUNT(DISTINCT s.id) AS total_siswa,
                   SUM(CASE WHEN st.status = 'diterima' THEN 1 ELSE 0 END) AS diterima,
                   SUM(CASE WHEN st.status = 'menunggu' THEN 1 ELSE 0 END) AS menunggu,
                   SUM(CASE WHEN st.status = 'perbaiki' THEN 1 ELSE 0 END) AS perbaiki,
                   SUM(CASE WHEN st.id IS NOT NULL THEN 1 ELSE 0 END) AS sudah_kumpul,
                   COUNT(DISTINCT s.id) - SUM(CASE WHEN st.id IS NOT NULL THEN 1 ELSE 0 END) AS belum_kumpul
            FROM kelas k
            LEFT JOIN guru g ON g.id = k.guru_id
            LEFT JOIN siswa s ON s.kelas_id = k.id AND s.is_active = 1
            LEFT JOIN tugas_hafalan t ON t.kelas_id = k.id AND t.tanggal_tugas = CURDATE() AND t.status_publikasi = 'published'
            LEFT JOIN setoran st ON st.tugas_id = t.id AND st.siswa_id = s.id
            WHERE k.is_active = 1
            GROUP BY k.id
            ORDER BY k.kode_kelas
        ");
    }

    public function getMonitorKelas(int $kelasId, ?string $tanggal = null): array {
        $tanggal = $tanggal ?: date('Y-m-d');

        return $this->findAll("
            SELECT s.id AS siswa_id, s.nis, s.nama_lengkap, s.nama_panggilan, s.foto_siswa,
                   st.id AS setoran_id, st.file_audio, st.waktu_kirim, st.status,
                   st.catatan_guru, st.waktu_koreksi,
                   t.nama_surat, t.ayat_dari, t.ayat_sampai
            FROM siswa s
            LEFT JOIN tugas_hafalan t ON t.kelas_id = s.kelas_id
                AND t.tanggal_tugas = :tanggal AND t.status_publikasi = 'published'
            LEFT JOIN setoran st ON st.tugas_id = t.id AND st.siswa_id = s.id
            WHERE s.kelas_id = :kelas_id AND s.is_active = 1
            ORDER BY s.nama_lengkap
        ", [':kelas_id' => $kelasId, ':tanggal' => $tanggal]);
    }

    public function getAllAudioHistory(int $siswaId): array {
        return $this->findAll("
            SELECT st.id AS setoran_id, st.status, st.waktu_kirim, st.waktu_koreksi,
                   st.catatan_guru, st.balasan_wali, st.file_audio,
                   t.nama_surat, t.bab, t.ayat_dari, t.ayat_sampai, t.tanggal_tugas
            FROM setoran st
            JOIN tugas_hafalan t ON t.id = st.tugas_id
            WHERE st.siswa_id = :siswa_id
            ORDER BY t.tanggal_tugas DESC
        ", [':siswa_id' => $siswaId]);
    }
}
