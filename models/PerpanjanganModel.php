<?php
/**
 * Model: Perpanjangan Waktu
 * Log perpanjangan deadline setoran
 */

require_once __DIR__ . '/BaseModel.php';

class PerpanjanganModel extends BaseModel {

    public function create(array $data): int {
        return $this->insert('perpanjangan_waktu', $data);
    }

    public function getAktif(int $tugasId, int $siswaId): ?array {
        return $this->findOne("
            SELECT * FROM perpanjangan_waktu
            WHERE tugas_id = :tugas_id AND siswa_id = :siswa_id
              AND deadline_baru > NOW()
            ORDER BY id DESC LIMIT 1
        ", [':tugas_id' => $tugasId, ':siswa_id' => $siswaId]);
    }

    public function getByTugas(int $tugasId): array {
        return $this->findAll("
            SELECT p.*, s.nama_lengkap AS nama_siswa, s.nis
            FROM perpanjangan_waktu p
            JOIN siswa s ON s.id = p.siswa_id
            WHERE p.tugas_id = :tugas_id
            ORDER BY p.created_at DESC
        ", [':tugas_id' => $tugasId]);
    }
}
