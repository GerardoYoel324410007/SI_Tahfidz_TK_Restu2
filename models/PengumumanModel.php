<?php
/**
 * Model: Pengumuman
 * Pesan/komando dari Admin
 */

require_once __DIR__ . '/BaseModel.php';

class PengumumanModel extends BaseModel {

    public function create(array $data): int {
        return $this->insert('pengumuman', $data);
    }

    public function getAll(int $limit = 20): array {
        $limit = (int) $limit;
        return $this->findAll("
            SELECT p.*, u.nomor_identitas AS pengirim_nomor
            FROM pengumuman p
            JOIN users u ON u.id = p.pengirim_id
            ORDER BY p.created_at DESC
            LIMIT {$limit}
        ");
    }

    public function getByKelas(int $kelasId, int $limit = 5): array {
        $limit = (int) $limit;
        return $this->findAll("
            SELECT p.*
            FROM pengumuman p
            WHERE JSON_CONTAINS(p.target_kelas, :kelas_id)
               OR p.target_kelas = '\"all\"'
            ORDER BY p.created_at DESC
            LIMIT {$limit}
        ", [':kelas_id' => (string) $kelasId]);
    }
}
