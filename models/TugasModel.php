<?php
/**
 * Model: Tugas Hafalan
 * Target hafalan harian per kelas
 */

require_once __DIR__ . '/BaseModel.php';

class TugasModel extends BaseModel {

    public function create(array $data): int {
        return $this->insert('tugas_hafalan', $data);
    }

    public function getById(int $id): ?array {
        return $this->findOne("
            SELECT t.*, k.nama_kelas, k.kode_kelas, g.nama_lengkap AS nama_guru
            FROM tugas_hafalan t
            JOIN kelas k ON k.id = t.kelas_id
            JOIN guru g ON g.id = t.guru_id
            WHERE t.id = :id
        ", [':id' => $id]);
    }

    public function getByKelasHariIni(int $kelasId): ?array {
        return $this->findOne("
            SELECT t.*, k.nama_kelas, k.kode_kelas
            FROM tugas_hafalan t
            JOIN kelas k ON k.id = t.kelas_id
            WHERE t.kelas_id = :kelas_id
              AND t.tanggal_tugas = CURDATE()
              AND t.status_publikasi = 'published'
            ORDER BY t.id DESC
            LIMIT 1
        ", [':kelas_id' => $kelasId]);
    }

    public function getRiwayat(int $kelasId, int $hari = 7): array {
        return $this->findAll("
            SELECT t.*,
                   COUNT(DISTINCT s.id) AS total_siswa,
                   SUM(CASE WHEN st.status = 'diterima' THEN 1 ELSE 0 END) AS diterima,
                   SUM(CASE WHEN st.status = 'menunggu' THEN 1 ELSE 0 END) AS menunggu,
                   SUM(CASE WHEN st.status = 'perbaiki' THEN 1 ELSE 0 END) AS perbaiki
            FROM tugas_hafalan t
            LEFT JOIN siswa s ON s.kelas_id = t.kelas_id AND s.is_active = 1
            LEFT JOIN setoran st ON st.tugas_id = t.id AND st.siswa_id = s.id
            WHERE t.kelas_id = :kelas_id
              AND t.tanggal_tugas >= DATE_SUB(CURDATE(), INTERVAL :hari DAY)
              AND t.status_publikasi = 'published'
            GROUP BY t.id
            ORDER BY t.tanggal_tugas DESC, t.id DESC
        ", [':kelas_id' => $kelasId, ':hari' => $hari]);
    }

    public function updateStatus(int $id, string $status): int {
        return $this->update('tugas_hafalan', $id, ['status_publikasi' => $status]);
    }
}
